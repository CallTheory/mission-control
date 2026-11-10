<?php

declare(strict_types=1);

namespace Tests\Unit\Faxing;

use App\Services\Faxing\RingCentralRateLimit;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RingCentralRateLimitTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_reads_every_header_off_a_response(): void
    {
        $rateLimit = RingCentralRateLimit::fromResponse(new Response(429, [
            'X-Rate-Limit-Group' => 'heavy',
            'X-Rate-Limit-Limit' => '10',
            'X-Rate-Limit-Remaining' => '0',
            'X-Rate-Limit-Window' => '60',
            'Retry-After' => '42',
        ]));

        $this->assertSame(429, $rateLimit->status);
        $this->assertSame('heavy', $rateLimit->group);
        $this->assertSame(10, $rateLimit->limit);
        $this->assertSame(0, $rateLimit->remaining);
        $this->assertSame(60, $rateLimit->window);
        $this->assertSame(42, $rateLimit->retryAfter);
        $this->assertTrue($rateLimit->isRateLimited());
        $this->assertTrue($rateLimit->isExhausted());
        $this->assertTrue($rateLimit->hasRateLimitHeaders());
    }

    public function test_header_names_are_case_insensitive(): void
    {
        $rateLimit = RingCentralRateLimit::fromHeaders(['x-rate-limit-remaining' => '3', 'RETRY-AFTER' => '5']);

        $this->assertSame(3, $rateLimit->remaining);
        $this->assertSame(5, $rateLimit->retryAfter);
    }

    public function test_a_raised_limit_is_taken_from_the_headers_not_assumed(): void
    {
        $rateLimit = RingCentralRateLimit::fromHeaders(['X-Rate-Limit-Limit' => '40', 'X-Rate-Limit-Remaining' => '39']);

        $this->assertSame(40, $rateLimit->limit);
        $this->assertFalse($rateLimit->isExhausted());
    }

    public function test_missing_headers_leave_every_field_null(): void
    {
        $rateLimit = RingCentralRateLimit::fromResponse(new Response(200));

        $this->assertSame(200, $rateLimit->status);
        $this->assertNull($rateLimit->group);
        $this->assertNull($rateLimit->limit);
        $this->assertNull($rateLimit->remaining);
        $this->assertNull($rateLimit->window);
        $this->assertNull($rateLimit->retryAfter);
        $this->assertFalse($rateLimit->hasRateLimitHeaders());
        // Unknown is not the same as exhausted: no headers must never hold a send.
        $this->assertFalse($rateLimit->isExhausted());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function malformedNumbers(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'negative' => ['-1'],
            'decimal' => ['1.5'],
            'with unit' => ['60s'],
            'word' => ['abc'],
            'absurdly long' => ['99999999999999999999'],
        ];
    }

    #[DataProvider('malformedNumbers')]
    public function test_malformed_numbers_parse_to_null(string $value): void
    {
        $rateLimit = RingCentralRateLimit::fromHeaders([
            'X-Rate-Limit-Limit' => $value,
            'X-Rate-Limit-Remaining' => $value,
            'X-Rate-Limit-Window' => $value,
            'Retry-After' => $value,
        ]);

        $this->assertNull($rateLimit->limit);
        $this->assertNull($rateLimit->remaining);
        $this->assertNull($rateLimit->window);
        $this->assertNull($rateLimit->retryAfter);
    }

    public function test_a_zero_window_or_limit_is_treated_as_malformed(): void
    {
        // A zero-second window would turn "hold for the window" into "don't hold at all".
        $rateLimit = RingCentralRateLimit::fromHeaders(['X-Rate-Limit-Window' => '0', 'X-Rate-Limit-Limit' => '0']);

        $this->assertNull($rateLimit->window);
        $this->assertNull($rateLimit->limit);
    }

    public function test_zero_remaining_is_meaningful(): void
    {
        $this->assertSame(0, RingCentralRateLimit::fromHeaders(['X-Rate-Limit-Remaining' => '0'])->remaining);
    }

    public function test_retry_after_accepts_an_http_date(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 14:03:00 UTC'));

        $rateLimit = RingCentralRateLimit::fromHeaders(['Retry-After' => 'Mon, 09 Nov 2026 14:03:45 GMT']);

        $this->assertSame(45, $rateLimit->retryAfter);
    }

    public function test_a_retry_after_date_in_the_past_means_now(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 14:03:00 UTC'));

        $this->assertSame(0, RingCentralRateLimit::fromHeaders(['Retry-After' => 'Mon, 09 Nov 2026 14:00:00 GMT'])->retryAfter);
    }

    public function test_an_exception_without_a_response_yields_an_empty_state(): void
    {
        $rateLimit = RingCentralRateLimit::fromThrowable(new RuntimeException('connection refused'));

        $this->assertNull($rateLimit->status);
        $this->assertFalse($rateLimit->hasRateLimitHeaders());
    }

    public function test_log_context_names_every_field(): void
    {
        $this->assertSame(
            ['http_status', 'rate_limit_group', 'rate_limit_limit', 'rate_limit_remaining', 'rate_limit_window', 'retry_after'],
            array_keys((new RingCentralRateLimit)->toLogContext()),
        );
    }
}
