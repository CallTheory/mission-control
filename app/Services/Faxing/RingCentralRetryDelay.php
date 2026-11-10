<?php

declare(strict_types=1);

namespace App\Services\Faxing;

/**
 * How long to wait after RingCentral rate-limits a fax, and the jitter added on top.
 */
class RingCentralRetryDelay
{
    /**
     * RingCentral's guidance when a 429 carries neither Retry-After nor a window: "retried
     * in 30 seconds or later". A fallback for missing headers, not an assumed limit.
     */
    public const FALLBACK_SECONDS = 30;

    /**
     * Ceiling on any single wait. A larger value is far more likely to be a malformed
     * header than a real instruction, and retryUntil bounds the whole fax anyway.
     */
    public const MAX_SECONDS = 3600;

    /**
     * Seconds the extension is locked out for after a 429: Retry-After if RingCentral sent
     * it, otherwise the rate-limit window, otherwise the documented fallback.
     */
    public static function lockoutSeconds(RingCentralRateLimit $rateLimit): int
    {
        $seconds = $rateLimit->retryAfter ?? $rateLimit->window ?? self::FALLBACK_SECONDS;

        return max(1, min($seconds, self::MAX_SECONDS));
    }

    /**
     * Add a few random seconds so a queue of held faxes does not come back in the same
     * instant — every one of them would hit RingCentral together and, during a lockout,
     * each rejected request restarts the penalty clock.
     *
     * The jitter is small next to the gap between scheduled faxes, so faxes still come
     * back in the order they were due; only faxes due within the same few seconds can
     * swap places.
     */
    public function withJitter(int $seconds): int
    {
        $jitter = max(0, (int) config('services.fax.ringcentral.rate_limit_jitter_seconds', 5));

        return max(1, min($seconds, self::MAX_SECONDS)) + ($jitter > 0 ? random_int(1, $jitter) : 0);
    }
}
