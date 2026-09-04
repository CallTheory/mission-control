<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\CapturesSpans;

class HttpTracingTest extends TestCase
{
    use CapturesSpans;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownCapturesSpans();
        parent::tearDown();
    }

    public function test_it_records_a_server_span_named_by_route_template(): void
    {
        Route::get('/trace-test/{id}', fn ($id) => response('ok'));
        $this->enableTracing();

        $this->get('/trace-test/12345')->assertOk();

        $span = $this->spanNamed('GET /trace-test/{id}');

        $this->assertNotNull($span, 'span should be named by template, not the raw URL');

        $attributes = $span->getAttributes()->toArray();
        $this->assertSame('GET', $attributes['http.request.method']);
        $this->assertSame(200, $attributes['http.response.status_code']);
        $this->assertSame('/trace-test/{id}', $attributes['http.route']);
        $this->assertSame('/trace-test/12345', $attributes['url.path']);
    }

    public function test_the_raw_id_is_not_in_the_span_name(): void
    {
        Route::get('/trace-test/{id}', fn ($id) => response('ok'));
        $this->enableTracing();

        $this->get('/trace-test/98765');

        foreach ($this->spans() as $span) {
            $this->assertStringNotContainsString('98765', $span->getName());
        }
    }

    public function test_query_strings_are_not_captured_by_default(): void
    {
        Route::get('/trace-test-q', fn () => response('ok'));
        $this->enableTracing();

        $this->get('/trace-test-q?token=super-secret&client=12345');

        $attributes = $this->spanNamed('GET /trace-test-q')->getAttributes()->toArray();

        $this->assertArrayNotHasKey('url.query', $attributes);
    }

    public function test_ignored_paths_produce_no_spans(): void
    {
        Route::get('/queue', fn () => response('ok'));
        $this->enableTracing();

        $this->get('/queue');

        $this->assertSame([], $this->spans());
    }

    public function test_a_server_error_marks_the_span_as_errored(): void
    {
        Route::get('/trace-boom', function () {
            abort(500, 'kaboom');
        });
        $this->enableTracing();

        $this->get('/trace-boom');

        $span = $this->spanNamed('GET /trace-boom');

        $this->assertNotNull($span);
        $this->assertSame('Error', $span->getStatus()->getCode());
    }

    public function test_a_client_error_does_not_mark_the_span_as_errored(): void
    {
        // Per OTel semantic conventions a 4xx is not a server error.
        Route::get('/trace-404', fn () => abort(404));
        $this->enableTracing();

        $this->get('/trace-404');

        $span = $this->spanNamed('GET /trace-404');

        $this->assertNotNull($span);
        $this->assertNotSame('Error', $span->getStatus()->getCode());
    }

    public function test_incoming_trace_context_is_continued(): void
    {
        Route::get('/trace-parent', fn () => response('ok'));
        $this->enableTracing();

        $traceId = '0af7651916cd43dd8448eb211c80319c';

        $this->get('/trace-parent', [
            'traceparent' => "00-{$traceId}-b7ad6b7169203331-01",
        ]);

        $span = $this->spanNamed('GET /trace-parent');

        $this->assertNotNull($span);
        $this->assertSame($traceId, $span->getContext()->getTraceId());
    }
}
