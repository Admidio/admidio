<?php
/**
 ***********************************************************************************************
 * Save profile/registration data
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * user_uuid  : Uuid of the user who should be edited
 * new_user   : 0 - Edit user of the user id
 *              1 - Create a new user
 *              2 - Create a registration
 *              3 - assign/accept a registration
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getUserUuid  = admFuncVariableIsValid($_GET, 'user_uuid', 'string');
$getNewUser = admFuncVariableIsValid($_GET, 'new_user', 'int');

// if current user has no login then only show registration dialog
if (!$gValidLogin) {
    $getNewUser = 2;
}

// save form data in session for back navigation
$_SESSION['profile_request'] = $_POST;

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    $exception->showHtml();
    // => EXIT
}

if (!isset($_POST['usr_login_name'])) {
    $_POST['usr_login_name'] = '';
}
if (!isset($_POST['reg_org_id'])) {
    $_POST['reg_org_id'] = $gCurrentOrgId;
}

// read user data
if ($getNewUser === 2 || $getNewUser === 3) {
    // create user registration object and set requested organization
    $user = new UserRegistration($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);
    $user->setOrganization((int) $_POST['reg_org_id']);
} else {
    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);
}

// pruefen, ob Modul aufgerufen werden darf
switch ($getNewUser) {
    case 0:
        // prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
        if (!$gCurrentUser->hasRightEditProfile($user)) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
        break;

    case 1:
        // prueft, ob der User die notwendigen Rechte hat, neue User anzulegen
        if (!$gCurrentUser->editUsers()) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
        break;

    case 2: // fallthrough
    case 3:
        // Registrierung deaktiviert, also auch diesen Modus sperren
        if (!$gSettingsManager->getBool('registration_enable_module')) {
            $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
            // => EXIT
        }
        break;
}

// ------------------------------------------------------------
// Feldinhalte pruefen der User-Klasse zuordnen
// ------------------------------------------------------------

// bei Registrierung muss Loginname und Pw geprueft werden
if ($getNewUser === 2) {
    if ($_POST['usr_login_name'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_USERNAME'))));
        // => EXIT
    }

    if ($_POST['usr_password'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_PASSWORD'))));
        // => EXIT
    }

    // Passwort muss mindestens 8 Zeichen lang sein
    if (strlen($_POST['usr_password']) < PASSWORD_MIN_LENGTH) {
        $gMessage->show($gL10n->get('PRO_PASSWORD_LENGTH'));
        // => EXIT
    }

    // beide Passwortfelder muessen identisch sein
    if ($_POST['usr_password'] !== $_POST['password_confirm']) {
        $gMessage->show($gL10n->get('SYS_PASSWORDS_NOT_EQUAL'));
        // => EXIT
    }

    if (PasswordUtils::passwordStrength($_POST['usr_password'], $user->getPasswordUserData()) < $gSettingsManager->getInt('password_min_strength')) {
        $gMessage->show($gL10n->get('PRO_PASSWORD_NOT_STRONG_ENOUGH'));
        // => EXIT
    }
}

// now check all profile fields
foreach ($gProfileFields->getProfileFields() as $field) {
    $postId    = 'usf-'. (int) $field->getValue('usf_id');
    $showField = false;

    // at registration check if the field is enabled for registration
    if ($getNewUser === 2 && $field->getValue('usf_registration') == 1) {
        $showField = true;
    }
    // check if the current user has the right to edit this profile field of the selected user
    elseif ($getNewUser !== 2 && $gCurrentUser->allowedEditProfileField($user, $field->getValue('usf_name_intern'))) {
        $showField = true;
    }

    // check and save only fields that aren't disabled
    if ($showField
    && ($field->getValue('usf_disabled') == 0
       || ($field->getValue('usf_disabled') == 1 && $gCurrentUser->hasRightEditProfile($user, false))
       || ($field->getValue('usf_disabled') == 1 && $getNewUser > 0))) {
        if (isset($_POST[$postId])) {
            // required fields must be filled
            if(strlen($_POST[$postId]) === 0
                && $field->hasRequiredInput($user->getValue('usr_id'), (($getNewUser === 2 || $getNewUser === 3) ? true : false))) {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($field->getValue('usf_name'))));
                // => EXIT
            }

            // if social network then extract username from url
            if (in_array($field->getValue('usf_name_intern'), array('FACEBOOK', 'GOOGLE_PLUS', 'TWITTER', 'XING'), true)) {
                if (StringUtils::strValidCharacters($_POST[$postId], 'url') && str_contains($_POST[$postId], '/')) {
                    if (strrpos($_POST[$postId], '/profile.php?id=') > 0) {
                        // extract facebook id (not facebook unique name) from url
                        $_POST[$postId] = substr($_POST[$postId], strrpos($_POST[$postId], '/profile.php?id=') + 16);
                    } else {
                        if (strrpos($_POST[$postId], '/posts') > 0) {
                            $_POST[$postId] = substr($_POST[$postId], 0, strrpos($_POST[$postId], '/posts'));
                        }
                        // xing has the suffix /cv in the url
                        if (strrpos($_POST[$postId], '/cv') > 0) {
                            $_POST[$postId] = substr($_POST[$postId], 0, strrpos($_POST[$postId], '/cv'));
                        }

                        $_POST[$postId] = substr($_POST[$postId], strrpos($_POST[$postId], '/') + 1);
                        if (strrpos($_POST[$postId], '?') > 0) {
                            $_POST[$postId] = substr($_POST[$postId], 0, strrpos($_POST[$postId], '?'));
                        }
                    }
                }
            }

            // Write value from field to user class object
            try {
                $user->setValue($field->getValue('usf_name_intern'), $_POST[$postId]);
            } catch (AdmException $e) {
                // show notice why the value of a profile field could not be set
                $e->showHtml();
            }
        } else {
            // Checkboxes do not pass a value at 0, so set it here if it is not a required field.
            if ($field->getValue('usf_type') === 'CHECKBOX' && $field->getValue('usf_required_input') == 0) {
                $user->setValue($field->getValue('usf_name_intern'), '0');
            } elseif ($field->getValue('usf_required_input') == 1) {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($field->getValue('usf_name'))));
                // => EXIT
            }
        }
    }
}

if ($gCurrentUser->isAdministrator() || $getNewUser > 0) {
    // Only administrators could change loginname or within a new registration
    if ($_POST['usr_login_name'] !== $user->getValue('usr_login_name')) {
        if (strlen($_POST['usr_login_name']) > 0) {
            // check if the username is already assigned
            $sql = 'SELECT usr_uuid
                      FROM '.TBL_USERS.'
                     WHERE usr_login_name = ?';
            $pdoStatement = $gDb->queryPrepared($sql, array($_POST['usr_login_name']));

            if ($pdoStatement->rowCount() > 0 && $pdoStatement->fetchColumn() !== $getUserUuid) {
                $gMessage->show($gL10n->get('PRO_LOGIN_NAME_EXIST'));
                // => EXIT
            }
        }

        if (!$user->setValue('usr_login_name', $_POST['usr_login_name'])) {
            $gMessage->show($gL10n->get('SYS_FIELD_INVALID_CHAR', array($gL10n->get('SYS_USERNAME'))));
            // => EXIT
        }
    }
}

// if registration, then still fill the corresponding fields
if ($getNewUser === 2) {
    $user->setPassword($_POST['usr_password']);

    // At user registration with activated captcha check the captcha input
    if ($gSettingsManager->getBool('enable_registration_captcha')) {
        try {
            FormValidation::checkCaptcha($_POST['captcha_code']);
        } catch (AdmException $e) {
            $e->showHtml();
            // => EXIT
        }
    }
}

// ------------------------------------------------------------
// Save user data to database
// ------------------------------------------------------------
$gDb->startTransaction();

try {
    $user->save();
} catch (AdmException $e) {
    unset($_SESSION['profile_request']);
    $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
    $gNavigation->deleteLastUrl();
    $e->showHtml();
    // => EXIT
}

$gDb->endTransaction();

// if data of the logged in user is changed, then update session variables
if ((int) $user->getValue('usr_id') === $gCurrentUserId) {
    $gCurrentUser = $user;
}

unset($_SESSION['profile_request']);
$gNavigation->deleteLastUrl();

// ------------------------------------------------------------
// redirect to the correct page depending on the call mode
// ------------------------------------------------------------

if ($getNewUser === 1 || $getNewUser === 3) {
    // assign a registration or create a new user

    if ($getNewUser === 3) {
        try {
            // accept a registration, assign necessary roles and send a notification email
            $user->acceptRegistration();
            $messageId = 'PRO_ASSIGN_REGISTRATION_SUCCESSFUL';
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
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/roles.php', array('user_uuid' => $user->getValue('usr_uuid'), 'new_user' => $getNewUser)));
    // => EXIT
    } else {
        $gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
        $gMessage->show($gL10n->get($messageId));
        // => EXIT
    }
} elseif ($getNewUser === 2) {
    // registration was successful then go to homepage
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_REGISTRATION_SAVED'));
// => EXIT
} elseif ($getNewUser === 0 && !$user->getValue('usr_valid')) {
    // a registration was edited then go back to profile view
    $gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
// => EXIT
} else {
    // go back to profile view
    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
    // => EXIT
}
