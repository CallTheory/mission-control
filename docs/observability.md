# Observability

Optional, opt-in integrations for seeing what the application is doing in
production. Both are **off by default** — a fresh install boots no SDK, opens no
connection, and sends nothing.

| Feature | Status | Destination |
| --- | --- | --- |
| Exception reporting | Implemented | GlitchTip (or Sentry — same wire protocol) |
| Distributed tracing | Implemented | Grafana Tempo via OTLP |

Configured at **System → Observability**, gated by the `system.observability`
capability.

## How configuration resolves

Three layers, later ones winning:

1. **Defaults** — everything off (`config/observability.php`).
2. **Database** — the `settings` singleton row, written by the admin page. The
   DSN is encrypted at rest via the `EncryptedSerialized` cast and listed in the
   model's `$hidden`.
3. **Environment** — when a variable is explicitly set it **overrides** the
   database.

Env wins last on purpose: an operator always has a kill switch that works
without database access, and CI/staging can pin a setting. When an env override
is active the admin page shows a banner, so the toggle never appears to silently
do nothing.

Resolution lives in `App\Services\Observability\ObservabilityConfig`. It runs at
boot on every request and every artisan command — including `migrate` on an
empty database — so it catches `Throwable` and falls back to defaults rather
than throwing. It memoizes in a static, **not** the cache store: the DSN is a
secret and caching it in Redis would write plaintext into a second system, and
`Cache::remember()` on the redis driver can itself throw during boot, which is
the failure this guards against.

## Why the SDK is not auto-discovered

`sentry/sentry-laravel` is listed under `extra.laravel.dont-discover` in
`composer.json`. **Do not "fix" this by re-enabling discovery.**

Sentry's own `ServiceProvider::register()` unconditionally binds `ClientBuilder`
and `HubInterface` even with a null DSN, and Laravel registers discovered
package providers *before* application providers (`Application::registerConfiguredProviders()`
splices the package manifest in at index 1). So there is no way to conditionally
veto it from our own provider — suppressing discovery is the only mechanism that
makes "disabled" mean the SDK is never loaded at all.

`App\Providers\ObservabilityServiceProvider` registers
`Sentry\Laravel\ServiceProvider` at runtime, only when reporting is enabled
**and** a DSN is present.

Consequences:

- There is no `Sentry` facade alias. Use `\Sentry\captureException()` or
  `app(\Sentry\State\HubInterface::class)`.
- `artisan sentry:test` and `sentry:publish` do not exist. The admin page's
  "Send test event" button replaces the former.
- `config/sentry.php` is deliberately **not** published; options are injected at
  runtime so there is one source of truth, and so the `before_send` callable
  never lands in a file `config:cache` would try to serialize.

Verify it is really off:

```bash
php artisan tinker --execute="var_dump(app()->bound(\Sentry\State\HubInterface::class));"
# must print false on an unconfigured install
```

## Why Sentry performance monitoring is disabled

`traces_sample_rate` is `null` and `Sentry\Laravel\Tracing\ServiceProvider` is
never registered. Tracing is owned by the OpenTelemetry → Tempo integration;
running both would double-instrument the application into two disconnected trace
trees. `enable_metrics` is also forced off — the SDK defaults it to **true**, and
GlitchTip drops metrics, so leaving it on only produces wasted outbound requests.

## What is and is not sent

Scrubbing happens in `App\Services\Observability\ScrubSentryEvent`, wired as
`before_send`. If the scrubber itself fails, the event is **dropped** rather than
sent unscrubbed.

**Never sent:**

- Request bodies. `max_request_body_size` is `none` — note the SDK default of
  `medium` sends up to 10KB *even when* `send_default_pii` is false, which only
  gates cookies, IP and auth headers.
- Cookies, `Authorization` and other credential headers.
- Livewire update payloads. These are dropped **wholesale**, not key-filtered,
  because `ManagesDataSourceSettings` hydrates *decrypted* credentials into
  public component properties, and those live inside a JSON-encoded
  `components[].snapshot` string that key-based scrubbing cannot see into.
- User email, username or IP. The user is reduced to a numeric id.
- SQL bindings and cache keys (breadcrumbs off) — they carry caller phone
  numbers, DOBs and patient names.
- Outbound HTTP breadcrumbs — RingCentral/Twilio URLs carry tokens in the query
  string.

