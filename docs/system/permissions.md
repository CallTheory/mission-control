# Permissions

Permissions in Mission Control are **capability-based** and **per-team**. An administrator
composes roles out of a fixed set of capabilities, and each team has its own set of roles.

> This replaced an older model with four hard-coded permission levels. If you are looking
> for "Agent / Dispatcher / Supervisor / Administrator" as fixed levels, they are now
> seeded *roles* that you can edit, rename, or replace.

Permissions are managed at **System → Permissions** (`/system/permissions`) and require
the `admin.manage_roles` capability.

## How it fits together

- A **capability** is a single, code-defined permission such as `utility.call_lookup` or
  `system.data_sources`. Capabilities are fixed — administrators cannot invent new ones,
  because a capability only means something if the application checks it.
- A **role** is a named bundle of capabilities. Roles belong to a team.
- A **user** holds one or more roles within each team they belong to.
- A **suffix rule** grants extra capabilities based on the user's linked Intelligent
  Series agent name.

For a utility, three things must all be true before a user can reach it:

1. The utility is enabled system-wide (**System → Enabled Utilities**).
2. The utility is enabled for the team (team settings).
3. The user holds the utility's capability through a role or a suffix rule.

Personal teams cannot access system settings or most utilities regardless of
capabilities.

## Seeded roles

Every non-personal team is created with six system roles. These are starting points —
edit them freely.

| Role | Intended for |
|------|--------------|
| **Administrator** | Full access, including system-level settings |
| **Manager** | Team transparency, including supervisors and agents |
| **Supervisor** | Accounts and agents assigned to their team |
| **Technical** | Utilities, API tokens, WCTP gateway and fax spool maintenance, but not individual user data |
| **Dispatcher** | Call data and utilities |
| **Agent** | Their own statistics |

## Capabilities

Capabilities are grouped in the role editor.

### System

| Capability | Grants |
|------------|--------|
| `system.access` | Access System Settings |
| `admin.manage_users` | Manage Users |
| `admin.manage_roles` | Manage Roles & Permissions |
| `system.data_sources` | Manage Data Sources |
| `system.integrations` | Manage Integrations |
| `system.observability` | Manage Observability |
| `system.azure_tokens` | Manage Azure Token Watcher |

### General

| Capability | Grants |
|------------|--------|
| `utilities.access` | Access Utilities |
| `accounts.view` | View Accounts |
| `api_tokens.manage` | Manage API Tokens |

### Team

| Capability | Grants |
|------------|--------|
| `team.manage` | Manage Team |
| `team.add_member` | Add Team Members |

### Utilities

One capability per utility, named `utility.<utility>`:

`utility.api_gateway`, `utility.better_emails`, `utility.board_check`,
`utility.call_lookup`, `utility.card_processing`, `utility.cloud_faxing`,
`utility.config_editor`, `utility.csv_export`, `utility.database_health`,
`utility.directory_search`, `utility.inbound_email`, `utility.mcp_server`,
`utility.message_export`, `utility.script_search`, `utility.voicemail_digest`

### Board

| Capability | Grants |
|------------|--------|
| `board.review` | Board Review |
| `board.report` | Board Report |
| `board.activity` | Board Activity |

### WCTP Gateway

| Capability | Grants |
|------------|--------|
| `wctp.manage` | Manage the [WCTP Gateway](wctp-gateway.md) |
| `wctp.messages` | View the WCTP message log |

### Cloud Faxing

| Capability | Grants |
|------------|--------|
| `fax.manage_spool` | Delete files from the [Cloud Faxing](../utilities/cloud-faxing.md) spool directories |

This is deliberately separate from `utility.cloud_faxing`: reading the fax status page is
routine supervisor work, while deleting spool files bypasses the Intelligent Series fax
service and cannot be undone.

## Suffix Rules

Suffix rules grant capabilities based on the user's linked Intelligent Series agent name,
so that an existing IS naming convention keeps working without assigning roles by hand.

A rule is a **match type** (`contains`, `starts with`, `ends with`, `exact`), a
**pattern**, and a set of capabilities.

Two rules are seeded by default:

| Pattern | Grants |
|---------|--------|
| `-SUP` | Board Check, Board Review, Board Report, Board Activity, Cloud Faxing |
| `-DISP` | Board Check |

An agent named `JSMITH-SUP` therefore reaches Board Check and the fax status page without
holding the supervisor role.

## Teams

There are two kinds of team:

1. **Personal teams** — created automatically for each user. A personal team cannot manage
   system settings or reach most utilities. It exists so that a user has a private
   dashboard.
2. **Teams** — everything else. Utilities, system settings, accounts and analytics are
   reached by switching into a team.

If you have not created a regular team yet, see the [getting started guide](../getting-started.md).
