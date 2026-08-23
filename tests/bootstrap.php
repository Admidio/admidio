<?php
/**
 * PHPUnit Bootstrap File
 * Loads environment variables and initializes test framework
 */

// Unit tests must stay independent of the database and filesystem. DatabaseTestCase loads
// tests/bootstrap-admidio.php lazily when the first integration/CLI test actually needs it.
require dirname(__DIR__) . '/vendor/autoload.php';

/*
 * The Session entity resolves its id from session_id() and regenerates it when there is none.
 * The CLI SAPI starts no session on its own, and PHP refuses to start one once output has been
 * written, so this has to happen in the PHPUnit bootstrap and not in the lazily loaded
 * tests/bootstrap-admidio.php, which is required after PHPUnit has printed its header.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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

        if (!$dbName || !preg_match('/(?:^|[_-])test(?:[_-]|$)/i', $dbName)) {
            throw new RuntimeException(
                "Safety check failed: Database name '$dbName' does not contain 'test' as a separate token.\n"
                . "Refusing to run destructive tests on a database whose name could merely contain "
                . "the letters 'test'.\n"
                . "Use a dedicated name such as 'admidio_test'."
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
}
