<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Models\FaxSpoolSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tracks whether each spool source is answering, and spaces out retries when one is not.
 *
 * **This is not failover, and must never become it.** The only thing recorded here
 * influences is *when* a source is next scanned. It never redirects a fax, never
 * nominates a substitute source, and never moves a file between sources: the files in an
 * unreachable source's tosend/ belong to that Intelligent Series server and wait there
 * until it answers again. Which server is live is IS's decision, not ours — see
 * FaxLaneRegistry.
 *
 * Reachability is also not the same as activity. Only one IS server processes faxes at a
 * time; the others sit perfectly healthy with an empty tosend/. An empty scan is a
 * success, not a fault.
 *
 * The cache carries the hot path so a source failing every minute does not mean a write
 * per minute; the database is updated on transitions and then at most every fifteen
 * minutes, because its copy exists for the admin screen rather than for counting.
 *
 * Cooldowns are stored as an absolute expiry timestamp rather than leaning on the store's
 * TTL, so how long a source is being rested can be reported on any cache driver instead
 * of only the ones that expose a TTL.
 */
class FaxSourceHealth
{
    /**
     * Seconds to wait before scanning again, indexed by consecutive failure count. A
     * server that is simply switched off should cost one attempt every half hour, not
     * one a minute plus a log line.
     *
     * @var array<int, int>
     */
    private const BACKOFF = [0, 60, 120, 300, 600, 1800];

    private const DB_SYNC_SECONDS = 900;

    public function markFailed(string $sourceKey, string $reason): void
    {
        $reason = Str::limit($reason, 900);

        try {
            $failures = ((int) Cache::get($this->key($sourceKey, 'failures'), 0)) + 1;

            Cache::put($this->key($sourceKey, 'failures'), $failures, 86400);
            Cache::put($this->key($sourceKey, 'last-error'), $reason, 86400);

            $cooldown = self::BACKOFF[min($failures, count(self::BACKOFF) - 1)];

            if ($cooldown > 0) {
                Cache::put($this->key($sourceKey, 'cooldown'), now()->addSeconds($cooldown)->timestamp, $cooldown);
            }
        } catch (Throwable $e) {
            $failures = 1;
        }

        Log::warning("Fax spool source [{$sourceKey}] unreachable: {$reason}");

        if ($failures === 1 || $this->databaseCopyIsStale($sourceKey)) {
            $this->persist($sourceKey, $failures, $reason);
        }
    }

    public function markHealthy(string $sourceKey): void
    {
        try {
            $failures = (int) Cache::get($this->key($sourceKey, 'failures'), 0);

            Cache::put($this->key($sourceKey, 'last-healthy'), now()->toIso8601String(), 604800);
            Cache::forget($this->key($sourceKey, 'failures'));
            Cache::forget($this->key($sourceKey, 'cooldown'));
        } catch (Throwable $e) {
            $failures = 0;
        }

        // Only a transition is worth a write; a healthy source is the common case and
        // scans every minute.
        if ($failures > 0) {
            Log::info("Fax spool source [{$sourceKey}] is answering again after {$failures} failed scans.");
            $this->persist($sourceKey, 0, null);
        }
    }

    /**
     * Whether this source is being rested after repeated failures.
     *
     * Fails *open* when Redis is unavailable: an unknown health state must mean "scan it"
     * rather than "skip it", or a Redis outage would silently stop faxing everywhere.
     */
    public function inCooldown(string $sourceKey): bool
    {
        return $this->cooldownRemaining($sourceKey) > 0;
    }

    public function cooldownRemaining(string $sourceKey): int
    {
        try {
            $expiresAt = Cache::get($this->key($sourceKey, 'cooldown'));
        } catch (Throwable $e) {
            return 0;
        }

        return $expiresAt === null ? 0 : max(0, (int) $expiresAt - now()->timestamp);
    }

    /**
     * @return array{failures: int, last_error: ?string, last_healthy_at: ?string}
     */
    public function status(string $sourceKey): array
    {
        try {
            return [
                'failures' => (int) Cache::get($this->key($sourceKey, 'failures'), 0),
                'last_error' => Cache::get($this->key($sourceKey, 'last-error')),
                'last_healthy_at' => Cache::get($this->key($sourceKey, 'last-healthy')),
            ];
        } catch (Throwable $e) {
            return ['failures' => 0, 'last_error' => null, 'last_healthy_at' => null];
        }
    }

    private function databaseCopyIsStale(string $sourceKey): bool
    {
        $source = FaxSpoolSource::findByKey($sourceKey);

        return $source === null
            || $source->health_synced_at === null
            || $source->health_synced_at->lt(now()->subSeconds(self::DB_SYNC_SECONDS));
    }

    private function persist(string $sourceKey, int $failures, ?string $error): void
    {
        try {
            FaxSpoolSource::query()->where('key', $sourceKey)->update([
                'consecutive_failures' => $failures,
                'last_error' => $error,
                'last_error_at' => $error === null ? null : now(),
                'last_healthy_at' => $failures === 0 ? now() : null,
                'health_synced_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning("Unable to record fax source health for [{$sourceKey}]: ".$e->getMessage());
        }
    }

    private function key(string $sourceKey, string $suffix): string
    {
        return "cloud-faxing:source-health:{$sourceKey}:{$suffix}";
    }
}
