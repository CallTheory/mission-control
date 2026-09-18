# Azure Token Expiry

Every client secret and certificate on every app registration in a Microsoft Entra ID
(Azure AD) tenant, sorted by how soon it expires, with email alerts before anything
breaks.

A daily sweep reads Microsoft Graph and stores a snapshot; the dashboard reads the
snapshot. **Nothing is ever written to Azure** — the app registration behind it holds one
read permission and Mission Control issues only GETs.

Configured at **System → Azure Tokens** (`/system/azure-tokens`), which requires the
`system.azure_tokens` capability. Credentials live on the **Microsoft Entra ID** tile
under [System → Integrations](integrations.md).

> Scope: app registration and service principal credentials. Not AWS, not Vultr, not user
> OAuth tokens, and it never rotates or renews anything — it tells you what is about to
> expire so a person can.

## Azure setup

The dashboard authenticates as its own app registration using the client credentials
flow. Once, in the tenant you want watched:

1. **Create an app registration** in Entra ID, e.g. `mission-control-token-watcher`.
2. **Grant the Microsoft Graph _application_ permission `Application.Read.All`** — the
   application variety, not delegated — and grant admin consent. Nothing else.
3. **Create a client secret** with the longest expiry Azure allows (24 months). The
   watcher monitors its own secret too; see [The watcher's own secret](#the-watchers-own-secret).
4. Note the **tenant (directory) ID**, **application (client) ID** and the **secret
   value**, which Azure shows only once.

Then in Mission Control, on **System → Integrations**, open the **Microsoft Entra ID**
tile, enter the three values, switch on **Run the daily sweep**, and save. The tile's
**test** link authenticates and reports how many app registrations the tenant has, which
is the quickest way to tell a wrong secret from missing admin consent.

The secret is encrypted at rest and is never rendered back into the page. Leave the field
blank when editing anything else and the stored value is kept.

### Least privilege

`Application.Read.All` exposes app registration metadata for the whole tenant, so grant
`system.azure_tokens` deliberately — see [Permissions](permissions.md). What is stored
here is expiry dates and names: Graph never returns secret or private key **values**, so
the database holds no credential material by design.

## What the sweep reads

| Endpoint | Why |
|----------|-----|
| `GET /v1.0/applications` | App registrations and their `passwordCredentials` (secrets) and `keyCredentials` (certificates) |
| `GET /v1.0/servicePrincipals` | Service principals carry credentials of their own — SAML signing certificates most often — that never appear under `/applications` |

Both are paged and followed to the end via `@odata.nextLink`; a tenant with more than a
hundred app registrations returns several pages, and stopping at the first would
under-report expiries silently.

Each credential becomes one row keyed by its Graph `keyId` **and** the object it sits on:
the same certificate commonly appears on both an application and its service principal
under one `keyId`, and both are real.

## The dashboard

Four counters — credentials tracked, expiring within 30 days, already expired,
acknowledged — above a table sorted by soonest expiry.

| Status | Meaning |
|--------|---------|
| **Expired** | Past its expiry date |
| **Critical** | 14 days or fewer remaining |
| **Warning** | 30 days or fewer remaining |
| **Healthy** | More than 30 days remaining |

Filters cover status, credential type, object type, acknowledgement and presence, and the
search box matches app name, client ID, credential name, hint and `keyId`. Each row links
to the app's **Credentials** blade in the Entra portal, and the client ID column (hidden
by default) is copyable.

### Row actions

- **Acknowledge** — mutes alerts for that credential. It stays in the table with its real
  status; this is for the expired leftovers nobody is going to delete this quarter.
- **Un-acknowledge** — re-enables alerts and clears the alert history, so a credential
  already inside a warning window alerts again rather than staying silent.

### Expired credentials are shown, not hidden

Azure does not delete expired secrets; they linger on the app registration indefinitely.
They appear as **Expired**, both because something may still be trying to authenticate
with them and because they are the cleanup list.

### Removed credentials

A credential a completed sweep no longer sees is marked removed rather than deleted. Those
rows are hidden by default (the **Presence** filter shows them), and they are never
alerted on — a deleted secret is not an expiring secret.

## Alerting

One email each time a credential crosses **30**, **14** or **3** days remaining, and once
more when it expires. Never repeated for the same threshold, and never sent for an
acknowledged credential. Everything crossing on the same sweep arrives as one digest.

| Setting | Purpose |
|---------|---------|
| **Send expiry alerts** | The master toggle |
| **Recipients** | Addresses, comma or newline separated |

The thresholds are not editable: they are what the sweep records per credential to keep
alerts idempotent, and a threshold that can be changed afterwards makes "already alerted
at 14 days" meaningless.

Turning alerting on **after** sweeps have been running still reports what is already
overdue — no threshold is consumed while alerting is off.

## Scheduling

`azure:sweep-credentials` runs daily at 06:15 from the scheduler.

```bash
# Sweep now, alert, and print the ten soonest expiries
php artisan azure:sweep-credentials

# Sweep without sending anything
php artisan azure:sweep-credentials --no-alerts

# Sweep even though 'Run the daily sweep' is off
php artisan azure:sweep-credentials --force
```

**Sweep now** on the dashboard queues the same work as a job. The job is unique for an
hour, so pressing it while the scheduled sweep is running is a no-op rather than two
passes over the same rows.

## Failure modes worth knowing

### Stale data

Every number on the page is only as true as the last completed sweep, so the page shows a
prominent warning when none has completed in 25 hours. A collector that has quietly
stopped must not read as a tenant with nothing expiring. A failed sweep also shows the
reason it failed, in place.

### The watcher's own secret

The watcher's app registration appears in its own table — useful, but if that secret
lapses the sweeps fail and the tenant looks unchanged. The stale-data warning is the
backstop, and the Entra tile's **test** link names this failure explicitly when it is the
cause. A certificate instead of a secret gets longer lifetimes if your tenant allows it.

### An empty read is treated as a failure

If Graph returns no app registrations at all, the sweep is recorded as failed and nothing
is marked removed. A tenant always contains at least the watcher's own registration, so an
empty result is a broken read — and acting on it would mark every credential removed at
once.

### Times are UTC

Everything Graph returns is UTC and days-remaining is computed in UTC. The table renders
dates as stored; the arithmetic never goes through a local timezone.

## Data model

| Table | Holds |
|-------|-------|
| `azure_credentials` | One row per credential: app, type, name/hint, start and end dates, last seen, removal, acknowledgement, last alerted threshold |
| `azure_credential_sweeps` | One row per sweep attempt: timings, counts, alerts sent, and the error if it failed |

Days-remaining and status are computed at read time, never stored: a stored "days left" is
wrong the moment the sweep finishes.
