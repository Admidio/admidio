<?php
/**
 ***********************************************************************************************
 * This script imports a bunch of demo data into a database of Admidio
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * lang : de (default) the language for the demo db
 ***********************************************************************************************
 */

use Admidio\Components\Entity\Component;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Session\Entity\Session;

$rootPath = dirname(__DIR__);

// embed config file
$configPath = $rootPath . '/adm_my_files/config.php';
if (is_file($configPath)) {
    require_once($configPath);
} elseif (is_file($rootPath . '/config.php')) {
    exit('<div style="color: #cc0000;">Old v1.x or v2.x Config-File detected! Please update first to the latest v3.3 Version!</div>');
} else {
    echo '<p style="color: #cc0000;">Error: Config file not found!</p>';
    exit();
}

// import of demo data must be enabled in config.php
if (!isset($gImportDemoData) || !$gImportDemoData) {
    exit('<p style="color: #cc0000;">Error: Demo data could not be imported because you have
    not set the preference <strong>gImportDemoData</strong> in your configuration file.</p>
    <p style="color: #cc0000;">Please add the following line to your config.php:<br /><em>$gImportDemoData = true;</em></p>');
}

require_once($rootPath . '/adm_program/system/bootstrap/bootstrap.php');

// this must be declared for backwards compatibility. Can be removed if update scripts don't use it anymore
const TBL_DATES = TABLE_PREFIX . '_dates';
const TBL_USER_LOG = TABLE_PREFIX . '_user_log';

/**
 * Deletes all files and folder within adm_my_files except the config.php . After that all
 * files and folder of the demo_data folder adm_my_files will be copied to the original adm_my_files folder.
 * @throws RuntimeException
 * @throws UnexpectedValueException
 */
function prepareAdmidioDataFolder()
{
    $dh = opendir(ADMIDIO_PATH . FOLDER_DATA);

    while (($fileFolderName = readdir($dh)) !== false) {
        $fileFolderPath = ADMIDIO_PATH . FOLDER_DATA . '/' . $fileFolderName;
        if (is_dir($fileFolderPath) && $fileFolderName !== '.' && $fileFolderName !== '..') {
            // delete all folders except the log folder
            if ($fileFolderName !== 'logs') {
                FileSystemUtils::deleteDirectoryIfExists($fileFolderPath, true);
            } else {
                // delete all files and folders in the log folder except the log files
                $dhLogs = opendir($fileFolderPath);

                while (($logsFileFolderName = readdir($dhLogs)) !== false) {
                    $logsFileFolderPath = $fileFolderPath . '/' . $logsFileFolderName;
                    if (is_dir($logsFileFolderPath) && $logsFileFolderName !== '.' && $logsFileFolderName !== '..') {
                        FileSystemUtils::deleteDirectoryIfExists($logsFileFolderPath, true);
                    } elseif (is_file($logsFileFolderPath)) {
                        if ('log' !== strtolower(pathinfo($logsFileFolderPath, PATHINFO_EXTENSION))) {
                            FileSystemUtils::deleteFileIfExists($logsFileFolderPath);
                        }
                    }
                }
                closedir($dhLogs);
            }
        } elseif (is_file($fileFolderPath) && $fileFolderName !== 'config.php' && $fileFolderName !== '.htaccess') {
            // delete all files except the config and htaccess files
            FileSystemUtils::deleteFileIfExists($fileFolderPath);
        }
    }
    closedir($dh);

    FileSystemUtils::copyDirectory(ADMIDIO_PATH . '/demo_data/adm_my_files', ADMIDIO_PATH . FOLDER_DATA, array('overwriteContent' => true));
}

/**
 * @param bool $enable
 */
function toggleForeignKeyChecks(bool $enable)
{
    global $gDb;

    if (DB_ENGINE === Database::PDO_ENGINE_MYSQL) {
        // disable foreign key checks for mysql, so tables can easily be deleted
        $sql = 'SET foreign_key_checks = ' . (int) $enable;
        $gDb->queryPrepared($sql);
    }
}

/**
 * @param array<int,string> $sqlStatements
 * @param string $filename
 */
function executeSqlStatements(array $sqlStatements, string $filename)
{
    global $gDb, $gL10n;

    foreach ($sqlStatements as $sqlStatement) {
        if ($filename === 'data.sql') {
            // search for translation strings with the prefix DDT or SYS and try to replace them
            preg_match_all('/(DDT_\w*)|(SYS_\w*)|(INS_\w*)|(DAT_\w*)/', $sqlStatement, $results);

            foreach ($results[0] as $value) {
                // if it's a string of a systemmail then html line feeds must be replaced
                if (str_starts_with($value, 'SYS_SYSMAIL_')) {
                    // convert <br /> to a normal line feed
                    $convertedText = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/', chr(13).chr(10), $gL10n->get($value));
                } else {
                    $convertedText = $gL10n->get($value);
                }

                // search for the exact value as a separate word and replace it with the translation
                // in l10n the single quote is transformed in html entity, but we need the original sql escaped
                $escapedText = $gDb->escapeString(str_replace('&rsquo;', '\'', $convertedText));
                $sqlStatement = preg_replace('/\b'.$value.'\b/', substr($escapedText, 1, strlen($escapedText) - 2), $sqlStatement);
            }
        }

        $gDb->queryPrepared($sqlStatement);
    }
}

/**
 * @param string $filename The SQL filename (db.sql, data.sql)
 */
function readAndExecuteSQLFromFile(string $filename)
{
    $sqlFilePath = __DIR__ . '/' . $filename;

    echo 'Reading file "'.$filename.'" ...<br />';

    try {
        $sqlStatements = Database::getSqlStatementsFromSqlFile($sqlFilePath);
    } catch (RuntimeException $exception) {
        exit('<p style="color: #cc0000;">' . $exception->getMessage() . ' File-Path: ' . $sqlFilePath . '</p>');
    }

    echo 'Read file "'.$filename.'" finished!<br />';
    echo 'Executing "'.$filename.'" SQL-Statements ...<br />';

    executeSqlStatements($sqlStatements, $filename);

    echo 'Executing "'.$filename.'" SQL-Statements finished!<br />';
}

function resetPostgresSequences()
{
    global $gDb;

    if (DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
        $sql = 'SELECT relname
                  FROM pg_class
                 WHERE relkind = \'S\'';
        $pdoStatement = $gDb->queryPrepared($sql);

        while ($relName = $pdoStatement->fetchColumn()) {
            $sql = 'SELECT setval(\'' . $relName . '\', 1000000)';
            $gDb->queryPrepared($sql);
        }
    }
}

/**
 * @param string $language
 */
function setInstallationLanguage(string $language)
{
    global $gDb;

    $sql = 'UPDATE '.TBL_PREFERENCES.'
               SET prf_value = ? -- $language
             WHERE prf_name = \'system_language\'';
    $gDb->queryPrepared($sql, array($language));
}

/**
 * @return string
 * @throws Exception
 */
function getInstalledDbVersion(): string
{
    global $gDb;

    $sql = 'SELECT 1 FROM ' . TBL_COMPONENTS;
    $pdoStatement = $gDb->queryPrepared($sql, array(), false);

    if ($pdoStatement === false) {
        // in Admidio version 2 the database version was stored in preferences table
        $sql = 'SELECT prf_value
                  FROM ' . TBL_PREFERENCES . '
                 WHERE prf_name   = \'db_version\'
                   AND prf_org_id = 1';
        $pdoStatement = $gDb->queryPrepared($sql);

        return $pdoStatement->fetchColumn();
    }

    $systemComponent = new Component($gDb);
    $systemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));

    return $systemComponent->getValue('com_version');
}

