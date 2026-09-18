# Cloud Faxing

Mission Control sits between Amtelco's Intelligent Series Fax Service and a cloud fax
provider (Documo mFax or RingCentral). IS drops a pair of files into a spool directory,
Mission Control submits the fax to the provider, and once the provider confirms delivery
the files are moved to `sent/` (or `fail/`) with a status code IS reads back.

- **`.cap`** — the fax payload (the message text).
- **`.fs`** — the per-recipient metadata: the IS job id (`$var_def DATA5`), the recipient
  number, the `.cap` it refers to. One `.cap` may be fanned out to several recipients,
  each with its own `.fs`.

Spool directories live under `storage/app/{mfax,ringcentral}/{tosend,sent,fail,preproc}`.

## Pipeline

| Step | Owner |
|:--|:--|
| Parse `.fs` files, dispatch a send job | `isfax:process`, `isfax:process-ring-central` (every minute) |
| Submit the fax, write a `pending_faxes` row | `SendFaxJob`, `SendFaxRingCentral` |
| Resolve delivery status | `FaxWebhookController` (primary), `isfax:check-pending` (fallback) |
| Move the spool files, set the IS status code | `MoveSuccessfulFaxFiles`, `MoveFailedFaxFiles` |
| Alert on files that stop moving | `isfax:monitor {provider}` (every 30 minutes) |
| Build the RingCentral status page snapshot | `isfax:build-ringcentral-dashboard` (every minute) |

## Delivery webhooks

**Configure these.** They are how a fax is meant to be resolved; the poller is a fallback
that spends provider API quota the outbound faxes need.

Set `FAX_WEBHOOK_SECRET` and point the provider at:

```
POST https://<host>/api/webhooks/fax/mfax?token=<FAX_WEBHOOK_SECRET>
POST https://<host>/api/webhooks/fax/ringcentral?token=<FAX_WEBHOOK_SECRET>
```

The secret may also be sent as an `X-Webhook-Secret` header. Verification fails closed:
with `FAX_WEBHOOK_SECRET` unset, every callback is rejected.

Both fax utility pages show when a callback was last received. If that line reads *"No
delivery webhook has ever been received"*, every delivery confirmation is coming from
polling — which is the single largest consumer of RingCentral API quota in this system.

## RingCentral rate limiting

RingCentral throttles the fax endpoint as a *heavy* API group, and throttles the OAuth
token endpoints separately and far more tightly. Four things keep us inside those limits:

1. **One shared access token.** `App\Services\Faxing\RingCentralClient` caches the token
   in Redis (encrypted, keyed on a fingerprint of the credentials so rotating them misses
   the old entry) and refreshes it under a lock. Every caller — send jobs, the poller, the
   dashboard builder, the utility page — shares it. Previously each of those performed its
   own JWT login, so a burst of 200 faxes cost 200 token requests on top of 200 sends.
2. **A client-side limiter.** `RateLimiter::for('ringcentral')`, sized by
   `RING_CENTRAL_SENDS_PER_MINUTE`. Exceeding what the account actually allows just trades
   a clean queue wait for 429s.
3. **429 is a delay, not a failure.** `SendFaxRingCentral` catches a throttling response,
   honours `Retry-After`, and releases the job without spending its exception budget.
   `maxExceptions = 3` still caps genuine send errors.
4. **A long retry window.** `RING_CENTRAL_RETRY_WINDOW` (default two hours) bounds how long
   a throttled fax keeps waiting its turn. This used to be ten minutes, which against a
   10/minute limiter meant a backlog over ~100 faxes began *failing* — files moved to
   `fail/`, alert emails sent — for faxes that were only queued.

The poller is correspondingly cheap: it ignores a fax for `FAX_POLL_GRACE_SECONDS` after
submission, re-checks any one fax no more often than `FAX_POLL_INTERVAL_SECONDS`, polls at
most `RING_CENTRAL_POLL_BATCH_SIZE` faxes per run (oldest-checked first, so nothing
starves), and stops for the rest of the run the moment it is throttled.

### Queue configuration

`ringcentral` and `mfax` run on their own Horizon supervisor (`supervisor-faxing`) so a
burst of media or export work cannot leave faxes waiting while their retry windows run
down.

