<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Livewire\System\WctpGateway;
use App\Models\EnterpriseHost;
use App\Models\Team;
use App\Models\WctpMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * The System WCTP gateway's host listing.
 *
 * Two screens manage enterprise_hosts and the difference is the point: the Utilities
 * screen is strictly team-scoped, while this one also surfaces global hosts -- rows
 * with a null team_id, visible to every team. That rule had no coverage.
 */
class WctpGatewayHostsTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->createSeededTeam();
    }

    public function test_it_shows_the_teams_hosts_and_global_ones_but_not_another_teams(): void
    {
        $mine = EnterpriseHost::factory()->create(['team_id' => $this->team->id, 'name' => 'Mine']);
        $global = EnterpriseHost::factory()->create(['team_id' => null, 'name' => 'Global']);
        $theirs = EnterpriseHost::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'name' => 'Theirs',
        ]);

        Livewire::actingAs($this->createUserWithRole($this->team, 'admin'))
            ->test(WctpGateway::class)
            // The listing lives on the Enterprise Hosts tab.
            ->set('activeTab', 'hosts')
            ->assertCanSeeTableRecords([$mine, $global])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_a_global_host_is_labelled_as_such(): void
    {
        EnterpriseHost::factory()->create(['team_id' => null, 'name' => 'Shared Host']);

        Livewire::actingAs($this->createUserWithRole($this->team, 'admin'))
            ->test(WctpGateway::class)
            // The listing lives on the Enterprise Hosts tab.
            ->set('activeTab', 'hosts')
            // A null team_id renders the Global placeholder in the Team column.
            ->assertSee('Global');
    }

    public function test_a_host_with_messages_cannot_be_deleted(): void
    {
        $host = EnterpriseHost::factory()->create(['team_id' => $this->team->id]);
        WctpMessage::factory()->create(['enterprise_host_id' => $host->id]);

        Livewire::actingAs($this->createUserWithRole($this->team, 'admin'))
            ->test(WctpGateway::class)
            // The listing lives on the Enterprise Hosts tab.
            ->set('activeTab', 'hosts')
            ->assertTableActionHidden('delete', $host);

        $this->assertDatabaseHas('enterprise_hosts', ['id' => $host->id]);
    }

    public function test_editing_without_a_security_code_keeps_the_stored_one(): void
    {
        $host = EnterpriseHost::factory()->create([
            'team_id' => $this->team->id,
            'securityCode' => 'original_secret',
        ]);

        Livewire::actingAs($this->createUserWithRole($this->team, 'admin'))
            ->test(WctpGateway::class)
            // The listing lives on the Enterprise Hosts tab.
            ->set('activeTab', 'hosts')
            ->callTableAction('edit', $host, [
                'name' => 'Renamed',
                'senderID' => $host->senderID,
                'securityCode' => '',
            ])
            ->assertHasNoErrors();

        $host->refresh();

        $this->assertSame('Renamed', $host->name);
        $this->assertSame('original_secret', $host->securityCode);
    }
}
