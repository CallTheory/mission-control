<?php

namespace Tests\Feature\Permissions;

use App\Actions\Roles\SeedDefaultRolesForTeam;
use App\Enums\Capability;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class GrantAdminRoleToTeamOwnersTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_17_000001_grant_admin_role_to_team_owners.php');

        $migration->up();
    }

    private function teamOwnedBy(User $owner, bool $personal = false): Team
    {
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => $personal,
        ]);

        if (! $personal) {
            (new SeedDefaultRolesForTeam)($team);
        }

        return $team;
    }

    public function test_owner_without_roles_receives_admin(): void
    {
        $owner = User::factory()->create();
        $team = $this->teamOwnedBy($owner);
        $owner->switchTeam($team);

        // The state the original backfill leaves behind: no team_user row,
        // no role_user row, and therefore no capabilities at all.
        $this->assertSame([], $owner->rolesForTeam($team)->pluck('key')->all());
        $this->assertFalse(Gate::forUser($owner)->check(Capability::SystemAccess->value));

        $this->runMigration();

        $owner = $owner->fresh();

        $this->assertSame(['admin'], $owner->rolesForTeam($team)->pluck('key')->all());
        $this->assertTrue(Gate::forUser($owner)->check(Capability::SystemAccess->value));
        $this->assertTrue(Gate::forUser($owner)->check(Capability::AccountsView->value));
    }

    public function test_owner_with_an_existing_role_is_left_alone(): void
    {
        $owner = User::factory()->create();
        $team = $this->teamOwnedBy($owner);
        $owner->switchTeam($team);
        $owner->assignRole($team->roles()->where('key', 'agent')->firstOrFail());

        $this->runMigration();

        $this->assertSame(['agent'], $owner->fresh()->rolesForTeam($team)->pluck('key')->all());
    }

    public function test_personal_team_owners_are_untouched(): void
    {
        $owner = User::factory()->create();
        $team = $this->teamOwnedBy($owner, personal: true);

        $this->runMigration();

        $this->assertSame(0, DB::table('role_user')->where('user_id', $owner->id)->count());
        $this->assertSame([], $owner->fresh()->rolesForTeam($team)->pluck('key')->all());
    }

    public function test_running_twice_does_not_duplicate_assignments(): void
    {
        $owner = User::factory()->create();
        $team = $this->teamOwnedBy($owner);

        $this->runMigration();
        $this->runMigration();

        $this->assertSame(1, DB::table('role_user')->where('user_id', $owner->id)->count());
        $this->assertSame(['admin'], $owner->fresh()->rolesForTeam($team)->pluck('key')->all());
    }
}
