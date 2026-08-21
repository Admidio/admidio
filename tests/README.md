# Admidio Regression Test Suite

Automated regression tests for Admidio core functionality.

Every integration test writes to and reads from a real database through the Admidio entities and
services. There are no mocks and no fake database: the suite installs Admidio from scratch through
the same `Installation` service the web installer uses, and each test then runs inside a transaction
that is rolled back afterwards.

## Quick Start

### Local Development (Docker)

```bash
# 1. Copy environment configuration
cp .env.test.example .env.test

# 2. Start test databases
docker-compose -f docker-compose.test.yml up -d

# 3. Setup test environment (creates the directories, waits for the database)
php tests/bin/setup-test-env.php

# 4. Run tests
composer test:unit          # unit tests, no database needed
composer test:integration   # integration tests
composer test:cli           # command line tests
composer test:all           # everything

# 5. Stop test databases when done
docker-compose -f docker-compose.test.yml down
```

### Without Docker

If you have an existing MySQL/MariaDB or PostgreSQL server:

1. Create an empty database whose name contains `test` and a user that may create tables in it
2. Put the connection settings into `.env.test`
3. Run `php tests/bin/setup-test-env.php`
4. Run the tests with the `composer test:*` commands above

The suite **drops every table** in that database before it installs Admidio into it. It refuses to
start unless the database name and `TEST_FILES_PATH` both contain `test`.

## What the suite contains

391 tests in 45 files:

| Suite | Files | Tests | Needs a database |
|-------|-------|-------|------------------|
| `tests/Unit` | 1 | 8 | no |
| `tests/Integration` | 40 | 351 | yes |
| `tests/Cli` | 4 | 32 | yes |

```
tests/
├── Unit/                    # Fast unit tests (no DB)
├── Integration/             # Database integration tests, one directory per domain (22 of them)
├── Cli/                     # Command line and installation tests
├── Support/                 # Test infrastructure
│   ├── AdmidioTestCase.php           # Base test class and the custom assertions
│   ├── DatabaseTestCase.php          # DB test class with transaction isolation
│   ├── TestDatabaseInitializer.php   # Installs Admidio once per PHPUnit process
│   ├── AdmidioTestFixture.php        # Fixture builder that writes through the entities
│   └── PermissionContext.php         # Sets the globals rights are resolved against
├── bin/
│   └── setup-test-env.php   # Environment setup and connectivity check
├── env.php                  # Reads .env.test and the process environment
├── bootstrap.php            # PHPUnit bootstrap
├── bootstrap-admidio.php    # Constants, globals and database connection Admidio needs
└── README.md                # This file
```

## Test Organization

### Unit Tests
- **Purpose:** Fast validation of pure logic
- **Dependencies:** none, they run without a database
- **Location:** `tests/Unit/`

### Integration Tests
- **Purpose:** Entity and service behaviour against a real database
- **Dependencies:** a database, installed by the suite itself
- **Location:** `tests/Integration/`, organized by domain (Users, Events, Roles, ...)

### CLI Tests
- **Purpose:** The command line infrastructure and the result of a headless installation
- **Dependencies:** a database
- **Location:** `tests/Cli/`
- They exercise `CliApplication`, `CliTaskRegistry` and `MaintenanceMode` **in process**. There is
  no subprocess test: `system/bootstrap/cli.php` reads `adm_my_files/config.php` unconditionally, so
  `./admidio` cannot be pointed at the test database.

## How a test runs

```
PHPUnit starts
  tests/bootstrap.php        -> environment, autoloader, safety checks
  tests/bootstrap-admidio.php-> constants, globals, database connection
  first DatabaseTestCase     -> TestDatabaseInitializer drops every table and installs Admidio

each test
  setUp()                    -> start a transaction
  ...the test runs, services may open nested transactions...
  tearDown()                 -> roll the transaction back
```

Nothing a test writes survives it, so the tests do not depend on each other and the database is
installed once per process rather than once per test.

