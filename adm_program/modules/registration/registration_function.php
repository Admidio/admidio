<?php
/**
 ***********************************************************************************************
 * Approve new registrations - functions
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode: 1 - Assign registration to a user who is already a member of the organization
 *       2 - Assign registration to a user who is NOT yet a member of the organization
 *       4 - Delete user account
 *       5 - Create new user and assign roles automatically without dialog
 *       6 - Registration does not need to be assigned, simply send login data
 * new_user_uuid: UUID of the new registered user to be processed
 * user_uuid:     UUID of the user to whom the new login should be assigned
 *****************************************************************************/
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // Initialize and check the parameters
    $getMode        = admFuncVariableIsValid($_GET, 'mode', 'int', array('requireValue' => true, 'validValues' => array(1, 2, 3, 4, 5, 6)));
    $getNewUserUuid = admFuncVariableIsValid($_GET, 'new_user_uuid', 'string', array('requireValue' => true));
    $getUserUuid    = admFuncVariableIsValid($_GET, 'user_uuid', 'string');

    if ($getMode == 4) {
        $gMessage->showHtmlTextOnly();
    }

    // only administrators could approve new users
    if (!$gCurrentUser->approveUsers()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // module must be enabled in the settings
    if (!$gSettingsManager->getBool('registration_enable_module')) {
        $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
        // => EXIT
    }

    // create user objects
    $registrationUser = new UserRegistration($gDb, $gProfileFields);
    $registrationUser->readDataByUuid($getNewUserUuid);

    if ($getMode === 1 || $getMode === 2) {
        try {
            $user = new User($gDb, $gProfileFields);
            $user->readDataByUuid($getUserUuid);
            $gDb->startTransaction();

            // adopt the data of the registration user to the existing user account
            $registrationUser->adoptUser($user);

            // first delete the new user set, then update the old one to avoid a
            // duplicate key because of the login name
            $registrationUser->notSendEmail();
            $registrationUser->delete();
            $user->save();

            // every new user to the organization will get the default roles for registration
            if ($getMode === 2) {
                $user->assignDefaultRoles();
            }
            $gDb->endTransaction();

            if ($gSettingsManager->getBool('system_notifications_enabled')) {
                // Send mail to the user to confirm the registration or the assignment to the new organization
                $systemMail = new SystemMail($gDb);
                $systemMail->addRecipientsByUser($getUserUuid);
                $systemMail->sendSystemMail('SYSMAIL_REGISTRATION_APPROVED', $user);
                $message = $gL10n->get('SYS_ASSIGN_LOGIN_EMAIL', array($user->getValue('EMAIL')));
            } else {
                $message = $gL10n->get('SYS_ASSIGN_LOGIN_SUCCESSFUL');
            }

            // if current user has the right to assign roles then show roles dialog
            // otherwise go to previous url (default roles are assigned automatically)
            if ($gCurrentUser->manageRoles()) {
                // User already exists, but is not yet a member of the current organization, so first assign roles and then send mail later
                admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/roles.php', array('user_uuid' => $getUserUuid, 'new_user' => 3)));
                // => EXIT
            } else {
                $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration.php');
                $gMessage->show($message);
                // => EXIT
            }
        } catch (AdmException $e) {
            // exception is thrown when email couldn't be sent
            // so save user data and then show error
            $user->save();
            $gDb->endTransaction();

            $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
            $e->showHtml();
            // => EXIT
        }
    } elseif ($getMode === 4) {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

        // delete registration
        $registrationUser->delete();

        // return successful delete for XMLHttpRequest
        echo 'done';
    } elseif ($getMode === 5) {
        // accept a registration, assign necessary roles and send a notification email
        $registrationUser->acceptRegistration();

        // if current user has the right to assign roles then show roles dialog
        // otherwise go to previous url (default roles are assigned automatically)
        if ($gCurrentUser->manageRoles()) {
            admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/roles.php', array('new_user' => '3', 'user_uuid' => $getNewUserUuid)));
        // => EXIT
        } else {
            $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
            $gMessage->show($gL10n->get('SYS_ASSIGN_REGISTRATION_SUCCESSFUL'));
            // => EXIT
        }
    } elseif ($getMode === 6) {
        // User already exists and has a login

        // delete registration
        $registrationUser->delete();

        // Resend access data
        $gNavigation->addUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration.php');
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/contacts/contacts_function.php', array('mode' => '4', 'user_uuid' => $getUserUuid)));
        // => EXIT
    }
} catch (AdmException | Exception $e) {
    $gMessage->show($e->getMessage());
}
