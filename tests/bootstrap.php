<?php
/**
 * PHPUnit Bootstrap File
 * Loads environment variables and initializes test framework
 */

require_once __DIR__ . '/env.php';

// .env.test or the process environment configures which database the run uses
admidioTestLoadEnvironment();

// Load Admidio autoloader
require dirname(__DIR__) . '/vendor/autoload.php';

// Ensure test markers exist (safety against destructive tests)
TestEnvironment::validateTestEnvironment();

// Load Admidio bootstrap for integration tests (initializes $gDb, $gLogger, etc.)
require __DIR__ . '/bootstrap-admidio.php';

/**
 * Test environment validation
 */
class TestEnvironment
{
    public static function validateTestEnvironment(): void
    {
        // Check 1: Database name must contain "test"
        $dbEngine = getenv('TEST_DATABASE_ENGINE');
        $dbNameEnv = 'TEST_DB_' . strtoupper($dbEngine) . '_NAME';
        $dbName = getenv($dbNameEnv);

        if (!$dbName || stripos($dbName, 'test') === false) {
            throw new RuntimeException(
                "Safety check failed: Database name '$dbName' does not contain 'test'.\n"
                . "Refusing to run destructive tests on non-test database.\n"
                . "Configure TEST_DB_*_NAME to include 'test' in the name."
            );
        }

        // Check 2: Test files path must exist and contain 'test'
        $testFilesPath = getenv('TEST_FILES_PATH');
        if (!$testFilesPath || stripos($testFilesPath, 'test') === false) {
            throw new RuntimeException(
                "Safety check failed: TEST_FILES_PATH must contain 'test' in the path.\n"
                . "Refusing to run destructive tests on non-test directory.\n"
                . "Current: $testFilesPath"
            );
        }

        if (!is_dir($testFilesPath)) {
            mkdir($testFilesPath, 0777, true);
        }

        // Check 3: Create test marker file
        $markerFile = $testFilesPath . '/.test-environment-marker';
        if (!file_exists($markerFile)) {
            touch($markerFile);
        }
    }

    public static function getTestDatabaseConfig(): array
    {
        return admidioTestDatabaseConfig();
    }

    public static function verifyDatabaseConnection(): bool
    {
        $config = self::getTestDatabaseConfig();

        try {
            if ($config['engine'] === 'postgres') {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    $config['host'],
                    $config['port'],
                    $config['database']
                );
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s',
                    $config['host'],
                    $config['port'],
                    $config['database']
                );
            }

            $pdo = new PDO($dsn, $config['user'], $config['password']);
            $pdo = null;
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
