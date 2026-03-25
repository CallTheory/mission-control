<?php

declare(strict_types=1);

namespace Tests\Feature\MessageExport;

use App\Models\MessageExport;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MessageExportModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-25 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_belongs_to_a_team(): void
    {
        $team = Team::factory()->create();
        $export = MessageExport::factory()->create(['team_id' => $team->id]);

        $this->assertInstanceOf(Team::class, $export->team);
        $this->assertEquals($team->id, $export->team->id);
    }

    public function test_it_encrypts_and_decrypts_selected_fields(): void
    {
        $fields = ['CallerName', 'CallerNumber', 'Message'];
        $export = MessageExport::factory()->create([
            'selected_fields' => $fields,
        ]);

        $export->refresh();
        $this->assertIsArray($export->selected_fields);
        $this->assertEquals($fields, $export->selected_fields);

        // Verify raw value is encrypted
        $raw = $export->getRawOriginal('selected_fields');
        $this->assertNotEquals(json_encode($fields), $raw);
    }

    public function test_it_encrypts_and_decrypts_filter_field(): void
    {
        $export = MessageExport::factory()->withFilter()->create();

        $export->refresh();
        $this->assertEquals('Status', $export->filter_field);

        $raw = $export->getRawOriginal('filter_field');
        $this->assertNotEquals('Status', $raw);
    }

    public function test_it_encrypts_and_decrypts_filter_value(): void
    {
        $export = MessageExport::factory()->withFilter()->create();

        $export->refresh();
        $this->assertEquals('Active', $export->filter_value);

        $raw = $export->getRawOriginal('filter_value');
        $this->assertNotEquals('Active', $raw);
    }

    public function test_null_filter_fields_stay_null(): void
    {
        $export = MessageExport::factory()->create([
            'filter_field' => null,
            'filter_value' => null,
        ]);

        $export->refresh();
        $this->assertNull($export->filter_field);
        $this->assertNull($export->filter_value);
    }

    public function test_it_casts_booleans_correctly(): void
    {
        $export = MessageExport::factory()->create([
            'include_call_info' => true,
            'enabled' => false,
        ]);

        $this->assertIsBool($export->include_call_info);
        $this->assertIsBool($export->enabled);
        $this->assertTrue($export->include_call_info);
        $this->assertFalse($export->enabled);
    }

    public function test_it_casts_dates_correctly(): void
    {
        $now = Carbon::now();
        $export = MessageExport::factory()->create([
            'last_run_at' => $now,
            'next_run_at' => $now->copy()->addDay(),
        ]);

        $this->assertInstanceOf(Carbon::class, $export->last_run_at);
        $this->assertInstanceOf(Carbon::class, $export->next_run_at);
    }

    public function test_is_manual_returns_true_for_manual_schedule(): void
    {
        $export = MessageExport::factory()->manual()->create();

        $this->assertTrue($export->isManual());
    }

    public function test_is_manual_returns_false_for_other_schedules(): void
    {
        $export = MessageExport::factory()->daily()->create();

        $this->assertFalse($export->isManual());
    }

    public function test_is_due_returns_false_for_manual_exports(): void
    {
        $export = MessageExport::factory()->manual()->create([
            'enabled' => true,
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $this->assertFalse($export->isDue());
    }

    public function test_is_due_returns_false_when_disabled(): void
    {
        $export = MessageExport::factory()->daily()->disabled()->create([
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $this->assertFalse($export->isDue());
    }

    public function test_is_due_returns_true_when_enabled_and_no_next_run_at(): void
    {
        $export = MessageExport::factory()->daily()->create([
            'enabled' => true,
            'next_run_at' => null,
        ]);

        $this->assertTrue($export->isDue());
    }

    public function test_is_due_returns_true_when_next_run_at_is_in_past(): void
    {
        $export = MessageExport::factory()->daily()->create([
            'enabled' => true,
            'next_run_at' => Carbon::now()->subHour(),
        ]);

        $this->assertTrue($export->isDue());
    }

    public function test_is_due_returns_false_when_next_run_at_is_in_future(): void
    {
        $export = MessageExport::factory()->daily()->create([
            'enabled' => true,
            'next_run_at' => Carbon::now()->addHour(),
        ]);

        $this->assertFalse($export->isDue());
    }

    public function test_calculate_next_run_at_for_hourly_schedule(): void
    {
        $export = MessageExport::factory()->hourly()->create([
            'timezone' => 'America/New_York',
        ]);

        $from = Carbon::parse('2026-03-25 10:30:00', 'America/New_York');
        $nextRun = $export->calculateNextRunAt($from);

        $this->assertEquals('2026-03-25 11:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_next_run_at_for_daily_schedule_same_day(): void
    {
        $export = MessageExport::factory()->daily()->create([
            'schedule_time' => '15:00',
            'timezone' => 'America/New_York',
        ]);

        $from = Carbon::parse('2026-03-25 10:00:00', 'America/New_York');
        $nextRun = $export->calculateNextRunAt($from);

        $this->assertEquals('2026-03-25 15:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_next_run_at_for_daily_schedule_next_day(): void
    {
        $export = MessageExport::factory()->daily()->create([
            'schedule_time' => '08:00',
            'timezone' => 'America/New_York',
        ]);

        $from = Carbon::parse('2026-03-25 10:00:00', 'America/New_York');
        $nextRun = $export->calculateNextRunAt($from);

        $this->assertEquals('2026-03-26 08:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_calculate_next_run_at_for_weekly_schedule(): void
    {
        $export = MessageExport::factory()->weekly()->create([
            'schedule_time' => '09:00',
            'schedule_day_of_week' => 1, // Monday
            'timezone' => 'America/New_York',
        ]);

        // Wednesday 2026-03-25
        $from = Carbon::parse('2026-03-25 10:00:00', 'America/New_York');
        $nextRun = $export->calculateNextRunAt($from);

        $this->assertEquals(Carbon::MONDAY, $nextRun->dayOfWeek);
        $this->assertEquals('09:00:00', $nextRun->format('H:i:s'));
    }

    public function test_calculate_next_run_at_for_monthly_schedule(): void
    {
        $export = MessageExport::factory()->monthly()->create([
            'schedule_time' => '09:00',
            'schedule_day_of_month' => 28,
            'timezone' => 'America/New_York',
        ]);

        $from = Carbon::parse('2026-03-25 10:00:00', 'America/New_York');
        $nextRun = $export->calculateNextRunAt($from);

        $this->assertEquals('2026-03-28 09:00:00', $nextRun->format('Y-m-d H:i:s'));
    }

    public function test_get_date_range_for_daily_schedule(): void
    {
        $export = MessageExport::factory()->daily()->create([
            'timezone' => 'America/New_York',
        ]);

        $endDate = Carbon::parse('2026-03-25 10:00:00', 'America/New_York');
        [$start, $end] = $export->getDateRange($endDate);

        $this->assertEquals('2026-03-24 10:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-03-25 10:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_get_date_range_for_weekly_schedule(): void
    {
        $export = MessageExport::factory()->weekly()->create([
            'timezone' => 'America/New_York',
        ]);

        $endDate = Carbon::parse('2026-03-25 10:00:00', 'America/New_York');
        [$start, $end] = $export->getDateRange($endDate);

        $this->assertEquals('2026-03-18 10:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-03-25 10:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_soft_deletes_are_enabled(): void
    {
        $export = MessageExport::factory()->create();
        $id = $export->id;

        $export->delete();

        $this->assertSoftDeleted('message_exports', ['id' => $id]);
    }

    public function test_recipients_cast_to_array(): void
    {
        $export = MessageExport::factory()->daily()->create([
            'recipients' => ['test@example.com', 'test2@example.com'],
        ]);

        $export->refresh();
        $this->assertIsArray($export->recipients);
        $this->assertCount(2, $export->recipients);
    }
}
