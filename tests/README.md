# Admidio Regression Test Suite

Comprehensive automated testing for Admidio Core functionality.

## Quick Start

### Local Development (Docker)

```bash
# 1. Copy environment configuration
cp .env.test.example .env.test

# 2. Start test databases
docker-compose -f docker-compose.test.yml up -d

# 3. Setup test environment
php tests/bin/setup-test-env.php

# 4. Run tests
composer test:unit                      # Run unit tests
composer test:integration               # Run integration tests (MariaDB)
composer test:integration --db=postgres # Run against PostgreSQL
composer test:all                       # Run everything

# 5. Stop test databases when done
docker-compose -f docker-compose.test.yml down
```

### Without Docker

If you have existing MySQL/PostgreSQL servers:

1. Update `.env.test` with your database connection details
2. Run `php tests/bin/setup-test-env.php`
3. Run tests with `composer test:integration`

## Directory Structure

```
tests/
├── Unit/                    # Fast unit tests (no DB)
│   └── ExampleUnitTest.php
├── Integration/             # Database integration tests
│   └── ExampleIntegrationTest.php
├── Cli/                     # CLI regression tests
├── Support/                 # Test infrastructure
│   ├── AdmidioTestCase.php           # Base test class
│   ├── DatabaseTestCase.php          # DB test class with transaction isolation
│   ├── CliTestCase.php               # CLI test class
│   ├── TestDataBuilder.php           # Fixture builder
│   └── CliTestCase.php
├── Fixtures/                # Test data files
│   ├── documents/
│   ├── images/
│   ├── import/
│   └── mail/
├── bin/
│   └── setup-test-env.php   # Environment setup script
├── bootstrap.php            # PHPUnit bootstrap
└── README.md               # This file
```

## Test Organization

### Unit Tests
- **Purpose:** Fast validation of pure logic
- **Dependencies:** No database, filesystem, or network
- **Execution Time:** < 5 seconds
- **Location:** `tests/Unit/`

### Integration Tests
- **Purpose:** Entity/Service behavior with real database
- **Dependencies:** Real database connection (MariaDB, PostgreSQL, or MySQL)
- **Execution Time:** 20-30 minutes (parallelized)
- **Location:** `tests/Integration/`
- **Organization:** By domain (Users, Events, etc.)

### CLI Tests
- **Purpose:** Complete administrative workflows
- **Dependencies:** CLI infrastructure, database
- **Execution Time:** 10-15 minutes
- **Location:** `tests/Cli/`
- **Modes:** In-process (fast) and subprocess (realistic)

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

use Admidio\Tests\Support\DatabaseTestCase;

class MyIntegrationTest extends DatabaseTestCase
{
    public function testUserCreation(): void
    {
        $user = $this->createTestUser('testuser', 'test@example.com');
        
        $this->assertNotEmpty($user['usr_id']);
        $this->assertEquals('testuser', $user['usr_login']);
    }
}
```

## Custom Assertions

### `assertValidUuid($value, $message = '')`
Assert that a value is a valid UUID v4

### `assertValidTimestamp($value, $message = '')`
Assert that a value is a valid timestamp

### `assertArrayHasKeys($keys, $array, $message = '')`
Assert that array has specific keys

### `assertCliSuccess($result, $message = '')`
Assert that a CLI command succeeded

### `assertCliFails($result, $expectedExitCode = 1, $message = '')`
Assert that a CLI command failed

## Test Data Builder

The `TestDataBuilder` class creates test fixtures through production APIs:

```php
$builder = $this->getTestDataBuilder();

// Create fixtures
$org = $builder->createOrganization('Test Org');
$user = $builder->createUser('testuser', 'test@example.com');
$role = $builder->createRole('Members');
$category = $builder->createCategory('Events', 'EVENTS');

// Assign membership
$membership = $builder->assignUserToRole($user, $role);

