<?php
/******************************************************************************
 * Basic script for all other Admidio scripts with all the necessary data und
 * variables to run a script in the Admidio environment
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

if ('common.php' == basename($_SERVER['SCRIPT_FILENAME']))
{
    die('This page may not be called directly !');
}

// embed config and constants file
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_program')-1). '/config.php');
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_program')-1). '/adm_program/system/constants.php');

// if there is no debug flag in config.php than set debug to false
if(isset($gDebug) == false || $gDebug != 1)
{
    $gDebug = 0;
}

if($gDebug)
{
    // write actual script with parameters in log file
    error_log("--------------------------------------------------------------------------------\n".
              $_SERVER['SCRIPT_FILENAME']. "\n? ". $_SERVER['QUERY_STRING']);
}

 // default prefix is set to 'adm' because of compatibility to old versions
if(strlen($g_tbl_praefix) == 0)
{
    $g_tbl_praefix = 'adm';
}

// includes WITHOUT database connections
require_once(SERVER_PATH. '/adm_program/system/db/database.php');
require_once(SERVER_PATH. '/adm_program/system/function.php');
require_once(SERVER_PATH. '/adm_program/system/string.php');
require_once(SERVER_PATH. '/adm_program/system/classes/datetime_extended.php');
require_once(SERVER_PATH. '/adm_program/system/classes/language.php');
require_once(SERVER_PATH. '/adm_program/system/classes/message.php');
require_once(SERVER_PATH. '/adm_program/system/classes/navigation.php');
require_once(SERVER_PATH. '/adm_program/system/classes/organization.php');
require_once(SERVER_PATH. '/adm_program/system/classes/profile_fields.php');
require_once(SERVER_PATH. '/adm_program/system/classes/table_session.php');
require_once(SERVER_PATH. '/adm_program/system/classes/user.php');
require_once(SERVER_PATH. '/adm_program/system/forum/forum.php');

// remove HMTL & PHP-Code from all parameters
$_REQUEST = admStrStripTagsSpecial($_REQUEST);
$_GET     = admStrStripTagsSpecial($_GET);
$_POST    = admStrStripTagsSpecial($_POST);
$_COOKIE  = admStrStripTagsSpecial($_COOKIE);

// escape all quotes so db queries are save
if(get_magic_quotes_gpc() == false)
{
    $_REQUEST = strAddSlashesDeep($_REQUEST);
    $_GET     = strAddSlashesDeep($_GET);
    $_POST    = strAddSlashesDeep($_POST);
    $_COOKIE  = strAddSlashesDeep($_COOKIE);
}

// global parameters
$gValidLogin = false;
$gLayout     = array();

 // create database object and establish connection to database
if(!isset($gDbType))
{
    $gDbType = 'mysql';
}
$gDb = Database::createDatabaseObject($gDbType);
$gDbConnection = $gDb->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

// determine forum script and include it bevor session is created
Forum::includeForumScript($gDb);

// determine cookie prefix and remove special characters
$gCookiePraefix = 'ADMIDIO_'. $g_organization;
if($gDebug)
{
    $gCookiePraefix .= '_'. ADMIDIO_VERSION. '_'. BETA_VERSION;
}
$gCookiePraefix = strtr($gCookiePraefix, ' .,;:','_____');

/*********************************************************************************
 Create and validate sessions, check auto login, read session variables
/********************************************************************************/

// start PHP session
if(headers_sent() == false)
{
    session_name($gCookiePraefix. '_PHP_ID');
    session_start();
}

// determine session id
if(isset($_COOKIE[$gCookiePraefix. '_ID']))
{
    $gSessionId = $_COOKIE[$gCookiePraefix. '_ID'];
}
else
{
    $gSessionId = session_id();
}

// create session object and read auto login data if available
$gCurrentSession = new TableSession($gDb, $gSessionId);

if($gCurrentSession->getValue('ses_id') == 0)
{
	$gCurrentSession->setValue('ses_session_id', $gSessionId);
    // maintain the session table and delete old sessions
    $gCurrentSession->tableCleanup($gPreferences['logout_minutes']);
}

// read global organization class for system preferences
if(isset($_SESSION['gCurrentOrganization']) 
&& isset($_SESSION['gPreferences']))
{
    $gCurrentOrganization     =& $_SESSION['gCurrentOrganization'];
    $gCurrentOrganization->db =& $gDb;
    $gPreferences             =& $_SESSION['gPreferences'];
}
else
{	
	// no php session then read organization of config file with their preferences
    $gCurrentOrganization = new Organization($gDb, $g_organization);
    
    if($gCurrentOrganization->getValue('org_id') == 0)
    {
        // organization not found
        die('<div style="color: #CC0000;">Error: The organization of the config.php could not be found in the database!</div>');
    }
    $gPreferences = $gCurrentOrganization->getPreferences();
 
    // save all data in session variables
    $_SESSION['gCurrentOrganization'] =& $gCurrentOrganization;
    $_SESSION['gPreferences']         =& $gPreferences;
}

