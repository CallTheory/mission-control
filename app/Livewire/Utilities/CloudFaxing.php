<?php

namespace App\Livewire\Utilities;

use App\Models\DataSource;
use Exception;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use JsonException;
use Livewire\Component;

class CloudFaxing extends Component
{
    private $guzzle;

    public array $tags = [];

    public array $state = [];

    public DataSource $datasource;

    public bool $confirmResendFax = false;

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
    public function openSendFaxDialog($messageId): void
    {

        $this->guzzle = new Guzzle([
            'base_uri' => 'https://api.documo.com/',
            'timeout' => 30.0,
            'headers' => [
                'Authorization' => 'Basic '.decrypt($this->datasource->mfax_api_key),
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
        $this->confirmResendFax = true;

    }

    public function resendFax(): void
    {
        if ($this->faxIdToSend === null) {
            $this->faxIdToSend = null;
            $this->confirmResendFax = false;

            return;
        }

        if ($this->datasource->mfax_api_key === null) {
            $this->faxIdToSend = null;
            $this->confirmResendFax = false;

            return;
        }

        $this->guzzle = new Guzzle([
            'base_uri' => 'https://api.documo.com/',
            'timeout' => 30.0,
            'headers' => [
                'Authorization' => 'Basic '.decrypt($this->datasource->mfax_api_key),
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
            $this->confirmResendFax = false;

            return;
        }

        $this->confirmResendFax = false;
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
            'base_uri' => 'https://api.documo.com/',
            'timeout' => 30.0,
            'headers' => [
                'Authorization' => 'Basic '.decrypt($this->datasource->mfax_api_key),
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
        $this->state['files_to_send'] = array_diff(scandir(storage_path('app/mfax/tosend/')), ['.', '..', '.gitignore']);
        $this->state['files_in_sent'] = array_diff(scandir(storage_path('app/mfax/sent/')), ['.', '..', '.gitignore']);
        $this->state['files_in_fail'] = array_diff(scandir(storage_path('app/mfax/fail/')), ['.', '..', '.gitignore']);
        $this->state['files_in_pre'] = array_diff(scandir(storage_path('app/mfax/preproc/')), ['.', '..', '.gitignore']);

        $this->state['files_to_send_count'] = count($this->state['files_to_send']);
        $this->state['files_in_sent_count'] = count($this->state['files_in_sent']);
        $this->state['files_in_fail_count'] = count($this->state['files_in_fail']);
        $this->state['files_in_pre_count'] = count($this->state['files_in_pre']);
        $this->state['mfax_failed_faxes'] = $this->getFailedFaxes();

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
