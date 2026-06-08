<?php

namespace App\Livewire\Utilities;

use App\Console\Commands\ISFaxing\BuildRingCentralFaxDashboard;
use App\Models\DataSource;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use JsonException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RingCentral\SDK\Http\ApiException;
use RingCentral\SDK\SDK as RingCentralSDK;

class CloudFaxingRingCentral extends Component
{
    #[Locked]
    public ?string $client_id = null;

    #[Locked]
    public string $client_secret;

    #[Locked]
    public string $jwtToken;

    #[Locked]
    public string $api_endpoint;

    public array $tags = [];

    public array $state = [];

    public mixed $datasource;

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
        $this->client_id = $this->datasource->ringcentral_client_id ?? '';
        try {
            $this->client_secret = decrypt($this->datasource->ringcentral_client_secret) ?? '';
            $this->jwtToken = decrypt($this->datasource->ringcentral_jwt_token) ?? '';
        } catch (Exception $e) {
            $this->client_secret = '';
            $this->jwtToken = '';
        }

        $this->api_endpoint = $this->datasource->ringcentral_api_endpoint ?? '';
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

        // Populate immediately from the cached snapshot so the (lazy-loaded) page paints
        // with data on first render instead of waiting for the first poll.
        $this->updateFaxData();
    }

    /**
     * @throws ApiException
     */
    public function openSendFaxDialog($messageId): void
    {
        /* Authenticate a user using a personal JWT token */
        try {
            // Instantiate the SDK and get the platform instance
            $rcsdk = new RingCentralSDK($this->client_id, $this->client_secret, $this->api_endpoint);
            $platform = $rcsdk->platform();
            $platform->login(['jwt' => $this->jwtToken]);
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
        $this->confirmResendFax = true;

    }

    /**
     * @throws ApiException
     */
    public function resendFax(): void
    {
        if ($this->faxIdToSend === null) {
            $this->faxIdToSend = null;
            $this->confirmResendFax = false;

            return;
        }

        $rcsdk = new RingCentralSDK($this->client_id, $this->client_secret, $this->api_endpoint);
        $platform = $rcsdk->platform();
        try {
            $platform->login(['jwt' => $this->jwtToken]);
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

        $this->confirmResendFax = false;
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
        $snapshot = Redis::get(BuildRingCentralFaxDashboard::DASHBOARD_CACHE_KEY);

        if ($snapshot === null) {
            // The scheduler hasn't built a snapshot yet; keep the current/default state.
            return;
        }

        try {
            $data = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error('CloudFaxingRingCentral: invalid dashboard snapshot: '.$e->getMessage());

            return;
        }

        $this->state['ringcentral_failed_faxes'] = $data['failed_faxes'] ?? [];
        $this->state['files_to_send'] = $data['files_to_send'] ?? [];
        $this->state['files_in_sent'] = $data['files_in_sent'] ?? [];
        $this->state['files_in_fail'] = $data['files_in_fail'] ?? [];
        $this->state['files_in_pre'] = $data['files_in_pre'] ?? [];
        $this->state['files_to_send_count'] = $data['files_to_send_count'] ?? 0;
        $this->state['files_in_sent_count'] = $data['files_in_sent_count'] ?? 0;
        $this->state['files_in_fail_count'] = $data['files_in_fail_count'] ?? 0;
        $this->state['files_in_pre_count'] = $data['files_in_pre_count'] ?? 0;
        $this->state['generated_at'] = $data['generated_at'] ?? null;
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
