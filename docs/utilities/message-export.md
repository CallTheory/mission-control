# Message Export

The Message Export utility delivers message data for a client account to email recipients,
either on a schedule or on demand. Where [CSV Export](csv-export.md) exports call log data
with the analytics filters, Message Export exports the **message fields** captured by your
scripts for a single account.

## Access

Message Export requires the `utility.message_export` capability, and must be enabled both
as a system utility and for the team. See [Permissions](../system/permissions.md).

## Creating an Export

Each export is configured with:

- **Name** — a descriptive name for the export
- **Account** — the client account to export messages for
- **Fields to Export** — the message fields to include. The available fields are read from
  the account itself, so pick the account first; changing the account resets the field
  selection.
- **Filter Field** / **Filter Value** — optional. Restrict the export to messages where
  the named field matches the given value.
- **Include call information** — adds call metadata alongside the message fields
- **Recipients** — one email address per line
- **Subject** — the email subject line
- **Schedule Type** — see below
- **Timezone** — the timezone the schedule runs in

## Schedule Types

| Type | Behaviour |
|------|-----------|
| **Manual (On-Demand Only)** | Never runs on its own; use **Run Now** |
| **Hourly** | Runs every hour |
| **Daily** | Runs once per day at the configured time |
| **Weekly** | Runs once per week on the configured day and time |
| **Monthly** | Runs once per month on the configured day of month and time |

The system calculates the next run time automatically and records the last execution
timestamp.

## Running on Demand

Any export can be run immediately with the **Run Now** action, which asks for a start and
end date rather than using the schedule's own window. This works for scheduled exports
too, so you can backfill a period without disturbing the schedule.

## History

The **History** page lists past export runs with their status and lets you download the
generated file. Export log entries are kept for 90 days by default
(`MESSAGE_EXPORT_LOG_DAYS_TO_KEEP`) and are purged nightly.

## Managing Exports

Exports can be edited, enabled or disabled without deleting them, and deleted outright.
Disabling an export leaves its configuration and history intact but stops it running.

## Setup

Enable Message Export in [System Settings](../system/index.md) and ensure the Intelligent
Series database connection is configured in [Datasources](../system/datasources.md).
