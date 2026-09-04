<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Providers\TracingServiceProvider;
use App\Services\Observability\ObservabilityConfig;
use App\Services\Observability\SpanExporterFactory;
use App\Services\Observability\Tracing;
use Illuminate\Queue\Queue;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use ReflectionProperty;

/**
 * Turns tracing on with an in-memory exporter and a synchronous processor, so
 * assertions never depend on flush timing.
 */
trait CapturesSpans
{
    protected ?InMemoryExporter $spanExporter = null;

    protected function enableTracing(array $overrides = []): void
    {
        config(array_merge([
            'observability.tracing.enabled' => true,
            'observability.tracing.sample_rate' => 1.0,
        ], $overrides));

        ObservabilityConfig::flush();

        $this->spanExporter = new InMemoryExporter;

        // Swap the exporter before anything builds the tracer.
        $this->app->singleton(SpanExporterFactory::class, function () {
            $exporter = $this->spanExporter;

            return new class($exporter) extends SpanExporterFactory
            {
                public function __construct(private $exporter) {}

                public function make(array $tracing): SpanExporterInterface
                {
                    return $this->exporter;
                }
            };
        });

        $this->app->forgetInstance(Tracing::class);

        $provider = new TracingServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    /**
     * @return array<int, ImmutableSpan>
     */
    protected function spans(): array
    {
        app(Tracing::class)->flush();

        return $this->spanExporter?->getSpans() ?? [];
    }

    protected function spanNamed(string $name): ?object
    {
        foreach ($this->spans() as $span) {
            if ($span->getName() === $name) {
                return $span;
            }
        }

        return null;
    }

    /**
     * Queue::$createPayloadCallbacks is a static on the framework with no
     * public reset, so callbacks would otherwise leak between tests and hold a
     * stale container.
     */
    protected function tearDownCapturesSpans(): void
    {
        $property = new ReflectionProperty(Queue::class, 'createPayloadCallbacks');
        $property->setAccessible(true);
        $property->setValue(null, []);

        $hook = new ReflectionProperty(TracingServiceProvider::class, 'payloadHookRegistered');
        $hook->setAccessible(true);
        $hook->setValue(null, false);

        ObservabilityConfig::flush();
    }
}
