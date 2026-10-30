<?php
/**
 * The backups (install/core-update/lib/backup.php): their directory, their names, the list, and
 * that no other file can be reached through a name.
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
        $this->assertMatchesRegularExpression('/^database_\d{4}-\d{2}-\d{2}_\d{6}_6\.0\.0-beta\.1_[a-f0-9]{32}\.zip$/', U\backupName('database', '6.0.0-beta.1'));
        $this->assertMatchesRegularExpression('/^files_[0-9_-]+_5\.0\.17_to_6\.0\.0-feature-x_[a-f0-9]{32}\.zip$/',
            U\backupName('files', '5.0.17_to_6.0.0-feature/x'), 'a branch name becomes a plain part of the name');
        $this->assertNotSame(U\backupName('database', '6.0.0'), U\backupName('database', '6.0.0'));
    }

    /**
     * @testdox Only a name of a backup leads to a file, never a path or another file
     */
    public function testOnlyBackupsCanBeReached(): void
    {
        $directory = U\backupsDirectory($this->root, '');
        $name = U\backupName('database', '6.0.0');
        $this->assertSame($this->root . '/adm_my_files/backups/' . $name, U\backupFile($directory, $name));

        foreach (array('../config.php', 'database_x.zip', 'config.php', '/etc/passwd', 'files_2026-10-13_120000_6.0.0_abcdef.zip/../../config.php',
            'database_2026-10-13_120000_6.0.0_abcdef.zip.php', 'database_2026-10-13_120000_6.0.0_abcdef.zip') as $name) {
            try {
                U\backupFile($directory, $name);
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
        $older = 'database_2026-10-01_120000_5.0.17_' . str_repeat('a', 32) . '.zip';
        $newer = 'files_2026-10-02_120000_5.0.17_to_6.0.0_' . str_repeat('b', 32) . '.zip';
        file_put_contents($this->root . '/adm_my_files/backups/' . $older, 'OLDER');
        file_put_contents($this->root . '/adm_my_files/backups/' . $newer, 'NEWER BACKUP');
        touch($this->root . '/adm_my_files/backups/' . $older, 1_000_000);
        touch($this->root . '/adm_my_files/backups/' . $newer, 2_000_000);
        file_put_contents($this->root . '/adm_my_files/backups/notes.zip', 'NOT A BACKUP');
        mkdir($this->root . '/adm_my_files/backups/.tables-123456');

        $this->assertSame(array(
            array('name' => $newer, 'kind' => 'files', 'size' => 12, 'time' => 2_000_000),
            array('name' => $older, 'kind' => 'database', 'size' => 5, 'time' => 1_000_000)
        ), U\listBackups(U\backupsDirectory($this->root, '')));
    }

    /**
     * @testdox A table file is restored statement by statement, and a cut-off or damaged one is an error
     */
    public function testRunSqlFile(): void
    {
        $pdo = new \PDO('sqlite::memory:', null, null, array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
        $file = $this->root . '/001_adm_roles.sql.gz';
        $write = static fn(string $sql) => file_put_contents($file, gzencode($sql));

        $write("-- MySQL dump\n\nDROP TABLE IF EXISTS adm_roles;\nCREATE TABLE adm_roles (\n  rol_id integer,\n  rol_name text\n);\n"
            . "INSERT INTO adm_roles VALUES (1,'a;'),(2,'b');\nINSERT INTO adm_roles VALUES (3,'c\\nd;');\n");
        U\runSqlFile($pdo, $file, 'roles');
        $this->assertSame(array('a;', 'b', 'c\\nd;'), $pdo->query('SELECT rol_name FROM adm_roles ORDER BY rol_id')->fetchAll(\PDO::FETCH_COLUMN));

        foreach (array('cut off' => "CREATE TABLE adm_x (a integer);\nINSERT INTO adm_x VALUES (1),\n",
            'without its table' => "INSERT INTO adm_roles VALUES (4,'e');\n") as $case => $sql) {
            $write($sql);
            try {
                U\runSqlFile($pdo, $file, 'roles');
                $this->fail('accepted a file ' . $case);
            } catch (UpdaterException $exception) {
                $this->assertStringContainsString('roles is incomplete', $exception->getDetail(), $case);
            }
        }

        file_put_contents($file, 'NOT GZIP');
        $this->expectException(UpdaterException::class);
        U\runSqlFile($pdo, $file, 'roles');
    }

    /**
     * @testdox The backups are in adm_my_files/backups, or in the directory of the preferences
     */
    public function testDirectory(): void
    {
        $this->assertSame($this->root . '/adm_my_files/backups', U\backupsDirectory($this->root, ''));
        $this->assertSame('/srv/admidio-backups', U\backupsDirectory($this->root, '/srv/admidio-backups/'));
        $this->assertSame('C:/Backups/Admidio', U\backupsDirectory($this->root, 'C:\\Backups\\Admidio'));

        $this->assertSame('adm_my_files/backups/x.zip', U\displayPath($this->root, $this->root . '/adm_my_files/backups/x.zip'));
        $this->assertSame('/srv/admidio-backups/x.zip', U\displayPath($this->root, '/srv/admidio-backups/x.zip'));
    }
}
