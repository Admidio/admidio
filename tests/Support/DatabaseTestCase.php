<?php
/**
 * Base test case for database integration tests
 * Uses transaction isolation for fast, deterministic tests
 */

namespace Admidio\Tests\Support;

use Admidio\Infrastructure\Database;
use PDO;

abstract class DatabaseTestCase extends AdmidioTestCase
{
    /**
     * PDO connection for test database
     */
    protected static ?PDO $pdo = null;

    /**
     * Admidio Database wrapper
     */
    protected static ?Database $gDb = null;

    /**
     * Test data builder for creating fixtures
     */
    protected ?TestDataBuilder $testDataBuilder = null;

    /**
     * Set up test database connection (one-time for all tests)
     */
    public static function setUpBeforeClass(): void
    {
        if (self::$pdo === null) {
            self::$pdo = self::createDatabaseConnection();
            // Database wrapper creation requires Admidio's full bootstrap with $gLogger, etc.
            // For now, store PDO directly - to be completed after Phase 1
            // self::$gDb = self::createAdmidioDatabaseWrapper();
        }
    }

    /**
     * Set up each test with transaction isolation
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Integration tests require full Admidio bootstrap
        // For now, these tests are skipped - to be completed in Phase 2
        $this->markTestSkipped(
            'Integration tests with Database and Entity classes require Admidio bootstrap ' .
            '(including $gLogger, $gCurrentUser, etc.). ' .
            'These will be fully implemented in PR 2 after core patterns are established. ' .
            'For now, see tests/README.md for the integration test design and TestDataBuilder pattern.'
        );
    }

    /**
     * Tear down each test by rolling back the transaction
     */
    protected function tearDown(): void
    {
        try {
            // Rollback the outer transaction, reverting all changes made during the test
            self::$gDb->rollback();
        } catch (\Exception $e) {
            // Log rollback failure but don't fail the test
            error_log('Rollback failed: ' . $e->getMessage());
        }

        $this->testDataBuilder = null;
        parent::tearDown();
    }

    /**
     * Create PDO connection to test database
     */
    private static function createDatabaseConnection(): PDO
    {
        $config = \TestEnvironment::getTestDatabaseConfig();

        $dsn = self::buildDsn($config);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            return new PDO($dsn, $config['user'], $config['password'], $options);
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                "Failed to connect to test database.\n"
                . "Engine: {$config['engine']}\n"
                . "Host: {$config['host']}:{$config['port']}\n"
                . "Database: {$config['database']}\n"
                . "Error: " . $e->getMessage() . "\n\n"
                . "Start test databases with:\n"
                . "  docker-compose -f docker-compose.test.yml up -d"
            );
        }
    }

    /**
     * Build DSN string for database connection
     */
    private static function buildDsn(array $config): string
    {
        if ($config['engine'] === 'postgres') {
            return sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $config['host'],
                $config['port'],
                $config['database']
            );
        }

        // MySQL/MariaDB
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );
    }

    /**
     * Create Admidio Database wrapper instance
     */
    private static function createAdmidioDatabaseWrapper(): Database
    {
        $config = \TestEnvironment::getTestDatabaseConfig();

        // Initialize Admidio Database class with test connection parameters
        // Map test config keys to Database constructor parameter names
        $engine = $config['engine'];

        // Map config engine names to Database constants
        if ($engine === 'mariadb') {
            $engine = Database::DB_TYPE_MARIADB;
        } elseif ($engine === 'postgres') {
            $engine = Database::DB_TYPE_PGSQL;
        } else {
            $engine = Database::DB_TYPE_MYSQL;
        }

        try {
            $database = new Database(
                $engine,
                $config['host'],
                $config['port'],
                $config['database'],
                $config['user'],
                $config['password']
            );
            return $database;
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to create Database instance.\n"
                . "Engine: {$config['engine']}\n"
                . "Host: {$config['host']}:{$config['port']}\n"
                . "Database: {$config['database']}\n"
                . "Error: " . $e->getMessage()
            );
        }
    }

    /**
     * Get the test database connection
     */
    protected function getDatabase(): Database
    {
        return self::$gDb;
    }

    /**
     * Get the test data builder
     */
    protected function getTestDataBuilder(): TestDataBuilder
    {
        return $this->testDataBuilder;
    }

    /**
     * Create a fresh organization for testing
     */
    protected function createTestOrganization(string $name = 'TEST'): array
    {
        return $this->testDataBuilder->createOrganization($name);
    }

    /**
     * Create a test user
     */
    protected function createTestUser(string $login = 'testuser', string $email = 'test@example.local'): array
    {
        return $this->testDataBuilder->createUser($login, $email);
    }

    /**
     * Create a test role
     */
    protected function createTestRole(string $name = 'Members'): array
    {
        return $this->testDataBuilder->createRole($name);
    }
}
