<?php
/**
 ***********************************************************************************************
 * Logout current user and delete cookie
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once('common.php');

// remove user from session
$gCurrentSession->logout();

// if login organization is different to organization of config file then create new session variables
if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) !== 0)
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
