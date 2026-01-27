<?php

declare(strict_types=1);

namespace Tests\Feature\VoicemailDigest;

use App\Models\Team;
use App\Models\VoicemailDigest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VoicemailDigestModelTest extends TestCase
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
        parent::tearDown();
    }

    public function test_it_belongs_to_a_team(): void
    {
        $team = Team::factory()->create();
        $digest = VoicemailDigest::factory()->create(['team_id' => $team->id]);

        $this->assertInstanceOf(Team::class, $digest->team);
        $this->assertEquals($team->id, $digest->team->id);
    }

    public function test_it_casts_recipients_to_array(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'recipients' => ['test1@example.com', 'test2@example.com'],
        ]);

        $this->assertIsArray($digest->recipients);
        $this->assertCount(2, $digest->recipients);
        $this->assertEquals('test1@example.com', $digest->recipients[0]);
    }

    public function test_it_casts_booleans_correctly(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'include_transcription' => true,
            'include_call_metadata' => false,
            'enabled' => true,
        ]);

        $this->assertIsBool($digest->include_transcription);
        $this->assertIsBool($digest->include_call_metadata);
        $this->assertIsBool($digest->enabled);
        $this->assertTrue($digest->include_transcription);
        $this->assertFalse($digest->include_call_metadata);
    }

    public function test_it_casts_dates_correctly(): void
    {
        $now = Carbon::now();
        $digest = VoicemailDigest::factory()->create([
            'last_run_at' => $now,
            'next_run_at' => $now->copy()->addDay(),
        ]);

        $this->assertInstanceOf(Carbon::class, $digest->last_run_at);
        $this->assertInstanceOf(Carbon::class, $digest->next_run_at);
    }

    public function test_calculate_next_run_at_for_hourly_schedule(): void
    {
        $digest = VoicemailDigest::factory()->hourly()->create([
            'timezone' => 'America/New_York',
        ]);

        $from = Carbon::parse('2026-01-26 10:30:00', 'America/New_York');
        $nextRun = $digest->calculateNextRunAt($from);

        $this->assertEquals('2026-01-26 11:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_next_run_at_for_daily_schedule_same_day(): void
    {
        $digest = VoicemailDigest::factory()->daily()->create([
            'schedule_time' => '15:00',
            'timezone' => 'America/New_York',
        ]);

        $from = Carbon::parse('2026-01-26 10:00:00', 'America/New_York');
        $nextRun = $digest->calculateNextRunAt($from);

        $this->assertEquals('2026-01-26 15:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_next_run_at_for_daily_schedule_next_day(): void
    {
        $digest = VoicemailDigest::factory()->daily()->create([
            'schedule_time' => '08:00',
            'timezone' => 'America/New_York',
        ]);

        $from = Carbon::parse('2026-01-26 10:00:00', 'America/New_York');
        $nextRun = $digest->calculateNextRunAt($from);

        $this->assertEquals('2026-01-27 08:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_next_run_at_for_weekly_schedule(): void
    {
        $digest = VoicemailDigest::factory()->weekly()->create([
            'schedule_time' => '09:00',
            'schedule_day_of_week' => 1, // Monday
            'timezone' => 'America/New_York',
        ]);

        // From Sunday 2026-01-25
        $from = Carbon::parse('2026-01-25 10:00:00', 'America/New_York');
        $nextRun = $digest->calculateNextRunAt($from);

        // Should be Monday 2026-01-26 at 09:00
        $this->assertEquals('2026-01-26 09:00:00', $nextRun->format('Y-m-d H:i:s'));
        $this->assertEquals(Carbon::MONDAY, $nextRun->dayOfWeek);
    }

    public function test_calculate_next_run_at_for_weekly_schedule_same_day_before_time(): void
    {
        $digest = VoicemailDigest::factory()->weekly()->create([
            'schedule_time' => '15:00',
            'schedule_day_of_week' => 1, // Monday
            'timezone' => 'America/New_York',
        ]);

        // From Monday 2026-01-26 at 10:00
        $from = Carbon::parse('2026-01-26 10:00:00', 'America/New_York');
        $nextRun = $digest->calculateNextRunAt($from);

        // Should be same Monday at 15:00
        $this->assertEquals('2026-01-26 15:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_next_run_at_for_weekly_schedule_same_day_after_time(): void
    {
        $digest = VoicemailDigest::factory()->weekly()->create([
            'schedule_time' => '08:00',
            'schedule_day_of_week' => 1, // Monday
            'timezone' => 'America/New_York',
        ]);

        // From Monday 2026-01-26 at 10:00 (after 08:00)
        $from = Carbon::parse('2026-01-26 10:00:00', 'America/New_York');
        $nextRun = $digest->calculateNextRunAt($from);

        // Should be next Monday at 08:00
        $this->assertEquals('2026-02-02 08:00:00', $nextRun->format('Y-m-d H:i:s'));
        $this->assertEquals(Carbon::MONDAY, $nextRun->dayOfWeek);
    }

    public function test_calculate_next_run_at_for_monthly_schedule_same_month(): void
    {
        $digest = VoicemailDigest::factory()->monthly()->create([
            'schedule_time' => '09:00',
            'schedule_day_of_month' => 28,
            'timezone' => 'America/New_York',
        ]);

        $from = Carbon::parse('2026-01-26 10:00:00', 'America/New_York');
        $nextRun = $digest->calculateNextRunAt($from);

        $this->assertEquals('2026-01-28 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_next_run_at_for_monthly_schedule_next_month(): void
    {
        $digest = VoicemailDigest::factory()->monthly()->create([
            'schedule_time' => '09:00',
            'schedule_day_of_month' => 15,
            'timezone' => 'America/New_York',
        ]);

        $from = Carbon::parse('2026-01-26 10:00:00', 'America/New_York');
        $nextRun = $digest->calculateNextRunAt($from);

        $this->assertEquals('2026-02-15 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_next_run_at_for_monthly_schedule_handles_short_months(): void
    {
        $digest = VoicemailDigest::factory()->monthly()->create([
            'schedule_time' => '09:00',
            'schedule_day_of_month' => 31,
            'timezone' => 'America/New_York',
        ]);

        // From February (28 days)
        $from = Carbon::parse('2026-02-15 10:00:00', 'America/New_York');
        $nextRun = $digest->calculateNextRunAt($from);

        // Should handle February not having 31 days
        $this->assertEquals(3, $nextRun->month); // March
        $this->assertEquals(31, $nextRun->day);
    }

    public function test_get_date_range_for_hourly_schedule(): void
    {
        $digest = VoicemailDigest::factory()->hourly()->create([
            'timezone' => 'America/New_York',
        ]);

        $endDate = Carbon::parse('2026-01-26 10:00:00', 'America/New_York');
        [$start, $end] = $digest->getDateRange($endDate);

        $this->assertEquals('2026-01-26 09:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-01-26 10:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_get_date_range_for_daily_schedule(): void
    {
        $digest = VoicemailDigest::factory()->daily()->create([
            'timezone' => 'America/New_York',
        ]);

        $endDate = Carbon::parse('2026-01-26 10:00:00', 'America/New_York');
        [$start, $end] = $digest->getDateRange($endDate);

        $this->assertEquals('2026-01-25 10:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-01-26 10:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_get_date_range_for_weekly_schedule(): void
    {
        $digest = VoicemailDigest::factory()->weekly()->create([
            'timezone' => 'America/New_York',
        ]);

        $endDate = Carbon::parse('2026-01-26 10:00:00', 'America/New_York');
        [$start, $end] = $digest->getDateRange($endDate);

        $this->assertEquals('2026-01-19 10:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-01-26 10:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_get_date_range_for_monthly_schedule(): void
    {
        $digest = VoicemailDigest::factory()->monthly()->create([
            'timezone' => 'America/New_York',
        ]);

        $endDate = Carbon::parse('2026-01-26 10:00:00', 'America/New_York');
        [$start, $end] = $digest->getDateRange($endDate);

        $this->assertEquals('2025-12-26 10:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-01-26 10:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_get_date_range_uses_current_time_if_no_end_date_provided(): void
    {
        $digest = VoicemailDigest::factory()->daily()->create([
            'timezone' => 'America/New_York',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-01-26 14:30:00', 'America/New_York'));

        [$start, $end] = $digest->getDateRange();

        $this->assertEquals('2026-01-25 14:30:00', $start->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-01-26 14:30:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_is_due_returns_false_when_disabled(): void
    {
        $digest = VoicemailDigest::factory()->disabled()->create([
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $this->assertFalse($digest->isDue());
    }

    public function test_is_due_returns_true_when_enabled_and_no_next_run_at(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'enabled' => true,
            'next_run_at' => null,
        ]);

        $this->assertTrue($digest->isDue());
    }

    public function test_is_due_returns_true_when_next_run_at_is_in_past(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'enabled' => true,
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $this->assertTrue($digest->isDue());
    }

    public function test_is_due_returns_true_when_next_run_at_is_now(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'enabled' => true,
            'next_run_at' => Carbon::now(),
        ]);

        $this->assertTrue($digest->isDue());
    }

    public function test_is_due_returns_false_when_next_run_at_is_in_future(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'enabled' => true,
            'next_run_at' => Carbon::now()->addHour(),
        ]);

        $this->assertFalse($digest->isDue());
    }

    public function test_soft_deletes_are_enabled(): void
    {
        $digest = VoicemailDigest::factory()->create();
        $id = $digest->id;

        $digest->delete();

        $this->assertSoftDeleted('voicemail_digests', ['id' => $id]);
        $this->assertNotNull($digest->fresh()->deleted_at);
    }

    public function test_calculate_next_run_at_respects_timezone(): void
    {
        $digest = VoicemailDigest::factory()->daily()->create([
            'schedule_time' => '08:00',
            'timezone' => 'America/Los_Angeles',
        ]);

        $from = Carbon::parse('2026-01-26 10:00:00', 'America/Los_Angeles');
        $nextRun = $digest->calculateNextRunAt($from);

        $this->assertEquals('America/Los_Angeles', $nextRun->timezone->getName());
    }
}
