<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\LoggerHolder;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SemConv\ResourceAttributes;
use Throwable;

/**
 * The only class that touches OpenTelemetry SDK types during normal operation.
 * Keeping the SDK confined here is what lets the disabled path avoid
 * autoloading any of it.
 */
class TracerFactory
{
    /**
     * @param  array<string, mixed>  $tracing
     */
    public function make(array $tracing): TracerProviderInterface
    {
        /*
         * Route the SDK's own diagnostics through Laravel's logger. Otherwise
         * a failing export writes stack traces to STDERR, which under a queue
         * worker fills the console and under FPM lands in the web server log.
         */
        try {
            // Log::channel() already returns a PSR-3 logger.
            LoggerHolder::set(Log::channel(config('logging.default')));
        } catch (Throwable) {
            // Diagnostics routing is best-effort.
        }

        $resource = $tracing['resource'];

        $resourceInfo = ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create(array_filter([
                ResourceAttributes::SERVICE_NAME => $resource['service_name'],
                ResourceAttributes::SERVICE_NAMESPACE => $resource['service_namespace'],
                ResourceAttributes::SERVICE_VERSION => $resource['service_version'],
                ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => $resource['deployment_environment'],
                'host.name' => gethostname() ?: 'unknown',
                'process.runtime.name' => 'php',
                'process.runtime.version' => PHP_VERSION,
                // Lets you separate web traffic from workers in Tempo.
                'laravel.role' => self::role(),
            ], static fn ($v) => $v !== null && $v !== '')))
        );

        $batch = $tracing['batch'];

        $processor = new BatchSpanProcessor(
            new ObservedSpanExporter(
                app(SpanExporterFactory::class)->make($tracing),
                fn (bool $ok) => app(Tracing::class)->observeExport($ok),
            ),
            Clock::getDefault(),
            (int) ($batch['max_queue_size'] ?? 512),
            (int) ($batch['schedule_delay_millis'] ?? 1000),
            (int) ($tracing['exporter']['timeout_ms'] ?? 2000),
            (int) ($batch['max_export_batch_size'] ?? 128),
        );

        return TracerProvider::builder()
            ->addSpanProcessor($processor)
            ->setResource($resourceInfo)
            // ParentBased means the sampling decision is made once at the root
            // and rides the traceparent into queued jobs, so a trace is either
            // captured whole or not at all — never a job span orphaned from the
            // request that dispatched it.
            ->setSampler(new ParentBased(
                new TraceIdRatioBasedSampler((float) $tracing['sample_rate'])
            ))
            ->build();
    }

    private static function role(): string
    {
        if (! app()->runningInConsole()) {
            return 'web';
        }

        $command = (string) (request()->server('argv')[1] ?? '');

        return match (true) {
            str_starts_with($command, 'horizon') => 'horizon',
            str_starts_with($command, 'queue:') => 'worker',
            default => 'console',
        };
    }
}
