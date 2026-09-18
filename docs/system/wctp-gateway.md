# WCTP Gateway

The WCTP Gateway provides enterprise SMS messaging through the Wireless Communications
Transfer Protocol (WCTP) standard, letting Intelligent Series send and receive text
messages through Twilio, Bandwidth, or Com.io (thinQ).

> **This moved.** The WCTP Gateway used to be a per-team utility under
> `/utilities/wctp-gateway`. Carriers, phone numbers, enterprise hosts and message
> traffic are one installation-wide configuration rather than something each team
> manages, so the whole section now lives under **System → WCTP Gateway**
> (`/system/wctp`). The old utility URLs redirect to their new homes.

## Access

The section is gated on two capabilities, both seeded to the **Administrator** and
**Technical** roles only:

| Capability | Covers |
|------------|--------|
| `wctp.manage` | Gateway settings, Carriers, Enterprise Hosts, and retrying a message |
| `wctp.messages` | The message log — read access only |

They are separate so that read access to the log can be granted to another role without
also handing over the setup screens. That is a checkbox in the role editor under
[Permissions](permissions.md), not a code change.

The section index at `/system/wctp` shows only the links the viewer can follow. With the
**WCTP Gateway** system feature switched off, every page in the section returns a 404.

## Pages

| Page | Path | Purpose |
|------|------|---------|
| Gateway | `/system/wctp/gateway` | Gateway-level settings |
| Carriers | `/system/wctp/carriers` | Default carrier and this installation's webhook URLs |
| Enterprise Hosts | `/system/wctp/enterprise-hosts` | Hosts, their phone numbers, and each number's carrier |
| Messages | `/system/wctp/messages` | The message log |

## Enterprise Hosts

An enterprise host is a WCTP-enabled endpoint that can send and receive messages. Each
host is configured with:

- **Name** — a descriptive name for the host
- **Sender ID** — the WCTP sender identifier used for message routing
- **Security Code** — an encrypted security code for authentication
- **Callback URL** — the webhook URL for delivery notifications
- **Phone Numbers** — one or more numbers assigned to this host, each with its own carrier
- **Enabled/Disabled** — toggle the host on or off

Hosts can be looked up by sender ID or by phone number, which is how an incoming message
is routed to the right destination.

## Carriers

All three carriers work in both directions: a WCTP `SubmitRequest` goes out through one
of them, and an inbound SMS or delivery receipt comes back in through the same one.

### How a carrier is chosen

The carrier is a property of the **phone number**, not of the enterprise host — a DID
belongs to exactly one carrier, so one host can hold a Twilio number and a Bandwidth
number at the same time.

1. The message is sent from the first number assigned to the enterprise host.
2. The carrier assigned to that number wins.
3. A number with no carrier assigned uses the system default, set at
   **System → WCTP Gateway → Carriers**.
4. With no default stored either, Twilio is used — the carrier the gateway shipped with.
5. A host with no numbers at all sends from the default carrier's own from-number.

If the chosen carrier has no credentials stored, the submit is refused immediately with
WCTP `503 Service unavailable` rather than being queued up to fail later.

Numbers and their carriers are edited under **Enterprise Hosts**.

### Credentials

Carrier credentials are entered at [System → Integrations](integrations.md). Secrets are
encrypted at rest and are never rendered back into the page — leaving a secret field
blank keeps the value already stored.

| Carrier | Credentials |
|---------|-------------|
| Twilio | Account SID, Auth Token, From Number |
| Bandwidth | Account ID, Application ID, API Token, API Secret, From Number |
| Com.io | Account ID, API Username, API Token, From Number |

For Bandwidth, the application ID is the messaging application that owns your numbers.
For Com.io, the username is the portal user the token belongs to.

## Webhook URLs

| Carrier | Inbound SMS | Delivery receipts |
|---------|-------------|-------------------|
| Twilio | `POST /wctp/sms/incoming` | `POST /wctp/callback/{wctpMessageId}` |
| Bandwidth | `POST /wctp/sms/bandwidth/incoming` | `POST /wctp/bandwidth/callback` |
| Com.io | `POST /wctp/sms/commio/incoming` | `POST /wctp/commio/callback` |

Twilio keeps its original unprefixed paths so consoles configured before the other
carriers existed keep working. Every carrier also has provider-scoped paths
(`/wctp/sms/twilio/incoming`, `/wctp/twilio/callback/{id}`).

For Bandwidth and Com.io, **both** URLs accept **both** kinds of post. Bandwidth's
messaging application has a single callback URL that receives inbound messages and
delivery receipts together, so one entry in the portal is enough, and it does not matter
which of the two URLs you paste where.

> The exact URLs for your installation are listed at **System → WCTP Gateway →
> Carriers**, and in each carrier's dialog under **System → Integrations**.

### Webhook authentication

Every inbound endpoint fails closed: with no credential stored to check against, the
request is rejected with `403`. An unauthenticated inbound endpoint would let anyone
inject messages into an enterprise host's queue.

- **Twilio** — the request signature (`X-Twilio-Signature`) is validated against your
  stored auth token. Nothing extra to configure.
- **Bandwidth and Com.io** — either of:
    - HTTP Basic credentials (**Callback Username** / **Callback Password**). Bandwidth
      can send these from the messaging application directly.
    - A shared secret (**Callback Token**), sent as `?token=...` on the URL or as an
      `X-Callback-Token` header. This is the usual choice for Com.io, whose portal takes
      a plain URL with no auth options.

Both methods are accepted for either carrier, because which one a portal can send is a
property of the portal.

## Incoming SMS

Incoming messages are routed to the correct enterprise host based on the destination
phone number. The gateway normalizes numbers — handling the `+1` prefix and stripping
non-digit characters — so that a match is found regardless of the format the carrier
uses.

## Message Log

The message log at `/system/wctp/messages` shows all WCTP traffic processed by the
gateway:

- Outbound messages sent from Intelligent Series
- Delivery status and timestamps
- Message content and recipient details
- The carrier each message went through
- Message counts per enterprise host

Each carrier identifies a message differently, so the log records both our own message id
and the carrier's. Twilio calls a per-message URL carrying the WCTP message id; Bandwidth
echoes the id back in its `tag` field; Com.io receipts are matched on the thinQ `guid`
stored when the message was accepted.
