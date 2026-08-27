<?php
namespace Admidio\Tests\Unit\Hooks\Support;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\Service\EntityHookQueue;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Tests\Support\AdmidioTestCase;
use Psr\Log\NullLogger;

/**
 * Base class for the hook tests that execute the real Entity lifecycle against FakeDatabase, an
 * in-memory SQLite connection, without a configured test database.
 *
 * Entity reads TABLE_PREFIX, DB_TYPE and DATETIME_NOW as bare global constants, so they have to
 * exist before it runs here - always with the MySQL-style boolean handling FakeDatabase expects,
 * regardless of which engine .env.test configures for the database-backed suites. Every definition
 * is guarded because composer test:all loads every testsuite in one process, and
 * tests/bootstrap-admidio.php defines the same constants for the database-backed suites; whichever
 * bootstrap runs first wins, see the guards there.
 */
abstract class EntityHookTestCase extends AdmidioTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!defined('TABLE_PREFIX')) {
            define('TABLE_PREFIX', 'adm');
        }
        if (!defined('DB_TYPE')) {
            define('DB_TYPE', Database::PDO_ENGINE_MYSQL);
        }
        if (!defined('DATETIME_NOW')) {
            define('DATETIME_NOW', date('Y-m-d H:i:s'));
        }

        // getExecutionTime(), which Language::get() and other Admidio code call unconditionally.
        // require_once is keyed by the resolved path, so this is a no-op if the real Admidio
        // bootstrap already loaded the same file.
        require_once dirname(__DIR__, 4) . '/system/bootstrap/function.php';

        $GLOBALS['gCurrentUserId'] = 0;
        $GLOBALS['gLogger'] = $GLOBALS['gLogger'] ?? new NullLogger();

        // The changelog needs settings and tables of its own and is not what these tests are about.
        Entity::setLoggingEnabled(false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Hooks::reset();
        EntityHookQueue::reset();
    }
}
