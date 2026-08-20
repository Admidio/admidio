<?php
/**
 * Test Database Initializer
 *
 * Initializes a full production-like Admidio installation for the test suite.
 * This runs once at test suite startup to create schema, default data, and admin user.
 */

namespace Admidio\Tests\Support;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Language;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;

class TestDatabaseInitializer
{
    /**
     * Initialize test database with full Admidio installation
     *
     * This creates the complete database schema, default data, organizations, and admin user.
     * Should be called once in test suite setUpBeforeClass()
     *
     * @param Database $database Test database connection
     * @param array $config Database configuration from .env.test
     * @return array Installation result with organizationId and administratorId
     */
    public static function initialize(Database $database, array $config): array
    {
        global $gDb, $gLogger, $gL10n, $gCurrentUser, $gCurrentUserId, $gCurrentSession;

        // Make database available to global scope for Installation service
        $gDb = $database;
        $GLOBALS['gDb'] = $gDb;

        // Ensure logger exists
        if (!isset($GLOBALS['gLogger'])) {
            $gLogger = new \Admidio\Infrastructure\Logger();
            $GLOBALS['gLogger'] = $gLogger;
        }

        // Set up language for error messages
        if (!isset($GLOBALS['gL10n'])) {
            $gL10n = new Language('en');
            $GLOBALS['gL10n'] = $gL10n;
        }

        // Initialize required globals
        $gCurrentUser = null;
        $gCurrentUserId = 0;
        $gCurrentSession = null;
        $GLOBALS['gCurrentUser'] = $gCurrentUser;
        $GLOBALS['gCurrentUserId'] = $gCurrentUserId;
        $GLOBALS['gCurrentSession'] = $gCurrentSession;

        // Check if database is already initialized
        if (self::isAlreadyInitialized($database, $config)) {
            echo "  ℹ Database already initialized, skipping installation\n";
            // Get the admin user and organization
            $adminQuery = $database->queryPrepared(
                'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login = ?',
                ['admin']
            );
            $adminUser = $adminQuery->fetch();

            $orgQuery = $database->queryPrepared('SELECT org_id FROM ' . TBL_ORGANIZATIONS . ' LIMIT 1');
            $org = $orgQuery->fetch();

            return [
                'organizationId' => (int) ($org['org_id'] ?? 1),
                'administratorId' => (int) ($adminUser['usr_id'] ?? 1),
            ];
        }

        echo "  Installing Admidio production setup...\n";

        // Drop all existing tables to start fresh
        self::dropAllTables($database, $config);

        // Create installation configuration from environment
        $installConfig = InstallationConfig::fromArray([
            'dbType' => $config['engine'] === 'mariadb' ? Database::DB_TYPE_MARIADB :
                       ($config['engine'] === 'postgres' ? Database::DB_TYPE_PGSQL : Database::DB_TYPE_MYSQL),
            'dbHost' => $config['host'],
            'dbPort' => $config['port'],
            'dbName' => $config['database'],
            'dbUsername' => $config['user'],
            'dbPassword' => $config['password'],
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

        // Run full installation
        $result = Installation::install($database, $installConfig);

        echo "  ✓ Database initialized\n";
        echo "  ✓ Schema created\n";
        echo "  ✓ Default data installed\n";
        echo "  ✓ Administrator user created\n";

        return $result;
    }

    /**
     * Check if database is already initialized
     */
    private static function isAlreadyInitialized(Database $database, array $config): bool
    {
        try {
            $result = $database->queryPrepared(
                'SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?',
                [$config['database']]
            );
            $row = $result->fetch();
            $tableCount = (int) ($row['cnt'] ?? 0);
            return $tableCount > 50; // Admidio has ~50+ tables
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Drop all tables in database
     */
    private static function dropAllTables(Database $database, array $config): void
    {
        try {
            // Disable foreign key checks temporarily
            $database->queryPrepared('SET FOREIGN_KEY_CHECKS = 0');

            // Get list of all tables
            $result = $database->queryPrepared(
                'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?',
                [$config['database']]
            );

            $count = 0;
            while ($row = $result->fetch()) {
                $tableName = $row['TABLE_NAME'];
                try {
                    $database->query("DROP TABLE IF EXISTS `{$tableName}`");
                    $count++;
                } catch (\Exception $e) {
                    // Silently skip errors
                }
            }

            // Re-enable foreign key checks
            $database->queryPrepared('SET FOREIGN_KEY_CHECKS = 1');

            if ($count > 0) {
                echo "  ✓ Dropped $count existing tables\n";
            }
        } catch (\Exception $e) {
            // If we can't drop tables, installation will fail anyway
        }
    }
}
