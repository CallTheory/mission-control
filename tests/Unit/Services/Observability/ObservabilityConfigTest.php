<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Observability;

use App\Models\System\Settings;
use App\Services\Observability\ObservabilityConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ObservabilityConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ObservabilityConfig::flush();
    }

    protected function tearDown(): void
    {
        ObservabilityConfig::flush();

        parent::tearDown();
    }

    public function test_errors_are_disabled_by_default_on_a_fresh_install(): void
    {
        // No settings row, no env vars.
        $this->assertFalse(ObservabilityConfig::errorsEnabled());
        $this->assertNull(ObservabilityConfig::errors()['dsn']);
    }

    public function test_disabled_when_the_row_exists_but_the_flag_is_false(): void
    {
        $this->makeSettings(['observability_errors_enabled' => false]);

        $this->assertFalse(ObservabilityConfig::errorsEnabled());
    }

    public function test_database_values_are_used_when_env_is_unset(): void
    {
        $this->makeSettings([
            'observability_errors_enabled' => true,
            'observability_errors_dsn' => 'https://key@glitchtip.example.com/3',
            'observability_environment' => 'staging',
            'observability_errors_sample_rate' => 0.25,
        ]);

        $errors = ObservabilityConfig::errors();

        $this->assertTrue(ObservabilityConfig::errorsEnabled());
        $this->assertSame('https://key@glitchtip.example.com/3', $errors['dsn']);
        $this->assertSame('staging', $errors['environment']);
        $this->assertSame(0.25, $errors['sample_rate']);
    }

    public function test_env_override_wins_over_the_database(): void
    {
        $this->makeSettings(['observability_errors_enabled' => true]);

        // The kill switch: env says off, the database says on.
        config(['observability.errors.enabled' => false]);
        ObservabilityConfig::flush();

        $this->assertFalse(ObservabilityConfig::errorsEnabled());
        $this->assertTrue(ObservabilityConfig::errors()['overridden_by_env']);
    }

    public function test_env_override_can_also_force_it_on(): void
    {
        $this->makeSettings(['observability_errors_enabled' => false]);

        config([
            'observability.errors.enabled' => true,
            'observability.errors.dsn' => 'https://key@env.example.com/1',
        ]);
        ObservabilityConfig::flush();

        $this->assertTrue(ObservabilityConfig::errorsEnabled());
        $this->assertSame('https://key@env.example.com/1', ObservabilityConfig::errors()['dsn']);
    }

    public function test_it_returns_defaults_when_the_settings_table_is_missing(): void
    {
        // Simulates a fresh install or migrate:fresh: this code runs at boot,
        // so an uncaught QueryException here would be fatal.
        Schema::drop('settings');

        $this->assertFalse(ObservabilityConfig::errorsEnabled());
        $this->assertFalse(ObservabilityConfig::tracingEnabled());
    }

    public function test_the_disabled_fast_path_performs_no_queries(): void
    {
        config([
            'observability.errors.enabled' => false,
            'observability.tracing.enabled' => false,
        ]);
        ObservabilityConfig::flush();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        ObservabilityConfig::errorsEnabled();

        $this->assertSame(0, $queries);
    }

    public function test_the_result_is_memoized(): void
    {
        $this->makeSettings(['observability_errors_enabled' => true]);
        ObservabilityConfig::errorsEnabled();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        ObservabilityConfig::errorsEnabled();
        ObservabilityConfig::errors();

        $this->assertSame(0, $queries);
    }

    public function test_flush_forces_a_reread(): void
    {
        $settings = $this->makeSettings(['observability_errors_enabled' => false]);
        $this->assertFalse(ObservabilityConfig::errorsEnabled());

        $settings->observability_errors_enabled = true;
        $settings->save();

        $this->assertFalse(ObservabilityConfig::errorsEnabled(), 'still memoized');

        ObservabilityConfig::flush();

        $this->assertTrue(ObservabilityConfig::errorsEnabled());
    }

    public function test_the_dsn_is_encrypted_at_rest(): void
    {
        $this->makeSettings([
            'observability_errors_enabled' => true,
            'observability_errors_dsn' => 'https://key@glitchtip.example.com/3',
        ]);

        $raw = DB::table('settings')->value('observability_errors_dsn');

        $this->assertNotSame('https://key@glitchtip.example.com/3', $raw);
        $this->assertSame(
            'https://key@glitchtip.example.com/3',
            ObservabilityConfig::errors()['dsn']
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeSettings(array $attributes): Settings
    {
        $settings = new Settings;

        foreach ($attributes as $key => $value) {
            $settings->{$key} = $value;
        }

        $settings->save();
        ObservabilityConfig::flush();

        return $settings;
    }
}
