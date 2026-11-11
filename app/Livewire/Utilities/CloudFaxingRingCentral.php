<?php

namespace App\Livewire\Utilities;

use App\Console\Commands\ISFaxing\BuildRingCentralFaxDashboard;
use App\Livewire\Concerns\ManagesFaxSpool;
use App\Livewire\Concerns\ShowsFaxFailures;
use App\Models\DataSource;
use App\Models\FaxSpoolSource;
use App\Models\PendingFax;
use App\Services\Faxing\FaxDashboardSnapshot;
use App\Services\Faxing\FaxSpool;
use App\Services\Faxing\RingCentralClient;
use App\Services\Faxing\RingCentralRecipient;
use App\Services\Faxing\RingCentralSenderPool;
use App\Services\Faxing\RingCentralThrottle;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use JsonException;
use Livewire\Component;

class CloudFaxingRingCentral extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use ManagesFaxSpool;
    use ShowsFaxFailures;

    /**
     * Which spool source this page is showing. Several Intelligent Series servers can
     * feed the same provider, and each has its own folders.
     */
    public string $sourceKey = 'ringcentral';

    public array $tags = [];

    public array $state = [];

    public mixed $datasource;

    public ?string $faxIdToSend = null;

    protected $listeners = ['faxResent'];

    public function faxResent($faxMessageId): void
    {
        $this->state['success_message'][$faxMessageId] = true;
    }

    public function mount(?string $source = null): void
    {
        $this->datasource = DataSource::firstOrFail();
        $this->sourceKey = FaxSpoolSource::resolveKey($source, 'ringcentral');
        $this->state['ringcentral_failed_faxes'] = [];
        $this->state['files_to_send'] = [];
        $this->state['files_in_sent'] = [];
        $this->state['files_in_fail'] = [];
        $this->state['files_in_pre'] = [];

        $this->state['files_to_send_count'] = 0;
        $this->state['files_in_sent_count'] = 0;
        $this->state['files_in_fail_count'] = 0;
        $this->state['files_in_pre_count'] = 0;
        $this->state['generated_at'] = null;
        $this->state['webhook_last_received_at'] = null;

        // Populate immediately from the cached snapshot so the (lazy-loaded) page paints
        // with data on first render instead of waiting for the first poll.
        $this->updateFaxData();
    }

    public function resendFaxAction(): Action
    {
        return Action::make('resendFax')
            ->label(__('Resend Fax'))
            ->color('danger')
            ->modalHeading(__('Resend Fax'))
            ->modalSubmitActionLabel(__('Resend'))
            // Prefilled with the number the fax went to. The Filament port made this field
            // required but never filled it: it read faxInfo.faxNumber, a key RingCentral's
            // message record does not have.
            ->fillForm(function (array $arguments): array {
                $messageId = (string) ($arguments['messageId'] ?? '');
                $this->faxIdToSend = $messageId !== '' ? $messageId : null;

                return ['faxNumber' => $this->faxIdToSend === null ? '' : ($this->originalRecipient($this->faxIdToSend) ?? '')];
            })
            ->schema([
                TextInput::make('faxNumber')
                    ->label(__('Recipient Fax Number'))
                    ->helperText(__('Change it to send the fax somewhere else. U.S. and Canadian numbers may be entered as 10 digits.'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->resendFax((string) ($data['faxNumber'] ?? ''));
            });
    }

    /**
     * The number a fax was sent to.
     *
     * Taken from the dashboard snapshot the page already renders from, so opening the
     * dialog costs no RingCentral request; only a fax missing from the snapshot is
     * looked up. Read on the server, never from the browser.
     */
    private function originalRecipient(string $messageId): ?string
    {
        foreach ($this->providerSnapshot()['failed_faxes'] ?? [] as $record) {
            if (is_array($record) && (string) ($record['id'] ?? '') === $messageId) {
                $number = $record['to'][0]['phoneNumber'] ?? null;

                return is_string($number) && $number !== '' ? $number : null;
            }
        }

        try {
            $record = $this->ringCentral($messageId)->platform()
                ->get('/restapi/v1.0/account/~/extension/~/message-store/'.$messageId)
                ->jsonArray();
        } catch (Exception $e) {
            Log::error('CloudFaxingRingCentral: could not look up fax '.$messageId.': '.$e->getMessage());

            return null;
        }

        $number = $record['to'][0]['phoneNumber'] ?? null;

        return is_string($number) && $number !== '' ? $number : null;
    }

    /**
     * Ask RingCentral to send a fax it already holds again.
     *
     * Unchanged, this is a plain resend to the original recipient. A changed number used
     * to be silently ignored, because the request only ever carried originalMessageId;
     * it now goes out as the recipient.
     */
    public function resendFax(string $faxNumber = ''): void
    {
        if ($this->faxIdToSend === null) {
            return;
        }

        $messageId = $this->faxIdToSend;
        $original = $this->originalRecipient($messageId);
        $requested = $this->e164($faxNumber);
        $body = ['originalMessageId' => $messageId];

        if ($requested !== null && $requested !== $this->e164((string) $original)) {
            $body['to'] = [['phoneNumber' => $requested]];
        }

        $client = $this->ringCentral($messageId);

        try {
            $rcsdk = $client->sdk();
            // Already authenticated by sdk(); calling $client->platform() would repeat it.
            $rcsdk->platform()->sendRequest(
                $rcsdk->createMultipartBuilder()
                    ->setBody($body)
                    ->request('/restapi/v1.0/account/~/extension/~/fax')
            );
        } catch (Exception $e) {
            if (App::environment('local')) {
                throw $e;
            }

            Log::error('CloudFaxingRingCentral: resend failed: '.RingCentralThrottle::describe($e), ['message_id' => $messageId]);

            // This used to be logged and then reported as a success.
            Notification::make()
                ->title(__('RingCentral did not resend the fax'))
                ->body(RingCentralThrottle::describe($e))
                ->danger()
                ->send();

            $this->faxIdToSend = null;

            return;
        }

        Log::info('CloudFaxingRingCentral: fax resent', [
            'message_id' => $messageId,
            'to' => $body['to'][0]['phoneNumber'] ?? $original,
            'changed_recipient' => isset($body['to']),
        ]);

        Notification::make()
            ->title(__('Fax resent to :number', ['number' => $body['to'][0]['phoneNumber'] ?? $original ?? __('its original recipient')]))
            ->success()
            ->send();

        $this->dispatch('resendFax', $messageId);
        $this->faxIdToSend = null;
        $this->redirect('/utilities/cloud-faxing/ringcentral');
    }

    private function e164(string $number): ?string
    {
        $number = preg_replace('/[^\d+]/', '', trim($number)) ?? '';

        return $number === '' ? null : RingCentralRecipient::normalize($number);
    }

    /**
     * Render the page from the shared snapshot built by isfax:build-ringcentral-dashboard.
     * This is a cheap Redis read — no RingCentral API call or filesystem scan happens in
     * the request, so every viewer sees the same data and the page loads instantly.
     */
    public function updateFaxData(): void
    {
        // The spool half is per source; the provider half (the RingCentral fax list and
        // the webhook heartbeat) is account-level and shared by every source, because a
        // provider callback carries no notion of which IS server produced the fax.
        $data = app(FaxDashboardSnapshot::class)->read($this->sourceKey) ?? [];
        $providerData = $this->providerSnapshot();

        if ($data === [] && $providerData === []) {
            // Nothing built yet; keep the current/default state rather than blanking.
            return;
        }

        $this->state['ringcentral_failed_faxes'] = $providerData['failed_faxes'] ?? [];
        $this->state['unreachable'] = $data['unreachable'] ?? false;
        // normalizeListing() so a snapshot written by the previous version of the builder
        // — plain filename strings, still in Redis until its TTL expires after a deploy —
        // renders instead of breaking the page.
        $this->state['files_to_send'] = FaxSpool::normalizeListing($data['files_to_send'] ?? []);
        $this->state['files_in_sent'] = FaxSpool::normalizeListing($data['files_in_sent'] ?? []);
        $this->state['files_in_fail'] = FaxSpool::normalizeListing($data['files_in_fail'] ?? []);
        $this->state['files_in_pre'] = FaxSpool::normalizeListing($data['files_in_pre'] ?? []);
        $this->state['files_to_send_count'] = $data['files_to_send_count'] ?? 0;
        $this->state['files_in_sent_count'] = $data['files_in_sent_count'] ?? 0;
        $this->state['files_in_fail_count'] = $data['files_in_fail_count'] ?? 0;
        $this->state['files_in_pre_count'] = $data['files_in_pre_count'] ?? 0;
        $this->state['generated_at'] = $data['generated_at'] ?? null;
        $this->state['webhook_last_received_at'] = $providerData['webhook_last_received_at'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function providerSnapshot(): array
    {
        $cached = Redis::get(BuildRingCentralFaxDashboard::DASHBOARD_CACHE_KEY);

        if ($cached === null) {
            return [];
        }

        try {
            $decoded = json_decode($cached, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error('CloudFaxingRingCentral: invalid provider snapshot: '.$e->getMessage());

            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    protected function faxSource(): string
    {
        return $this->sourceKey;
    }

    /**
     * A client authenticated as the extension that sent this fax.
     *
     * A fax lives in the sending extension's message store, so viewing or resending one
     * sent through an additional extension has to authenticate as that extension. The
     * sender is worked out here from our own records, never taken from the browser.
     */
    private function ringCentral(?string $messageId = null): RingCentralClient
    {
        $pool = app(RingCentralSenderPool::class);
        $extension = $messageId !== null ? $pool->find($this->datasource, $this->senderForMessage($messageId)) : null;

        return $extension !== null
            ? RingCentralClient::forExtension($this->datasource, $extension)
            : new RingCentralClient($this->datasource);
    }

    private function senderForMessage(string $messageId): ?string
    {
        try {
            $sender = PendingFax::query()
                ->where('fax_provider', 'ringcentral')
                ->where('api_fax_id', $messageId)
                ->value('ringcentral_sender');

            if (filled($sender)) {
                return (string) $sender;
            }
        } catch (QueryException) {
            // Column not migrated yet: every fax was sent by the primary.
            return null;
        }

        // Faxes sent outside Mission Control have no row; the dashboard snapshot tagged
        // each record with the extension whose message store it came from.
        foreach ($this->providerSnapshot()['failed_faxes'] ?? [] as $record) {
            if (is_array($record) && (string) ($record['id'] ?? '') === $messageId) {
                return $record['mc_sender'] ?? null;
            }
        }

        return null;
    }

    protected function faxProvider(): string
    {
        return 'ringcentral';
    }

    /**
     * Re-scan the spool folders after a deletion and write the result straight back into
     * the shared snapshot.
     *
     * The page normally renders from the snapshot the scheduler builds each minute, so
     * without this a deleted file would keep appearing for up to a minute — for every
     * viewer, not just the one who deleted it.
     */
    /**
     * Re-read the spool after a deletion and write the result straight back into the
     * shared snapshot.
     *
     * The page normally renders from the snapshot the scheduler builds each minute, so
     * without this a deleted file would keep appearing for up to a minute — for every
     * viewer, not just the one who deleted it. This is a synchronous read of the spool,
     * but only ever in response to an operator explicitly asking for it.
     */
    protected function refreshFaxSpoolState(): void
    {
        $source = FaxSpoolSource::findByKey($this->sourceKey);

        if ($source !== null) {
            app(FaxDashboardSnapshot::class)->build($source);
        }

        $this->updateFaxData();
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mx-2 text-sm">
           Loading page content...one moment, please.
        </div>
        HTML;
    }

    public function render(): View
    {
        return view('livewire.utilities.cloud-faxing-ring-central');
    }
}
