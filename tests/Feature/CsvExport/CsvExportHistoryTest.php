<?php

declare(strict_types=1);

namespace Tests\Feature\CsvExport;

use App\Livewire\Utilities\CsvExportHistory;
use App\Models\CsvExportLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class CsvExportHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create([
            'personal_team' => false,
            'utility_csv_export' => true,
        ]);

        $this->user->teams()->attach($this->team, ['role' => 'admin']);
        $this->user->switchTeam($this->team);
        $this->user = $this->user->fresh();

        $this->enableSystemFeatureFlag();
    }

    protected function tearDown(): void
    {
        Storage::deleteDirectory('feature-flags');
        parent::tearDown();
    }

    public function test_history_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('utilities.csv-export.history'));

        $response->assertStatus(200)
            ->assertViewIs('utilities.csv-export-history');
    }

    public function test_history_page_returns_404_when_feature_disabled(): void
    {
        Storage::deleteDirectory('feature-flags');

        $response = $this->actingAs($this->user)
            ->get(route('utilities.csv-export.history'));

        $response->assertStatus(404);
    }

    public function test_history_page_returns_403_for_personal_team(): void
    {
        $personalTeam = Team::factory()->create([
            'personal_team' => true,
            'utility_csv_export' => true,
        ]);

        $this->user->teams()->attach($personalTeam, ['role' => 'admin']);
        $this->user->switchTeam($personalTeam);
        $this->user = $this->user->fresh();

        $response = $this->actingAs($this->user)
            ->get(route('utilities.csv-export.history'));

        $response->assertStatus(403);
    }

    public function test_history_component_displays_logs(): void
    {
        $log = CsvExportLog::factory()->completed()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'result_count' => 42,
        ]);

        Livewire::actingAs($this->user)
            ->test(CsvExportHistory::class)
            ->assertSee($this->user->name)
            ->assertSee('42')
            ->assertSee('Completed');
    }

    public function test_history_component_filters_by_status(): void
    {
        CsvExportLog::factory()->completed()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
        ]);

        CsvExportLog::factory()->failed()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(CsvExportHistory::class);

        $html = $component->html();
        $this->assertEquals(1, substr_count($html, 'bg-green-100'));
        $this->assertEquals(1, substr_count($html, 'bg-red-100'));

        $component->set('filterStatus', 'completed');
        $html = $component->html();
        $this->assertEquals(1, substr_count($html, 'bg-green-100'));
        $this->assertEquals(0, substr_count($html, 'bg-red-100'));
    }

    public function test_history_component_filters_by_user(): void
    {
        $otherUser = User::factory()->create(['name' => 'Other Export User']);
        $otherUser->teams()->attach($this->team, ['role' => 'editor']);

        CsvExportLog::factory()->completed()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
        ]);

        CsvExportLog::factory()->completed()->create([
            'user_id' => $otherUser->id,
            'team_id' => $this->team->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(CsvExportHistory::class);

        $html = $component->html();
        $this->assertEquals(2, substr_count($html, 'bg-green-100'));

        $component->set('filterUser', $this->user->id);
        $html = $component->html();
        $this->assertEquals(1, substr_count($html, 'bg-green-100'));
    }

    public function test_history_component_only_shows_team_logs(): void
    {
        $otherTeam = Team::factory()->create();
        $otherUser = User::factory()->create(['name' => 'Other Team User']);

        CsvExportLog::factory()->completed()->create([
            'user_id' => $otherUser->id,
            'team_id' => $otherTeam->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(CsvExportHistory::class)
            ->assertDontSee('Other Team User')
            ->assertSee('No export history found.');
    }

    public function test_reexport_redirects_to_export_page(): void
    {
        $log = CsvExportLog::factory()->completed()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(CsvExportHistory::class)
            ->call('reexport', $log->id)
            ->assertRedirect(route('utilities.csv-export', ['reexport_log_id' => $log->id]));
    }

    public function test_reexport_rejects_other_team_log(): void
    {
        $otherTeam = Team::factory()->create();
        $log = CsvExportLog::factory()->completed()->create([
            'user_id' => $this->user->id,
            'team_id' => $otherTeam->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(CsvExportHistory::class)
            ->call('reexport', $log->id)
            ->assertSee('Export log not found.');
    }

    public function test_model_mark_as_completed(): void
    {
        $log = CsvExportLog::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'result_count' => 0,
            'filename' => null,
        ]);

        $log->markAsCompleted(150, 'call-log-export-2026-03-04.csv');
        $log->refresh();

        $this->assertEquals('completed', $log->status);
        $this->assertEquals(150, $log->result_count);
        $this->assertEquals('call-log-export-2026-03-04.csv', $log->filename);
    }

    public function test_model_mark_as_failed(): void
    {
        $log = CsvExportLog::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
        ]);

        $log->markAsFailed('Connection refused');
        $log->refresh();

        $this->assertEquals('failed', $log->status);
        $this->assertEquals('Connection refused', $log->error_message);
    }

    public function test_model_for_team_scope(): void
    {
        $otherTeam = Team::factory()->create();

        CsvExportLog::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
        ]);

        CsvExportLog::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $otherTeam->id,
        ]);

        $logs = CsvExportLog::forTeam($this->team->id)->get();

        $this->assertCount(1, $logs);
        $this->assertEquals($this->team->id, $logs->first()->team_id);
    }

    public function test_purge_command_deletes_old_logs(): void
    {
        CsvExportLog::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(91),
        ]);

        CsvExportLog::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('csv-export:purge-logs')
            ->assertExitCode(0);

        $this->assertCount(1, CsvExportLog::all());
    }

    public function test_purge_command_respects_config(): void
    {
        config(['utilities.csv-export.days_to_keep' => 10]);

        CsvExportLog::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(15),
        ]);

        CsvExportLog::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('csv-export:purge-logs')
            ->assertExitCode(0);

        $this->assertCount(1, CsvExportLog::all());
    }

    public function test_log_belongs_to_user(): void
    {
        $log = CsvExportLog::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
        ]);

        $this->assertEquals($this->user->id, $log->user->id);
    }

    public function test_log_belongs_to_team(): void
    {
        $log = CsvExportLog::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
        ]);

        $this->assertEquals($this->team->id, $log->team->id);
    }

    public function test_log_casts_filters_to_array(): void
    {
        $log = CsvExportLog::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'filters' => ['start_date' => '2026-03-04T10:00', 'end_date' => '2026-03-04T11:00'],
        ]);

        $log->refresh();
        $this->assertIsArray($log->filters);
        $this->assertEquals('2026-03-04T10:00', $log->filters['start_date']);
    }

    public function test_log_cascades_on_user_delete(): void
    {
        $tempUser = User::factory()->create();
        CsvExportLog::factory()->create([
            'user_id' => $tempUser->id,
            'team_id' => $this->team->id,
        ]);

        $this->assertCount(1, CsvExportLog::where('user_id', $tempUser->id)->get());

        $tempUser->forceDelete();

        $this->assertCount(0, CsvExportLog::where('user_id', $tempUser->id)->get());
    }

    private function enableSystemFeatureFlag(): void
    {
        Storage::makeDirectory('feature-flags');
        $encrypted = encrypt('csv-export');
        Storage::put('feature-flags/csv-export.flag', $encrypted);
    }
}
