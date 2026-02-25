<?php

declare(strict_types=1);

namespace Tests\Feature\VoicemailDigest;

use App\Jobs\SendVoicemailDigest;
use App\Models\Team;
use App\Models\VoicemailDigest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProcessScheduledVoicemailDigestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-26 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Storage::deleteDirectory('feature-flags');
        parent::tearDown();
    }

    public function test_it_exits_successfully_when_feature_flag_disabled(): void
    {
        $this->artisan('voicemail-digest:process')
            ->expectsOutput('Voicemail digest feature is not enabled.')
            ->assertExitCode(0);
    }

    public function test_it_exits_successfully_when_no_schedules_are_due(): void
    {
        $this->enableSystemFeatureFlag();

        VoicemailDigest::factory()->create([
            'enabled' => true,
            'next_run_at' => Carbon::now()->addHour(),
        ]);

        $this->artisan('voicemail-digest:process')
            ->expectsOutput('No voicemail digest schedules are due.')
            ->assertExitCode(0);
    }

    public function test_it_processes_due_schedules_with_null_next_run_at(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $digest = VoicemailDigest::factory()->daily()->create([
            'team_id' => $team->id,
            'name' => 'Test Schedule',
            'enabled' => true,
            'next_run_at' => null,
            'schedule_time' => '08:00',
        ]);

        $this->artisan('voicemail-digest:process')
            ->expectsOutput("Processing voicemail digest schedule: Test Schedule (ID: {$digest->id})")
            ->assertExitCode(0);

        Queue::assertPushed(SendVoicemailDigest::class, function ($job) use ($digest) {
            return $job->schedule->id === $digest->id;
        });

        $digest->refresh();
        $this->assertNotNull($digest->last_run_at);
        $this->assertNotNull($digest->next_run_at);
        $this->assertEquals(Carbon::now(), $digest->last_run_at);
    }

    public function test_it_processes_schedules_with_past_next_run_at(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $digest = VoicemailDigest::factory()->daily()->create([
            'team_id' => $team->id,
            'enabled' => true,
            'next_run_at' => Carbon::now()->subHour(),
            'schedule_time' => '15:00',
        ]);

        $this->artisan('voicemail-digest:process')
            ->assertExitCode(0);

        Queue::assertPushed(SendVoicemailDigest::class, 1);

        $digest->refresh();
        $this->assertEquals(Carbon::now(), $digest->last_run_at);
        $this->assertGreaterThanOrEqual(Carbon::now(), $digest->next_run_at);
    }

    public function test_it_processes_schedules_with_current_next_run_at(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $digest = VoicemailDigest::factory()->hourly()->create([
            'team_id' => $team->id,
            'enabled' => true,
            'next_run_at' => Carbon::now(),
        ]);

        $this->artisan('voicemail-digest:process')
            ->assertExitCode(0);

        Queue::assertPushed(SendVoicemailDigest::class, 1);
    }

    public function test_it_skips_disabled_schedules(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        VoicemailDigest::factory()->create([
            'team_id' => $team->id,
            'enabled' => false,
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $this->artisan('voicemail-digest:process')
            ->expectsOutput('No voicemail digest schedules are due.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_it_skips_schedules_with_future_next_run_at(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        VoicemailDigest::factory()->create([
            'team_id' => $team->id,
            'enabled' => true,
            'next_run_at' => Carbon::now()->addHour(),
        ]);

        $this->artisan('voicemail-digest:process')
            ->expectsOutput('No voicemail digest schedules are due.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_it_skips_schedules_when_team_utility_disabled(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => false,
        ]);

        $digest = VoicemailDigest::factory()->create([
            'team_id' => $team->id,
            'enabled' => true,
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $this->artisan('voicemail-digest:process')
            ->assertExitCode(0);

        Queue::assertNothingPushed();

        $digest->refresh();
        $this->assertNull($digest->last_run_at);
    }

    public function test_it_processes_multiple_due_schedules(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $digest1 = VoicemailDigest::factory()->create([
            'team_id' => $team->id,
            'name' => 'Schedule 1',
            'enabled' => true,
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $digest2 = VoicemailDigest::factory()->create([
            'team_id' => $team->id,
            'name' => 'Schedule 2',
            'enabled' => true,
            'next_run_at' => Carbon::now()->subMinutes(30),
        ]);

        $digest3 = VoicemailDigest::factory()->create([
            'team_id' => $team->id,
            'name' => 'Schedule 3',
            'enabled' => true,
            'next_run_at' => Carbon::now()->addHour(),
        ]);

        $this->artisan('voicemail-digest:process')
            ->expectsOutput("Processing voicemail digest schedule: Schedule 1 (ID: {$digest1->id})")
            ->expectsOutput("Processing voicemail digest schedule: Schedule 2 (ID: {$digest2->id})")
            ->assertExitCode(0);

        Queue::assertPushed(SendVoicemailDigest::class, 2);
        Queue::assertPushed(SendVoicemailDigest::class, function ($job) use ($digest1) {
            return $job->schedule->id === $digest1->id;
        });
        Queue::assertPushed(SendVoicemailDigest::class, function ($job) use ($digest2) {
            return $job->schedule->id === $digest2->id;
        });
    }

    public function test_it_dispatches_job_with_correct_date_range_for_hourly(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $digest = VoicemailDigest::factory()->hourly()->create([
            'team_id' => $team->id,
            'enabled' => true,
            'next_run_at' => Carbon::now(),
        ]);

        $this->artisan('voicemail-digest:process')
            ->assertExitCode(0);

        Queue::assertPushed(SendVoicemailDigest::class, function ($job) {
            $expectedStart = Carbon::now()->subHour();
            $expectedEnd = Carbon::now();

            return abs($job->startDate->diffInSeconds($expectedStart)) < 2
                && abs($job->endDate->diffInSeconds($expectedEnd)) < 2;
        });
    }

    public function test_it_dispatches_job_with_correct_date_range_for_daily(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $digest = VoicemailDigest::factory()->daily()->create([
            'team_id' => $team->id,
            'enabled' => true,
            'next_run_at' => Carbon::now(),
        ]);

        $this->artisan('voicemail-digest:process')
            ->assertExitCode(0);

        Queue::assertPushed(SendVoicemailDigest::class, function ($job) {
            $expectedStart = Carbon::now()->subDay();
            $expectedEnd = Carbon::now();

            return abs($job->startDate->diffInSeconds($expectedStart)) < 2
                && abs($job->endDate->diffInSeconds($expectedEnd)) < 2;
        });
    }

    public function test_it_updates_last_run_at_timestamp(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $digest = VoicemailDigest::factory()->create([
            'team_id' => $team->id,
            'enabled' => true,
            'next_run_at' => Carbon::now()->subHour(),
            'last_run_at' => Carbon::now()->subDay(),
        ]);

        $oldLastRun = $digest->last_run_at;

        $this->artisan('voicemail-digest:process')
            ->assertExitCode(0);

        $digest->refresh();
        $this->assertNotEquals($oldLastRun, $digest->last_run_at);
        $this->assertEquals(Carbon::now(), $digest->last_run_at);
    }

    public function test_it_calculates_and_updates_next_run_at(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $digest = VoicemailDigest::factory()->daily()->create([
            'team_id' => $team->id,
            'enabled' => true,
            'next_run_at' => Carbon::now()->subHour(),
            'schedule_time' => '15:00',
            'timezone' => 'America/New_York',
        ]);

        $this->artisan('voicemail-digest:process')
            ->expectsOutputToContain('Next run:')
            ->assertExitCode(0);

        $digest->refresh();
        $this->assertNotNull($digest->next_run_at);
        $this->assertGreaterThanOrEqual(Carbon::now(), $digest->next_run_at);
    }

    public function test_it_outputs_next_run_time_in_console(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $digest = VoicemailDigest::factory()->hourly()->create([
            'team_id' => $team->id,
            'name' => 'Console Test',
            'enabled' => true,
            'next_run_at' => Carbon::now(),
        ]);

        $this->artisan('voicemail-digest:process')
            ->expectsOutputToContain("Dispatched job for schedule {$digest->id}. Next run:")
            ->assertExitCode(0);
    }

    public function test_it_handles_schedules_from_multiple_teams(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team1 = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);
        $team2 = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $digest1 = VoicemailDigest::factory()->create([
            'team_id' => $team1->id,
            'enabled' => true,
            'next_run_at' => Carbon::now(),
        ]);

        $digest2 = VoicemailDigest::factory()->create([
            'team_id' => $team2->id,
            'enabled' => true,
            'next_run_at' => Carbon::now(),
        ]);

        $this->artisan('voicemail-digest:process')
            ->assertExitCode(0);

        Queue::assertPushed(SendVoicemailDigest::class, 2);
    }

    public function test_it_dispatches_job_with_correct_date_range_for_immediate(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $lastRun = Carbon::now()->subMinutes(5);
        $digest = VoicemailDigest::factory()->immediate()->create([
            'team_id' => $team->id,
            'enabled' => true,
            'next_run_at' => Carbon::now(),
            'last_run_at' => $lastRun,
        ]);

        $this->artisan('voicemail-digest:process')
            ->assertExitCode(0);

        Queue::assertPushed(SendVoicemailDigest::class, function ($job) use ($lastRun) {
            return abs($job->startDate->diffInSeconds($lastRun)) < 2
                && abs($job->endDate->diffInSeconds(Carbon::now())) < 2;
        });
    }

    public function test_it_dispatches_job_with_fallback_range_for_immediate_first_run(): void
    {
        Queue::fake();
        $this->enableSystemFeatureFlag();

        $team = Team::factory()->create([
            'utility_voicemail_digest' => true,
        ]);

        $digest = VoicemailDigest::factory()->immediate()->create([
            'team_id' => $team->id,
            'enabled' => true,
            'next_run_at' => null,
            'last_run_at' => null,
        ]);

        $this->artisan('voicemail-digest:process')
            ->assertExitCode(0);

        Queue::assertPushed(SendVoicemailDigest::class, function ($job) {
            $expectedStart = Carbon::now()->subHour();

            return abs($job->startDate->diffInSeconds($expectedStart)) < 2
                && abs($job->endDate->diffInSeconds(Carbon::now())) < 2;
        });
    }

    private function enableSystemFeatureFlag(): void
    {
        Storage::makeDirectory('feature-flags');
        $encrypted = encrypt('voicemail-digest');
        Storage::put('feature-flags/voicemail-digest.flag', $encrypted);
    }
}
