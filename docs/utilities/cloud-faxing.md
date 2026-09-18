# Cloud Faxing

Mission Control sits between Amtelco's Intelligent Series Fax Service and a cloud fax
provider, sending faxes through the *Copia* integration of Intelligent Series Fax.

Two providers are supported:

- **mFax** (by Documo) — enterprise cloud fax for regulated industries
- **RingCentral** — the RingCentral Fax API

You sign up for your own account with the provider and enter the API details in the
System section.

> Bandwidth and Com.io are **SMS carriers** for the [WCTP Gateway](../system/wctp-gateway.md).
> They are not fax providers and have no fax spool directories.

## How it works

Intelligent Series drops a pair of files into a spool directory, Mission Control submits
the fax to the provider, and once the provider confirms delivery the files are moved to
`sent/` (or `fail/`) with a status code that Intelligent Series reads back.

- **`.cap`** — the fax payload (the message text).
- **`.fs`** — the per-recipient metadata: the IS job id, the recipient number, and which
  `.cap` it refers to. One `.cap` can be fanned out to several recipients, each with its
  own `.fs`.

## Mission Control Setup

- Enable **Cloud Faxing** under System → Enabled Utilities, and for each team that needs it
- Choose which providers are active at **System → Cloud Faxing**
- Configure your fax submission failure and folder buildup email notifications at
  **System → Cloud Faxing**
