<?php

declare(strict_types=1);

namespace Tests\Feature\MessageExport;

use App\Livewire\Utilities\MessageExportHistory;
use App\Models\MessageExport;
use App\Models\MessageExportLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

final class MessageExportHistoryTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create([
            'personal_team' => false,
            'utility_message_export' => true,
        ]);

        $this->user->teams()->attach($this->team, ['role' => 'admin']);
        $this->user->switchTeam($this->team);
        $this->user = $this->user->fresh();

        $this->enableSystemFeatureFlag();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_history_component_displays_logs(): void
    {
        $export = MessageExport::factory()->create([
            'team_id' => $this->team->id,
        ]);

        $log = MessageExportLog::factory()->completed()->create([
            'message_export_id' => $export->id,
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'export_name' => 'Test Export',
            'message_count' => 42,
        ]);

        Livewire::actingAs($this->user)
            ->test(MessageExportHistory::class)
            ->assertSee('Test Export')
            ->assertSee('42')
            ->assertSee('Completed');
    }

    public function test_history_component_filters_by_status(): void
    {
        $export = MessageExport::factory()->create([
            'team_id' => $this->team->id,
        ]);

        $completed = MessageExportLog::factory()->completed()->create([
            'message_export_id' => $export->id,
            'team_id' => $this->team->id,
        ]);

        $failed = MessageExportLog::factory()->failed()->create([
            'message_export_id' => $export->id,
            'team_id' => $this->team->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(MessageExportHistory::class)
            ->assertCanSeeTableRecords([$completed, $failed])
            ->filterTable('status', 'completed')
            ->assertCanSeeTableRecords([$completed])
            ->assertCanNotSeeTableRecords([$failed]);
    }

    public function test_history_component_filters_by_export(): void
    {
        $export1 = MessageExport::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Export One',
        ]);

        $export2 = MessageExport::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Export Two',
        ]);

        $logOne = MessageExportLog::factory()->completed()->create([
            'message_export_id' => $export1->id,
            'team_id' => $this->team->id,
            'export_name' => 'Export One',
        ]);

        $logTwo = MessageExportLog::factory()->completed()->create([
            'message_export_id' => $export2->id,
            'team_id' => $this->team->id,
            'export_name' => 'Export Two',
        ]);

        // Asserting on records rather than rendered markup: "Export Two" also appears
        // in the filter dropdown, so a string search cannot tell rows from options.
        Livewire::actingAs($this->user)
            ->test(MessageExportHistory::class)
            ->assertCanSeeTableRecords([$logOne, $logTwo])
            ->filterTable('message_export_id', $export1->id)
            ->assertCanSeeTableRecords([$logOne])
            ->assertCanNotSeeTableRecords([$logTwo]);
    }

    public function test_history_component_only_shows_team_logs(): void
    {
        $otherTeam = Team::factory()->create();

        MessageExportLog::factory()->completed()->create([
            'team_id' => $otherTeam->id,
            'export_name' => 'Other Team Export',
        ]);

        Livewire::actingAs($this->user)
            ->test(MessageExportHistory::class)
            ->assertDontSee('Other Team Export')
            ->assertSee('No export history found.');
    }

    private function enableSystemFeatureFlag(): void
    {
        $this->enableSystemFeature('message-export');
    }
}
