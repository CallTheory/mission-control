<?php

namespace Tests\Feature\Permissions;

use App\Actions\Roles\SeedDefaultRolesForTeam;
use App\Enums\Capability;
use App\Livewire\System\RoleManager;
use App\Models\Role;
use App\Models\SuffixRule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleManagerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        (new SeedDefaultRolesForTeam)($this->team);

        $this->admin = User::factory()->create();
        $this->team->users()->attach($this->admin, ['role' => 'admin']);
        $this->admin->switchTeam($this->team);
        $this->admin->assignRole($this->team->roles()->where('key', 'admin')->firstOrFail());
        $this->admin = $this->admin->fresh();
    }

    public function test_admin_can_create_a_role(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleManager::class)
            ->set('newLabel', 'Night Shift')
            ->call('createRole')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('roles', [
            'team_id' => $this->team->id,
            'key' => 'night_shift',
            'label' => 'Night Shift',
            'is_system' => false,
        ]);
    }

    public function test_admin_can_edit_role_capabilities(): void
    {
        $agent = $this->team->roles()->where('key', 'agent')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(RoleManager::class)
            ->call('editRole', $agent->id)
            ->set('selectedCapabilities', [Capability::UtilityCardProcessing->value])
            ->call('saveCapabilities')
            ->assertHasNoErrors();

        $agent->refresh();
        $this->assertTrue($agent->grants(Capability::UtilityCardProcessing));
        $this->assertFalse($agent->grants(Capability::UtilityDatabaseHealth->value));
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $agent = $this->team->roles()->where('key', 'agent')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(RoleManager::class)
            ->call('deleteRole', $agent->id)
            ->assertHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $agent->id]);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $custom = Role::create([
            'team_id' => $this->team->id,
            'key' => 'temp',
            'label' => 'Temp',
            'is_system' => false,
        ]);
        $this->admin->assignRole($custom);

        Livewire::actingAs($this->admin)
            ->test(RoleManager::class)
            ->call('deleteRole', $custom->id)
            ->assertHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $custom->id]);
    }

    public function test_unused_custom_role_can_be_deleted(): void
    {
        $custom = Role::create([
            'team_id' => $this->team->id,
            'key' => 'temp',
            'label' => 'Temp',
            'is_system' => false,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RoleManager::class)
            ->call('deleteRole', $custom->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('roles', ['id' => $custom->id]);
    }

    public function test_cannot_remove_last_manage_roles_capability(): void
    {
        $admin = $this->team->roles()->where('key', 'admin')->firstOrFail();

        // Admin is the only role granting manage_roles; stripping it must fail.
        Livewire::actingAs($this->admin)
            ->test(RoleManager::class)
            ->call('editRole', $admin->id)
            ->set('selectedCapabilities', [Capability::UtilityBoardCheck->value])
            ->call('saveCapabilities')
            ->assertHasErrors('capabilities');

        $admin->refresh();
        $this->assertTrue($admin->grants(Capability::AdminManageRoles));
    }

    public function test_admin_can_add_and_remove_suffix_rule(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleManager::class)
            ->set('suffixPattern', '-NIGHT')
            ->set('suffixMatchType', 'contains')
            ->set('suffixCapabilities', [Capability::UtilityDatabaseHealth->value])
            ->call('addSuffixRule')
            ->assertHasNoErrors();

        $rule = SuffixRule::where('team_id', $this->team->id)->where('pattern', '-NIGHT')->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(RoleManager::class)
            ->call('deleteSuffixRule', $rule->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('suffix_rules', ['id' => $rule->id]);
    }

    public function test_permissions_page_forbidden_without_manage_roles(): void
    {
        // The real guard is the controller (Livewire swallows mount-time aborts).
        $agentUser = User::factory()->create();
        $this->team->users()->attach($agentUser, ['role' => 'agent']);
        $agentUser->switchTeam($this->team);
        $agentUser->assignRole($this->team->roles()->where('key', 'agent')->firstOrFail());

        $this->actingAs($agentUser->fresh())->get('/system/permissions')->assertForbidden();
    }

    public function test_permissions_page_allowed_for_admin(): void
    {
        $this->actingAs($this->admin)->get('/system/permissions')->assertOk();
    }
}
