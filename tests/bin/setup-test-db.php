#!/usr/bin/env php
<?php
/**
 * Initialize test database using Admidio's Installation service
 *
 * This script uses the actual Installation service to properly initialize
 * the test database with schema, default data, and admin user.
 *
 * Usage: php tests/bin/setup-test-db.php
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap-admidio.php';

use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;

$dbConfig = getTestDatabaseConfig();

echo "═══════════════════════════════════════════════════════════\n";
echo "  Admidio Test Database Setup\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Engine: {$dbConfig['engine']}\n";
echo "Host: {$dbConfig['host']}:{$dbConfig['port']}\n";
echo "Database: {$dbConfig['database']}\n";
echo "User: {$dbConfig['user']}\n\n";

try {
    $gDb = createTestDatabase($dbConfig);
    echo "✓ Database connection established\n\n";

    // Check if database is already initialized
    $checkSql = 'SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?';
    $result = $gDb->queryPrepared($checkSql, [$dbConfig['database']]);
    $row = $result->fetch();
    $existingTables = (int) ($row['cnt'] ?? 0);

    if ($existingTables > 0) {
        echo "⚠ Warning: Database already has $existingTables tables\n";
        echo "Dropping all tables...\n\n";

        // Drop all tables
        $tables = $gDb->queryPrepared(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?',
            [$dbConfig['database']]
        );

        $gDb->queryPrepared('SET FOREIGN_KEY_CHECKS = 0');
        while ($tableRow = $tables->fetch()) {
            $tableName = $tableRow['TABLE_NAME'];
            $gDb->query("DROP TABLE IF EXISTS `$tableName`");
            echo ".";
        }
        $gDb->queryPrepared('SET FOREIGN_KEY_CHECKS = 1');
        echo "\n✓ All tables dropped\n\n";
    }

    // Create installation configuration
    $config = InstallationConfig::fromArray([
        'dbType' => $dbConfig['engine'] === 'mariadb' ? 'mariadb' : $dbConfig['engine'],
        'dbHost' => $dbConfig['host'],
        'dbPort' => $dbConfig['port'],
        'dbName' => $dbConfig['database'],
        'dbUsername' => $dbConfig['user'],
        'dbPassword' => $dbConfig['password'],
        'tablePrefix' => TABLE_PREFIX,
        'rootUrl' => 'http://localhost/admidio',
        'language' => 'en',
        'timezone' => 'UTC',
        'organizationName' => 'Test Organization',
        'organizationShortName' => 'TEST',
        'organizationEmail' => 'test@example.local',
        'adminLogin' => 'admin',
        'adminFirstName' => 'Admin',
        'adminLastName' => 'User',
        'adminEmail' => 'admin@test.local',
        'adminPassword' => 'test_admin_123',
    ]);

    echo "Installing Admidio database schema and default data...\n";
    echo "(This may take 30-60 seconds)\n\n";

    // Run installation
    $installResult = Installation::install($gDb, $config);

    echo "\n✓ Database initialization complete!\n";
    echo "✓ Schema created\n";
    echo "✓ Default data installed\n";
    echo "✓ Organization created: " . $config->organizationName . "\n";
    echo "✓ Administrator created: " . $config->administratorLogin . "\n";
    echo "✓ Organization ID: " . $installResult['organizationId'] . "\n";
    echo "✓ Administrator ID: " . $installResult['administratorId'] . "\n\n";

    // Verify tables
    $result = $gDb->queryPrepared(
        'SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?',
        [$dbConfig['database']]
    );
    $row = $result->fetch();
    $tableCount = (int) ($row['cnt'] ?? 0);
    echo "✓ Tables created: $tableCount\n\n";

    echo "═══════════════════════════════════════════════════════════\n";
    echo "  Test database is ready for integration testing\n";
    echo "═══════════════════════════════════════════════════════════\n";

} catch (Throwable $e) {
    echo "✗ Error during database setup:\n";
    echo "  " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "  Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
