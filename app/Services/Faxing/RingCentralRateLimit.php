<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use Carbon\CarbonImmutable;
use Psr\Http\Message\ResponseInterface;
use RingCentral\SDK\Http\ApiException;
use RingCentral\SDK\Http\ApiResponse;
use Throwable;

/**
 * The rate-limit state RingCentral reports on a response.
 *
 * RingCentral returns X-Rate-Limit-Group/-Limit/-Remaining/-Window on (almost) every
 * response and usually Retry-After on a 429. Limits are per authenticated user per app
 * and can be raised by RingCentral support, so nothing here assumes a particular limit:
 * whatever the headers say is what we act on.
 *
 * Every field is nullable. A missing, empty, negative or otherwise malformed header
 * parses to null rather than to a number, so callers fall back to their own safe default
 * instead of acting on garbage — a Window of "abc" must never become a zero-second wait.
 */
final class RingCentralRateLimit
{
    public function __construct(
        public readonly ?int $status = null,
        public readonly ?string $group = null,
        public readonly ?int $limit = null,
        public readonly ?int $remaining = null,
        public readonly ?int $window = null,
        public readonly ?int $retryAfter = null,
    ) {}

    public static function fromResponse(?ResponseInterface $response): self
    {
        if ($response === null) {
            return new self;
        }

        return self::fromHeaders(
            array_map(fn (array $values) => implode(',', $values), $response->getHeaders()),
            $response->getStatusCode(),
        );
    }

    /**
     * Read the headers off whatever a RingCentral SDK call threw or returned. Anything
     * without a response — a connection failure, a non-SDK exception — yields an empty
     * state rather than an error, because this runs inside error handling.
     */
    public static function fromThrowable(Throwable $e): self
    {
        try {
            // The SDK documents apiResponse() as non-null, but its constructor accepts
            // null, so check rather than trust the docblock inside error handling.
            $apiResponse = $e instanceof ApiException ? $e->apiResponse() : null;

            if ($apiResponse instanceof ApiResponse) {
                return self::fromResponse($apiResponse->response());
            }
        } catch (Throwable) {
            // Fall through to an empty state.
        }

        return new self;
    }

    /**
     * @param  array<string, string|array<int, string>>  $headers  header name => value(s), any case
     */
    public static function fromHeaders(array $headers, ?int $status = null): self
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = is_array($value) ? implode(',', $value) : (string) $value;
        }

        $group = trim($normalized['x-rate-limit-group'] ?? '');

        return new self(
            status: $status !== null && $status > 0 ? $status : null,
            group: $group === '' ? null : $group,
            limit: self::positiveInt($normalized['x-rate-limit-limit'] ?? null),
            remaining: self::nonNegativeInt($normalized['x-rate-limit-remaining'] ?? null),
            window: self::positiveInt($normalized['x-rate-limit-window'] ?? null),
            retryAfter: self::retryAfterSeconds($normalized['retry-after'] ?? null),
        );
    }

    /**
     * True when RingCentral sent any of its rate-limit headers at all. Their absence on a
     * 429 means some other limit (account-level or per-IP) was hit.
     */
    public function hasRateLimitHeaders(): bool
    {
        return $this->group !== null || $this->limit !== null
            || $this->remaining !== null || $this->window !== null;
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    /**
     * The last response used up the window: the next request would be rejected and, worse,
     * would restart RingCentral's penalty clock.
     */
    public function isExhausted(): bool
    {
        return $this->remaining === 0;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toLogContext(): array
    {
        return [
            'http_status' => $this->status,
            'rate_limit_group' => $this->group,
            'rate_limit_limit' => $this->limit,
            'rate_limit_remaining' => $this->remaining,
            'rate_limit_window' => $this->window,
            'retry_after' => $this->retryAfter,
        ];
    }

    private static function nonNegativeInt(?string $value): ?int
    {
        $value = trim((string) $value);

        // ctype_digit rejects "", "-1", "1.5", "10s" — all of which mean the header is not
        // something we understand.
        if ($value === '' || ! ctype_digit($value) || strlen($value) > 9) {
            return null;
        }

        return (int) $value;
    }

    private static function positiveInt(?string $value): ?int
    {
        $int = self::nonNegativeInt($value);

        return $int !== null && $int > 0 ? $int : null;
    }

    /**
     * Retry-After is either delta-seconds or an HTTP-date (RFC 9110 §10.2.3).
     */
    private static function retryAfterSeconds(?string $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return self::nonNegativeInt($value);
        }

        try {
            $date = CarbonImmutable::createFromFormat(DATE_RFC7231, $value);
        } catch (Throwable) {
            return null;
        }

        if ($date === null) {
            return null;
        }

        return max(0, $date->getTimestamp() - CarbonImmutable::now()->getTimestamp());
    }
}
