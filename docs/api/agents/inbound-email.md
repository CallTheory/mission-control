# Inbound Email API

The Inbound Email API provides endpoints for viewing and forwarding inbound email messages
processed by Mission Control.

These routes exist only while the **Inbound Email** utility is enabled as a system
utility.

## View Email

Retrieve the HTML or text body of an inbound email message.

**Endpoint**: `GET /api/agents/inbound-email/view/{email}`

`{email}` is the inbound email record's identifier, not an address.

**Authentication**: a **signed URL**. Mission Control generates the signed link; the
request is rejected if the signature is missing, altered, or expired. There is no API key
for this endpoint — link to it from the notification Mission Control produces rather than
constructing the URL yourself.

**Response**: the raw HTML or text content of the email, with a `200` status code.

## Forward Email

Forward an inbound email message to another email address.

**Endpoint**: `POST /api/agents/inbound-email/forward/{email}`

**Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `api_key` | string | Yes | The inbound email forwarding shared secret |
| `email` | string | Yes | Valid email address to forward to |

The shared secret is configured on the server as `INBOUND_EMAIL_FORWARD_SECRET` and is
surfaced to agents in the email itself. Verification **fails closed**: with no secret
configured, every request is rejected.

**Responses**:

| Status | Body |
|--------|------|
| `200` | `{"success": "true"}` |
| `400` | `{"success": "false", "errors": { ... }}` — validation failed |
| `403` | `{"success": "false", "error": "unauthorized"}` — bad or missing `api_key` |
