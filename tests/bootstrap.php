<?php
/**
 * PHPUnit Bootstrap File
 * Loads environment variables and initializes test framework
 */

use Admidio\Infrastructure\Database;

// Load environment variables from .env.test
$envFile = dirname(__DIR__) . '/.env.test';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
} else {
    throw new RuntimeException(
        "Test environment not configured.\n"
        . "Run: php tests/bin/setup-test-env.php\n"
        . "Or copy: cp .env.test.example .env.test"
    );
}

// Verify test environment is properly configured
if (!getenv('TEST_DATABASE_ENGINE')) {
    throw new RuntimeException('TEST_DATABASE_ENGINE not set in .env.test');
}

if (!getenv('TEST_FILES_PATH')) {
    throw new RuntimeException('TEST_FILES_PATH not set in .env.test');
}

// Load Admidio autoloader
require dirname(__DIR__) . '/vendor/autoload.php';

// Ensure test markers exist (safety against destructive tests)
TestEnvironment::validateTestEnvironment();

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
        $engine = getenv('TEST_DATABASE_ENGINE') ?: 'mariadb';
        $prefix = 'TEST_DB_' . strtoupper($engine);

        return [
            'engine' => $engine,
            'host' => getenv($prefix . '_HOST') ?: 'localhost',
            'port' => getenv($prefix . '_PORT') ?: ($engine === 'postgres' ? 5432 : 3306),
            'user' => getenv($prefix . '_USER') ?: 'admidio',
            'password' => getenv($prefix . '_PASS') ?: '',
            'database' => getenv($prefix . '_NAME') ?: 'admidio_test',
        ];
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
