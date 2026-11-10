<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Models\DataSource;
use App\Models\PendingFax;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RingCentral\SDK\Http\ApiException;
use Throwable;

/**
 * Every call RingCentral made trying to deliver one fax, from its call log.
 *
 * The message store only says a fax failed ("SendingFailed", "CallFailed"). The call log
 * shows each attempt: when it was dialled, how long the call lasted, and how it ended.
 * The durations are what tell a busy line apart from a number that answers but has no
 * fax machine on it.
 *
 * Fetched only when someone opens a failed fax, and cached in Redis. Call-log reads may
 * share a rate-limit group with fax sending, so a fetch is never made while the sending
 * extension is being held for its limit. The response's rate-limit headers are logged,
 * and recorded against the extension when RingCentral reports the same group as sending.
 */
class RingCentralFaxAttempts
{
    private const CACHE_PREFIX = 'cloud-faxing:ringcentral:call-log:';

    /**
     * Not cached for long when RingCentral had no calls yet: its call log can lag a fax
     * by a few minutes.
     */
    private const EMPTY_CACHE_SECONDS = 120;

    public function __construct(
        private readonly RingCentralSenderPool $pool,
        private readonly RingCentralRateLimitState $state,
    ) {}

    /**
     * @return array{
     *     attempts: list<array{started_at: string, duration: int, result: string, reason: string|null}>,
     *     hint: string|null,
     *     error: string|null,
     *     fetched_at: string|null,
     * }
     */
    public function for(PendingFax $fax): array
    {
        $messageId = (string) $fax->api_fax_id;

        if ($fax->fax_provider !== 'ringcentral' || $messageId === '') {
            return $this->error(__('RingCentral never accepted this fax, so it made no calls to deliver it.'));
        }

        $cached = $this->readCache($messageId);

        if ($cached !== null) {
            return $cached;
        }

        $datasource = DataSource::first();
        $extension = $datasource === null ? null : $this->pool->find($datasource, $fax->ringcentral_sender);

        if ($datasource === null || $extension === null) {
            return $this->error(__('The RingCentral extension that sent this fax is no longer configured.'));
        }

        $availableAt = $this->state->availableAt($extension->key);

        if ($availableAt !== null) {
            return $this->error(__('RingCentral is holding :extension for its rate limit. Try again in :seconds seconds.', [
                'extension' => $extension->label,
                'seconds' => max(1, $availableAt - now()->getTimestamp()),
            ]));
        }

        $client = RingCentralClient::forExtension($datasource, $extension);
        $sentAt = Carbon::parse($fax->submitted_at ?? $fax->created_at);
        $until = Carbon::parse($fax->resolved_at ?? now())->addMinutes(5)->min(now());

        try {
            $response = $client->platform()->get('/restapi/v1.0/account/~/extension/~/call-log', [
                'type' => 'Fax',
                'direction' => 'Outbound',
                'view' => 'Detailed',
                'phoneNumber' => RingCentralRecipient::normalize((string) $fax->phone),
                // A little either side: the call log stamps when the call was dialled, which
                // can be seconds before our clock recorded the submission.
                'dateFrom' => $sentAt->copy()->subMinutes(2)->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'dateTo' => $until->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'perPage' => 100,
            ]);
        } catch (ApiException $e) {
            $this->noteRateLimit($extension, RingCentralRateLimit::fromThrowable($e));

            Log::warning('RingCentral call log lookup failed: '.RingCentralThrottle::describe($e), ['api_fax_id' => $messageId]);

            return $this->error(RingCentralThrottle::isRateLimited($e)
                ? __('RingCentral is rate limiting these lookups. Try again in a minute.')
                : __('RingCentral would not return the call log: :reason', ['reason' => RingCentralThrottle::describe($e)]));
        } catch (Throwable $e) {
            Log::warning('RingCentral call log lookup failed: '.$e->getMessage(), ['api_fax_id' => $messageId]);

            return $this->error(__('Could not reach RingCentral: :reason', ['reason' => $e->getMessage()]));
        }

        $this->noteRateLimit($extension, RingCentralRateLimit::fromResponse($response->response()));

        $attempts = $this->attempts($response->jsonArray()['records'] ?? [], $messageId);
        $result = [
            'attempts' => $attempts,
            'hint' => self::hint($attempts),
            'error' => null,
            'fetched_at' => now()->toIso8601String(),
        ];

        $this->writeCache($messageId, $result, $attempts === [] ? self::EMPTY_CACHE_SECONDS : $this->cacheSeconds());

        return $result;
    }

