<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use Laravel\Jetstream\Contracts\DeletesTeams;

class DeleteTeam implements DeletesTeams
{
    /**
     * Delete the given team.
     *
     * @param  Team  $team
     * @return void
     */
    public function delete(Team $team): void
    {
        $team->purge();
    }
}
