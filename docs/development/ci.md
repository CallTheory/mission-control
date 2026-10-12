# Continuous integration

The test suite, static analysis and the asset build run on every pull request and every
push to `main`, defined in [`.forgejo/workflows/ci.yml`](../../.forgejo/workflows/ci.yml).

Three jobs, deliberately separate so a red build says *which* thing broke:

| Job | What it proves |
|---|---|
| **Test suite** | `php artisan test --testsuite=Unit,Feature` |
| **Static analysis** | `pint --test` for formatting, `phpstan analyse` against the baseline |
| **Asset build** | `pnpm run build` still produces a bundle |

The asset build needs **Composer** packages as well as node ones, which is not obvious:
`resources/css/app.css` imports seven stylesheets out of `vendor/filament` and `@source`s
Filament's PHP and Blade so Tailwind can scan them for class names. Without `vendor/` the
build fails on the first `@import`, and a build that somehow resolved it would still emit
CSS missing every Filament class.

## Why a prebuilt image

Every job runs inside a PHP 8.4 container rather than installing PHP on the runner.

This suite needs `ext-mailparse`, which is in no stock PHP image, and the SQL Server
integration tests need `sqlsrv` and `pdo_sqlsrv`, which only exist as `pecl` builds. Both
compile in minutes. Doing that on every run would cost more wall-clock than the tests do.

`docker/8.4/Dockerfile` already installs all of it — it is the image Sail builds for local
development — so CI uses the same environment developers do, which is the point.

## Using your own image

**You are not required to use ours.** Set a repository variable `CI_IMAGE` under
**Settings → Actions → Variables** and all three jobs use it instead:

```
CI_IMAGE = registry.example.com/your-project/ci:8.4-1
```

A fork that sets nothing falls back to the image named in the workflow, which is published
in a public registry project precisely so that a fork can run the tests it inherited
without anyone's credentials.

### Building it

From a clone of this repository:

```bash
# The build arg is required by the Dockerfile; any group id will do for CI, where
# nothing is bind-mounted from a host user.
docker build \
  --build-arg WWWGROUP=1000 \
  -t registry.example.com/your-project/ci:8.4-1 \
  docker/8.4

docker push registry.example.com/your-project/ci:8.4-1
```

It is a large image and takes several minutes to build — a full PHP 8.4 stack, Microsoft's
ODBC 18 driver and `mssql-tools18`, Node, ffmpeg, sox and imagick — with most of the time
spent in the two `pecl install` compiles. You only rebuild it when `docker/8.4/Dockerfile`
changes, which is rarely.

The image's `ENTRYPOINT` is `start-container`, which runs supervisord and serves the app.
That does not interfere here: a runner overrides the entrypoint of a job container and
runs the workflow's own steps in it. The image needs no CI-specific variant.

Because the image declares no `USER`, steps run as root and the mounted workspace is
writable. If you build your own from a base that drops privileges, check that
`composer install` can still write `vendor/`.

### Tag it explicitly, never `latest`

The workflow pins a tag (`ci:8.4-1`) rather than tracking `latest`, and you should too.
Pinned to a mutable tag, a base rebuild can silently change what the suite ran against —
and a red build stops being attributable to the commit that caused it. Bump the number
when the Dockerfile changes.

The tag appears **three times** in the workflow, once per job. That repetition is forced:
`container.image` is evaluated before a job's environment exists, so neither a workflow
`env` nor an earlier step can supply it. Change all three.

### A private registry

`container.image` is pulled *before* any step runs, so a `docker login` step is too late to
help. Authentication has to be configured on the runner itself, in the Docker daemon's
`config.json` — not in this workflow. That is why the fallback image is public: it keeps a
fork's CI working with no runner changes at all.

## Formatting: changed files only

`vendor/bin/pint --test` with no arguments reports **60 style issues across 825 files** on
this repository. None of them are anyone's current work; the codebase was never uniformly
Pint-clean, and reformatting it wholesale would produce an unreviewable diff claiming
authorship of churn nobody intended.

So the job runs `pint --test --diff=origin/main`, which checks only what the branch
changed. New code is held to the standard and old drift is left for a deliberate pass of
its own. That is also why the job checks out with `fetch-depth: 0` and fetches `main`
explicitly: `actions/checkout` fetches the pull request's head, not the branch it targets,
so without that there is no merge base to diff against.

Run the same check locally before pushing:

```bash
vendor/bin/pint --test --diff=main
```

## The PHPStan baseline

