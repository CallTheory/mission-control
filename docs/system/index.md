# System Settings

The overview section of System Settings allows us to set general information about your
Mission Control system. Access requires the `system.access` capability and a non-personal
team.

## Sections

| Section | Path | Purpose |
|---------|------|---------|
| General | `/system` | Tools, timezone, feature and utility toggles |
| [Data Sources](datasources.md) | `/system/data-sources` | Intelligent Series and related connections |
| [Integrations](integrations.md) | `/system/integrations` | Third-party API credentials |
| [Permissions](permissions.md) | `/system/permissions` | Roles, capabilities and suffix rules |
| [Observability](observability.md) | `/system/observability` | Error reporting and tracing |
| [Azure Tokens](azure-tokens.md) | `/system/azure-tokens` | Entra credential expiry dashboard and alerts |
| [WCTP Gateway](wctp-gateway.md) | `/system/wctp` | SMS carriers, enterprise hosts, message log |
| [SAML SSO](../authentication/saml-sso.md) | `/system/saml-settings` | Single sign-on |
| Users | `/system/users` | User administration |

Individual utilities with system-level configuration have their own pages, linked from
**System Utilities** below.

## Queue Viewer

View the real-time queue status of background jobs being processed by Mission Control.

## Application Debug

The Application Debug feature causes large amounts of data to be saved into the local
database, including the unencrypted details of each request.

This feature is disabled by default and can only be enabled from the server command line
for short-term application debugging.

## Switch Data Timezone

Select the timezone that your switch (call) data is stored as. This is typically the
timezone assigned to your Intelligent SQL server.

## System Features

This section allows you to selectively enable or disable system features.

- Transcription
- Screen Captures
- MCP Server
- WCTP Gateway

> Turning a feature off here hides it everywhere, including its routes. It is the outermost
> of the three layers that control access — see [Permissions](permissions.md).

## System Utilities

This section allows you to selectively enable or disable system utilities. A utility
switched off here is unavailable to every team, regardless of team settings or
permissions.

Click through to learn more about the system configuration for each utility.

- [API Gateway](api-gateway.md)
- [Better Emails](../utilities/better-emails.md)
- [Board Check](../utilities/board-check.md)
- [Call Lookup](../utilities/call-lookup.md)
- [Card Processing](../utilities/card-processing.md)
- [Cloud Faxing](../utilities/cloud-faxing.md)
- [Config Editor](../utilities/config-editor.md)
- [CSV Export](../utilities/csv-export.md)
- [Database Health](../utilities/database-health.md)
- [Directory Search](../utilities/directory-search.md)
- [Inbound Email](../utilities/inbound-email.md)
- [MCP Server](../utilities/mcp-server.md)
- [Message Export](../utilities/message-export.md)
- [Script Search](../utilities/script-search.md)
- [Voicemail Digest](../utilities/voicemail-digest.md)