// if auto login is set and session is new or a user assigned then check then create valid login
$userIdAutoLogin = 0;
// compute time in ms from last activity in session until now
$time_gap = time() - strtotime($gCurrentSession->getValue('ses_timestamp', 'Y-m-d H:i:s'));

// if no user object or user activity is long ago, then create auto login if possible
if(isset($_COOKIE[$gCookiePraefix. '_DATA'])
&& (  isset($_SESSION['gCurrentUser']) == false 
   || (isset($_SESSION['gCurrentUser']) && $time_gap > $gPreferences['logout_minutes'] * 60)))
{
	$admidio_data = explode(';', $_COOKIE[$gCookiePraefix. '_DATA']);

	if($admidio_data[0] == true         // autologin
	&& is_numeric($admidio_data[1]))    // user_id 
	{   
		// restore user from auto login session
		require_once(SERVER_PATH. '/adm_program/system/classes/table_auto_login.php');
		$auto_login = new TableAutoLogin($gDb, $gSessionId);
		
		// restore user if saved database user id == cookie user id
		// if session is inactive than set it to an active session
		if($auto_login->getValue('atl_usr_id') == $admidio_data[1])
		{
			$userIdAutoLogin = $auto_login->getValue('atl_usr_id');
			$gCurrentSession->setValue('ses_timestamp', DATETIME_NOW);
		}
		else
		{
			// something is wrong -> for security reasons delete that auto login
			$auto_login->delete();
		}

		// auto login successful then create a valid session
		$gCurrentSession->setValue('ses_usr_id',  $userIdAutoLogin);
	}
}

// now if auto login is done, read global user data
if(isset($_SESSION['gProfileFields']) 
&& isset($_SESSION['gCurrentUser']))
{
	$gProfileFields           =& $_SESSION['gProfileFields'];
    $gProfileFields->mDb      =& $gDb;
    $gCurrentUser             =& $_SESSION['gCurrentUser'];
    $gCurrentUser->db         =& $gDb;
	$gCurrentUser->mProfileFieldsData->mDb =& $gDb;

	// checks if user in database session is the same as in php session
	if($gCurrentUser->getValue('usr_id') != $gCurrentSession->getValue('ses_usr_id'))
	{
		$gCurrentUser->clear();
		$gCurrentSession->setValue('ses_usr_id', '');
	}
}
else
{	
	// create object with current user field structure und user object
	$gProfileFields = new ProfileFields($gDb, $gCurrentOrganization);
	$gCurrentUser   = new User($gDb, $gProfileFields, $userIdAutoLogin);
    
	// save all data in session variables
    $_SESSION['gProfileFields']       =& $gProfileFields;
	$_SESSION['gCurrentUser']         =& $gCurrentUser;
}

// erst einmal pruefen, ob Organisation- oder Userobjekt neu eingelesen werden muessen,
// da die Daten evtl. von anderen Usern in der DB geaendert wurden
if($gCurrentSession->getValue('ses_renew') == 1 || $gCurrentSession->getValue('ses_renew') == 3)
{
	// read new field structure in object and than create new user object with new field structure
	$gProfileFields->readProfileFields();
	$userID = $gCurrentUser->getValue('usr_id');
	$gCurrentUser = new User($gDb, $gProfileFields, $userID);
	$_SESSION['gCurrentUser'] =& $gCurrentUser;
	$gCurrentSession->setValue('ses_renew', 0);
}
if($gCurrentSession->getValue('ses_renew') == 2 || $gCurrentSession->getValue('ses_renew') == 3)
{
	// Organisationsobjekt neu einlesen
	$gCurrentOrganization->readData($g_organization);
	$gPreferences = $gCurrentOrganization->getPreferences();
	$gCurrentSession->setValue('ses_renew', 0);
}

