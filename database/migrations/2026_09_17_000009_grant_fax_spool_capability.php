<?php

use App\Enums\Capability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grant the new fax spool maintenance capability to the roles that should hold it.
 *
 * config/roles.php covers teams seeded after this deploy; SeedDefaultRolesForTeam only
 * syncs capabilities for roles it creates, so existing installs need the grant applied
 * here. The technical role also picks up utility.cloud_faxing — it never had the fax
 * utility, so otherwise there would be no page on which to do the maintenance.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $grants = [
            'admin' => [Capability::FaxManageSpool->value],
            'technical' => [Capability::FaxManageSpool->value, Capability::UtilityCloudFaxing->value],
        ];

        foreach ($grants as $roleKey => $capabilities) {
            $roleIds = DB::table('roles')
                ->where('is_system', true)
                ->where('key', $roleKey)
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                foreach ($capabilities as $capability) {
                    DB::table('role_capability')->insertOrIgnore([
                        'role_id' => $roleId,
                        'capability' => $capability,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('role_capability')
            ->where('capability', Capability::FaxManageSpool->value)
            ->delete();

        // utility.cloud_faxing was not held by the technical role before this migration.
        $technicalRoleIds = DB::table('roles')
            ->where('is_system', true)
            ->where('key', 'technical')
            ->pluck('id');

        DB::table('role_capability')
            ->whereIn('role_id', $technicalRoleIds)
            ->where('capability', Capability::UtilityCloudFaxing->value)
            ->delete();
    }
};
