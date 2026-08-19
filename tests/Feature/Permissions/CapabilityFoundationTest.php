<?php

namespace Tests\Feature\Permissions;

use App\Actions\Roles\SeedDefaultRolesForTeam;
use App\Enums\Capability;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function teamWithRoles(): Team
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
        ]);

        (new SeedDefaultRolesForTeam)($team);

        return $team;
    }

    private function member(Team $team, string $roleKey): User
    {
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => $roleKey]);
        $user->switchTeam($team);

        $role = $team->roles()->where('key', $roleKey)->firstOrFail();
        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_each_team_is_seeded_with_the_six_system_roles(): void
    {
        $team = $this->teamWithRoles();

        $this->assertEqualsCanonicalizing(
            ['admin', 'manager', 'supervisor', 'technical', 'dispatcher', 'agent'],
            $team->roles()->pluck('key')->all()
        );

        $this->assertTrue(
            Role::where('team_id', $team->id)->where('key', 'admin')->first()->is_system
        );
    }

    public function test_admin_has_full_capabilities(): void
    {
        $team = $this->teamWithRoles();
        $admin = $this->member($team, 'admin');

        $this->assertTrue($admin->hasCapability(Capability::SystemAccess, $team));
        $this->assertTrue($admin->hasCapability(Capability::AdminManageRoles, $team));
        $this->assertTrue($admin->hasCapability(Capability::UtilityConfigEditor, $team));
        $this->assertTrue($admin->hasCapability(Capability::UtilityCardProcessing, $team));
    }

    public function test_agent_only_has_open_utilities(): void
    {
        $team = $this->teamWithRoles();
        $agent = $this->member($team, 'agent');

        // Historically reachable with no role check.
        $this->assertTrue($agent->hasCapability(Capability::UtilityDatabaseHealth, $team));
        $this->assertTrue($agent->hasCapability(Capability::UtilityDirectorySearch, $team));

        // Role-gated areas: denied.
        $this->assertFalse($agent->hasCapability(Capability::SystemAccess, $team));
        $this->assertFalse($agent->hasCapability(Capability::UtilityCardProcessing, $team));
        $this->assertFalse($agent->hasCapability(Capability::UtilitiesAccess, $team));
        $this->assertFalse($agent->hasCapability(Capability::UtilityConfigEditor, $team));
    }

    public function test_supervisor_matches_prior_role_gates(): void
    {
        $team = $this->teamWithRoles();
        $supervisor = $this->member($team, 'supervisor');

        $this->assertTrue($supervisor->hasCapability(Capability::UtilityBoardCheck, $team));
        $this->assertTrue($supervisor->hasCapability(Capability::BoardReview, $team));
        $this->assertTrue($supervisor->hasCapability(Capability::UtilityCloudFaxing, $team));
        $this->assertTrue($supervisor->hasCapability(Capability::UtilitiesAccess, $team));
        $this->assertTrue($supervisor->hasCapability(Capability::TeamAddMember, $team));

        // Supervisor could NOT card-process, manage the team, or see analytics.
        $this->assertFalse($supervisor->hasCapability(Capability::UtilityCardProcessing, $team));
        $this->assertFalse($supervisor->hasCapability(Capability::TeamManage, $team));
        $this->assertFalse($supervisor->hasCapability(Capability::AnalyticsView, $team));
    }

    public function test_multiple_roles_union_capabilities(): void
    {
        $team = $this->teamWithRoles();
        $user = $this->member($team, 'agent');

        $this->assertFalse($user->hasCapability(Capability::UtilitiesAccess, $team));

        // Add a second role; access is the union.
        $dispatcher = $team->roles()->where('key', 'dispatcher')->firstOrFail();
        $user->assignRole($dispatcher);
        $user = $user->fresh();

        $this->assertTrue($user->hasCapability(Capability::UtilitiesAccess, $team));
        $this->assertTrue($user->hasCapability(Capability::UtilityBoardCheck, $team));
        // Still no admin powers from either role.
        $this->assertFalse($user->hasCapability(Capability::SystemAccess, $team));
    }

    public function test_capabilities_are_scoped_per_team(): void
    {
        $teamA = $this->teamWithRoles();
        $teamB = $this->teamWithRoles();

        $user = $this->member($teamA, 'admin');
        // Member of A as admin, not on B at all.
        $this->assertTrue($user->hasCapability(Capability::SystemAccess, $teamA));
        $this->assertFalse($user->hasCapability(Capability::SystemAccess, $teamB));
    }
}
