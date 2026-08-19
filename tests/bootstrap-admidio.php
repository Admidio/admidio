<?php
/**
 * Admidio Bootstrap for Regression Tests
 *
 * Initializes Admidio's core infrastructure including:
 * - Database connection and wrapper
 * - Logger initialization
 * - Session and user context
 * - Configuration loading
 *
 * This bootstrap enables full integration testing with Admidio's
 * Entity and Service classes.
 */

// Prevent multiple loads
if (defined('ADMIDIO_TEST_BOOTSTRAP_LOADED')) {
    return;
}
define('ADMIDIO_TEST_BOOTSTRAP_LOADED', true);

// Get the Admidio root path
$admidioRoot = dirname(__DIR__);

// Load environment configuration
$envFile = $admidioRoot . '/.env.test';
if (!file_exists($envFile)) {
    throw new RuntimeException(
        "Test environment not configured.\n"
        . "Copy .env.test.example to .env.test and run: php tests/bin/setup-test-env.php"
    );
}

// Load environment variables
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

// Verify test environment
if (!getenv('TEST_DATABASE_ENGINE')) {
    throw new RuntimeException('TEST_DATABASE_ENGINE not set in .env.test');
}

// Load Admidio's autoloader
require_once $admidioRoot . '/vendor/autoload.php';

// Load system bootstrap functions (getExecutionTime, etc)
require_once $admidioRoot . '/system/bootstrap/function.php';

// Initialize Logger FIRST (minimal mock for testing)
// Database constructor calls $gLogger->debug(), so it must exist before DB init
$gLogger = new TestLogger();
$GLOBALS['gLogger'] = $gLogger;

// Initialize empty session/user for now
// Full user context will be set in individual tests as needed
$gCurrentUser = null;
$gCurrentSession = null;
$GLOBALS['gCurrentUser'] = $gCurrentUser;
$GLOBALS['gCurrentSession'] = $gCurrentSession;

// Now get database configuration and initialize
$dbConfig = getTestDatabaseConfig();

try {
    // Initialize Admidio Database class
    // Logger is now available globally for Database to use
    $gDb = createTestDatabase($dbConfig);
} catch (\Exception $e) {
    throw new RuntimeException(
        "Failed to initialize test database.\n"
        . "Ensure Docker services are running: docker-compose -f docker-compose.test.yml up -d\n"
        . "Error: " . $e->getMessage()
    );
}

// Make database available globally
$GLOBALS['gDb'] = $gDb;

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get test database configuration
 */
function getTestDatabaseConfig(): array
{
    $engine = getenv('TEST_DATABASE_ENGINE') ?: 'mariadb';
    $prefix = 'TEST_DB_' . strtoupper($engine);

    return [
        'engine' => $engine,
        'host' => getenv($prefix . '_HOST') ?: 'localhost',
        'port' => (int)(getenv($prefix . '_PORT') ?: ($engine === 'postgres' ? 5432 : 3306)),
        'user' => getenv($prefix . '_USER') ?: 'admidio',
        'password' => getenv($prefix . '_PASS') ?: '',
        'database' => getenv($prefix . '_NAME') ?: 'admidio_test',
    ];
}

/**
 * Create test database instance
 */
function createTestDatabase(array $config): \Admidio\Infrastructure\Database
{
    // Map config engine names to Database constants
    $engine = $config['engine'];
    if ($engine === 'mariadb') {
        $engine = \Admidio\Infrastructure\Database::DB_TYPE_MARIADB;
    } elseif ($engine === 'postgres') {
        $engine = \Admidio\Infrastructure\Database::DB_TYPE_PGSQL;
    } else {
        $engine = \Admidio\Infrastructure\Database::DB_TYPE_MYSQL;
    }

    return new \Admidio\Infrastructure\Database(
        $engine,
        $config['host'],
        $config['port'],
        $config['database'],
        $config['user'],
        $config['password']
    );
}

/**
 * Minimal logger for testing
 * Implements the methods Admidio Database needs
 */
class TestLogger
{
    /**
     * Log a debug message
     */
    public function debug(string $message, array $context = []): void
    {
        // Tests don't need logging output
    }

    /**
     * Log an info message
     */
    public function info(string $message, array $context = []): void
    {
        // Tests don't need logging output
    }

    /**
     * Log a warning
     */
    public function warning(string $message, array $context = []): void
    {
        // Tests don't need logging output
    }

    /**
     * Log an error
     */
    public function error(string $message, array $context = []): void
    {
        // Tests don't need logging output
    }
}