- Configure [delivery webhooks](#delivery-webhooks) — this matters more than it looks
- Enable Samba on the Mission Control server (contact support with the IP addresses of
  your IS Fax Service server)

## mFax Setup

Minimum required setup:

1. Create a new API-only account with mFax by Documo.
2. Generate an API key to be used.
3. Enter the mFax API key into the System section of Mission Control.

If using an mFax Cover Page:

1. Get the Cover Page ID you want to use from mFax (try *Copy ID to clipboard* from your
   chosen Cover Page menu).
2. Enter the mFax Cover Page ID into the System section of Mission Control.
3. Enter the Cover Page Sender Name (required).
4. Enter the Cover Page Subject Line (required).
5. Enter the Cover Page Notes (optional).

## RingCentral Setup

Minimum required setup:

1. Create a new RingCentral Fax developer account.
2. Generate an API client application to be used.
3. Generate a JWT token from user credentials.
4. Enter the RingCentral Client ID into the System section of Mission Control.
5. Enter the RingCentral Client Secret into the System section of Mission Control.
6. Enter the RingCentral JWT Token into the System section of Mission Control.
7. Enter the RingCentral API Endpoint into the System section of Mission Control.

## Intelligent Series Supervisor Setup

Point Intelligent Series Faxing at the Samba shares for mFax:

- Capture (CAP): `\\mission-control.yourdomain.com\mfax\tosend`
- Pre Process: `\\mission-control.yourdomain.com\mfax\preproc`
- Send (TOSEND): `\\mission-control.yourdomain.com\mfax\tosend`
- Sent (SENT): `\\mission-control.yourdomain.com\mfax\sent`
- Fail (FAIL): `\\mission-control.yourdomain.com\mfax\fail`

If you want to send through RingCentral, use the following settings:

- Capture (CAP): `\\mission-control.yourdomain.com\ringcentral\tosend`
- Pre Process: `\\mission-control.yourdomain.com\ringcentral\preproc`
- Send (TOSEND): `\\mission-control.yourdomain.com\ringcentral\tosend`
- Sent (SENT): `\\mission-control.yourdomain.com\ringcentral\sent`
- Fail (FAIL): `\\mission-control.yourdomain.com\ringcentral\fail`

### Alternative Setup

We can also configure a `cifs` mount to your Windows UNC share. To do this, you will need
to create a Windows service account for Mission Control to connect through, and provide
the username and password to Call Theory for setup.

```bash
id forge
# returns uid=1001,gid=1001 which is used in the next command
sudo mount -t cifs //server.domain.local/mfax /home/forge/mission-control/storage/app/mfax -o username=domainuser,password=redacted,domain=domain.local,uid=1001,gid=1001
sudo mount -t cifs //server.domain.local/people-praise /home/forge/mission-control/storage/app/people-praise -o username=domainuser,password=redacted,domain=domain.local,uid=1001,gid=1001
```

## Delivery Webhooks

Delivery webhooks are how a fax gets confirmed. Mission Control can also poll the provider
for status, but polling is a fallback that spends provider API quota the outbound faxes
need — on a busy system that is the single largest cause of rate limiting.

**Configure these.** Set a webhook secret and point the provider at:

```
POST https://your-server.tld/api/webhooks/fax/mfax?token=<secret>
POST https://your-server.tld/api/webhooks/fax/ringcentral?token=<secret>
```

The secret may also be sent as an `X-Webhook-Secret` header. Verification fails closed:
with no secret configured, every callback is rejected.

Both fax status pages show when a callback was last received. If that line reads *"No
delivery webhook has ever been received"*, every delivery confirmation is coming from
polling and you should fix the webhook configuration.

## Fax Status Pages

Each active provider gets its own status page under **Utilities → Cloud Faxing**, showing:

- Recent faxes with their delivery status, with a **Resend** action on failed faxes
- The account each fax belongs to
- File counts and contents for each of the four spool folders
- When a delivery webhook was last received

## Account Attribution

Faxes are identified by the Intelligent Series account (client) they belong to, resolved
from the job id in the `.fs` file.

mFax carries this as a provider-side tag, which is how faxes have always been identified
in the Documo interface. RingCentral has no tag concept, so Mission Control stores the
account itself when the fax is submitted. The account then appears in the same places for
both providers:

- both fax status tables
- the spool file listings — a `.fs` resolves its own account, and a `.cap` borrows one
  from the record that references it or from a sibling `.fs` that points at it
- the fax failure and folder buildup alert emails

RingCentral additionally gets the account number prefixed onto the uploaded document's
filename, which is the closest available equivalent of the mFax tag — it is what shows
against the message in RingCentral's own message store.

If the account cannot be resolved the fax still goes out; it is just unlabelled.

## Spool File Maintenance

A phantom `.cap` or `.fs` — one the fax service will never pick up — stalls an account's
faxing. Both fax status pages list every spool file with its type, size, age and account,
and offer:

- **Delete** on an individual file
- **Clear Folder** to empty a spool folder

These controls require the `fax.manage_spool` capability, held by the **Administrator**
and **Technical** roles by default and editable from [Permissions](../system/permissions.md).
It is separate from `utility.cloud_faxing` on purpose: reading the status page is routine
work, while deleting spool files bypasses the fax service and cannot be undone.

Every deletion is recorded in the application log with the user who performed it.
Deleting a `.fs` also stops Mission Control from chasing that fax any further.

## Alerts

Two notification emails are configured at **System → Cloud Faxing**:

- **Fax Failure Notification Email** — a fax submission to mFax or RingCentral failed. For
  normal fax delivery failures, use the built-in notifications in your mFax or RingCentral
  portal.
- **Fax Buildup Notification Email** — files are sitting in a spool folder longer than 15
  minutes, meaning processing has stalled. The alert names the stuck files and the
  accounts they belong to, and links to the status page where they can be cleared.

## Troubleshooting

**Faxes fail in bursts during busy periods.** Check the webhook line on the status page
first — if no webhook has ever arrived, polling is consuming the API quota that sending
needs.

**A file sits in `tosend/` and nothing happens.** The buildup alert names the file and its
account. Deleting the file from the status page clears it without an SSH session.

**Everything shows "Account unknown".** Mission Control cannot reach the Intelligent
Series database, or the job ids in the `.fs` files are not present in it. Faxing still
works; only the labelling is lost. Check the IS connection under
[Datasources](../system/datasources.md).
