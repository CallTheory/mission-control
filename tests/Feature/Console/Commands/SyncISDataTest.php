<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\SyncMergeCommWebHooks;
use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * `intelligent-data:sync` queues the MergeComm web trigger sync.
 *
 * This test never ran: it used `/** @test *\/`, which PHPUnit 12 dropped, so the class read
 * as empty. While it was invisible the dispatch inside the command was commented out, and
 * the scheduler ran a no-op every thirty minutes -- so nothing refreshed
 * `mergecomm_web_triggers`, the catalogue inbound email rules choose a trigger from, and
 * nothing else writes that table.
 *
 * The previous commit made this test assert the no-op, deliberately, so that re-enabling the
 * dispatch would fail here and force someone to make the decision. This is that decision:
 * the dispatch is back, at a daily cadence rather than half-hourly, because a rule with an
 * account set routes through SendEmailToAmtelcoSMTP and never reads the table at all.
 * ScheduleCadenceTest holds the cadence; these hold the dispatch and its guard.
 */
class SyncISDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_the_trigger_sync(): void
    {
        Queue::fake();

        DataSource::create(['is_db_host' => 'sql.example.test']);

        $this->artisan('intelligent-data:sync')->assertExitCode(0);

        Queue::assertPushed(SyncMergeCommWebHooks::class);
    }

    /**
     * The job opens with DataSource::firstOrFail(), so without this guard a daily schedule
     * means a failed job every day on any install that has not connected a switch.
     */
    public function test_it_queues_nothing_without_a_data_source(): void
    {
        Queue::fake();

        $this->artisan('intelligent-data:sync')
            ->expectsOutputToContain('No data source is configured')
            ->assertExitCode(0);

        Queue::assertNotPushed(SyncMergeCommWebHooks::class);
    }
}