**Redacted from free text** (exception messages, breadcrumbs, extra context):
phone numbers, email addresses, PEM private-key/certificate blocks, and the
Amtelco message labels (`Ptn:`, `DOB:`, `Caller ID:`, `Phone:`, `Address:`, …)
documented in `App\Models\Stats\Helpers::$knownMessageLabels`.

Keep `zend.exception_ignore_args` at its PHP default of `On`. Sentry's docs
suggest turning it off for richer stack traces; doing so serializes every local
variable at every frame, including decrypted credentials. The scrubber walks
frame variables defensively regardless.

## Filtering noisy exceptions

Use `observability.errors.ignore_exceptions`, **not** `Handler::$dontReport` —
the latter suppresses the local log too. Laravel's own `internalDontReport`
already covers Authentication, Authorization, Http (404/403/419),
ModelNotFound, TokenMismatch and Validation, so those never reach GlitchTip.

The list starts empty on purpose. Populate it after real data shows what is
noisy; likely candidates here are `Illuminate\Http\Client\ConnectionException`
(Twilio/RingCentral/SendGrid outages), `ProcessTimedOutException`
(browsershot/sox/whisper) and `PDOException` from the Amtelco client database.

## Operational notes

- **Web requests** pick up a settings change immediately — there is no Octane and
  config is not cached in production.
- **Queue workers are long-lived** and read configuration once at boot. After
  changing settings, run `php artisan horizon:terminate` so background jobs pick
  up the change. Until then workers keep the previous setting, including staying
  *enabled* after you disable it.
- **A green "Send test event" proves connectivity and credentials, not feature
  support.** GlitchTip returns 200 for payloads it then silently drops.
- **An admin-supplied DSN means the server makes an outbound request to a host
  of the admin's choosing.** That is inherent to "point it at your own
  GlitchTip". It is mitigated by the `system.observability` capability, which
  should be granted narrowly. Grant it deliberately.
- **No Docker/Sail changes are required.** The app container already declares
  `extra_hosts: host.docker.internal:host-gateway`, so a GlitchTip on the host is
  reachable at `http://host.docker.internal:<port>/<projectId>`.


---

# Tracing

Manual OpenTelemetry instrumentation exported over OTLP/HTTP. The
`ext-opentelemetry` PECL extension is **not** required and is deliberately not
used — the `opentelemetry-auto-*` packages depend on it and silently no-op
without it, so everything here is explicit instrumentation that works on a
stock PHP install.

## Topology, and why the endpoint defaults to localhost

The app should export to a collector running **on the same host** — normally a
Grafana Alloy agent with an `otelcol.receiver.otlp` block, which forwards to
Tempo. A loopback POST is sub-millisecond; exporting straight to a remote Tempo
or Grafana Cloud puts a real network round-trip into the export path.

**Nothing here assumes that agent exists.** Alloy is your infrastructure, not
something this application installs. The admin page has a **Check collector**
button that probes the endpoint and reports one of:

- *reachable* — something answered
- *no collector is listening* — with the setup step you still need to do
- *rejected the credentials* — reachable, but the auth username/token is wrong

Enabling tracing also runs that check automatically, so a misconfiguration
surfaces immediately instead of spans silently going nowhere.

### Alloy configuration

```river
otelcol.receiver.otlp "mission_control" {
  http { endpoint = "0.0.0.0:4318" }   // matches the app default
  // grpc {} intentionally omitted — the app speaks OTLP/HTTP only
  output { traces = [otelcol.processor.batch.default.input] }
}

otelcol.processor.batch "default" {
  send_batch_size = 512
  timeout         = "2s"
  output { traces = [otelcol.exporter.otlp.tempo.input] }
}

otelcol.exporter.otlp "tempo" {
  client {
    endpoint = "tempo.internal:4317"
    // Grafana Cloud instead:
    // endpoint = "tempo-prod-04-prod-us-east-0.grafana.net:443"
    // auth     = otelcol.auth.basic.grafana_cloud.handler
  }
}
```

## Sampling, and "always keep errors"

The sampler is `ParentBased(TraceIdRatioBased)`. The decision is made **once**
at the root span and rides the `traceparent` into queued jobs, so you get whole
traces or nothing — never a job span orphaned from the request that dispatched
it. Use one sample rate everywhere; a different rate in the worker would break
that.