// nun die Session pruefen
if($gCurrentSession->getValue('ses_usr_id') > 0)
{
	if($gCurrentSession->getValue('ses_usr_id') == $gCurrentUser->getValue('usr_id'))
	{
		// Session gehoert zu einem eingeloggten User -> pruefen, ob der User noch eingeloggt sein darf
		$time_gap = time() - strtotime($gCurrentSession->getValue('ses_timestamp', 'Y-m-d H:i:s'));
		
		// wenn laenger nichts gemacht wurde, als in Orga-Prefs eingestellt ist, dann ausloggen
		if($time_gap < $gPreferences['logout_minutes'] * 60) 
		{
			// User-Login ist gueltig
			$gValidLogin = true;
			$gCurrentSession->setValue('ses_timestamp', DATETIME_NOW);
		}
		else
		{
			// User war zu lange inaktiv -> User aus Session entfernen
			$gCurrentUser->clear();
			$gCurrentSession->setValue('ses_usr_id', '');
		}
	}
	else
	{
		// irgendwas stimmt nicht, also alles zuruecksetzen
		$gCurrentUser->clear();
		$gCurrentSession->setValue('ses_usr_id', '');
	}
}

// Update auf Sessionsatz machen (u.a. timestamp aktualisieren)
$gCurrentSession->save();

// if session is created with auto login then update user login data
if($userIdAutoLogin > 0 && $gCurrentUser->getValue('usr_id'))
{
	// count logins and save login date
	$gCurrentUser->updateLoginData();
}

/*********************************************************************************
 create necessary objects and parameters
/********************************************************************************/

// read language file
$gL10n = new Language($gPreferences['system_language']);

// Pfad zum gewaehlten Theme zusammensetzen
if(isset($gPreferences['theme']) == false)
{
    $gPreferences['theme'] = 'classic';
}
define('THEME_SERVER_PATH', SERVER_PATH. '/adm_themes/'. $gPreferences['theme']);
define('THEME_PATH', $g_root_path. '/adm_themes/'. $gPreferences['theme']);

// Nachrichtenklasse anlegen
$gMessage = new Message();

// Objekt fuer die Zuruecknavigation in den Modulen
// hier werden die Urls in einem Stack gespeichert
if(isset($_SESSION['navigation']) == false)
{
    $_SESSION['navigation'] = new Navigation();
}

// check version of database against version of file system and show notice if not equal
admFuncCheckDatabaseVersion($gPreferences['db_version'], $gPreferences['db_version_beta'], $gCurrentUser->isWebmaster(), $gPreferences['email_administrator']);

// Homepageseite festlegen
if($gValidLogin)
{
    $gHomepage = $g_root_path. '/'. $gPreferences['homepage_login'];
}
else
{
    $gHomepage = $g_root_path. '/'. $gPreferences['homepage_logout'];
}

/*********************************************************************************
Verbindung zur Forum-Datenbank herstellen und die Funktionen, sowie Routinen des Forums laden.
/********************************************************************************/

if($gPreferences['enable_forum_interface']) 
{
    // Admidio-Zugangsdaten nehmen, wenn entsprechende Einstellung gesetzt ist
    if($gPreferences['forum_sqldata_from_admidio']) 
    {
        $gPreferences['forum_srv'] = $gDb->server;
        $gPreferences['forum_usr'] = $gDb->user;
        $gPreferences['forum_pw']  = $gDb->password;
        $gPreferences['forum_db']  = $gDb->dbName;
    }
    
    // globale Klassen mit Datenbankbezug werden in Sessionvariablen gespeichert, 
    // damit die Daten nicht bei jedem Script aus der Datenbank ausgelesen werden muessen
    if(isset($_SESSION['gForum']))
    {
        $gForum =& $_SESSION['gForum'];
    }
    else
    {
        $gForum = Forum::createForumObject($gPreferences['forum_version']);
        $_SESSION['gForum'] =& $gForum;
    }
        
    if(!$gForum->connect($gPreferences['forum_srv'], $gPreferences['forum_usr'], $gPreferences['forum_pw'], $gPreferences['forum_db'], $gDb))
    {
        // Verbindungsprobleme, deshalb Forum deaktivieren, damit Admidio ggf. noch funktioniert
        $gPreferences['enable_forum_interface'] = 0;
    }
    else
    {
        // Einstellungen des Forums einlesen
        if(!$gForum->initialize(session_id(), $gPreferences['forum_praefix'], $gPreferences['forum_export_user'], $gPreferences['forum_link_intern'], $gCurrentUser->getValue('usr_login_name')))
        {
            echo '<div style="color: #CC0000;">Verbindungsfehler zum Forum !<br />
                Das eingegebene Forumpräfix <strong>'. $gPreferences['forum_praefix'].'</strong> ist nicht korrekt oder<br />
                es wurde die falsche Datenbankverbindung zum Forum angegeben.</div>';
        }
    }    
}

?>
