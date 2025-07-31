<?php

namespace App\Jobs;

use App\Mail\FaxFailAlert;
use App\Models\DataSource;
use App\Models\Stats\Helpers;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RingCentral\SDK\Http\ApiException;
use RingCentral\SDK\SDK as RingCentralSDK;

class SendFaxRingCentral implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $jobID;

    public string $client_id;

    public string $client_secret;

    public string $api_endpoint;

    public string $fsFileName;

    public string $capfile;

    public string $filename;

    public string $phone;

    public string $status;

    public string $jwtToken;

    public string $notes;

    public DataSource $datasource;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $fax)
    {
        $this->datasource = DataSource::firstOrFail();

        $this->phone = str_ireplace(['-', '.', ' ', '(', ')'], '', $fax['phone']);
        $this->jobID = $fax['jobID'];
        $this->capfile = $fax['capfile'];
        $this->filename = $fax['filename'];
        $this->status = $fax['status'];
        $this->fsFileName = $fax['fsFileName'];

        if ($this->datasource->ringcentral_client_id !== null) {
            $this->client_id = $this->datasource->ringcentral_client_id;
            $this->api_endpoint = $this->datasource->ringcentral_api_endpoint;
            try {
                $this->client_secret = decrypt($this->datasource->ringcentral_client_secret);
                $this->jwtToken = decrypt($this->datasource->ringcentral_jwt_token);

            } catch (Exception $e) {
                Log::error($e->getMessage(), $fax);
                exit();
            }
        } else {
            Log::error('Empty ringcentral client details', $fax);
            exit();
        }

    }

    public function handle(): void
    {
        if (Helpers::isSystemFeatureEnabled('cloud-faxing')) {

            if (! Str::startsWith($this->phone, '+') && strlen($this->phone) === 10) {
                $toNumber = "+1{$this->phone}";
            }elseif ( Str::startsWith($this->phone, '9') && strlen($this->phone) === 11 ) {
                $toNumber = "+1" . Str::after($this->phone, '9');
            }elseif ( Str::startsWith($this->phone, '91') && strlen($this->phone) === 12 ) {
                $toNumber = "+1" . Str::after($this->phone, '91');
            }
            elseif (! Str::startsWith($this->phone, '+')) {
                $toNumber = "+{$this->phone}";
            } else {
                $toNumber = "{$this->phone}";
            }

            $faxFsDetails = [
                'jobID' => $this->jobID,
                'capfile' => $this->capfile,
                'filename' => $this->filename,
                'phone' => $this->phone,
                'status' => $this->status,
                'fsFileName' => $this->fsFileName,
            ];

            /* Authenticate a user using a personal JWT token */
            try {

                // Instantiate the SDK and get the platform instance
                $rcsdk = new RingCentralSDK($this->client_id, $this->client_secret, $this->api_endpoint);
                $platform = $rcsdk->platform();
                $platform->login(['jwt' => $this->jwtToken]);
            } catch (ApiException $e) {
                Log::error($e->getMessage(), ['ringCentralApiResponse' => $e->apiResponse()]);
                Mail::queue(new FaxFailAlert($faxFsDetails, $e->getMessage()));
                MoveFailedFaxFiles::dispatch($faxFsDetails, 'ringcentral');

                return;
            } catch (Exception $e) {
                Log::error($e->getMessage(), ['ringCentralApiResponse' => $e->apiResponse()]);
                Mail::queue(new FaxFailAlert($faxFsDetails, $e->getMessage()));
                MoveFailedFaxFiles::dispatch($faxFsDetails, 'ringcentral');

                return;
            }

            try {

                if(config('app.switch_engine') == 'infinity')
                {
                    $bodyParams = $rcsdk->createMultipartBuilder()
                        ->setBody([
                            'to' => [
                                ['phoneNumber' => $toNumber],
                            ],
                            'faxResolution' => 'High',
                            'coverIndex' => 0, // no fax cover page, otherwise uses default from the account
                        ])
                        ->add(file_get_contents(storage_path('app/ringcentral/messages/'.$this->capfile)), str_replace('.cap', '.txt', $this->filename))
                        ->request('/restapi/v1.0/account/~/extension/~/fax');
                }
                else{
                    $bodyParams = $rcsdk->createMultipartBuilder()
                        ->setBody([
                            'to' => [
                                ['phoneNumber' => $toNumber],
                            ],
                            'faxResolution' => 'High',
                            'coverIndex' => 0, // no fax cover page, otherwise uses default from the account
                        ])
                        ->add(file_get_contents(storage_path('app/ringcentral/tosend/'.$this->capfile)), str_replace('.cap', '.txt', $this->filename))
                        ->request('/restapi/v1.0/account/~/extension/~/fax');
                }

                $resp = $platform->sendRequest($bodyParams);

                MoveSuccessfulFaxFiles::dispatch($faxFsDetails, 'ringcentral');
            } catch (ApiException $e) {
                Log::error($e->getMessage(), ['ringCentralApiResponse' => $e->apiResponse()]);
                Mail::queue(new FaxFailAlert($faxFsDetails, $e->getMessage()));
                MoveFailedFaxFiles::dispatch($faxFsDetails, 'ringcentral');
            } catch (Exception $e) {
                Log::error($e->getMessage());
                Mail::queue(new FaxFailAlert($faxFsDetails, $e->getMessage()));
                MoveFailedFaxFiles::dispatch($faxFsDetails, 'ringcentral');
            }
        }
    }

    public function uniqueId()
    {
        return $this->jobID;
    }
}
