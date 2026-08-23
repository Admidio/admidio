<?php
/**
 * Base test case for database integration tests
 * Uses transaction isolation for fast, deterministic tests
 */

namespace Admidio\Tests\Support;

use Admidio\Infrastructure\Database;

abstract class DatabaseTestCase extends AdmidioTestCase
{
    /**
     * Admidio Database wrapper
     */
    protected static ?Database $gDb = null;

    /**
     * Set up test database connection (one-time for all tests)
     */
    public static function setUpBeforeClass(): void
    {
        // Keep the global PHPUnit bootstrap DB-free. Only database-backed tests load the Admidio
        // integration bootstrap, so `composer test:unit` remains a true unit-test boundary.
        if (!defined('ADMIDIO_TEST_BOOTSTRAP_LOADED')) {
            require_once dirname(__DIR__) . '/env.php';
            admidioTestLoadEnvironment();
            \TestEnvironment::validateTestEnvironment();
            require dirname(__DIR__) . '/bootstrap-admidio.php';
        }

        if (isset($GLOBALS['gDb']) && $GLOBALS['gDb'] instanceof Database) {
            self::$gDb = $GLOBALS['gDb'];
        } else {
            // the bootstrap connects lazily so that the unit tests run without a database
            throw new \RuntimeException(
                $GLOBALS['gDbConnectionError']
                ?? 'Database not initialized. Ensure Admidio bootstrap is loaded via tests/bootstrap.php'
            );
        }

        // Initialize full Admidio installation if not already done
        static $initialized = false;
        if (!$initialized) {
            echo "\n  Setting up Admidio test database...\n";

            $dbConfig = self::getTestDatabaseConfig();
            TestDatabaseInitializer::initialize(self::$gDb, $dbConfig);

            $initialized = true;
            echo "\n";
        }
    }

    /**
     * Get test database configuration
     */
    protected static function getTestDatabaseConfig(): array
    {
        return admidioTestDatabaseConfig();
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

        parent::tearDown();
    }

    /**
     * Get the test database connection
     */
    protected function getDatabase(): Database
    {
        return self::$gDb;
    }
}