`phpstan.neon.dist` analyses `app/` at level 5 and includes `phpstan-baseline.neon`, a
generated record of the errors that predate the analysis being added. With it, a clean run
exits zero and **only a new error fails the build** — which is the entire point of running
it in CI, and was tracked by hand before CI existed.

The baseline is a debt ledger, not an endorsement. Entries come out as the code behind them
is fixed. To regenerate it after a deliberate cleanup:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

Do not regenerate it to make a new error go away. That is what the ledger is for noticing.

### `ignoreErrors` and the baseline are different things

`phpstan.neon.dist` also keeps a hand-written `ignoreErrors` list. Those entries are
*decisions*, each with a comment explaining why PHPStan is wrong about that code — a
framework-imposed signature, an SDK it cannot resolve, a defensive null check it thinks is
redundant. They are meant to be read.

The baseline is the opposite: a machine-written list of things nobody has got to yet.

`reportUnmatchedIgnoredErrors` is left at its default, so a hand-written ignore that stops
matching is reported as an error rather than lingering. Six were removed when the baseline
was introduced, having gone stale at some earlier point — which is also why the error count
being tracked by hand had been six higher than the number of real findings.

## Two tests do not run in CI

`LocalSpoolFilesystemTest` has two cases that need the filesystem to actually refuse a
write — that an unwritable fax spool is *reported* rather than silently accepted, which
matters because results are returned to Intelligent Series by rewriting the `.fs` file into
`sent/` or `fail/`.

CI jobs run as **root**, because the image declares no `USER` and the runner mounts the
workspace as root. Root bypasses the permission check, so a directory at `0555` is still
writable to it and both tests invert. They now assert that precondition and skip when it
cannot be met, naming the effective uid in the skip message.

State the cost plainly rather than reading a green run as coverage: **on CI as it stands,
nothing checks that behaviour.** Running the suite as a normal user — which is what a
developer machine does — is what checks it. If that guarantee becomes important enough to
want in CI, the job has to run as a non-root user, which means dealing with workspace and
cache ownership.

## The test suite needs no services

`phpunit.xml` pins `APP_ENV=testing`, an in-memory SQLite database, and array drivers for
cache, session, mail and queue. So the test job needs no database, no Redis and no
built assets — `tests/TestCase.php` calls `withoutVite()`, so a page render does not need a
Vite manifest.

That last point was not true until recently. `layouts/app.blade.php` calls `@vite()` and
`public/build` is gitignored, so a fresh clone that ran `composer install && php artisan
test` got `ViteManifestNotFoundException` on every test that renders a page. The suite
passed locally only because developers had happened to run `pnpm run build` at some point.

The one thing the job does need is an `APP_KEY`, which `phpunit.xml` does not supply.

## Not yet wired: the integration suite

There is a third testsuite, `Integration`, covering the analytics reports against a real
SQL Server. It needs a database, so it needs a service container:

```yaml
  integration:
    name: Integration suite
    runs-on: ubuntu-latest
    container:
      image: ${{ vars.CI_IMAGE || 'cr.calltheory.com/mission-control/ci:8.4-1' }}
    services:
      mssql:
        image: mcr.microsoft.com/mssql/server:2022-latest
        env:
          ACCEPT_EULA: Y
          MSSQL_SA_PASSWORD: Str0ng!TestPassword
        ports:
          - 1433:1433
    steps:
      - uses: actions/checkout@v4
      - run: composer install --no-interaction --prefer-dist --no-progress
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan test --testsuite=Integration
        env:
          IS_DB_HOST: mssql
          IS_DB_PORT: 1433
          IS_DB_USER: sa
          IS_DB_PASS: Str0ng!TestPassword
```

It is left out of `ci.yml` on purpose: the `Integration` testsuite is introduced by the
analytics branch, and declaring a SQL Server service for a suite that does not exist yet
would spin up a gigabyte of database to do nothing on every push. Add the job in the same
change that adds the suite, and confirm the environment variable names against
`tests/Traits/InteractsWithIntelligentDatabase.php` and `config/database.php` — the block
above is a starting point, not a verified configuration.

## If the runner cannot fetch `actions/*`

`actions/checkout` and `actions/cache` resolve from whatever `DEFAULT_ACTIONS_URL` the
Forgejo instance is configured with. If a runner cannot reach it, both steps can be
replaced with plain shell — a `git clone` of the repository at the triggering commit, and
dropping the cache steps entirely. The jobs get slower; nothing else changes.
