<?php
/**
 ***********************************************************************************************
 * Show registration dialog or the list with new registrations
 *
 * @copyright The Admidio Team
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

try {
    // check if module is active
    if (!$gSettingsManager->getBool('registration_enable_module')) {
        $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
        // => EXIT
    }

    // Initialize and check the parameters
    $getRegistrationId = admFuncVariableIsValid($_GET, 'id', 'string');
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('show_similar')));

    if ($getRegistrationId === '') {
        if (!$gValidLogin) {
            // if there is no login then show a profile form where the user can register himself
            admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_new.php', array('new_user' => '2')));
            // => EXIT
        } elseif (!$gCurrentUser->approveUsers()) {
            // Only Users with the right "approve users" can work with registrations, otherwise exit.
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    } else {
        // user has clicked the link in his registration email, and now we must check if it's a valid request
        // and then confirm his registration

        $userRegistration = new UserRegistration($gDb, $gProfileFields);
        $userRegistration->readDataByUuid($getUserUuid);

        if ($userRegistration->validate($getRegistrationId)) {
            if ($gSettingsManager->getBool('registration_manual_approval')) {
                // notify all authorized members about the new registration to approve it
                $userRegistration->notifyAuthorizedMembers();

                $gMessage->setForwardUrl($gCurrentOrganization->getValue('org_homepage'));
                $gMessage->show($gL10n->get('SYS_REGISTRATION_VALIDATION_OK', array($gCurrentOrganization->getValue('org_longname'))));
                // => EXIT
            } else {
                // user has done a successful registration, so the account could be activated
                $userRegistration->acceptRegistration();

                $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/login.php');
                $gMessage->show($gL10n->get('SYS_REGISTRATION_VALIDATION_OK_SELF'));
                // => EXIT
            }
        } else {
            $gMessage->show($gL10n->get('SYS_REGISTRATION_VALIDATION_FAILED'));
            // => EXIT
        }
    }

    if ($getMode === '' && $getUserUuid === '') {
        // show list with all registrations that should be approved

        // set headline of the script
        $headline = $gL10n->get('SYS_REGISTRATIONS');

        // Navigation in module starts here
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-file-signature');

        // create html page object
        $page = new ModuleRegistration('admidio-registration', $headline);
        $page->createContentRegistrationList();
        $page->show();
    } elseif ($getMode === 'show_similar') {
        // set headline of the script
        $headline = $gL10n->get('SYS_ASSIGN_REGISTRATION');

        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create html page object
        $page = new ModuleContacts('admidio-registration-assign', $headline);
        $registrationUser = new User($gDb, $gProfileFields);
        $registrationUser->readDataByUuid($getUserUuid);
        $page->createContentAssignUser($registrationUser, true);
        $page->show();
    }
} catch (AdmException|Exception|SmartyException $e) {
    $gMessage->show($e->getMessage());
}
