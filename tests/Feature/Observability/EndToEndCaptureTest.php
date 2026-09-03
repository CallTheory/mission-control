<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Models\System\Settings;
use App\Providers\ObservabilityServiceProvider;
use App\Services\Observability\ObservabilityConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Sentry\State\HubInterface;
use Tests\Mocks\SpyTransport;
use Tests\TestCase;

class EndToEndCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ObservabilityConfig::flush();
        SpyTransport::reset();
    }

    protected function tearDown(): void
    {
        ObservabilityConfig::flush();
        SpyTransport::reset();

        parent::tearDown();
    }

    public function test_a_captured_exception_reaches_the_transport_scrubbed(): void
    {
        $this->enableWithSpyTransport();

        \Sentry\captureException(new RuntimeException(
            'Delivery failed for Caller ID: 5551234567 (patient@hospital.example)'
        ));

        app(HubInterface::class)->getClient()->flush();

        $this->assertCount(1, SpyTransport::$events);

        $value = SpyTransport::$events[0]->getExceptions()[0]->getValue();

        $this->assertStringNotContainsString('5551234567', $value);
        $this->assertStringNotContainsString('patient@hospital.example', $value);
    }

    public function test_nothing_is_captured_when_reporting_is_disabled(): void
    {
        // No settings row at all: the SDK must not even be bound.
        $this->assertFalse($this->app->bound(HubInterface::class));
        $this->assertCount(0, SpyTransport::$events);
    }

    private function enableWithSpyTransport(): void
    {
        $settings = new Settings;
        $settings->observability_errors_enabled = true;
        $settings->observability_errors_dsn = 'https://publickey@glitchtip.example.com/3';
        $settings->save();

        ObservabilityConfig::flush();
        (new ObservabilityServiceProvider($this->app))->register();

        // Swap in the spy after the provider has set config('sentry').
        config(['sentry.transport' => new SpyTransport]);

        // Force the hub to rebuild with the spy transport.
        $this->app->forgetInstance(HubInterface::class);
        \Sentry\SentrySdk::setCurrentHub($this->app->make(HubInterface::class));
    }
}
