<?php
/**
 ***********************************************************************************************
 * This script imports a bunch of demo data into a Admidio database
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * lang : de (default) the language for the demo db
 ***********************************************************************************************
 */

// embed config file
if(is_file('../adm_my_files/config.php'))
{
    // search in path of version 3.x
    require_once(__DIR__ . '/../adm_my_files/config.php');
}
elseif(is_file('../config.php'))
{
    // search in path of version 1.x and 2.x
    require_once(__DIR__ . '/../config.php');
}
else
{
    exit('<p style="color: #cc0000;">Error: Config file not found!</p>');
}

require_once(__DIR__ . '/../adm_program/system/bootstrap.php');

// import of demo data must be enabled in config.php
if(!isset($gImportDemoData) || $gImportDemoData != 1)
{
    exit('<p style="color: #cc0000;">Error: Demo data could not be imported because you have
    not set the preference <strong>gImportDemoData</strong> in your configuration file.</p>
    <p style="color: #cc0000;">Please add the following line to your config.php:<br /><em>$gImportDemoData = 1;</em></p>');
}

/**
 * parts of this function are from get_backtrace out of phpBB3
 * @return string Return a nicely formatted backtrace (parts from the php manual by diz at ysagoon dot com)
 */
function getBacktrace()
{
    $output = '<div style="font-family: monospace;">';
    $backtrace = debug_backtrace();

    foreach ($backtrace as $number => $trace)
    {
        // We skip the first one, because it only shows this file/function
        if ($number === 0)
        {
            continue;
        }

        // Strip the current directory from path
        if (empty($trace['file']))
        {
            $trace['file'] = '';
        }
        else
        {
            $trace['file'] = str_replace(array(ADMIDIO_PATH, '\\'), array('', '/'), $trace['file']);
            $trace['file'] = substr($trace['file'], 1);
        }
        $args = array();

        // If include/require/include_once is not called, do not show arguments - they may contain sensible information
        if (!in_array($trace['function'], array('include', 'require', 'include_once'), true))
        {
            unset($trace['args']);
        }
        else
        {
            // Path...
            if (!empty($trace['args'][0]))
            {
                $argument = noHTML($trace['args'][0]);
                $argument = str_replace(array(ADMIDIO_PATH, '\\'), array('', '/'), $argument);
                $argument = substr($argument, 1);
                $args[] = '\''.$argument.'\'';
            }
        }

        $trace['class'] = array_key_exists('class', $trace) ? $trace['class'] : '';
        $trace['type']  = array_key_exists('type',  $trace) ? $trace['type'] : '';

        $output .= '<br />';
        $output .= '<strong>FILE:</strong> '.noHTML($trace['file']).'<br />';
        $output .= '<strong>LINE:</strong> '.((!empty($trace['line'])) ? $trace['line'] : '').'<br />';

        $output .= '<strong>CALL:</strong> '.noHTML($trace['class'].$trace['type'].$trace['function']).
            '('.(count($args) ? implode(', ', $args) : '').')<br />';
    }
    $output .= '</div>';

    return $output;
}

// Initialize and check the parameters
$getLanguage = admFuncVariableIsValid($_GET, 'lang', 'string', array('defaultValue' => 'de'));

// set runtime of script to 2 minutes because of the many work to do
// but no output of error message because of safe mode
@set_time_limit(1000);

// create an installation unique cookie prefix and remove special characters
$gCookiePraefix = 'ADMIDIO_' . $g_organization . '_' . $g_adm_db . '_' . $g_tbl_praefix;
$gCookiePraefix = str_replace(array(' ', '.', ',', ';', ':', '[', ']'), '_', $gCookiePraefix);

// start php session and remove session object with all data, so that
// all data will be read after the update
Session::start($gCookiePraefix);
unset($_SESSION['gCurrentSession']);

echo 'Start with installation ...<br />';

// create language and language data object to handle translations
$gLanguageData = new LanguageData($getLanguage);
$gL10n = new Language($gLanguageData);
$gL10n->addLanguagePath(ADMIDIO_PATH . '/demo_data/languages');

// copy content of folder adm_my_files to productive folder
$srcFolder = ADMIDIO_PATH . '/demo_data/adm_my_files';
$newFolder = ADMIDIO_PATH . FOLDER_DATA;

$myFilesFolder = new Folder($srcFolder);
$myFilesFolder->delete($newFolder.'/backup');
$myFilesFolder->delete($newFolder.'/download');
$myFilesFolder->delete($newFolder.'/photos');
$returnValue = $myFilesFolder->copy($newFolder);
if(!$returnValue)
{
    echo '<p style="color: #cc0000;">Folder <strong>adm_my_files</strong> is not writable.<br />
    No files could be copied to that folder.</p>';
    exit();
}
echo 'Folder <strong>adm_my_files</strong> was successfully copied.<br />';

// connect to database
try
{
    $db = new Database($gDbType, $g_adm_srv, $g_adm_port, $g_adm_db, $g_adm_usr, $g_adm_pw);
}
catch(AdmException $e)
{
    exit('<br />'.$gL10n->get('SYS_DATABASE_NO_LOGIN', $e->getText()));
}

if($gDbType === Database::PDO_ENGINE_MYSQL)
{
    // disable foreign key checks for mysql, so tables can easily deleted
    $db->queryPrepared('SET foreign_key_checks = 0');
}

/**
 * @param string   $filename The SQL filename (db.sql, data.sql)
 * @param Database $database
 */
function readAndExecuteSQLFromFile($filename, Database $database)
{
    global $g_tbl_praefix, $gL10n;

    $file = fopen($filename, 'rb');
    if ($file === false)
    {
        exit('<p style="color: #cc0000;">File <strong>data.sql</strong> could not be found in folder <strong>demo_data</strong>.</p>');
    }
    $content  = fread($file, filesize($filename));
    $sqlArray = explode(';', $content);
    fclose($file);

    echo 'Read file '.$filename.' ...<br />';

    foreach($sqlArray as $sql)
    {
        if(trim($sql) !== '')
        {
            // set prefix for all tables and execute sql statement
            $sql = str_replace('%PREFIX%', $g_tbl_praefix, $sql);

            if ($filename === 'data.sql')
            {
                // search for translation strings with the prefix DEMO or SYS and try replace them
                preg_match_all('/(DEMO_\w*)|(SYS_\w*)|(INS_\w*)|(DAT_\w*)/', $sql, $results);

                foreach($results[0] as $value)
                {
                    // if it's a string of a systemmail then html linefeeds must be replaced
                    if(admStrStartsWith($value, 'SYS_SYSMAIL_'))
                    {
                        // convert <br /> to a normal line feed
                        $convertedText = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/', chr(13).chr(10), $gL10n->get($value));
                    }
                    else
                    {
                        $convertedText = $gL10n->get($value);
                    }

                    // search for the exact value as a separate word and replace it with the translation
                    // in l10n the single quote is transformed in html entity, but we need the original sql escaped
                    $sql = preg_replace('/\b'.$value.'\b/', $database->escapeString(str_replace('&rsquo;', '\'', $convertedText)), $sql);
                }
            }

            $database->query($sql); // TODO add more params
        }
    }
}

readAndExecuteSQLFromFile('db.sql', $db);
readAndExecuteSQLFromFile('data.sql', $db);

// manipulate some dates so that it's suitable to the current date
echo 'Edit data of database ...<br />';
include_once(__DIR__ . '/data_edit.php');

// in postgresql all sequences must get a new start value because our inserts have given ids
if($gDbType === Database::PDO_ENGINE_PGSQL || $gDbType === 'postgresql') // for backwards compatibility "postgresql"
{
    $sql = 'SELECT relname
              FROM pg_class
             WHERE relkind = \'S\'';
    $sqlStatement = $db->queryPrepared($sql);

    while($relname = $sqlStatement->fetchColumn())
    {
        $sql = 'SELECT setval(\'' . $relname . '\', 1000000)';
        $db->queryPrepared($sql);
    }
}

// set parameter lang to default language for this installation
$sql = 'UPDATE '.$g_tbl_praefix.'_preferences
           SET prf_value = ? -- $getLanguage
         WHERE prf_name = \'system_language\'';
$db->queryPrepared($sql, array($getLanguage));

if($gDbType === Database::PDO_ENGINE_MYSQL)
{
    // activate foreign key checks, so database is consistent
    $db->queryPrepared('SET foreign_key_checks = 1');
}

echo 'Installation successful !<br />';

// read installed database version
$sql = 'SELECT 1 FROM ' . TBL_COMPONENTS;
if(!$db->queryPrepared($sql, array(), false))
{
    // in Admidio version 2 the database version was stored in preferences table
    $sql = 'SELECT prf_value
              FROM ' . $g_tbl_praefix . '_preferences
             WHERE prf_name   = \'db_version\'
               AND prf_org_id = 1';
    $pdoStatement = $db->queryPrepared($sql);
    $databaseVersion = $pdoStatement->fetchColumn();
}
else
{
    $systemComponent = new Component($db);
    $systemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
    $databaseVersion = $systemComponent->getValue('com_version');
}

echo '<p>Database and test-data have the Admidio version '.$databaseVersion.'.<br />
 Your files have Admidio version '.ADMIDIO_VERSION.'.<br /><br />
 Please perform an <a href="'.ADMIDIO_URL.'/adm_program/installation/update.php">update of your database</a>.</p>
 <p style="font-size: 9pt;">&copy; 2004 - 2017&nbsp;&nbsp;The Admidio team</p>';
