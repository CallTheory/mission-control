<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\Span;
use Throwable;

/**
 * The facade every instrumentation point calls.
 *
 * Two rules hold everywhere in this class:
 *
 *  1. When tracing is disabled every method is a guarded no-op that touches no
 *     SDK class, so a disabled install pays a boolean property read.
 *  2. No method ever throws. Instrumentation must never break a user request or
 *     fail a queued job — a throwing JobProcessed listener would propagate into
 *     the worker loop.
 */
class Tracing
{
    private mixed $tracer = null;

    private mixed $provider = null;

    /** @var array<string, mixed> */
    private array $config = [];

    private bool $degraded = false;

    private int $consecutiveFailures = 0;

    private int $spansThisTrace = 0;

    private bool $warned = false;

    /** Root span/scope for the current request, job or command. */
    private mixed $rootSpan = null;

    private mixed $rootScope = null;

    /** @var array<string, array{span: mixed, scope: mixed}> */
    private array $active = [];

    public static function disabled(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function enabled(mixed $provider, array $config): self
    {
        $instance = new self;
        $instance->provider = $provider;
        $instance->config = $config;

        try {
            $instance->tracer = $provider->getTracer('mission-control');
        } catch (Throwable $e) {
            $instance->degrade($e);
        }

        return $instance;
    }

    public function isEnabled(): bool
    {
        return $this->tracer !== null && ! $this->degraded;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    public function shouldIgnorePath(string $path): bool
    {
        foreach ($this->config['ignore_paths'] ?? [] as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The active trace id, or null. SDK-free so the GlitchTip integration and
     * the log processor can call it unconditionally.
     */
    public function currentTraceId(): ?string
    {
        return $this->currentTraceContext()['trace_id'] ?? null;
    }

    /**
     * @return array{trace_id: string, span_id: string}|null
     */
    public function currentTraceContext(): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            $context = Span::getCurrent()->getContext();

            if (! $context->isValid()) {
                return null;
            }

            return [
                'trace_id' => $context->getTraceId(),
                'span_id' => $context->getSpanId(),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function tracer(): mixed
    {
        return $this->tracer;
    }

    public function setRoot(mixed $span, mixed $scope): void
    {
        $this->rootSpan = $span;
        $this->rootScope = $scope;
        $this->spansThisTrace = 0;
    }

    public function rootSpan(): mixed
    {
        return $this->rootSpan;
    }

    public function clearRoot(): void
    {
        // Detach defensively: callers normally detach their own scope, but a
        // leaked scope would corrupt the context for the rest of the process.
        try {
            $this->rootScope?->detach();
        } catch (Throwable) {
            // already detached
        }

        $this->rootSpan = null;
        $this->rootScope = null;
    }

    public function stash(string $key, mixed $span, mixed $scope): void
    {
        $this->active[$key] = ['span' => $span, 'scope' => $scope];
    }

    /**
     * @return array{span: mixed, scope: mixed}|null
     */
    public function unstash(string $key): ?array
    {
        $entry = $this->active[$key] ?? null;
        unset($this->active[$key]);

        return $entry;
    }

    /**
     * Child spans are capped per trace so a runaway N+1 cannot silently
     * overrun the batch queue.
     */
    public function allowChildSpan(): bool
    {
        $max = (int) ($this->config['max_spans_per_trace'] ?? 500);

        if ($this->spansThisTrace >= $max) {
            if ($this->rootSpan !== null) {
                try {
                    $this->rootSpan->setAttribute('laravel.spans_dropped', true);
                } catch (Throwable) {
                    // best effort
                }
            }

            return false;
        }

        $this->spansThisTrace++;

        return true;
    }

    public function flush(): void
    {
        if ($this->provider === null) {
            return;
        }

        try {
            // Note: forceFlush() returns true even when the transport failed,
            // so export success is observed via ObservedSpanExporter instead
            // (see observeExport()).
            $this->provider->forceFlush();
        } catch (Throwable $e) {
            $this->noteFailure($e);
        }
    }

    public function shutdown(): void
    {
        if ($this->provider === null) {
            return;
        }

        try {
            $this->provider->shutdown();
        } catch (Throwable) {
            // Nothing useful to do while tearing down.
        }
    }

    /**
     * Called by ObservedSpanExporter for every export attempt.
     */
    public function observeExport(bool $succeeded): void
    {
        if ($succeeded) {
            $this->consecutiveFailures = 0;

            return;
        }

        $this->noteFailure(new \RuntimeException('Span export failed.'));
    }

    /**
     * Runs a callback, swallowing anything it throws.
     */
    public function safely(callable $callback): mixed
    {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            return $callback();
        } catch (Throwable $e) {
            $this->noteFailure($e);

            return null;
        }
    }

    /**
     * After repeated failures stop trying for the rest of the process. In
     * FPM a process serves many requests, so this genuinely helps; there is
     * deliberately no shared/Redis-backed breaker, which would put a network
     * round-trip in the hot path to guard against a bounded, sub-second cost.
     */
    private function noteFailure(Throwable $e): void
    {
        if (++$this->consecutiveFailures >= 3) {
            $this->degrade($e);
        }
    }

    private function degrade(Throwable $e): void
    {
        $this->degraded = true;
        $this->tracer = null;

        if (! $this->warned) {
            $this->warned = true;

            try {
                Log::warning('Tracing disabled for this process after repeated failures', [
                    'error' => $e->getMessage(),
                ]);
            } catch (Throwable) {
                // Logging itself failing must not cascade.
            }
        }
    }
}
