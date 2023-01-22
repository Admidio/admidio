<?php
/**
 ***********************************************************************************************
 * Logout current user and delete cookie
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');

$gValidLogin = false;

// remove user from session
$gCurrentSession->logout();

// if login organization is different to organization of config file then create new session variables
if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) !== 0 && $g_organization !== '') {
    // read organization of config file with their preferences
    $gCurrentOrganization->readDataByColumns(array('org_shortname' => $g_organization));

    // read new profile field structure for this organization
    $gProfileFields->readProfileFields($gCurrentOrgId);

    // save new organization id to session
    $gCurrentSession->setValue('ses_org_id', $gCurrentOrgId);
    $gCurrentSession->save();

    // read all settings from the new organization
    $gSettingsManager = new SettingsManager($gDb, $gCurrentOrgId);
}

// clear data from global objects
$gCurrentUser->clear();
$gMenu->initialize();

// set homepage to logout page
$gHomepage = ADMIDIO_URL . '/' . $gSettingsManager->getString('homepage_logout');

// message logout successful and go to homepage
$gMessage->setForwardUrl($gHomepage, 2000);
$gMessage->show($gL10n->get('SYS_LOGOUT_SUCCESSFUL'));
// => EXIT
