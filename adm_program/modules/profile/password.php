<?php
/**
 ***********************************************************************************************
 * Change password
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * user_uuid        : Uuid of the user whose password should be changed
 * mode    - html   : Default mode to show a html form to change the password
 *           change : Change password in database
 ***********************************************************************************************
 */
use Admidio\UserInterface\Form;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    header('Content-type: text/html; charset=utf-8');

    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('requireValue' => true));
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html', 'validValues' => array('html', 'change')));

    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);
    $userId = $user->getValue('usr_id');

    // only the own password could be individual set.
    // Administrators could only set password if systemmail is deactivated and a loginname is set
    if ($gCurrentUserId !== $userId
        && (!isMember($userId)
            || (!$gCurrentUser->isAdministrator() && $gCurrentUserId !== $userId)
            || ($gCurrentUser->isAdministrator() && $user->getValue('usr_login_name') === '')
            || ($gCurrentUser->isAdministrator() && $user->getValue('EMAIL') !== '' && $gSettingsManager->getBool('system_notifications_enabled')))) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    if ($getMode === 'change') {
        if (isset($_SESSION['profilePasswordEditForm'])) {
            $profilePasswordEditForm = $_SESSION['profilePasswordEditForm'];
            $profilePasswordEditForm->validate($_POST);
        } else {
            throw new AdmException('SYS_INVALID_PAGE_VIEW');
        }

        if ($gCurrentUser->isAdministrator() && $gCurrentUserId !== $userId) {
            $oldPassword = '';
        } else {
            $oldPassword = $_POST['old_password'];
        }

        $newPassword = $_POST['new_password'];
        $newPasswordConfirm = $_POST['new_password_confirm'];

        // Handle form input

        if (strlen($newPassword) >= PASSWORD_MIN_LENGTH) {
            if (PasswordUtils::passwordStrength($newPassword, $user->getPasswordUserData()) >= $gSettingsManager->getInt('password_min_strength')) {
                if ($newPassword === $newPasswordConfirm) {
                    // check if old password is correct.
                    // Administrator could change password of other users without this verification.
                    if (PasswordUtils::verify($oldPassword, $user->getValue('usr_password'))
                        || ($gCurrentUser->isAdministrator() && $gCurrentUserId !== $userId)) {
                        $user->saveChangesWithoutRights();
                        $user->setPassword($newPassword);
                        $user->save();

                        // if password of current user changed, then update value in current session
                        if ($gCurrentUserId === (int)$user->getValue('usr_id')) {
                            $gCurrentUser->setPassword($newPassword);
                        }

                        echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_PASSWORD_CHANGED')));
                        exit();
                    } else {
                        throw new AdmException('SYS_PASSWORD_OLD_WRONG');
                    }
                } else {
                    throw new AdmException('SYS_PASSWORDS_NOT_EQUAL');
                }
            } else {
                throw new AdmException('SYS_PASSWORD_NOT_STRONG_ENOUGH');
            }
        } else {
            throw new AdmException('SYS_PASSWORD_LENGTH');
        }
    } elseif ($getMode === 'html') {

        // Show password form

        $zxcvbnUserInputs = json_encode($user->getPasswordUserData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $passwordStrengthLevel = 1;
        if ($gSettingsManager->getInt('password_min_strength')) {
            $passwordStrengthLevel = $gSettingsManager->getInt('password_min_strength');
        }

        // show form
        $form = new Form(
            'password_edit_form',
            'modules/profile.password.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/password.php', array('user_uuid' => $getUserUuid, 'mode' => 'change'))
        );
        if ($gCurrentUserId === $userId) {
            // to change own password user must enter the valid old password for verification
            $form->addInput(
                'old_password',
                $gL10n->get('SYS_CURRENT_PASSWORD'),
                '',
                array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED)
            );
        }
        $form->addInput(
            'new_password',
            $gL10n->get('SYS_NEW_PASSWORD'),
            '',
            array(
                'type' => 'password',
                'property' => HtmlForm::FIELD_REQUIRED,
                'minLength' => PASSWORD_MIN_LENGTH,
                'passwordStrength' => true,
                'passwordUserData' => $user->getPasswordUserData(),
                'helpTextId' => 'SYS_PASSWORD_DESCRIPTION'
            )
        );
        $form->addInput(
            'new_password_confirm',
            $gL10n->get('SYS_REPEAT'),
            '',
            array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH)
        );
        $form->addSubmitButton(
            'btn_save',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg')
        );

        $smarty = HtmlPage::createSmartyObject();
        $smarty->assign('zxcvbnUserInputs', $zxcvbnUserInputs);
        $form->addToSmarty($smarty);
        $_SESSION['profilePasswordEditForm'] = $form;
        echo $smarty->fetch('modules/profile.password.edit.tpl');
    }
} catch (AdmException|Exception $e) {
    if ($getMode === 'change') {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->showInModalWindow();
        $gMessage->show($e->getMessage());
    }
}
