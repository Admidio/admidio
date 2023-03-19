<?php
/**
 ***********************************************************************************************
 * Basic script for all other Admidio scripts with all the necessary data und
 * variables to run a script in the Admidio environment
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'common.php') {
    exit('This page may not be called directly!');
}

$rootPath = dirname(dirname(__DIR__));

// if config file doesn't exists, than show installation dialog
if (!is_file($rootPath . '/adm_my_files/config.php')) {
    header('Location: adm_program/installation/index.php');
    exit();
}

// load config and init bootstrapping
require_once($rootPath . '/adm_my_files/config.php');
require_once($rootPath . '/adm_program/system/bootstrap/bootstrap.php');

// global parameters
$gValidLogin = false;
if(!isset($g_organization)) {
    $g_organization = '';
}

try {
    $gDb = Database::createDatabaseInstance();
} catch (AdmException $e) {
    $e->showText();
    // => EXIT
}

/*********************************************************************************
 Create and validate sessions, check auto login, read session variables
/********************************************************************************/

// start PHP session
try {
    Session::start(COOKIE_PREFIX);
} catch (\RuntimeException $exception) {
    // TODO
}

if (array_key_exists('gCurrentSession', $_SESSION)) {
    // read session object from PHP session
    /**
     * @var Session $gCurrentSession The global session object that will store the other global objects and
     *                               validates the session against the stored session in the database
     */
    $gCurrentSession = $_SESSION['gCurrentSession'];
    $gCurrentSession->refresh();
}

// Session handling
if (array_key_exists('gCurrentSession', $_SESSION)
    && $_SESSION['gCurrentSession']->hasObject('gCurrentOrganization')
    && $_SESSION['gCurrentSession']->getValue('ses_reload') === false) {
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
    $gSettingsManager =& $gCurrentOrganization->getSettingsManager();
    /**
     * @var int $gCurrentOrgId The ID of the current organization.
     */
    $gCurrentOrgId = $gCurrentOrganization->getValue('org_id');
} else {
    if (array_key_exists('gCurrentSession', $_SESSION)) {
        $gCurrentSession->initializeObjects();
    } else {
        // create new session object and store it in PHP session
        $gCurrentSession = new Session($gDb, COOKIE_PREFIX);
        $_SESSION['gCurrentSession'] = $gCurrentSession;
    }

    // create system component
    $gSystemComponent = new Component($gDb);
    $gSystemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
    $gCurrentSession->addObject('gSystemComponent', $gSystemComponent);

    // create object of the organization of config file with their preferences
    if ($gCurrentSession->getOrganizationId() > 0) {
        $gCurrentOrganization = new Organization($gDb, $gCurrentSession->getOrganizationId());
    } else {
        $gCurrentOrganization = Organization::createDefaultOrganizationObject($gDb, $g_organization);
    }

    /**
     * @var int $gCurrentOrgId The ID of the current organization.
     */
    $gCurrentOrgId = $gCurrentOrganization->getValue('org_id');

    if ($gCurrentOrgId === 0) {
        $gLogger->error('Organization could not be found!', array('$g_organization' => $g_organization));

        // organization not found
        exit('<div style="color: #cc0000;">Error: The organization of the config.php could not be found in the database!</div>');
    }
    // add the organization to the session
    $gSettingsManager =& $gCurrentOrganization->getSettingsManager();
    $gCurrentSession->addObject('gCurrentOrganization', $gCurrentOrganization);
    $gCurrentSession->setValue('ses_org_id', $gCurrentOrgId);

    // create a language data object and assign it to the language object
    $gLanguageData = new LanguageData($gSettingsManager->getString('system_language'));
    $gCurrentSession->addObject('gLanguageData', $gLanguageData);

    // delete old entries in session table
    $gCurrentSession->tableCleanup($gSettingsManager->getInt('logout_minutes'));
}

$gL10n = new Language($gLanguageData);

$sesUsrId      = $gCurrentSession->getValue('ses_usr_id');

