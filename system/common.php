<?php

use Admidio\Application\Navigation;
use Admidio\Components\Entity\Component;
use Admidio\Infrastructure\ChangeNotification;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Language;
use Admidio\Menu\ValueObject\Menu;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Session\Entity\Session;
use Admidio\UI\Component\Message;
use Admidio\Users\Entity\User;

/**
 ***********************************************************************************************
 * Basic script for all other Admidio scripts with all the necessary data und
 * variables to run a script in the Admidio environment
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'common.php') {
    exit('This page may not be called directly!');
}

$rootPath = dirname(__DIR__);

// if a config file doesn't exist, then show installation dialog
if (!is_file($rootPath . '/adm_my_files/config.php')) {
    header('Location: install/index.php');
    exit();
}

// load config and init bootstrapping
require_once($rootPath . '/adm_my_files/config.php');
require_once($rootPath . '/system/bootstrap/bootstrap.php');

// global parameters
$gValidLogin = false;
if(!isset($g_organization)) {
    $g_organization = '';
}

try {
    $gDb = Database::createDatabaseInstance();
} catch (Exception $e) {
    echo $e->getMessage();
    exit();
}

// check for empty db and redirect to installation wizard
try {
    if (empty($gDb->getTableColumns(TBL_SESSIONS))) {
        // Postgres does not throw an error, but returns an empty array if the session table does not yet exist.
        header('Location: ' . ADMIDIO_URL . FOLDER_INSTALLATION . '/index.php');
        exit();
    }
} catch (Throwable $t) {
    header('Location: ' . ADMIDIO_URL . FOLDER_INSTALLATION . '/index.php');
    exit();
}


/*********************************************************************************
 Create and validate sessions, check auto login, read session variables
/********************************************************************************/

// start PHP session
try {
    Session::start(COOKIE_PREFIX);
} catch (RuntimeException $exception) {
    // TODO
}

if (array_key_exists('gCurrentSession', $_SESSION)) {
    // read a session object from PHP session
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
    // read a system component
    /**
     * @var Component $gSystemComponent
     */
    $gSystemComponent =& $gCurrentSession->getObject('gSystemComponent');
    // read language data from session and assign them to the language object
    /**
     * @var Language $gL10n
     */
    $gL10n =& $gCurrentSession->getObject('gL10n');
    // read organization data from a session object
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
        // create a new session object and store it in PHP session
        $gCurrentSession = new Session($gDb, COOKIE_PREFIX);
        $_SESSION['gCurrentSession'] = $gCurrentSession;
    }

    // create a system component
    $gSystemComponent = new Component($gDb);
    $gSystemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
    $gCurrentSession->addObject('gSystemComponent', $gSystemComponent);

    // create an object of the organization of config file with their preferences
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
    $gL10n = new Language($gSettingsManager->getString('system_language'), true);
    $gCurrentSession->addObject('gL10n', $gL10n);

    // delete old entries in session table
    $gCurrentSession->tableCleanup($gSettingsManager->getInt('logout_minutes'));
}

// Check if reduced layout should be shown
if (array_key_exists('iframe', $_GET)) {
    $gLayoutReduced = (bool) $_GET['iframe'];
    $_SESSION['gLayoutReduced'] = $_GET['iframe'];
} elseif (array_key_exists('gLayoutReduced', $_SESSION)) {
    $gLayoutReduced = $_SESSION['gLayoutReduced'];
} else {
    $gLayoutReduced = false;
}

$sesUsrId = (int) $gCurrentSession->getValue('ses_usr_id');

// Create a notification object to store and send change notifications to profile fields
$gChangeNotification = new ChangeNotification();

// now if auto login is done, read global user data
if ($gCurrentSession->hasObject('gCurrentUser')) {
    /**
     * @var ProfileFields $gProfileFields
     */
    $gProfileFields =& $gCurrentSession->getObject('gProfileFields');
    /**
     * @var User $gCurrentUser The current user object of the registered user. For visitors, there will be no data loaded.
     */
    $gCurrentUser  =& $gCurrentSession->getObject('gCurrentUser');
    /**
     * @var int $gCurrentOrgId The ID of the current registered user or 0 if it's a visitor.
     */
    $gCurrentUserId = $gCurrentUser->getValue('usr_id');
    $gCurrentUserUUID = $gCurrentUser->getValue('usr_uuid');

    // checks if user in database session is the same as in php session
    if ($gCurrentUserId !== $sesUsrId) {
        $gCurrentUser->clear();
        $gCurrentSession->setValue('ses_usr_id', '');
    }
} else {
    // create an object with current user field structure und user object
    $gProfileFields = new ProfileFields($gDb, $gCurrentOrgId);
    $gCurrentUser   = new User($gDb, $gProfileFields, $sesUsrId);
    $gCurrentUserId = $gCurrentUser->getValue('usr_id');
    $gCurrentUserUUID = $gCurrentUser->getValue('usr_uuid');

    // if a session is created with auto login then update user login data
    // if a user object is created and session has usr_id then this is an auto login,
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
     * @var Menu $gMenu
     */
    $gMenu =& $gCurrentSession->getObject('gMenu');
} else {
    // read a menu from database
    $gMenu = new Menu();
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

// create the necessary objects and parameters

// set default theme if no theme or old theme was set
if (!$gSettingsManager->has('theme') || $gSettingsManager->get('theme') == 'modern') {
    $gSettingsManager->set('theme', 'simple');
}

define('THEME_PATH', ADMIDIO_PATH . FOLDER_THEMES . '/' . $gSettingsManager->getString('theme'));
define('THEME_URL', ADMIDIO_URL  . FOLDER_THEMES . '/' . $gSettingsManager->getString('theme'));


if ($gSettingsManager->has('theme_fallback') && !empty($gSettingsManager->getString('theme_fallback'))) {
    define('THEME_FALLBACK_PATH', ADMIDIO_PATH . FOLDER_THEMES . '/' . $gSettingsManager->getString('theme_fallback'));
    define('THEME_FALLBACK_URL', ADMIDIO_URL  . FOLDER_THEMES . '/' . $gSettingsManager->getString('theme_fallback'));
}

// Create a message object which can be called if a message should be shown
$gMessage = new Message();

// Create an object for navigation between the scripts and modules
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
    // check a version of database against a version of file system and show notice if not equal
    $gSystemComponent->checkDatabaseVersion();
} catch (Throwable $e) {
    $gSettingsManager->disableExceptions();
    $gMessage->hideThemeBody();
    $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/update.php');
    $gMessage->show($e->getMessage(), 'Admidio - '.$gL10n->get('INS_UPDATE'));
}

// set default homepage
if ($gValidLogin) {
    $gHomepage = ADMIDIO_URL . '/' . $gSettingsManager->getString('homepage_login');
} else {
    $gHomepage = ADMIDIO_URL . '/' . $gSettingsManager->getString('homepage_logout');
}
