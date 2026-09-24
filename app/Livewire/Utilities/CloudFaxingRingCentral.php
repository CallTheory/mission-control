<?php

namespace App\Livewire\Utilities;

use App\Console\Commands\ISFaxing\BuildRingCentralFaxDashboard;
use App\Livewire\Concerns\ManagesFaxSpool;
use App\Livewire\Concerns\ShowsFaxFailures;
use App\Models\DataSource;
use App\Models\FaxSpoolSource;
use App\Services\Faxing\FaxDashboardSnapshot;
use App\Services\Faxing\FaxSpool;
use App\Services\Faxing\RingCentralClient;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use JsonException;
use Livewire\Component;
use RingCentral\SDK\Http\ApiException;

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

    public ?string $faxIdToSend;

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

    /**
     * @throws ApiException
     */
    public function resendFaxAction(): Action
    {
        return Action::make('resendFax')
            ->label(__('Resend Fax'))
            ->color('danger')
            ->modalHeading(__('Resend Fax'))
            ->modalSubmitActionLabel(__('Resend'))
            // Prefilling calls the provider for the fax's current recipient, which is
            // what openSendFaxDialog() did when the old dialog was toggled open.
            ->fillForm(function (array $arguments): array {
                $this->openSendFaxDialog($arguments['messageId'] ?? null);

                return ['faxNumber' => $this->state['faxInfo']['faxNumber'] ?? ''];
            })
            ->schema([
                TextInput::make('faxNumber')
                    ->label(__('Recipient Fax Number'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->state['faxInfo']['faxNumber'] = $data['faxNumber'];
                $this->resendFax();
            });
    }

    public function openSendFaxDialog($messageId): void
    {
        try {
            // Reuses the shared access token rather than performing its own JWT login;
            // looking at a fax used to cost two token requests before it even resent.
            $platform = $this->ringCentral()->platform();
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return;
        }

        try {
            $endpoint = '/restapi/v1.0/account/~/extension/~/message-store/'.$messageId;
            $resp = $platform->get($endpoint);
            $this->state['faxInfo'] = $resp->jsonArray();

        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }

        $this->faxIdToSend = $messageId;

    }

    /**
     * @throws ApiException
     */
    public function resendFax(): void
    {
        if ($this->faxIdToSend === null) {
            $this->faxIdToSend = null;

            return;
        }

        $client = $this->ringCentral();

        try {
            $rcsdk = $client->sdk();
            // Already authenticated by sdk(); calling $client->platform() would repeat it.
            $platform = $rcsdk->platform();
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return;
        }
        try {
            $bodyParams = $rcsdk->createMultipartBuilder()
                ->setBody([
                    'originalMessageId' => $this->faxIdToSend,
                ])
                ->request('/restapi/v1.0/account/~/extension/~/fax');

            $resp = $platform->sendRequest($bodyParams);

        } catch (Exception $e) {
            if (App::environment('local')) {
                throw $e;
            }
            Log::error($e->getMessage());
        }

        $this->dispatch('resendFax', $this->faxIdToSend);
        $this->faxIdToSend = null;
        $this->redirect('/utilities/cloud-faxing/ringcentral');
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

    private function ringCentral(): RingCentralClient
    {
        return new RingCentralClient($this->datasource);
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