Note that a `retry_after` key inside a `config/horizon.php` supervisor block **is inert** —
Horizon stores it and never uses it. The value that applies comes from the queue connection
in `config/queue.php`, which is why each lane has its own connection whose `retry_after`
exceeds that supervisor's `timeout`. When they are equal, a job that is merely slow is
handed to a second worker while the first is still running, which for a fax means sending
it twice.

## Account attribution

Faxes are identified by the Intelligent Series account (client) they belong to, resolved by
`App\Services\Faxing\FaxAccountLookup` — the `faxJobs` → `cltClients` join on the job id
from the `.fs` file, cached per job id.

mFax carries this as a provider-side tag, which is how faxes have always been identified in
the Documo interface. RingCentral has no tag concept, so instead the account is stored on
the `pending_faxes` row (`client_number`, `client_name`) at submission time. That makes it
available everywhere regardless of provider:

- both fax status tables (RingCentral records are matched back on the provider message id);
- the spool file listings — a `.fs` resolves its own account, and a `.cap` borrows one from
  the `pending_faxes` row that references it or from a sibling `.fs` that points at it;
- the failure and buildup alert emails.

RingCentral additionally gets the account number prefixed onto the uploaded document's
filename, which is the closest available analogue to the mFax tag: it is what shows against
the message in RingCentral's own message store.

A lookup failure never blocks a fax — it just goes out unattributed.

## Spool file maintenance

A phantom `.cap` or `.fs` — one the fax service will never pick up — stalls an account's
faxing and used to require an SSH session to clear. Both fax utility pages now list every
spool file with its type, size, age and account, and offer per-file **Delete** and per-folder
**Clear Folder** actions.

These are gated on `Capability::FaxManageSpool` (`fax.manage_spool`), held by the **admin**
and **technical** roles by default and editable from the roles UI. It is deliberately
separate from `utility.cloud_faxing`: reading the fax status page is routine supervisor
work, while deleting spool files bypasses the fax service and cannot be undone.

`App\Services\Faxing\FaxSpool` performs the deletions. Filenames arrive from the browser, so
each one is reduced to a basename, matched against a conservative pattern, and confirmed by
`realpath` to sit directly inside the intended spool directory — a traversal attempt or a
symlink resolves elsewhere and is refused. Every deletion is logged with the acting user.

Deleting a `.fs` also stops the two things that would otherwise keep chasing it: any pending
`pending_faxes` row is resolved, and the send job's `ShouldBeUnique` lock is force-released
so a later file of the same name is not silently ignored.

## Configuration

| Variable | Default | Purpose |
|:--|:--|:--|
| `FAX_WEBHOOK_SECRET` | — | Shared secret on provider callbacks. Unset = all callbacks rejected. |
| `RING_CENTRAL_SENDS_PER_MINUTE` | `10` | Client-side submission limiter. |
| `RING_CENTRAL_RETRY_WINDOW` | `7200` | How long a throttled fax keeps waiting before it is failed. |
| `RING_CENTRAL_POLL_BATCH_SIZE` | `15` | Max faxes polled per `isfax:check-pending` run. |
| `FAX_POLL_GRACE_SECONDS` | `120` | Delay before a submitted fax is polled at all. |
| `FAX_POLL_INTERVAL_SECONDS` | `120` | Minimum gap between polls of the same fax. |
| `FAX_PENDING_TIMEOUT_SECONDS` | `7200` | How long a fax may stay pending before it is given up on. |

## Troubleshooting

**Faxes fail in bursts during busy periods.** Check the fax page's webhook line first — if
no webhook has ever arrived, polling is consuming the API quota that sending needs. Then
check `RING_CENTRAL_SENDS_PER_MINUTE` against what the RingCentral account actually allows.

**A file sits in `tosend/` and nothing happens.** The buildup alert names the file and its
account. A `.fs` whose send job died holding its unique lock is the usual cause; the lock
now expires on its own (`uniqueFor`), and deleting the file from the utility page releases
it immediately.

**Everything is "Account unknown".** `FaxAccountLookup` cannot reach the IS database, or the
job ids in the `.fs` files are not in `faxJobs`. Faxing still works; only the labelling is
lost. Check the IS data source credentials at System → Data Sources.
