<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Actions\Roles\SeedDefaultRolesForTeam;
use App\Enums\Capability;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;

/**
 * Builds a non-personal team seeded with the default system roles, plus users
 * holding a given role — the setup every capability/authorization test needs.
 */
trait CreatesTeamUsers
{
    protected function createSeededTeam(): Team
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        (new SeedDefaultRolesForTeam)($team);

        return $team;
    }

    /**
     * A user attached to $team and assigned the system role with key $roleKey
     * (admin, manager, supervisor, dispatcher, agent, ...).
     */
    protected function createUserWithRole(Team $team, string $roleKey): User
    {
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => $roleKey]);
        $user->switchTeam($team);
        $user->assignRole($team->roles()->where('key', $roleKey)->firstOrFail());

        return $user->fresh();
    }

    /**
     * A user whose role has had $capability revoked — for asserting that a guard
     * actually denies rather than passing because everything is permitted.
     */
    protected function createUserWithout(Team $team, string $roleKey, Capability $capability): User
    {
        $user = $this->createUserWithRole($team, $roleKey);

        /** @var Role $role */
        $role = $team->roles()->where('key', $roleKey)->firstOrFail();
        $role->capabilities()->where('capability', $capability->value)->delete();

        return $user->fresh();
    }
}
