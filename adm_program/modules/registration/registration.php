<?php
/**
 ***********************************************************************************************
 * Show registration dialog or the list with new registrations
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * id        : Validation id to confirm the registration by the user.
 * user_uuid : UUID of the user who wants to confirm his registration.
 * mode      : show_similar - Show users with similar names with the option to assign the registration to them.
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// check if module is active
if (!$gSettingsManager->getBool('registration_enable_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Initialize and check the parameters
$getRegistrationId = admFuncVariableIsValid($_GET, 'id', 'string');
$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string');
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('show_similar')));

// Only Users with the right "approve users" can work with registrations, otherwise exit.
// User is only allowed to confirm his own registration with the registration ID
if ($getRegistrationId === '' && !$gCurrentUser->approveUsers()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if ($getRegistrationId !== '') {
    // user has clicked the link in his registration email, and now we must check if it's a valid request
    // and then confirm his registration

    try {
        $userRegistration = new UserRegistration($gDb, $gProfileFields);
        $userRegistration->readDataByUuid($getUserUuid);

        if ($userRegistration->validate($getRegistrationId)) {
            if ($gSettingsManager->getBool('registration_manual_approval')) {
                // notify all authorized members about the new registration to approve it
                $userRegistration->notifyAuthorizedMembers();

                $gMessage->show($gL10n->get('SYS_REGISTRATION_VALIDATION_OK', array($gCurrentOrganization->getValue('org_longname'))));
                // => EXIT
            } else {
                // user has done a successful registration, so the account could be activated
                $userRegistration->acceptRegistration();

                $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_SYSTEM.'/login.php');
                $gMessage->show($gL10n->get('SYS_REGISTRATION_VALIDATION_OK_SELF'));
                // => EXIT
            }
        } else {
            $gMessage->show($gL10n->get('SYS_REGISTRATION_VALIDATION_FAILED'));
            // => EXIT
        }
    } catch (AdmException $e) {
        $e->showHtml();
    }
} elseif ($getMode === '' && $getUserUuid === '') {
    // show list with all registrations that should be approved

    // if there is no login then show a profile form where the user can register himself
    if (!$gValidLogin) {
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile_new.php', array('new_user' => '2')));
        // => EXIT
    }

    // set headline of the script
    $headline = $gL10n->get('SYS_NEW_REGISTRATIONS');

    // Navigation in module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-address-card');

    try {
        // create html page object
        $page = new ModuleRegistration('admidio-registration', $headline);
        $page->createContentRegistrationList();
        $page->show();
    } catch (SmartyException $e) {
        $gMessage->show($e->getMessage());
    } catch (AdmException $e) {
        $e->showHtml();
    }
} elseif ($getMode === 'show_similar') {
    // set headline of the script
    $headline = $gL10n->get('SYS_ASSIGN_REGISTRATION');

    $gNavigation->addUrl(CURRENT_URL, $headline);

    try {
        // create html page object
        $page = new ModuleRegistration('admidio-registration-assign', $headline);
        $page->createContentAssignUser($getUserUuid);
        $page->show();
    } catch (SmartyException $e) {
        $gMessage->show($e->getMessage());
    } catch (AdmException $e) {
        $e->showHtml();
    }
}
