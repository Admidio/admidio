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
    private static \OpenSSLAsymmetricKey $key;

    private string $root = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
        self::$key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // An installation of 5.0.14: a changed core file, an unchanged one, a file the new release
        // no longer has, an unknown file in a managed directory, a custom root file, an empty
        // directory of the release and the files that are never touched.
        $this->root = sys_get_temp_dir() . '/adm_phase_b_' . uniqid();
        $this->write(array(
            'adm_my_files/config.php' => '<?php $g_adm_db = "club"; $g_root_path = "https://club.example.org";',
            'index.php' => 'OLD INDEX',
            'system/bootstrap/bootstrap.php' => 'OLD BOOTSTRAP',
            'src/Same.php' => 'SAME',
            'src/Old/Gone.php' => 'REMOVED BY THE RELEASE',
            'src/Unknown.php' => 'UNKNOWN',
            'install/core-update/index.php' => 'NEW UPDATER',
            'custom_logo.png' => 'MY LOGO',
            'my-notes.txt' => 'MY NOTES'
        ));
        mkdir($this->root . '/src/Empty', 0777, true);
    }

    protected function tearDown(): void
    {
        U\releaseLock();
        U\deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * @param array<string,string> $files
     */
    private function write(array $files): void
    {
        foreach ($files as $path => $content) {
            @mkdir(dirname($this->root . '/' . $path), 0777, true);
            file_put_contents($this->root . '/' . $path, $content);
        }
    }

    private function read(string $path): string
    {
        return (string)@file_get_contents($this->root . '/' . $path);
    }

    /**
     * The workspace as phase A leaves it: a verified package of 5.1.0, its manifest and the signed
     * update.txt, in state prepared.
     * @param array<string,string> $tamper path => content packaged instead of what the manifest names
     * @return array{0: array<string,mixed>, 1: string} context and update ID
     */
    private function prepared(array $tamper = array()): array
    {
        $files = array(
            'index.php' => 'NEW INDEX',
            'system/bootstrap/bootstrap.php' => 'NEW BOOTSTRAP',
            'src/Same.php' => 'SAME',
            'src/New/Fresh.php' => 'NEW FILE',
            'install/core-update/index.php' => 'NEW UPDATER',
            'adm_my_files/config_example.php' => 'EXAMPLE'
        );
        $manifest = json_encode(array(
            'version' => '5.1.0',
            'managedDirectories' => array('src', 'system', 'install'),
            'managedRootFiles' => array('index.php'),
            'preservePaths' => array('adm_my_files', 'install/core-update', 'custom_logo.png'),
            'requirements' => array('php' => '>=8.2', 'extensions' => array()),
            'totalSize' => 100,
            'files' => array_map(static fn(string $content): string => hash('sha256', $content), $files)
        ));

        $state = U\createUpdate($this->root, array('installedVersion' => '5.0.14'));
        $workspace = U\workspace($this->root, $state['updateId']);
        $zip = new ZipArchive();
        $zip->open($workspace . '/package.zip', ZipArchive::CREATE);
        foreach (array_merge($files, $tamper) as $path => $content) {
            $zip->addFromString('admidio-5.1.0/' . $path, $content);
        }
        $zip->addFromString('admidio-5.1.0/install/update-manifest.json', $manifest);
        $zip->close();

        $text = "Version=5.1.0\nPackage-URL=https://example.org/admidio-5.1.0.zip\nPackage-SHA256=" . hash_file('sha256', $workspace . '/package.zip')
            . "\nPackage-Size=" . filesize($workspace . '/package.zip') . "\nMinimum-Source-Version=5.0.0\nMinimum-PHP-Version=8.2\n";
        file_put_contents($workspace . '/update.txt', $text);
        file_put_contents($workspace . '/update.txt.sig', U\signData($text, self::$key));
        file_put_contents($workspace . '/release-key.pem', openssl_pkey_get_details(self::$key)['key']);
        file_put_contents($workspace . '/update-manifest.json', $manifest);
        U\updateState($this->root, $state['updateId'], static fn(array $s): array => array_merge($s, array(
            'state' => 'prepared', 'channel' => 'stable', 'targetVersion' => '5.1.0', 'targetVersionText' => '5.1.0')));

        $context = array(
            'config' => U\loadConfig($this->root),
            'pdo' => null,
            'texts' => array('language' => 'en', 'texts' => array()),
            'updaterPath' => $this->root . '/install/core-update',
            'http' => array(),
            'chunkSize' => U\CHUNK_SIZE
        );

        return array($context, $state['updateId']);
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
        $this->assertSame(array('create' => 1, 'replace' => 2, 'delete' => 2, 'unchanged' => 1), $state['plan'],
            'the manifest of the release, which is installed as well, is not counted');
        $this->assertFalse(U\checksHaveErrors($state['planChecks']));

        $plan = U\readPlan(U\workspace($this->root, $updateId));
        $this->assertSame(array('system/bootstrap/bootstrap.php', 'index.php', 'src/New/Fresh.php', 'src/Old/Gone.php', 'src/Unknown.php',
            'install/update-manifest.json'), array_column($plan, 'path'), 'system/bootstrap/ first, removals after, the manifest last');
        $this->assertSame('OLD INDEX', $this->read('index.php'), 'nothing is changed before the confirmation');
        $this->assertFileDoesNotExist($this->root . '/adm_my_files/maintenance.json');
    }

    /**
     * @testdox The confirmed update installs and verifies the files in maintenance mode and hands over to install/update.php
     */
    public function testInstall(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);

        U\phaseBConfirm($context, $updateId);
        $this->assertCount(1, U\readState($this->root, $updateId)['credentials'], 'the browser gets the updater cookie');
        $result = $this->runToDecision($context, $updateId);

        $this->assertSame('redirect', $result['status'], $result['message']);
        $this->assertSame('https://club.example.org/install/update.php?core_update=' . $updateId, $result['url']);
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
    }

    /**
     * @testdox Restoring brings back every file and switches maintenance mode off
     */
    public function testRollback(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);
        U\phaseBConfirm($context, $updateId);
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
        $this->assertSame(array(), U\readState($this->root, $updateId)['credentials'], 'nobody can continue the update');
    }

    /**
     * @testdox Restoring is refused once the database update has started
     */
    public function testNoRollbackAfterTheDatabaseStarted(): void
    {
        [$context, $updateId] = $this->prepared();
        $this->runToDecision($context, $updateId);
        U\phaseBConfirm($context, $updateId);
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
        U\phaseBConfirm($context, $updateId);
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

        $this->assertSame('redirect', $result['status'], $result['message']);
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
        U\phaseBConfirm($context, $updateId);
        U\phaseBRun($context, $updateId); // maintenance on
        $extracted = U\workspace($this->root, $updateId) . '/extracted/index.php';
        file_put_contents($extracted, 'BROKEN');

        $result = $this->runToDecision($context, $updateId);
        $this->assertSame('error', $result['status']);
        $this->assertSame('rollback_required', $result['state']);
        $this->assertSame('SYS_CORE_UPDATE_FILE_FAILED', U\readState($this->root, $updateId)['lastError']);
        $this->assertFileExists($this->root . '/adm_my_files/maintenance.json', 'maintenance mode stays on');

        file_put_contents($extracted, 'NEW INDEX');
        $this->assertSame('redirect', $this->runToDecision($context, $updateId)['status'], 'continued after the cause was fixed');
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
            U\phaseBConfirm($context, $updateId);
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
        U\phaseBConfirm($context, $updateId);
        $this->runToDecision($context, $updateId);
        U\updateState($this->root, $updateId, static fn(array $s): array => array_merge($s, array('state' => 'completed')));

        U\phaseBFinish($context, $updateId);

        $this->assertDirectoryDoesNotExist(U\workspace($this->root, $updateId));
        $this->assertNull(U\activeUpdateId($this->root));
    }
}
