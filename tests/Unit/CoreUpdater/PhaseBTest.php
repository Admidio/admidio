<?php
/**
 * Phase B from the verified package to installed and verified files
 * (install/core-update/lib/phase-b.php). The release, its signed update.txt and the installation
 * are made by the test in a temporary directory; phase A is represented by the workspace it leaves.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;
use AdmidioCoreUpdater\UpdaterException;
use ZipArchive;

final class PhaseBTest extends AdmidioTestCase
{
    use PreparedUpdate;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::loadUpdater();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createInstallation();
    }

    protected function tearDown(): void
    {
        $this->removeInstallation();

        parent::tearDown();
    }

    /**
     * Run until the update needs a decision.
     * @param array<string,mixed> $context
     * @return array<string,mixed> The last answer.
     */
    private function runToDecision(array $context, string $updateId): array
    {
        for ($request = 0; $request < 50; $request++) {
            $result = U\phaseBRun($context, $updateId);
            if ($result['status'] !== 'success') {
                return $result;
            }
        }

        $this->fail('phase B did not reach a decision');
    }

    /**
     * @testdox The plan replaces changed files, creates new ones, removes what the release no longer has and leaves the rest
     */
    public function testPlan(): void
    {
        [$context, $updateId] = $this->prepared();

        $this->assertSame('review', $this->runToDecision($context, $updateId)['status']);

        $state = U\readState($this->root, $updateId);
        $this->assertSame(array('create' => 1, 'replace' => 2, 'delete' => 2, 'kept' => 4, 'unchanged' => 1, 'inventory' => false), $state['plan'],
            'the manifest of the release, which is installed as well, is not counted; without an inventory every replaced or removed file is kept');
        $this->assertFalse(U\checksHaveErrors($state['planChecks']));

        $plan = U\readPlan(U\workspace($this->root, $updateId));
        $this->assertSame(array('system/bootstrap/bootstrap.php', 'index.php', 'src/New/Fresh.php', 'src/Old/Gone.php', 'src/Unknown.php',
            'install/update-manifest.json'), array_column($plan, 'path'), 'system/bootstrap/ first, removals after, the manifest last');
        $this->assertSame('OLD INDEX', $this->read('index.php'), 'nothing is changed before the confirmation');
        $this->assertFileDoesNotExist($this->root . '/adm_my_files/maintenance.json');
    }

    /**
     * @testdox The confirmed update installs and verifies the files in maintenance mode, ready for the database update
     */
    public function testInstall(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);

        U\phaseBConfirm($context, $updateId, 'own');
        $this->assertCount(1, U\readState($this->root, $updateId)['credentials'], 'the browser gets the updater cookie');
        $result = $this->runToDecision($context, $updateId);

        $this->assertSame('reload', $result['status'], $result['message']);
        $this->assertSame('files_installed', U\readState($this->root, $updateId)['state']);
        $this->assertSame('NEW INDEX', $this->read('index.php'));
        $this->assertSame('NEW BOOTSTRAP', $this->read('system/bootstrap/bootstrap.php'));
        $this->assertSame('NEW FILE', $this->read('src/New/Fresh.php'));
        $this->assertFileDoesNotExist($this->root . '/src/Old/Gone.php');
        $this->assertDirectoryDoesNotExist($this->root . '/src/Old', 'a directory the removals emptied is removed');
        $this->assertDirectoryExists($this->root . '/src/Empty', 'a directory that was empty before stays');
        $this->assertFileDoesNotExist($this->root . '/src/Unknown.php', 'an unknown file in a managed directory is removed');
        $this->assertSame('MY NOTES', $this->read('my-notes.txt'), 'an unknown root file is never touched');
        $this->assertSame('MY LOGO', $this->read('custom_logo.png'));
        $this->assertFileDoesNotExist($this->root . '/adm_my_files/config_example.php', 'adm_my_files is never written');
        $this->assertSame('5.1.0', U\filesVersion($this->root), 'the manifest of the release is the inventory of the next update');

        $maintenance = json_decode($this->read('adm_my_files/maintenance.json'), true);
        $this->assertSame('core-update:' . $updateId, $maintenance['owner']);
        $this->assertSame(array('install/core-update/index.php', 'install/update.php'), $maintenance['allowedScripts']);
        $this->assertSame(array('schema', 'owner', 'startedAt', 'retryAfter', 'title', 'message', 'allowedScripts'), array_keys($maintenance),
            'the format of MaintenanceMode::enable()');

        $log = (string)file_get_contents(U\workspace($this->root, $updateId) . '/update.log');
        foreach (array('state extracting', 'state review {"create":1', 'confirmed by the administrator, database backup: own', 'state installing',
            'state archiving', 'state verifying', 'state files_installed') as $line) {
            $this->assertStringContainsString($line, $log);
        }
    }

    /**
     * @testdox The source archive of a developer channel adds and replaces files, removes nothing and leaves no inventory
     */
    public function testDeveloperPackage(): void
    {
        [$context, $updateId, $workspace] = $this->preparedDeveloper(array());

        $this->assertSame('review', $this->runToDecision($context, $updateId)['status']);
        $plan = U\readPlan($workspace);
        $this->assertSame(array('composer.json', 'index.php', 'src/New/Fresh.php', 'install/update-manifest.json'), array_column($plan, 'path'));
        $this->assertSame('delete', $plan[3]['action'], 'the inventory of the installed release goes');

        U\phaseBConfirm($context, $updateId, 'own');
        $this->assertSame('reload', $this->runToDecision($context, $updateId)['status']);
        $this->assertSame('files_installed', U\readState($this->root, $updateId)['state']);
        $this->assertSame('DEV INDEX', $this->read('index.php'));
        $this->assertSame('NEW FILE', $this->read('src/New/Fresh.php'));
        $this->assertSame('REMOVED BY THE RELEASE', $this->read('src/Old/Gone.php'), 'nothing is removed');
        $this->assertSame('UNKNOWN', $this->read('src/Unknown.php'));
        $this->assertFileDoesNotExist($this->root . '/tests/DevTest.php', 'only what a release would contain is installed');
        $this->assertFileDoesNotExist($this->root . '/install/update-manifest.json');
        $this->assertNull(U\installedInventory($this->root), 'the next update keeps every file it replaces');
        $this->assertMatchesRegularExpression('#^kept/[0-9_-]+_5\.0\.14_to_5\.1\.0-feature-x-ababab[a-f0-9]_[a-f0-9]{6}\.zip$#',
            (string)U\readState($this->root, $updateId)['keptArchive']);
    }

    /**
     * @testdox A version without the updater ends with its files, and its database is updated by hand
     */
    public function testDeveloperPackageWithoutUpdater(): void
    {
        [$context, $updateId] = $this->preparedDeveloper(array('withoutUpdater' => true));
        $this->runToDecision($context, $updateId);

        U\phaseBConfirm($context, $updateId, 'own');
        $this->assertSame('reload', $this->runToDecision($context, $updateId)['status']);

        $state = U\readState($this->root, $updateId);
        $this->assertSame('completed', $state['state']);
        $this->assertTrue($state['databaseManual']);
        $this->assertSame('DEV INDEX', $this->read('index.php'));
        $this->assertFileDoesNotExist($this->root . '/adm_my_files/maintenance.json', 'nobody else would switch it off');
        $this->assertSame('skipped', U\updateSteps($state)['database']);
    }

    /**
     * The workspace of a developer channel as phase A leaves it: a source archive of the branch
     * feature/x and the manifest the updater made for it. The installed release has an inventory,
     * which no longer fits after a developer install, and index.php was changed locally.
     * @param array<string,mixed> $fields More fields of the state.
     * @return array{0: array<string,mixed>, 1: string, 2: string} context, update ID and workspace
     */
    private function preparedDeveloper(array $fields): array
    {
        $this->write(array('install/update-manifest.json' => json_encode(array('version' => '5.0.14', 'files' => array(
            'index.php' => hash('sha256', 'ORIGINAL INDEX'), 'src/Same.php' => hash('sha256', 'SAME'), 'src/Old/Gone.php' => hash('sha256', 'REMOVED BY THE RELEASE'))))));

        $state = U\createUpdate($this->root, array('installedVersion' => '5.0.14'));
        $updateId = $state['updateId'];
        $workspace = U\workspace($this->root, $updateId);
        $zip = new ZipArchive();
        $zip->open($workspace . '/package.zip', ZipArchive::CREATE);
        foreach (array('index.php' => 'DEV INDEX', 'src/Same.php' => 'SAME', 'src/New/Fresh.php' => 'NEW FILE', 'install/core-update/index.php' => 'NEW UPDATER',
            'tests/DevTest.php' => 'A TEST', 'composer.json' => '{"archive": {"exclude": ["tests/*"]}}') as $path => $content) {
            $zip->addFromString('admidio-feature-x/' . $path, $content);
        }
        $zip->setArchiveComment(str_repeat('ab', 20));
        $zip->close();
        $package = U\openPackage($workspace . '/package.zip', U\MAX_EXTRACTED_SIZE);
        file_put_contents($workspace . '/update-manifest.json', json_encode(U\developerManifest($package, '5.1.0')));
        $package['zip']->close();
        U\updateState($this->root, $updateId, static fn(array $s): array => array_merge($s, array('state' => 'prepared', 'channel' => 'branch',
            'ref' => 'feature/x', 'commit' => str_repeat('ab', 20), 'packageSha256' => hash_file('sha256', $workspace . '/package.zip'),
            'targetVersion' => '5.1.0', 'targetVersionText' => '5.1.0 (feature/x ababab)') + $fields));
        $context = array('config' => U\loadConfig($this->root), 'pdo' => null, 'texts' => array('language' => 'en', 'texts' => array()),
            'updaterPath' => $this->root . '/install/core-update', 'http' => array(), 'chunkSize' => U\CHUNK_SIZE);

        return array($context, $updateId, $workspace);
    }

    /**
     * @testdox Restoring brings back every file and switches maintenance mode off
     */
    public function testRollback(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);
        U\phaseBConfirm($context, $updateId, 'own');
        $this->runToDecision($context, $updateId);

        U\phaseBRollback($context, $updateId);
        $result = $this->runToDecision($context, $updateId);

        $this->assertSame('rolled_back', $result['status'], $result['message']);
        $this->assertSame('OLD INDEX', $this->read('index.php'));
        $this->assertSame('OLD BOOTSTRAP', $this->read('system/bootstrap/bootstrap.php'));
        $this->assertSame('REMOVED BY THE RELEASE', $this->read('src/Old/Gone.php'));
        $this->assertSame('UNKNOWN', $this->read('src/Unknown.php'));
        $this->assertFileDoesNotExist($this->root . '/src/New/Fresh.php');
        $this->assertDirectoryDoesNotExist($this->root . '/src/New', 'a directory the update created is removed again');
        $this->assertFileDoesNotExist($this->root . '/install/update-manifest.json');
        $this->assertFileDoesNotExist($this->root . '/adm_my_files/maintenance.json');
        $this->assertNull(U\activeUpdateId($this->root));
        $this->assertCount(1, U\readState($this->root, $updateId)['credentials'], 'the browser still sees the result');

        U\phaseBRolledBack($context, $updateId);
        $this->assertSame(array(), U\readState($this->root, $updateId)['credentials'], 'then nobody can continue the update');
    }

    /**
     * @testdox Restoring is refused once the database update has started
     */
    public function testNoRollbackAfterTheDatabaseStarted(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);
        U\phaseBConfirm($context, $updateId, 'own');
        $this->runToDecision($context, $updateId);
        U\updateState($this->root, $updateId, static fn(array $s): array => array_merge($s, array('state' => 'database_failed', 'databaseStarted' => true)));

        $this->expectExceptionMessage('SYS_CORE_UPDATE_REQUEST_INVALID');
        U\phaseBRollback($context, $updateId);
    }

    /**
     * @testdox An operation cut off halfway is undone and repeated
     */
    public function testResumeAfterAnInterruptedOperation(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);
        U\phaseBConfirm($context, $updateId, 'own');
        U\phaseBRun($context, $updateId); // maintenance on
        $workspace = U\workspace($this->root, $updateId);

        // The request died after the journal entry of the first operation and half a copy.
        mkdir($workspace . '/backup/system/bootstrap', 0777, true);
        copy($this->root . '/system/bootstrap/bootstrap.php', $workspace . '/backup/system/bootstrap/bootstrap.php');
        U\appendJournal($workspace, array('index' => 0, 'phase' => 'prepared', 'action' => 'replace', 'path' => 'system/bootstrap/bootstrap.php',
            'mode' => null, 'directories' => array()));
        file_put_contents($this->root . '/system/bootstrap/bootstrap.php', 'HALF');
        file_put_contents($workspace . '/journal.jsonl', '{"index":1,"pha', FILE_APPEND);

        $result = $this->runToDecision($context, $updateId);

        $this->assertSame('reload', $result['status'], $result['message']);
        $this->assertSame('NEW BOOTSTRAP', $this->read('system/bootstrap/bootstrap.php'));
    }

    /**
     * @testdox A file that does not match the manifest ends the update before anything is changed
     */
    public function testDamagedPackage(): void
    {
        [$context, $updateId] = $this->prepared(array('src/New/Fresh.php' => 'MANIPULATED'));

        $result = $this->runToDecision($context, $updateId);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('SYS_CORE_UPDATE_PACKAGE_INVALID', $result['message']);
        $this->assertNull(U\activeUpdateId($this->root));
        $this->assertDirectoryDoesNotExist(U\workspace($this->root, $updateId));
        $this->assertSame('OLD INDEX', $this->read('index.php'));
    }

    /**
     * @testdox An error while installing stops in rollback_required, from where the update continues
     */
    public function testErrorWhileInstalling(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);
        U\phaseBConfirm($context, $updateId, 'own');
        U\phaseBRun($context, $updateId); // maintenance on
        $extracted = U\workspace($this->root, $updateId) . '/extracted/index.php';
        file_put_contents($extracted, 'BROKEN');

        $result = $this->runToDecision($context, $updateId);
        $this->assertSame('error', $result['status']);
        $this->assertSame('rollback_required', $result['state']);
        $this->assertSame('SYS_CORE_UPDATE_FILE_FAILED', U\readState($this->root, $updateId)['lastError']);
        $this->assertFileExists($this->root . '/adm_my_files/maintenance.json', 'maintenance mode stays on');

        file_put_contents($extracted, 'NEW INDEX');
        $this->assertSame('reload', $this->runToDecision($context, $updateId)['status'], 'continued after the cause was fixed');
        $this->assertSame('NEW INDEX', $this->read('index.php'));
    }

    /**
     * @testdox A plan with an error cannot be confirmed, and cancelling removes the workspace
     */
    public function testReviewAndCancel(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);
        U\updateState($this->root, $updateId, static function (array $s): array {
            $s['planChecks'][] = array('check' => 'D3', 'status' => 'error', 'text' => 'SYS_CORE_UPDATE_CHECK_SPACE_MISSING', 'params' => array());

            return $s;
        });

        try {
            U\phaseBConfirm($context, $updateId, 'own');
            $this->fail('a plan with an error was confirmed');
        } catch (UpdaterException $exception) {
            $this->assertSame('SYS_CORE_UPDATE_REQUEST_INVALID', $exception->getMessage());
        }

        U\phaseBCancel($context, $updateId);
        $this->assertNull(U\activeUpdateId($this->root));
        $this->assertDirectoryDoesNotExist(U\workspace($this->root, $updateId));
        $this->assertSame('NEW UPDATER', $this->read('install/core-update/index.php'), 'the updater of the new release stays');
    }

    /**
     * @testdox After install/update.php completed the update, the workspace is removed
     */
    public function testFinish(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);
        U\phaseBConfirm($context, $updateId, 'own');
        $this->runToDecision($context, $updateId);
        U\updateState($this->root, $updateId, static fn(array $s): array => array_merge($s, array('state' => 'completed')));

        U\phaseBFinish($context, $updateId);

        $this->assertDirectoryDoesNotExist(U\workspace($this->root, $updateId));
        $this->assertNull(U\activeUpdateId($this->root));
        $logs = glob($this->root . '/adm_my_files/logs/core-update_*_5.0.14_to_5.1.0.log');
        $this->assertCount(1, $logs, 'update.log is kept with the logs of Admidio');
        $this->assertStringContainsString('completed, workspace removed', (string)file_get_contents($logs[0]));
    }

    /**
     * @testdox With an inventory only changed and unknown files go into the kept-files archive
     */
    public function testKeptFilesArchive(): void
    {
        // The manifest of the installed 5.0.14: index.php and Gone.php are original, bootstrap.php
        // was changed afterwards, Unknown.php was never part of the release.
        $this->write(array('install/update-manifest.json' => json_encode(array('version' => '5.0.14', 'files' => array(
            'index.php' => hash('sha256', 'OLD INDEX'),
            'system/bootstrap/bootstrap.php' => hash('sha256', 'ORIGINAL BOOTSTRAP'),
            'src/Old/Gone.php' => hash('sha256', 'REMOVED BY THE RELEASE'),
            'src/Same.php' => hash('sha256', 'SAME')
        )))));
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);

        $state = U\readState($this->root, $updateId);
        $this->assertSame(2, $state['plan']['kept']);
        $this->assertTrue($state['plan']['inventory']);
        $keep = array_column(U\readPlan(U\workspace($this->root, $updateId)), 'keep', 'path');
        $this->assertSame(array('system/bootstrap/bootstrap.php' => 'modified', 'src/Unknown.php' => 'unknown'), $keep);

        U\phaseBConfirm($context, $updateId, 'own');
        $this->assertSame('reload', $this->runToDecision($context, $updateId)['status']);

        $archive = U\updatesDirectory($this->root) . '/' . U\readState($this->root, $updateId)['keptArchive'];
        $this->assertMatchesRegularExpression('#/kept/\d{4}-\d{2}-\d{2}_\d{6}_5\.0\.14_to_5\.1\.0_[a-f0-9]{6}\.zip$#', $archive);
        $zip = new ZipArchive();
        $zip->open($archive);
        $this->assertSame('OLD BOOTSTRAP', $zip->getFromName('system/bootstrap/bootstrap.php'), 'the file as it was before the update');
        $this->assertSame('UNKNOWN', $zip->getFromName('src/Unknown.php'));
        $this->assertFalse($zip->getFromName('index.php'), 'an original file is not kept');
        $meta = json_decode((string)$zip->getFromName('meta.json'), true);
        $zip->close();
        $this->assertSame('5.0.14', $meta['installedVersion']);
        $this->assertContains(array('path' => 'src/Unknown.php', 'reason' => 'unknown', 'action' => 'removed'), $meta['files']);

        U\phaseBRollback($context, $updateId);
        $this->runToDecision($context, $updateId);
        $this->assertFileDoesNotExist($archive, 'after a rollback the files are back, the archive is not needed');
    }

    /**
     * @testdox A backup by the updater can only be chosen where the database can be backed up
     */
    public function testBackupChoice(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);
        $this->assertSame('', U\readState($this->root, $updateId)['dumpMethod'], 'no mysqldump-php in the test installation');

        foreach (array('dump', '', 'later') as $choice) {
            try {
                U\phaseBConfirm($context, $updateId, $choice);
                $this->fail('accepted: ' . $choice);
            } catch (UpdaterException $exception) {
                $this->assertSame('SYS_CORE_UPDATE_REQUEST_INVALID', $exception->getMessage(), $choice);
            }
        }

        $this->assertSame('own', U\phaseBConfirm($context, $updateId, 'own')['backup']);
        U\phaseBRun($context, $updateId);
        $this->assertSame('installing', U\readState($this->root, $updateId)['state'], 'without a backup by the updater no dumping state');
    }

    /**
     * @testdox After a failed database update the files come back once the administrator restored the database
     *
     * Without a backup of the updater the database cannot be restored by it, and the old files on a
     * half-migrated database would not work, so the administrator confirms an own restore first.
     */
    public function testRestoreWithOwnBackup(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);
        U\phaseBConfirm($context, $updateId, 'own');
        $this->runToDecision($context, $updateId);
        // The database update of the release moved what its manifest names in migrationPaths.
        U\deleteDirectory($this->root . '/old_plugins');
        U\updateState($this->root, $updateId, static fn(array $s): array => array_merge($s, array('state' => 'database_failed', 'databaseStarted' => true)));

        $this->assertSame('', U\restoreMethod($context, U\readState($this->root, $updateId)));
        try {
            U\phaseBRestore($context, $updateId, false);
            $this->fail('the files were restored without a restored database');
        } catch (UpdaterException $exception) {
            $this->assertSame('SYS_CORE_UPDATE_REQUEST_INVALID', $exception->getMessage());
        }

        $this->assertSame('rolling_back', U\phaseBRestore($context, $updateId, true)['state']);
        $result = $this->runToDecision($context, $updateId);

        $this->assertSame('rolled_back', $result['status']);
        $this->assertSame('SYS_CORE_UPDATE_VERSION_RESTORED', $result['message']);
        $this->assertSame('OLD INDEX', $this->read('index.php'));
        $this->assertSame('REMOVED BY THE RELEASE', $this->read('src/Old/Gone.php'));
        $this->assertSame('OLD PLUGIN', $this->read('old_plugins/birthday/index.php'), 'what the database update moved comes back');
        $this->assertFileDoesNotExist($this->root . '/adm_my_files/maintenance.json');
        $this->assertNull(U\activeUpdateId($this->root));
    }
}
