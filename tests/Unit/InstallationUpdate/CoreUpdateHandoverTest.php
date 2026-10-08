<?php
/**
 * The handover of an automatic core update to install/update.php: when the migration may run,
 * who may run it without logging in again, what the state records and when maintenance mode goes
 * off. The updates are kept in a temporary installation directory; the maintenance file is the real
 * one of the test installation, because that is the file the bootstrap reads.
 */

namespace Admidio\Tests\Unit\InstallationUpdate;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\MaintenanceMode;
use Admidio\InstallationUpdate\Service\CoreUpdateHandover;
use Admidio\Tests\Support\AdmidioTestCase;
use Psr\Log\NullLogger;

final class CoreUpdateHandoverTest extends AdmidioTestCase
{
    private const UPDATE_ID = '0123456789abcdef0123456789abcdef';
    private const SECRET = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';
    private const CREDENTIAL = self::UPDATE_ID . '.' . self::SECRET;

    private string $rootPath = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 2) . '/constants.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (MaintenanceMode::isEnabled()) {
            $this->markTestSkipped('The test installation is in maintenance mode; its state must not be touched.');
        }

        $GLOBALS['gLogger'] = $GLOBALS['gLogger'] ?? new NullLogger();
        // Exception translates its message; this stub answers with the key, so a test can name it.
        $GLOBALS['gL10n'] = new class {
            public function get(string $textId, array $params = array()): string
            {
                return $textId;
            }
        };

        $this->rootPath = sys_get_temp_dir() . '/adm_core_update_' . uniqid();
        mkdir($this->rootPath . '/adm_my_files/core-update/' . self::UPDATE_ID, 0777, true);
        CoreUpdateHandover::setRootPath($this->rootPath);
    }

    protected function tearDown(): void
    {
        CoreUpdateHandover::setRootPath(null);
        unset($GLOBALS['gL10n']);

        if ($this->rootPath !== '') {
            MaintenanceMode::disable();
            $entries = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->rootPath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($entries as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($this->rootPath);
        }

        parent::tearDown();
    }

    /**
     * The workspace of the test update.
     */
    private function workspace(): string
    {
        return $this->rootPath . '/adm_my_files/core-update/' . self::UPDATE_ID;
    }

    /**
     * Write the state of the test update as the core updater leaves it.
     */
    private function writeState(string $state, array $fields = array()): void
    {
        file_put_contents($this->workspace() . '/state.json', json_encode(array_merge(array(
            'schemaVersion' => 1,
            'updateId' => self::UPDATE_ID,
            'state' => $state,
            'credentials' => array(
                array('hash' => hash('sha256', self::SECRET), 'createdAt' => time(), 'expiresAt' => time() + 3600)
            ),
            'csrfToken' => str_repeat('c', 64),
            'targetVersion' => '5.1.0',
            'lastError' => ''
        ), $fields)));
    }

    /**
     * @return array<string,mixed>
     */
    private function readState(): array
    {
        return json_decode((string)file_get_contents($this->workspace() . '/state.json'), true);
    }

    private function enableMaintenance(string $owner): void
    {
        MaintenanceMode::enable('Update', 'Admidio is being updated.',
            array('install/core-update/index.php', 'install/update.php'), 120, $owner);
    }

    /**
     * @testdox An update that does not exist cannot be named
     */
    public function testUnknownUpdateIsRefused(): void
    {
        foreach (array('', '../../etc', strtoupper(self::UPDATE_ID), 'ffffffffffffffffffffffffffffffff') as $updateId) {
            try {
                CoreUpdateHandover::load($updateId);
                $this->fail('accepted "' . $updateId . '"');
            } catch (Exception $exception) {
                $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
            }
        }
    }

    /**
     * @testdox A state that belongs to another update is refused
     */
    public function testStateOfAnotherUpdateIsRefused(): void
    {
        $this->writeState('files_installed', array('updateId' => 'ffffffffffffffffffffffffffffffff'));

        $this->expectException(Exception::class);
        CoreUpdateHandover::load(self::UPDATE_ID);
    }

    /**
     * @return array<string,array{0: string, 1: bool}>
     */
    public static function states(): array
    {
        return array(
            'prepared' => array('prepared', false),
            'extracting' => array('extracting', false),
            'planning' => array('planning', false),
            'review' => array('review', false),
            'ready' => array('ready', false),
            'dumping' => array('dumping', false),
            'installing' => array('installing', false),
            'verifying' => array('verifying', false),
            'rollback_required' => array('rollback_required', false),
            'files_installed' => array('files_installed', true),
            'database_running' => array('database_running', true),
            'database_failed' => array('database_failed', true),
            'completed' => array('completed', true)
        );
    }

    /**
     * @testdox The migration may only run once every file is installed
     * @dataProvider states
     */
    public function testMigrationOnlyAfterTheFiles(string $state, bool $allowed): void
    {
        $this->writeState($state);

        $this->assertSame($allowed, CoreUpdateHandover::load(self::UPDATE_ID)->isMigrationAllowed());
    }

    /**
     * @testdox Without maintenance mode there is no core update to finish
     */
    public function testNoMaintenanceNoHandover(): void
    {
        $this->writeState('files_installed');

        $this->assertNull(CoreUpdateHandover::fromMaintenance());
    }

    /**
     * @testdox A maintenance mode that something else switched on is not a core update
     */
    public function testForeignMaintenanceIsIgnored(): void
    {
        $this->enableMaintenance('cli');

        $this->assertNull(CoreUpdateHandover::fromMaintenance());
    }

    /**
     * @testdox The core update that owns the maintenance mode is found without being named
     *
     * install/update.php opened in another tab, without the core_update parameter, while the files
     * are being copied must not start the migration.
     */
    public function testOwnerOfMaintenanceIsFound(): void
    {
        $this->writeState('installing');
        $this->enableMaintenance('core-update:' . self::UPDATE_ID);

        $handover = CoreUpdateHandover::fromMaintenance();

        $this->assertNotNull($handover);
        $this->assertSame(self::UPDATE_ID, $handover->getUpdateId());
        $this->assertFalse($handover->isMigrationAllowed());
    }

    /**
     * @testdox An update whose state is gone still keeps the migration out
     */
    public function testUnreadableStateKeepsTheMigrationOut(): void
    {
        $this->enableMaintenance('core-update:' . self::UPDATE_ID);

        $handover = CoreUpdateHandover::fromMaintenance();

        $this->assertNotNull($handover);
        $this->assertSame('', $handover->getState());
        $this->assertFalse($handover->isMigrationAllowed());
    }

    /**
     * @testdox Only the credential of the update, before it expires, replaces the login
     */
    public function testCredential(): void
    {
        $this->writeState('files_installed');
        $handover = CoreUpdateHandover::load(self::UPDATE_ID);

        $this->assertTrue($handover->isAuthorized(self::CREDENTIAL));
        $this->assertFalse($handover->isAuthorized(self::UPDATE_ID . '.' . str_repeat('0', 64)), 'another secret');
        $this->assertFalse($handover->isAuthorized('ffffffffffffffffffffffffffffffff.' . self::SECRET), 'another update');
        $this->assertFalse($handover->isAuthorized(''));
        $this->assertFalse($handover->isAuthorized(array(self::CREDENTIAL)), 'a cookie array');

        $this->writeState('files_installed', array('credentials' => array(
            array('hash' => hash('sha256', self::SECRET), 'createdAt' => time() - 90000, 'expiresAt' => time() - 1)
        )));
        $this->assertFalse(CoreUpdateHandover::load(self::UPDATE_ID)->isAuthorized(self::CREDENTIAL), 'expired');
    }

    /**
     * @testdox The start of the migration is recorded, so the files can no longer be rolled back
     */
    public function testDatabaseRunning(): void
    {
        $this->writeState('files_installed');

        CoreUpdateHandover::load(self::UPDATE_ID)->markDatabaseRunning();

        $state = $this->readState();
        $this->assertSame('database_running', $state['state']);
        $this->assertTrue($state['databaseStarted']);
        $this->assertSame('5.1.0', $state['targetVersion'], 'the other fields stay');
        $this->assertFileExists($this->workspace() . '/state.json.previous');
        $this->assertSame('files_installed',
            json_decode((string)file_get_contents($this->workspace() . '/state.json.previous'), true)['state']);
    }

    /**
     * @testdox A failed migration is recorded and leaves the maintenance mode on
     */
    public function testDatabaseFailed(): void
    {
        $this->writeState('database_running', array('databaseStarted' => true));
        $this->enableMaintenance('core-update:' . self::UPDATE_ID);

        CoreUpdateHandover::load(self::UPDATE_ID)->markDatabaseFailed('Table adm_users is missing');

        $state = $this->readState();
        $this->assertSame('database_failed', $state['state']);
        $this->assertSame('Table adm_users is missing', $state['lastError']);
        $this->assertTrue($state['databaseStarted']);
        $this->assertTrue(MaintenanceMode::isEnabled());
    }

    /**
     * @testdox A finished migration completes the update and switches its maintenance mode off
     */
    public function testCompleteSwitchesMaintenanceOff(): void
    {
        $this->writeState('database_running', array('databaseStarted' => true, 'lastError' => 'an earlier attempt'));
        $this->enableMaintenance('core-update:' . self::UPDATE_ID);

        CoreUpdateHandover::fromMaintenance()->complete();

        $this->assertSame('completed', $this->readState()['state']);
        $this->assertSame('', $this->readState()['lastError']);
        $this->assertFalse(MaintenanceMode::isEnabled());
    }

    /**
     * @testdox Completing never switches off a maintenance mode that belongs to something else
     */
    public function testCompleteKeepsForeignMaintenance(): void
    {
        $this->writeState('files_installed');
        $this->enableMaintenance('cli');

        CoreUpdateHandover::load(self::UPDATE_ID)->complete();

        $this->assertSame('completed', $this->readState()['state']);
        $this->assertTrue(MaintenanceMode::isEnabled());
        $this->assertSame('cli', MaintenanceMode::getState()['owner']);
    }

    /**
     * @testdox An update cannot be completed while its files are still being installed
     */
    public function testCompleteBeforeTheFilesIsRefused(): void
    {
        $this->writeState('installing');
        $this->enableMaintenance('core-update:' . self::UPDATE_ID);

        try {
            CoreUpdateHandover::load(self::UPDATE_ID)->complete();
            $this->fail('the update was completed');
        } catch (Exception $exception) {
            $this->assertSame('SYS_CORE_UPDATE_IN_PROGRESS', $exception->getTranslationId());
        }

        $this->assertSame('installing', $this->readState()['state']);
        $this->assertTrue(MaintenanceMode::isEnabled());
    }

    /**
     * @testdox The result page of the updater is the one of the update the cookie names
     */
    public function testResultUrl(): void
    {
        $this->writeState('completed');

        $this->assertSame(
            ADMIDIO_URL . FOLDER_INSTALLATION . '/core-update/index.php?action=finish',
            CoreUpdateHandover::load(self::UPDATE_ID)->getResultUrl()
        );
    }

    /**
     * @testdox The database update does not load the plugins of the installation
     *
     * install/update.php runs before the plugins are known to work with the new release, so it
     * must not go through the regular bootstrap, which loads every enabled plugin. The one update
     * step that loads plugins is limited to the plugins Admidio ships with.
     */
    public function testDatabaseUpdateLoadsNoPlugins(): void
    {
        $script = (string)file_get_contents(ADMIDIO_PATH . '/install/update.php');
        $this->assertStringNotContainsString('common.php', $script);
        $this->assertStringNotContainsString('PluginLoader', $script);

        $steps = (string)file_get_contents(ADMIDIO_PATH . '/src/InstallationUpdate/Service/UpdateStepsCode.php');
        $this->assertSame(1, substr_count($steps, 'PluginLoader::load('), 'one update step loads plugins');
        $this->assertStringNotContainsString('PluginLoader::loadEnabled', $steps);
        $this->assertMatchesRegularExpression(
            '/foreach \(PluginRegistry::BUILT_IN as \$id\)(?:(?!function ).)*PluginLoader::load\(/s',
            $steps,
            'and only for the built-in plugins'
        );
    }
}
