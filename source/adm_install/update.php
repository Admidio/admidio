<?php
/******************************************************************************
 * Update der Admidio-Datenbank auf eine neue Version
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters: 
 *
 * mode     = 1 : (Default) Statuspruefung und Anzeige, ob ein Update notwendig ist
 *            2 : Update durchfuehren
 *            3 : Ergebnis des Updates ausgeben
 *
 *****************************************************************************/

require_once('install_functions.php');

// Uebergabevariablen pruefen

if(isset($_GET['mode']) && is_numeric($_GET['mode']))
{
    $req_mode = $_GET['mode'];
}
else
{
    $req_mode = 1;
}

// Konstanten und Konfigurationsdatei einbinden
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_install')-1). '/config.php');
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_install')-1). '/adm_program/system/constants.php');

// default praefix is "adm" because of compatibility to older versions
if(strlen($g_tbl_praefix) == 0)
{
    $g_tbl_praefix = 'adm';
}

// Default-DB-Type ist immer MySql
if(!isset($gDbType))
{
    $gDbType = 'mysql';
}

// PHP-Version pruefen und ggf. mit Hinweis abbrechen
if(version_compare(phpversion(), MIN_PHP_VERSION) == -1)
{
    die('<div style="color: #CC0000;">Error: Your PHP version '.phpversion().' does not fulfill 
		the minimum requirements for this Admidio version. You need at least PHP '.MIN_PHP_VERSION.' or more highly.</div>');
}

require_once(SERVER_PATH. '/adm_program/system/db/database.php');
require_once(SERVER_PATH. '/adm_program/system/string.php');
require_once(SERVER_PATH. '/adm_program/system/function.php');
require_once(SERVER_PATH. '/adm_program/system/classes/datetime_extended.php');
require_once(SERVER_PATH. '/adm_program/system/classes/language.php');
require_once(SERVER_PATH. '/adm_program/system/classes/organization.php');

// Verbindung zu Datenbank herstellen
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

// Sprachdateien einlesen
if(isset($gPreferences['system_language']) == false)
{
    $gPreferences['system_language'] = 'de';
}
$gL10n = new Language($gPreferences['system_language']);

$message = '';

//Datenbank- und PHP-Version prüfen
if(checkVersions($gDb, $message) == false)
{
	showPage($message, $g_root_path.'/adm_program/index.php', 'application_view_list.png', $gL10n->get('SYS_OVERVIEW'), 2);
}

if($req_mode == 1)
{
    // pruefen, ob ein Update ueberhaupt notwendig ist
    if(isset($gPreferences['db_version']) == false)
    {
        // bei einem Update von Admidio 1.x muss die spezielle Version noch erfragt werden,
        // da in Admidio 1.x die Version noch nicht in der DB gepflegt wurde
        $message = '<img style="vertical-align: top;" src="layout/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" />
                    <strong>'.$gL10n->get('INS_DATABASE_NEEDS_UPDATED').'</strong><br /><br />
                    '.$gL10n->get('INS_UPDATE_FROM_ADMIDIO_1X', ADMIDIO_VERSION).'<br /><br />
                    '.$gL10n->get('INS_PREVIOUS_ADMIDIO_VERSION').':&nbsp;
                    <select id="old_version" name="old_version" size="1">
                        <option value="0" selected="selected">- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>
                        <option value="1.4.9">Version 1.4.*</option>
                        <option value="1.3.9">Version 1.3.*</option>
                        <option value="1.2.9">Version 1.2.*</option>
                    </select>';
    }
    elseif(version_compare($gPreferences['db_version'], ADMIDIO_VERSION) != 0 || $gPreferences['db_version_beta'] != BETA_VERSION)
    {
        $message = '<img style="vertical-align: top;" src="layout/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" />
                    <strong>'.$gL10n->get('INS_DATABASE_NEEDS_UPDATED_VERSION', $gPreferences['db_version'], ADMIDIO_VERSION).'</strong>';
    }
    else
    {
        $message = '<img style="vertical-align: top;" src="layout/ok.png" /> 
                    <strong>'.$gL10n->get('INS_DATABASE_DOESNOT_NEED_UPDATED').'</strong><br /><br />
                    '.$gL10n->get('INS_DATABASE_IS_UP_TO_DATE');
        showPage($message, $g_root_path.'/adm_program/index.php', 'application_view_list.png', $gL10n->get('SYS_OVERVIEW'), 2);
    }

    // falls dies eine Betaversion ist, dann Hinweis ausgeben
    if(BETA_VERSION > 0)
    {
        $message .= '<br /><br />'.$gL10n->get('INS_WARNING_BETA_VERSION');
    }
    showPage($message, 'update.php?mode=2', 'database_in.png', $gL10n->get('INS_UPDATE_DATABASE'), 2);
}
elseif($req_mode == 2)
{
    // Updatescripte fuer die Datenbank verarbeiten
    
    if(isset($gPreferences['db_version']) == false)
    {
        if(isset($_POST['old_version']) == false 
        || strlen($_POST['old_version']) > 5
        || $_POST['old_version'] == 0)
        {
            showPage($gL10n->get('SYS_FIELD_EMPTY', 'INS_PREVIOUS_ADMIDIO_VERSION'), 'update.php', 'back.png', $gL10n->get('SYS_BACK'), 2);
        }
        $old_version = $_POST['old_version'];
    }
    else
    {
        $old_version = $gPreferences['db_version'];
    } 

    // setzt die Ausfuehrungszeit des Scripts auf 2 Min., da hier teilweise sehr viel gemacht wird
    // allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
    @set_time_limit(300);

    // vor dem Update die Versionsnummer umsetzen, damit keiner mehr was machen kann
    $temp_version = substr(ADMIDIO_VERSION, 0, 4). 'u';
    $sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = \''. $temp_version. '\'
             WHERE prf_name    = \'db_version\' ';
    $gDb->query($sql);                

    // erst einmal die evtl. neuen Orga-Einstellungen in DB schreiben
    include('db_scripts/preferences.php');

    $sql = 'SELECT * FROM '. TBL_ORGANIZATIONS;
    $result_orga = $gDb->query($sql);

    while($row_orga = $gDb->fetch_array($result_orga))
    {
        $gCurrentOrganization->setValue('org_id', $row_orga['org_id']);
        $gCurrentOrganization->setPreferences($orga_preferences, false);
    }

    $main_version      = substr($old_version, 0, 1);
    $sub_version       = substr($old_version, 2, 1);
    $micro_version     = substr($old_version, 4, 1);
    $micro_version     = $micro_version + 1;
    $flag_next_version = true;

	if($gDbType == 'mysql')
	{
	    // Pruefungen auf Referenzen in anderen Tabellen beim Update ausschalten
	    $sql = 'SET foreign_key_checks = 0 ';
	    $gDb->query($sql);
	}

    // nun in einer Schleife die Update-Scripte fuer alle Versionen zwischen der Alten und Neuen einspielen
    while($flag_next_version)
    {
        $flag_next_version = false;
        
        // in der Schleife wird geschaut ob es Scripte fuer eine Microversion (3.Versionsstelle) gibt
        // Microversion 0 sollte immer vorhanden sein, die anderen in den meisten Faellen nicht
        for($micro_version = $micro_version; $micro_version < 15; $micro_version++)
        {
            // Update-Datei der naechsten hoeheren Version ermitteln
            $sql_file  = 'db_scripts/upd_'. $main_version. '_'. $sub_version. '_'. $micro_version. '_db.sql';
            $conv_file = 'db_scripts/upd_'. $main_version. '_'. $sub_version. '_'. $micro_version. '_conv.php';                
            
            if(file_exists($sql_file))
            {
                // SQL-Script abarbeiten
                $file    = fopen($sql_file, 'r')
                           or showPage($gL10n->get('INS_ERROR_OPEN_FILE', $sql_file), 'update.php', 'back.png', $gL10n->get('SYS_BACK'));
                $content = fread($file, filesize($sql_file));
                $sql_arr = explode(';', $content);
                fclose($file);
    
                foreach($sql_arr as $sql)
                {
                    if(strlen(trim($sql)) > 0)
                    {
                        // Praefix fuer die Tabellen einsetzen und SQL-Statement ausfuehren
                        $sql = str_replace('%PREFIX%', $g_tbl_praefix, $sql);
                        $gDb->query($sql);
                    }
                }
                
                $flag_next_version = true;
            }
			
			// now set db specific admidio preferences
			$gDb->setDBSpecificAdmidioProperties($main_version. '.'. $sub_version. '.'. $micro_version);
    
            if(file_exists($conv_file))
            {
                // Nun das PHP-Script abarbeiten
                include($conv_file);
                $flag_next_version = true;
            }
        }

        // keine Datei mit der Microversion gefunden, dann die Main- oder Subversion hochsetzen,
        // solange bis die aktuelle Versionsnummer erreicht wurde
        if($flag_next_version == false
        && version_compare($main_version. '.'. $sub_version. '.'. $micro_version , ADMIDIO_VERSION) == -1)
        {
            if($sub_version == 9)
            {
                $main_version  = $main_version + 1;
                $sub_version   = 0;
            }
            else
            {
                $sub_version   = $sub_version + 1;
            }
            
            $micro_version     = 0;
            $flag_next_version = true;
        }
    }

	if($gDbType == 'mysql')
	{
	    // Pruefungen auf Referenzen in anderen Tabellen wieder aktivieren
	    $sql = 'SET foreign_key_checks = 1 ';
	    $gDb->query($sql);
	}

    // nach dem Update erst einmal bei Sessions das neue Einlesen des Organisations- und Userobjekts erzwingen
    $sql = 'UPDATE '. TBL_SESSIONS. ' SET ses_renew = 1 ';
    $gDb->query($sql);
    
    // nach einem erfolgreichen Update noch die neue Versionsnummer in DB schreiben
    $sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = \''. ADMIDIO_VERSION. '\'
             WHERE prf_name    = \'db_version\' ';
    $gDb->query($sql);                

    $sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = \''. BETA_VERSION. '\'
             WHERE prf_name    = \'db_version_beta\' ';
    $gDb->query($sql);                
    
    // globale Objekte aus einer evtl. vorhandenen Session entfernen, 
    // damit diese nach dem Update neu eingelesen werden muessen
    
	// detect cookie praefix and remove special char
    $gCookiePraefix = 'ADMIDIO_'. $g_organization;
    if($gDebug)
    {
        $gCookiePraefix .= '_'. ADMIDIO_VERSION. '_'. BETA_VERSION;
    }
    $gCookiePraefix = strtr($gCookiePraefix, ' .,;:','_____');
    
	// start php session and initialize global parameters
    session_name($gCookiePraefix. '_PHP_ID');
    session_start();
    unset($_SESSION['g_current_organisation'], $_SESSION['gPreferences'], $_SESSION['gCurrentUser']);

    // Hinweis, dass Update erfolgreich war
    $message = '<img style="vertical-align: top;" src="layout/ok.png" /> <strong>'.$gL10n->get('INS_UPDATING_WAS_SUCCESSFUL').'</strong><br /><br />
               '.$gL10n->get('INS_UPDATE_TO_VERSION_SUCCESSFUL', ADMIDIO_VERSION. BETA_VERSION_TEXT).'<br /><br />
               '.$gL10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT');
    showPage($message, 'http://www.admidio.org/index.php?page=donate', 'money.png', $gL10n->get('SYS_DONATE'), 2);
}

?>