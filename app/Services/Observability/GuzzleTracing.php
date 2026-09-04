<?php

declare(strict_types=1);

namespace App\Services\Observability;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Guzzle middleware that times outbound calls and propagates trace context.
 *
 * Coverage note: Laravel's Http facade is used at exactly ONE call site in this
 * application (app/Jobs/ForwardToEnterpriseHost.php). Everything else builds
 * Guzzle directly, which is why handlerStack() exists — pass it into those
 * clients. The Twilio, RingCentral and Stripe SDKs use their own internal
 * clients and remain un-traced; calls through them show up as unexplained gaps
 * inside their parent span.
 */
class GuzzleTracing
{
    /**
     * A handler stack with tracing attached, or null when tracing is off — so
     * call sites can pass it straight into `new Client(['handler' => ...])`.
     */
    public static function handlerStack(?HandlerStack $stack = null): ?HandlerStack
    {
        if (! app(Tracing::class)->isEnabled()) {
            return null;
        }

        $stack ??= HandlerStack::create();
        $stack->push(self::middleware(), 'otel_tracing');

        return $stack;
    }

    public static function middleware(): callable
    {
        return function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler) {
                $tracing = app(Tracing::class);

                if (! $tracing->isEnabled() || ! $tracing->allowChildSpan()) {
                    return $handler($request, $options);
                }

                $span = null;
                $scope = null;

                try {
                    $uri = $request->getUri();

                    $span = $tracing->tracer()
                        ->spanBuilder(strtoupper($request->getMethod()).' '.$uri->getHost())
                        ->setSpanKind(SpanKind::KIND_CLIENT)
                        ->startSpan();

                    $span->setAttributes([
                        'http.request.method' => $request->getMethod(),
                        // userinfo and query stripped: they carry tokens.
                        'url.full' => (string) $uri->withUserInfo('')->withQuery(''),
                        'server.address' => $uri->getHost(),
                        'server.port' => $uri->getPort(),
                    ]);

                    $scope = $span->activate();

                    $carrier = [];
                    TraceContextPropagator::getInstance()
                        ->inject($carrier);

                    foreach ($carrier as $header => $value) {
                        $request = $request->withHeader($header, $value);
                    }
                } catch (Throwable) {
                    $scope?->detach();

                    return $handler($request, $options);
                }

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($span, $scope) {
                        self::finish($span, $scope, $response->getStatusCode(), null);

                        return $response;
                    },
                    function ($reason) use ($span, $scope) {
                        self::finish($span, $scope, null, $reason);

                        return Create::rejectionFor($reason);
                    }
                );
            };
        };
    }

    private static function finish(mixed $span, mixed $scope, ?int $status, mixed $reason): void
    {
        try {
            if ($status !== null) {
                $span->setAttribute('http.response.status_code', $status);

                if ($status >= 400) {
                    $span->setStatus(StatusCode::STATUS_ERROR, 'HTTP '.$status);
                }
            }

            if ($reason instanceof Throwable) {
                $span->recordException($reason);
                $span->setStatus(StatusCode::STATUS_ERROR, $reason->getMessage());
            }

            $scope?->detach();
            $span->end();
        } catch (Throwable) {
            // instrumentation must never break the caller
        }
    }
}
