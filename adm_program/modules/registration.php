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
 * user_uuid_assigned : UUID of the user to whom the new login should be assigned
 * mode      : show_list     - Show a list with all open registrations that should be approved.
 *             show_similar  - Show users with similar names with the option to assign the registration to them.
 *             assign_member - Assign registration to a user who is already a member of the organization
 *             assign_user   - Assign registration to a user who is NOT yet a member of the organization
 *             delete_user   - Delete user account
 *             create_user   - Create new user and assign roles automatically without dialog
 *             send_login    - Registration does not need to be assigned, simply send login data
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\SystemMail;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserRegistration;

try {
    require_once(__DIR__ . '/../system/common.php');

    // check if module is active
    if (!$gSettingsManager->getBool('registration_enable_module')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('show_list', 'show_similar', 'assign_member', 'assign_user', 'delete_user', 'create_user', 'send_login')));
    $getUserUUID = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getUserUUIDAssigned = admFuncVariableIsValid($_GET, 'user_uuid_assigned', 'uuid');
    $getRegistrationId = admFuncVariableIsValid($_GET, 'id', 'string');

    if ($getRegistrationId === '') {
        if (!$gValidLogin) {
            // if there is no login then show a profile form where the user can register himself
            admRedirect(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_new.php');
            // => EXIT
        } elseif (!$gCurrentUser->approveUsers()) {
            // Only Users with the right "approve users" can work with registrations, otherwise exit.
            throw new Exception('SYS_NO_RIGHTS');
        }
    } else {
        // user has clicked the link in his registration email, and now we must check if it's a valid request
        // and then confirm his registration

        $userRegistration = new UserRegistration($gDb, $gProfileFields);
        $userRegistration->readDataByUuid($getUserUUID);

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
            throw new Exception('SYS_REGISTRATION_VALIDATION_FAILED');
        }
    }

    if ($getUserUUID === '') {
        // show list with all registrations that should be approved

        // set headline of the script
        $headline = $gL10n->get('SYS_REGISTRATIONS');

        // Navigation in module starts here
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-card-checklist');

        // create html page object
        $page = new Admidio\UI\View\Registration('adm_registration', $headline);
        $page->setContentFullWidth();
        $page->createRegistrationList();
        $page->show();
    }

    // create user objects
    $registrationUser = new UserRegistration($gDb, $gProfileFields);
    $registrationUser->readDataByUuid($getUserUUID);

    if ($getMode === 'show_similar') {
        // set headline of the script
        $headline = $gL10n->get('SYS_ASSIGN_REGISTRATION');

        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create html page object
        $page = new ModuleContacts('adm_registration_assign', $headline);
        $registrationUser = new User($gDb, $gProfileFields);
        $registrationUser->readDataByUuid($getUserUUID);
        $page->createContentAssignUser($registrationUser, true);
        $page->show();
    } elseif (in_array($getMode, array('assign_member', 'assign_user'))) {
        try {
            $user = new User($gDb, $gProfileFields);
            $user->readDataByUuid($getUserUUIDAssigned);
            $gDb->startTransaction();

            // adopt the data of the registration user to the existing user account
            $registrationUser->adoptUser($user);

            // first delete the new user set, then update the old one to avoid a
            // duplicate key because of the login name
            $registrationUser->notSendEmail();
            $registrationUser->delete();
            $user->save();

            // every new user to the organization will get the default roles for registration
            if ($getMode === 'assign_user') {
                $user->assignDefaultRoles();
            }
            $gDb->endTransaction();

            if ($gSettingsManager->getBool('system_notifications_enabled')) {
                // Send mail to the user to confirm the registration or the assignment to the new organization
                $systemMail = new SystemMail($gDb);
                $systemMail->addRecipientsByUser($getUserUUIDAssigned);
                $systemMail->sendSystemMail('SYSMAIL_REGISTRATION_APPROVED', $user);
                $message = $gL10n->get('SYS_ASSIGN_LOGIN_EMAIL', array($user->getValue('EMAIL')));
            } else {
                $message = $gL10n->get('SYS_ASSIGN_LOGIN_SUCCESSFUL');
            }

            // if current user has the right to assign roles then show roles dialog
            // otherwise go to previous url (default roles are assigned automatically)
            if ($gCurrentUser->manageRoles()) {
                // User already exists, but is not yet a member of the current organization, so first assign roles and then send mail later
                admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/roles.php', array('user_uuid' => $getUserUUIDAssigned, 'accept_registration' => true)));
                // => EXIT
            } else {
                $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration.php');
                $gMessage->show($message);
                // => EXIT
            }
        } catch (Exception $e) {
            // exception is thrown when email couldn't be sent
            // so save user data and then show error
            $user->save();
            $gDb->endTransaction();

            $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
            $gMessage->show($e->getMessage());
            // => EXIT
        }
    } elseif ($getMode === 'delete_user') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        // delete registration
        $registrationUser->delete();
        echo json_encode(array('status' => 'success'));
        exit();
    } elseif ($getMode === 'create_user') {
        // accept a registration, assign necessary roles and send a notification email
        $registrationUser->acceptRegistration();

        // if current user has the right to assign roles then show roles dialog
        // otherwise go to previous url (default roles are assigned automatically)
        if ($gCurrentUser->manageRoles()) {
            admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/roles.php', array('accept_registration' => true, 'user_uuid' => $getUserUUID)));
            // => EXIT
        } else {
            $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
            $gMessage->show($gL10n->get('SYS_ASSIGN_REGISTRATION_SUCCESSFUL'));
            // => EXIT
        }
    } elseif ($getMode === 'send_login') {
        // User already exists and has a login than sent access data with a new password
        $user = new User($gDb, $gProfileFields);
        $user->readDataByUuid($getUserUUIDAssigned);
        $user->sendNewPassword();

        // delete the registration because it isn't needed anymore
        $registrationUser->notSendEmail();
        $registrationUser->delete();
        admRedirect(ADMIDIO_URL.FOLDER_MODULES.'/registration.php');
        // => EXIT
    }
} catch (Throwable $e) {
    $gMessage->show($e->getMessage());
}
