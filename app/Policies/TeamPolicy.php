<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        if ($user->currentTeam->personal_team === true) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        if ($user->currentTeam->personal_team === true && Team::count() > 1) {
            return false;
        }

        return
            $user->hasTeamRole($user->currentTeam, 'admin') ||
            $user->hasTeamRole($user->currentTeam, 'manager') ||
            $user->ownsTeam($user->currentTeam);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function update(User $user, Team $team): bool
    {
        if ($team->personal_team === true) {
            return false;
        }

        return
            $user->hasTeamRole($team, 'admin') ||
            $user->hasTeamRole($team, 'manager') ||
            $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can add team members.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function addTeamMember(User $user, Team $team): bool
    {
        if ($team->personal_team === true) {
            return false;
        }

        return
            $user->hasTeamRole($team, 'admin') ||
            $user->hasTeamRole($team, 'manager') ||
            $user->hasTeamRole($team, 'supervisor') ||
            $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can update team member permissions.
     *
     * @param User $user
     * @param Team $team
     * @return mixed
     */
    public function updateTeamMember(User $user, Team $team): bool
    {
        if ($team->personal_team === true) {
            return false;
        }

        return
            $user->hasTeamRole($team, 'admin') ||
            $user->hasTeamRole($team, 'manager') ||
            $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can remove team members.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function removeTeamMember(User $user, Team $team): bool
    {
        if ($team->personal_team === true) {
            return false;
        }

        return
            $user->hasTeamRole($team, 'admin') ||
            $user->hasTeamRole($team, 'manager') ||
            $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param Team $team
     * @return bool
     */
    public function delete(User $user, Team $team): bool
    {
        if ($team->personal_team === true) {
            return false;
        }

        return
            $user->hasTeamRole($team, 'admin') ||
            $user->hasTeamRole($team, 'manager') ||
            $user->ownsTeam($team);
    }
}
