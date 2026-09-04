<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Services\Observability\SpanRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\CapturesSpans;

class QueueTracingTest extends TestCase
{
    use CapturesSpans;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownCapturesSpans();
        parent::tearDown();
    }

    public function test_the_payload_carrier_contains_a_traceparent(): void
    {
        Route::get('/trace-dispatch', function () {
            return response()->json(app(SpanRecorder::class)->queuePayloadCarrier());
        });
        $this->enableTracing();

        $carrier = $this->get('/trace-dispatch')->json();

        $this->assertArrayHasKey('otel', $carrier);
        $this->assertArrayHasKey('traceparent', $carrier['otel']);
    }

    public function test_a_foreign_traceparent_in_the_payload_is_adopted(): void
    {
        // The assertion that actually proves propagation. Under the sync queue
        // a job runs inside the dispatching request and would inherit the
        // context anyway, so this injects a FOREIGN trace id with no local
        // active span.
        $this->enableTracing();

        $traceId = '4bf92f3577b34da6a3ce929d0e0e4736';

        $job = new class($traceId)
        {
            public function __construct(private string $traceId) {}

            public function payload(): array
            {
                return [
                    'displayName' => 'ForeignJob',
                    'uuid' => 'uuid-1',
                    'otel' => ['traceparent' => "00-{$this->traceId}-00f067aa0ba902b7-01"],
                ];
            }

            public function getQueue(): string
            {
                return 'default';
            }

            public function attempts(): int
            {
                return 1;
            }

            public function getJobId(): string
            {
                return 'job-1';
            }

            public function isReleased(): bool
            {
                return false;
            }
        };

        $event = new class($job)
        {
            public string $connectionName = 'redis';

            public function __construct(public $job) {}
        };

        $recorder = app(SpanRecorder::class);
        $recorder->startJobSpan($event);
        $recorder->endJobSpan($event, null);

        $span = $this->spanNamed('ForeignJob process');

        $this->assertNotNull($span);
        $this->assertSame($traceId, $span->getContext()->getTraceId());
    }

    public function test_a_job_dispatched_from_a_request_shares_its_trace(): void
    {
        Route::get('/trace-job', function () {
            TracedTestJob::dispatch();

            return response('ok');
        });
        $this->enableTracing();

        $this->get('/trace-job')->assertOk();

        $http = $this->spanNamed('GET /trace-job');
        $job = collect($this->spans())->first(
            fn ($s) => str_contains($s->getName(), 'TracedTestJob')
        );

        $this->assertNotNull($http);
        $this->assertNotNull($job, 'the queued job should produce its own span');
        $this->assertSame(
            $http->getContext()->getTraceId(),
            $job->getContext()->getTraceId(),
            'job span must belong to the dispatching request trace'
        );
    }

    public function test_a_failing_job_produces_exactly_one_errored_span(): void
    {
        // JobExceptionOccurred and JobFailed can both fire; endJobSpan must be
        // idempotent or the span would be ended twice.
        $this->enableTracing();

        try {
            TracedTestJob::dispatch(true);
        } catch (RuntimeException) {
            // sync queue rethrows
        }

        $jobSpans = collect($this->spans())->filter(
            fn ($s) => str_contains($s->getName(), 'TracedTestJob')
        );

        $this->assertCount(1, $jobSpans);
        $this->assertSame('Error', $jobSpans->first()->getStatus()->getCode());
    }
}
