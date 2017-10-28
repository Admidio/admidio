<?php
/**
 ***********************************************************************************************
 * Validate login data, create cookie and sign in the user to Admidio
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');

// Initialize parameters
$bAutoLogin = false;
$loginname  = '';
$password   = '';
$organizationId = (int) $gCurrentOrganization->getValue('org_id');

// Filter parameters
// parameters could be from login dialog or login plugin !!!
/**
 * @param string $prefix
 */
function initLoginParams($prefix = '')
{
    global $bAutoLogin, $loginname, $password, $organizationId, $gPreferences;

    $loginname = $_POST[$prefix.'usr_login_name'];
    $password  = $_POST[$prefix.'usr_password'];

    if($gPreferences['enable_auto_login'] == 1 && array_key_exists($prefix.'auto_login', $_POST) && $_POST[$prefix.'auto_login'] == 1)
    {
        $bAutoLogin = true;
    }
    // if user can choose organization then save the selection
    if(array_key_exists($prefix.'org_id', $_POST) && is_numeric($_POST[$prefix.'org_id']) && $_POST[$prefix.'org_id'] > 0)
    {
        $organizationId = (int) $_POST[$prefix.'org_id'];
    }
}

if(array_key_exists('usr_login_name', $_POST) && $_POST['usr_login_name'] !== '')
{
    initLoginParams('');
}

if(array_key_exists('plg_usr_login_name', $_POST) && $_POST['plg_usr_login_name'] !== '')
{
    initLoginParams('plg_');
}

if($loginname === '')
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_USERNAME')));
    // => EXIT
}

if($password === '')
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_PASSWORD')));
    // => EXIT
}

// TODO Future: check Password min/max Length
//if(strlen($password) < PASSWORD_MIN_LENGTH)
//{
//    $gMessage->show($gL10n->get('PRO_PASSWORD_LENGTH', $gL10n->get('SYS_PASSWORD')));
//}

// Search for username
$sql = 'SELECT usr_id
          FROM '.TBL_USERS.'
         WHERE UPPER(usr_login_name) = UPPER(?)';
$userStatement = $gDb->queryPrepared($sql, array($loginname));

if ($userStatement->rowCount() === 0)
{
    $gLogger->warning('AUTHENTICATION: Incorrect username/password!', array('username' => $loginname, 'password' => '******'));

    $gMessage->show($gL10n->get('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT'));
    // => EXIT
}
else
{
    // if login organization is different to organization of config file then create new session variables
    if($organizationId !== (int) $gCurrentOrganization->getValue('org_id'))
    {
        // read organization of config file with their preferences
        $gCurrentOrganization->readDataById($organizationId);
        $gPreferences = $gCurrentOrganization->getPreferences();

        // read new profile field structure for this organization
        $gProfileFields->readProfileFields($organizationId);

        // save new organization id to session
        $gCurrentSession->setValue('ses_org_id', $organizationId);
        $gCurrentSession->save();
    }

    // create user object
    $gCurrentUser = new User($gDb, $gProfileFields, (int) $userStatement->fetchColumn());

    $checkLoginReturn = $gCurrentUser->checkLogin($password, $bAutoLogin);

    if (is_string($checkLoginReturn))
    {
        $gMessage->show($checkLoginReturn);
        // => EXIT
    }
    else
    {
        // check if browser can set cookies and throw error if not
        if (!array_key_exists($gCookiePraefix.'_PHP_SESSION_ID', $_COOKIE))
        {
            $gMessage->show($gL10n->get('SYS_COOKIE_NOT_SET', DOMAIN));
            // => EXIT
        }

        // remove login page from navigation stack
        if(admStrEndsWith($gNavigation->getUrl(), '/login.php'))
        {
            $gNavigation->deleteLastUrl();
        }

        // If no forward url has been set, then refer to the start page after login
        if(array_key_exists('login_forward_url', $_SESSION))
        {
            $forwardUrl = $_SESSION['login_forward_url'];
        }
        else
        {
            $forwardUrl = $gHomepage;
        }

        unset($_SESSION['login_forward_url']);

        admRedirect($forwardUrl);
        // => EXIT
    }
}
