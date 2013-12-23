<?php
/******************************************************************************
 * This script imports a bunch of data into a demo database
 *
 * Copyright    : © 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * lang : de (default) the language for the demo db
 *
 *****************************************************************************/

include('../config.php');
include('../adm_program/system/constants.php');
include('../adm_program/system/function.php');
include('../adm_program/system/string.php');

// default database type should be MySQL
if(!isset($gDbType))
{
    $gDbType = 'mysql';
}

// parts of this funtion are from get_backtrace out of phpBB3
// Return a nicely formatted backtrace (parts from the php manual by diz at ysagoon dot com)

function getBacktrace()
{
    $output = '<div style="font-family: monospace;">';
    $backtrace = debug_backtrace();
    $path = SERVER_PATH;

    foreach ($backtrace as $number => $trace)
    {
        // We skip the first one, because it only shows this file/function
        if ($number == 0)
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
            $trace['file'] = str_replace(array($path, '\\'), array('', '/'), $trace['file']);
            $trace['file'] = substr($trace['file'], 1);
        }
        $args = array();

        // If include/require/include_once is not called, do not show arguments - they may contain sensible information
        if (!in_array($trace['function'], array('include', 'require', 'include_once')))
        {
            unset($trace['args']);
        }
        else
        {
            // Path...
            if (!empty($trace['args'][0]))
            {
                $argument = htmlentities($trace['args'][0]);
                $argument = str_replace(array($path, '\\'), array('', '/'), $argument);
                $argument = substr($argument, 1);
                $args[] = "'{$argument}'";
            }
        }

        $trace['class'] = (!isset($trace['class'])) ? '' : $trace['class'];
        $trace['type'] = (!isset($trace['type'])) ? '' : $trace['type'];

        $output .= '<br />';
        $output .= '<b>FILE:</b> ' . htmlentities($trace['file']) . '<br />';
        $output .= '<b>LINE:</b> ' . ((!empty($trace['line'])) ? $trace['line'] : '') . '<br />';

        $output .= '<b>CALL:</b> ' . htmlentities($trace['class'] . $trace['type'] . $trace['function']) . '(' . ((sizeof($args)) ? implode(', ', $args) : '') . ')<br />';
    }
    $output .= '</div>';
    return $output;
}

// Initialize and check the parameters
$getLanguage = admFuncVariableIsValid($_GET, 'lang', 'string', 'de');

// set runtime of script to 2 minutes because of the many work to do
// but no output of error message because of safe mode
@set_time_limit(300); 

echo 'Start with installation ...<br />';

// create language and language data object to handle translations
$gL10n = new Language();
$gLanguageData = new LanguageData($getLanguage);
$gL10n->addLanguageData($gLanguageData);
$gL10n->addLanguagePath(SERVER_PATH. '/db_scripts/languages');

// copy content of folder adm_my_files to productive folder
$srcFolder = SERVER_PATH. '/db_scripts/adm_my_files';
$newFolder = SERVER_PATH. '/adm_my_files';

$myFilesFolder = new Folder($srcFolder);
$b_return = $myFilesFolder->delete($newFolder.'/backup');
$b_return = $myFilesFolder->delete($newFolder.'/download');
$b_return = $myFilesFolder->delete($newFolder.'/photos');
$b_return = $myFilesFolder->copy($newFolder);
if($b_return == false)
{
    echo 'Folder <strong>adm_my_files</strong> is not writeable.<br />
    No files could be copied to that folder.';
    exit();
}
echo 'Folder <strong>adm_my_files</strong> was successfully copied.<br />';

 // connect to database
$db = Database::createDatabaseObject($gDbType);
$connection = $db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);


if($gDbType == 'mysql')
{
    // disable foreign key checks for mysql, so tables can easily deleted
    $sql = 'SET foreign_key_checks = 0 ';
    $db->query($sql);
}


$filename = 'db.sql';
$file     = fopen($filename, 'r')
			or die('File <strong>db.sql</strong> could not be found in folder <strong>adm_program/installation/db_scripts</strong>.');
$content  = fread($file, filesize($filename));
$sql_arr  = explode(';', $content);
fclose($file);

echo 'Read file db.sql ...<br />';

foreach($sql_arr as $sql)
{
    if(strlen(trim($sql)) > 0)
    {
		// set prefix for all tables and execute sql statement
        $sql = str_replace('%PREFIX%', $g_tbl_praefix, $sql);        
        $db->query($sql);
    }
}


$filename = 'data.sql';
$file     = fopen($filename, 'r')
            or die('File <strong>db.sql</strong> could not be found in folder <strong>adm_program/installation/db_scripts</strong>.');
$content  = fread($file, filesize($filename));
$sql_arr  = explode(';', $content);
fclose($file);

echo 'Read file data.sql ...<br />';

foreach($sql_arr as $sql)
{
    if(strlen(trim($sql)) > 0)
    {
		// set prefix for all tables and execute sql statement
        $sql = str_replace('%PREFIX%', $g_tbl_praefix, $sql);

        // search for translation strings with the prefix DEMO or SYS and try replace them
		preg_match_all('/(DEMO_\w*)|(SYS_\w*)|(INS_\w*)|(DAT_\w*)/' , $sql, $results);
		
		foreach($results[0] as $key => $value)
		{
			// convert <br /> to a normal line feed
			$convertedText = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/',chr(13).chr(10),$gL10n->get($value));

			// search for the exact value as a separate word and replace it with the translation
			// in l10n the single quote is transformed in html entity, but we need the original sql escaped
			$sql = preg_replace('/\b'.$value.'\b/', $db->escape_string(str_replace('&rsquo;', '\'', $convertedText)), $sql);
		}

        $db->query($sql);
    }
}

// manipulate some dates so that it's suitable to the current date
echo 'Edit data of database ...<br />';
include('data_edit.php');

// in postgresql all sequences must get a new start value because our inserts have given ids
if($gDbType == 'postgresql')
{
	$sql = 'SELECT c.relname FROM pg_class c WHERE c.relkind = \'S\' ';
	$sqlResult = $db->query($sql);
	
	while($row = $db->fetch_array($sqlResult))
	{
		$sql = 'SELECT setval(\''.$row['relname'].'\', 1000000)';
		$db->query($sql);
	}
}

// set parameter lang to default language for this installation
$sql = 'UPDATE '.$g_tbl_praefix.'_preferences SET prf_value = \''.$getLanguage.'\'
         WHERE prf_name   = \'system_language\' ';
$db->query($sql);

if($gDbType == 'mysql')
{
    // activate foreign key checks, so database is consistant
    $sql = 'SET foreign_key_checks = 1 ';
    $db->query($sql);
}

echo 'Installation successful !<br />';

// read installed database version
if($db->query('SELECT 1 FROM '.TBL_COMPONENTS, false) == false)
{
    // in Admidio version 2 the database version was stored in preferences table
    $sql = 'SELECT prf_value FROM '.$g_tbl_praefix.'_preferences
             WHERE prf_name   = \'db_version\' 
               AND prf_org_id = 1';
    $db->query($sql);
    $row  = $db->fetch_array();
    $databaseVersion = $row['prf_value'];
}
else
{
    $databaseVersion = $gSystemComponent->getValue('com_version');
}

echo '<p>Database and testdata have the Admidio version '.$databaseVersion.'.<br />
 Your files have Admidio version '.ADMIDIO_VERSION.'.<br /><br />
 Please perform an <a href="../adm_program/installation/update.php">update of your database</a>.</p>
 <p style="font-size: 9pt;">&copy; 2004 - 2013&nbsp;&nbsp;The Admidio team</p>';

?>