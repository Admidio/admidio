<?php
/**
 * The backups in adm_my_files/backups/ (install/core-update/lib/backup.php): their names, the
 * list, and that no other file can be reached through a name.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;
use AdmidioCoreUpdater\UpdaterException;

final class BackupTest extends AdmidioTestCase
{
    private string $root = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/adm_backups_' . uniqid();
        mkdir($this->root . '/adm_my_files/backups', 0777, true);
    }

    protected function tearDown(): void
    {
        U\deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * @testdox A backup is named after what it holds, its time and versions, with a random part
     */
    public function testNames(): void
    {
        $this->assertMatchesRegularExpression('/^database_\d{4}-\d{2}-\d{2}_\d{6}_6\.0\.0-beta\.1_[a-f0-9]{6}\.zip$/', U\backupName('database', '6.0.0-beta.1'));
        $this->assertMatchesRegularExpression('/^files_[0-9_-]+_5\.0\.17_to_6\.0\.0-feature-x_[a-f0-9]{6}\.zip$/',
            U\backupName('files', '5.0.17_to_6.0.0-feature/x'), 'a branch name becomes a plain part of the name');
        $this->assertNotSame(U\backupName('database', '6.0.0'), U\backupName('database', '6.0.0'));
    }

    /**
     * @testdox Only a name of a backup leads to a file, never a path or another file
     */
    public function testOnlyBackupsCanBeReached(): void
    {
        $name = U\backupName('database', '6.0.0');
        $this->assertSame($this->root . '/adm_my_files/backups/' . $name, U\backupFile($this->root, $name));

        foreach (array('../config.php', 'database_x.zip', 'config.php', '/etc/passwd', 'files_2026-10-13_120000_6.0.0_abcdef.zip/../../config.php',
            'database_2026-10-13_120000_6.0.0_abcdef.zip.php') as $name) {
            try {
                U\backupFile($this->root, $name);
                $this->fail('accepted: ' . $name);
            } catch (UpdaterException $exception) {
                $this->assertSame('SYS_CORE_UPDATE_REQUEST_INVALID', $exception->getMessage(), $name);
            }
        }
    }

    /**
     * @testdox The list holds the backups, the newest first, and nothing else of the directory
     */
    public function testList(): void
    {
        $older = 'database_2026-10-01_120000_5.0.17_aaaaaa.zip';
        $newer = 'files_2026-10-02_120000_5.0.17_to_6.0.0_bbbbbb.zip';
        file_put_contents($this->root . '/adm_my_files/backups/' . $older, 'OLDER');
        file_put_contents($this->root . '/adm_my_files/backups/' . $newer, 'NEWER BACKUP');
        touch($this->root . '/adm_my_files/backups/' . $older, 1_000_000);
        touch($this->root . '/adm_my_files/backups/' . $newer, 2_000_000);
        file_put_contents($this->root . '/adm_my_files/backups/notes.zip', 'NOT A BACKUP');
        mkdir($this->root . '/adm_my_files/backups/.tables-123456');

        $this->assertSame(array(
            array('name' => $newer, 'kind' => 'files', 'size' => 12, 'time' => 2_000_000),
            array('name' => $older, 'kind' => 'database', 'size' => 5, 'time' => 1_000_000)
        ), U\listBackups($this->root));
    }
}
