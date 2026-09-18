# Observability

Optional integrations for seeing what the application is doing in production. Both are
**off by default** — a fresh install loads no SDK, opens no connection, and sends nothing.

| Feature | Destination |
|---------|-------------|
| Error reporting | GlitchTip, or Sentry — they share a wire protocol |
| Distributed tracing | Grafana Tempo, via OTLP |

Configured at **System → Observability** (`/system/observability`), which requires the
`system.observability` capability.

> Both features send data off your server to a destination you nominate. Grant
> `system.observability` deliberately and narrowly — see [Permissions](permissions.md).

## Error Reporting

Sends exception reports to GlitchTip or Sentry.

| Setting | Purpose |
|---------|---------|
| **Enable exception reporting** | The master toggle |
| **DSN** | The project DSN from GlitchTip/Sentry. Write-only — it is never displayed back |
| **Environment** | Labels events, e.g. `production` or `staging` |
| **Release** | Optional release identifier for grouping |
| **Sample rate** | Fraction of events to send, from `0.0` to `1.0` |

A **Send test event** button confirms the connection end to end, and the page records the
result of the last test.

> A green test event proves connectivity and credentials — not that your destination
> supports every payload. GlitchTip returns a success response for some payloads it then
> silently drops.

### What is and is not sent

Mission Control handles call center data: caller names, phone numbers, dates of birth and
patient identifiers. Every event is scrubbed before it leaves the server, and if the
scrubber itself fails the event is **dropped** rather than sent unscrubbed.

**Never sent:**

- Request bodies
- Cookies, `Authorization` headers and other credentials
- Livewire payloads — dropped wholesale rather than filtered field by field, because the
  settings screens hold decrypted credentials in a form that key-based scrubbing cannot
  see into
- User email, username or IP address — the user is reduced to a numeric id
- SQL bindings and cache keys, which carry caller phone numbers, dates of birth and
  patient names
- Outbound API request details, whose URLs can carry tokens

**Redacted from free text** (exception messages and context): phone numbers, email
addresses, private key and certificate blocks, and the Amtelco message field labels
(`Ptn:`, `DOB:`, `Caller ID:`, `Phone:`, `Address:` and similar).

### Noisy exceptions

Laravel's own routine exceptions — authentication, authorization, 404/403/419, model not
found, validation — never reach your error tracker. If a specific exception class proves
noisy in practice, it can be added to the ignore list; likely candidates are connection
timeouts to Twilio, RingCentral or SendGrid, media processing timeouts, and database
errors from the optional client database.

Performance monitoring is deliberately disabled in the error reporting SDK — tracing is
owned by the OpenTelemetry integration below, and running both would split the same
request across two disconnected trace trees.

## Tracing

Exports distributed traces over OTLP to a collector, which forwards them to Grafana Tempo.

| Setting | Purpose |
|---------|---------|
| **Enable tracing** | The master toggle |
| **OTLP endpoint** | Where spans are sent. Defaults to a collector on localhost |
| **Protocol** | `http/protobuf` or the alternatives your collector accepts |
| **Auth username** / **Auth token** | Credentials for the collector, if it requires them |
| **Service name** | How this installation appears in Tempo |
| **Sample rate** | Fraction of traces to keep |
| **Database spans** | Whether to emit a span per query |
| **Slow query threshold (ms)** | With database spans on, only record queries slower than this. `0` records all of them |

Turn database spans on only while investigating something specific — one page with an
inefficient query pattern can emit hundreds of spans, making this by far the
highest-volume signal.

### Topology

Export to a collector running **on the same host** — normally a Grafana Alloy agent that
forwards to Tempo. A loopback request is sub-millisecond, whereas exporting straight to a
remote Tempo or Grafana Cloud puts a network round-trip into the export path.

The collector is your infrastructure; Mission Control does not install it. The settings
page has a **Check collector** button that probes the endpoint and reports whether
something answered, nothing is listening, or the credentials were rejected. Enabling
tracing runs that check automatically, so a misconfiguration surfaces immediately rather
than spans silently going nowhere.

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

### Sampling, and keeping every error

The sampling decision is made once at the start of a request and carried into any queued
jobs it dispatches, so you get whole traces or nothing — never a job span orphaned from
the request that created it. Use one sample rate everywhere.

That also means sampling cannot "always keep errors": the decision is made before the
outcome is known. To keep every error, set the sample rate to `1.0`, export everything
over loopback (which is cheap), and let the collector decide:

```river
otelcol.processor.tail_sampling "policy" {
  decision_wait = "10s"
  policy { name = "errors"      type = "status_code"   status_code { status_codes = ["ERROR"] } }
  policy { name = "slow"        type = "latency"       latency { threshold_ms = 1000 } }
  policy { name = "sample-rest" type = "probabilistic" probabilistic { sampling_percentage = 5 } }
  output { traces = [otelcol.processor.batch.default.input] }
}
```

One caveat: the collector buffers by trace for `decision_wait`, so a queued job that runs
minutes after the request that dispatched it arrives too late for that window and is
evaluated as its own trace.

### What is traced

| Signal | Notes |
|--------|-------|
| HTTP requests | One span per request, named by route rather than raw URL |
| Queue jobs | Children of whatever dispatched them |
| Artisan commands | Long-running workers excluded |
| Scheduled tasks | With the cron expression and runtime |
| Database queries | Off by default, behind its own toggle |
| Outbound HTTP | Most outbound calls |

**Known gaps.** The Twilio, RingCentral and Stripe SDKs use their own HTTP clients and are
not instrumented, so calls through them appear as unexplained gaps inside their parent
span. If you see several seconds unaccounted for inside a fax send, that is an untraced
RingCentral call rather than a mystery.

Scheduled commands run in a separate process, so a scheduled command is a separate trace
rather than a child of the schedule span.

## Applying a settings change

Web requests pick up a change immediately.

**Queue workers do not.** Workers are long-lived and read the configuration once when they
start, so after changing any observability setting run:

```bash
php artisan horizon:terminate
```

Until you do, background jobs keep using the previous setting — including staying
*enabled* after you have disabled it.

## Failure behaviour

Neither integration is allowed to take the application down.

- Connect and request timeouts are short, and failed exports are not retried.
- After several consecutive export failures, tracing switches itself off for the rest of
  that process and logs a single warning.
- If the collector is unreachable or the DSN is wrong, spans and events are dropped and
  the request completes normally.

In production the trace export happens after the response has already been sent, so it
costs the user nothing.

## Correlating a report with a trace

- Log lines carry the trace and span id, so an id from a log can be pasted into Tempo.
- Error reports carry a trace id tag, and a direct link to the trace when the trace UI URL
  is configured.
- API error responses include a trace id, which gives an otherwise opaque error message
  something support can look up.
- Every traced response carries an **`X-Trace-Id`** header, so "send me the trace id from
  your browser's developer tools" is a workable support request. Trace ids carry no data,
  so sharing one is safe.

## Environment Overrides

Settings saved on this page live in the database, but an environment variable of the same
name **overrides** the stored value. This is intentional: an operator always has a kill
switch that works without database access, and staging or CI can pin a setting.

When an override is active the page shows a banner, so a toggle never appears to silently
do nothing.
