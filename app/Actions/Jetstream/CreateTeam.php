<?php

namespace App\Actions\Jetstream;

use App\Actions\Roles\SeedDefaultRolesForTeam;
use App\Models\Team;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Events\AddingTeam;
use Laravel\Jetstream\Jetstream;

class CreateTeam implements CreatesTeams
{
    /**
     * Validate and create a new team for the given user.
     *
     * @param  mixed  $user
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create($user, array $input): Team
    {
        Gate::forUser($user)->authorize('create', Jetstream::newTeamModel());

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
        ])->validateWithBag('createTeam');

        AddingTeam::dispatch($user);

        $user->switchTeam($team = $user->ownedTeams()->create([
            'name' => $input['name'],
            'personal_team' => false,
        ]));

        // Seed the new team with the default roles and grant the owner the
        // admin role so the capability system mirrors ownership from day one.
        (new SeedDefaultRolesForTeam)($team);

        if ($adminRole = $team->roles()->where('key', 'admin')->first()) {
            $user->assignRole($adminRole);
        }

        return $team;
    }
}
