<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Observability\ObservabilityConfig;
use App\Services\Observability\ScrubSentryEvent;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the GlitchTip/Sentry SDK — and only when an operator has explicitly
 * turned it on and supplied a DSN.
 *
 * `sentry/sentry-laravel` is listed under extra.laravel.dont-discover in
 * composer.json, because its own ServiceProvider::register() unconditionally
 * binds ClientBuilder and HubInterface even with a null DSN, and discovered
 * package providers register BEFORE application providers (verified in
 * Application::registerConfiguredProviders(), which splices the package
 * manifest in at index 1). Suppressing discovery is therefore the only way to
 * make "disabled" mean the SDK is never loaded at all.
 *
 * Consequence: there is no `Sentry` facade alias. Use \Sentry\captureMessage()
 * or app(\Sentry\State\HubInterface::class).
 */
class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! ObservabilityConfig::errorsEnabled()) {
            return;
        }

        $errors = ObservabilityConfig::errors();

        if (blank($errors['dsn'])) {
            // Enabled but not configured: stay completely inert rather than
            // booting an SDK that has nowhere to send anything.
            return;
        }

        // Injected at runtime instead of publishing config/sentry.php: it keeps
        // one source of truth, and keeps the before_send callable out of a file
        // that config:cache would try to serialize.
        config()->set('sentry', $this->sentryOptions($errors));

        $this->app->register(\Sentry\Laravel\ServiceProvider::class);

        // Sentry\Laravel\Tracing\ServiceProvider is deliberately NOT registered:
        // tracing goes to Grafana Tempo via OpenTelemetry. Running both would
        // double-instrument into two disconnected trace trees.
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array<string, mixed>
     */
    private function sentryOptions(array $errors): array
    {
        return [
            'dsn' => $errors['dsn'],
            'environment' => $errors['environment'],
            'release' => $errors['release'],
            'sample_rate' => (float) $errors['sample_rate'],

            // Performance monitoring belongs to the Tempo integration. null
            // (not 0) is unambiguous: Options::isTracingEnabled() stays false.
            'traces_sample_rate' => null,

            // GlitchTip supports none of these; leaving them on just produces
            // outbound requests to endpoints that drop the payload.
            'profiles_sample_rate' => null,
            'enable_logs' => false,
            'enable_metrics' => false,   // SDK default is TRUE — must override

            'send_default_pii' => $errors['send_default_pii'],

            // The SDK default of 'medium' sends up to 10KB of request body even
            // when send_default_pii is false, which only gates cookies, IP and
            // auth headers. This app carries PHI-adjacent data, so: none.
            'max_request_body_size' => 'none',

            'max_value_length' => 2048,
            'attach_stacktrace' => true,
            'context_lines' => 5,
            'in_app_exclude' => [base_path('vendor')],

            'http_connect_timeout' => 2,
            'http_timeout' => 5,

            'ignore_exceptions' => $errors['ignore_exceptions'],

            'before_send' => [ScrubSentryEvent::class, 'handle'],
            'before_send_transaction' => [ScrubSentryEvent::class, 'dropTransaction'],

            // Laravel-specific keys consumed by the package's provider.
            // NOTE: mergeConfigFrom is a SHALLOW merge, so every breadcrumb key
            // must be present or the omitted ones revert to package defaults.
            'breadcrumbs' => $errors['breadcrumbs'],
            'tracing' => [
                'queue_job_transactions' => false,
                'queue_jobs' => false,
                'sql_queries' => false,
                'views' => false,
                'livewire' => false,
                'http_client_requests' => false,
                'redis_commands' => false,
                'missing_routes' => false,
                'continue_after_response' => false,
                'default_integrations' => false,
            ],
            'controllers_base_namespace' => 'App\\Http\\Controllers',
        ];
    }
}
