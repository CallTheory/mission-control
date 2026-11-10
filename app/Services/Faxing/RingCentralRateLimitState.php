<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use Illuminate\Support\Facades\Cache;

/**
 * What each sending extension last heard from RingCentral about its fax rate limit.
 *
 * Shared through the cache (Redis in production) so every worker sees the same picture:
 * if one worker's send came back with X-Rate-Limit-Remaining: 0, no other worker should
 * send on that extension until the window has passed — the request would be rejected and,
 * because a rejected request restarts RingCentral's penalty clock, would make the wait
 * longer for everyone.
 *
 * Only fax *send* responses are recorded here. Status polls and dashboard reads belong to
 * other rate-limit groups with their own allowances; recording them would overwrite the
 * fax group's numbers with unrelated ones.
 *
 * Writes for one extension happen while holding that extension's send lock (see
 * RingCentralSenderSelector), so read-modify-write here does not race.
 */
class RingCentralRateLimitState
{
    /**
     * How long to hold an extension that reported Remaining: 0 without saying how long its
     * window is. RingCentral's documented windows are 60 seconds; this is a fallback for a
     * missing header, not an assumed limit.
     */
    public const DEFAULT_WINDOW_SECONDS = 60;

    private const PREFIX = 'cloud-faxing:ringcentral:rate-limit:';

    public function record(string $extension, RingCentralRateLimit $rateLimit, ?int $now = null): void
    {
        $now ??= now()->getTimestamp();
        $state = $this->read($extension);

        if ($rateLimit->isRateLimited()) {
            $state['locked_until'] = max((int) ($state['locked_until'] ?? 0), $now + RingCentralRetryDelay::lockoutSeconds($rateLimit));
        }

        if ($rateLimit->remaining !== null) {
            $state['remaining'] = $rateLimit->remaining;
            $state['limit'] = $rateLimit->limit;
            $state['window'] = $rateLimit->window;
            $state['group'] = $rateLimit->group;
            $state['observed_at'] = $now;
        }

        if ($state === []) {
            return;
        }

        $expires = max((int) ($state['locked_until'] ?? 0), (int) ($state['observed_at'] ?? 0) + (int) ($state['window'] ?? self::DEFAULT_WINDOW_SECONDS));

        // Keep it a little past the point it stops mattering, so the status page can still
        // show the last numbers RingCentral reported.
        Cache::put($this->key($extension), $state, max(60, $expires - $now) + 600);
    }

    /**
     * When this extension may next send, or null if it may send now.
     */
    public function availableAt(string $extension, ?int $now = null): ?int
    {
        $now ??= now()->getTimestamp();
        $state = $this->read($extension);
        $until = 0;

        if (($state['locked_until'] ?? 0) > $now) {
            $until = (int) $state['locked_until'];
        }

        // Out of requests: hold for the window rather than send and collect a 429. The
        // window is counted from when we saw Remaining hit zero, which is conservative —
        // RingCentral's window slides, so capacity may return a little sooner.
        if (($state['remaining'] ?? null) === 0) {
            $holdUntil = (int) $state['observed_at'] + (int) ($state['window'] ?? self::DEFAULT_WINDOW_SECONDS);

            if ($holdUntil > $now) {
                $until = max($until, $holdUntil);
            }
        }

        return $until > $now ? $until : null;
    }

    /**
     * The raw state, for display.
     *
     * @return array{remaining?: int, limit?: int|null, window?: int|null, group?: string|null, observed_at?: int, locked_until?: int}
     */
    public function snapshot(string $extension): array
    {
        return $this->read($extension);
    }

    public function forget(string $extension): void
    {
        Cache::forget($this->key($extension));
    }

    /**
     * Count one more rate-limited attempt for a fax and return the running total.
     *
     * Kept here rather than on the job: a released job is re-queued from its original
     * payload, so a counter property on the job would reset on every retry.
     */
    public function countRateLimitedAttempt(string $fax, int $ttlSeconds): int
    {
        $key = self::PREFIX.'retries:'.$fax;

        Cache::add($key, 0, $ttlSeconds);

        return (int) Cache::increment($key);
    }

    public function forgetRateLimitedAttempts(string $fax): void
    {
        Cache::forget(self::PREFIX.'retries:'.$fax);
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $extension): array
    {
        $state = Cache::get($this->key($extension));

        return is_array($state) ? $state : [];
    }

    private function key(string $extension): string
    {
        return self::PREFIX.$extension;
    }
}
