<?php

namespace App\Livewire\Utilities;

use App\Livewire\Concerns\ManagesFaxSpool;
use App\Models\DataSource;
use App\Services\Faxing\FaxDeliveryWebhooks;
use App\Services\Faxing\FaxSpool;
use App\Services\Observability\GuzzleTracing;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
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

    private $guzzle;

    public array $tags = [];

    public array $state = [];

    public DataSource $datasource;

    public ?string $faxIdToSend;

    protected $listeners = ['faxResent'];

    public function faxResent($faxMessageId): void
    {
        $this->state['success_message'][$faxMessageId] = true;
    }

    public function mount(): void
    {
        $this->datasource = DataSource::firstOrFail();
        $this->updateFaxData();
    }

    /**
     * @throws GuzzleException
     * @throws Exception
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

        $this->guzzle = new Guzzle([
            // null when tracing is off, so Guzzle uses its default handler.
            'handler' => GuzzleTracing::handlerStack(),
            'base_uri' => 'https://api.documo.com/',
            'timeout' => 30.0,
            'headers' => [
                'Authorization' => 'Basic '.$this->datasource->mfax_api_key,
            ],
        ]);

        try {
            $faxInfo = $this->guzzle->get("/v1/fax/{$messageId}/info");
            $this->state['faxInfo'] = json_decode($faxInfo->getBody(), true, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            Log::error('Documo api error'.$e->getMessage());
            throw $e;
        }

        $this->faxIdToSend = $messageId;

    }

    public function resendFax(): void
    {
        if ($this->faxIdToSend === null) {
            $this->faxIdToSend = null;

            return;
        }

        if ($this->datasource->mfax_api_key === null) {
            $this->faxIdToSend = null;

            return;
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

        try {
            $resend = $this->guzzle->post('/v1/fax/resend', [
                'form_params' => [
                    'messageId' => $this->faxIdToSend,
                    'recipientFax' => $this->state['faxInfo']['faxNumber'] ?? '',
                ],
            ]);
        } catch (Exception $e) {
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
                $resend = $this->guzzle->post("/v1/fax/{$this->faxIdToSend}/tag", [
                    'form_params' => $tagParams,
                ]);
            } catch (Exception $e) {
            }
        }

        if ($resend->getStatusCode() !== 200) {
            $this->faxIdToSend = null;

            return;
        }

        $this->dispatch('resendFax', $this->faxIdToSend);
        $this->faxIdToSend = null;
        $this->redirect('/utilities/cloud-faxing');
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

    /**
     * Read the spool folders. Unlike the RingCentral page there is no cached snapshot to
     * update here — this page has always scanned the directories per request.
     */
    protected function refreshFaxSpoolState(): void
    {
        foreach ((new FaxSpool)->snapshot('mfax') as $key => $value) {
            $this->state[$key] = $value;
        }
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