The bootstrap sets `$gDebug`, so PDO runs in `ERRMODE_EXCEPTION` and a failing statement raises
an Admidio exception with the SQL error instead of being swallowed. Without it PostgreSQL reports
a rejected statement only through the return value of `execute()`, which `Database::queryPrepared()`
passes on as `false` without a log entry, and a test then sees a record that was never written.

Two things to know when writing a test:

- `Admidio\Infrastructure\Exception::__construct()` calls `$gDb->rollback()`, which unwinds the
  transaction stack to depth 0 and with it the transaction that isolates the test. A test that
  expects an Admidio exception should assert the message and read nothing further from the database.
- `Entity::$loggingEnabled` is static and `Session::__construct()` switches it off for the whole
  process. A test that needs the changelog has to call `Entity::setLoggingEnabled(true)` in its
  `setUp()` and restore the previous value in `tearDown()`.

## Writing Tests

### Example Unit Test

```php
<?php
namespace Admidio\Tests\Unit;

use Admidio\Tests\Support\AdmidioTestCase;

class MyTest extends AdmidioTestCase
{
    public function testSomething(): void
    {
        $result = someFunctionToTest();
        $this->assertEquals('expected', $result);
    }
}
```

### Example Integration Test

```php
<?php
namespace Admidio\Tests\Integration;

use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;

class MyIntegrationTest extends DatabaseTestCase
{
    public function testUserCreation(): void
    {
        $fixture = new AdmidioTestFixture($this->getDatabase());
        $user = $fixture->createAndSaveUser('testuser', 'test@example.local');

        $this->assertNotEmpty($user['usr_id']);
    }
}
```

## Custom Assertions

`AdmidioTestCase` adds three assertions to the PHPUnit ones:

- `assertValidUuid($value, $message = '')` - the value is a UUID
- `assertValidTimestamp($value, $message = '')` - the value is a timestamp
- `assertArrayHasKeys($keys, $array, $message = '')` - the array has all of these keys

## Test Fixture

`AdmidioTestFixture` creates records through the Admidio entities, so a fixture goes through the
same code the application uses and really is in the database:

```php
$fixture = new AdmidioTestFixture($this->getDatabase());

$org = $fixture->createAndSaveOrganization('Test Org', 'testorg');
$user = $fixture->createAndSaveUser('testuser', 'test@example.local');
$role = $fixture->createAndSaveRoleWithRights('Members', $org['org_id'], ['rol_announcements' => 1]);
$fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
```

The `PermissionContext` trait sets the globals Admidio resolves rights against:

```php
$member = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);
$this->withCurrentUser($member, $org['org_id'], true, function () {
    // $gCurrentUser, $gCurrentOrgId and $gSettingsManager are set in here
});
```

## Configuration

The run is configured through environment variables. `tests/env.php` reads `.env.test` into the
process environment, but **a variable that is already set wins over the file**, so a CI job
configures a run through its own environment and needs no `.env.test` at all.

These are the variables the suite reads:

| Variable | Meaning |
|----------|---------|
| `TEST_DATABASE_ENGINE` | `mariadb`, `mysql` or `postgres` - selects which of the blocks below is used |
| `TEST_DB_<ENGINE>_HOST` | host, use `127.0.0.1` rather than `localhost` for MySQL |
| `TEST_DB_<ENGINE>_PORT` | port |
| `TEST_DB_<ENGINE>_USER` | user |
| `TEST_DB_<ENGINE>_PASS` | password |
| `TEST_DB_<ENGINE>_NAME` | database, must contain `test` |
| `TEST_FILES_PATH` | the adm_my_files of the test run, Admidio's `FOLDER_DATA` points at it. Inside the checkout, path must contain `test` |
| `TEST_MAIL_HOST`, `TEST_MAIL_PORT` | only used by the setup script to report whether Mailpit answers |

`<ENGINE>` is `MARIADB`, `MYSQL` or `POSTGRES`.

### Choosing the database engine

The engine is part of the environment, not a command line option:

