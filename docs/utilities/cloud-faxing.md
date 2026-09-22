# Cloud Faxing

Mission Control sits between Amtelco's Intelligent Series Fax Service and a cloud fax
provider, sending faxes through the *Copia* integration of Intelligent Series Fax.

Two providers are supported:

- **mFax** (by Documo) — enterprise cloud fax for regulated industries
- **RingCentral** — the RingCentral Fax API

You sign up for your own account with the provider and enter the API details in the
System section.

> Bandwidth and Commio are **SMS carriers** for the [WCTP Gateway](../system/wctp-gateway.md).
> They are not fax providers and have no fax spool directories.

## How it works

Intelligent Series drops a pair of files into a spool directory, Mission Control submits
the fax to the provider, and once the provider confirms delivery the files are moved to
`sent/` (or `fail/`) with a status code that Intelligent Series reads back.

- **`.cap`** — the fax payload (the message text).
- **`.fs`** — the per-recipient metadata: the IS job id, the recipient number, and which
  `.cap` it refers to. One `.cap` can be fanned out to several recipients, each with its
  own `.fs`.

## Fax Servers

Amtelco's fax service allows exactly one output path, so the provider used to be decided by
*which directory* Intelligent Series was pointed at — changing from mFax to RingCentral
meant an IS Supervisor change on your server. It no longer does. A **fax server** is simply
a place faxes arrive from, and Mission Control chooses the provider per fax.

Sites often run more than one IS server, with only one of them processing faxes at a time.
Every fax server is read independently, every minute, and whichever is producing files is
the live one. There is no failover and no primary: which server is active is Intelligent
Series' decision, and Mission Control never needs to know. A server sitting reachable with
an empty `tosend/` is the normal resting state, not a fault, and one that goes down cannot
hold up another.

How many fax servers you need depends on how the files reach us, not on how many IS
servers you have:

| Topology | Fax servers needed |
|---|---|
| **IS writes to our share** (`\\mission-control\fax\tosend`) | **One**, however many IS servers you run — they all write to the same place |
| **We read the IS server's own share** (`//isserver/fax`) | **One per IS server**, each with its own host and credentials |

Manage them at **System → Cloud Faxing → Fax Servers**. **Test Connection** reports whether
the share answers, how long it took, what is in each folder, and — importantly — whether it
is *writable*: delivery results are reported back to Intelligent Series by rewriting the
`.fs` file, so a read-only share sends faxes that are never confirmed.

### Reading an IS server's share directly

Mission Control can connect to a Windows share itself instead of relying on a `cifs` mount.
This is worth doing. On a stale kernel mount, reads block in uninterruptible sleep, where no
PHP timeout can reach them — one unreachable server can tie up workers indefinitely. An
app-level connection has a real timeout, and the credentials become something the admin
screen can test rather than a line in `/etc/fstab`.

To migrate an existing mount:

```bash
php artisan isfax:detect-mounts            # report what is mounted
php artisan isfax:detect-mounts --persist  # create draft fax servers from it
```

This recovers the host, share, username and domain from `/proc/mounts`. It cannot recover
the **password** — that was consumed by `mount.cifs` and is not recorded anywhere readable
— so the draft is created disabled and still on the local driver, and your existing mount
keeps serving every fax. Add the password under **System → Cloud Faxing → Fax Servers**,
use **Test Connection**, then switch the server to the SMB driver and enable it. Unmount
only once it has been running that way happily; switching back is a single change.

Kerberos (`sec=krb5`) mounts are reported but cannot be migrated — they authenticate with a
ticket rather than a password.

> **If you keep a `cifs` mount**, make it a **soft** mount (`soft,echo_interval=10`).
> On a hard mount a dead server blocks reads in a way nothing in the application can
> interrupt.

## Provider Routing

Which provider a fax goes out through is set at **System → Cloud Faxing → Provider Routing**,
and needs no Intelligent Series change:

- **Default provider** — used by any fax server that is not pinned to one.
- **Failover** — off by default. When on, a failed submission is retried through the other
  provider.
- **Pins** — force particular faxes through a particular provider. Pin a **destination fax
  number** when one provider's route to it starts failing, or an **Intelligent Series
  account** to move one client. Numbers match however they are written.

Each pin decides for itself whether failover applies. Leave it off when the pin exists
*because* the other provider is failing for that fax — falling back to it would undo the fix.

**Check a number** on the routing screen shows which provider a given number would use and
why, which is the question to ask first when a fax went somewhere unexpected.

The provider that actually sent each fax, and the reason it was chosen, are recorded against
the fax and shown on the status pages.

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

### Recommended: one provider-agnostic path

Point Intelligent Series Faxing at a single set of folders and let Mission Control choose
the provider. Changing provider afterwards is then a setting here, not a change on your
server:

- Capture (CAP): `\\mission-control.yourdomain.com\fax\tosend`
- Pre Process: `\\mission-control.yourdomain.com\fax\preproc`
- Send (TOSEND): `\\mission-control.yourdomain.com\fax\tosend`
- Sent (SENT): `\\mission-control.yourdomain.com\fax\sent`
- Fail (FAIL): `\\mission-control.yourdomain.com\fax\fail`

Add a matching fax server in Mission Control with the key `fax`, leaving its provider unset
so routing decides. If you run several IS servers, they can all point at this same path —
only one processes faxes at a time, so they will not collide.

### Existing provider-named paths

The original per-provider shares keep working exactly as they always have, and appear as
two fax servers named **mFax** and **RingCentral**. Nothing needs to change unless you want
to stop editing IS Supervisor to switch providers.

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

Prefer adding an SMB fax server (above), which Mission Control manages and can test. The
`cifs` mount below still works and is what existing installations use.

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

Each active provider gets its own status page under **Utilities → Cloud Faxing**. When more
than one fax server is configured, a switcher appears at the top of the page and each
server's spool is shown separately. Each page shows:

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
  minutes, meaning processing has stalled. The alert names the fax server, the stuck files
  and the accounts they belong to, and links to the status page where they can be cleared.

A fax server being *unreachable* is recorded and shown on the Fax Servers screen but does
not email: with several IS servers, one being switched off is ordinary. What is alerted on
is work that arrived and then stopped moving.

If two fax servers hold unsent files at the same time, that is logged as a warning — only
one Intelligent Series fax service should be processing at a time, so it usually means two
are active.

## Troubleshooting

**Faxes fail in bursts during busy periods.** Check the webhook line on the status page
first — if no webhook has ever arrived, polling is consuming the API quota that sending
needs.

**A file sits in `tosend/` and nothing happens.** The buildup alert names the file and its
account. Deleting the file from the status page clears it without an SSH session.

**A fax went out through the wrong provider.** Use **Check a number** on the Provider
Routing screen: it reports which provider that number resolves to and why. A pin on the
number or on the account outranks the fax server's own provider.

**One IS server's faxes stopped but the other's are fine.** Look at the Status column on
the Fax Servers screen and use **Test Connection**. Each server is read independently, so
one failing does not affect the others — and its files stay where they are until it comes
back.

**Everything shows "Account unknown".** Mission Control cannot reach the Intelligent
Series database, or the job ids in the `.fs` files are not present in it. Faxing still
works; only the labelling is lost. Check the IS connection under
[Datasources](../system/datasources.md).