Head sampling cannot "always keep errors" — the decision precedes the outcome.
The correct answer is **tail sampling in Alloy**: set the app's sample rate to
`1.0`, export everything over loopback (cheap), and let Alloy decide:

```river
otelcol.processor.tail_sampling "policy" {
  decision_wait = "10s"
  policy { name = "errors"      type = "status_code"   status_code { status_codes = ["ERROR"] } }
  policy { name = "slow"        type = "latency"       latency { threshold_ms = 1000 } }
  policy { name = "sample-rest" type = "probabilistic" probabilistic { sampling_percentage = 5 } }
  output { traces = [otelcol.processor.batch.default.input] }
}
```

Caveat: tail sampling buffers by trace id for `decision_wait`, so a queued job
that runs minutes after its parent request arrives after the window and is
evaluated as its own trace.

## What is instrumented

| Signal | Notes |
| --- | --- |
| HTTP requests | Root span per request, named by **route template** (`GET /system/users/{user}`), never the raw URL. Livewire updates are named by component (`LIVEWIRE system.role-manager`) instead of collapsing into one bucket. |
| Queue jobs | Trace context is injected into the payload (~60 bytes), so a job is a child of whatever dispatched it. None of the job classes needed changes. |
| Artisan commands | Plus scheduled tasks. |
| Database queries | **Off by default**, behind its own toggle with an optional slow-query threshold. |
| Outbound HTTP | Laravel's `Http` facade, plus the raw Guzzle sites that opt in via `GuzzleTracing::handlerStack()`. |

### Un-traced gaps, stated plainly

The **Twilio, RingCentral and Stripe SDKs** use their own internal Guzzle
clients and are **not** instrumented. Calls through them appear as unexplained
gaps inside their parent span — if you see four seconds unaccounted for inside a
`SendFaxJob` span, that is an un-traced RingCentral call, not a mystery.

`$schedule->command()` forks a separate process, so the child command is a
separate trace root rather than a child of the `schedule` span. The command span
does read a `TRACEPARENT` environment variable if one is present, so a cron
wrapper can join them.

## Volume and safety

- `max_spans_per_trace` (500) caps child spans; on overflow the root span gets
  `laravel.spans_dropped` so truncation is visible rather than silent.
- **DB spans are the highest-volume signal by far** — one N+1 page can emit
  hundreds. Keep the sample rate low if you turn them on.
- Long-running commands (`horizon`, `queue:work`, `schedule:work`) are on a hard
  ignore list. Without it their span would never end, never export, and would
  become the parent of every job the worker processes — one multi-day trace.
- The Horizon dashboard, Telescope and asset paths are in `ignore_paths`; the
  dashboard polls several times a second per open tab.

## Failure behavior

A broken, slow or absent collector must never break a request or fail a job.

- Connect timeout 0.5s, request timeout 2s.
- **Retries are set to 0.** The SDK defaults to 3 with backoff, which against a
  dead collector turns a 2s timeout into ~6s of added latency for no benefit,
  since the collector is meant to be on localhost.
- After 3 consecutive failed exports, tracing switches itself off for the rest
  of the process and logs one warning. Note `forceFlush()` returns `true` even
  when the transport failed, so failures are observed through
  `ObservedSpanExporter` rather than the flush result.
- Every method on the `Tracing` facade swallows its own errors. This matters
  most in the queue listeners: a throwing `JobProcessed` handler would propagate
  into the worker loop.

Production runs PHP-FPM, so `fastcgi_finish_request()` exists and the export in
`terminating()` happens **after** the response has been sent — it costs the user
nothing. That function is checked at runtime rather than assumed, so under
`artisan serve`, IIS FastCGI or the CLI the export is synchronous and the tight
timeouts above are what bound the cost.

## Correlation

- Log lines carry `trace_id` and `span_id` via a Monolog tap
  (`App\Logging\AddTraceContext`), so an id from a log can be pasted into Tempo.
- GlitchTip events carry a `trace_id` tag, plus a `tempo_url` tag when
  `OBSERVABILITY_TRACE_UI_URL` is set — one click from issue to trace.
- API error responses include `trace_id`, finally giving the opaque
  `"An unclassified error occurred."` message a handle support can look up.
