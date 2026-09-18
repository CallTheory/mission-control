<?php

use App\Enums\Capability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The WCTP gateway stops being a per-team utility and becomes a system area.
 *
 * `utility.wctp_gateway` was in the "open utilities" set in config/roles.php, so
 * every seeded role -- agent and dispatcher included -- could reach the host and
 * message screens and create, edit or delete enterprise hosts. Setting up carriers,
 * numbers and hosts is administrative work, so it moves behind two new capabilities
 * held only by the admin and technical roles.
 *
 * config/roles.php covers teams seeded after this deploy; SeedDefaultRolesForTeam
 * only syncs capabilities for roles it creates, so existing installs need the grant
 * and the revoke applied here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $roleIds = DB::table('roles')
            ->where('is_system', true)
            ->whereIn('key', ['admin', 'technical'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ([Capability::WctpManage->value, Capability::WctpMessages->value] as $capability) {
                DB::table('role_capability')->insertOrIgnore([
                    'role_id' => $roleId,
                    'capability' => $capability,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // The old capability no longer gates anything, and leaving it assigned would
        // show a dead checkbox in the role editor.
        DB::table('role_capability')->where('capability', 'utility.wctp_gateway')->delete();
        DB::table('suffix_rules')->where('capability', 'utility.wctp_gateway')->delete();
    }

    public function down(): void
    {
        DB::table('role_capability')
            ->whereIn('capability', [Capability::WctpManage->value, Capability::WctpMessages->value])
            ->delete();

        // Restore the previous grant: every seeded role had it.
        $roleIds = DB::table('roles')->where('is_system', true)->pluck('id');
        $now = now();

        foreach ($roleIds as $roleId) {
            DB::table('role_capability')->insertOrIgnore([
                'role_id' => $roleId,
                'capability' => 'utility.wctp_gateway',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
