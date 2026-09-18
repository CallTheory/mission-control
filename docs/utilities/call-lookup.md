# Call Lookup

A simple tool to lookup call information for a particular call from the Amtelco Intelligent Series service.

## Quick Usage

Every page in the Mission Control dashboard has a search bar at the top. You can use this search bar to search for a call by its call ID.

Simply enter the `ISCallId` and press the <kbd>Enter</kbd> key!

## Normal Usage

- Click the *Utilities* link across the top of Mission Control
- Click the *Call Lookup* link in the left-side navigation (or use the button on the Utilities page)
- Enter the `ISCallId` into the text box and press *Search*

## Who can reach a call

Opening a call, playing its recording and watching its screen capture all follow one
rule, applied by `App\Support\CallAccess`:

- **Personal teams** have no account allow-lists and never will, so they are scoped by
  agent: you reach the calls you handled, matched on your `agtId`. A user with no `agtId`
  reaches nothing.
- **Shared teams** are scoped by the team's *Allowed Accounts* and *Allowed Billing
  Numbers*, or by the **This team may see every account** checkbox beside them.

An empty allow-list means "no restriction" throughout Mission Control -- the listing
queries emit no filter for it at all. That reading is only safe when someone chose it, so
a shared team with no lists **and** without the checkbox ticked counts as unconfigured,
and call data is withheld from it rather than shown in full.

Team settings will not let you save that state: a team must either list what it may see
or be marked as seeing everything. Teams that predate this setting were backfilled to
"sees every account", so nothing changed for them -- it applies to teams created and
never configured.

### Why a checkbox rather than a wide-open range

Writing a permissive range such as `1-299999` into the list looks equivalent but is not.
The range is matched numerically, so it silently denies any account above the bound you
picked, any non-numeric account number, and any call whose client record did not resolve
(`Call`'s query uses a `left join` on `cltClients`, so `ClientNumber` is nullable). It
also has to stay true forever. The checkbox states the same intent without a number that
can go stale.