/**
 * @param string $language
 * @throws RuntimeException
 */
function doInstallation(string $language)
{
    global $gDb, $gL10n; // necessary for "data_edit.php"

    // set runtime of script to 2 minutes because of the many work to do
    PhpIniUtils::startNewExecutionTimeLimit(120);

    // disable foreign key checks for mysql, so tables can easily be deleted
    toggleForeignKeyChecks(false);

    readAndExecuteSQLFromFile('db.sql');
    readAndExecuteSQLFromFile('data.sql');

    // manipulate some dates so that it's suitable to the current date
    echo 'Edit data of database ...<br />';
    require_once(__DIR__ . '/data_edit.php');

    // in postgresql all sequences must get a new start value because our inserts have given ids
    resetPostgresSequences();

    // set parameter lang to default language for this installation
    setInstallationLanguage($language);

    // activate foreign key checks, so database is consistent
    toggleForeignKeyChecks(true);
}

// Initialize and check the parameters
$getLanguage = admFuncVariableIsValid($_GET, 'lang', 'string', array('defaultValue' => 'de'));

// start php session and remove session object with all data, so that
// all data will be read after the update
try {
    Session::start(COOKIE_PREFIX);
} catch (RuntimeException $exception) {
    // TODO
}
unset($_SESSION['gCurrentSession']);

// create language object to handle translations
$gL10n = new Language($getLanguage);
$gL10n->addLanguageFolderPath(ADMIDIO_PATH . '/demo_data/languages');

// copy content of folder adm_my_files to productive folder
try {
    prepareAdmidioDataFolder();
} catch (RuntimeException $exception) {
    echo '<p style="color: #cc0000;">' . $exception->getMessage() . '</p>';
    exit();
}
echo 'Folder <strong>adm_my_files</strong> was successfully copied.<br />';

// connect to database
try {
    $gDb = Database::createDatabaseInstance();
} catch (Exception $e) {
    exit('<br />'.$gL10n->get('SYS_DATABASE_NO_LOGIN', array($e->getMessage())));
}

echo 'Start installing ...<br />';

doInstallation($getLanguage);

echo 'Installation successful!<br />';

// read installed database version
try {
    $databaseVersion = getInstalledDbVersion();
} catch (Exception $e) {
    exit('<br />'.$e->getMessage());
}

echo '<p>Database and test-data have the Admidio version '.$databaseVersion.'.<br />
 Your files have Admidio version '.ADMIDIO_VERSION.'.<br /><br />
 Please perform an <a href="'.ADMIDIO_URL . FOLDER_INSTALLATION . '/update.php">update of your database</a>.</p>
 <p style="font-size: 9pt;">&copy; 2004 - ' . date('Y') . '&nbsp;&nbsp;The Admidio team</p>';
