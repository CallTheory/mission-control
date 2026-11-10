<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Services\Faxing\RingCentralExtension;
use App\Services\Faxing\RingCentralRateLimit;
use App\Services\Faxing\RingCentralRateLimitState;
use App\Services\Faxing\RingCentralSenderLease;
use App\Services\Faxing\RingCentralSenderSelector;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RingCentralSenderSelectorTest extends TestCase
{
    private RingCentralRateLimitState $state;

    private int $now = 1_800_000_000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = new RingCentralRateLimitState;
    }

    private function selector(int $busyWait = 0): RingCentralSenderSelector
    {
        return new RingCentralSenderSelector($this->state, $busyWait);
    }

    private function extension(string $key): RingCentralExtension
    {
        return new RingCentralExtension($key, $key, "jwt-{$key}");
    }

    private function exhaust(string $key, int $window = 60): void
    {
        $this->state->record($key, new RingCentralRateLimit(status: 200, remaining: 0, window: $window), $this->now);
    }

    private function lockOut(string $key, int $seconds): void
    {
        $this->state->record($key, new RingCentralRateLimit(status: 429, retryAfter: $seconds), $this->now);
    }

    public function test_a_single_extension_with_no_history_is_leased(): void
    {
        $lease = $this->selector()->acquire([$this->extension('primary')], $this->now);

        $this->assertInstanceOf(RingCentralSenderLease::class, $lease);
        $this->assertSame('primary', $lease->extension->key);
    }

    public function test_a_single_extension_out_of_requests_is_held_for_its_window(): void
    {
        $this->exhaust('primary');

        $this->assertSame(60, $this->selector()->acquire([$this->extension('primary')], $this->now));
    }

    public function test_a_single_locked_out_extension_is_held_until_the_lockout_ends(): void
    {
        $this->lockOut('primary', 37);

        $this->assertSame(37, $this->selector()->acquire([$this->extension('primary')], $this->now));
    }

    public function test_a_locked_out_extension_is_skipped_for_an_eligible_one(): void
    {
        $this->lockOut('primary', 60);

        $lease = $this->selector()->acquire([$this->extension('primary'), $this->extension('sender-2')], $this->now);

        $this->assertInstanceOf(RingCentralSenderLease::class, $lease);
        $this->assertSame('sender-2', $lease->extension->key);
    }

    public function test_an_exhausted_extension_is_skipped_for_an_eligible_one(): void
    {
        $this->exhaust('sender-2');

        foreach (range(1, 4) as $ignored) {
            $lease = $this->selector()->acquire([$this->extension('primary'), $this->extension('sender-2'), $this->extension('sender-3')], $this->now);

            $this->assertInstanceOf(RingCentralSenderLease::class, $lease);
            $this->assertNotSame('sender-2', $lease->extension->key);
            $lease->release();
        }
    }

    public function test_when_every_extension_is_out_the_wait_is_until_the_first_lockout_ends(): void
    {
        $this->lockOut('primary', 50);
        $this->exhaust('sender-2', 60);
        $this->lockOut('sender-3', 20);

        $wait = $this->selector()->acquire([$this->extension('primary'), $this->extension('sender-2'), $this->extension('sender-3')], $this->now);

        $this->assertSame(20, $wait);
    }

    public function test_eligible_extensions_are_used_in_rotation(): void
    {
        $extensions = [$this->extension('primary'), $this->extension('sender-2'), $this->extension('sender-3')];
        $used = [];

        foreach (range(1, 6) as $ignored) {
            $lease = $this->selector()->acquire($extensions, $this->now);
            $used[] = $lease->extension->key;
            $lease->release();
        }

        // Every extension takes a turn, and each exactly twice in six sends.
        $this->assertEqualsCanonicalizing(['primary', 'primary', 'sender-2', 'sender-2', 'sender-3', 'sender-3'], $used);
        $this->assertNotSame($used[0], $used[1]);
    }

    public function test_an_extension_mid_send_is_not_leased_twice(): void
    {
        $first = $this->selector()->acquire([$this->extension('primary'), $this->extension('sender-2')], $this->now);
        $second = $this->selector()->acquire([$this->extension('primary'), $this->extension('sender-2')], $this->now);

        $this->assertInstanceOf(RingCentralSenderLease::class, $second);
        $this->assertNotSame($first->extension->key, $second->extension->key);
    }

    public function test_when_the_only_eligible_extension_is_busy_the_fax_comes_back_shortly(): void
    {
        $held = $this->selector()->acquire([$this->extension('primary')], $this->now);

        $this->assertInstanceOf(RingCentralSenderLease::class, $held);
        $this->assertSame(RingCentralSenderSelector::BUSY_RETRY_SECONDS, $this->selector(busyWait: 0)->acquire([$this->extension('primary')], $this->now));

        $held->release();

        $this->assertInstanceOf(RingCentralSenderLease::class, $this->selector()->acquire([$this->extension('primary')], $this->now));
    }

    public function test_state_recorded_by_the_previous_holder_is_rechecked_under_the_lock(): void
    {
        // Another worker holds the lock, sends, and records that the extension is out.
        $lock = Cache::lock('cloud-faxing:ringcentral:send:primary', 150);
        $lock->get();
        $this->exhaust('primary');
        $lock->release();

        $this->assertSame(60, $this->selector()->acquire([$this->extension('primary')], $this->now));
    }

    public function test_seconds_until_available_is_zero_while_any_extension_can_send(): void
    {
        $this->lockOut('primary', 60);

        $this->assertSame(0, $this->selector()->secondsUntilAvailable([$this->extension('primary'), $this->extension('sender-2')], $this->now));
        $this->assertSame(60, $this->selector()->secondsUntilAvailable([$this->extension('primary')], $this->now));
    }

    public function test_the_optional_per_extension_cap_holds_sends_once_reached(): void
    {
        config(['services.fax.ringcentral.sends_per_minute' => 2]);
        $extensions = [$this->extension('primary')];

        $this->selector()->acquire($extensions, $this->now)->release();
        $this->selector()->acquire($extensions, $this->now)->release();

        $this->assertIsInt($this->selector()->acquire($extensions, $this->now));
    }

    public function test_without_a_configured_cap_only_ringcentral_headers_hold_sends(): void
    {
        config(['services.fax.ringcentral.sends_per_minute' => null]);
        $extensions = [$this->extension('primary')];

        foreach (range(1, 25) as $ignored) {
            $lease = $this->selector()->acquire($extensions, $this->now);
            $this->assertInstanceOf(RingCentralSenderLease::class, $lease);
            $lease->release();
        }
    }
}
