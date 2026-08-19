#!/usr/bin/env php
<?php
/**
 * Admidio Regression Test Suite - Setup Script
 * Initializes test environment and verifies connectivity
 *
 * Usage: php tests/bin/setup-test-env.php [--db=mariadb|postgres|mysql]
 */

use PDO;

define('ADMIDIO_ROOT', dirname(__DIR__, 2));

// Parse arguments
$database = 'mariadb';
foreach ($argv as $arg) {
    if (strpos($arg, '--db=') === 0) {
        $database = substr($arg, 5);
    }
}

if (!in_array($database, ['mariadb', 'mysql', 'postgres'], true)) {
    echo "Error: Invalid database engine: $database\n";
    echo "Supported: mariadb, mysql, postgres\n";
    exit(1);
}

echo "Admidio Regression Test Suite - Setup\n";
echo "=====================================\n\n";

// Step 1: Check .env.test exists
echo "Step 1: Checking .env.test configuration...\n";
$envFile = ADMIDIO_ROOT . '/.env.test';
if (!file_exists($envFile)) {
    echo "✗ .env.test not found\n";
    echo "Please create it by running:\n";
    echo "  cp .env.test.example .env.test\n";
    exit(1);
}
echo "✓ .env.test found\n\n";

// Step 2: Load environment
echo "Step 2: Loading environment configuration...\n";
$env = loadEnv($envFile);
echo "✓ Environment loaded\n\n";

// Step 3: Create test directories
echo "Step 3: Creating test directories...\n";
createTestDirectories($env);
echo "✓ Test directories created\n\n";

// Step 4: Verify database connection
echo "Step 4: Verifying database connection ($database)...\n";
$pdo = verifyDatabaseConnection($database, $env);
if ($pdo === false) {
    echo "✗ Database connection failed\n";
    echo "Make sure Docker containers are running:\n";
    echo "  docker-compose -f docker-compose.test.yml up -d\n";
    exit(1);
}
echo "✓ Database connection successful\n\n";

// Step 5: Verify mail connection (optional)
echo "Step 5: Checking mail (Mailpit) connectivity...\n";
if (verifyMailConnection($env)) {
    echo "✓ Mail server reachable\n";
} else {
    echo "⚠ Mail server not reachable (optional, continues)\n";
}
echo "\n";

// Step 6: Create test marker file
echo "Step 6: Creating test environment marker...\n";
createTestMarker($env);
echo "✓ Test marker created\n\n";

// Success message
echo "Setup Complete!\n";
echo "===============\n\n";
echo "You can now run tests:\n";
echo "  composer test:unit              # Run unit tests\n";
echo "  composer test:integration       # Run integration tests ($database)\n";
echo "  composer test:integration --db=postgres  # Run against PostgreSQL\n";
echo "\nTo stop test services:\n";
echo "  docker-compose -f docker-compose.test.yml down\n";

exit(0);

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Load environment variables from .env.test file
 */
function loadEnv(string $envFile): array
{
    $env = [];
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
        $env[$name] = $value;
    }

    return $env;
}

/**
 * Create test directories with safety checks
 */
function createTestDirectories(array $env): void
{
    $testFilesPath = $env['TEST_FILES_PATH'] ?? './adm_my_files_test';

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
 */
function verifyDatabaseConnection(string $database, array $env): PDO|false
{
    $prefix = 'TEST_DB_' . strtoupper($database);
    $host = $env[$prefix . '_HOST'] ?? 'localhost';
    $port = $env[$prefix . '_PORT'] ?? ($database === 'postgres' ? 5432 : 3306);
    $user = $env[$prefix . '_USER'] ?? 'admidio';
    $pass = $env[$prefix . '_PASS'] ?? '';
    $dbname = $env[$prefix . '_NAME'] ?? 'admidio_test';

    // Safety check: database name must contain 'test'
    if (stripos($dbname, 'test') === false) {
        echo "✗ Safety check failed: Database name must contain 'test'\n";
        echo "  Current: $dbname\n";
        exit(1);
    }

    try {
        if ($database === 'postgres') {
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        } else {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        }

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Test connection
        $pdo->query('SELECT 1');

        return $pdo;
    } catch (PDOException $e) {
        echo "✗ Connection Error:\n";
        echo "  Engine: $database\n";
        echo "  Host: $host:$port\n";
        echo "  Database: $dbname\n";
        echo "  Error: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Verify mail server connectivity
 */
function verifyMailConnection(array $env): bool
{
    $host = $env['TEST_MAIL_HOST'] ?? 'localhost';
    $port = $env['TEST_MAIL_PORT'] ?? 1025;

    $socket = @fsockopen($host, $port, $errno, $errstr, 2);
    if ($socket) {
        fclose($socket);
        return true;
    }
    return false;
}

/**
 * Create test environment marker file
 */
function createTestMarker(array $env): void
{
    $testFilesPath = $env['TEST_FILES_PATH'] ?? './adm_my_files_test';
    $markerFile = $testFilesPath . '/.test-environment-marker';

    if (!file_exists($markerFile)) {
        file_put_contents($markerFile, 'This directory contains test data and can be safely deleted.' . PHP_EOL);
    }
}
