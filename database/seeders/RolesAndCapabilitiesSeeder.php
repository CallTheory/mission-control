<?php

namespace Database\Seeders;

use App\Actions\Roles\SeedDefaultRolesForTeam;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Seeds every non-personal team with the default system roles / capabilities
 * and the global suffix rules. Idempotent — safe to re-run on existing data.
 */
class RolesAndCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $seeder = new SeedDefaultRolesForTeam;

        $seeder->seedGlobalSuffixRules();

        Team::query()
            ->where('personal_team', false)
            ->each(fn (Team $team) => $seeder($team));
    }
}
