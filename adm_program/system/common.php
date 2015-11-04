<?php
/******************************************************************************
 * Basic script for all other Admidio scripts with all the necessary data und
 * variables to run a script in the Admidio environment
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

if (basename($_SERVER['SCRIPT_FILENAME']) === 'common.php')
{
    exit('This page may not be called directly !');
}

// embed config and constants file
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_program') - 1) . '/adm_my_files/config.php');
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_program') - 1) . '/adm_program/system/constants.php');

// if there is no debug flag in config.php than set debug to false
if(!isset($gDebug) || $gDebug !== 1)
{
    $gDebug = 0;
}

if($gDebug)
{
    // write actual script with parameters in log file
    error_log('--------------------------------------------------------------------------------'."\n" .
              $_SERVER['SCRIPT_FILENAME'] . "\n? " . $_SERVER['QUERY_STRING']);
    error_log('memory_used::' . memory_get_usage());
}

 // default prefix is set to 'adm' because of compatibility to old versions
if($g_tbl_praefix === '')
{
    $g_tbl_praefix = 'adm';
}

// includes WITHOUT database connections
require_once(SERVER_PATH . '/adm_program/libs/htmlawed/htmlawed.php');
require_once(SERVER_PATH . '/adm_program/system/function.php');
require_once(SERVER_PATH . '/adm_program/system/string.php');

// remove HMTL & PHP-Code from all parameters
$_GET    = admStrStripTagsSpecial($_GET);
$_POST   = admStrStripTagsSpecial($_POST);
$_COOKIE = admStrStripTagsSpecial($_COOKIE);

// escape all quotes so db queries are save
if(!get_magic_quotes_gpc())
{
    $_GET    = strAddSlashesDeep($_GET);
    $_POST   = strAddSlashesDeep($_POST);
    $_COOKIE = strAddSlashesDeep($_COOKIE);
}

// global parameters
$gValidLogin = false;
$gLayout     = array();

 // create database object and establish connection to database
if(!isset($gDbType))
{
    $gDbType = 'mysql';
}

try
{
    $gDb = new Database($gDbType, $g_adm_srv, null, $g_adm_db, $g_adm_usr, $g_adm_pw);
}
catch(AdmException $e)
{
    $e->showText();
}

// create an installation unique cookie prefix and remove special characters
$gCookiePraefix = 'ADMIDIO_' . $g_organization . '_' . $g_adm_db . '_' . $g_tbl_praefix;
$gCookiePraefix = strtr($gCookiePraefix, ' .,;:[]', '_______');

/*********************************************************************************
 Create and validate sessions, check auto login, read session variables
/********************************************************************************/

// start PHP session
if(!headers_sent())
{
    session_name($gCookiePraefix . '_PHP_ID');
    session_start();
}

// determine session id
if(array_key_exists($gCookiePraefix . '_ID', $_COOKIE))
{
    $gSessionId = $_COOKIE[$gCookiePraefix . '_ID'];
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
if(array_key_exists('gCurrentSession', $_SESSION))
{
    // read session object from PHP session
    $gCurrentSession       = $_SESSION['gCurrentSession'];
    $gCurrentSession->setDatabase($gDb);
    // reload session data and if necessary the organization object
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
    if(array_key_exists($gCookiePraefix . '_DATA', $_COOKIE) && $time_gap > $gPreferences['logout_minutes'] * 60
    && $gCurrentSession->hasObject('gCurrentUser'))
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
    $_SESSION['gCurrentSession'] = $gCurrentSession;

    // create system component
    $gSystemComponent = new Component($gDb);
    $gSystemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
    $gCurrentSession->addObject('gSystemComponent', $gSystemComponent);

    // if cookie ADMIDIO_DATA is set then there could be an auto login
    // the auto login must be done here because after that the corresponding organization must be set
    if(array_key_exists($gCookiePraefix . '_DATA', $_COOKIE))
    {
        // restore user from auto login session
        $autoLogin = new AutoLogin($gDb, $gSessionId);
        $autoLogin->setValidLogin($gCurrentSession, $_COOKIE[$gCookiePraefix . '_DATA']);
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

    if($gCurrentOrganization->getValue('org_id') === 0)
    {
        // organization not found
        exit('<div style="color: #cc0000;">Error: The organization of the config.php could not be found in the database!</div>');
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
    $gCurrentUser->mProfileFieldsData->setDatabase($gDb);

    // checks if user in database session is the same as in php session
    if($gCurrentUser->getValue('usr_id') !== $gCurrentSession->getValue('ses_usr_id'))
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
$sesRenew = $gCurrentSession->getValue('ses_renew');
if($sesRenew === 1 || $sesRenew === 3)
{
    // read new field structure in object and than create new user object with new field structure
    $gProfileFields->readProfileFields($gCurrentOrganization->getValue('org_id'));
    $gCurrentUser->readDataById($gCurrentUser->getValue('usr_id'));
    $gCurrentSession->setValue('ses_renew', 0);
}

// check session if user login is valid
if($gCurrentSession->getValue('ses_usr_id') > 0)
{
    if($gCurrentSession->isValidLogin($gCurrentUser->getValue('usr_id')))
    {
        $gValidLogin = true;
    }
    else
    {
        $gCurrentUser->clear();
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
if(!array_key_exists('theme', $gPreferences))
{
    $gPreferences['theme'] = 'modern';
}
define('THEME_SERVER_PATH', SERVER_PATH . '/adm_themes/' . $gPreferences['theme']);
define('THEME_PATH', $g_root_path . '/adm_themes/' . $gPreferences['theme']);

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
    $gSystemComponent->checkDatabaseVersion();
}
catch(AdmException $e)
{
    $e->showHtml();
}

// set default homepage
if($gValidLogin)
{
    $gHomepage = $g_root_path . '/' . $gPreferences['homepage_login'];
}
else
{
    $gHomepage = $g_root_path . '/' . $gPreferences['homepage_logout'];
}
