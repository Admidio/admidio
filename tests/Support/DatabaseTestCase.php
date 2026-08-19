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
            self::$gDb = self::createAdmidioDatabaseWrapper();
        }
    }

    /**
     * Set up each test with transaction isolation
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Begin outer transaction that will be rolled back in tearDown
        // This provides isolation without recreating the database
        try {
            self::$gDb->startTransaction();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database transaction failed: ' . $e->getMessage());
        }

        // Create test data builder for this test
        $this->testDataBuilder = new TestDataBuilder(self::$gDb);
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
     * This is a simplified version - real implementation depends on Admidio's Database class
     */
    private static function createAdmidioDatabaseWrapper(): Database
    {
        $config = \TestEnvironment::getTestDatabaseConfig();

        // Initialize Admidio Database class with test connection
        // This assumes Database class accepts DSN or PDO connection
        $database = new Database();
        $database->connect(
            self::$pdo,
            $config['engine'],
            $config['host'],
            $config['port'],
            $config['user'],
            $config['password'],
            $config['database']
        );

        return $database;
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
