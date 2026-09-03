<?php

use App\Enums\Capability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * config/roles.php grants the admin template every capability, so newly
     * created teams pick up system.observability automatically. Existing
     * installs do not: SeedDefaultRolesForTeam only syncs capabilities for
     * roles it creates, so their admin roles would be missing it and the new
     * page would 403 for everyone.
     */
    public function up(): void
    {
        $adminRoleIds = DB::table('roles')
            ->where('is_system', true)
            ->where('key', 'admin')
            ->pluck('id');

        $now = now();

        foreach ($adminRoleIds as $roleId) {
            DB::table('role_capability')->insertOrIgnore([
                'role_id' => $roleId,
                'capability' => Capability::SystemObservability->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role_capability')
            ->where('capability', Capability::SystemObservability->value)
            ->delete();
    }
};
