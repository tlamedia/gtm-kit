# gtm-kit Tests

Automated test suites for the `gtm-kit` core plugin. Three harnesses ship here:

- **Unit (PHPUnit + BrainMonkey)** — fast, no WordPress boot. Targets pure PHP in `src/`.
- **Integration (PHPUnit + wp-phpunit)** — boots WordPress against a real database. Targets hooks, admin screens, and anything that needs WP core.
- **JavaScript (Vitest + JSDOM)** — tests for ES modules under `src/js/`.

## Prerequisites

| Tool | Version |
|---|---|
| PHP | 7.4, 8.1, 8.2, 8.3, 8.4 (CI matrix). 8.0 is permitted by Composer but [not actively tested](#why-no-php-80-in-the-matrix). |
| Composer | 2.x |
| Node | 20.x |
| npm | 10.x |
| MySQL or MariaDB | MySQL 8.0 in CI. Any recent MySQL/MariaDB works locally. |
| Subversion (`svn`) | Any recent version. Required by `bin/install-wp-tests.sh` to check out WP test helpers. |

On macOS with [Laravel Herd](https://herd.laravel.com/), Node 20+, and [DBngin](https://dbngin.com/) running MySQL 8.0 on port 3308 (the defaults assumed by the setup script), you have everything you need.

## One-command setup

From the plugin root (`wp-content/plugins/gtm-kit/`):

```bash
composer setup-tests
```

That runs, in order:

1. `composer install` — installs PHP dev dependencies.
2. `npm ci` — installs JS dev dependencies.
3. `bash bin/install-wp-tests.sh gtmkit_tests root '' 127.0.0.1:3308 6.9` — downloads WordPress 6.9, pulls the wordpress-develop test helpers, and creates the `gtmkit_tests` database.

If your database runs on a different host/port or you want a different WP version, call the installer directly:

```bash
bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> <wp-version>
# e.g. MariaDB on the default 3306:
bash bin/install-wp-tests.sh gtmkit_tests root '' 127.0.0.1 6.9
# e.g. WP 6.8 on DBngin MySQL:
bash bin/install-wp-tests.sh gtmkit_tests root '' 127.0.0.1:3308 6.8
```

The installer caches each requested WP version under `$TMPDIR/wordpress-<version>/` and `$TMPDIR/wordpress-tests-lib-<version>/`, so switching versions does not re-download.

## Running the suites

```bash
composer test            # unit + integration (PHP)
composer test:unit       # unit only
composer test:integration # integration only
npm test                 # JS (Vitest)
```

The integration suite needs `WP_TESTS_DIR` to point at the WP test install. The setup script leaves it at `$TMPDIR/wordpress-tests-lib-6.9/` by default. Export it for your shell:

```bash
export WP_TESTS_DIR="$TMPDIR/wordpress-tests-lib-6.9"
```

Or set it once in a local-only `phpunit.xml` override (which `.gitignore` already excludes):

```xml
<php>
    <env name="WP_TESTS_DIR" value="/var/folders/.../wordpress-tests-lib-6.9"/>
</php>
```

## Running a single test

```bash
# One file
vendor/bin/phpunit --testsuite unit tests/phpunit/Unit/Common/UtilTest.php

# One method
vendor/bin/phpunit --testsuite unit --filter test_shorten_version_keeps_major_minor

# One JS file
npx vitest run tests/js/dataLayer.test.js
```

## Coverage

Coverage is driven by **PCOV in CI** and **Xdebug locally** (per project decision).

```bash
composer test:coverage
open tests/_reports/coverage/index.html
```

Report directory `tests/_reports/` is gitignored. Clover XML at `tests/_reports/clover.xml` is the format uploaded as a CI artifact.

Coverage is scoped to the **integration** suite. The unit suite uses the BrainMonkey bootstrap (no WP boot), and the integration suite uses the wp-phpunit bootstrap (full WP boot) — the two cannot share a single PHPUnit invocation. Integration covers the widest set of `src/` paths, so it is the cell we measure. Line coverage for pure utilities exercised only by the unit suite will therefore be undercounted; this is an accepted tradeoff for the simpler harness.

Local runs without a coverage driver print `Warning: No code coverage driver available` and skip coverage generation — the suite itself still passes. Install Xdebug in your PHP CLI to enable local HTML reports.

### Updating the coverage baseline

`tests/.coverage-baseline.json` records the line-coverage numbers the project compares PRs against. It is a soft comparison, not an enforced gate.

Populate or update it after a green CI run:

1. Open the workflow run on GitHub and download the `coverage-clover` artifact from the PHP 8.3 job.
2. Read line coverage from the Clover XML — the `<metrics elements="..." coveredelements="..." />` attributes on the root `<project>` element.
3. Commit the updated `tests/.coverage-baseline.json` with `recorded_at`, `git_sha`, and `ci_run_url` filled in.

The placeholder file ships with `null` values for the first release of the harness; the first green `main` CI run after that is the one that sets the baseline.

## Where to put a new test

| Covers | Path | Harness | Suffix |
|---|---|---|---|
| Pure PHP in `src/` (no WP calls or fully stubbed) | `tests/phpunit/Unit/<Namespace>/` | BrainMonkey | `*Test.php` |
| PHP that needs WordPress booted (hooks, Options persistence, DB) | `tests/phpunit/Integration/<Namespace>/` | wp-phpunit | `*Test.php` |
| ES modules under `src/js/` | `tests/js/` | Vitest | `*.test.js` |

Use the existing starters as templates:

- Unit: [`tests/phpunit/Unit/Common/UtilTest.php`](phpunit/Unit/Common/UtilTest.php)
- Integration: [`tests/phpunit/Integration/Frontend/FrontendTest.php`](phpunit/Integration/Frontend/FrontendTest.php)
- JS: [`tests/js/dataLayer.test.js`](js/dataLayer.test.js)

Mirror the `src/` namespace/path in the test file's directory so tests are trivially discoverable from the production file.

## Debugging integration tests with Xdebug in Herd

1. Ensure Herd is using a PHP binary with Xdebug loaded (`herd php83 -v` should list Xdebug).
2. Configure your IDE to listen on port 9003 (PhpStorm default).
3. Run with the Xdebug trigger:

   ```bash
   XDEBUG_TRIGGER=1 WP_TESTS_DIR="$TMPDIR/wordpress-tests-lib-6.9" composer test:integration
   ```

4. For single-method debugging: `... --filter test_get_gtm_script_renders_container_id_and_datalayer_name`.

## Project notes (why this is set up the way it is)

### PHPUnit is pinned to 9.6

The `gtm-kit` plugin supports PHP 7.4. PHPUnit 10+ requires PHP 8.1 minimum. Bumping PHPUnit means dropping PHP 7.4 — so we stay on 9.6. This is the only deliberate divergence from `gtm-kit-app`, which runs on PHP 8.3+ and uses PHPUnit 12.5.

When the plugin's PHP floor eventually rises, bump PHPUnit in the same PR. Do not bump ahead of the PHP floor.

### Why no PHP 8.0 in the matrix

Plugin user telemetry shows under 4% of installs on PHP 8.0 and 92% on WP 6.9. The CI matrix is PHP 7.4, 8.1, 8.2, 8.3, 8.4 × WP 6.9 — five jobs instead of fifteen. PHP 8.0 is still permitted by `composer.json` (`^7.4`), it is just not actively tested. Do not add it back without fresh telemetry.

### Unit tests use BrainMonkey, not WP_Mock

BrainMonkey's lighter function-stubbing API (`Brain\Monkey\Functions\stubs()`) is a better fit for the mostly-pure utilities in `src/Common/` and `src/Options/`. WP_Mock is not a dependency.

### No Codecov

Local HTML reports and GitHub Actions job summaries are sufficient. No external coverage service is wired up. Revisit if the contributor workflow changes.
