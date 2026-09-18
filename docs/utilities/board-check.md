# Board Check

The Board Check is a full-blown quality assurance review of every message taken in your system.

## Access

Board Check is gated on capabilities, and its three sub-pages each have their own:

| Page | Capability |
|------|------------|
| Board Check | `utility.board_check` |
| Board Review | `board.review` |
| Board Report | `board.report` |
| Board Activity | `board.activity` |

By default the Administrator, Manager, Supervisor and Dispatcher roles hold
`utility.board_check`, and the board sub-page capabilities are seeded to every role so
that existing workflows keep working — adjust them to suit your call center.

Suffix rules cover the Intelligent Series naming convention without assigning roles by
hand: an agent whose IS name contains `-SUP` gets Board Check plus all three sub-pages,
and `-DISP` gets Board Check. See [Permissions](../system/permissions.md#suffix-rules).

## Board Check Workflow

1. Dispatchers and supervisors can review messages from the _Board Check_ list
2. They can see the call details, tracker events, and call stats while reviewing
3. The message can be categorized, commented on, and marked who is responsible
4. The review is then sent to a supervisor Board Review list where they can approve or reject the review
5. The reviews are then exported to a CSV file suitable for use with PeoplePraise or other quality assurance systems

## Board Review

Supervisors access the Board Review list to approve or reject reviews submitted by dispatchers. This is the second stage of the quality assurance workflow.

## Board Report

The Board Report provides summary reporting on board check activity, including metrics on reviews completed, categories, and trends.

## Board Activity

The Board Activity view provides an audit log of all board check actions for accountability and compliance purposes.

## PeoplePraise Integration

Board Check can upload precision reports via API to
[PeoplePraise](../system/integrations.md#peoplepraise). Configure your PeoplePraise API
credentials in the System Integrations section.

Exports run on the `people-praise` queue every fifteen minutes.

### Screenshots

Each exported item includes a PNG screenshot of the message summary, rendered on the
Mission Control server with headless Chrome. Chrome is installed during provisioning, so
there is normally nothing to set up.

To confirm it is working:

```bash
php artisan screenshot:check --test
```

That reports whether Node.js, the required packages and the Chrome binary are all present,
generates a test screenshot, and checks that PeoplePraise credentials are configured.

Screenshot rendering needs roughly 100–200 MB of memory per process and is CPU-intensive,
which is worth knowing if you export large batches.

### If screenshots fail

A screenshot that cannot be rendered fails the export job rather than exporting without an
image, so the first place it shows up is as a failed job on the `people-praise` queue in
the Queue Viewer. The reason is in the application log:

```bash
tail -f storage/logs/laravel.log | grep "Screenshot"
```

| Symptom in the log | Cause |
|--------------------|-------|
| `Browser was not found at the configured executablePath` | The Chrome binary is missing — contact support to reinstall it |
| `EACCES: permission denied` | The web server user cannot read the Chrome cache directory |
| `error while loading shared libraries` | A system library Chrome depends on is missing |
| `Chrome crashed`, or timeouts | Not enough memory available to the process |

Also check that the PeoplePraise credentials are present under System → Integrations —
the export needs those regardless of whether the screenshot rendered.
