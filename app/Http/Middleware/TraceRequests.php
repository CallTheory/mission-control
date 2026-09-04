<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Observability\Tracing;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Opens the root server span for a request.
 *
 * Registered FIRST in the global middleware stack so it times everything.
 * A consequence of being first is that TrustProxies has not run and the route
 * has not been matched when handle() begins, so the span is started up front
 * but its attributes are set after $next() returns, when the real client IP,
 * scheme and matched route are known.
 *
 * MUST be a container singleton (see TracingServiceProvider): Laravel resolves
 * middleware again for terminate(), so per-instance state would be lost.
 */
class TraceRequests
{
    public function __construct(private readonly Tracing $tracing) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tracing->isEnabled() || $this->tracing->shouldIgnorePath($request->path())) {
            return $next($request);
        }

        $span = null;
        $scope = null;

        try {
            $parent = TraceContextPropagator::getInstance()
                ->extract($this->headers($request));

            $span = $this->tracing->tracer()
                ->spanBuilder(strtoupper($request->method()))
                ->setParent($parent)
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->startSpan();

            $scope = $span->activate();
            $this->tracing->setRoot($span, $scope);
        } catch (Throwable) {
            return $next($request);
        }

        try {
            $response = $next($request);

            $this->describe($span, $request, $response);

            /*
             * Trace ids carry no data, and exposing one turns "something broke"
             * into a support request someone can actually look up in Tempo.
             */
            try {
                $traceId = $span->getContext()->getTraceId();

                if ($traceId !== '' && $response->headers !== null) {
                    $response->headers->set('X-Trace-Id', $traceId);
                }
            } catch (Throwable) {
                // header is best-effort
            }

            return $response;
        } catch (Throwable $e) {
            try {
                $span->recordException($e);
                $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            } catch (Throwable) {
                // never mask the original exception
            }

            throw $e;
        } finally {
            try {
                $scope?->detach();
                $span?->end();
                $this->tracing->clearRoot();
            } catch (Throwable) {
                // instrumentation must not break the response
            }
        }
    }

    private function describe(mixed $span, Request $request, Response $response): void
    {
        try {
            $route = $request->route();
            $uri = $route !== null ? '/'.ltrim($route->uri(), '/') : null;

            // Route TEMPLATE, never the raw URL — otherwise every id becomes a
            // distinct span name and the data is unusable.
            $span->updateName($this->spanName($request, $uri));

            $status = $response->getStatusCode();

            $span->setAttributes(array_filter([
                'http.request.method' => $request->method(),
                'http.route' => $uri,
                'http.response.status_code' => $status,
                'url.path' => $request->getPathInfo(),
                'url.scheme' => $request->getScheme(),
                'server.address' => $request->getHost(),
                'server.port' => $request->getPort(),
                'client.address' => $request->ip(),
                'user_agent.original' => Str::limit((string) $request->userAgent(), 256, ''),
                'network.protocol.version' => $request->getProtocolVersion(),
                'laravel.route.name' => $route?->getName(),
            ], static fn ($v) => $v !== null && $v !== ''));

            $config = $this->tracing->config()['instrumentation']['http'] ?? [];

            if (($config['capture_user'] ?? true) && $request->user() !== null) {
                // Numeric ids only — never an email or username.
                $span->setAttribute('app.user.id', (int) $request->user()->getAuthIdentifier());

                if ($teamId = $request->user()->current_team_id ?? null) {
                    $span->setAttribute('app.team.id', (int) $teamId);
                }
            }

            // Off by default: query strings carry tokens and client identifiers.
            if (($config['capture_query'] ?? false) && $request->getQueryString() !== null) {
                $span->setAttribute('url.query', $request->getQueryString());
            }

            $this->describeLivewire($span, $request, $route?->getName());

            // Per OTel semconv a 4xx is not a server error; only 5xx is.
            if ($status >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR, 'HTTP '.$status);
            }
        } catch (Throwable) {
            // attributes are best-effort
        }
    }

    private function spanName(Request $request, ?string $uri): string
    {
        $method = strtoupper($request->method());

        // Unmatched (404): semconv forbids inventing a low-cardinality name.
        return $uri === null ? $method : $method.' '.$uri;
    }

    /**
     * Every Livewire interaction posts to the same route, so without this the
     * entire Livewire surface collapses into one useless span name.
     */
    private function describeLivewire(mixed $span, Request $request, ?string $routeName): void
    {
        if ($routeName === null || ! str_ends_with($routeName, 'livewire.update')) {
            return;
        }

        try {
            $snapshot = json_decode(
                (string) data_get($request->input('components.0'), 'snapshot'),
                true
            );

            $component = data_get($snapshot, 'memo.name');

            if (is_string($component) && $component !== '') {
                $span->updateName('LIVEWIRE '.$component);
                $span->setAttribute('livewire.component', $component);
            }

            // Method NAMES only. Never params — they carry form values.
            $calls = collect($request->input('components.0.calls', []))
                ->pluck('method')
                ->filter()
                ->take(3)
                ->implode(',');

            if ($calls !== '') {
                $span->setAttribute('livewire.calls', $calls);
            }
        } catch (Throwable) {
            // naming is best-effort
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $carrier = [];

        foreach (['traceparent', 'tracestate'] as $header) {
            if ($request->headers->has($header)) {
                $carrier[$header] = (string) $request->headers->get($header);
            }
        }

        return $carrier;
    }
}
