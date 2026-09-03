<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Models\System\Settings;
use App\Providers\ObservabilityServiceProvider;
use App\Services\Observability\ObservabilityConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Sentry\State\HubInterface;
use Tests\TestCase;

class SentryRegistrationTest extends TestCase
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

    public function test_sentry_is_not_bound_on_a_fresh_install(): void
    {
        // The load-bearing assertion for the whole opt-in guarantee. If this
        // fails, extra.laravel.dont-discover is not taking effect and the SDK
        // is booting itself regardless of configuration.
        $this->assertFalse($this->app->bound(HubInterface::class));
    }

    public function test_sentry_is_not_bound_when_enabled_without_a_dsn(): void
    {
        $this->configure(['observability_errors_enabled' => true]);

        $this->assertFalse($this->app->bound(HubInterface::class));
    }

    public function test_sentry_is_bound_when_enabled_with_a_dsn(): void
    {
        $this->configure([
            'observability_errors_enabled' => true,
            'observability_errors_dsn' => 'https://publickey@glitchtip.example.com/3',
        ]);

        $this->assertTrue($this->app->bound(HubInterface::class));
    }

    public function test_performance_monitoring_stays_off(): void
    {
        $this->configure([
            'observability_errors_enabled' => true,
            'observability_errors_dsn' => 'https://publickey@glitchtip.example.com/3',
        ]);

        // Tracing belongs to the Tempo integration; enabling Sentry's own would
        // produce a second, disconnected trace tree.
        $this->assertNull(config('sentry.traces_sample_rate'));
        $this->assertNull(config('sentry.profiles_sample_rate'));
        $this->assertFalse(config('sentry.enable_metrics'));
        $this->assertFalse($this->app->providerIsLoaded(\Sentry\Laravel\Tracing\ServiceProvider::class));
    }

    public function test_request_bodies_and_pii_are_disabled(): void
    {
        $this->configure([
            'observability_errors_enabled' => true,
            'observability_errors_dsn' => 'https://publickey@glitchtip.example.com/3',
        ]);

        $this->assertSame('none', config('sentry.max_request_body_size'));
        $this->assertFalse(config('sentry.send_default_pii'));
        $this->assertFalse(config('sentry.breadcrumbs.sql_bindings'));
        $this->assertFalse(config('sentry.breadcrumbs.http_client_requests'));
    }

    /**
     * Persist settings, then re-run the provider so the binding reflects them.
     * The application was booted before the row existed.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function configure(array $attributes): void
    {
        $settings = new Settings;

        foreach ($attributes as $key => $value) {
            $settings->{$key} = $value;
        }

        $settings->save();

        ObservabilityConfig::flush();
        (new ObservabilityServiceProvider($this->app))->register();
    }
}
