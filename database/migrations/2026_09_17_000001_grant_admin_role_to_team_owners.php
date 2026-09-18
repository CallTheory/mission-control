<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give every non-personal team's owner the admin role when they hold no
     * roles on that team yet.
     *
     * The original backfill walks team_user rows, but Jetstream records
     * ownership on teams.user_id and only creates a team_user row for invited
     * members. An owner who never went through the invite flow therefore came
     * out of that migration with zero roles — and, because capabilitiesFor()
     * reads only role_user and the team_user pivot, zero capabilities. Their
     * navigation collapses to Dashboard and every @can gate denies them,
     * including system.access, so they cannot even reach the permissions page
     * to grant themselves the role.
     *
     * Only owners with no existing roles are touched, so an owner an admin has
     * deliberately placed on a narrower role keeps it.
     */
    public function up(): void
    {
        $now = now();

        $adminRoleIdsByTeam = DB::table('roles')
            ->where('key', 'admin')
            ->pluck('id', 'team_id');

        $owners = DB::table('teams')
            ->where('personal_team', false)
            ->whereNotNull('user_id')
            ->get(['id', 'user_id']);

        foreach ($owners as $team) {
            $roleId = $adminRoleIdsByTeam[$team->id] ?? null;

            if ($roleId === null) {
                continue;
            }

            $hasAnyRoleOnTeam = DB::table('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $team->user_id)
                ->where('roles.team_id', $team->id)
                ->exists();

            if ($hasAnyRoleOnTeam) {
                continue;
            }

            DB::table('role_user')->insertOrIgnore([
                'role_id' => $roleId,
                'user_id' => $team->user_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Not reversible: the rows this adds are indistinguishable from an admin
     * role an operator assigned by hand, and revoking the wrong one would lock
     * a real administrator out of System. Drop the assignment in the
     * permissions UI if it is genuinely unwanted.
     */
    public function down(): void
    {
        //
    }
};
