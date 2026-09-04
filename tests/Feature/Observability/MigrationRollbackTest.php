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
        $this->artisan('migrate:rollback', ['--step' => 3])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('settings', 'observability_errors_dsn'));
        $this->assertFalse(Schema::hasColumn('settings', 'observability_tracing_auth_token'));
        $this->artisan('migrate')->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('settings', 'observability_errors_dsn'));
    }
}
