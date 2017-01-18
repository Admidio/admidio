<?php
/**
 ***********************************************************************************************
 * Logout current user and delete cookie
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
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
$gHomepage = ADMIDIO_URL . '/' . $gPreferences['homepage_logout'];

// message logout successful and go to homepage
$gMessage->setForwardUrl($gHomepage, 2000);
$gMessage->show($gL10n->get('SYS_LOGOUT_SUCCESSFUL'));
// => EXIT
