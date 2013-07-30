<?php
/******************************************************************************
 * Handle update of Admidio database to a new version
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters: 
 *
 * mode     = 1 : (Default) Check update status and show dialog with status
 *            2 : Perform update
 *            3 : Show result of update
 *
 *****************************************************************************/

// embed config and constants file
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_install')-1). '/config.php');

if(strlen($g_tbl_praefix) == 0)
{
	// default praefix is "adm" because of compatibility to older versions
    $g_tbl_praefix = 'adm';
}

require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_install')-1). '/adm_program/system/constants.php');

// check PHP version and show notice if version is too low
if(version_compare(phpversion(), MIN_PHP_VERSION) == -1)
{
    die('<div style="color: #CC0000;">Error: Your PHP version '.phpversion().' does not fulfill 
		the minimum requirements for this Admidio version. You need at least PHP '.MIN_PHP_VERSION.' or more highly.</div>');
}

require_once('install_functions.php');
require_once(SERVER_PATH. '/adm_program/system/db/database.php');
require_once(SERVER_PATH. '/adm_program/system/string.php');
require_once(SERVER_PATH. '/adm_program/system/function.php');
require_once(SERVER_PATH. '/adm_program/system/classes/component_update.php');
require_once(SERVER_PATH. '/adm_program/system/classes/datetime_extended.php');
require_once(SERVER_PATH. '/adm_program/system/classes/language.php');
require_once(SERVER_PATH. '/adm_program/system/classes/language_data.php');
require_once(SERVER_PATH. '/adm_program/system/classes/organization.php');
 
// Initialize and check the parameters

$getMode = admFuncVariableIsValid($_GET, 'mode', 'numeric', 1);
$message = '';

// Default-DB-Type ist immer MySql
if(!isset($gDbType))
{
    $gDbType = 'mysql';
}

// connect to database
$gDb = Database::createDatabaseObject($gDbType);
$gDbConnection = $gDb->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

// Daten der aktuellen Organisation einlesen
$gCurrentOrganization = new Organization($gDb, $g_organization);

if($gCurrentOrganization->getValue('org_id') == 0)
{
    // Organisation wurde nicht gefunden
    die('<div style="color: #CC0000;">Error: The organization of the config.php could not be found in the database!</div>');
}

// organisationsspezifische Einstellungen aus adm_preferences auslesen
$gPreferences = $gCurrentOrganization->getPreferences();

// create language and language data object to handle translations
if(isset($gPreferences['system_language']) == false)
{
    $gPreferences['system_language'] = 'de';
}
$gL10n = new Language();
$gLanguageData = new LanguageData($gPreferences['system_language']);
$gL10n->addLanguageData($gLanguageData);

//Datenbank- und PHP-Version prüfen
if(checkVersions($gDb, $message) == false)
{
	showPage($message, $g_root_path.'/adm_program/index.php', 'application_view_list.png', $gL10n->get('SYS_OVERVIEW'), 2);
}

// read current version of Admidio database
$installedDbVersion     = '';
$installedDbBetaVersion = '';
$maxUpdateStep          = 0;
$currentUpdateStep      = 0;

if($gDb->query('SELECT 1 FROM '.TBL_COMPONENTS, false) == false)
{
    // in Admidio version 2 the database version was stored in preferences table
    if(isset($gPreferences['db_version']))
    {
        $installedDbVersion     = $gPreferences['db_version'];
        $installedDbBetaVersion = $gPreferences['db_version_beta'];
    }
}
else
{
    // read system component
    $componentUpdateHandle = new ComponentUpdate($gDb);
    $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
    
    if($componentUpdateHandle->getValue('com_id') > 0)
    {
        $installedDbVersion     = $componentUpdateHandle->getValue('com_version');
        $installedDbBetaVersion = $componentUpdateHandle->getValue('com_beta');
        $currentUpdateStep      = $componentUpdateHandle->getValue('com_update_step');
        $maxUpdateStep          = $componentUpdateHandle->getMaxUpdateStep();
    }
}

// if databse version is not set then show notice
if(strlen($installedDbVersion) == 0)
{
	$message = '<img style="vertical-align: top;" src="layout/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" />
				<h2 class="admHeadline2">'.$gL10n->get('INS_UPDATE_NOT_POSSIBLE').'</h2>'.
				$gL10n->get('INS_NO_INSTALLED_VERSION_FOUND', ADMIDIO_VERSION);
	showPage($message, $g_root_path.'/adm_program/index.php', 'application_view_list.png', $gL10n->get('SYS_OVERVIEW'), 2);
}


if($getMode == 1)
{
	// if database version is smaller then source version -> update
	// if database version is equal to source but beta has a differnce -> update
    if(version_compare($installedDbVersion, ADMIDIO_VERSION) < 0
	||(version_compare($installedDbVersion, ADMIDIO_VERSION) == 0 && $maxUpdateStep > $currentUpdateStep))
    {
        $message = '<h2 class="admHeadline2"><img style="vertical-align: top;" src="layout/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" />
                    '.$gL10n->get('INS_DATABASE_NEEDS_UPDATED_VERSION', $installedDbVersion, ADMIDIO_VERSION).'</h2>';
    }
	// if versions are equal > no update
    elseif(version_compare($installedDbVersion, ADMIDIO_VERSION) == 0 && $maxUpdateStep == $currentUpdateStep)
    {
        $message = '<h2 class="admHeadline2"><img style="vertical-align: top;" src="layout/ok.png" /> '.$gL10n->get('INS_DATABASE_DOESNOT_NEED_UPDATED').'</h2>
                    '.$gL10n->get('INS_DATABASE_IS_UP_TO_DATE');
        showPage($message, $g_root_path.'/adm_program/index.php', 'application_view_list.png', $gL10n->get('SYS_OVERVIEW'), 2);
    }
	// if source version smaller then database -> show error
	else
	{
        $message = '<h2 class="admHeadline2"><img style="vertical-align: top;" src="layout/warning.png" /> '.$gL10n->get('SYS_ERROR').'</h2>
                    '.$gL10n->get('SYS_WEBMASTER_FILESYSTEM_INVALID', $installedDbVersion, ADMIDIO_VERSION, '<a href="http://www.admidio.org/index.php?page=download">', '</a>');
        showPage($message, $g_root_path.'/adm_program/index.php', 'application_view_list.png', $gL10n->get('SYS_OVERVIEW'), 2);
	}

    // falls dies eine Betaversion ist, dann Hinweis ausgeben
    if(BETA_VERSION > 0)
    {
        $message .= '<br /><br />'.$gL10n->get('INS_WARNING_BETA_VERSION');
    }
    showPage($message, 'update.php?mode=2', 'database_in.png', $gL10n->get('INS_UPDATE_DATABASE'), 2);
}
elseif($getMode == 2)
{
    // Updatescripte fuer die Datenbank verarbeiten

    // setzt die Ausfuehrungszeit des Scripts auf 2 Min., da hier teilweise sehr viel gemacht wird
    // allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
    @set_time_limit(300);

    // erst einmal die evtl. neuen Orga-Einstellungen in DB schreiben
    include('db_scripts/preferences.php');

    $sql = 'SELECT * FROM '. TBL_ORGANIZATIONS;
    $result_orga = $gDb->query($sql);

    while($row_orga = $gDb->fetch_array($result_orga))
    {
        $gCurrentOrganization->setValue('org_id', $row_orga['org_id']);
        $gCurrentOrganization->setPreferences($orga_preferences, false);
    }

    $mainVersion      = substr($installedDbVersion, 0, 1);
    $subVersion       = substr($installedDbVersion, 2, 1);
    $microVersion     = substr($installedDbVersion, 4, 1);
    $microVersion     = $microVersion + 1;
    $flagNextVersion = true;

	if($gDbType == 'mysql')
	{
        // disable foreign key checks for mysql, so tables can easily deleted
	    $sql = 'SET foreign_key_checks = 0 ';
	    $gDb->query($sql);
	}

    // before version 3 we had an other update mechanism which will be handled here
    if($mainVersion < 3)
    {
        // nun in einer Schleife die Update-Scripte fuer alle Versionen zwischen der Alten und Neuen einspielen
        while($flagNextVersion)
        {
            $flagNextVersion = false;
            
            if($mainVersion < 3)
            {
                // until version 3 Admidio had sql and php files where the update statements where stored
                // these files must be excecuted
            
                // in der Schleife wird geschaut ob es Scripte fuer eine Microversion (3.Versionsstelle) gibt
                // Microversion 0 sollte immer vorhanden sein, die anderen in den meisten Faellen nicht
                for($microVersion = $microVersion; $microVersion < 15; $microVersion++)
                {
                    // Update-Datei der naechsten hoeheren Version ermitteln
                    $sqlUpdateFile = 'db_scripts/upd_'. $mainVersion. '_'. $subVersion. '_'. $microVersion. '_db.sql';
                    $phpUpdateFile = 'db_scripts/upd_'. $mainVersion. '_'. $subVersion. '_'. $microVersion. '_conv.php';                
                    
                    // output of the version number for better debugging
                    if($gDebug)
                    {
                        error_log('Update to version '.$mainVersion.'.'.$subVersion.'.'.$microVersion);
                    }
                    
                    if(file_exists($sqlUpdateFile))
                    {
                        // SQL-Script abarbeiten
                        $file    = fopen($sqlUpdateFile, 'r')
                                   or showPage($gL10n->get('INS_ERROR_OPEN_FILE', $sqlUpdateFile), 'update.php', 'back.png', $gL10n->get('SYS_BACK'));
                        $content = fread($file, filesize($sqlUpdateFile));
                        $sql_arr = explode(';', $content);
                        fclose($file);
            
                        foreach($sql_arr as $sql)
                        {
                            if(strlen(trim($sql)) > 0)
                            {
                                // replace prefix with installation specific table prefix
                                $sql = str_replace('%PREFIX%', $g_tbl_praefix, $sql);
                                // now execute update sql
                                $gDb->query($sql);
                            }
                        }
                        
                        $flagNextVersion = true;
                    }
                    
                    // now set db specific admidio preferences
                    $gDb->setDBSpecificAdmidioProperties($mainVersion. '.'. $subVersion. '.'. $microVersion);
            
                    // check if an php update file exists and then execute the script
                    if(file_exists($phpUpdateFile))
                    {
                        include($phpUpdateFile);
                        $flagNextVersion = true;
                    }
                }

                // keine Datei mit der Microversion gefunden, dann die Main- oder Subversion hochsetzen,
                // solange bis die aktuelle Versionsnummer erreicht wurde
                if($flagNextVersion == false
                && version_compare($mainVersion. '.'. $subVersion. '.'. $microVersion , ADMIDIO_VERSION) == -1)
                {
                    if($subVersion == 4) // we do not have more then 4 subversions with old updater
                    {
                        $mainVersion = $mainVersion + 1;
                        $subVersion  = 0;
                    }
                    else
                    {
                        $subVersion  = $subVersion + 1;
                    }
                    
                    $microVersion    = 0;
                    $flagNextVersion = true;
                }
            }
        }
    }
    
    // since version 3 we do the update with xml files and a new class model
    if($mainVersion >= 3)
    {
        // reread component because in version 3.0 the component will be created within the update
        $componentUpdateHandle = new ComponentUpdate($gDb);
        $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
        $componentUpdateHandle->setTargetVersion(ADMIDIO_VERSION);
        $componentUpdateHandle->update();
    }

	if($gDbType == 'mysql')
	{
        // activate foreign key checks, so database is consistant
	    $sql = 'SET foreign_key_checks = 1 ';
	    $gDb->query($sql);
	}

    // nach dem Update erst einmal bei Sessions das neue Einlesen des Organisations- und Userobjekts erzwingen
    $sql = 'UPDATE '. TBL_SESSIONS. ' SET ses_renew = 1 ';
    $gDb->query($sql);

    // create an installation unique cookie prefix and remove special characters
    $gCookiePraefix = 'ADMIDIO_'.$g_organization.'_'.$g_adm_db.'_'.$g_tbl_praefix;
    $gCookiePraefix = strtr($gCookiePraefix, ' .,;:','_____');
    
	// start php session and remove session object with all data, so that
	// all data will be read after the update
    session_name($gCookiePraefix. '_PHP_ID');
    session_start();
    unset($_SESSION['gCurrentSession']);

    // Hinweis, dass Update erfolgreich war
    $message = '<h2 class="admHeadline2"><img style="vertical-align: top;" src="layout/ok.png" /> '.$gL10n->get('INS_UPDATING_WAS_SUCCESSFUL').'</h2>
               '.$gL10n->get('INS_UPDATE_TO_VERSION_SUCCESSFUL', ADMIDIO_VERSION. BETA_VERSION_TEXT).'<br /><br />
               '.$gL10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT');
    showPage($message, 'http://www.admidio.org/index.php?page=donate', 'money.png', $gL10n->get('SYS_DONATE'), 2);
}

?>