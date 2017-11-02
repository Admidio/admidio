<?php
/**
 ***********************************************************************************************
 * Basic script for all other Admidio scripts with all the necessary data und
 * variables to run a script in the Admidio environment
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'common.php')
{
    exit('This page may not be called directly!');
}

// load config and init bootstrapping
$rootPath = substr(__FILE__, 0, strpos(__FILE__, DIRECTORY_SEPARATOR . 'adm_program'));
require_once($rootPath . '/adm_my_files/config.php');
require_once($rootPath . '/adm_program/system/bootstrap.php');

// global parameters
$gValidLogin = false;

try
{
    $gDb = new Database($gDbType, $g_adm_srv, $g_adm_port, $g_adm_db, $g_adm_usr, $g_adm_pw);
}
catch(AdmException $e)
{
    $e->showText();
    // => EXIT
}

// create an installation unique cookie prefix and remove special characters
$gCookiePraefix = 'ADMIDIO_' . $g_organization . '_' . $g_adm_db . '_' . $g_tbl_praefix;
$gCookiePraefix = str_replace(array(' ', '.', ',', ';', ':', '[', ']'), '_', $gCookiePraefix);

/*********************************************************************************
 Create and validate sessions, check auto login, read session variables
/********************************************************************************/

// start PHP session
if(!headers_sent())
{
    Session::start($gCookiePraefix);
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

// Session handling
if(array_key_exists('gCurrentSession', $_SESSION) && $_SESSION['gCurrentSession']->hasObject('gCurrentOrganization'))
{
    // read session object from PHP session
    /**
     * @var Session $gCurrentSession
     */
    $gCurrentSession = $_SESSION['gCurrentSession'];
    // reload session data and if necessary the organization object
    $gCurrentSession->refreshSession();
    // read system component
    /**
     * @var Component $gSystemComponent
     */
    $gSystemComponent =& $gCurrentSession->getObject('gSystemComponent');
    // read language data from session and assign them to the language object
    /**
     * @var LanguageData $gLanguageData
     */
    $gLanguageData =& $gCurrentSession->getObject('gLanguageData');
    // read organization data from session object
    /**
     * @var Organization $gCurrentOrganization
     */
    $gCurrentOrganization =& $gCurrentSession->getObject('gCurrentOrganization');
    $gPreferences = $gCurrentOrganization->getPreferences();
}
else
{
    // create new session object and store it in PHP session
    $gCurrentSession = new Session($gDb, $gSessionId, $gCookiePraefix);
    $_SESSION['gCurrentSession'] = $gCurrentSession;

    // create system component
    $gSystemComponent = new Component($gDb);
    $gSystemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
    $gCurrentSession->addObject('gSystemComponent', $gSystemComponent);

    // create object of the organization of config file with their preferences
    if($gCurrentSession->getOrganizationId() > 0)
    {
        $gCurrentOrganization = new Organization($gDb, $gCurrentSession->getOrganizationId());
    }
    else
    {
        $gCurrentOrganization = new Organization($gDb, $g_organization);
    }

    if((int) $gCurrentOrganization->getValue('org_id') === 0)
    {
        $gLogger->error('Organization could not be found!', array('$g_organization' => $g_organization));

        // organization not found
        exit('<div style="color: #cc0000;">Error: The organization of the config.php could not be found in the database!</div>');
    }
    // add the organization to the session
    $gPreferences = $gCurrentOrganization->getPreferences();
    $gCurrentSession->addObject('gCurrentOrganization', $gCurrentOrganization);
    $gCurrentSession->setValue('ses_org_id', $gCurrentOrganization->getValue('org_id'));

    // create a language data object and assign it to the language object
    $gLanguageData = new LanguageData($gPreferences['system_language']);
    $gCurrentSession->addObject('gLanguageData', $gLanguageData);

    // delete old entries in session table
    $gCurrentSession->tableCleanup((int) $gPreferences['logout_minutes']);
}

$gL10n = new Language($gLanguageData);

$orgId    = (int) $gCurrentOrganization->getValue('org_id');
$sesUsrId = (int) $gCurrentSession->getValue('ses_usr_id');

// now if auto login is done, read global user data
if($gCurrentSession->hasObject('gCurrentUser'))
{
    /**
     * @var ProfileFields $gProfileFields
     */
    $gProfileFields =& $gCurrentSession->getObject('gProfileFields');
    /**
     * @var User $gCurrentUser
     */
    $gCurrentUser =& $gCurrentSession->getObject('gCurrentUser');

    // checks if user in database session is the same as in php session
    if((int) $gCurrentUser->getValue('usr_id') !== $sesUsrId)
    {
        $gCurrentUser->clear();
        $gCurrentSession->setValue('ses_usr_id', '');
    }
}
else
{
    // create object with current user field structure und user object
    $gProfileFields = new ProfileFields($gDb, $orgId);
    $gCurrentUser   = new User($gDb, $gProfileFields, $sesUsrId);

    // if session is created with auto login then update user login data
    // if user object is created and session has usr_id then this is an auto login
    // and we should update the login data and count logins
    if($sesUsrId > 0)
    {
        $gCurrentUser->updateLoginData();
    }

    // save all data in session
    $gCurrentSession->addObject('gProfileFields', $gProfileFields);
    $gCurrentSession->addObject('gCurrentUser', $gCurrentUser);
}

$sesRenew = (int) $gCurrentSession->getValue('ses_renew');
$usrId    = (int) $gCurrentUser->getValue('usr_id');

// check if organization or user object must be renewed if data was changed by other users
if($sesRenew === 1 || $sesRenew === 3)
{
    // read new field structure in object and than create new user object with new field structure
    $gProfileFields->readProfileFields($orgId);
    $gCurrentUser->readDataById($usrId);
    $gCurrentSession->setValue('ses_renew', 0);
}

// check session if user login is valid
if($sesUsrId > 0)
{
    if($gCurrentSession->isValidLogin($usrId))
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

// create necessary objects and parameters

// set default theme if no theme was set
if(!array_key_exists('theme', $gPreferences))
{
    $gPreferences['theme'] = 'modern';
}

define('THEME_ADMIDIO_PATH', ADMIDIO_PATH . FOLDER_THEMES . '/' . $gPreferences['theme']); // Will get "THEME_PATH" in v4.0
define('THEME_URL', ADMIDIO_URL . FOLDER_THEMES . '/' . $gPreferences['theme']);
define('THEME_SERVER_PATH', THEME_ADMIDIO_PATH); // TODO deprecated: Remove in Admidio 4.0
define('THEME_PATH', THEME_URL); // TODO deprecated: Remove in Admidio 4.0

// Create message object which can be called if a message should be shown
$gMessage = new Message();

// Create object for navigation between the scripts and modules
// Every URL will be stored in a stack and can be called if user want's to navigate back
if($gCurrentSession->hasObject('gNavigation'))
{
    /**
     * @var Navigation $gNavigation
     */
    $gNavigation =& $gCurrentSession->getObject('gNavigation');
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
    $gMessage->showThemeBody(false);
    $gMessage->hideButtons();
    $gMessage->show($e->getText(), 'Admidio - '.$gL10n->get('INS_UPDATE'));
}

// set default homepage
if($gValidLogin)
{
    $gHomepage = ADMIDIO_URL . '/' . $gPreferences['homepage_login'];
}
else
{
    $gHomepage = ADMIDIO_URL . '/' . $gPreferences['homepage_logout'];
}
