#!/usr/bin/env php
<?php
/**
 * Initialize test database with Admidio schema
 *
 * Usage: php tests/bin/init-test-db.php
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap-admidio.php';

$dbConfig = getTestDatabaseConfig();

echo "Initializing test database...\n";
echo "Engine: {$dbConfig['engine']}\n";
echo "Host: {$dbConfig['host']}:{$dbConfig['port']}\n";
echo "Database: {$dbConfig['database']}\n\n";

try {
    $gDb = createTestDatabase($dbConfig);
    echo "✓ Database connection successful\n\n";

    // Load the database schema
    $schemaFile = dirname(__DIR__, 2) . '/install/db_scripts/db.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("Schema file not found: $schemaFile");
    }

    echo "Loading schema from $schemaFile...\n";
    $schemaContent = file_get_contents($schemaFile);

    // Replace %PREFIX% with actual prefix
    $schemaContent = str_replace('%PREFIX%', 'adm', $schemaContent);

    // Get PDO connection directly
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port'], $dbConfig['database']),
        $dbConfig['user'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]
    );

    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', preg_split('/;(?=\s*[\r\n])/m', $schemaContent)));
    $count = 0;
    foreach ($statements as $statement) {
        // Skip empty lines and comments
        if (empty($statement) || strpos(ltrim($statement), '/*') === 0 || strpos(ltrim($statement), '--') === 0) {
            continue;
        }

        // Add semicolon back if not present
        if (!str_ends_with($statement, ';')) {
            $statement .= ';';
        }

        if (@$pdo->exec($statement) !== false) {
            $count++;
            echo ".";
        } else {
            // Silently skip errors - some statements reference tables being dropped
            echo "W";
        }

        if ($count % 50 === 0) {
            echo " ($count)\n";
        }
    }
    echo "\n✓ Schema loaded with $count statements\n";

    echo "\n\n✓ Database initialized with $count statements\n";
    echo "✓ Test database is ready for testing\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
