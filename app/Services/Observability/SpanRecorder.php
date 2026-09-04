<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Events\QueryExecuted;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Trace\Span;
use Throwable;

/**
 * Creates the non-HTTP spans (queue jobs, artisan commands, database queries).
 *
 * Every public method is wrapped so instrumentation can never break the thing
 * it is instrumenting — a throwing JobProcessed listener would propagate
 * straight into the queue worker loop.
 */
class SpanRecorder
{
    public function __construct(private readonly Tracing $tracing) {}

    /**
     * Trace context to embed in a queued job payload, so the job becomes a
     * child of whatever dispatched it.
     *
     * @return array<string, mixed>
     */
    public function queuePayloadCarrier(): array
    {
        return $this->tracing->safely(function () {
            $carrier = [];

            TraceContextPropagator::getInstance()
                ->inject($carrier);

            return $carrier === [] ? [] : ['otel' => $carrier];
        }) ?? [];
    }

    public function startJobSpan(object $event): void
    {
        $this->tracing->safely(function () use ($event) {
            $payload = $event->job->payload();

            $parent = TraceContextPropagator::getInstance()
                ->extract($payload['otel'] ?? []);

            $name = ($payload['displayName'] ?? 'job').' process';

            $span = $this->tracing->tracer()->spanBuilder($name)
                ->setParent($parent)
                ->setSpanKind(SpanKind::KIND_CONSUMER)
                ->startSpan();

            $span->setAttributes(array_filter([
                'messaging.system' => config("queue.connections.{$event->connectionName}.driver"),
                'messaging.operation' => 'process',
                'messaging.destination.name' => $event->job->getQueue(),
                'messaging.message.id' => $payload['uuid'] ?? null,
                'laravel.job.class' => $payload['displayName'] ?? null,
                'laravel.job.connection' => $event->connectionName,
                'laravel.job.attempts' => $event->job->attempts(),
                // A Horizon UI retry builds a fresh payload, so it starts a new
                // trace; record the link so the two can be joined manually.
                'laravel.job.retry_of' => $payload['retry_of'] ?? null,
            ], static fn ($v) => $v !== null));

            $scope = $span->activate();
            $this->tracing->setRoot($span, $scope);
            $this->tracing->stash($this->jobKey($event), $span, $scope);
        });
    }

    public function endJobSpan(object $event, ?Throwable $exception): void
    {
        $this->tracing->safely(function () use ($event, $exception) {
            // JobExceptionOccurred and JobFailed can both fire for one job;
            // unstash() makes the second call a no-op.
            $entry = $this->tracing->unstash($this->jobKey($event));

            if ($entry === null) {
                return;
            }

            $span = $entry['span'];

            if ($exception !== null) {
                $span->recordException($exception);
                $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            } elseif (method_exists($event->job, 'isReleased') && $event->job->isReleased()) {
                // RateLimited middleware releases the job back to the queue.
                // Without this it would look like a success that did nothing.
                $span->setAttribute('laravel.job.released', true);
            }

            $entry['scope']->detach();
            $span->end();
            $this->tracing->clearRoot();
        });

        $this->tracing->flush();
    }

    public function startCommandSpan(CommandStarting $event): void
    {
        $this->tracing->safely(function () use ($event) {
            $parent = Context::getCurrent();

            // Lets a cron wrapper (or a future scheduler fix) join a child
            // process to its parent trace.
            if ($traceparent = getenv('TRACEPARENT')) {
                $parent = TraceContextPropagator::getInstance()
                    ->extract(['traceparent' => $traceparent]);
            }

            $span = $this->tracing->tracer()
                ->spanBuilder('artisan '.$event->command)
                ->setParent($parent)
                ->setSpanKind(SpanKind::KIND_INTERNAL)
                ->startSpan();

            $span->setAttribute('laravel.command', $event->command);
            $span->setAttribute('process.pid', getmypid());

            $scope = $span->activate();
            $this->tracing->setRoot($span, $scope);
            $this->tracing->stash('command', $span, $scope);
        });
    }

