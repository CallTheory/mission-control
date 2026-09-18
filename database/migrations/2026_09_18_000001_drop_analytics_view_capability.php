<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Analytics section was retired; `analytics.view` no longer gates anything.
 *
 * The capability has been removed from App\Enums\Capability, so on an existing install the
 * stored grants would otherwise linger as a dead "View Analytics" checkbox in the role
 * editor -- one that a well-meaning administrator could spend real time trying to make
 * work. Revoking it here is what makes the checkbox disappear.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_capability')->where('capability', 'analytics.view')->delete();
        DB::table('suffix_rules')->where('capability', 'analytics.view')->delete();
    }

    /**
     * Restore the previous grant: the manager role held it, matching the old
     * config/roles.php default.
     */
    public function down(): void
    {
        $now = now();

        $roleIds = DB::table('roles')
            ->where('is_system', true)
            ->where('key', 'manager')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_capability')->insertOrIgnore([
                'role_id' => $roleId,
                'capability' => 'analytics.view',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
