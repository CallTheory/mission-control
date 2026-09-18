<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Tracks whether provider delivery callbacks are actually reaching us.
 *
 * Webhooks are the cheap way to resolve a fax; isfax:check-pending polling is the
 * fallback, and every poll spends provider API quota that outbound faxes need. Whether
 * webhooks are configured is not otherwise visible from inside the app, so each callback
 * leaves a timestamp and the fax pages surface it.
 */
class FaxDeliveryWebhooks
{
    /**
     * A week — long enough that "last received" stays meaningful across a quiet weekend.
     */
    private const TTL_SECONDS = 604800;

    public static function key(string $provider): string
    {
        return "cloud-faxing:webhook-last-received:{$provider}";
    }

    public static function record(string $provider): void
    {
        try {
            Redis::setEx(self::key($provider), self::TTL_SECONDS, now()->toIso8601String());
        } catch (Throwable $e) {
            Log::warning('Unable to record fax webhook heartbeat: '.$e->getMessage());
        }
    }

    public static function lastReceivedAt(string $provider): ?string
    {
        try {
            return Redis::get(self::key($provider));
        } catch (Throwable $e) {
            Log::warning('Unable to read fax webhook heartbeat: '.$e->getMessage());

            return null;
        }
    }
}
