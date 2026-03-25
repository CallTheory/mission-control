<?php

declare(strict_types=1);

namespace Tests\Feature\MessageExport;

use App\Livewire\Utilities\MessageExportHistory;
use App\Models\MessageExport;
use App\Models\MessageExportLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class MessageExportHistoryTest extends TestCase
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
            'utility_message_export' => true,
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

        MessageExportLog::factory()->completed()->create([
            'message_export_id' => $export->id,
            'team_id' => $this->team->id,
        ]);

        MessageExportLog::factory()->failed()->create([
            'message_export_id' => $export->id,
            'team_id' => $this->team->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(MessageExportHistory::class);

        $html = $component->html();
        $this->assertEquals(1, substr_count($html, 'bg-green-100'));
        $this->assertEquals(1, substr_count($html, 'bg-red-100'));

        $component->set('filterStatus', 'completed');
        $html = $component->html();
        $this->assertEquals(1, substr_count($html, 'bg-green-100'));
        $this->assertEquals(0, substr_count($html, 'bg-red-100'));
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

        MessageExportLog::factory()->completed()->create([
            'message_export_id' => $export1->id,
            'team_id' => $this->team->id,
            'export_name' => 'Export One',
        ]);

        MessageExportLog::factory()->completed()->create([
            'message_export_id' => $export2->id,
            'team_id' => $this->team->id,
            'export_name' => 'Export Two',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(MessageExportHistory::class);

        $html = $component->html();
        $this->assertStringContainsString('Export One', $html);
        $this->assertStringContainsString('Export Two', $html);

        $component->set('filterExport', $export1->id);
        $html = $component->html();
        // "Export Two" still appears in the filter dropdown, but should not appear in table rows
        // Count the number of completed badges — should only be 1 when filtered
        $this->assertEquals(1, substr_count($html, 'bg-green-100'));
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
        Storage::makeDirectory('feature-flags');
        $encrypted = encrypt('message-export');
        Storage::put('feature-flags/message-export.flag', $encrypted);
    }
}
