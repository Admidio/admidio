<?php
/**
 * The database update of the updater's command line (CoreUpdateHandover::updateDatabase()), which
 * install/core-update/cli.php runs in a process of its own with the code of the new release. The
 * updates are kept in a temporary installation directory; the database is the one of the test
 * installation, which is up to date, so that no update step runs inside the test transaction.
 */

namespace Admidio\Tests\Integration\CoreUpdater;

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\InstallationUpdate\Service\CoreUpdateHandover;
use Admidio\Tests\Support\DatabaseTestCase;
use AdmidioCoreUpdater as U;
use ReflectionProperty;
use RuntimeException;

final class CommandLineDatabaseUpdateTest extends DatabaseTestCase
{
    private const UPDATE_ID = '0123456789abcdef0123456789abcdef';

    private string $rootPath = '';

    /** The globals updateDatabase() sets, as install/update.php does; they must not outlive the test. */
    private const UPDATE_GLOBALS = array('gProfileFields', 'gChangeNotification', 'gLoginForUpdate', 'gCurrentUserId');

    /** @var array<string,bool> */
    private array $entityFlags = array();

    /** @var array<string,mixed> */
    private array $globalsBefore = array();

    protected function setUp(): void
    {
        parent::setUp();

        // updateDatabase() switches the changelog and the entity hooks off, as install/update.php does.
        foreach (array('loggingEnabled', 'hooksEnabled') as $flag) {
            $this->entityFlags[$flag] = (new ReflectionProperty(Entity::class, $flag))->getValue();
        }

        foreach (self::UPDATE_GLOBALS as $name) {
            if (array_key_exists($name, $GLOBALS)) {
                $this->globalsBefore[$name] = $GLOBALS[$name];
            }
        }

        $this->rootPath = sys_get_temp_dir() . '/adm_cli_database_' . uniqid();
        mkdir($this->rootPath . '/adm_my_files/core-update/' . self::UPDATE_ID, 0777, true);
        CoreUpdateHandover::setRootPath($this->rootPath);
    }

    protected function tearDown(): void
    {
        CoreUpdateHandover::setRootPath(null);
        U\releaseLock();
        U\deleteDirectory($this->rootPath);
        Entity::setLoggingEnabled($this->entityFlags['loggingEnabled']);
        Entity::setHooksEnabled($this->entityFlags['hooksEnabled']);
        foreach (self::UPDATE_GLOBALS as $name) {
            if (array_key_exists($name, $this->globalsBefore)) {
                $GLOBALS[$name] = $this->globalsBefore[$name];
            } else {
                unset($GLOBALS[$name]);
            }
        }

        parent::tearDown();
    }

    private function setState(string $state): void
    {
        U\writeState($this->rootPath, array('schemaVersion' => U\SCHEMA_VERSION, 'updateId' => self::UPDATE_ID, 'state' => $state,
            'credentials' => array(), 'csrfToken' => str_repeat('a', 64), 'lastError' => ''));
    }

    private function state(): array
    {
        return U\readState($this->rootPath, self::UPDATE_ID);
    }

    /**
     * @testdox A database that is up to date completes the update without running a step
     */
    public function testUpToDateDatabaseCompletesTheUpdate(): void
    {
        $this->setState('files_installed');

        CoreUpdateHandover::load(self::UPDATE_ID)->updateDatabase();

        $state = $this->state();
        $this->assertSame('completed', $state['state']);
        $this->assertTrue($state['databaseStarted']);
        $this->assertSame(array(), $state['databaseNotes']);
        $log = (string)file_get_contents(U\workspace($this->rootPath, self::UPDATE_ID) . '/update.log');
        $this->assertStringContainsString('state database_running', $log);
        $this->assertStringContainsString('state completed: database updated', $log);
    }

    /**
     * @testdox The database is not touched while the files are still being installed
     */
    public function testRefusedBeforeTheFiles(): void
    {
        $this->setState('installing');

        try {
            CoreUpdateHandover::load(self::UPDATE_ID)->updateDatabase();
            $this->fail('the database update ran on a mix of two releases');
        } catch (Exception $exception) {
            $this->assertSame('SYS_CORE_UPDATE_IN_PROGRESS', $exception->getTranslationId());
        }
        $this->assertSame('installing', $this->state()['state']);
    }

    /**
     * @testdox A second process does not run the database update next to the first
     */
    public function testRefusedWhileAnotherProcessRunsIt(): void
    {
        $this->setState('files_installed');
        $lock = U\lockDatabaseUpdate($this->rootPath, self::UPDATE_ID);
        $this->assertNotNull($lock);

        try {
            CoreUpdateHandover::load(self::UPDATE_ID)->updateDatabase();
            $this->fail('a second database update was started');
        } catch (Exception $exception) {
            $this->assertSame('SYS_CORE_UPDATE_DATABASE_RUNNING', $exception->getTranslationId());
        } finally {
            fclose($lock);
        }
        $this->assertSame('files_installed', $this->state()['state']);
    }

    /**
     * @testdox A database that is ahead of the files is refused before the update is marked as started
     */
    public function testDatabaseAheadOfTheFiles(): void
    {
        $this->setState('files_installed');
        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_COMPONENTS . ' SET com_version = ? WHERE com_type = ? AND com_name_intern = ?',
            array('99.0.0', 'SYSTEM', 'CORE')
        );

        try {
            CoreUpdateHandover::load(self::UPDATE_ID)->updateDatabase();
            $this->fail('an older release updated a newer database');
        } catch (RuntimeException) {
            $state = $this->state();
            $this->assertSame('files_installed', $state['state']);
            $this->assertArrayNotHasKey('databaseStarted', $state, 'the files can still be restored');
        }
    }
}
