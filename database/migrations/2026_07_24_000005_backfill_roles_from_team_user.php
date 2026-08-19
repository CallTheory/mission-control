<?php

use App\Actions\Roles\SeedDefaultRolesForTeam;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reproduce existing access under the new capability system: seed each
     * non-personal team's default roles, then convert every team_user.role
     * string into a role_user assignment pointing at that team's matching role.
     */
    public function up(): void
    {
        $seeder = new SeedDefaultRolesForTeam;
        $seeder->seedGlobalSuffixRules();

        Team::query()->where('personal_team', false)->each(function (Team $team) use ($seeder) {
            $seeder($team);

            $roleIdsByKey = Role::query()
                ->where('team_id', $team->id)
                ->pluck('id', 'key');

            $memberships = DB::table('team_user')
                ->where('team_id', $team->id)
                ->whereNotNull('role')
                ->get(['user_id', 'role']);

            foreach ($memberships as $membership) {
                $roleId = $roleIdsByKey[$membership->role] ?? null;

                if ($roleId === null) {
                    continue;
                }

                DB::table('role_user')->updateOrInsert(
                    ['role_id' => $roleId, 'user_id' => $membership->user_id],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }
        });
    }

    public function down(): void
    {
        DB::table('role_user')->delete();
        DB::table('role_capability')->delete();
        DB::table('suffix_rules')->delete();
        DB::table('roles')->delete();
    }
};
