<?php
/**
 * Restoring the database from the backup of the updater after a failed database update
 * (install/core-update/lib/phase-b.php), against the database server of the test suite. The test
 * works on tables of its own prefix through a connection of its own: restoring runs DDL, which would
 * commit the transaction of a DatabaseTestCase.
 */

namespace Admidio\Tests\Integration\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;
use PDO;

final class DatabaseRestoreTest extends AdmidioTestCase
{
    private const PREFIX = 'zzrestore';
    private const LIBRARY = '/vendor/ifsnop/mysqldump-php/src/Ifsnop/Mysqldump/Mysqldump.php';

    private string $root = '';
    private PDO $pdo;
    /** @var array<string,mixed> */
    private array $context = array();

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 2) . '/env.php';
        admidioTestLoadEnvironment();
        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $database = admidioTestDatabaseConfig();
        if ($database['engine'] === 'postgres') {
            $this->markTestSkipped('PostgreSQL is restored by psql, which the test environment does not provide.');
        }

        $this->root = sys_get_temp_dir() . '/adm_restore_' . uniqid();
        mkdir(dirname($this->root . self::LIBRARY), 0777, true);
        mkdir($this->root . '/adm_my_files', 0777, true);
        copy(dirname(__DIR__, 3) . self::LIBRARY, $this->root . self::LIBRARY);

        $config = U\configFromValues($this->root, array(
            'gDbType' => 'mysql',
            'g_adm_srv' => $database['host'],
            'g_adm_port' => $database['port'],
            'g_adm_db' => $database['database'],
            'g_adm_usr' => $database['user'],
            'g_adm_pw' => $database['password'],
            'g_tbl_praefix' => self::PREFIX
        ));
        $this->pdo = U\connectDatabase($config);
        $this->context = array('config' => $config, 'pdo' => $this->pdo, 'texts' => array('language' => 'en', 'texts' => array()));
        $this->dropTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->dropTables();
        }
        U\releaseLock();
        U\deleteDirectory($this->root);

        parent::tearDown();
    }

    private function dropTables(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (array('members', 'roles', 'columns') as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS ' . self::PREFIX . '_' . $table);
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function rows(string $table): array
    {
        return $this->pdo->query('SELECT * FROM ' . self::PREFIX . '_' . $table . ' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The installation before the update, two tables with a foreign key, backed up by the updater;
     * then the failed database update changes data, drops a table and creates a new one.
     * @param array<string,mixed> $context
     * @return array{0: array<string,mixed>, 1: array<string,array<int,array<string,mixed>>>} The state in
     *         database_failed and the tables at the backup.
     */
    private function backUpAndFail(array $context): array
    {
        $this->pdo->exec('CREATE TABLE ' . self::PREFIX . '_roles (rol_id integer PRIMARY KEY, rol_name varchar(50))');
        $this->pdo->exec('CREATE TABLE ' . self::PREFIX . '_members (mem_id integer PRIMARY KEY, mem_rol_id integer,
            FOREIGN KEY (mem_rol_id) REFERENCES ' . self::PREFIX . '_roles (rol_id))');
        $this->pdo->exec("INSERT INTO " . self::PREFIX . "_roles VALUES (1, 'Administrator'), (2, 'Mitglied; mit Semikolon')");
        $this->pdo->exec('INSERT INTO ' . self::PREFIX . '_members VALUES (10, 1), (11, 2)');
        $before = array('roles' => $this->rows('roles'), 'members' => $this->rows('members'));

        $state = U\createUpdate($this->root, array('installedVersion' => '5.0.17'));
        file_put_contents(U\workspace($this->root, $state['updateId']) . '/plan.json', '[]');
        $state = array_merge($state, array('state' => 'dumping', 'dumpMethod' => 'mysqldump', 'cursor' => 0));
        for ($request = 0; $request < 20 && $state['state'] === 'dumping'; $request++) {
            $state = U\dumpPart($context, $state);
        }
        U\writeState($this->root, $state);

        $this->pdo->exec("UPDATE " . self::PREFIX . "_roles SET rol_name = 'changed'");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec('DROP TABLE ' . self::PREFIX . '_members');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $this->pdo->exec('CREATE TABLE ' . self::PREFIX . '_columns (col_id integer PRIMARY KEY)');

        return array(U\updateState($this->root, $state['updateId'],
            static fn(array $s): array => array_merge($s, array('state' => 'database_failed', 'databaseStarted' => true))), $before);
    }

    /**
     * @testdox The database is restored as it was at the backup, and the tables of the new version are removed
     */
    public function testRestore(): void
    {
        [$state, $before] = $this->backUpAndFail($this->context);

        $this->assertSame('mysql', U\restoreMethod($this->context, U\readState($this->root, $state['updateId'])));
        $state = U\phaseBRestore($this->context, $state['updateId'], false);
        $this->assertSame('restoring_database', $state['state']);
        for ($request = 0; $request < 20 && $state['state'] === 'restoring_database'; $request++) {
            $state = U\restoreDatabasePart($this->context, $state);
        }

        $this->assertSame('rolling_back', $state['state'], 'then the files are restored');
        $this->assertSame($before, array('roles' => $this->rows('roles'), 'members' => $this->rows('members')));
        $this->assertFalse($this->pdo->query("SHOW TABLES LIKE '" . self::PREFIX . "\\_columns'")->fetchColumn(), 'the table of the new version is removed');
        $this->assertStringContainsString('database restored from ' . $state['dumpFile'] . ' (2 tables)',
            (string)file_get_contents(U\workspace($this->root, $state['updateId']) . '/update.log'));
    }

    /**
     * @testdox A backup with a password is encrypted, and only restored with that password
     */
    public function testEncryptedBackup(): void
    {
        if (!U\canEncryptZip()) {
            $this->markTestSkipped('The ZIP extension of this PHP cannot encrypt with AES-256.');
        }

        $withPassword = array_merge($this->context, array('dumpPassword' => 'Backup-Passw0rd'));
        [$state, $before] = $this->backUpAndFail($withPassword);

        $this->assertTrue($state['dumpEncrypted']);
        $this->assertStringNotContainsString('Backup-Passw0rd', (string)file_get_contents(U\workspace($this->root, $state['updateId']) . '/state.json'));
        $zip = new \ZipArchive();
        $zip->open(U\updateBackupFile($this->root, $state, $state['dumpFile']));
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $this->assertSame(\ZipArchive::EM_AES_256, $zip->statIndex($index)['encryption_method'], $zip->getNameIndex($index));
        }
        $this->assertFalse($zip->getFromIndex(0), 'not readable without the password');
        $zip->close();

        // Without the password the updater cannot restore the database, and a restore that is forced
        // fails before it removes anything.
        $this->assertSame('', U\restoreMethod($this->context, $state));
        $forced = array_merge($state, array('state' => 'restoring_database', 'restoreMethod' => 'mysql', 'cursor' => 0));
        try {
            U\restoreDatabasePart($this->context, $forced);
            $this->fail('an unreadable backup was restored');
        } catch (U\UpdaterException $exception) {
            $this->assertStringContainsString('cannot be read', $exception->getDetail());
        }
        $this->assertNotFalse($this->pdo->query("SHOW TABLES LIKE '" . self::PREFIX . "\\_columns'")->fetchColumn(), 'nothing was removed');

        $this->assertSame('mysql', U\restoreMethod($withPassword, $state));
        $state = U\phaseBRestore($withPassword, $state['updateId'], false);
        for ($request = 0; $request < 20 && $state['state'] === 'restoring_database'; $request++) {
            $state = U\restoreDatabasePart($withPassword, $state);
        }
        $this->assertSame('rolling_back', $state['state']);
        $this->assertSame($before, array('roles' => $this->rows('roles'), 'members' => $this->rows('members')));
    }
}
