<?php

declare(strict_types=1);

namespace App\Services\Observability;

use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Throwable;

/**
 * Wraps the real exporter so export failures become visible.
 *
 * BatchSpanProcessor::forceFlush() returns true even when the underlying
 * transport failed — it reports "an export was attempted", logging the
 * transport error to the SDK's own logger rather than surfacing it. Without
 * this decorator the circuit breaker in Tracing would never trip and a dead
 * collector would cost every request its connect timeout, forever.
 */
class ObservedSpanExporter implements SpanExporterInterface
{
    /** @var callable(bool): void */
    private $observer;

    public function __construct(
        private readonly SpanExporterInterface $inner,
        callable $observer,
    ) {
        $this->observer = $observer;
    }

    public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
    {
        $future = $this->inner->export($batch, $cancellation);

        return $future
            ->map(function ($result) {
                ($this->observer)($result !== false);

                return $result;
            })
            ->catch(function (Throwable $e) {
                ($this->observer)(false);

                return false;
            });
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return $this->inner->shutdown($cancellation);
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return $this->inner->forceFlush($cancellation);
    }
}
