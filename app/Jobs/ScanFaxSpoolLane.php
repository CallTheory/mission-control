<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\FaxProvider;
use App\Models\FaxSpoolSource;
use App\Models\PendingFax;
use App\Services\Faxing\FaxAccountLookup;
use App\Services\Faxing\FaxFailureLog;
use App\Services\Faxing\FaxRouter;
use App\Services\Faxing\FaxSourceHealth;
use App\Services\Faxing\FaxSpool;
use App\Services\Faxing\FsFileParser;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Scans one (source, provider) spool for new `.fs` files and submits them.
 *
 * This used to run inline in the scheduler, which is what made a single unreachable
 * Intelligent Series server stall every other one: schedule:run executes due commands in
 * sequence, so a scandir() that never returns blocks the whole minute's work — and cron
 * starts another one sixty seconds later.
 *
 * As a queued job the damage is bounded instead. Before each job the worker arms
 * pcntl_alarm($timeout); on expiry the handler marks the job failed, fires failed(), and
 * kills the worker, which Horizon replaces. One lane's hang costs one disposable process.
 *
 * The honest caveat: PHP dispatches signals between VM instructions, so a process blocked
 * in an *uninterruptible* syscall — the D state a hard CIFS/NFS mount produces — never
 * reaches the handler at all. Queueing moves that failure off the critical path but
 * cannot cure it. Mounts must be soft (`soft,echo_interval=10`); the real fix is the
 * app-level SMB driver, whose timeouts are enforceable.
 */
