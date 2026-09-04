<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Http\Kernel;
use App\Http\Middleware\TraceRequests;
use App\Services\Observability\ObservabilityConfig;
use App\Services\Observability\Tracing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TracingDisabledTest extends TestCase
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

    public function test_tracing_is_off_on_a_fresh_install(): void
    {
        $this->assertFalse(app(Tracing::class)->isEnabled());
        $this->assertNull(app(Tracing::class)->currentTraceId());
    }

    public function test_a_request_still_works_with_tracing_off(): void
    {
        // The guest root redirects to login; what matters is that the
        // middleware in position #1 does not interfere.
        $this->get('/')->assertRedirect();
    }

    public function test_the_middleware_is_registered_first(): void
    {
        $kernel = new \ReflectionClass(Kernel::class);
        $property = $kernel->getProperty('middleware');
        $property->setAccessible(true);

        $middleware = $property->getValue($this->app->make(Kernel::class));

        // Must wrap everything, including TrustProxies and maintenance mode.
        $this->assertSame(TraceRequests::class, $middleware[0]);
    }

    public function test_the_middleware_is_a_singleton(): void
    {
        // Kernel::terminateMiddleware() resolves middleware again; without a
        // singleton binding the span opened in handle() would be lost.
        $this->assertSame(
            app(TraceRequests::class),
            app(TraceRequests::class)
        );
    }
}
