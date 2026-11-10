<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Services\Faxing\RingCentralRateLimit;
use App\Services\Faxing\RingCentralRateLimitState;
use Tests\TestCase;

class RingCentralRateLimitStateTest extends TestCase
{
    private RingCentralRateLimitState $state;

    private int $now = 1_800_000_000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = new RingCentralRateLimitState;
    }

    public function test_an_extension_with_no_history_may_send(): void
    {
        $this->assertNull($this->state->availableAt('primary', $this->now));
    }

    public function test_requests_remaining_means_no_hold(): void
    {
        $this->state->record('primary', new RingCentralRateLimit(status: 200, limit: 10, remaining: 1, window: 60), $this->now);

        $this->assertNull($this->state->availableAt('primary', $this->now));
    }

    public function test_zero_remaining_holds_the_extension_for_the_window(): void
    {
        $this->state->record('primary', new RingCentralRateLimit(status: 200, limit: 10, remaining: 0, window: 60), $this->now);

        $this->assertSame($this->now + 60, $this->state->availableAt('primary', $this->now));
        $this->assertSame($this->now + 60, $this->state->availableAt('primary', $this->now + 59));
        $this->assertNull($this->state->availableAt('primary', $this->now + 60));
    }

    public function test_the_hold_follows_whatever_window_ringcentral_reports(): void
    {
        $this->state->record('primary', new RingCentralRateLimit(status: 200, remaining: 0, window: 90), $this->now);

        $this->assertSame($this->now + 90, $this->state->availableAt('primary', $this->now));
    }

    public function test_zero_remaining_without_a_window_falls_back_to_the_default(): void
    {
        $this->state->record('primary', new RingCentralRateLimit(status: 200, remaining: 0), $this->now);

        $this->assertSame($this->now + RingCentralRateLimitState::DEFAULT_WINDOW_SECONDS, $this->state->availableAt('primary', $this->now));
    }

    public function test_a_429_locks_the_extension_for_retry_after(): void
    {
        $this->state->record('primary', new RingCentralRateLimit(status: 429, retryAfter: 45), $this->now);

        $this->assertSame($this->now + 45, $this->state->availableAt('primary', $this->now));
    }

    public function test_a_429_carrying_zero_remaining_waits_for_the_later_of_the_two(): void
    {
        $this->state->record('primary', new RingCentralRateLimit(status: 429, remaining: 0, window: 60, retryAfter: 20), $this->now);

        $this->assertSame($this->now + 60, $this->state->availableAt('primary', $this->now));
    }

    public function test_a_later_success_without_headers_does_not_clear_a_lockout(): void
    {
        $this->state->record('primary', new RingCentralRateLimit(status: 429, retryAfter: 45), $this->now);
        $this->state->record('primary', new RingCentralRateLimit(status: 200), $this->now + 1);

        $this->assertSame($this->now + 45, $this->state->availableAt('primary', $this->now + 1));
    }

    public function test_extensions_are_tracked_independently(): void
    {
        $this->state->record('primary', new RingCentralRateLimit(status: 429, retryAfter: 45), $this->now);

        $this->assertNull($this->state->availableAt('sender-2', $this->now));
    }

    public function test_rate_limited_attempts_are_counted_per_fax_and_can_be_reset(): void
    {
        $this->assertSame(1, $this->state->countRateLimitedAttempt('ringcentral:IS20.fs', 7200));
        $this->assertSame(2, $this->state->countRateLimitedAttempt('ringcentral:IS20.fs', 7200));
        $this->assertSame(1, $this->state->countRateLimitedAttempt('ringcentral:IS21.fs', 7200));

        $this->state->forgetRateLimitedAttempts('ringcentral:IS20.fs');

        $this->assertSame(1, $this->state->countRateLimitedAttempt('ringcentral:IS20.fs', 7200));
    }
}