class ScanFaxSpoolLane implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One attempt only. Retrying a timed-out scan just puts a second worker on the same
     * unreachable mount; the next scheduler tick re-dispatches anyway.
     */
    public int $tries = 1;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public string $sourceKey = 'mfax';

    public function __construct(string $sourceKey)
    {
        $this->sourceKey = $sourceKey;
        $this->onQueue('fax-scan');
    }

    /**
     * One scan per source at a time, and deliberately not per provider: a source has one
     * tosend/ folder, and which provider each fax leaves through is decided per fax by
     * FaxRouter. Keying this per provider would scan the same directory twice and submit
     * every fax once per provider.
     */
    public function uniqueId(): string
    {
        return $this->sourceKey;
    }

    /**
     * Long, because it only ever binds when a worker dies abnormally — a job that
     * completes releases the lock regardless. A hang that never delivers the alarm is
     * therefore retried every fifteen minutes rather than every sixty seconds.
     */
    public function uniqueFor(): int
    {
        return 900;
    }

    public function handle(FsFileParser $parser, FaxSpool $spool, FaxSourceHealth $health, FaxRouter $router): void
    {
        $source = FaxSpoolSource::findByKey($this->sourceKey);
        $toSendPath = $spool->path(FaxProvider::fallback()->value, 'tosend', $this->sourceKey);

        $names = @scandir($toSendPath);

        if ($names === false) {
            // Unreachable or missing. Recorded and backed off, never redirected: the
            // files belong to this source and wait here until it comes back.
            $health->markFailed($this->sourceKey, "Unable to read {$toSendPath}");

            return;
        }

        foreach ($names as $name) {
            if (! Str::endsWith($name, '.fs')) {
                continue;
            }

            try {
                $this->processFsFile($parser, $spool, $router, $source, $toSendPath, $name);
            } catch (Throwable $e) {
                // One malformed or unreadable file must not abandon the rest of the lane.
                Log::error("ScanFaxSpoolLane [{$this->sourceKey}] failed on {$name}: ".$e->getMessage());
            }
        }

        $health->markHealthy($this->sourceKey);
    }

    private function processFsFile(
        FsFileParser $parser,
        FaxSpool $spool,
        FaxRouter $router,
        ?FaxSpoolSource $source,
        string $toSendPath,
        string $name,
    ): void {
        $fsFile = $toSendPath.$name;
        $contents = @file_get_contents($fsFile);

        if ($contents === false) {
            return;
        }

        $isfax = $parser->parse($name, $contents) + ['source_key' => $this->sourceKey];
        $validator = $parser->validate($isfax);

        if ($validator->fails()) {
            $this->quarantine($spool, $fsFile, $name, implode('. ', $validator->errors()->all()));

            return;
        }

        // Scoped to this source, and deliberately not to a provider: a .fs name is a
        // short per-server Intelligent Series sequence, so the source is what makes it
        // unique. Leaving the provider in would let a re-route resubmit a fax that is
        // already in flight through the other provider.
        $alreadyPending = PendingFax::query()
            ->where('fs_file_name', $isfax['fsFileName'])
            ->where('spool_source_key', $this->sourceKey)
            ->where('delivery_status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return;
        }

        // A .fs whose payload has gone is unsendable, and dispatching it anyway is how a
        // single orphan turns into a failure email every minute for ever: the send job
        // fails, and the job that would move it to fail/ is itself suppressed whenever a
        // lock for that filename is stranded. Quarantine it here instead — straight
        // through the driver, so nothing can swallow it.
        if (! $this->payloadExists($spool, $isfax)) {
            $reason = "Fax payload missing: {$isfax['capfile']}";

            // Recorded as well as quarantined: the operator needs to see that this fax
            // did not go out, and the .cap is gone so it cannot simply be sent again.
            app(FaxFailureLog::class)->recordSubmissionFailure(
                $isfax, $this->providerFor($isfax), $this->sourceKey, $reason
            );

            $this->quarantine($spool, $fsFile, $name, $reason);

            return;
        }

        $route = $router->route($isfax, $source, fn (): ?string => $this->accountNumber($isfax));
        $isfax['routing_reason'] = $route->reason;
        $isfax['allow_failover'] = $route->allowFailover;

        Log::info("Submitting fax job {$isfax['jobID']} [{$this->sourceKey}] via {$route->provider->value} ({$route->reason})");

        $route->provider === FaxProvider::RingCentral
            ? SendFaxRingCentral::dispatch($isfax)
            : SendFaxJob::dispatch($isfax);
    }

    /**
     * The Intelligent Series account this fax belongs to, for account pins.
     *
     * Best effort: the account lookup is a cached query against the IS database, and a
     * fax must still go out when that is unreachable — it just cannot be pinned by
     * account.
     *
     * @param  array<string, mixed>  $isfax
     */
    private function accountNumber(array $isfax): ?string
    {
        try {
            return FaxAccountLookup::make()->forJobId((int) ($isfax['jobID'] ?? 0))['number'] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Move a persistently invalid `.fs` out of the way so it stops re-failing every
     * minute forever. The grace window guards against catching the fax service mid-write.
     */
    /**
     * Which provider this fax would have used, for the failure record.
     *
     * @param  array<string, mixed>  $isfax
     */
    private function providerFor(array $isfax): string
    {
        $source = FaxSpoolSource::findByKey($this->sourceKey);

        return $source?->pinned_provider->value ?? FaxProvider::fallback()->value;
    }

    /**
     * Whether the .cap this .fs points at is actually there.
     *
     * Payloads are shared: Intelligent Series fans one .cap out to several .fs files, so
     * a sibling's successful move can legitimately remove the payload while this .fs is
     * still waiting. That leaves an orphan that can never be sent.
     *
     * @param  array<string, mixed>  $isfax
     */
    private function payloadExists(FaxSpool $spool, array $isfax): bool
    {
        $capfile = (string) ($isfax['capfile'] ?? '');

        if ($capfile === '') {
            return false;
        }

        // Infinity keeps payloads in its own directory; classic IS leaves them beside
        // the .fs in tosend.
        $folder = config('app.switch_engine') === FsFileParser::ENGINE_INFINITY ? 'messages' : 'tosend';

        return $spool->filesystem(FaxProvider::fallback()->value, $this->sourceKey)
            ->read($folder, $capfile) !== null;
    }

    /**
     * Move a .fs that can never be processed out of the way, so it stops being retried —
     * and emailed about — every minute for ever.
     *
     * The grace window guards against catching the fax service mid-write, where the .fs
     * lands a moment before its payload.
     */
    private function quarantine(FaxSpool $spool, string $fsFile, string $name, string $reason): void
    {
        Log::error("Unprocessable fax file {$name} [{$this->sourceKey}]: {$reason}");

        $modified = @filemtime($fsFile);

        if ($modified === false || $modified >= time() - 120) {
            return;
        }

        $filesystem = $spool->filesystem(FaxProvider::fallback()->value, $this->sourceKey);

        if ($filesystem->move('tosend', $name, 'fail', $name)) {
            Log::warning("Quarantined fax file {$name} [{$this->sourceKey}] to fail/: {$reason}");
        }
    }

    public function failed(Throwable $exception): void
    {
        app(FaxSourceHealth::class)->markFailed($this->sourceKey, $exception->getMessage());

        // A timeout kills the worker from inside the signal handler, before
        // CallQueuedHandler releases the unique lock. Without this a single slow scan
        // would silence a healthy lane for the whole uniqueFor window.
        try {
            Cache::lock(UniqueLock::getKey($this))->forceRelease();
        } catch (Throwable $e) {
            Log::warning("ScanFaxSpoolLane [{$this->sourceKey}] could not release its lock: ".$e->getMessage());
        }
    }
}
