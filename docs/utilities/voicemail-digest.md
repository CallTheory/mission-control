# Voicemail Digest

The Voicemail Digest utility delivers scheduled email digests of voicemail recordings to configured recipients.

## Access

The Voicemail Digest requires the `utility.voicemail_digest` capability, and must be enabled
both as a system utility and for the team. See [Permissions](../system/permissions.md).

## Creating a Digest

Each digest is configured with the following options:

- **Name**: A descriptive name for the digest
- **Client Number**: Filter voicemails by client account number
- **Billing Code**: Filter voicemails by billing code
- **Recipients**: One or more email addresses to receive the digest
- **Subject**: The email subject line
- **Timezone**: The timezone for scheduling
- **Include Transcription**: Optionally include voicemail transcriptions in the digest
- **Include Call Metadata**: Optionally include call metadata (caller ID, duration, etc.)
- **Enabled**: Toggle the digest on or off

## Schedule Types

Digests can be scheduled at different intervals:

- **Hourly**: Runs every hour
- **Daily**: Runs once per day at the configured time
- **Weekly**: Runs once per week on the configured day and time
- **Monthly**: Runs once per month on the configured day and time

The system automatically calculates the next run time and tracks the last execution timestamp.

## Running on Demand

The **Send Now** action runs a digest immediately, asking for a start and end date rather
than using the schedule's own window. This works for scheduled digests too, so you can
resend a period without disturbing the schedule.

## History

The **History** page lists past digest runs and their status. Log entries are kept for 30
days by default (`VOICEMAIL_DIGEST_LOG_DAYS_TO_KEEP`) and are purged nightly.

## Setup

Enable the Voicemail Digest in [System Settings](../system/index.md). Transcriptions are
only included when the **Transcription** system feature is also enabled — see
[Transcriptions](../transcriptions.md).
