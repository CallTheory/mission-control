<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Logging\AddTraceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\CapturesSpans;

/**
 * The three handles that let an operator get from a symptom back to a trace.
 */
class TraceCorrelationTest extends TestCase
{
    use CapturesSpans;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownCapturesSpans();
        parent::tearDown();
    }

    public function test_the_response_carries_an_x_trace_id_header(): void
    {
        Route::get('/trace-header', fn () => response('ok'));
        $this->enableTracing();

        $response = $this->get('/trace-header');

        $traceId = $response->headers->get('X-Trace-Id');

        $this->assertNotNull($traceId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $traceId);
        $this->assertSame($this->spanNamed('GET /trace-header')->getContext()->getTraceId(), $traceId);
    }

    public function test_no_trace_header_when_tracing_is_disabled(): void
    {
        Route::get('/trace-header-off', fn () => response('ok'));

        $this->assertNull($this->get('/trace-header-off')->headers->get('X-Trace-Id'));
    }

    public function test_api_error_responses_include_a_trace_id(): void
    {
        // Gives the otherwise opaque "An unclassified error occurred." a handle.
        Route::get('/api/trace-boom', fn () => throw new RuntimeException('boom'));
        $this->enableTracing();

        $body = $this->getJson('/api/trace-boom')->json();

        $this->assertArrayHasKey('trace_id', $body);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $body['trace_id']);
    }

    public function test_api_errors_omit_the_trace_id_when_tracing_is_off(): void
    {
        Route::get('/api/trace-boom-off', fn () => throw new RuntimeException('boom'));

        $this->assertArrayNotHasKey('trace_id', $this->getJson('/api/trace-boom-off')->json());
    }

    public function test_log_records_are_stamped_with_the_trace_context(): void
    {
        Route::get('/trace-log', function () {
            Log::info('inside a traced request');

            return response('ok');
        });
        $this->enableTracing();

        $handler = new TestHandler;
        Log::swap(new \Illuminate\Log\Logger(new Logger('test', [$handler])));
        (new AddTraceContext)(Log::getLogger());

        $this->get('/trace-log');

        $record = collect($handler->getRecords())
            ->first(fn ($r) => $r['message'] === 'inside a traced request');

        $this->assertNotNull($record);
        $this->assertArrayHasKey('trace_id', $record['extra']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $record['extra']['trace_id']);
    }

    public function test_the_log_tap_is_harmless_when_tracing_is_off(): void
    {
        $handler = new TestHandler;
        Log::swap(new \Illuminate\Log\Logger(new Logger('test', [$handler])));
        (new AddTraceContext)(Log::getLogger());

        Log::info('no trace here');

        $record = collect($handler->getRecords())->first();

        $this->assertNotNull($record);
        $this->assertArrayNotHasKey('trace_id', $record['extra']);
    }
}
