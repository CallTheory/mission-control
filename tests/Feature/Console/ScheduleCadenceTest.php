<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * The cadence of the MergeComm trigger sync, which was a deliberate decision rather than a
 * default.
 *
 * It ran every thirty minutes while its body was commented out. Re-enabling it at that
 * cadence would mean half-hourly queries against the switch for a catalogue that most
 * inbound email rules never read -- a rule with an account set routes through Amtelco SMTP
 * and never touches it. Daily, with the manual run on System -> Tools for when somebody has
 * just added a trigger.
 */
class ScheduleCadenceTest extends TestCase
{
    private function expressionFor(string $command): string
    {
        $events = app(Schedule::class)->events();

        foreach ($events as $event) {
            if (str_contains($event->command ?? '', $command)) {
                return $event->expression;
            }
        }

        $this->fail("[{$command}] is not scheduled at all.");
    }

    public function test_the_trigger_sync_runs_daily(): void
    {
        // '0 0 * * *' is Laravel's daily(). Asserted as the expression rather than by calling
        // daily() back, so changing the schedule to anything else fails here and has to be
        // argued for.
        $this->assertSame('0 0 * * *', $this->expressionFor('intelligent-data:sync'));
    }

    /**
     * A bare withoutOverlapping() holds its mutex for 24 hours. On a daily command that can
     * swallow an entire run, so this one passes an explicit expiry.
     */
    public function test_the_trigger_sync_does_not_hold_a_day_long_mutex(): void
    {
        $events = app(Schedule::class)->events();

        foreach ($events as $event) {
            if (str_contains($event->command ?? '', 'intelligent-data:sync')) {
                $this->assertLessThan(1440, $event->expiresAt, 'The overlap mutex outlives the gap between runs.');

                return;
            }
        }

        $this->fail('intelligent-data:sync is not scheduled at all.');
    }
}
