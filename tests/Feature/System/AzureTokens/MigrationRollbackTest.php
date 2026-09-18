<?php

declare(strict_types=1);

namespace Tests\Feature\System\AzureTokens;

use App\Enums\Capability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

class MigrationRollbackTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    /** The migrations this feature owns, in the order they run. */
    private const PATHS = [
        'database/migrations/2026_09_18_000002_add_azure_graph_to_data_sources.php',
        'database/migrations/2026_09_18_000003_create_azure_credentials_table.php',
        'database/migrations/2026_09_18_000004_create_azure_credential_sweeps_table.php',
        'database/migrations/2026_09_18_000005_add_azure_token_alerts_to_settings.php',
        'database/migrations/2026_09_18_000006_grant_azure_tokens_capability_to_admin_roles.php',
    ];

    public function test_up_creates_every_column_the_feature_reads(): void
    {
        foreach (['azure_tenant_id', 'azure_client_id', 'azure_client_secret', 'azure_enabled'] as $column) {
            $this->assertTrue(Schema::hasColumn('data_sources', $column), "missing column {$column}");
        }

        foreach (['azure_tokens_alert_enabled', 'azure_tokens_alert_recipients'] as $column) {
            $this->assertTrue(Schema::hasColumn('settings', $column), "missing column {$column}");
        }

        foreach ([
            'key_id', 'app_object_id', 'app_client_id', 'app_name', 'source', 'cred_type', 'cred_name',
            'hint', 'start_utc', 'end_utc', 'last_seen_utc', 'removed_at', 'acknowledged',
            'acknowledged_at', 'acknowledged_by', 'alerted_threshold',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('azure_credentials', $column), "missing column {$column}");
        }

        $this->assertTrue(Schema::hasColumn('azure_credential_sweeps', 'alerts_sent'));
    }

    /**
     * Rolled back by path rather than by --step, so appending an unrelated migration
     * later cannot silently change which ones this covers.
     */
    public function test_migrations_roll_back_cleanly(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::PATHS])->assertSuccessful();

        $this->assertFalse(Schema::hasTable('azure_credentials'));
        $this->assertFalse(Schema::hasTable('azure_credential_sweeps'));
        $this->assertFalse(Schema::hasColumn('data_sources', 'azure_client_secret'));
        $this->assertFalse(Schema::hasColumn('settings', 'azure_tokens_alert_recipients'));

        $this->artisan('migrate')->assertSuccessful();

        $this->assertTrue(Schema::hasTable('azure_credentials'));
        $this->assertTrue(Schema::hasColumn('data_sources', 'azure_client_secret'));
    }

    /**
     * Existing installs get the capability from the grant migration rather than from
     * config/roles.php, which only applies to roles seeded after this ships.
     */
    public function test_the_capability_is_granted_to_existing_system_admin_roles(): void
    {
        $team = $this->createSeededTeam();
        $adminRoleId = $team->roles()->where('key', 'admin')->firstOrFail()->id;

        // Stand in for an install that predates the capability.
        DB::table('role_capability')
            ->where('capability', Capability::SystemAzureTokens->value)
            ->delete();

        $this->artisan('migrate:rollback', ['--path' => [
            'database/migrations/2026_09_18_000006_grant_azure_tokens_capability_to_admin_roles.php',
        ]])->assertSuccessful();

        $this->artisan('migrate')->assertSuccessful();

        $this->assertDatabaseHas('role_capability', [
            'role_id' => $adminRoleId,
            'capability' => Capability::SystemAzureTokens->value,
        ]);
    }
}
