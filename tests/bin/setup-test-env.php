#!/usr/bin/env php
<?php
/**
 * Admidio Regression Test Suite - Setup Script
 * Initializes test environment and verifies connectivity
 *
 * The engine is taken from TEST_DATABASE_ENGINE and can be overridden for this run:
 *
 * Usage: php tests/bin/setup-test-env.php [--db=mariadb|postgres|mysql]
 */

define('ADMIDIO_ROOT', dirname(__DIR__, 2));

require_once dirname(__DIR__) . '/env.php';

echo "Admidio Regression Test Suite - Setup\n";
echo "=====================================\n\n";

// Step 1: Load configuration from the environment and .env.test
echo "Step 1: Loading environment configuration...\n";
try {
    admidioTestLoadEnvironment();
} catch (RuntimeException $exception) {
    echo '✗ ' . $exception->getMessage() . "\n";
    exit(1);
}
echo '✓ Environment loaded' . (is_file(ADMIDIO_ROOT . '/.env.test') ? " (.env.test and process environment)\n\n" : " (process environment)\n\n");

// Determine the database engine, the command line wins over the environment
$database = admidioTestEnv('TEST_DATABASE_ENGINE', 'mariadb');
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--db=')) {
        $database = substr($argument, 5);
    }
}

if (!in_array($database, ['mariadb', 'mysql', 'postgres'], true)) {
    echo "Error: Invalid database engine: $database\n";
    echo "Supported: mariadb, mysql, postgres\n";
    exit(1);
}

// Step 2: Create test directories
echo "Step 2: Creating test directories...\n";
createTestDirectories();
echo "✓ Test directories created\n\n";

// Step 3: Verify database connection
echo "Step 3: Verifying database connection ($database)...\n";
if (verifyDatabaseConnection($database) === false) {
    echo "✗ Database connection failed\n";
    echo "Make sure the database is running:\n";
    echo "  docker-compose -f docker-compose.test.yml up -d\n";
    exit(1);
}
echo "✓ Database connection successful\n\n";

// Step 4: Verify mail connection
echo "Step 4: Checking mail (Mailpit) connectivity...\n";
if (verifyMailConnection()) {
    echo "✓ Mail server reachable\n";
} else {
    echo "✗ Mail server not reachable\n";
    echo "  The integration suite contains a real Mailpit delivery test.\n";
    exit(1);
}
echo "\n";

// Step 5: Verify the source-controlled filesystem safety marker
echo "Step 5: Verifying test environment marker...\n";
verifyTestMarker();
echo "✓ Test marker verified\n\n";

// Success message
echo "Setup Complete!\n";
echo "===============\n\n";
echo "You can now run tests:\n";
echo "  composer test:unit              # Run unit tests\n";
echo "  composer test:integration       # Run integration tests ($database)\n";
echo "  composer test:cli               # Run command line tests\n";
echo "\nTo test another engine, set TEST_DATABASE_ENGINE and run this script again.\n";
echo "\nTo stop test services:\n";
echo "  docker-compose -f docker-compose.test.yml down\n";

exit(0);

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Create test directories with safety checks
 */
function createTestDirectories(): void
{
    $testFilesPath = admidioTestEnv('TEST_FILES_PATH', './tests/adm_my_files');

    // Safety check: path must contain 'test'
    if (stripos($testFilesPath, 'test') === false) {
        throw new RuntimeException(
            "Safety check failed: TEST_FILES_PATH must contain 'test'.\n"
            . "Current: $testFilesPath"
        );
    }

    // Create directory
    if (!is_dir($testFilesPath)) {
        if (!mkdir($testFilesPath, 0777, true)) {
            throw new RuntimeException("Failed to create test files directory: $testFilesPath");
        }
    }

    // Create subdirectories
    $subdirs = [
        'documents',
        'documents_test',
        'photos',
        'temp',
        'logs',
        'import',
        'export',
    ];

    foreach ($subdirs as $subdir) {
        $path = $testFilesPath . '/' . $subdir;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}

/**
 * Verify database connection
 *
 * A service container answers the port before it accepts connections, so the connection is
 * retried until the server is really up.
 */
function verifyDatabaseConnection(string $database): PDO|false
{
    $config = admidioTestDatabaseConfig($database);

    // Safety check: database name must contain 'test'
    if (stripos((string) $config['database'], 'test') === false) {
        echo "✗ Safety check failed: Database name must contain 'test'\n";
        echo "  Current: {$config['database']}\n";
        exit(1);
    }

    if ($database === 'postgres') {
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    } else {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    }

    $lastError = '';

    for ($attempt = 1; $attempt <= 30; ++$attempt) {
        try {
            $pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Test connection
            $pdo->query('SELECT 1');

            return $pdo;
        } catch (PDOException $exception) {
            $lastError = $exception->getMessage();

            if ($attempt === 1) {
                echo "  Waiting for the database to accept connections...\n";
            }

            sleep(2);
        }
    }

    echo "✗ Connection Error:\n";
    echo "  Engine: $database\n";
    echo "  Host: {$config['host']}:{$config['port']}\n";
    echo "  Database: {$config['database']}\n";
    echo "  User: {$config['user']}\n";
    echo "  Error: $lastError\n";

    return false;
}

/**
 * Verify mail server connectivity
 */
function verifyMailConnection(): bool
{
    $host = admidioTestEnv('TEST_MAIL_HOST', '127.0.0.1');
    $port = (int) admidioTestEnv('TEST_MAIL_PORT', '1025');

    $socket = @fsockopen($host, $port, $errno, $errstr, 2);
    if ($socket) {
        fclose($socket);
        return true;
    }
    return false;
}

/**
 * Verify the source-controlled test environment marker.
 *
 * The marker is deliberately not created here. Filesystem regression tests only perform
 * destructive cleanup when the checkout itself contains this marker.
 */
function verifyTestMarker(): void
{
    $testFilesPath = admidioTestEnv('TEST_FILES_PATH', './tests/adm_my_files');
    $markerFile = $testFilesPath . '/.admidio-regression-test';
    if (!is_file($markerFile)) {
        throw new RuntimeException(
            'Safety check failed: filesystem regression test marker is missing: ' . $markerFile
        );
    }
}
