<?php
/**
 ***********************************************************************************************
 * Show form where user can request a new password and handle the request
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * id: Validation id for the link if this is a valid password reset request
 * user_uuid: UUID of the user who wants a reset his password
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\SystemMail;
use Admidio\Infrastructure\Utils\PasswordUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/common.php');

    // Initialize and check the parameters
    $getResetId = admFuncVariableIsValid($_GET, 'id', 'string');
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');

    // "systemmail" and "request password" must be activated
    if (!$gSettingsManager->getBool('system_notifications_enabled') || !$gSettingsManager->getBool('enable_password_recovery')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    if ($gValidLogin) {
        $gMessage->setForwardUrl(ADMIDIO_URL . '/', 2000);
        throw new Exception('SYS_RESET_PW_AREADY_LOGGED_IN');
    }

    if ($getUserUuid !== '') {
        // user has clicked the link in his email, and now we must check if it's a valid request and then show a password form

        // search for a user with the email address that has a valid login and membership to a role
        $sql = 'SELECT usr_id, usr_pw_reset_timestamp
              FROM ' . TBL_USERS . '
             WHERE usr_uuid = ? -- $getUserUuid
               AND usr_pw_reset_id = ? -- $getResetId
               AND usr_valid  = true ';
        $queryParams = array(
            $getUserUuid,
            $getResetId
        );
        $userStatement = $gDb->queryPrepared($sql, $queryParams);

        if ($userStatement->rowCount() === 1) {
            // if the reset id was requested for more than 20 minutes -> show invalid page view
            $row = $userStatement->fetch();
            $timeGap = time() - strtotime($row['usr_pw_reset_timestamp']);

            if ($timeGap > 20 * 60) {
                throw new Exception('SYS_PASSWORD_RESET_INVALID', array('<a href="' . ADMIDIO_URL . FOLDER_SYSTEM . '/password_reset.php">' . $gL10n->get('SYS_PASSWORD_FORGOTTEN') . '</a>'));
            }
        } else {
            throw new Exception('SYS_PASSWORD_RESET_INVALID', array('<a href="' . ADMIDIO_URL . FOLDER_SYSTEM . '/password_reset.php">' . $gL10n->get('SYS_PASSWORD_FORGOTTEN') . '</a>'));
        }

        $user = new User($gDb, $gProfileFields, $row['usr_id']);
        $gNavigation->clear();

        if (!empty($_POST['new_password'])) {
            try {
                // check form field input and sanitized it from malicious content
                $passwordResetSetPasswordForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
                $formValues = $passwordResetSetPasswordForm->validate($_POST);

                // check password and save new password in a database
                $newPassword = $formValues['new_password'];
                $newPasswordConfirm = $formValues['new_password_confirm'];

                // Handle form input
                if (strlen($newPassword) >= PASSWORD_MIN_LENGTH) {
                    if (PasswordUtils::passwordStrength($newPassword, $user->getPasswordUserData()) >= $gSettingsManager->getInt('password_min_strength')) {
                        if ($newPassword === $newPasswordConfirm) {
                            $user->saveChangesWithoutRights();
                            $user->setPassword($newPassword);
                            $user->setValue('usr_tfa_secret', '');
                            $user->setValue('usr_pw_reset_id', '');
                            $user->setValue('usr_pw_reset_timestamp', '');
                            $user->save();

                            // if a user has tried to log in several times, we should reset the invalid counter,
                            // so he could log in with the new password immediately
                            $user->resetInvalidLogins();

                            echo json_encode(array(
                                'status' => 'success',
                                'message' => $gL10n->get('SYS_PASSWORD_RESET_SAVED'),
                                'url' => ADMIDIO_URL . FOLDER_SYSTEM . '/login.php'
                            ));
                            exit();

                        } else {
                            throw new Exception('SYS_PASSWORDS_NOT_EQUAL');
                        }
                    } else {
                        throw new Exception('SYS_PASSWORD_NOT_STRONG_ENOUGH');
                    }
                } else {
                    throw new Exception('SYS_PASSWORD_LENGTH');
                }
            } catch (Exception $e) {
                echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
            }
        } else {
            // show dialog to change password

            $page = PagePresenter::withHtmlIDAndHeadline('admidio-profile-photo-edit', $gL10n->get('SYS_CHANGE_PASSWORD'));

            // show form
            $form = new FormPresenter(
                'adm_password_reset_set_password_form',
                'system/password-reset.set-password.tpl',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/password_reset.php', array('user_uuid' => $getUserUuid, 'id' => $getResetId)),
                $page
            );
            $form->addInput(
                'new_password',
                $gL10n->get('SYS_NEW_PASSWORD'),
                '',
                array(
                    'type' => 'password',
                    'property' => FormPresenter::FIELD_REQUIRED,
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
                array('type' => 'password', 'property' => FormPresenter::FIELD_REQUIRED, 'minLength' => PASSWORD_MIN_LENGTH)
            );
            $form->addSubmitButton(
                'adm_button_save',
                $gL10n->get('SYS_SAVE'),
                array('icon' => 'bi-check-lg')
            );

            $form->addToHtmlPage();
            $gCurrentSession->addFormObject($form);
            $page->show();
        }
    } elseif (!empty($_POST['recipient_email'])) {
        // password reset form was sent, and now we should create an email for the user
        try {
            // check form field input and sanitized it from malicious content
            $passwordResetForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $passwordResetForm->validate($_POST);

            if (StringUtils::strValidCharacters($formValues['recipient_email'], 'email')) {
                // search for a user with the email address that has a valid login and membership to a role
                $sql = 'SELECT usr_id
                      FROM ' . TBL_MEMBERS . '
                INNER JOIN ' . TBL_ROLES . '
                        ON rol_id = mem_rol_id
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                INNER JOIN ' . TBL_USERS . '
                        ON usr_id = mem_usr_id
                INNER JOIN ' . TBL_USER_DATA . ' AS email
                        ON email.usd_usr_id = usr_id
                       AND email.usd_usf_id = ? -- $gProfileFields->getProperty(\'EMAIL\', \'usf_id\')
                       AND email.usd_value  = ? -- $formValues[\'recipient_email\']
                     WHERE LENGTH(usr_login_name) > 0
                       AND rol_valid  = true
                       AND usr_valid  = true
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW
                  GROUP BY usr_id';
                $queryParams = array(
                    $gProfileFields->getProperty('EMAIL', 'usf_id'),
                    $_POST['recipient_email'],
                    DATE_NOW,
                    DATE_NOW
                );
            } else {
                // First try to find user with username. Also, an email could be a username.
                $sql = 'SELECT usr_id
                      FROM ' . TBL_MEMBERS . '
                INNER JOIN ' . TBL_ROLES . '
                        ON rol_id = mem_rol_id
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                INNER JOIN ' . TBL_USERS . '
                        ON usr_id = mem_usr_id
                     WHERE usr_login_name = ? -- $formValues[\'recipient_email\']
                       AND rol_valid  = true
                       AND usr_valid  = true
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW
                  GROUP BY usr_id';
                $queryParams = array(
                    $formValues['recipient_email'],
                    DATE_NOW,
                    DATE_NOW
                );
            }
            $userStatement = $gDb->queryPrepared($sql, $queryParams);
            $count = $userStatement->rowCount();

            // show error if more than one user found
            if ($count > 1) {
                throw new Exception('SYS_LOSTPW_SEVERAL_EMAIL', array($formValues['recipient_email']));
            } elseif ($count === 1) {
                // a valid username or email was found then send new password
                $user = new User($gDb, $gProfileFields, (int)$userStatement->fetchColumn());

                // create an activation id
                $passwordResetId = SecurityUtils::getRandomString(50);

                $user->setValue('usr_pw_reset_id', $passwordResetId);
                $user->setValue('usr_pw_reset_timestamp', DATETIME_NOW);

                $sysmail = new SystemMail($gDb);
                $sysmail->addRecipientsByUser($user->getValue('usr_uuid'));
                $sysmail->setVariable(1, SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/password_reset.php', array('user_uuid' => $user->getValue('usr_uuid'), 'id' => $passwordResetId)));
                $sysmail->sendSystemMail('SYSMAIL_PASSWORD_RESET', $user);

                $user->saveChangesWithoutRights();
                $user->save(false);
            }

            // always show positive feedback to prevent hackers to validate an email-address or username
            $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/login.php');

            if (StringUtils::strValidCharacters($_POST['recipient_email'], 'email')) {
                $message = $gL10n->get('SYS_LOSTPW_SEND_EMAIL', array($_POST['recipient_email']));
            } else {
                $message = $gL10n->get('SYS_LOSTPW_SEND_USERNAME', array($_POST['recipient_email']));
            }
            echo json_encode(array(
                'status' => 'success',
                'message' => $message,
                'url' => ADMIDIO_URL . FOLDER_SYSTEM . '/login.php'
            ));
            exit();
        } catch (Exception $e) {
            if (isset($user)) {
                // initialize password reset columns
                $user->setValue('usr_pw_reset_id', '');
                $user->setValue('usr_pw_reset_timestamp', '');
                $user->saveChangesWithoutRights();
                $user->save(false);
            }

            echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
        }
    } else {
        // HTML_PART

        $headline = $gL10n->get('SYS_PASSWORD_FORGOTTEN');

        // save url to navigation stack
        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create an HTML page object
        $page = PagePresenter::withHtmlIDAndHeadline('admidio-password-reset', $headline);

        // show form
        $form = new FormPresenter(
            'adm_password_reset_form',
            'system/password-reset.tpl',
            ADMIDIO_URL . FOLDER_SYSTEM . '/password_reset.php',
            $page
        );
        $form->addInput(
            'recipient_email',
            $gL10n->get('SYS_USERNAME_OR_EMAIL'),
            '',
            array('maxLength' => 254, 'property' => FormPresenter::FIELD_REQUIRED)
        );

        // if captchas are enabled, then visitors of the website must resolve this
        if (!$gValidLogin && $gSettingsManager->getBool('mail_captcha_enabled')) {
            $form->addCaptcha('adm_captcha_code');
        }

        $form->addSubmitButton(
            'adm_button_send',
            $gL10n->get('SYS_SEND'),
            array('icon' => 'bi-envelope-fill', 'class' => 'offset-sm-3')
        );

        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
        $page->show();
    }
} catch (Throwable $e) {
    handleException($e);
}
