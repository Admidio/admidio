<?php
/******************************************************************************
 * Validate login data, create cookie and sign in the user to Admidio
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');

// Initialize parameters
$userFound  = 0;
$bAutoLogin = false;
$loginname  = '';
$password   = '';
$organizationId = $gCurrentOrganization->getValue('org_id');

// Filter parameters
// parameters could be from login dialog or login plugin !!!
if(array_key_exists('usr_login_name', $_POST) && $_POST['usr_login_name'] !== '')
{
    $loginname = $_POST['usr_login_name'];
    $password  = $_POST['usr_password'];

    if($gPreferences['enable_auto_login'] == 1 && array_key_exists('auto_login', $_POST) && $_POST['auto_login'] == 1)
    {
        $bAutoLogin = true;
    }

    // if user can choose organization then save the selection
    if(array_key_exists('org_id', $_POST) && is_numeric($_POST['org_id']) && $_POST['org_id'] > 0)
    {
        $organizationId = $_POST['org_id'];
    }
}

if(array_key_exists('plg_usr_login_name', $_POST) && $_POST['plg_usr_login_name'] !== '')
{
    $loginname = $_POST['plg_usr_login_name'];
    $password  = $_POST['plg_usr_password'];

    if($gPreferences['enable_auto_login'] == 1 && array_key_exists('plg_auto_login', $_POST) && $_POST['plg_auto_login'] == 1)
    {
        $bAutoLogin = true;
    }

    // if user can choose organization then save the selection
    if(array_key_exists('plg_org_id', $_POST) && is_numeric($_POST['plg_org_id']) && $_POST['plg_org_id'] > 0)
    {
        $organizationId = $_POST['plg_org_id'];
    }
}

if($loginname === '')
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_USERNAME')));
}

if($password === '')
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_PASSWORD')));
}

// TODO Future: check Password min/max Length
//if(strlen($password) < 8)
//{
//    $gMessage->show($gL10n->get('PRO_PASSWORD_LENGTH', $gL10n->get('SYS_PASSWORD')));
//}

// check name and password
// user must have membership of one role of the organization

$sql = 'SELECT DISTINCT usr_id
          FROM '. TBL_USERS. ', '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
         WHERE UPPER(usr_login_name) LIKE UPPER(\''.$loginname.'\')
           AND usr_valid      = 1
           AND mem_usr_id     = usr_id
           AND mem_rol_id     = rol_id
           AND mem_begin     <= \''.DATE_NOW.'\'
           AND mem_end        > \''.DATE_NOW.'\'
           AND rol_valid      = 1
           AND rol_cat_id     = cat_id
           AND cat_org_id     = '.$organizationId;
$result = $gDb->query($sql);

$userFound = $gDb->num_rows($result);
$userRow   = $gDb->fetch_array($result);

if ($userFound === 1)
{
    // if login organization is different to organization of config file then create new session variables
    if($organizationId != $gCurrentOrganization->getValue('org_id'))
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

    try
    {
        // create user object
        $gCurrentUser = new User($gDb, $gProfileFields, $userRow['usr_id']);

        if($gCurrentUser->checkLogin($password, $bAutoLogin))
        {
            // show successful login message
            $login_message = 'SYS_LOGIN_SUCCESSFUL';

            // bei einer Beta-Version noch einen Hinweis ausgeben !
            if(ADMIDIO_VERSION_BETA > 0 && !$gDebug)
            {
                $login_message = 'SYS_BETA_VERSION';
            }

            // falls noch keine Forward-Url gesetzt wurde, dann nach dem Login auf die Startseite verweisen
            if(!array_key_exists('login_forward_url', $_SESSION))
            {
                $_SESSION['login_forward_url'] = $g_root_path . '/' . $gPreferences['homepage_login'];
            }

            // bevor zur entsprechenden Seite weitergeleitet wird, muss noch geprueft werden,
            // ob der Browser Cookies setzen darf -> sonst kein Login moeglich
            $location = 'Location: ' . $g_root_path . '/adm_program/system/cookie_check.php?message_code=' . $login_message;
            header($location);
            exit();
        }
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }
}
else
{
    // now check if login is not released or doesn't exists
    $sql = 'SELECT usr_id
              FROM '. TBL_USERS. ', '.TBL_REGISTRATIONS.'
             WHERE usr_login_name LIKE \''. $loginname. '\'
               AND usr_valid  = 0
               AND reg_usr_id = usr_id
               AND reg_org_id = '.$gCurrentOrganization->getValue('org_id');
    $result = $gDb->query($sql);

    if($gDb->num_rows($result) === 1)
    {
        $gMessage->show($gL10n->get('SYS_LOGIN_NOT_ACTIVATED'));
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_LOGIN_UNKNOWN'));
    }
}
