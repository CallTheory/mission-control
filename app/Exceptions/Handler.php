<?php

namespace App\Exceptions;

use App\Services\Observability\ObservabilityConfig;
use App\Services\Observability\SpanRecorder;
use App\Services\Observability\Tracing;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Psr\Log\LogLevel;
use Sentry\Laravel\Integration;
use Sentry\State\HubInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        /*
         * Report unhandled exceptions to GlitchTip/Sentry when an operator has
         * turned it on. Guarded on the container binding as well as the config
         * because ObservabilityServiceProvider only registers the SDK when a
         * DSN is actually present.
         *
         * This is the legacy (pre-Laravel-11) wiring: Integration::handles(),
         * which the modern docs show, expects the fluent bootstrap this
         * application does not use and would silently do nothing here.
         *
         * The callback does not call ->stop(), so normal log reporting still
         * happens.
         */
        if (ObservabilityConfig::errorsEnabled() && $this->container->bound(HubInterface::class)) {
            $this->reportable(function (Throwable $e): void {
                // Correlate the GlitchTip issue with its Tempo trace. Laravel
                // renders most exceptions inside the router pipeline, so they
                // never reach the tracing middleware's catch — this is the
                // reliable hook for marking the span as failed.
                if ($context = app(Tracing::class)->currentTraceContext()) {
                    \Sentry\configureScope(function ($scope) use ($context): void {
                        $scope->setTag('trace_id', $context['trace_id']);
                        $scope->setContext('trace', $context);

                        if ($template = config('observability.tracing.ui_url_template')) {
                            $scope->setTag('tempo_url', sprintf($template, $context['trace_id']));
                        }
                    });
                }

                Integration::captureUnhandledException($e);
            });
        }

        // Mark the active span as failed regardless of whether error reporting
        // is on. Does nothing when tracing is disabled.
        $this->reportable(function (Throwable $e): void {
            app(SpanRecorder::class)->recordExceptionOnCurrent($e);
        });

        $this->renderable(function (Throwable $e, Request $request): ?JsonResponse {
            if ($request->is('api/*')) {
                if ($e instanceof NotFoundHttpException) {
                    $errorCode = 404;
                } else {
                    $errorCode = 400;
                }

                $body = [
                    'error' => App::environment('local') ? get_class($e).': '.$e->getMessage() : 'An unclassified error occurred.',
                ];

                // Trace ids carry no data, and they turn an opaque error into
                // something support can actually look up.
                if ($traceId = app(Tracing::class)->currentTraceId()) {
                    $body['trace_id'] = $traceId;
                }

                return response()->json($body, $errorCode);
            }

            return null;
        });
    }
}
