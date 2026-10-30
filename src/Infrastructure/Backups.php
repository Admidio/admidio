<?php

namespace Admidio\Infrastructure;

use AdmidioCoreUpdater as Updater;
use AdmidioCoreUpdater\UpdaterException;

// The backups of Admidio and of the core updater have one implementation, the updater's own: it has
// to back up the database while src/ and vendor/ are being replaced.
require_once ADMIDIO_PATH . '/install/core-update/lib/common.php';
require_once ADMIDIO_PATH . '/install/core-update/lib/backup.php';

/**
 * The backups of the installation in the directory of the preference system_backup_path, by default
 * adm_my_files/backups/: backups of the database, which an administrator makes and every core
 * update makes before it changes anything, and the archives of the files an update has replaced or
 * removed. A backup of the database is a ZIP with one
 * compressed file per table (PostgreSQL: the output of pg_dump), encrypted with AES-256 when a
 * password for the backups is set. Nothing is removed automatically.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Backups
{
    /**
     * Whether the database of this installation can be backed up here: MySQL and MariaDB always,
     * PostgreSQL if PHP may run pg_dump.
     * @return bool
     */
    public static function isDatabaseBackupPossible(): bool
    {
        return Updater\databaseBackupMethod(self::context(false)['config'], self::library()) !== '';
    }

    /**
     * Back up the database.
     * @return string The name of the new backup.
     * @throws Exception The database cannot be backed up here, or the backup failed.
     */
    public static function createDatabaseBackup(): string
    {
        try {
            return Updater\createDatabaseBackup(self::context(true), self::library(), self::getDirectory(),
                Updater\filesVersion(ADMIDIO_PATH))['file'];
        } catch (UpdaterException $exception) {
            throw new Exception(Updater\isTextKey($exception->getMessage()) ? $exception->getMessage() : 'SYS_DATABASE_BACKUP_FAILED',
                $exception->getParams());
        }
    }

    /**
     * The backups, the newest first: their name, "database" or "files", size in bytes and time.
     * @return array<int,array{name: string, kind: string, size: int, time: int}>
     */
    public static function getList(): array
    {
        return Updater\listBackups(self::getDirectory());
    }

    /**
     * The directory of the backups. The setting belongs to the whole installation, so it is read
     * from the first organization, as the core updater reads it.
     * @return string
     * @throws Exception
     */
    public static function getDirectory(): string
    {
        global $gDb;

        $sql = 'SELECT prf_value
                  FROM ' . TBL_PREFERENCES . '
                 WHERE prf_name = ?
                   AND prf_org_id = (SELECT MIN(org_id) FROM ' . TBL_ORGANIZATIONS . ')';
        $configured = $gDb->queryPrepared($sql, array('system_backup_path'))->fetchColumn();

        return Updater\backupsDirectory(ADMIDIO_PATH, (string)$configured);
    }

    /**
     * The path of a backup that exists.
     * @param string $name
     * @return string
     * @throws Exception There is no backup of this name.
     */
    public static function getFile(string $name): string
    {
        try {
            $file = Updater\backupFile(self::getDirectory(), $name);
        } catch (UpdaterException) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        if (!is_file($file)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        return $file;
    }

    /**
     * Remove a backup.
     * @param string $name
     * @return void
     * @throws Exception There is no backup of this name, or it cannot be removed.
     */
    public static function delete(string $name): void
    {
        if (!@unlink(self::getFile($name))) {
            throw new Exception('SYS_FILE_NOT_DELETED', array($name));
        }
    }

    /**
     * The context of the updater's backup functions, from the configuration Admidio has read: also
     * a configuration file other than adm_my_files/config.php is the one of the command line.
     * @param bool $withDatabase Whether the password of the preferences is read from the database.
     * @return array<string,mixed>
     */
    private static function context(bool $withDatabase): array
    {
        $values = array();
        foreach (array('gDbType', 'g_adm_srv', 'g_adm_port', 'g_adm_db', 'g_adm_usr', 'g_adm_pw', 'g_tbl_praefix', 'g_root_path',
            'gTimezone') as $name) {
            if (isset($GLOBALS[$name])) {
                $values[$name] = $GLOBALS[$name];
            }
        }
        $config = Updater\configFromValues(ADMIDIO_PATH, $values);

        return array('config' => $config, 'pdo' => $withDatabase ? Updater\connectDatabase($config) : null);
    }

    /**
     * The file of mysqldump-php of this installation.
     * @return string
     */
    private static function library(): string
    {
        return ADMIDIO_PATH . '/vendor/ifsnop/mysqldump-php/src/Ifsnop/Mysqldump/Mysqldump.php';
    }
}
