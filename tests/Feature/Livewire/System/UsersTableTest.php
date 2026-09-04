<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Enums\Capability;
use App\Livewire\System\Users;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * Covers the System users list after its move from hand-rolled markup to a
 * Filament table. The capability guard and the record link are the parts a
 * table rewrite can silently drop, so both are asserted explicitly.
 */
class UsersTableTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->createSeededTeam();
    }

    public function test_it_lists_users(): void
    {
        $admin = $this->createUserWithRole($this->team, 'admin');
        $other = User::factory()->create(['name' => 'Rowan Adeyemi']);

        Livewire::actingAs($admin)
            ->test(Users::class)
            ->assertCanSeeTableRecords([$admin, $other])
            ->assertCanRenderTableColumn('name')
            ->assertCanRenderTableColumn('email')
            ->assertCanRenderTableColumn('teams');
    }

    public function test_it_searches_by_name_and_email(): void
    {
        $admin = $this->createUserWithRole($this->team, 'admin');
        $match = User::factory()->create(['name' => 'Dana Whitfield', 'email' => 'dana@example.test']);
        $miss = User::factory()->create(['name' => 'Rowan Adeyemi', 'email' => 'rowan@example.test']);

        Livewire::actingAs($admin)
            ->test(Users::class)
            ->searchTable('Whitfield')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$miss])
            ->searchTable('rowan@example.test')
            ->assertCanSeeTableRecords([$miss])
            ->assertCanNotSeeTableRecords([$match]);
    }

    public function test_it_sorts_by_name(): void
    {
        $admin = $this->createUserWithRole($this->team, 'admin');
        $first = User::factory()->create(['name' => 'Aaron Beck']);
        $last = User::factory()->create(['name' => 'Zoe Yang']);

        Livewire::actingAs($admin)
            ->test(Users::class)
            ->sortTable('name')
            ->assertCanSeeTableRecords([$first, $last], inOrder: true)
            ->sortTable('name', 'desc')
            ->assertCanSeeTableRecords([$last, $first], inOrder: true);
    }

    public function test_each_row_links_to_the_user_detail_page(): void
    {
        $admin = $this->createUserWithRole($this->team, 'admin');

        Livewire::actingAs($admin)
            ->test(Users::class)
            ->assertSee('/system/users/'.$admin->id, escape: false);
    }

    public function test_a_user_without_the_capability_is_denied(): void
    {
        $denied = $this->createUserWithout($this->team, 'admin', Capability::AdminManageUsers);

        Livewire::actingAs($denied)
            ->test(Users::class)
            ->assertForbidden();
    }
}
