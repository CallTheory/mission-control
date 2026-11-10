<?php

namespace App\Livewire\Utilities;

use App\Livewire\Concerns\ManagesFaxSpool;
use App\Livewire\Concerns\ShowsFaxFailures;
use App\Models\DataSource;
use App\Models\FaxSpoolSource;
use App\Services\Faxing\FaxDashboardSnapshot;
use App\Services\Faxing\FaxDeliveryWebhooks;
use App\Services\Faxing\FaxSpool;
use App\Services\Observability\GuzzleTracing;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use JsonException;
use Livewire\Component;

class CloudFaxing extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use ManagesFaxSpool;
    use ShowsFaxFailures;

    /**
     * Which spool source this page is showing. Several Intelligent Series servers can
     * feed the same provider, and each has its own folders.
     */
    public string $sourceKey = 'mfax';

    private $guzzle;

    public array $tags = [];

    public array $state = [];

    public DataSource $datasource;

    public ?string $faxIdToSend = null;

    protected $listeners = ['faxResent'];

    public function faxResent($faxMessageId): void
    {
        $this->state['success_message'][$faxMessageId] = true;
    }

    public function mount(?string $source = null): void
    {
        $this->datasource = DataSource::firstOrFail();
        $this->sourceKey = FaxSpoolSource::resolveKey($source, 'mfax');
        $this->updateFaxData();
    }

    /**
     * Test hook: when bound, the mFax HTTP client used by the resend dialog.
     */
    public const GUZZLE_BINDING = 'faxing.mfax.guzzle';

    public function resendFaxAction(): Action
    {
        return Action::make('resendFax')
            ->label(__('Resend Fax'))
            ->color('danger')
            ->modalHeading(__('Resend Fax'))
            ->modalSubmitActionLabel(__('Resend'))
            // Prefilled with the number the fax went to.
            ->fillForm(function (array $arguments): array {
                $messageId = (string) ($arguments['messageId'] ?? '');
                $this->faxIdToSend = $messageId !== '' ? $messageId : null;

                return ['faxNumber' => $this->faxIdToSend === null ? '' : ($this->originalRecipient($this->faxIdToSend) ?? '')];
            })
            ->schema([
                TextInput::make('faxNumber')
                    ->label(__('Recipient Fax Number'))
                    ->helperText(__('Change it to send the fax somewhere else.'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->resendFax((string) ($data['faxNumber'] ?? ''));
            });
    }

    /**
     * The number a fax was sent to.
     *
     * Taken from the rows the page is already showing, so the dialog opens without
     * waiting on mFax; only a fax not on the page is looked up. The row only supplies the
     * field's starting value, which the person can change anyway.
     */
    private function originalRecipient(string $messageId): ?string
    {
        foreach (is_array($this->state['mfax_failed_faxes'] ?? null) ? $this->state['mfax_failed_faxes'] : [] as $row) {
            if (is_array($row) && (string) ($row['messageId'] ?? '') === $messageId && filled($row['faxNumber'] ?? null)) {
                return (string) $row['faxNumber'];
            }
        }

        try {
            $info = json_decode((string) $this->mfax()->get("/v1/fax/{$messageId}/info")->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            Log::error('Documo api error'.$e->getMessage());

            return null;
        }

        $number = is_array($info) ? ($info['faxNumber'] ?? null) : null;

        return filled($number) ? (string) $number : null;
    }

    public function resendFax(string $faxNumber = ''): void
    {
        if ($this->faxIdToSend === null || $this->datasource->mfax_api_key === null) {
            $this->faxIdToSend = null;

            return;
        }

        $messageId = $this->faxIdToSend;
        $this->guzzle = $this->mfax();

        try {
            $resend = $this->guzzle->post('/v1/fax/resend', [
                'form_params' => [
                    'messageId' => $messageId,
                    'recipientFax' => trim($faxNumber),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('CloudFaxing: resend failed: '.$e->getMessage(), ['message_id' => $messageId]);

            // This used to fail with no message at all.
            $this->resendFailed($e->getMessage());

            return;
        }

        if ($resend->getStatusCode() !== 200) {
            $this->resendFailed(__('mFax answered HTTP :status.', ['status' => $resend->getStatusCode()]));

            return;
        }

        $this->updateTags();

        $tagParams = [];

        foreach ($this->tags as $uuid => $t) {
            if (strtolower($t) === 'resent') {
                $tagParams = [
                    'tagId' => $uuid,
                ];
                break;
            }
        }

        if (count($tagParams) !== 0) {
            try {
                $this->guzzle->post("/v1/fax/{$messageId}/tag", [
                    'form_params' => $tagParams,
                ]);
            } catch (Exception $e) {
            }
        }

        Notification::make()
            ->title(__('Fax resent to :number', ['number' => trim($faxNumber)]))
            ->success()
            ->send();

        $this->dispatch('resendFax', $messageId);
        $this->faxIdToSend = null;
        $this->redirect('/utilities/cloud-faxing');
    }

    private function resendFailed(string $reason): void
    {
        Notification::make()
            ->title(__('mFax did not resend the fax'))
            ->body($reason)
            ->danger()
            ->send();

        $this->faxIdToSend = null;
    }

    private function mfax(): Guzzle
    {
        if (app()->bound(self::GUZZLE_BINDING)) {
            return app(self::GUZZLE_BINDING);
        }

        return new Guzzle([
            // null when tracing is off, so Guzzle uses its default handler.
            'handler' => GuzzleTracing::handlerStack(),
            'base_uri' => 'https://api.documo.com/',
            'timeout' => 30.0,
            'headers' => [
                'Authorization' => 'Basic '.$this->datasource->mfax_api_key,
            ],
        ]);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getFailedFaxes(): array|bool
    {
        if ($this->datasource->mfax_api_key === null) {
            return false;
        }

        $cachedResults = Redis::get('cloud-faxing:failed-faxes');
        $cachedTags = Redis::get('cloud-faxing:fax-tags');
        if ($cachedTags !== null) {
            $this->tags = json_decode($cachedTags, true, 512, JSON_THROW_ON_ERROR);
        }

        if ($cachedResults !== null) {
            return json_decode($cachedResults, true, 512, JSON_THROW_ON_ERROR);
        }

        $this->guzzle = new Guzzle([
            // null when tracing is off, so Guzzle uses its default handler.
            'handler' => GuzzleTracing::handlerStack(),
            'base_uri' => 'https://api.documo.com/',
            'timeout' => 30.0,
            'headers' => [
                'Authorization' => 'Basic '.$this->datasource->mfax_api_key,
            ],
        ]);

        $this->updateTags();

        $cachedAppId = Redis::get('cloud-faxing:app-id');

        if (is_null($cachedAppId)) {
            try {
                $me = $this->guzzle->get('/v1/me');
            } catch (Exception $e) {
                return false;
            }

            if ($me->getStatusCode() !== 200) {
                return false;
            }
            $meResult = json_decode((string) $me->getBody(), true);

            $mfax_application_id = $meResult['accountId'] ?? null;
        } else {
            $mfax_application_id = json_decode($cachedAppId);
        }

        if ($mfax_application_id === null) {
            return false;
        }

        Redis::setEx('cloud-faxing:app-id', 60, json_encode($mfax_application_id, JSON_UNESCAPED_SLASHES));

        try {
            $history = $this->guzzle->get("/v1/fax/history?accountId={$mfax_application_id}&direction=outbound&status=all&limit=50&include=tags");
        } catch (Exception $e) {
            return false;
        }

        if ($history->getStatusCode() !== 200) {
            return false;
        }
        $historyResult = json_decode((string) $history->getBody(), true);

        if (isset($historyResult['rows'])) {
            Redis::setEx('cloud-faxing:failed-faxes', 15, json_encode($historyResult['rows'], JSON_UNESCAPED_SLASHES));

            return $historyResult['rows'];
        }

        return [];
    }

    public function updateFaxData(): void
    {
        $this->refreshFaxSpoolState();

        $this->state['mfax_failed_faxes'] = $this->getFailedFaxes();
        $this->state['webhook_last_received_at'] = FaxDeliveryWebhooks::lastReceivedAt('mfax');
    }

    protected function faxProvider(): string
    {
        return 'mfax';
    }

    protected function faxSource(): string
    {
        return $this->sourceKey;
    }

    /**
     * Read the spool folders from the cached per-source snapshot.
     *
     * This used to scan the directories inline, on every page load. That was tolerable
     * while the spool was always a local directory; it is not once a source can be a
     * remote share, because an unreachable server would hang an fpm worker on a user's
     * request. The snapshot is built on the scheduler instead.
     */
    protected function refreshFaxSpoolState(): void
    {
        $snapshot = app(FaxDashboardSnapshot::class)->read($this->sourceKey);

        if ($snapshot === null) {
            // Nothing built yet. Leave the page as it is rather than blanking it.
            return;
        }

        foreach (FaxSpool::FOLDERS as $key => $folder) {
            $this->state[$key] = FaxSpool::normalizeListing($snapshot[$key] ?? []);
            $this->state["{$key}_count"] = $snapshot["{$key}_count"] ?? 0;
        }

        $this->state['generated_at'] = $snapshot['generated_at'] ?? null;
        // An unreachable source must not look like an idle one: every server that is not
        // currently processing legitimately has an empty tosend/.
        $this->state['unreachable'] = $snapshot['unreachable'] ?? false;
    }

    /**
     * Re-read the spool after a deletion and write the result straight back into the
     * shared snapshot, so the file stops appearing for every viewer rather than just the
     * one who deleted it.
     */
    protected function rebuildFaxSpoolSnapshot(): void
    {
        $source = FaxSpoolSource::findByKey($this->sourceKey);

        if ($source !== null) {
            app(FaxDashboardSnapshot::class)->build($source);
        }

        $this->refreshFaxSpoolState();
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
        return view('livewire.utilities.cloud-faxing');
    }

    public function updateTags(): void
    {
        // mfax only
        try {
            $existingTagList = $this->guzzle->get('/v1/tags');
        } catch (Exception $e) {
        }

        if (isset($existingTagList) && $existingTagList->getStatusCode() === 200) {
            $tagsList = json_decode((string) $existingTagList->getBody(), true);
            foreach ($tagsList['rows'] as $tl) {
                $this->tags[$tl['uuid']] = $tl['name'];
            }

            Redis::setEx('cloud-faxing:fax-tags', 60, json_encode($this->tags, JSON_UNESCAPED_SLASHES));
        }
    }
}