```bash
# in .env.test
TEST_DATABASE_ENGINE=postgres

# or for one run
TEST_DATABASE_ENGINE=postgres composer test:integration
```

PostgreSQL needs the `pdo_pgsql` extension, MySQL and MariaDB need `pdo_mysql`.

## Running Tests

```bash
composer test:unit          # unit tests only
composer test:integration   # integration tests
composer test:cli           # command line tests
composer test:all           # all three suites in one process
composer test:coverage      # HTML coverage report in tests/reports/coverage (needs Xdebug)
composer test:setup         # tests/bin/setup-test-env.php
```

### GitHub Actions

`.github/workflows/regression-tests.yml` runs on every pull request against `v4.3`, on every push
to `v4.3`, weekly for MySQL, and on demand:

| Job | Runs | Contents |
|-----|------|----------|
| `fast-checks` | every run | PHP syntax of `src`, `system`, `modules`, `install`, `tests`; `composer validate`; unit tests |
| `mariadb` | every run | MariaDB 10.11, integration and CLI tests |
| `postgres` | every run | PostgreSQL 15, integration and CLI tests |
| `mysql` | weekly and on demand | MySQL 8.0, integration and CLI tests |

The database jobs get their configuration from the `env:` block of the job, so nothing copies or
edits `.env.test` on the runner.

## Differences between the engines

The suite runs on MariaDB and PostgreSQL. Three tests behave differently or are skipped on
PostgreSQL, each of them because of a defect in Admidio rather than in the test:

- `UserRelationWorkflowTest::testATypeWithoutACounterpartIsUnidirectional` and
  `::testARelationOfAUnidirectionalTypeHasNoCounterpart` are skipped: the installation writes
  `urt_id` 1 to 8 by hand and never advances the PostgreSQL sequence, so the application cannot
  create a relation type until the sequence has passed 8.
- `DatabaseAbstractionTest::testDatabaseReportsWhichEngineItRunsOn` skips its `tableExists()`
  assertions: the method compares `information_schema.table_schema` with the database name, which on
  PostgreSQL is the schema and never matches.
- `DatabaseAbstractionTest::testCaseSensitivityOfTextComparisonDependsOnTheEngine` asserts both
  behaviours: MySQL compares text without regard to case, PostgreSQL byte by byte.

A fresh MySQL or MariaDB installation is also missing the two forum tables, because their
definitions in `install/db_scripts/db.sql` end with the PostgreSQL clause `ENCODING 'UTF8'`.
`InstallationResultTest::testForumTablesAreMissingAfterAFreshInstallation` pins that down and fails
once it is fixed, which is the point.

## Performance

Measured on one developer machine (PHP 8.4, databases in Docker), whole suite, 391 tests:

- MariaDB 10.11: about 7 minutes
- PostgreSQL 15: about 1 minute
- Unit tests alone: under a second

Most of the time goes into the installation at the start of the process and into the entities, not
into PHPUnit.

## Troubleshooting

### Database connection failed

```bash
# Verify containers are running
docker-compose -f docker-compose.test.yml ps

# Check logs
docker-compose -f docker-compose.test.yml logs mariadb

# Restart
docker-compose -f docker-compose.test.yml restart
```

A MySQL client reads `localhost` as "connect through the unix socket". Use `127.0.0.1` for a
database that is published on a port.

### Permission denied on test files

```bash
mkdir -p tests/adm_my_files
chmod 777 tests/adm_my_files
```

### The unit tests fail at the bootstrap

`TEST_DATABASE_ENGINE` and `TEST_FILES_PATH` have to be set even for the unit tests, because the
bootstrap checks that it is looking at a test environment. The unit tests themselves need no
database: if the connection fails, the bootstrap remembers the error and only reports it when a test
asks for the database.

## References

- [PHPUnit Documentation](https://phpunit.de/)
- [Docker Compose](https://docs.docker.com/compose/)
- [GitHub Actions](https://docs.github.com/en/actions)
