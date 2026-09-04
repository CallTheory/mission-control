<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Providers\TracingServiceProvider;
use App\Services\Observability\ObservabilityConfig;
use App\Services\Observability\Tracing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A broken or absent collector must never break a user request. This uses the
 * REAL exporter pointed at a port nothing listens on.
 */
class ExporterFailureTest extends TestCase
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

    public function test_an_unreachable_collector_does_not_break_a_request(): void
    {
        Route::get('/trace-dead-collector', fn () => response('ok'));
        $this->enableWithDeadEndpoint();

        $start = microtime(true);

        $this->get('/trace-dead-collector')->assertOk();
        app(Tracing::class)->flush();

        $elapsed = microtime(true) - $start;

        // A regression that re-enables the SDK's default 3 retries would turn
        // this into ~6 seconds.
        $this->assertLessThan(
            5.0,
            $elapsed,
            'export against a dead collector must stay bounded by the timeouts'
        );
    }

    public function test_a_job_still_completes_with_a_dead_collector(): void
    {
        $this->enableWithDeadEndpoint();

        TracedTestJob::dispatch();

        // Reaching here without an exception is the assertion.
        $this->assertTrue(true);
    }

    public function test_repeated_failures_degrade_the_tracer_for_the_process(): void
    {
        $this->enableWithDeadEndpoint();

        $tracing = app(Tracing::class);
        $this->assertTrue($tracing->isEnabled());

        // An empty queue flushes successfully, so each round must actually
        // produce a span for the export to fail on.
        for ($i = 0; $i < 4; $i++) {
            $span = $tracing->tracer()?->spanBuilder("probe-{$i}")->startSpan();
            $span?->end();
            $tracing->flush();
        }

        $this->assertFalse(
            $tracing->isEnabled(),
            'after repeated export failures tracing should stop trying for this process'
        );
    }

    private function enableWithDeadEndpoint(): void
    {
        config([
            'observability.tracing.enabled' => true,
            'observability.tracing.sample_rate' => 1.0,
            // Port 1 refuses connections immediately.
            'observability.tracing.exporter.endpoint' => 'http://127.0.0.1:1',
            'observability.tracing.exporter.timeout' => 0.2,
            'observability.tracing.exporter.connect_timeout' => 0.2,
            'observability.tracing.exporter.max_retries' => 0,
        ]);

        ObservabilityConfig::flush();
        $this->app->forgetInstance(Tracing::class);

        $provider = new TracingServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }
}
