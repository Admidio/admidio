<?php
/**
 * The backup of the database that the updater takes before it changes any file
 * (install/core-update/lib/phase-b.php), against the database of the test suite. mysqldump-php
 * opens a connection of its own, so it backs up the committed tables.
 */

namespace Admidio\Tests\Integration\CoreUpdater;

use Admidio\Tests\Support\DatabaseTestCase;
use AdmidioCoreUpdater as U;
use PDO;
use ZipArchive;

final class DatabaseDumpTest extends DatabaseTestCase
{
    private const LIBRARY = '/vendor/ifsnop/mysqldump-php/src/Ifsnop/Mysqldump/Mysqldump.php';

    private string $root = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (admidioTestDatabaseConfig()['engine'] === 'postgres') {
            $this->markTestSkipped('PostgreSQL is backed up by pg_dump, which the test environment does not provide.');
        }

        // An installation with the library of the installed release, as every 5.x release has it.
        $this->root = sys_get_temp_dir() . '/adm_dump_' . uniqid();
        mkdir(dirname($this->root . self::LIBRARY), 0777, true);
        mkdir($this->root . '/adm_my_files', 0777, true);
        copy(dirname(__DIR__, 3) . self::LIBRARY, $this->root . self::LIBRARY);
    }

    protected function tearDown(): void
    {
        U\releaseLock();
        U\deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * The context of the backup functions for the test database, with the connection of the test.
     * @return array<string,mixed>
     */
    private function context(): array
    {
        $database = admidioTestDatabaseConfig();

        return array(
            'config' => U\configFromValues($this->root, array(
                'gDbType' => 'mysql',
                'g_adm_srv' => $database['host'],
                'g_adm_port' => $database['port'],
                'g_adm_db' => $database['database'],
                'g_adm_usr' => $database['user'],
                'g_adm_pw' => $database['password'],
                'g_tbl_praefix' => TABLE_PREFIX
            )),
            'pdo' => (new \ReflectionProperty($this->getDatabase(), 'pdo'))->getValue($this->getDatabase())
        );
    }

    /**
     * @testdox Every table of the installation is backed up into one ZIP, one compressed file per table
     */
    public function testDump(): void
    {
        $context = $this->context();
        $state = U\createUpdate($this->root, array('installedVersion' => '5.0.14'));
        $workspace = U\workspace($this->root, $state['updateId']);
        file_put_contents($workspace . '/plan.json', '[]');

        $this->assertSame('mysqldump', U\dumpMethod($context, $workspace));

        $state = array_merge($state, array('state' => 'dumping', 'dumpMethod' => 'mysqldump', 'cursor' => 0));
        for ($request = 0; $request < 100 && $state['state'] === 'dumping'; $request++) {
            $state = U\dumpPart($context, $state);
        }

        $this->assertSame('installing', $state['state']);
        $this->assertMatchesRegularExpression('#^database_\d{4}-\d{2}-\d{2}_\d{6}_5\.0\.14_[a-f0-9]{32}\.zip$#', $state['dumpFile']);
        $this->assertDirectoryDoesNotExist($workspace . '/dump', 'the table files are packed and removed');

        $tables = $context['pdo']->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()
            AND table_type = \'BASE TABLE\' AND table_name LIKE \'' . TABLE_PREFIX . '\\_%\'')->fetchAll(PDO::FETCH_COLUMN);
        $zip = new ZipArchive();
        $zip->open(U\updateBackupFile($this->root, $state, $state['dumpFile']));
        $this->assertSame(count($tables), $zip->numFiles, 'one file per table');
        $users = '';
        for ($index = 0; $index < $zip->numFiles; $index++) {
            if (str_ends_with((string)$zip->getNameIndex($index), '_' . TABLE_PREFIX . '_users.sql.gz')) {
                $users = (string)gzdecode((string)$zip->getFromIndex($index));
            }
        }
        $zip->close();
        $this->assertStringContainsString('CREATE TABLE `' . TABLE_PREFIX . '_users`', $users);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `' . TABLE_PREFIX . '_users`', $users);
    }

    /**
     * @testdox The directory of the backups is the preference of the first organization
     */
    public function testBackupsDirectoryFromThePreferences(): void
    {
        $context = $this->context();
        $this->assertSame($this->root . '/adm_my_files/backups', U\configuredBackupsDirectory($context), 'without the preference');

        $orgId = (int)$context['pdo']->query('SELECT MIN(org_id) FROM ' . TABLE_PREFIX . '_organizations')->fetchColumn();
        $context['pdo']->prepare('DELETE FROM ' . TABLE_PREFIX . '_preferences WHERE prf_name = ?')->execute(array('system_backup_path'));
        $context['pdo']->prepare('INSERT INTO ' . TABLE_PREFIX . '_preferences (prf_org_id, prf_name, prf_value) VALUES (?, ?, ?)')
            ->execute(array($orgId, 'system_backup_path', $this->root . '/outside/'));
        $this->assertSame($this->root . '/outside', U\configuredBackupsDirectory($context));
    }

    /**
     * @testdox A backup that an administrator asks for is the same kind of backup, made in one go
     */
    public function testBackupInOneGo(): void
    {
        $directory = U\backupsDirectory($this->root, '');
        $backup = U\createDatabaseBackup($this->context(), $this->root . self::LIBRARY, $directory, '6.0.0-beta.1');

        $this->assertMatchesRegularExpression('#^database_\d{4}-\d{2}-\d{2}_\d{6}_6\.0\.0-beta\.1_[a-f0-9]{32}\.zip$#', $backup['file']);
        $this->assertFalse($backup['encrypted'], 'no password for the backups is set');
        $this->assertSame(array($backup['file']), array_column(U\listBackups($directory), 'name'), 'nothing else is left in the directory');
        $zip = new ZipArchive();
        $zip->open(U\backupFile($directory, $backup['file']));
        $this->assertGreaterThan(10, $zip->numFiles, 'one file per table');
        $zip->close();
    }
}
