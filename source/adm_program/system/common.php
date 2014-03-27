<?php
/******************************************************************************
 * Basic script for all other Admidio scripts with all the necessary data und
 * variables to run a script in the Admidio environment
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

if ('common.php' == basename($_SERVER['SCRIPT_FILENAME']))
{
    die('This page may not be called directly !');
}

// embed config and constants file
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_program')-1). '/adm_my_files/config.php');
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
require_once(SERVER_PATH. '/adm_program/libs/htmlawed/htmlawed.php');
require_once(SERVER_PATH. '/adm_program/system/function.php');
require_once(SERVER_PATH. '/adm_program/system/string.php');

// remove HMTL & PHP-Code from all parameters
$_GET     = admStrStripTagsSpecial($_GET);
$_POST    = admStrStripTagsSpecial($_POST);
$_COOKIE  = admStrStripTagsSpecial($_COOKIE);

// escape all quotes so db queries are save
if(get_magic_quotes_gpc() == false)
{
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
if($gDb->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db) == false)
{
    // organization not found
    die('<div style="color: #CC0000;">Error: Wrong database connection parameters!</div>');
}

// determine forum script and include it before session is created
Forum::includeForumScript($gDb);

// create an installation unique cookie prefix and remove special characters
$gCookiePraefix = 'ADMIDIO_'.$g_organization.'_'.$g_adm_db.'_'.$g_tbl_praefix;
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

// if auto login is set and session is new or a user assigned then check then create valid login
$userIdAutoLogin = 0;

// create language object to handle translation texts
$gL10n = new Language();

// Session handling
if(isset($_SESSION['gCurrentSession']))
{
	// read session object from PHP session
	$gCurrentSession       = $_SESSION['gCurrentSession'];
	$gCurrentSession->db  =& $gDb;
	// reload session data and if neccessary the organization object
	$gCurrentSession->refreshSession();
    // read system component
    $gSystemComponent =& $gCurrentSession->getObject('gSystemComponent');
	// read language data from session and assign them to the language object
	$gL10n->addLanguageData($gCurrentSession->getObject('gLanguageData'));
	// read organization data from session object
	$gCurrentOrganization =& $gCurrentSession->getObject('gCurrentOrganization');
    $gPreferences          = $gCurrentOrganization->getPreferences();
    
    // compute time in ms from last activity in session until now
    $time_gap = time() - strtotime($gCurrentSession->getValue('ses_timestamp', 'Y-m-d H:i:s'));
    
    // if cookie ADMIDIO_DATA is set and last user activity is longer ago, then create auto login if possible
    if(isset($_COOKIE[$gCookiePraefix. '_DATA'])
    && $gCurrentSession->hasObject('gCurrentUser') && $time_gap > $gPreferences['logout_minutes'] * 60)
    {
    	// restore user from auto login session
    	$autoLogin = new AutoLogin($gDb, $gSessionId);
    	$autoLogin->setValidLogin($gCurrentSession, $_COOKIE[$gCookiePraefix. '_DATA']);
    	$userIdAutoLogin = $autoLogin->getValue('atl_usr_id');
    }
}
else
{
	// create new session object and store it in PHP session
	$gCurrentSession = new Session($gDb, $gSessionId);
	$_SESSION['gCurrentSession'] =& $gCurrentSession;

    // create system component
    $gSystemComponent = new Component($gDb);
    $gSystemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
    $gCurrentSession->addObject('gSystemComponent', $gSystemComponent);
	
	// if cookie ADMIDIO_DATA is set then there could be an auto login
	// the auto login must be done here because after that the corresponding organization must be set
	if(isset($_COOKIE[$gCookiePraefix. '_DATA']))
	{
    	// restore user from auto login session
    	$autoLogin = new AutoLogin($gDb, $gSessionId);
    	$autoLogin->setValidLogin($gCurrentSession, $_COOKIE[$gCookiePraefix. '_DATA']);
    	$userIdAutoLogin = $autoLogin->getValue('atl_usr_id');    	
    	
    	// create object of the organization of config file with their preferences
    	if($autoLogin->getValue('atl_org_id') > 0)
    	{
            $gCurrentOrganization = new Organization($gDb, $autoLogin->getValue('atl_org_id'));
        }
        else
        {
            $gCurrentOrganization = new Organization($gDb, $g_organization);            
        }
	}
	else
	{
    	// create object of the organization of config file with their preferences
        $gCurrentOrganization = new Organization($gDb, $g_organization);
    }
    
    if($gCurrentOrganization->getValue('org_id') == 0)
    {
        // organization not found
        die('<div style="color: #CC0000;">Error: The organization of the config.php could not be found in the database!</div>');
    }
    // add the organization to the session
    $gPreferences = $gCurrentOrganization->getPreferences();
	$gCurrentSession->addObject('gCurrentOrganization', $gCurrentOrganization);
    $gCurrentSession->setValue('ses_org_id', $gCurrentOrganization->getValue('org_id'));
		
	// create a language data object and assign it to the language object
	$gLanguageData = new LanguageData($gPreferences['system_language']);
	$gL10n->addLanguageData($gLanguageData);
	$gCurrentSession->addObject('gLanguageData', $gLanguageData);
	
	// delete old entries in session table
    $gCurrentSession->tableCleanup($gPreferences['logout_minutes']);
}

// now if auto login is done, read global user data
if($gCurrentSession->hasObject('gCurrentUser'))
{
	$gProfileFields =& $gCurrentSession->getObject('gProfileFields');
	$gCurrentUser   =& $gCurrentSession->getObject('gCurrentUser');
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
	$gProfileFields = new ProfileFields($gDb, $gCurrentOrganization->getValue('org_id'));
	$gCurrentUser   = new User($gDb, $gProfileFields, $userIdAutoLogin);
	
	// save all data in session
	$gCurrentSession->addObject('gProfileFields', $gProfileFields);
	$gCurrentSession->addObject('gCurrentUser', $gCurrentUser);
}

// check if organization or user object must be renewed if data was changed by other users
if($gCurrentSession->getValue('ses_renew') == 1 || $gCurrentSession->getValue('ses_renew') == 3)
{
	// read new field structure in object and than create new user object with new field structure
	$gProfileFields->readProfileFields($gCurrentOrganization->getValue('org_id'));
	$gCurrentUser->readDataById($gCurrentUser->getValue('usr_id'));
	$gCurrentSession->setValue('ses_renew', 0);
}

// check session
if($gCurrentSession->getValue('ses_usr_id') > 0)
{
	if($gCurrentSession->getValue('ses_usr_id') == $gCurrentUser->getValue('usr_id'))
	{
		// session has a user assigned -> check if login is still valid
		$time_gap = time() - strtotime($gCurrentSession->getValue('ses_timestamp', 'Y-m-d H:i:s'));
		
		// Check how long the user was inactive. If time range is to long -> logout
		if($time_gap < $gPreferences['logout_minutes'] * 60) 
		{
			// user login is valid !
			$gValidLogin = true;
			$gCurrentSession->setValue('ses_timestamp', DATETIME_NOW);
		}
		else
		{
			// user was inactive -> clear user data and remove him from session
			$gCurrentUser->clear();
			$gCurrentSession->setValue('ses_usr_id', '');
		}
	}
	else
	{
		// something is wrong -> clear user data
		$gCurrentUser->clear();
		$gCurrentSession->setValue('ses_usr_id', '');
	}
}

// update session recordset (i.a. refresh timestamp)
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

// set default theme if no theme was set
if(isset($gPreferences['theme']) == false)
{
    $gPreferences['theme'] = 'classic';
}
define('THEME_SERVER_PATH', SERVER_PATH. '/adm_themes/'. $gPreferences['theme']);
define('THEME_PATH', $g_root_path. '/adm_themes/'. $gPreferences['theme']);

// Create message object which can be called if a message should be shown
$gMessage = new Message();

// Create object for navigation between the scripts and modules
// Every URL will be stored in a stack and can be called if user want's to navigate back
if($gCurrentSession->hasObject('gNavigation'))
{
    $gNavigation = $gCurrentSession->getObject('gNavigation');
}
else
{
    $gNavigation = new Navigation();
    $gCurrentSession->addObject('gNavigation', $gNavigation);
}

try
{
    // check version of database against version of file system and show notice if not equal
    $gSystemComponent->checkDatabaseVersion($gCurrentUser->isWebmaster(), $gPreferences['email_administrator']);
}
catch(AdmException $e)
{
    $e->showHtml();
} 

// set default homepage
if($gValidLogin)
{
    $gHomepage = $g_root_path. '/'. $gPreferences['homepage_login'];
}
else
{
    $gHomepage = $g_root_path. '/'. $gPreferences['homepage_logout'];
}

/*********************************************************************************
 Create connection to forum database and load functions and routines for forum
/********************************************************************************/

if($gPreferences['enable_forum_interface']) 
{
	// set Admidio database connection if this is set
    if($gPreferences['forum_sqldata_from_admidio']) 
    {
        $gPreferences['forum_srv'] = $gDb->server;
        $gPreferences['forum_usr'] = $gDb->user;
        $gPreferences['forum_pw']  = $gDb->password;
        $gPreferences['forum_db']  = $gDb->dbName;
    }
    
	// save forum object in Admidio session
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
		// connection to forum database failed -> deactivate forum
        $gPreferences['enable_forum_interface'] = 0;
    }
    else
    {
		// read forum preferences
        if(!$gForum->initialize(session_id(), $gPreferences['forum_praefix'], $gPreferences['forum_export_user'], $gPreferences['forum_link_intern'], $gCurrentUser->getValue('usr_login_name')))
        {
            echo '<div style="color: #CC0000;">Verbindungsfehler zum Forum !<br />
                Das eingegebene Forumpräfix <strong>'. $gPreferences['forum_praefix'].'</strong> ist nicht korrekt oder<br />
                es wurde die falsche Datenbankverbindung zum Forum angegeben.</div>';
        }
    }    
}

?>
