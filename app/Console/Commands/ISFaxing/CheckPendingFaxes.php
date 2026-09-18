<?php

declare(strict_types=1);

namespace App\Console\Commands\ISFaxing;

use App\Jobs\MoveFailedFaxFiles;
use App\Jobs\MoveSuccessfulFaxFiles;
use App\Mail\FaxFailAlert;
use App\Models\DataSource;
use App\Models\PendingFax;
use App\Models\Stats\Helpers;
use App\Services\Faxing\RingCentralClient;
use App\Services\Faxing\RingCentralThrottle;
use App\Services\Observability\GuzzleTracing;
use Exception;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RingCentral\SDK\Platform\Platform as RingCentralPlatform;
use Symfony\Component\Console\Command\Command as CommandStatus;
use Throwable;

/**
 * Fallback delivery-status poller.
 *
 * The provider webhooks (App\Http\Controllers\API\Webhooks\FaxWebhookController) are the
 * primary way a fax gets resolved; this command exists for deployments where those are
 * not configured or are not arriving.
 *
 * It used to poll *every* pending fax on *every* minute, with a sleep(2) between each
 * call. Thirty pending faxes therefore meant thirty API calls a minute — three times the
 * budget allotted to actually sending faxes — and a minute of sleeping inside a
 * withoutOverlapping command that consequently starved its own next run. Now each fax is
 * polled no more often than poll_interval_seconds, not at all until poll_grace_seconds
 * have passed (by which time a webhook has usually resolved it), and no more than
 * poll_batch_size faxes are polled per run, oldest-checked first so nothing starves.
 */
class CheckPendingFaxes extends Command
{
    protected $signature = 'isfax:check-pending';

    protected $description = 'Check delivery status of pending faxes with MFax/RingCentral';

    public function handle(): int
    {
        if (! Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            return CommandStatus::SUCCESS;
        }

        $datasource = DataSource::first();

        if ($datasource === null) {
            return CommandStatus::SUCCESS;
        }

        $this->timeOutAbandonedFaxes();

        $this->pollProvider('ringcentral', $datasource);
        $this->pollProvider('mfax', $datasource);

        return CommandStatus::SUCCESS;
    }

    /**
     * Give up on faxes that have been pending far longer than any provider takes.
     *
     * Previously this was `poll_attempts > 120`, which only meant "two hours" while every
     * pending fax was polled once a minute. Now that polling is batched and spaced, the
     * timeout has to be measured in time.
     */
    private function timeOutAbandonedFaxes(): void
    {
        $cutoff = Carbon::now()->subSeconds($this->timeoutSeconds());

        PendingFax::pending()
            ->where(fn ($query) => $query->where('submitted_at', '<', $cutoff)
                ->orWhere(fn ($q) => $q->whereNull('submitted_at')->where('created_at', '<', $cutoff)))
            ->get()
            ->each(fn (PendingFax $fax) => $this->resolveFax(
                $fax,
                'failed',
                'Timed out after '.round($this->timeoutSeconds() / 60).' minutes without a delivery confirmation'
            ));
    }

    private function pollProvider(string $provider, DataSource $datasource): void
    {
        $due = $this->faxesDueForPolling($provider);

        if ($due->isEmpty()) {
            return;
        }

        $this->info("Polling {$due->count()} pending {$provider} fax(es).");

        if ($provider === 'ringcentral') {
            $this->pollRingCentral($due, $datasource);

            return;
        }

        $this->pollMfax($due, $datasource);
    }

