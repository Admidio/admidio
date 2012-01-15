<?php
/******************************************************************************
 * Logout current user and delete cookie
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');
require_once('classes/table_auto_login.php');

// remove user from session
$gCurrentSession->setValue('ses_usr_id', '');
$gCurrentSession->save();

// delete content of cookie
$domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
//setcookie($gCookiePraefix. '_ID', '' , time() - 1000, '/', $domain, 0);

// remove auto login
if(isset($_COOKIE[$gCookiePraefix. '_DATA']))
{
    setcookie($gCookiePraefix. '_DATA', '', time() - 1000, '/', $domain, 0);
    
    $auto_login = new TableAutoLogin($gDb, $gSessionId);
    $auto_login->delete(); 
}

// if login organization is different to organization of config file then create new session variables
if($g_organization != $gCurrentOrganization->getValue('org_shortname'))
{
	// read organization of config file with their preferences
    $gCurrentOrganization->readData($g_organization);
    $gPreferences = $gCurrentOrganization->getPreferences();
	
	// create object with current user field structure und user object
	$gProfileFields = new ProfileFields($gDb, $gCurrentOrganization);
	
	// save all data in session variables
    $_SESSION['gCurrentOrganization'] =& $gCurrentOrganization;
    $_SESSION['gPreferences']         =& $gPreferences;
    $_SESSION['gProfileFields']       =& $gProfileFields;
}

// clear data from object of current user
$gCurrentUser->clear();

// set homepage to logout page
$gHomepage = $g_root_path. '/'. $gPreferences['homepage_logout'];

$message_code = 'SYS_LOGOUT_SUCCESSFUL';

// if session of forum is active then delete that session
if($gPreferences['enable_forum_interface'] && $gForum->session_valid)
{
    $gForum->userLogoff();
    $message_code = 'SYS_FORUM_LOGOUT';
}

// message logout successful and go to homepage
$gMessage->setForwardUrl($gHomepage, 2000);
$gMessage->show($gL10n->get($message_code));
?>