<?php

namespace Tests\Feature\Observability;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_creates_every_observability_column(): void
    {
        foreach ([
            'observability_errors_enabled', 'observability_errors_dsn', 'observability_environment',
            'observability_release', 'observability_errors_sample_rate', 'observability_last_test_at',
            'observability_last_test_status', 'observability_tracing_enabled', 'observability_tracing_endpoint',
            'observability_tracing_protocol', 'observability_tracing_auth_username', 'observability_tracing_auth_token',
            'observability_tracing_service_name', 'observability_tracing_sample_rate',
            'observability_tracing_db_spans_enabled', 'observability_tracing_db_slow_query_ms',
            'observability_tracing_export_timeout_ms',
        ] as $col) {
            $this->assertTrue(Schema::hasColumn('settings', $col), "missing column {$col}");
        }
    }

    public function test_migrations_roll_back_cleanly(): void
    {
        // Target the observability migrations by path rather than by --step,
        // so appending an unrelated migration later cannot silently change
        // which ones this rolls back.
        $this->artisan('migrate:rollback', ['--path' => [
            'database/migrations/2026_09_03_000001_add_observability_errors_to_settings.php',
            'database/migrations/2026_09_03_000002_grant_observability_capability_to_admin_roles.php',
            'database/migrations/2026_09_03_000003_add_observability_tracing_to_settings.php',
        ]])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('settings', 'observability_errors_dsn'));
        $this->assertFalse(Schema::hasColumn('settings', 'observability_tracing_auth_token'));
        $this->artisan('migrate')->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('settings', 'observability_errors_dsn'));
    }
}
