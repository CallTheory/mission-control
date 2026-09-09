<?php

declare(strict_types=1);

namespace Tests\Feature\MessageExport;

use App\Jobs\ProcessMessageExport;
use App\Livewire\Utilities\MessageExport;
use App\Models\MessageExport as MessageExportModel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * The message export screen after its listing and three dialogs became a Filament
 * table with actions. The component itself had no tests before this: the suite covered
 * the model, the job and the history screen, but nothing ever instantiated it.
 */
class MessageExportScreenTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['personal_team' => false, 'utility_message_export' => true]);
        $this->user->teams()->attach($this->team, ['role' => 'admin']);
        $this->user->switchTeam($this->team);
        $this->user = $this->user->fresh();

        $this->enableSystemFeature('message-export');
    }

    private function export(array $attributes = []): MessageExportModel
    {
        return MessageExportModel::factory()->create(array_merge([
            'team_id' => $this->team->id,
            'schedule_type' => 'manual',
            'timezone' => 'UTC',
        ], $attributes));
    }

    public function test_it_lists_only_the_teams_exports(): void
    {
        $mine = $this->export(['name' => 'Mine']);
        $theirs = MessageExportModel::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'name' => 'Theirs',
        ]);

        Livewire::actingAs($this->user)
            ->test(MessageExport::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_running_an_export_now_queues_the_job_for_the_given_range(): void
    {
        Queue::fake();

        $export = $this->export(['name' => 'Nightly']);

        Livewire::actingAs($this->user)
            ->test(MessageExport::class)
            ->callTableAction('runNow', $export, [
                'start_date' => '2026-01-01 00:00:00',
                'end_date' => '2026-01-02 00:00:00',
            ])
            ->assertHasNoErrors()
            ->assertNotified();

        Queue::assertPushed(ProcessMessageExport::class);
    }

    public function test_running_an_export_rejects_an_end_before_the_start(): void
    {
        Queue::fake();

        $export = $this->export();

        Livewire::actingAs($this->user)
            ->test(MessageExport::class)
            ->callTableAction('runNow', $export, [
                'start_date' => '2026-01-02 00:00:00',
                'end_date' => '2026-01-01 00:00:00',
            ])
            ->assertHasFormErrors(['end_date']);

        Queue::assertNotPushed(ProcessMessageExport::class);
    }

    public function test_toggling_enabled_flips_it(): void
    {
        $export = $this->export(['enabled' => true]);

        Livewire::actingAs($this->user)
            ->test(MessageExport::class)
            ->callTableAction('toggleEnabled', $export);

        $this->assertFalse((bool) $export->refresh()->enabled);
    }

    public function test_deleting_removes_the_export(): void
    {
        $export = $this->export();

        Livewire::actingAs($this->user)
            ->test(MessageExport::class)
            ->callTableAction('delete', $export);

        $this->assertSoftDeleted('message_exports', ['id' => $export->id]);
    }

    public function test_the_run_now_dialog_is_prefilled_from_the_schedule(): void
    {
        $export = $this->export(['schedule_type' => 'daily', 'timezone' => 'UTC']);

        [$start, $end] = $export->getDateRange();

        Livewire::actingAs($this->user)
            ->test(MessageExport::class)
            ->mountTableAction('runNow', $export)
            // The picker normalises to its display format, so compare on that.
            ->assertActionDataSet([
                'start_date' => $start->format('Y-m-d H:i'),
                'end_date' => $end->format('Y-m-d H:i'),
            ]);
    }
}