// Create a notification object to store and send change notifications to profile fields
$gChangeNotification = new ChangeNotification();

// now if auto login is done, read global user data
if ($gCurrentSession->hasObject('gCurrentUser')) {
    /**
     * @var ProfileFields $gProfileFields
     */
    $gProfileFields =& $gCurrentSession->getObject('gProfileFields');
    /**
     * @var User $gCurrentUser The current user object of the registered user. For visitors there will be no data loaded.
     */
    $gCurrentUser  =& $gCurrentSession->getObject('gCurrentUser');
    /**
     * @var int $gCurrentOrgId The ID of the current registered user or 0 if its an visitor.
     */
    $gCurrentUserId = $gCurrentUser->getValue('usr_id');

    // checks if user in database session is the same as in php session
    if ($gCurrentUserId !== $sesUsrId) {
        $gCurrentUser->clear();
        $gCurrentSession->setValue('ses_usr_id', '');
    }
} else {
    // create object with current user field structure und user object
    $gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
    $gCurrentUser   = new User($gDb, $gProfileFields, $sesUsrId);
    $gCurrentUserId = $gCurrentUser->getValue('usr_id');

    // if session is created with auto login then update user login data
    // if user object is created and session has usr_id then this is an auto login
    // and we should update the login data and count logins
    if ($sesUsrId > 0) {
        $gCurrentUser->updateLoginData();
    }

    // save all data in session
    $gCurrentSession->addObject('gProfileFields', $gProfileFields);
    $gCurrentSession->addObject('gCurrentUser', $gCurrentUser);
}

// create a global menu object that reads the menu structure only once
if ($gCurrentSession->hasObject('gMenu')) {
    /**
     * @var MainMenu $gMenu
     */
    $gMenu =& $gCurrentSession->getObject('gMenu');
} else {
    // read menu from database
    $gMenu = new MainMenu();
    $gCurrentSession->addObject('gMenu', $gMenu);
}

// check session if user login is valid
if ($sesUsrId > 0) {
    if ($gCurrentSession->isValidLogin($gCurrentUserId)) {
        $gValidLogin = true;
    } else {
        $gCurrentUser->clear();
    }
}

// update session recordset (i.a. refresh timestamp)
$gCurrentSession->setValue('ses_reload', 0);
$gCurrentSession->save();

// create necessary objects and parameters

// set default theme if no theme or old theme was set
if (!$gSettingsManager->has('theme') || $gSettingsManager->get('theme') == 'modern') {
    $gSettingsManager->set('theme', 'simple');
}

define('THEME_PATH', ADMIDIO_PATH . FOLDER_THEMES . '/' . $gSettingsManager->getString('theme'));
define('THEME_URL', ADMIDIO_URL  . FOLDER_THEMES . '/' . $gSettingsManager->getString('theme'));

// Create message object which can be called if a message should be shown
$gMessage = new Message();

// Create object for navigation between the scripts and modules
// Every URL will be stored in a stack and can be called if user want's to navigate back
if ($gCurrentSession->hasObject('gNavigation')) {
    /**
     * @var Navigation $gNavigation
     */
    $gNavigation =& $gCurrentSession->getObject('gNavigation');
} else {
    $gNavigation = new Navigation();
    $gCurrentSession->addObject('gNavigation', $gNavigation);
}

try {
    // check version of database against version of file system and show notice if not equal
    $gSystemComponent->checkDatabaseVersion();
} catch (AdmException $e) {
    $gSettingsManager->disableExceptions();
    $gMessage->showThemeBody(false);
    $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/update.php');
    $gMessage->show($e->getText(), 'Admidio - '.$gL10n->get('INS_UPDATE'));
}

// set default homepage
if ($gValidLogin) {
    $gHomepage = ADMIDIO_URL . '/' . $gSettingsManager->getString('homepage_login');
} else {
    $gHomepage = ADMIDIO_URL . '/' . $gSettingsManager->getString('homepage_logout');
}