    /**
     * Only the calls made for *this* fax. The same number may have been dialled for an
     * earlier fax in the same window (Intelligent Series re-submitting one that failed),
     * and those calls belong to that fax, not this one.
     *
     * @param  array<int, mixed>  $records
     * @return list<array{started_at: string, duration: int, result: string, reason: string|null}>
     */
    private function attempts(array $records, string $messageId): array
    {
        $attempts = [];

        foreach ($records as $record) {
            if (! is_array($record) || (string) ($record['message']['id'] ?? '') !== $messageId) {
                continue;
            }

            $result = (string) ($record['result'] ?? 'Unknown');
            $reason = null;

            foreach ($record['legs'] ?? [] as $leg) {
                $legReason = is_array($leg) ? ($leg['reason'] ?? null) : null;

                if (is_string($legReason) && $legReason !== '' && $legReason !== $result) {
                    $reason = $legReason;
                }
            }

            $attempts[] = [
                'started_at' => (string) ($record['startTime'] ?? ''),
                'duration' => (int) ($record['duration'] ?? 0),
                'result' => $result,
                'reason' => $reason,
            ];
        }

        usort($attempts, fn (array $a, array $b) => strcmp($a['started_at'], $b['started_at']));

        return $attempts;
    }

    /**
     * A plain reading of the attempts, when the pattern is clear enough to say anything.
     *
     * @param  list<array{started_at: string, duration: int, result: string, reason: string|null}>  $attempts
     */
    public static function hint(array $attempts): ?string
    {
        if ($attempts === []) {
            return null;
        }

        $results = array_map(fn (array $a) => strtolower($a['result'].' '.($a['reason'] ?? '')), $attempts);
        $durations = array_column($attempts, 'duration');
        $count = count($attempts);

        if (array_filter($results, fn (string $r) => str_contains($r, 'sent') && ! str_contains($r, 'not sent')) !== []) {
            return null;
        }

        if (array_filter($results, fn (string $r) => str_contains($r, 'busy')) !== []) {
            return __('The line was busy on at least one attempt. If this keeps happening at the same time of day, the recipient\'s fax line is in use then.');
        }

        if (array_filter($results, fn (string $r) => str_contains($r, 'no answer')) !== []) {
            return __('Nothing answered. The fax machine may be switched off or disconnected.');
        }

        if (min($durations) >= 45) {
            return trans_choice(
                '{1} The call was answered and ran for about a minute without a fax connecting. That usually means a person, voicemail or phone menu answered, or a fax machine that could not complete the handshake. Call the number to check it is a working fax line.|[2,*] All :count calls were answered and ran for about a minute without a fax connecting. That usually means a person, voicemail or phone menu answered, or a fax machine that could not complete the handshake. Call the number to check it is a working fax line.',
                $count,
                ['count' => $count],
            );
        }

        if (max($durations) < 15) {
            return __('Every call ended within a few seconds without connecting. Check that the number is correct and can receive calls.');
        }

        return null;
    }

    /**
     * Call-log reads are recorded against the extension only when RingCentral says they
     * draw on the same allowance as sending; otherwise they would overwrite the fax
     * group's numbers with unrelated ones.
     */
    private function noteRateLimit(RingCentralExtension $extension, RingCentralRateLimit $rateLimit): void
    {
        Log::info('RingCentral call log response', $rateLimit->toLogContext() + ['ringcentral_sender' => $extension->key]);

        $sendingGroup = $this->state->snapshot($extension->key)['group'] ?? null;

        if ($rateLimit->group !== null && $rateLimit->group === $sendingGroup) {
            $this->state->record($extension->key, $rateLimit);
        }
    }

    private function cacheSeconds(): int
    {
        return max(60, (int) config('services.fax.ringcentral.call_log_cache_seconds', 1800));
    }

    /**
     * @return array{attempts: list<array{started_at: string, duration: int, result: string, reason: string|null}>, hint: string|null, error: string|null, fetched_at: string|null}|null
     */
    private function readCache(string $messageId): ?array
    {
        try {
            $payload = Redis::get(self::CACHE_PREFIX.$messageId);
        } catch (Throwable $e) {
            return null;
        }

        $decoded = is_string($payload) ? json_decode($payload, true) : null;

        return is_array($decoded) && isset($decoded['attempts']) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function writeCache(string $messageId, array $result, int $seconds): void
    {
        try {
            Redis::setEx(self::CACHE_PREFIX.$messageId, $seconds, json_encode($result, JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            // An uncached lookup is only a slower one.
            Log::warning('Could not cache the RingCentral call log: '.$e->getMessage());
        }
    }

    /**
     * Errors are never cached: each is something that should be retried, not remembered.
     *
     * @return array{attempts: list<array{started_at: string, duration: int, result: string, reason: string|null}>, hint: null, error: string, fetched_at: null}
     */
    private function error(string $message): array
    {
        return ['attempts' => [], 'hint' => null, 'error' => $message, 'fetched_at' => null];
    }
}
