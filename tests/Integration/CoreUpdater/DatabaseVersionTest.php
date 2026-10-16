<?php
/**
 * The version of the database as the updater reads it, which takes part in the rule
 * which version may be installed.
 */

namespace Admidio\Tests\Integration\CoreUpdater;

use Admidio\Tests\Support\DatabaseTestCase;
use AdmidioCoreUpdater as U;
use PDO;

final class DatabaseVersionTest extends DatabaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    /**
     * The connection of the test transaction, for the updater's plain PDO access.
     */
    private function pdo(): PDO
    {
        return (new \ReflectionProperty($this->getDatabase(), 'pdo'))->getValue($this->getDatabase());
    }

    private function setCoreVersion(string $version, int $beta): void
    {
        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_COMPONENTS . ' SET com_version = ?, com_beta = ? WHERE com_type = ? AND com_name_intern = ?',
            array($version, $beta, 'SYSTEM', 'CORE')
        );
    }

    /**
     * @testdox The version of the database is read from the CORE component, with its beta
     */
    public function testDatabaseVersion(): void
    {
        $this->setCoreVersion('5.0.14', 0);
        $this->assertSame('5.0.14', U\databaseVersion($this->pdo(), TABLE_PREFIX));

        $this->setCoreVersion('6.0.0', 2);
        $this->assertSame('6.0.0-beta.2', U\databaseVersion($this->pdo(), TABLE_PREFIX));
    }

    /**
     * @testdox A database that is ahead of the offered version refuses it
     */
    public function testDatabaseAheadRefusesTheUpdate(): void
    {
        $this->setCoreVersion('6.0.0', 0);

        $this->expectExceptionMessage('SYS_CORE_UPDATE_DATABASE_NEWER');
        U\checkVersionRule('6.0.0-beta.3', '6.0.0-beta.3', U\databaseVersion($this->pdo(), TABLE_PREFIX));
    }

    /**
     * @testdox Without the components table the version of the database is unknown
     */
    public function testUnknownDatabaseVersion(): void
    {
        $this->assertNull(U\databaseVersion($this->pdo(), 'not_installed'));
    }
}
