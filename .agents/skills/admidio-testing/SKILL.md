---
name: admidio-testing
description: Write or run Admidio regression tests, especially database, permission, entity, CLI, or cross-engine tests.
---

# Regression tests

Read `tests/README.md`, `composer.json`, the relevant test base and
`.github/workflows/regression-tests.yml` before choosing commands or claiming CI parity. Start with
the smallest test observing the regression and broaden when justified. Documentation-only changes
need structural/link validation rather than database tests.

Use `tests/Unit/` for pure behavior, `tests/Integration/<Domain>/` for database/entity/service/rights,
`tests/Cli/` for CLI, `tests/Support/` for reusable helpers and `tests/fixtures/plugins/` for plugins.
Extend the existing harness: `DatabaseTestCase` rolls back each test, `AdministratorTestCase` adds
an administrator, `AdmidioTestFixture` creates real entities and `PermissionContext` sets actor,
organization and settings. Do not mock away the database behavior being tested. Restore modified
globals, hooks and static flags, including `Entity::$loggingEnabled`.

Subprocesses use separate connections and committed state only. Use `CliSubprocess` when that
boundary matters; it cannot see fixtures inside the parent's rollback transaction.

## Safe execution

The suite drops every table in its configured database. Inspect dedicated test configuration before
running it: the database name must contain `test`, and `TEST_FILES_PATH` must satisfy dedicated-tree
marker checks. Never use development/production data or overwrite an existing `.env.test`. Do not
assume documented services/extensions are available locally.

For initial setup, the README documents copying `.env.test.example` if no local config exists,
starting `docker-compose -f docker-compose.test.yml up -d`, and running
`php tests/bin/setup-test-env.php`. Check safety/configuration before setup or suite execution.
MySQL/MariaDB need `pdo_mysql`; PostgreSQL needs enabled `pdo_pgsql`. Engine selection uses
`TEST_DATABASE_ENGINE=postgres`, not a PHPUnit option. Report unavailable dependencies explicitly.

Scripts include `composer test:unit`, `composer test:integration`, `composer test:cli`,
`composer test:all`; individual files use `php vendor/bin/phpunit <path>`. Run `php -l` on touched PHP,
parse edited XML/JSON and run `git diff --check`. For dependency work, run
`composer validate --strict --no-check-publish`. Consult the current workflow before broader CI checks.
Report commands/results precisely, distinguishing syntax checks from runtime, browser, mail and
cross-engine coverage. Never describe an unexecuted check as passing.
