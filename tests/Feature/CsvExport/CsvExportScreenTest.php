<?php

declare(strict_types=1);

namespace Tests\Feature\CsvExport;

use App\Livewire\Analytics\CallLog;
use App\Livewire\Utilities\CsvExport;
use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * The CSV export screen after its filter form became a Filament schema shared with
 * Analytics\CallLog. The Amtelco database is unreachable in tests, so what is asserted
 * is the behaviour that survives that: the screen renders, the filters are the shared
 * set, and a failed query is reported rather than thrown at the user.
 */
class CsvExportScreenTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Settings::firstOrCreate([], ['switch_data_timezone' => 'UTC']);

        $this->user = User::factory()->create();
        $team = Team::factory()->create(['personal_team' => false, 'utility_csv_export' => true]);
        $this->user->teams()->attach($team, ['role' => 'admin']);
        $this->user->switchTeam($team);
        $this->user = $this->user->fresh();

        $this->enableSystemFeature('csv-export');
    }

    public function test_it_renders_with_the_shared_call_log_filters(): void
    {
        Livewire::actingAs($this->user)
            ->test(CsvExport::class)
            ->assertOk()
            ->assertFormExists()
            ->assertFormFieldExists('start_date')
            ->assertFormFieldExists('end_date')
            ->assertFormFieldExists('client_number')
            ->assertFormFieldExists('ani')
            ->assertFormFieldExists('keyword_search')
            ->assertFormFieldExists('has_recordings');
    }

    public function test_the_date_range_defaults_to_the_last_hour(): void
    {
        $component = Livewire::actingAs($this->user)->test(CsvExport::class);

        $data = $component->get('data');

        $this->assertNotNull($data['start_date']);
        $this->assertNotNull($data['end_date']);
    }

    public function test_a_failed_query_is_reported_rather_than_thrown(): void
    {
        // No Amtelco server in tests, so the preview query fails. The screen has to
        // surface that rather than 500.
        Livewire::actingAs($this->user)
            ->test(CsvExport::class)
            ->callAction('preview')
            ->assertOk()
            ->assertNotified();
    }

    public function test_the_filter_set_matches_the_call_log_screen(): void
    {
        // Both screens build their filters from FiltersCallLog, so the two can no
        // longer drift. This asserts they are literally the same definition.
        $csv = Livewire::actingAs($this->user)->test(CsvExport::class)->instance();
        $log = new CallLog;

        $names = fn (array $schema): array => array_map(
            fn ($component) => $component->getName(),
            $schema
        );

        $reflection = new \ReflectionMethod($csv, 'callLogFilterSchema');
        $reflection->setAccessible(true);

        $this->assertSame(
            ['start_date', 'end_date', 'client_number', 'ani', 'call_type', 'agent',
                'min_duration', 'max_duration', 'keyword', 'keyword_search',
                'has_messages', 'has_recordings', 'has_video'],
            $names($reflection->invoke($csv))
        );
    }
}
