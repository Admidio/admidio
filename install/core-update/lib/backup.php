<?php
/**
 * The backups of an installation in adm_my_files/backups/: the backups of the database, which an
 * update makes before it changes anything and an administrator makes in the preferences or with
 * "admidio database:backup", and the archives of the files an update has replaced or removed
 * (kept-files archives). They are never removed automatically.
 *
 * A backup of the database is a ZIP with one compressed file per table (mysqldump-php) or the
 * output of pg_dump, encrypted with AES-256 when a password for the backups is set. Admidio uses
 * these functions as well, so that there is one kind of backup.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use PDO;
use Throwable;

/** The name of a backup: what it holds, the time it was made, versions, and a random part. */
const BACKUP_NAME_PATTERN = '/^(database|files)_\d{4}-\d{2}-\d{2}_\d{6}_[A-Za-z0-9._-]+_[a-f0-9]{6}\.zip$/';

function backupsDirectory(string $root): string
{
    return $root . '/adm_my_files/backups';
}

/**
 * The path of a backup in the directory of the backups.
 * @throws UpdaterException The name is not the name of a backup.
 */
function backupFile(string $root, string $name): string
{
    if (preg_match(BACKUP_NAME_PATTERN, $name) !== 1) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_INVALID', '"' . $name . '" is not the name of a backup.');
    }

    return backupsDirectory($root) . '/' . $name;
}

/** A new name for a backup of the database or of the kept files, with the given versions. */
function backupName(string $kind, string $versions): string
{
    return $kind . '_' . date('Y-m-d_His') . '_' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $versions) . '_' . bin2hex(random_bytes(3)) . '.zip';
}

/**
 * How the database can be backed up: "mysqldump" (mysqldump-php from $library), "pg_dump" (the
 * PostgreSQL program, if PHP may run it) or "" when it cannot.
 * @param array<string,mixed> $config
 */
function databaseBackupMethod(array $config, string $library): string
{
    if ($config['dbType'] === 'mysql') {
        return $library !== '' && is_file($library) ? 'mysqldump' : '';
    }

    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (!function_exists('exec') || in_array('exec', $disabled, true)) {
        return '';
    }
    @exec('pg_dump --version 2>&1', $output, $status);

    return $status === 0 ? 'pg_dump' : '';
}

/**
 * The password that protects a backup of the database once it leaves the server: the one given
 * to the command line (--dump-password-env), else $gCoreUpdateDumpPassword of config.php, else the
 * preference system_update_dump_password of the first organization; "" for none. It is read anew
 * whenever it is needed, also to restore the backup, because it is never written to the state or
 * the log.
 * @param array<string,mixed> $ctx
 */
