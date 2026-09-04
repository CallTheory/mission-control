<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CapturesSpans;

class HttpClientTracingTest extends TestCase
{
    use CapturesSpans;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownCapturesSpans();
        parent::tearDown();
    }

    public function test_an_outbound_call_produces_a_client_span(): void
    {
        $this->enableTracing();
        Http::fake(['api.example.com/*' => Http::response(['ok' => true])]);

        Http::get('https://api.example.com/v1/things?token=secret-token');

        $span = $this->spanNamed('GET api.example.com');

        $this->assertNotNull($span);

        $attributes = $span->getAttributes()->toArray();
        $this->assertSame(200, $attributes['http.response.status_code']);

        // Query strings carry tokens and must not be recorded.
        $this->assertStringNotContainsString('secret-token', $attributes['url.full']);
    }

    public function test_trace_context_is_propagated_to_the_callee(): void
    {
        $this->enableTracing();
        Http::fake(['api.example.com/*' => Http::response('ok')]);

        Http::get('https://api.example.com/v1/things');

        Http::assertSent(fn ($request) => $request->hasHeader('traceparent'));
    }

    public function test_a_failed_response_marks_the_span_as_errored(): void
    {
        $this->enableTracing();
        Http::fake(['api.example.com/*' => Http::response('nope', 500)]);

        Http::get('https://api.example.com/v1/things');

        $this->assertSame('Error', $this->spanNamed('GET api.example.com')->getStatus()->getCode());
    }
}