    /**
     * @return Collection<int, PendingFax>
     */
    private function faxesDueForPolling(string $provider): Collection
    {
        $grace = Carbon::now()->subSeconds((int) config('services.fax.poll_grace_seconds', 120));
        $interval = Carbon::now()->subSeconds((int) config('services.fax.poll_interval_seconds', 120));

        return PendingFax::pending()
            ->where('fax_provider', $provider)
            // Leave a freshly submitted fax alone; a webhook usually resolves it first.
            ->where(fn ($query) => $query->where('submitted_at', '<=', $grace)
                ->orWhere(fn ($q) => $q->whereNull('submitted_at')->where('created_at', '<=', $grace)))
            ->where(fn ($query) => $query->whereNull('last_polled_at')
                ->orWhere('last_polled_at', '<=', $interval))
            // Never-polled first, then longest-since-polled, so a large backlog still
            // gives every fax a turn instead of re-checking the same few.
            ->orderByRaw('last_polled_at is null desc')
            ->orderBy('last_polled_at')
            ->limit($this->batchSize())
            ->get();
    }

    /**
     * @param  Collection<int, PendingFax>  $faxes
     */
    private function pollRingCentral(Collection $faxes, DataSource $datasource): void
    {
        $client = new RingCentralClient($datasource);

        if (! $client->configured()) {
            return;
        }

        try {
            $platform = $client->platform();
        } catch (Throwable $e) {
            Log::error("CheckPendingFaxes: RingCentral auth failed: {$e->getMessage()}");

            return;
        }

        foreach ($faxes as $pendingFax) {
            $this->markPolled($pendingFax);

            try {
                $this->checkRingCentral($pendingFax, $platform);
            } catch (Throwable $e) {
                // Being throttled means every remaining call this run would be throttled
                // too, and each one steals quota from actual fax sending. Stop here and
                // pick up where we left off next run.
                if (RingCentralThrottle::isRateLimited($e)) {
                    Log::warning('CheckPendingFaxes: throttled by RingCentral; abandoning the rest of this run.');

                    return;
                }

                if (RingCentralThrottle::isUnauthorized($e)) {
                    $client->forgetToken();
                    Log::warning('CheckPendingFaxes: RingCentral rejected the cached token; will re-authenticate next run.');

                    return;
                }

                Log::error("CheckPendingFaxes error for #{$pendingFax->id}: {$e->getMessage()}");
            }
        }
    }

    /**
     * @param  Collection<int, PendingFax>  $faxes
     */
    private function pollMfax(Collection $faxes, DataSource $datasource): void
    {
        if (blank($datasource->mfax_api_key)) {
            return;
        }

        $guzzle = new Guzzle([
            // null when tracing is off, so Guzzle uses its default handler.
            'handler' => GuzzleTracing::handlerStack(),
            'base_uri' => 'https://api.documo.com/',
            'timeout' => 30.0,
            'headers' => [
                'Authorization' => 'Basic '.$datasource->mfax_api_key,
            ],
        ]);

        foreach ($faxes as $pendingFax) {
            $this->markPolled($pendingFax);

            try {
                $this->checkMfax($pendingFax, $guzzle);
            } catch (RequestException $e) {
                if ($e->getResponse()?->getStatusCode() === 429) {
                    Log::warning('CheckPendingFaxes: throttled by mFax; abandoning the rest of this run.');

                    return;
                }

                Log::error("CheckPendingFaxes error for #{$pendingFax->id}: {$e->getMessage()}");
            } catch (Exception $e) {
                Log::error("CheckPendingFaxes error for #{$pendingFax->id}: {$e->getMessage()}");
            }
        }
    }

    private function checkMfax(PendingFax $pendingFax, Guzzle $guzzle): void
    {
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

    private function markPolled(PendingFax $pendingFax): void
    {
        $pendingFax->forceFill([
            'poll_attempts' => $pendingFax->poll_attempts + 1,
            'last_polled_at' => Carbon::now(),
        ])->save();
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
            'account' => $pendingFax->accountLabel() ?? 'Unknown',
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

    private function batchSize(): int
    {
        return max(1, (int) config('services.fax.ringcentral.poll_batch_size', 15));
    }

    private function timeoutSeconds(): int
    {
        return max(300, (int) config('services.fax.pending_timeout_seconds', 7200));
    }
}
