<?php
/**
 ***********************************************************************************************
 * Show registration dialog or the list with new registrations
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// check if module is active
if (!$gSettingsManager->getBool('registration_enable_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// if there is no login then show a profile form where the user can register himself
if (!$gValidLogin) {
    admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile_new.php', array('new_user' => '2')));
    // => EXIT
}

// Only Users with the right "approve users" can confirm registrations, otherwise exit.
if (!$gCurrentUser->approveUsers()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set headline of the script
$headline = $gL10n->get('SYS_NEW_REGISTRATIONS');

// Navigation in module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-address-card');

try {
    // create html page object
    $page = new ModuleRegistration('admidio-registration', $headline);
    $page->createContent();
    $page->show();
} catch (SmartyException $e) {
    $gMessage->show($e->getMessage());
}