    public function endCommandSpan(CommandFinished $event): void
    {
        $this->tracing->safely(function () use ($event) {
            $entry = $this->tracing->unstash('command');

            if ($entry === null) {
                return;
            }

            $span = $entry['span'];
            $span->setAttribute('laravel.command.exit_code', $event->exitCode);

            if ($event->exitCode !== 0) {
                $span->setStatus(StatusCode::STATUS_ERROR, "Exited {$event->exitCode}");
            }

            $entry['scope']->detach();
            $span->end();
            $this->tracing->clearRoot();
        });
    }

    /**
     * DB::listen fires AFTER execution, so the start timestamp is synthesized
     * by subtracting the reported duration.
     */
    public function recordQuery(QueryExecuted $query): void
    {
        $this->tracing->safely(function () use ($query) {
            $db = $this->tracing->config()['instrumentation']['db'] ?? [];
            $threshold = (int) ($db['slow_query_ms'] ?? 0);

            if ($threshold > 0 && $query->time < $threshold) {
                return;
            }

            if (! $this->tracing->allowChildSpan()) {
                return;
            }

            $endNanos = Clock::getDefault()->now();
            $startNanos = $endNanos - (int) round($query->time * 1_000_000);

            // Bindings are NEVER recorded and toRawSql() is never called: they
            // carry caller names, phone numbers and DOBs.
            $statement = SqlSanitizer::sanitize(
                $query->sql,
                (int) ($db['max_statement_length'] ?? 2048)
            );
            $operation = SqlSanitizer::operation($query->sql);

            $span = $this->tracing->tracer()
                ->spanBuilder($operation.' '.$query->connectionName)
                ->setSpanKind(SpanKind::KIND_CLIENT)
                ->setStartTimestamp($startNanos)
                ->startSpan();

            $span->setAttributes(array_filter([
                'db.system' => $query->connection->getDriverName(),
                'db.namespace' => $query->connection->getDatabaseName(),
                'db.query.text' => $statement,
                'db.operation.name' => $operation,
                'laravel.db.connection' => $query->connectionName,
            ], static fn (string $v) => $v !== ''));

            $span->end($endNanos);
        });
    }

    public function startScheduledTaskSpan(object $event): void
    {
        $this->tracing->safely(function () use ($event) {
            $summary = method_exists($event->task, 'getSummaryForDisplay')
                ? (string) $event->task->getSummaryForDisplay()
                : 'task';

            $span = $this->tracing->tracer()
                ->spanBuilder('schedule '.$summary)
                ->setSpanKind(SpanKind::KIND_INTERNAL)
                ->startSpan();

            $span->setAttribute('laravel.schedule.task', $summary);

            if (property_exists($event->task, 'expression')) {
                $span->setAttribute('laravel.schedule.expression', (string) $event->task->expression);
            }

            $scope = $span->activate();
            $this->tracing->setRoot($span, $scope);
            $this->tracing->stash($this->scheduleKey($event), $span, $scope);
        });
    }

    public function endScheduledTaskSpan(object $event, ?Throwable $exception = null): void
    {
        $this->tracing->safely(function () use ($event, $exception) {
            $entry = $this->tracing->unstash($this->scheduleKey($event));

            if ($entry === null) {
                return;
            }

            $span = $entry['span'];

            if ($exception !== null) {
                $span->recordException($exception);
                $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            }

            if (property_exists($event, 'runtime')) {
                $span->setAttribute('laravel.schedule.runtime_ms', (int) round($event->runtime * 1000));
            }

            $entry['scope']->detach();
            $span->end();
            $this->tracing->clearRoot();
        });

        $this->tracing->flush();
    }

    /**
     * Scheduled tasks run sequentially in one schedule:run process, so the
     * summary is a stable enough key.
     */
    private function scheduleKey(object $event): string
    {
        return 'schedule:'.(method_exists($event->task, 'getSummaryForDisplay')
            ? $event->task->getSummaryForDisplay()
            : spl_object_hash($event->task));
    }

    public function recordExceptionOnCurrent(Throwable $e): void
    {
        $this->tracing->safely(function () use ($e) {
            $span = Span::getCurrent();

            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        });
    }

    private function jobKey(object $event): string
    {
        return 'job:'.($event->job->getJobId() ?: spl_object_hash($event->job));
    }
}
