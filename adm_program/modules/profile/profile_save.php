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

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getAcceptRegistration = admFuncVariableIsValid($_GET, 'accept_registration', 'bool');

    // read user data
    if (!$gValidLogin || $getAcceptRegistration) {
        // create user registration object and set requested organization
        $user = new UserRegistration($gDb, $gProfileFields);
        $user->readDataByUuid($getUserUuid);
        if (isset($_POST['reg_org_id'])) {
            $user->setOrganization((int)$_POST['reg_org_id']);
        }
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

    if (isset($_SESSION['profileEditForm'])) {
        $profileEditForm = $_SESSION['profileEditForm'];
        $formValues = $profileEditForm->validate($_POST);
    } else {
        throw new AdmException('SYS_INVALID_PAGE_VIEW');
    }

    // Login name and password must be checked during registration
    if (!$gValidLogin) {
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

    // write all profile fields to the user object
    foreach ($formValues as $key => $value) {
        if (strpos($key, 'usr_') !== 0) {
            $user->setValue($key, $value);
        }
    }

    if (isset($_POST['usr_login_name']) && ($gCurrentUser->isAdministrator() || $getUserUuid === '')) {
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

    // ------------------------------------------------------------
    // redirect to the correct page depending on the call mode
    // ------------------------------------------------------------

    if (!$gValidLogin) {
        // registration was successful then go to homepage
        $gNavigation->deleteLastUrl();
        echo json_encode(array(
            'status' => 'success',
            'message' => $gL10n->get('SYS_REGISTRATION_SAVED'),
            'url' => $gHomepage
        ));
        exit();
    } else {
        if ($getUserUuid === '' || $getAcceptRegistration) {
            // assign a registration or create a new user

            if ($getAcceptRegistration) {
                // accept a registration, assign necessary roles and send a notification email
                $user->acceptRegistration();
                $messageId = 'SYS_ASSIGN_REGISTRATION_SUCCESSFUL';
            } else {
                // a new user is created with the user management module
                // then the user must get the necessary roles
                $user->assignDefaultRoles();
                $messageId = 'SYS_SAVE_DATA';
            }

            // if current user has the right to assign roles then show roles dialog
            // otherwise go to previous url (default roles are assigned automatically)
            if ($gCurrentUser->assignRoles()) {
                echo json_encode(array(
                    'status' => 'success',
                    'url' => SecurityUtils::encodeUrl(
                        ADMIDIO_URL . FOLDER_MODULES . '/profile/roles.php',
                        array(
                            'user_uuid' => $user->getValue('usr_uuid'),
                            'accept_registration' => $getAcceptRegistration,
                            'new_user' => ($getUserUuid === '' ? true : false)
                        ))
                ));
                exit();
            } else {
                $gNavigation->deleteLastUrl();
                echo json_encode(array(
                    'status' => 'success',
                    'message' => $messageId,
                    'url' => $gNavigation->getPreviousUrl()
                ));
                exit();
            }
        } elseif (!$user->getValue('usr_valid')) {
            // a registration was edited then go back to profile view
            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getPreviousUrl()));
            exit();
        } else {
            // go back to profile view
            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            exit();
        }
    }
} catch (AdmException|Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
