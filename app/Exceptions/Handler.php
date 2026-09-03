<?php

namespace App\Exceptions;

use App\Services\Observability\ObservabilityConfig;
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
                Integration::captureUnhandledException($e);
            });
        }

        $this->renderable(function (Throwable $e, Request $request): ?JsonResponse {
            if ($request->is('api/*')) {
                if ($e instanceof NotFoundHttpException) {
                    $errorCode = 404;
                } else {
                    $errorCode = 400;
                }

                return response()->json([
                    'error' => App::environment('local') ? get_class($e).': '.$e->getMessage() : 'An unclassified error occurred.',
                ], $errorCode);
            }

            return null;
        });
    }
}
