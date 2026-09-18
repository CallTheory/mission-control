# WCTP Gateway — SMS Carriers

The WCTP gateway relays messages through three SMS carriers: **Twilio**,
**Bandwidth** (v2 Messaging API) and **Com.io / thinQ**. All three work in both
directions — a WCTP `SubmitRequest` goes out through one of them, and an inbound SMS
or delivery receipt comes back in through the same one.

## How a carrier is chosen

Per phone number. A DID belongs to exactly one carrier, so the carrier is a property
of the number rather than of the Enterprise Host — one host can hold a Twilio number
and a Bandwidth number at the same time.

1. The message is sent from the first number assigned to the Enterprise Host
   (`EnterpriseHost::getOutboundPhoneNumber()`).
2. The carrier assigned to that number wins (`enterprise_hosts.number_providers`).
3. A number with no carrier assigned uses the system default
   (`data_sources.sms_default_provider`, set at **System → WCTP Gateway → Carriers**,
   `/system/wctp/carriers`).
4. With no default stored either, Twilio is used — the carrier the gateway shipped
   with.
5. A host with no numbers at all sends from the default carrier's own from-number.

If the chosen carrier has no credentials stored, the submit is refused with WCTP
`503 Service unavailable` rather than being queued to fail later.

Numbers and their carriers are edited at **System → WCTP Gateway → Enterprise Hosts**
(`/system/wctp/enterprise-hosts`).

## Who can reach it

The gateway is administrative: carriers, numbers, hosts and traffic are one
installation-wide configuration, not a per-team utility, so the whole section lives
under `/system/wctp` and is gated on two capabilities:

| Capability | Covers |
| --- | --- |
| `wctp.manage` | `/system/wctp/gateway`, `/system/wctp/carriers`, `/system/wctp/enterprise-hosts`, and retrying a message |
| `wctp.messages` | `/system/wctp/messages` — read access to the log |

Both are seeded to the **admin** and **technical** roles only. They are separate so
read access to the log can be granted to another role later without also granting the
setup screens — a checkbox in the role editor, not a code change.

The section index at `/system/wctp` needs either capability and shows only the links
the viewer can follow. With the `wctp-gateway` system feature switched off, every page
in the section is a 404.

Note this replaced a per-team utility whose capability sat in the "open utilities" set
in `config/roles.php`, which meant every seeded role — agent and dispatcher included —
could create, edit and delete enterprise hosts. The migration
`2026_09_17_000007_move_wctp_gateway_to_system_capabilities` revokes that on existing
installs and grants the new capabilities to the admin and technical roles.

## Credentials

All credentials live on the single `DataSource` row, edited at **System →
Integrations**. Secrets are encrypted at rest (`EncryptedSerialized` cast) and are
never rendered back into the page — leaving a secret field blank keeps the stored
value.

| Carrier | API credentials | Notes |
| --- | --- | --- |
| Twilio | Account SID, Auth Token, From Number | Unchanged |
| Bandwidth | Account ID, Application ID, API Token, API Secret, From Number | The application ID is the messaging application that owns your numbers |
| Com.io | Account ID, API Username, API Token, From Number | The username is the portal user the token belongs to |

API hosts are configuration, not credentials, so a sandbox or regional endpoint can be
pointed at without a code change: `BANDWIDTH_ENDPOINT` (default
`https://messaging.bandwidth.com`) and `COMMIO_ENDPOINT` (default
`https://api.thinq.com`).

## Webhook URLs

| Carrier | Inbound SMS | Delivery receipts |
| --- | --- | --- |
| Twilio | `POST /wctp/sms/incoming` | `POST /wctp/callback/{wctpMessageId}` |
| Bandwidth | `POST /wctp/sms/bandwidth/incoming` | `POST /wctp/bandwidth/callback` |
| Com.io | `POST /wctp/sms/commio/incoming` | `POST /wctp/commio/callback` |

Twilio keeps its original unprefixed paths so consoles configured before the other
carriers existed keep working. Every carrier also has provider-scoped paths
(`/wctp/sms/twilio/incoming`, `/wctp/twilio/callback/{id}`).

For Bandwidth and Com.io **both** URLs accept **both** kinds of post. Bandwidth's
messaging application has a single callback URL that receives inbound messages and
delivery receipts together, so one entry in the portal is enough; which of the two
URLs is pasted where does not matter.

The exact URLs for this installation are listed at **System → WCTP Gateway →
Carriers** (`/system/wctp/carriers`), and in each carrier's dialog under **System →
Integrations**.

## Webhook authentication

Every inbound endpoint fails closed: with no credential stored to check against, the
request is rejected with `403`. An unauthenticated inbound endpoint would let anyone
inject messages into an Enterprise Host's queue.

- **Twilio** — request signature (`X-Twilio-Signature`), validated against the stored
  auth token. Nothing extra to configure.
- **Bandwidth and Com.io** — either of:
  - HTTP Basic credentials (`Callback Username` / `Callback Password`). Bandwidth can
    send these from the messaging application directly.
  - A shared secret (`Callback Token`), presented as `?token=...` on the URL or as an
    `X-Callback-Token` header. This is the usual choice for Com.io, whose portal takes
    a plain URL with no auth options.

Both are accepted for either carrier, because which one a portal can send is a
property of the portal. Validation can be switched off in development with
`WCTP_VALIDATE_PROVIDER_WEBHOOKS=false` (Twilio also honours the older
`WCTP_VALIDATE_TWILIO_SIGNATURES`).

## Matching a delivery receipt to its message

The three carriers identify a message differently, and `wctp_messages` records both
ids (`provider`, `provider_message_id`):

- **Twilio** calls a per-message URL carrying our WCTP message id.
- **Bandwidth** has no per-message callback URL, so each outbound message is sent with
  its WCTP message id as the Bandwidth `tag`, and the receipt echoes it back.
- **Com.io** has neither, so receipts are matched on the thinQ `guid` stored when the
  message was accepted.

`twilio_sid` predates multi-carrier support and is still written for Twilio messages
only — it is a Twilio SID by name, so another carrier's id does not go in it.

## Adding a fourth carrier

One class plus its credential columns:

1. Add a case to `App\Enums\SmsProvider`.
2. Implement `App\Services\Sms\SmsGateway` (extend
   `App\Services\Sms\Gateways\Gateway` for the credential and phone-number helpers,
   and use `AuthenticatesCarrierWebhook` unless the carrier signs its requests).
3. Register it in `SmsGatewayManager::GATEWAYS`.
4. Add the credential columns to `data_sources` and a tile under
   `app/Livewire/System/Integrations/`.

Routes, middleware, per-number selection, the job and the webhook handler are all
carrier-agnostic and need no changes.
