<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class to implement useful method for installation and update process.
 */
class InstallationUtils
{
    /**
     * Checks whether the minimum requirements for PHP and MySQL have been met.
     * @param Database $database Object of the database that should be checked. A connection should be established.
     * @return string Returns an error text if the database doesn't meet the necessary requirements.
     * @throws Exception
     */
    public static function checkDatabaseVersion(Database $database): string
    {
        global $gL10n;

        // check database version
        if (version_compare($database->getVersion(), $database->getMinimumRequiredVersion(), '<')) {
            return $gL10n->get('SYS_DATABASE_VERSION') . ': <strong>' . $database->getVersion() . '</strong><br /><br />' .
                $gL10n->get('INS_WRONG_MYSQL_VERSION', array(ADMIDIO_VERSION_TEXT, $database->getMinimumRequiredVersion(),
                    '<a href="' . ADMIDIO_HOMEPAGE . 'download.php">', '</a>'));
        }

        return '';
    }

    /**
     * Method will check if the folder adm_my_files is writable for the PHP user. The subfolders **ecard_templates**
     * **logs**, **mail_templates** and **temp** will be checked if they exist and created if they don't exist.
     * @return void
     * @throws AdmException
     */
    public static function checkFolderPermissions()
    {
        // check if adm_my_files has write permissions
        if (!is_writable(ADMIDIO_PATH . FOLDER_DATA)) {
            // try to set write permissions otherwise throw an exception
            try {
                FileSystemUtils::chmodDirectory(ADMIDIO_PATH . FOLDER_DATA);
            } catch (RuntimeException | UnexpectedValueException $e) {
                throw new AdmException('SYS_FOLDER_NOT_WRITABLE', array(FOLDER_DATA));
            }
        }

        // now check some sub folders and create them if necessary
        try {
            FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates');
        } catch (RuntimeException | UnexpectedValueException $e) {
            throw new AdmException('SYS_FOLDER_NOT_WRITABLE', array(FOLDER_DATA . '/ecard_templates'));
        }
        try {
            FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/logs');
        } catch (RuntimeException | UnexpectedValueException $e) {
            throw new AdmException('SYS_FOLDER_NOT_WRITABLE', array(FOLDER_DATA . '/logs'));
        }
        try {
            FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates');
        } catch (RuntimeException | UnexpectedValueException $e) {
            throw new AdmException('SYS_FOLDER_NOT_WRITABLE', array(FOLDER_DATA . '/mail_templates'));
        }
        try {
            FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_TEMP_DATA);
        } catch (RuntimeException | UnexpectedValueException $e) {
            throw new AdmException('SYS_FOLDER_NOT_WRITABLE', array(FOLDER_TEMP_DATA));
        }
    }

    /**
     * @param Database $db
     */
    public static function disableSoundexSearchIfPgSql(Database $db)
    {
        if (DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
            // soundex is not a default function in PostgresSQL
            $sql = 'UPDATE ' . TBL_PREFERENCES . '
                   SET prf_value = false
                 WHERE prf_name = \'system_search_similar\'';
            $db->queryPrepared($sql);
        }
    }

    /**
     * Read data from sql file and execute all statements to the current database
     * @param Database $db
     * @param string $sqlFileName
     * @return true|string Returns true no error occurs ales error message is returned
     */
    public static function querySqlFile(Database $db, string $sqlFileName)
    {
        global $gL10n;

        $sqlPath = ADMIDIO_PATH . FOLDER_INSTALLATION . '/db_scripts/';
        $sqlFilePath = $sqlPath . $sqlFileName;

        if (!is_file($sqlFilePath)) {
            return $gL10n->get('INS_DATABASE_FILE_NOT_FOUND', array($sqlFileName, $sqlPath));
        }

        try {
            $sqlStatements = Database::getSqlStatementsFromSqlFile($sqlFilePath);
        } catch (RuntimeException $exception) {
            return $gL10n->get('INS_ERROR_OPEN_FILE', array($sqlFilePath));
        }

        foreach ($sqlStatements as $sqlStatement) {
            $db->queryPrepared($sqlStatement);
        }

        return true;
    }
}
