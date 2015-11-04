<?php
/******************************************************************************
 * Logout current user and delete cookie
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');

// remove user from session
$gCurrentSession->setValue('ses_usr_id', '');
$gCurrentSession->save();

// delete content of cookie
$domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));

// remove auto login
if(isset($_COOKIE[$gCookiePraefix.'_DATA']))
{
    setcookie($gCookiePraefix.'_DATA', '', time() - 1000, '/', $domain, 0);

    $autoLogin = new AutoLogin($gDb, $gSessionId);
    $autoLogin->delete();
}

// if login organization is different to organization of config file then create new session variables
if($g_organization != $gCurrentOrganization->getValue('org_shortname'))
{
    // read organization of config file with their preferences
    $gCurrentOrganization->readDataByColumns(array('org_shortname' => $g_organization));
    $gPreferences = $gCurrentOrganization->getPreferences();

    // read new profile field structure for this organization
    $gProfileFields->readProfileFields($gCurrentOrganization->getValue('org_id'));
}

// clear data from object of current user
$gCurrentUser->clear();

// set homepage to logout page
$gHomepage = $g_root_path.'/'.$gPreferences['homepage_logout'];

$message_code = 'SYS_LOGOUT_SUCCESSFUL';

// message logout successful and go to homepage
$gMessage->setForwardUrl($gHomepage, 2000);
$gMessage->show($gL10n->get($message_code));
