<?php
/**
 ***********************************************************************************************
 * Save profile/registration data
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * user_uuid  : UUID of the user who should be edited
 * accept_registration : If set to true, another forward url to role assignment will be set.
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

try {
    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getAcceptRegistration = admFuncVariableIsValid($_GET, 'accept_registration', 'bool');

    // save form data in session for back navigation
    $_SESSION['profile_request'] = $_POST;

    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

    if (!isset($_POST['usr_login_name'])) {
        $_POST['usr_login_name'] = '';
    }
    if (!isset($_POST['reg_org_id'])) {
        $_POST['reg_org_id'] = $gCurrentOrgId;
    }

    // read user data
    if (!$gValidLogin || $getAcceptRegistration) {
        // create user registration object and set requested organization
        $user = new UserRegistration($gDb, $gProfileFields);
        $user->readDataByUuid($getUserUuid);
        $user->setOrganization((int)$_POST['reg_org_id']);
    } else {
        $user = new User($gDb, $gProfileFields);
        $user->readDataByUuid($getUserUuid);
    }

    // check if module may be called
    if (!$gValidLogin) {
        // Registration disabled, so also lock this mode
        if (!$gSettingsManager->getBool('registration_enable_module')) {
            throw new AdmException('SYS_MODULE_DISABLED');
        }
    } else {
        if ($getUserUuid === '') {
            // checks if the user has the necessary rights to create new users
            if (!$gCurrentUser->editUsers()) {
                throw new AdmException('SYS_NO_RIGHTS');
            }
        } else {
            // checks if the user has the necessary rights to change the corresponding profile
            if (!$gCurrentUser->hasRightEditProfile($user)) {
                throw new AdmException('SYS_NO_RIGHTS');
            }
        }
    }

    // ------------------------------------------------------------
    // Assign check field contents to the user class
    // ------------------------------------------------------------

    // Login name and password must be checked during registration
    if (!$gValidLogin) {
        if ($_POST['usr_login_name'] === '') {
            throw new AdmException('SYS_FIELD_EMPTY', array('SYS_USERNAME'));
        }

        if ($_POST['usr_password'] === '') {
            throw new AdmException('SYS_FIELD_EMPTY', array('SYS_PASSWORD'));
        }

        // Passwort muss mindestens 8 Zeichen lang sein
        if (strlen($_POST['usr_password']) < PASSWORD_MIN_LENGTH) {
            throw new AdmException('SYS_PASSWORD_LENGTH');
        }

        // both password fields must be identical
        if ($_POST['usr_password'] !== $_POST['password_confirm']) {
            throw new AdmException('SYS_PASSWORDS_NOT_EQUAL');
        }

        if (PasswordUtils::passwordStrength($_POST['usr_password'], $user->getPasswordUserData()) < $gSettingsManager->getInt('password_min_strength')) {
            throw new AdmException('SYS_PASSWORD_NOT_STRONG_ENOUGH');
        }
    }

    // now check all profile fields
    foreach ($gProfileFields->getProfileFields() as $field) {
        $postId = 'usf-' . (int)$field->getValue('usf_id');
        $showField = false;

        // at registration check if the field is enabled for registration
        if (!$gValidLogin && $field->getValue('usf_registration') == 1) {
            $showField = true;
        } // check if the current user has the right to edit this profile field of the selected user
        elseif ($gValidLogin && $gCurrentUser->allowedEditProfileField($user, $field->getValue('usf_name_intern'))) {
            $showField = true;
        }

        // check and save only fields that aren't disabled
        if ($showField
            && ($field->getValue('usf_disabled') == 0
                || ($field->getValue('usf_disabled') == 1 && $gCurrentUser->hasRightEditProfile($user, false))
                || ($field->getValue('usf_disabled') == 1 && $getUserUuid === ''))) {
            if (isset($_POST[$postId])) {
                // required fields must be filled
                if (strlen($_POST[$postId]) === 0
                    && $field->hasRequiredInput($user->getValue('usr_id'), ((!$gValidLogin || $getAcceptRegistration) ? true : false))) {
                    throw new AdmException('SYS_FIELD_EMPTY', array($field->getValue('usf_name')));
                }

                // Write value from field to user class object
                $user->setValue($field->getValue('usf_name_intern'), $_POST[$postId]);
            } else {
                // Checkboxes do not pass a value at 0, so set it here if it is not a required field.
                if ($field->getValue('usf_type') === 'CHECKBOX' && $field->getValue('usf_required_input') == 0) {
                    $user->setValue($field->getValue('usf_name_intern'), '0');
                } elseif ($field->getValue('usf_required_input') == 1) {
                    throw new AdmException('SYS_FIELD_EMPTY', array($field->getValue('usf_name')));
                }
            }
        }
    }

    if ($gCurrentUser->isAdministrator() || $getUserUuid === '') {
        // Only administrators could change login name or within a new registration
        if ($_POST['usr_login_name'] !== $user->getValue('usr_login_name')) {
            if (strlen($_POST['usr_login_name']) > 0) {
                // check if the username is already assigned
                $sql = 'SELECT usr_uuid
                      FROM ' . TBL_USERS . '
                     WHERE usr_login_name = ?';
                $pdoStatement = $gDb->queryPrepared($sql, array($_POST['usr_login_name']));

                if ($pdoStatement->rowCount() > 0 && $pdoStatement->fetchColumn() !== $getUserUuid) {
                    throw new AdmException('SYS_LOGIN_NAME_EXIST');
                }
            }

            if (!$user->setValue('usr_login_name', $_POST['usr_login_name'])) {
                throw new AdmException('SYS_FIELD_INVALID_CHAR', array('SYS_USERNAME'));
            }
        }
    }

    // if registration, then still fill the corresponding fields
    if (!$gValidLogin) {
        $user->setPassword($_POST['usr_password']);

        // At user registration with activated captcha check the captcha input
        if ($gSettingsManager->getBool('registration_enable_captcha')) {
            FormValidation::checkCaptcha($_POST['captcha_code']);
        }
    }

    // ------------------------------------------------------------
    // Save user data to database
    // ------------------------------------------------------------
    $gDb->startTransaction();

    $user->save();

    $gDb->endTransaction();

    // if data of the logged-in user is changed, then update session variables
    if ((int)$user->getValue('usr_id') === $gCurrentUserId) {
        $gCurrentUser = $user;
    }

    unset($_SESSION['profile_request']);

    // ------------------------------------------------------------
    // redirect to the correct page depending on the call mode
    // ------------------------------------------------------------

    if (!$gValidLogin) {
        // registration was successful then go to homepage
        $gNavigation->deleteLastUrl();
        $gMessage->setForwardUrl($gHomepage);
        $gMessage->show($gL10n->get('SYS_REGISTRATION_SAVED'));
        // => EXIT
    } else {
        if ($getUserUuid !== '' && !$user->getValue('usr_valid')) {
            // a registration was edited then go back to profile view
            $gNavigation->deleteLastUrl();
            $gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
            $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
            // => EXIT
        } elseif ($getUserUuid === '' || $getAcceptRegistration) {
            // assign a registration or create a new user

            if ($getAcceptRegistration) {
                try {
                    // accept a registration, assign necessary roles and send a notification email
                    $user->acceptRegistration();
                    $messageId = 'SYS_ASSIGN_REGISTRATION_SUCCESSFUL';
                } catch (AdmException $e) {
                    $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
                    $e->showHtml();
                    // => EXIT
                }
            } else {
                // a new user is created with the user management module
                // then the user must get the necessary roles
                $user->assignDefaultRoles();
                $messageId = 'SYS_SAVE_DATA';
            }

            // if current user has the right to assign roles then show roles dialog
            // otherwise go to previous url (default roles are assigned automatically)
            if ($gCurrentUser->assignRoles()) {
                admRedirect(SecurityUtils::encodeUrl(
                    ADMIDIO_URL . FOLDER_MODULES . '/profile/roles.php',
                        array(
                            'user_uuid' => $user->getValue('usr_uuid'),
                            'accept_registration' => $getAcceptRegistration,
                            'new_user' => ($getUserUuid === '' ? true : false)
                        )
                ));
                // => EXIT
            } else {
                $gNavigation->deleteLastUrl();
                $gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
                $gMessage->show($gL10n->get($messageId));
                // => EXIT
            }
        } else {
            // go back to profile view
            $gNavigation->deleteLastUrl();
            $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
            $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
            // => EXIT
        }
    }
} catch (AdmException|Exception $e) {
    $gMessage->show($e->getMessage());
}
