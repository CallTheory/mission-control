# CSV Export

The CSV Export utility allows you to export data from Mission Control to CSV files for use in external reporting tools, spreadsheets, and other applications.

## Access

CSV Export requires the `utility.csv_export` capability, and must be enabled both as a
system utility and for the team. See [Permissions](../system/permissions.md).

## Filters

CSV Export uses the standard call log filter set:

- Start and end date, in the configured switch data timezone
- Client Number
- ANI
- Call Type
- Agent
- Minimum and maximum duration, in seconds
- Keyword, and keyword contains
- Has messages / Has recordings / Has screen capture

**Preview Count** runs the query and reports how many calls match without downloading
anything, so you can narrow a filter before exporting.

## Output

Exports include 29 columns of call data: call identifiers, client information, caller
details, call type, agent information, duration, and attribute flags. Timestamps use the
configured switch data timezone.

Each team's allowed account numbers and billing codes are enforced automatically. Users
only receive data for the clients their team is authorized to see, and this cannot be
overridden from the export screen.

## History

The **History** page lists past exports and who ran them. Export log entries are kept for
90 days by default (`CSV_EXPORT_LOG_DAYS_TO_KEEP`) and are purged nightly.

## Setup

Enable CSV Export in [System Settings](../system/index.md). The System → CSV Export page
summarises how access and account restrictions are applied.
