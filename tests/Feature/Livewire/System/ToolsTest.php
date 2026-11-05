<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Enums\Capability;
use App\Jobs\SyncMergeCommWebHooks;
use App\Livewire\System\Tools;
use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * System -> Tools, and specifically the manual MergeComm trigger sync.
 *
 * The scheduled sync is daily, which is right for a catalogue most inbound email rules never
 * read. This button exists for the case the daily cadence does not serve: somebody has just
 * added a web trigger in Intelligent Series and wants to route email to it now.
 */
class ToolsTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private function admin()
    {
        return $this->createUserWithRole($this->createSeededTeam(), 'admin');
    }

    public function test_it_queues_the_trigger_sync(): void
    {
        Queue::fake();

        DataSource::create(['is_db_host' => 'sql.example.test']);

        Livewire::actingAs($this->admin())
            ->test(Tools::class)
            ->assertSee('Sync MergeComm Triggers')
            ->call('syncMergeCommTriggers')
            ->assertSet('triggerSyncQueued', true)
            ->assertSee('Trigger Sync Queued');

        Queue::assertPushed(SyncMergeCommWebHooks::class);
    }

    /**
     * Nothing to sync from, so the tile says what to do instead of queueing a job whose
     * first statement would throw.
     */
    public function test_it_offers_nothing_without_a_data_source(): void
    {
        Queue::fake();

        Livewire::actingAs($this->admin())
            ->test(Tools::class)
            ->assertSee('Connect an Intelligent Series data source first')
            ->call('syncMergeCommTriggers')
            ->assertSet('triggerSyncQueued', false);

        Queue::assertNotPushed(SyncMergeCommWebHooks::class);
    }

    /**
     * Livewire does not re-run the page controller on POST /livewire/update, so the action
     * authorizes for itself -- the same reason every other System component does.
     */
    public function test_a_user_without_system_access_cannot_queue_it(): void
    {
        Queue::fake();

        DataSource::create(['is_db_host' => 'sql.example.test']);

        $team = $this->createSeededTeam();
        $user = $this->createUserWithout($team, 'admin', Capability::SystemAccess);

        Livewire::actingAs($user)->test(Tools::class)->assertForbidden();

        Queue::assertNotPushed(SyncMergeCommWebHooks::class);
    }
}
