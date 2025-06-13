<?php

namespace App\Livewire\Utilities;

use App\Models\DataSource;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
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
            $endpoint = '/restapi/v1.0/account/~/extension/~/fax';
            $resp = $platform->get($endpoint);

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
     * @throws \JsonException
     */
    public function getFailedFaxes(): array|bool
    {
        $this->mount();

        if ($this->datasource->ringcentral_client_id === null) {
            return false;
        }

        $cachedResults = Redis::get('cloud-faxing:ring-central:failed-faxes');
        if ($cachedResults !== null) {
            // Log::alert('Ring Central - Using cached results in display');
            return json_decode($cachedResults, true, 512, JSON_THROW_ON_ERROR);
        }

        if (! is_null($this->client_id) && $this->client_id !== '') {
            try {
                $rcsdk = new RingCentralSDK($this->client_id, $this->client_secret, $this->api_endpoint);
                $platform = $rcsdk->platform();
                $platform->login(['jwt' => $this->jwtToken]);
            } catch (Exception $e) {
                Log::error($e->getMessage());

                return false;
            }
        } else {
            return false;
        }

        try {
            $now = Carbon::now();
            $queryParams = [
                // The default value is dateTo minus 24 hours
                // 'dateFrom' => '2023-01-01T00:00:00.000Z',
                // 'dateFrom' => $now->copy()->subHours(24)->format('c'),
                // The default value is current time
                // 'dateTo' => '2023-01-31T23:59:59.999Z',
                // 'dateTo' => $now->format('c'),
                'messageType' => ['Fax'],
                'perPage' => 50,
            ];

            $endpoint = '/restapi/v1.0/account/~/extension/~/message-store';

            $resp = $platform->get($endpoint, $queryParams);
            $jsonArray = $resp->jsonArray();
            Redis::setEx('cloud-faxing:ring-central:failed-faxes', 15, json_encode($jsonArray['records'], JSON_UNESCAPED_SLASHES));

            return $jsonArray['records'];

        } catch (Exception $e) {
            Log::error($e->getMessage());

            return false;
        }

    }

    public function updateFaxData(): void
    {
        $this->state['files_to_send'] = array_diff(scandir(storage_path('app/ringcentral/tosend/')), ['.', '..', '.gitignore']);
        $this->state['files_in_sent'] = array_diff(scandir(storage_path('app/ringcentral/sent/')), ['.', '..', '.gitignore']);
        $this->state['files_in_fail'] = array_diff(scandir(storage_path('app/ringcentral/fail/')), ['.', '..', '.gitignore']);
        $this->state['files_in_pre'] = array_diff(scandir(storage_path('app/ringcentral/preproc/')), ['.', '..', '.gitignore']);

        $this->state['files_to_send_count'] = count($this->state['files_to_send']);
        $this->state['files_in_sent_count'] = count($this->state['files_in_sent']);
        $this->state['files_in_fail_count'] = count($this->state['files_in_fail']);
        $this->state['files_in_pre_count'] = count($this->state['files_in_pre']);

        try {
            $this->state['ringcentral_failed_faxes'] = $this->getFailedFaxes();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            $this->state['ringcentral_failed_faxes'] = [];

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
        return view('livewire.utilities.cloud-faxing-ring-central');
    }
}