// Retrieve fixtures
$org = $builder->getOrganization();      // First created
$orgs = $builder->getOrganizations();    // All created
```

## Database Configuration

### Environment Variables

Set in `.env.test`:

- `TEST_DATABASE_ENGINE` - Primary engine (mariadb, mysql, postgres)
- `TEST_DB_MARIADB_HOST`, `TEST_DB_MARIADB_PORT`, `TEST_DB_MARIADB_USER`, `TEST_DB_MARIADB_PASS`, `TEST_DB_MARIADB_NAME`
- `TEST_DB_POSTGRES_HOST`, `TEST_DB_POSTGRES_PORT`, `TEST_DB_POSTGRES_USER`, `TEST_DB_POSTGRES_PASS`, `TEST_DB_POSTGRES_NAME`
- `TEST_DB_MYSQL_HOST`, `TEST_DB_MYSQL_PORT`, `TEST_DB_MYSQL_USER`, `TEST_DB_MYSQL_PASS`, `TEST_DB_MYSQL_NAME`
- `TEST_FILES_PATH` - Test files directory (must contain "test")
- `TEST_FIXTURES_PATH` - Fixtures directory

### Transaction Isolation

Integration tests use transaction-based isolation for speed:

```php
setUp()    // Start outer transaction
  ...test runs...
  Service may start nested transactions
tearDown() // Rollback outer transaction (reverts all changes)
```

**Benefits:**
- No database recreation per test
- Tests run 10-100x faster
- Prevents data leakage between tests
- Nested transactions supported

## Running Tests

### Commands

```bash
# Run unit tests only
composer test:unit

# Run integration tests (MariaDB by default)
composer test:integration

# Run against specific database
composer test:integration --db=postgres
composer test:integration --db=mysql

# Run CLI tests
composer test:cli

# Run everything
composer test:all

# Run with coverage
composer test:coverage

# Setup environment
composer test:setup
```

### GitHub Actions

Tests run automatically on:
- Every PR against `v4.3`
- Every push to `master`
- Scheduled weekly for MySQL

Workflows:
- `fast-checks` - PHP syntax, CS-Fixer, unit tests (every PR)
- `mariadb` - MariaDB integration + CLI (every PR)
- `postgres` - PostgreSQL integration (every PR)
- `mysql` - MySQL integration (scheduled)

## CI/CD Database Matrix

| Database | Version | Trigger | Purpose |
|----------|---------|---------|---------|
| MariaDB | 10.6 | Every PR | Primary testing |
| PostgreSQL | 15 | Every PR | Cross-database validation |
| MySQL | 8.0 | Scheduled | Third engine compatibility |

## Troubleshooting

### Database Connection Failed

**Error:** "Cannot connect to test database"

**Solution:**
```bash
# Verify containers are running
docker-compose -f docker-compose.test.yml ps

# Check logs
docker-compose -f docker-compose.test.yml logs mariadb

# Restart
docker-compose -f docker-compose.test.yml restart
```

### Permission Denied on Test Files

**Error:** "Permission denied" when writing test files

**Solution:**
```bash
# Ensure test directory exists and is writable
mkdir -p adm_my_files_test
chmod 777 adm_my_files_test
```

### Tests Hanging

**Likely cause:** Database connection not isolated properly

**Solution:**
- Check database is responding: `docker-compose -f docker-compose.test.yml ps`
- Verify connection settings in `.env.test`
- Run `php tests/bin/setup-test-env.php` again

## Performance

**Typical Execution Times (Parallelized):**
- Unit tests: < 5 seconds
- Integration tests: 20-30 minutes
- CLI tests: 10-15 minutes
- **Total:** 45-60 minutes

**Tips for Speed:**
- Run unit tests first (`composer test:unit`)
- Run integration tests in parallel on CI
- Use transaction isolation (not fresh DB per test)
- Run slow CLI tests last

## Next Steps

After PR 1 (Infrastructure):

1. **PR 2 (Foundation)** - Database, Entity, User, Role tests
2. **PR 3 (Services)** - Event, Message, Photo, Document services
3. **PR 4 (CLI Regression)** - Complete workflow scenarios
4. **PR 5 (Lifecycle)** - Installation and upgrade tests
5. **PR 6 (Polish)** - Optimization and documentation

## References

- [PHPUnit Documentation](https://phpunit.de/)
- [Admidio Architecture](https://www.admidio.org/)
- [Docker Compose](https://docs.docker.com/compose/)
- [GitHub Actions](https://docs.github.com/en/actions)

## Getting Help

- Check `phpunit.xml` for configuration
- Review example tests in `tests/Unit/` and `tests/Integration/`
- See `tests/Support/` for available test case classes
- Check this README for common issues
