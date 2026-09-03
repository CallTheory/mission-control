# Observability

Optional, opt-in integrations for seeing what the application is doing in
production. Both are **off by default** — a fresh install boots no SDK, opens no
connection, and sends nothing.

| Feature | Status | Destination |
| --- | --- | --- |
| Exception reporting | Implemented | GlitchTip (or Sentry — same wire protocol) |
| Distributed tracing | Planned | Grafana Tempo via OTLP |

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
