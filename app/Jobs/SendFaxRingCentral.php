<?php

namespace App\Jobs;

use App\Enums\FaxProvider;
use App\Mail\FaxFailAlert;
use App\Models\DataSource;
use App\Models\PendingFax;
use App\Models\Stats\Helpers;
use App\Services\Faxing\FaxAccountLookup;
use App\Services\Faxing\FaxLockKey;
use App\Services\Faxing\FaxRoute;
use App\Services\Faxing\FaxRouter;
use App\Services\Faxing\FaxSpool;
use App\Services\Faxing\RingCentralClient;
use App\Services\Faxing\RingCentralThrottle;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RingCentral\SDK\Http\ApiException;
use Throwable;

class SendFaxRingCentral implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Real send failures are capped by maxExceptions. Neither a release from the
     * RateLimited middleware nor a 429 from RingCentral is an exception, so being
     * throttled no longer burns the retry budget — retryUntil bounds how long we keep
     * re-queueing.
     */
    public int $maxExceptions = 3;

    public array $backoff = [30, 60];

    public int $jobID;

    public string $fsFileName;

    /**
     * Which spool source this fax came out of.
     *
     * Nullable with a null default on purpose. This job releases itself back onto the
     * queue while throttled and bounds itself with retryUntil, so a payload serialized
     * before sources existed can be unserialized up to retry_window (two hours by
     * default) after the deploy that added this property. PHP applies declared defaults
     * only for properties absent from the payload, so a non-nullable typed property would
     * fatal in handle() and again in failed() — the fax would be neither sent nor
     * reported, and the .fs would sit in tosend until the buildup alert fired.
     */
    public ?string $spoolSourceKey = null;

    /**
     * Why the router chose this provider, and whether a failed submission may be retried
     * through a different one. Both default so a payload written before routing existed
     * unserializes — see the note on $spoolSourceKey.
     */
    public ?string $routingReason = null;

    public bool $allowFailover = false;

    /**
     * Providers already attempted for this fax, so failover walks forward rather than
     * bouncing between two providers until the retry window runs out.
     *
     * @var array<int, string>
     */
    public array $triedProviders = [];

    public string $capfile;

    public string $filename;

    public string $phone;

    public string $status;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $fax)
    {
        // Strip everything that isn't a digit or leading +, e.g. the stray trailing ';'
        // that arrives in some .fs files ("9139069098;") and breaks E.164 normalization.
        $this->phone = preg_replace('/[^0-9+]/', '', $fax['phone']);
        $this->jobID = $fax['jobID'];
        // basename() the filenames from .fs contents so they can't escape the spool dir.
        $this->capfile = basename($fax['capfile']);
        $this->filename = basename($fax['filename']);
        $this->status = $fax['status'];
        $this->fsFileName = basename($fax['fsFileName']);
        $this->spoolSourceKey = $fax['source_key'] ?? null;
        $this->routingReason = $fax['routing_reason'] ?? null;
        $this->allowFailover = (bool) ($fax['allow_failover'] ?? false);
        $this->triedProviders = $fax['tried_providers'] ?? [];
        $this->onQueue('ringcentral');
    }

    public function middleware(): array
    {
        return [new RateLimited('ringcentral')];
    }

    /**
     * How long to keep re-queueing a fax that keeps getting throttled.
     *
     * This used to be 10 minutes, which — against a 10/minute limiter — meant a backlog
     * of more than ~100 faxes started *failing* the moment it formed: files moved to
     * fail/, alert emails sent, for faxes that were merely waiting their turn. A rate
     * limit should apply backpressure, not discard work, so the window is now hours and
     * configurable. maxExceptions still caps genuine send errors at three.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addSeconds($this->retryWindowSeconds());
    }

    /**
     * The .fs filename (e.g. IS20.fs) is the true per-recipient identity. Keying the
     * unique lock on it (instead of the shared jobID from $var_def DATA5) lets a
     * fan-out — one .cap to several numbers — send to every recipient instead of just
     * the first.
     */
    public function uniqueId(): string
    {
        return FaxLockKey::for($this->spoolSourceKey, FaxProvider::RingCentral->value, $this->fsFileName);
    }

    /**
     * Bound the unique lock so it cannot outlive the job that holds it.
     *
     * Without this the lock was held until the job completed — so a worker killed
     * mid-send (a deploy, a Horizon restart, an OOM) left a lock nobody would ever
     * release, and isfax:process-ring-central silently refused to re-dispatch that .fs
     * forever. The file just sat in tosend/ as a phantom until the buildup alert fired.
     */
    public function uniqueFor(): int
    {
        return $this->retryWindowSeconds() + 600;
    }

    public function handle(): void
    {
        if (! Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            Log::info('SendFaxringCentral Feature Turned Off');

            return;
        }

        $datasource = DataSource::first();

        if ($datasource === null) {
            $this->fail(new Exception('No data source configured'));

            return;
        }

        $client = new RingCentralClient($datasource);

        if (! $client->configured()) {
            $this->fail(new Exception('Empty ringcentral client details'));

            return;
        }

        $toNumber = $this->normalizedRecipient();

        $faxFsDetails = [
            'jobID' => $this->jobID,
            'capfile' => $this->capfile,
            'filename' => $this->filename,
            'phone' => $this->phone,
            'status' => $this->status,
            'fsFileName' => $this->fsFileName,
            'source_key' => $this->sourceKey(),
        ];

        // Resolve the Intelligent Series account before sending, so the record we write
        // is identifiable even if the IS database becomes unreachable later. A failure
        // here never blocks the fax — it just goes out unlabelled.
        $account = FaxAccountLookup::make($datasource)->forJobId($this->jobID);

        $payloadPath = $this->capFilePath();

        if (! is_file($payloadPath)) {
            $this->fail(new Exception("Fax payload missing: {$payloadPath}"));

            return;
        }

        try {
            $rcsdk = $client->sdk();

            $bodyParams = $rcsdk->createMultipartBuilder()
                ->setBody([
                    'to' => [
                        ['phoneNumber' => $toNumber],
                    ],
                    'faxResolution' => 'High',
                    'coverIndex' => 0, // no fax cover page, otherwise uses default from the account
                ])
                ->add(file_get_contents($payloadPath), $this->attachmentName($account))
                ->request('/restapi/v1.0/account/~/extension/~/fax');

            $resp = $rcsdk->platform()->sendRequest($bodyParams);
            $respData = $resp->json();
            $apiMessageId = (string) ($respData->id ?? '');

            PendingFax::create([
                'spool_source_key' => $this->sourceKey(),
                'routing_reason' => $this->routingReason,
                'api_fax_id' => $apiMessageId,
                'fax_provider' => 'ringcentral',
                'job_id' => $this->jobID,
                'fs_file_name' => $this->fsFileName,
                'cap_file' => $this->capfile,
                'filename' => $this->filename,
                'phone' => $this->phone,
                'client_number' => $account['number'] ?? null,
                'client_name' => $account['name'] ?? null,
                'original_status' => $this->status,
                'delivery_status' => 'pending',
                'submitted_at' => now(),
            ]);

            Log::info('ringCentralSuccess '.$toNumber, $faxFsDetails + ['account' => $account['number'] ?? null]);
        } catch (ApiException $e) {
            // Being throttled is not a failed fax. Wait out the window the API asked for
            // and try again without touching the exception budget.
            if (RingCentralThrottle::isRateLimited($e)) {
                $retryAfter = RingCentralThrottle::retryAfter($e);

                Log::warning("SendFaxRingCentral throttled by RingCentral; retrying in {$retryAfter}s", $faxFsDetails);

                $this->release($retryAfter);

                return;
            }

            // A rejected token means the shared one is stale — drop it so the next
            // attempt authenticates cleanly rather than replaying a dead token.
            if (RingCentralThrottle::isUnauthorized($e)) {
                $client->forgetToken();

                Log::warning('SendFaxRingCentral: RingCentral rejected the cached token; re-authenticating.', $faxFsDetails);

                $this->release(10);

                return;
            }

            Log::error($e->getMessage(), ['ringCentralApiResponse' => $e->apiResponse()]);

            throw $e;
        } catch (Exception $e) {
            Log::error($e->getMessage());

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $faxFsDetails = [
            'jobID' => $this->jobID,
            'capfile' => $this->capfile,
            'filename' => $this->filename,
            'phone' => $this->phone,
            'status' => $this->status,
            'fsFileName' => $this->fsFileName,
            'source_key' => $this->sourceKey(),
            // Cached from the send attempt, so this costs nothing and tells whoever reads
            // the alert whose fax failed.
            'account' => $this->accountLabel(),
        ];

        Log::error("SendFaxRingCentral failed: {$exception->getMessage()}", $faxFsDetails);

        // Handing the fax to the other provider means it is still in flight, so neither
        // the failure alert nor the fail/ move may happen yet.
        if ($this->attemptFailover($faxFsDetails)) {
            return;
        }

        Mail::queue(new FaxFailAlert($faxFsDetails, $exception->getMessage()));
        MoveFailedFaxFiles::dispatch($faxFsDetails, FaxProvider::RingCentral->value, $this->sourceKey());
    }

    private function currentProvider(): FaxProvider
    {
        return FaxProvider::RingCentral;
    }

    /**
     * Try the other provider before giving up, when the route allowed it.
     *
     * Only reached from failed(), i.e. after this provider has exhausted its own retries.
     * Returns true when the fax has been handed on, in which case the caller must NOT
     * report a failure back to Intelligent Series — the fax is still in flight.
     */
    private function attemptFailover(array $faxFsDetails): bool
    {
        $next = app(FaxRouter::class)->nextProvider(
            new FaxRoute($this->currentProvider(), $this->routingReason ?? 'unknown', $this->allowFailover),
            [...$this->triedProviders, $this->currentProvider()->value],
        );

        if ($next === null) {
            return false;
        }

        $payload = $faxFsDetails + [
            'routing_reason' => 'failover from '.$this->currentProvider()->value,
            'allow_failover' => $this->allowFailover,
            'tried_providers' => [...$this->triedProviders, $this->currentProvider()->value],
        ];

        Log::warning("Fax {$this->fsFileName} failing over from {$this->currentProvider()->value} to {$next->value}");

        $next === FaxProvider::RingCentral
            ? SendFaxRingCentral::dispatch($payload)
            : SendFaxJob::dispatch($payload);

        return true;
    }

    private function accountLabel(): string
    {
        try {
            $account = FaxAccountLookup::make()->forJobId($this->jobID);
        } catch (Throwable $e) {
            return 'Unknown';
        }

        if ($account === null) {
            return 'Unknown';
        }

        return trim($account['number'].(blank($account['name']) ? '' : " — {$account['name']}"));
    }

    private function retryWindowSeconds(): int
    {
        return max(60, (int) config('services.fax.ringcentral.retry_window', 7200));
    }

    /**
     * Where the .cap payload lives depends on the switch engine: Infinity keeps messages
     * in its own directory, classic IS leaves them alongside the .fs in tosend.
     */
    private function capFilePath(): string
    {
        $folder = config('app.switch_engine') === 'infinity' ? 'messages' : 'tosend';

        return (new FaxSpool)->path(FaxProvider::RingCentral->value, $folder, $this->sourceKey()).$this->capfile;
    }

    /**
     * The spool source this fax belongs to, defaulting to the legacy RingCentral one.
     */
    public function sourceKey(): string
    {
        return $this->spoolSourceKey ?? FaxProvider::RingCentral->value;
    }

    /**
     * Name the uploaded document after the account it belongs to.
     *
     * RingCentral has no tag API, so this is the nearest equivalent of the mFax tag:
     * the account number becomes part of the attachment name that shows up against the
     * message in RingCentral's own message store. The .txt extension has to survive —
     * RingCentral decides how to render the document from it.
     *
     * @param  array{number: string, name: string}|null  $account
     */
    private function attachmentName(?array $account): string
    {
        $name = str_replace('.cap', '.txt', $this->filename);

        if ($account === null || ! config('services.fax.ringcentral.tag_attachment_name', true)) {
            return $name;
        }

        $number = preg_replace('/[^A-Za-z0-9_-]/', '', $account['number']);

        return $number === '' ? $name : "{$number}_{$name}";
    }

    private function normalizedRecipient(): string
    {
        if (! Str::startsWith($this->phone, '+') && strlen($this->phone) === 10) {
            return "+1{$this->phone}";
        }

        if (Str::startsWith($this->phone, '9') && strlen($this->phone) === 11) {
            return '+1'.Str::after($this->phone, '9');
        }

        if (Str::startsWith($this->phone, '91') && strlen($this->phone) === 12) {
            return '+1'.Str::after($this->phone, '91');
        }

        if (! Str::startsWith($this->phone, '+')) {
            return "+{$this->phone}";
        }

        return $this->phone;
    }
}
