<?php

declare(strict_types=1);

namespace Tests\Feature\CsvExport;

use App\Livewire\Utilities\CallLog;
use App\Livewire\Utilities\CsvExport;
use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * The shared call-log date filters default to the switch timezone.
 *
 * Every other test in the suite runs with switch_data_timezone = 'UTC', which is
 * exactly the value that hides this class of bug: the defaults were being computed
 * from the property's declared fallback of 'UTC' because Filament builds the table
 * schema before the trait's mount hook runs, and on a UTC switch that is
 * indistinguishable from correct. On a Central switch it was five hours of skew,
 * pre-filling a window that had not happened yet, so the screen opened empty.
 *
 * These therefore pin a *non-UTC* switch timezone on purpose.
 */
class CallLogFilterTimezoneTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private const SWITCH_TIMEZONE = 'America/Chicago';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = Settings::firstOrCreate([], ['switch_data_timezone' => self::SWITCH_TIMEZONE]);
        $settings->switch_data_timezone = self::SWITCH_TIMEZONE;
        $settings->save();

        $this->user = User::factory()->create(['timezone' => 'America/New_York']);
        $team = Team::factory()->create([
            'personal_team' => false,
            'utility_csv_export' => true,
            'utility_call_lookup' => true,
        ]);
        $this->user->teams()->attach($team, ['role' => 'admin']);
        $this->user->switchTeam($team);
        $this->user = $this->user->fresh();

        $this->enableSystemFeature('csv-export');
    }

    public function test_csv_export_dates_default_to_the_switch_timezone(): void
    {
        $data = Livewire::actingAs($this->user)->test(CsvExport::class)->get('data');

        $this->assertStringStartsWith(
            Carbon::now(self::SWITCH_TIMEZONE)->format('Y-m-d H:i'),
            (string) $data['end_date'],
            'end_date defaulted to something other than "now" in the switch timezone.'
        );

        $this->assertStringStartsWith(
            Carbon::now(self::SWITCH_TIMEZONE)->subHour()->format('Y-m-d H:i'),
            (string) $data['start_date'],
            'start_date defaulted to something other than an hour ago in the switch timezone.'
        );
    }

    public function test_the_defaults_are_not_the_utc_clock(): void
    {
        // The specific regression: a Central switch pre-filled with UTC times.
        $data = Livewire::actingAs($this->user)->test(CsvExport::class)->get('data');

        $this->assertStringStartsNotWith(
            Carbon::now('UTC')->format('Y-m-d H:i'),
            (string) $data['end_date'],
            'end_date is the UTC clock; the switch timezone was not applied.'
        );
    }

    public function test_the_timezone_resolves_before_the_schema_is_built(): void
    {
        // Guards the ordering directly: whatever the label promises has to be the
        // zone the defaults were computed in, on both screens that share the trait.
        foreach ([CsvExport::class, CallLog::class] as $screen) {
            $component = Livewire::actingAs($this->user)->test($screen);

            $this->assertSame(
                self::SWITCH_TIMEZONE,
                $component->instance()->timezone,
                $screen.' did not resolve the switch timezone.'
            );
        }
    }
}
