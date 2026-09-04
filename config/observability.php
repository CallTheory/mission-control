<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Observability
    |--------------------------------------------------------------------------
    |
    | Two independent, OPT-IN integrations, both OFF by default:
    |
    |   errors  — exception reporting to a GlitchTip (or Sentry) instance
    |   tracing — OpenTelemetry spans exported over OTLP to Grafana Tempo
    |
    | Values are normally set by an administrator in System -> Observability and
    | stored in the `settings` table. The env vars below are FALLBACKS AND
    | OVERRIDES: when an env var is explicitly set it WINS over the database
    | value, so an operator always has a kill switch that works without database
    | access. `null` means "not specified here — defer to the database".
    |
    | Resolution happens in App\Services\Observability\ObservabilityConfig.
    |
    */

    'environment' => env('OBSERVABILITY_ENVIRONMENT'),   // null => config('app.env')
    'release' => env('OBSERVABILITY_RELEASE'),

    'errors' => [

        // null => defer to the database. true/false => hard env override.
        'enabled' => env('OBSERVABILITY_ERRORS_ENABLED'),

        'dsn' => env('OBSERVABILITY_ERRORS_DSN'),

        'sample_rate' => env('OBSERVABILITY_ERRORS_SAMPLE_RATE'),

        /*
         | Deliberately env-only and never exposed in the admin UI. Enabling it
         | attaches the client IP, cookies and unfiltered auth headers to every
         | event. This application handles call-center data, so it stays off.
         */
        'send_default_pii' => (bool) env('OBSERVABILITY_SEND_DEFAULT_PII', false),

        /*
         | Sentry merges this array shallowly, so every key must be listed or
         | the omitted ones silently revert to the SDK defaults.
         */
        'breadcrumbs' => [
            'logs' => (bool) env('OBSERVABILITY_BREADCRUMBS_LOGS', true),

            // Raw SQL can inline literals, and bindings carry caller phone
            // numbers, DOBs and patient names. Both stay off.
            'sql_queries' => (bool) env('OBSERVABILITY_BREADCRUMBS_SQL', false),
            'sql_bindings' => false,

            // Cache keys embed account and call identifiers.
            'cache' => false,

            // RingCentral/Twilio URLs carry tokens in the query string.
            'http_client_requests' => false,

            'livewire' => (bool) env('OBSERVABILITY_BREADCRUMBS_LIVEWIRE', true),
            'queue_info' => true,
            'command_info' => true,
            'notifications' => true,
        ],

        /*
         | Exception classes to drop before sending. Laravel's own
         | internalDontReport already covers Authentication, Authorization,
         | Http (404/403/419), ModelNotFound, TokenMismatch and Validation, so
         | start empty and add only what proves noisy in practice.
         */
        'ignore_exceptions' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracing (OpenTelemetry -> Grafana Tempo)
    |--------------------------------------------------------------------------
    |
    | Spans are exported over OTLP/HTTP. The intended topology is a Grafana
    | Alloy (or OpenTelemetry Collector) agent listening on localhost, which
    | forwards to Tempo — a loopback POST is sub-millisecond, whereas exporting
    | straight to a remote Tempo puts a real network round-trip in the export
    | path. Nothing here assumes that agent exists: the admin page probes the
    | endpoint and tells the operator when nothing is listening.
    |
    */
    'tracing' => [

        'enabled' => env('OBSERVABILITY_TRACING_ENABLED'),

        'exporter' => [
            'endpoint' => env('OBSERVABILITY_TRACING_ENDPOINT', 'http://localhost:4318'),
            'protocol' => env('OBSERVABILITY_TRACING_PROTOCOL', 'http/protobuf'),
            'compression' => env('OBSERVABILITY_TRACING_COMPRESSION', 'none'),
            'timeout' => (float) env('OBSERVABILITY_TRACING_TIMEOUT', 2.0),
            'connect_timeout' => (float) env('OBSERVABILITY_TRACING_CONNECT_TIMEOUT', 0.5),

            /*
             | The SDK defaults to 3 retries with backoff, which against a dead
             | collector turns a 2s timeout into ~6s of added latency. The
             | collector is meant to be on localhost, so retrying buys nothing.
             */
            'max_retries' => (int) env('OBSERVABILITY_TRACING_MAX_RETRIES', 0),

            // Grafana Cloud uses basic auth: username = instance ID.
            'auth_username' => env('OBSERVABILITY_TRACING_AUTH_USERNAME'),
            'auth_token' => env('OBSERVABILITY_TRACING_AUTH_TOKEN'),
        ],

        'resource' => [
            'service_name' => env('OBSERVABILITY_TRACING_SERVICE_NAME'),
            'service_namespace' => env('OBSERVABILITY_TRACING_SERVICE_NAMESPACE', 'mission-control'),
        ],

        'sample_rate' => env('OBSERVABILITY_TRACING_SAMPLE_RATE'),

        /*
         | One runaway N+1 page can otherwise blow past the batch queue and
         | silently drop spans with no signal that it happened.
         */
        'max_spans_per_trace' => 500,

        'batch' => [
            'max_queue_size' => 512,
            'schedule_delay_millis' => 1000,
            'export_timeout_millis' => 2000,
            'max_export_batch_size' => 128,
        ],

        'instrumentation' => [
            'http' => [
                // Query strings carry tokens and client identifiers.
                'capture_query' => false,
                // Numeric ids only, never emails.
                'capture_user' => true,
            ],
            'queue' => ['enabled' => true],
            'console' => [
                'enabled' => true,
                /*
                 | CommandStarting fires for long-running workers too. Without
                 | this list `artisan horizon` opens a span that never ends,
                 | never exports, and becomes the parent of every job the worker
                 | processes — one absurd multi-day trace.
                 */
                'ignore' => [
                    'horizon', 'horizon:*', 'queue:work', 'queue:listen',
                    'queue:restart', 'schedule:work', 'serve', 'tinker',
                    'websockets:serve', 'octane:*',
                ],
            ],
            'db' => [
                'enabled' => false,
                'slow_query_ms' => 0,
                'max_statement_length' => 2048,
            ],
            'client' => ['enabled' => true],
        ],

        /*
         | The Horizon dashboard polls several times a second per open tab and
         | would otherwise dominate span volume.
         */
        'ignore_paths' => [
            'queue', 'queue/*', 'telescope', 'telescope/*', 'up',
            'livewire/livewire.js*', 'livewire/preview-file/*', 'build/*',
        ],

        // Deep-link template for correlation; %s is replaced with the trace id.
        'ui_url_template' => env('OBSERVABILITY_TRACE_UI_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scrubbing
    |--------------------------------------------------------------------------
    |
    | Applied by App\Services\Observability\ScrubSentryEvent before any event
    | leaves the application.
    |
    */

    'scrubbing' => [

        'keys' => [
            'password', 'passwd', 'secret', 'token', 'api_key', 'apikey',
            'authorization', 'credential', 'credentials', 'private_key',
            'certificate', 'cert', 'dsn', 'jwt', 'client_secret',
            'account_sid', 'auth_token', 'signature', '_token', 'xsrf', 'session',
        ],

        'headers' => [
            'authorization', 'cookie', 'set-cookie', 'x-csrf-token',
            'x-xsrf-token', 'x-twilio-signature', 'proxy-authorization',
        ],

        /*
         | Message labels parsed out of Amtelco messages — see
         | App\Models\Stats\Helpers::$knownMessageLabels. These routinely appear
         | in exception messages and log breadcrumbs.
         */
        'message_labels' => [
            'Ptn', 'DOB', 'Caller ID', 'Clr ID', 'ACD ANI', 'Phone',
            'Name', 'First Name', 'Last Name', 'Address', 'eMail', 'Email',
        ],

        'redact_phone_numbers' => true,
        'redact_email_addresses' => true,

        // Livewire snapshots carry decrypted credentials in public component
        // properties, so the whole payload is dropped rather than key-filtered.
        'strip_livewire_payloads' => true,

        // Bound the recursive walk so a pathological payload cannot burn CPU
        // inside the error path.
        'max_depth' => 8,
        'max_nodes' => 2000,
        'max_string_length' => 2048,
    ],

];
