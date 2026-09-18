<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use RingCentral\SDK\Http\ApiException;
use Throwable;

/**
 * Reads the throttling signals off a RingCentral API error.
 *
 * A 429 is not a fax failure — it is the API telling us to come back later — but it
 * used to arrive as an ApiException indistinguishable from a real send error, burn a
 * retry, and on the third one move a perfectly good fax into fail/. These helpers let
 * the jobs tell the two apart and honour the Retry-After header instead of guessing.
 */
class RingCentralThrottle
{
    /**
     * Fallback delay when the API throttles us without saying for how long. Slightly
     * over a minute so the next attempt lands in a fresh rate-limit window.
     */
    public const DEFAULT_RETRY_AFTER = 65;

    /**
     * Never sit on a job for longer than this, however large a Retry-After we are handed.
     */
    private const MAX_RETRY_AFTER = 900;

    public static function isRateLimited(Throwable $e): bool
    {
        return self::statusCode($e) === 429;
    }

    public static function isUnauthorized(Throwable $e): bool
    {
        return in_array(self::statusCode($e), [400, 401], true) && self::mentionsToken($e);
    }

    /**
     * Seconds to wait before retrying, taken from Retry-After when the API supplies it.
     */
    public static function retryAfter(Throwable $e, int $default = self::DEFAULT_RETRY_AFTER): int
    {
        $header = null;

        if ($e instanceof ApiException) {
            $response = $e->apiResponse()?->response();
            $header = $response?->getHeaderLine('Retry-After');
        }

        $seconds = is_numeric($header) ? (int) $header : $default;

        return max(1, min($seconds, self::MAX_RETRY_AFTER));
    }

    public static function statusCode(Throwable $e): ?int
    {
        if ($e instanceof ApiException) {
            $status = $e->apiResponse()?->response()?->getStatusCode();

            if ($status !== null) {
                return (int) $status;
            }
        }

        $code = $e->getCode();

        return is_int($code) && $code > 0 ? $code : null;
    }

    /**
     * RingCentral answers an expired or revoked token with a 400/401 whose body names
     * the token, which is our cue to drop the shared token rather than retry with it.
     */
    private static function mentionsToken(Throwable $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'token')
            || str_contains(strtolower($e->getMessage()), 'unauthorized');
    }
}
