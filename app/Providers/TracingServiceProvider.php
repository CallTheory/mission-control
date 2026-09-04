<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\TraceRequests;
use App\Services\Observability\GuzzleTracing;
use App\Services\Observability\ObservabilityConfig;
use App\Services\Observability\SpanRecorder;
use App\Services\Observability\TracerFactory;
use App\Services\Observability\Tracing;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

/**
 * Boots OpenTelemetry tracing — and only when an operator has turned it on.
 *
 * When disabled this provider binds one small class whose methods are guarded
 * no-ops and attaches no listeners, so no SDK class is ever autoloaded.
 */
class TracingServiceProvider extends ServiceProvider
{
    /** Queue payload hooks are static on the framework; register once. */
    private static bool $payloadHookRegistered = false;

    private bool $flushed = false;

    public function register(): void
    {
        $this->app->singleton(Tracing::class, function () {
            try {
                if (! ObservabilityConfig::tracingEnabled()) {
                    return Tracing::disabled();
                }

                $config = ObservabilityConfig::tracing();

                return Tracing::enabled(
                    $this->app->make(TracerFactory::class)->make($config),
                    $config
                );
            } catch (Throwable) {
                // A misconfigured exporter must never prevent the app booting.
                return Tracing::disabled();
            }
        });

        /*
         * Kernel::terminateMiddleware() resolves middleware from the container
         * again, so a NEW instance would run terminate(). Binding this as a
         * singleton is what lets span state survive from handle() to
         * terminate(); without it spans would simply never end.
         */
        $this->app->singleton(TraceRequests::class);
    }

    public function boot(): void
    {
        if (! ObservabilityConfig::tracingEnabled()) {
            return;
        }

        $this->registerFlushHooks();
        $this->registerQueueHooks();
        $this->registerConsoleHooks();
        $this->registerDatabaseHooks();
        $this->registerHttpClientHooks();
    }

    private function tracing(): Tracing
    {
        return $this->app->make(Tracing::class);
    }

    private function registerFlushHooks(): void
    {
        /*
         * Production runs PHP-FPM, so fastcgi_finish_request() exists and the
         * response is already flushed to the user before terminating callbacks
         * run — the export costs the user nothing. The function is checked at
         * runtime rather than assumed, so this stays correct under `artisan
         * serve`, IIS FastCGI or the CLI, where the export is synchronous and
         * the tight export/connect timeouts are what bound the cost.
         */
        $this->app->terminating(function () {
            $this->flushOnce();
        });

        Event::listen(WorkerStopping::class, function () {
            $this->tracing()->shutdown();
        });

        // Backstop for fatals and exit() paths.
        register_shutdown_function(function () {
            $this->flushOnce();
        });
    }

    private function flushOnce(): void
    {
        if ($this->flushed) {
            return;
        }

        $this->flushed = true;
        $this->tracing()->flush();
    }

    private function registerQueueHooks(): void
    {
        if (! (bool) (ObservabilityConfig::tracing()['instrumentation']['queue']['enabled'] ?? true)) {
            return;
        }

        if (! self::$payloadHookRegistered) {
            self::$payloadHookRegistered = true;

            /*
             * Inject W3C trace context into every queued payload (~60 bytes) so
             * a job becomes a child of whatever dispatched it. Resolves Tracing
             * from the container rather than capturing it, so a rebuilt
             * container in tests does not leave a stale instance wired in.
             * None of the 20 existing job classes need to change.
             */
            Queue::createPayloadUsing(function () {
                return app(SpanRecorder::class)->queuePayloadCarrier();
            });
        }

        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            app(SpanRecorder::class)->startJobSpan($event);
        });

        $end = function ($event) {
            $exception = property_exists($event, 'exception') ? $event->exception : null;
            app(SpanRecorder::class)->endJobSpan($event, $exception);
        };

        // JobExceptionOccurred and JobFailed can BOTH fire for one job, so
        // endJobSpan() must be idempotent.
        Event::listen(JobProcessed::class, $end);
        Event::listen(JobExceptionOccurred::class, $end);
        Event::listen(JobFailed::class, $end);
    }

    private function registerConsoleHooks(): void
    {
        $console = ObservabilityConfig::tracing()['instrumentation']['console'] ?? [];

        if (! (bool) ($console['enabled'] ?? true)) {
            return;
        }

        $ignore = $console['ignore'] ?? [];

        Event::listen(CommandStarting::class, function (CommandStarting $event) use ($ignore) {
            /*
             * Without this guard `artisan horizon` and `queue:work` — which run
             * for days — would open a span that never ends, never exports, and
             * becomes the active parent of every job the worker processes.
             */
            if ($this->matchesAny((string) $event->command, $ignore)) {
                return;
            }

            app(SpanRecorder::class)->startCommandSpan($event);
        });

        Event::listen(CommandFinished::class, function (CommandFinished $event) use ($ignore) {
            if ($this->matchesAny((string) $event->command, $ignore)) {
                return;
            }

            app(SpanRecorder::class)->endCommandSpan($event);
        });
    }

    private function registerHttpClientHooks(): void
    {
        if (! (bool) (ObservabilityConfig::tracing()['instrumentation']['client']['enabled'] ?? true)) {
            return;
        }

        /*
         * Covers Laravel's Http facade. NOTE: that is used at exactly one call
         * site in this application; the seven `new GuzzleHttp\Client` sites
         * must opt in via GuzzleTracing::handlerStack(), and the Twilio,
         * RingCentral and Stripe SDKs remain un-traced.
         */
        Http::globalMiddleware(GuzzleTracing::middleware());
    }

    private function registerDatabaseHooks(): void
    {
        $db = ObservabilityConfig::tracing()['instrumentation']['db'] ?? [];

        if (! (bool) ($db['enabled'] ?? false)) {
            return;
        }

        DB::listen(function (QueryExecuted $query) {
            app(SpanRecorder::class)->recordQuery($query);
        });
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
