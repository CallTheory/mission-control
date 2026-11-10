<?php

declare(strict_types=1);

namespace Tests\Unit\Faxing;

use App\Services\Faxing\RingCentralRateLimit;
use App\Services\Faxing\RingCentralRetryDelay;
use Tests\TestCase;

class RingCentralRetryDelayTest extends TestCase
{
    public function test_retry_after_wins_when_present(): void
    {
        $this->assertSame(42, RingCentralRetryDelay::lockoutSeconds(new RingCentralRateLimit(status: 429, window: 60, retryAfter: 42)));
    }

    public function test_the_window_is_used_without_retry_after(): void
    {
        $this->assertSame(60, RingCentralRetryDelay::lockoutSeconds(new RingCentralRateLimit(status: 429, window: 60)));
    }

    public function test_a_raised_window_is_honoured_not_a_hard_coded_minute(): void
    {
        $this->assertSame(120, RingCentralRetryDelay::lockoutSeconds(new RingCentralRateLimit(status: 429, window: 120)));
    }

    public function test_with_neither_header_the_documented_fallback_applies(): void
    {
        $this->assertSame(RingCentralRetryDelay::FALLBACK_SECONDS, RingCentralRetryDelay::lockoutSeconds(new RingCentralRateLimit(status: 429)));
    }

    public function test_a_zero_retry_after_still_waits_a_second(): void
    {
        $this->assertSame(1, RingCentralRetryDelay::lockoutSeconds(new RingCentralRateLimit(status: 429, retryAfter: 0)));
    }

    public function test_an_absurd_retry_after_is_capped(): void
    {
        $this->assertSame(RingCentralRetryDelay::MAX_SECONDS, RingCentralRetryDelay::lockoutSeconds(new RingCentralRateLimit(status: 429, retryAfter: 999999)));
    }

    public function test_jitter_adds_between_one_and_the_configured_seconds(): void
    {
        config(['services.fax.ringcentral.rate_limit_jitter_seconds' => 5]);
        $delay = new RingCentralRetryDelay;

        $seen = [];

        for ($i = 0; $i < 200; $i++) {
            $seen[] = $delay->withJitter(60);
        }

        $this->assertGreaterThanOrEqual(61, min($seen));
        $this->assertLessThanOrEqual(65, max($seen));
        // Not all identical: the point of jitter is that held faxes do not return together.
        $this->assertGreaterThan(1, count(array_unique($seen)));
    }

    public function test_jitter_can_be_turned_off(): void
    {
        config(['services.fax.ringcentral.rate_limit_jitter_seconds' => 0]);

        $this->assertSame(60, (new RingCentralRetryDelay)->withJitter(60));
    }

    public function test_a_zero_wait_still_becomes_at_least_one_second(): void
    {
        config(['services.fax.ringcentral.rate_limit_jitter_seconds' => 0]);

        $this->assertSame(1, (new RingCentralRetryDelay)->withJitter(0));
    }
}