function dumpPassword(array $ctx): string
{
    if ((string)($ctx['dumpPassword'] ?? '') !== '') {
        return (string)$ctx['dumpPassword'];
    }
    if ($ctx['config']['dumpPassword'] !== '') {
        return $ctx['config']['dumpPassword'];
    }
    if (!($ctx['pdo'] ?? null) instanceof PDO) {
        return '';
    }

    try {
        $tablePrefix = $ctx['config']['tablePrefix'];
        $row = dbRow($ctx['pdo'], 'SELECT prf_value FROM ' . $tablePrefix . '_preferences WHERE prf_name = ?
            AND prf_org_id = (SELECT MIN(org_id) FROM ' . $tablePrefix . '_organizations)', array('system_update_dump_password'));
    } catch (Throwable) {
        return '';
    }

    return (string)($row['prf_value'] ?? '');
}

/** Whether the ZIP extension of this PHP can encrypt with AES-256. */
function canEncryptZip(): bool
{
    return defined('ZipArchive::EM_AES_256') && method_exists(\ZipArchive::class, 'setEncryptionName');
}

/**
 * Back up the next tables into files of $directory, until the deadline has passed; one table is
 * always done, however long it takes. $progress keeps the tables and the cursor between the calls
 * of a backup that is made in several requests. PostgreSQL is backed up by pg_dump in one go.
 * @param array<string,mixed> $ctx
 * @param array{tables?: array<int,string>, cursor?: int} $progress
 * @return bool Whether every table is backed up.
 */
function backUpTables(array $ctx, string $method, string $library, string $directory, array &$progress, float $deadline): bool
{
    $config = $ctx['config'];
    ensureDirectory($directory);

    if ($method === 'pg_dump') {
        $file = $directory . '/' . $config['dbName'] . '.sql.gz';
        putenv('PGPASSWORD=' . $config['dbPassword']);
        exec('pg_dump --host=' . escapeshellarg($config['dbHost']) . ($config['dbPort'] !== null ? ' --port=' . (int)$config['dbPort'] : '')
            . ' --username=' . escapeshellarg($config['dbUser']) . ' --dbname=' . escapeshellarg($config['dbName'])
            . ' --clean --if-exists --no-owner --compress=6 --table=' . escapeshellarg($config['tablePrefix'] . '_*')
            . ' --file=' . escapeshellarg($file) . ' 2>&1', $output, $status);
        putenv('PGPASSWORD');
        if ($status !== 0) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUEST_FAILED', 'pg_dump failed: ' . implode(' ', $output), array());
        }

        return true;
    }

    if (!isset($progress['tables'])) {
        $statement = $ctx['pdo']->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()
            AND table_type = ? AND table_name LIKE ? ORDER BY table_name');
        $statement->execute(array('BASE TABLE', str_replace('_', '\\_', $config['tablePrefix']) . '\\_%'));
        $progress['tables'] = $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    if (!class_exists(\Ifsnop\Mysqldump\Mysqldump::class, false)) {
        require_once $library;
    }
    $dsn = 'mysql:host=' . $config['dbHost'] . ($config['dbPort'] !== null ? ';port=' . $config['dbPort'] : '') . ';dbname=' . $config['dbName'];
    $cursor = (int)($progress['cursor'] ?? 0);
    do {
        $table = $progress['tables'][$cursor] ?? null;
        if ($table === null) {
            break;
        }
        $dump = new \Ifsnop\Mysqldump\Mysqldump($dsn, $config['dbUser'], $config['dbPassword'], array(
            'include-tables' => array($table),
            'compress' => \Ifsnop\Mysqldump\Mysqldump::GZIP,
            'add-drop-table' => true,
            'default-character-set' => \Ifsnop\Mysqldump\Mysqldump::UTF8MB4
        ));
        $dump->start(sprintf('%s/%03d_%s.sql.gz', $directory, $cursor + 1, $table));
        $cursor++;
    } while (microtime(true) < $deadline);
    $progress['cursor'] = $cursor;

    return $cursor >= count($progress['tables']);
}

/**
 * Pack the table files of $directory into a new backup in the directory of the backups, encrypted
 * with AES-256 when a password for the backups is set and this PHP can, and remove $directory.
 * @param array<string,mixed> $ctx
 * @return array{file: string, encrypted: bool} The name of the backup.
 */
function packDatabaseBackup(array $ctx, string $directory, string $version): array
{
    $root = $ctx['config']['rootPath'];
    ensureDirectory(backupsDirectory($root));
    $name = backupName('database', $version);
    $password = canEncryptZip() ? dumpPassword($ctx) : '';

    $zip = new \ZipArchive();
    if ($zip->open(backupFile($root, $name), \ZipArchive::CREATE) !== true) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', $name . ' cannot be created.');
    }
    // The table files are compressed already.
    foreach (glob($directory . '/*.sql.gz') ?: array() as $file) {
        $zip->addFile($file, basename($file));
        $zip->setCompressionName(basename($file), \ZipArchive::CM_STORE);
        if ($password !== '' && !$zip->setEncryptionName(basename($file), \ZipArchive::EM_AES_256, $password)) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', $name . ' cannot be encrypted.');
        }
    }
    if (!$zip->close()) {
        throw UpdaterException::withDetail('SYS_CORE_UPDATE_FILES_NOT_WRITABLE', $name . ' cannot be written.');
    }
    deleteDirectory($directory);

    return array('file' => $name, 'encrypted' => $password !== '');
}

/**
 * Back up the database in one go, for the administrator who asks for a backup.
 * @param array<string,mixed> $ctx
 * @return array{file: string, encrypted: bool} The name of the backup.
 * @throws UpdaterException The database cannot be backed up here, or the backup failed.
 */
function createDatabaseBackup(array $ctx, string $library, string $version): array
{
    $method = databaseBackupMethod($ctx['config'], $library);
    if ($method === '') {
        throw new UpdaterException('SYS_CORE_UPDATE_BACKUP_NOT_POSSIBLE');
    }

    $directory = backupsDirectory($ctx['config']['rootPath']) . '/.tables-' . bin2hex(random_bytes(6));
    try {
        $progress = array();
        while (!backUpTables($ctx, $method, $library, $directory, $progress, microtime(true) + 60)) {
            // a large database takes several rounds
        }

        return packDatabaseBackup($ctx, $directory, $version);
    } finally {
        deleteDirectory($directory);
    }
}

/**
 * The backups in the directory of the backups, the newest first.
 * @return array<int,array{name: string, kind: string, size: int, time: int}>
 */
function listBackups(string $root): array
{
    $backups = array();
    foreach (glob(backupsDirectory($root) . '/*.zip') ?: array() as $file) {
        $name = basename($file);
        if (preg_match(BACKUP_NAME_PATTERN, $name, $matches) === 1) {
            $backups[] = array('name' => $name, 'kind' => $matches[1], 'size' => (int)filesize($file), 'time' => (int)filemtime($file));
        }
    }
    usort($backups, static fn(array $a, array $b): int => array($b['time'], $b['name']) <=> array($a['time'], $a['name']));

    return $backups;
}
