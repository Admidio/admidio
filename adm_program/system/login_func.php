<?php
/**
 ***********************************************************************************************
 * Validate login data, create cookie and sign in the user to Admidio
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');

// Initialize parameters
$bAutoLogin     = false;
$loginname      = '';
$password       = '';
$organizationId = $gCurrentOrgId;

// Filter parameters
// parameters could be from login dialog or login plugin !!!
/**
 * @param string $prefix
 */
function initLoginParams($prefix)
{
    global $bAutoLogin, $loginname, $password, $organizationId, $gSettingsManager;

    $loginname = $_POST[$prefix . 'usr_login_name'];
    $password  = $_POST[$prefix . 'usr_password'];

    if ($gSettingsManager->getBool('enable_auto_login') && array_key_exists($prefix . 'auto_login', $_POST) && $_POST[$prefix . 'auto_login'] == 1) {
        $bAutoLogin = true;
    }
    // if user can choose organization then save the selection
    if (array_key_exists($prefix . 'org_id', $_POST) && is_numeric($_POST[$prefix . 'org_id']) && $_POST[$prefix . 'org_id'] > 0) {
        $organizationId = (int) $_POST[$prefix . 'org_id'];
    }
}

/**
 * tries to create the actual user by setting the global variable $gCurrentUser based on the user creditials given
 * by the $_POST array received in the http request header
 * @throws AdmException in case of errors. exception->text contains a string with the reason why the login failed.
 *                     Possible reasons: SYS_LOGIN_MAX_INVALID_LOGIN
 *                                       SYS_LOGIN_NOT_ACTIVATED
 *                                       SYS_LOGIN_USER_NO_MEMBER_IN_ORGANISATION
 *                                       SYS_LOGIN_USER_NO_ADMINISTRATOR
 *                                       SYS_LOGIN_USERNAME_PASSWORD_INCORRECT
 * @return true|string Return true if login was successful
 */
function createUserObjectFromPost()
{
    global $gCurrentUser, $gSettingsManager, $gMenu, $loginname, $password, $gDb, $gL10n;
    global $gCurrentOrganization, $bAutoLogin, $organizationId, $gProfileFields, $userStatement;
    global $gCurrentSession, $gCurrentOrgId;

    if (array_key_exists('usr_login_name', $_POST) && $_POST['usr_login_name'] !== '') {
        initLoginParams('');
    }

    if (array_key_exists('plg_usr_login_name', $_POST) && $_POST['plg_usr_login_name'] !== '') {
        initLoginParams('plg_');
    }

    if ($loginname === '') {
        throw new AdmException('SYS_FIELD_EMPTY', array($gL10n->get('SYS_USERNAME')));
        // => EXIT
    }

    if ($password === '') {
        throw new AdmException('SYS_FIELD_EMPTY', array($gL10n->get('SYS_PASSWORD')));
        // => EXIT
    }

    // Search for username
    $sql = 'SELECT usr_id
              FROM ' . TBL_USERS . '
             WHERE UPPER(usr_login_name) = UPPER(?)';
    $userStatement = $gDb->queryPrepared($sql, array($loginname));

    if ($userStatement->rowCount() === 0) {
        throw new AdmException('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT');
        // => EXIT
    }

    // if login organization is different to organization of config file then create new session variables
    if ($organizationId !== $gCurrentOrgId) {
        // read organization of config file with their preferences
        $gCurrentOrganization->readDataById($organizationId);
        $gCurrentOrgId = $organizationId;

        // read new profile field structure for this organization
        $gProfileFields->readProfileFields($organizationId);

        // save new organization id to session
        $gCurrentSession->setValue('ses_org_id', $organizationId);
        $gCurrentSession->save();

        // read all settings from the new organization
        $gSettingsManager = new SettingsManager($gDb, $organizationId);
    }

    // remove all menu entries
    $gMenu->initialize();

    // create user object
    $gCurrentUser = new User($gDb, $gProfileFields, (int) $userStatement->fetchColumn());
    $gCurrentUserId = $gCurrentUser->getValue('usr_id');

    return $gCurrentUser->checkLogin($password, $bAutoLogin);
}
