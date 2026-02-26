<?php

declare(strict_types=1);

namespace App\Console\Commands\ISFaxing;

use App\Jobs\MoveFailedFaxFiles;
use App\Jobs\MoveSuccessfulFaxFiles;
use App\Mail\FaxFailAlert;
use App\Models\DataSource;
use App\Models\PendingFax;
use App\Models\Stats\Helpers;
use Exception;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RingCentral\SDK\Platform\Platform as RingCentralPlatform;
use RingCentral\SDK\SDK as RingCentralSDK;
use Symfony\Component\Console\Command\Command as CommandStatus;

class CheckPendingFaxes extends Command
{
    protected $signature = 'isfax:check-pending';

    protected $description = 'Check delivery status of pending faxes with MFax/RingCentral';

    public function handle(): int
    {
        if (! Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            return CommandStatus::SUCCESS;
        }

        $pendingFaxes = PendingFax::where('delivery_status', 'pending')->get();

        if ($pendingFaxes->isEmpty()) {
            return CommandStatus::SUCCESS;
        }

        $datasource = DataSource::first();

        // Authenticate RingCentral once if any pending faxes use it
        $rcPlatform = null;
        if ($pendingFaxes->contains('fax_provider', 'ringcentral')) {
            try {
                $rcsdk = new RingCentralSDK(
                    $datasource->ringcentral_client_id,
                    decrypt($datasource->ringcentral_client_secret),
                    $datasource->ringcentral_api_endpoint
                );
                $rcPlatform = $rcsdk->platform();
                $rcPlatform->login(['jwt' => decrypt($datasource->ringcentral_jwt_token)]);
            } catch (Exception $e) {
                Log::error("CheckPendingFaxes: RingCentral auth failed: {$e->getMessage()}");
            }
        }

        foreach ($pendingFaxes as $pendingFax) {
            $pendingFax->increment('poll_attempts');

            if ($pendingFax->poll_attempts > 120) {
                $this->resolveFax($pendingFax, 'failed', 'Timed out after 2 hours');

                continue;
            }

            try {
                if ($pendingFax->fax_provider === 'mfax') {
                    $this->checkMfax($pendingFax, $datasource);
                } elseif ($pendingFax->fax_provider === 'ringcentral' && $rcPlatform) {
                    $this->checkRingCentral($pendingFax, $rcPlatform);
                    sleep(2);
                }
            } catch (Exception $e) {
                Log::error("CheckPendingFaxes error for #{$pendingFax->id}: {$e->getMessage()}");
            }
        }

        return CommandStatus::SUCCESS;
    }

    private function checkMfax(PendingFax $pendingFax, DataSource $datasource): void
    {
        $guzzle = new Guzzle([
            'base_uri' => 'https://api.documo.com/',
            'timeout' => 30.0,
            'headers' => [
                'Authorization' => 'Basic '.decrypt($datasource->mfax_api_key),
            ],
        ]);

        $response = $guzzle->get("/v1/faxes/{$pendingFax->api_fax_id}");
        $data = json_decode((string) $response->getBody(), true);
        $status = $data['status'] ?? null;

        // Documo statuses: success, failed, cancelled, sending, queued
        if (in_array($status, ['success', 'completed'])) {
            $this->resolveFax($pendingFax, 'success');
        } elseif (in_array($status, ['failed', 'cancelled'])) {
            $this->resolveFax($pendingFax, 'failed', "MFax status: {$status}");
        }
        // Otherwise still pending — do nothing
    }

    private function checkRingCentral(PendingFax $pendingFax, RingCentralPlatform $platform): void
    {
        $response = $platform->get("/restapi/v1.0/account/~/extension/~/message-store/{$pendingFax->api_fax_id}");
        $data = $response->json();
        $messageStatus = $data->messageStatus ?? null;

        // RingCentral messageStatus: Queued, Sent, Delivered, DeliveryFailed, SendingFailed, Received
        if (in_array($messageStatus, ['Sent', 'Delivered'])) {
            $this->resolveFax($pendingFax, 'success');
        } elseif (in_array($messageStatus, ['DeliveryFailed', 'SendingFailed'])) {
            $this->resolveFax($pendingFax, 'failed', "RingCentral status: {$messageStatus}");
        }
    }

    private function resolveFax(PendingFax $pendingFax, string $outcome, ?string $reason = null): void
    {
        $faxFsDetails = [
            'jobID' => $pendingFax->job_id,
            'capfile' => $pendingFax->cap_file,
            'filename' => $pendingFax->filename,
            'phone' => $pendingFax->phone,
            'status' => $pendingFax->original_status,
            'fsFileName' => $pendingFax->fs_file_name,
        ];

        if ($outcome === 'success') {
            MoveSuccessfulFaxFiles::dispatch($faxFsDetails, $pendingFax->fax_provider);
            $this->info("Fax #{$pendingFax->id} (job {$pendingFax->job_id}) delivered successfully.");
        } else {
            MoveFailedFaxFiles::dispatch($faxFsDetails, $pendingFax->fax_provider);
            Mail::queue(new FaxFailAlert($faxFsDetails, $reason ?? 'Fax delivery failed'));
            $this->error("Fax #{$pendingFax->id} (job {$pendingFax->job_id}) failed: {$reason}");
        }

        $pendingFax->update([
            'delivery_status' => $outcome,
            'resolved_at' => Carbon::now(),
        ]);
    }
}
