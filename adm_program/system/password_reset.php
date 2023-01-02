<?php
/**
 ***********************************************************************************************
 * Show form where user can request a new password and handle the request
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * id        : Validation id for the link if this is a valid password reset request
 * user_uuid : UUID of the user who wants a reset his password
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');

// Initialize and check the parameters
$getResetId  = admFuncVariableIsValid($_GET, 'id', 'string');
$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string');

// "systemmail" and "request password" must be activated
if (!$gSettingsManager->getBool('system_notifications_enabled') || !$gSettingsManager->getBool('enable_password_recovery')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if ($gValidLogin) {
    $gMessage->setForwardUrl(ADMIDIO_URL.'/adm_program/', 2000);
    $gMessage->show($gL10n->get('SYS_RESET_PW_AREADY_LOGGED_IN'));
    // => EXIT
}

if ($getUserUuid !== '') {
    // user has clicked the link in his email and now we must check if it's a valid request and then show password form

    // search for user with the email address that have a valid login and membership to a role
    $sql = 'SELECT usr_id, usr_pw_reset_timestamp
              FROM '.TBL_USERS.'
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
            $gMessage->show($gL10n->get('SYS_PASSWORD_RESET_INVALID', array('<a href="'.ADMIDIO_URL.FOLDER_SYSTEM.'/password_reset.php">'.$gL10n->get('SYS_PASSWORD_FORGOTTEN').'</a>')));
            // => EXIT
        }
    } else {
        $gMessage->show($gL10n->get('SYS_PASSWORD_RESET_INVALID', array('<a href="'.ADMIDIO_URL.FOLDER_SYSTEM.'/password_reset.php">'.$gL10n->get('SYS_PASSWORD_FORGOTTEN').'</a>')));
        // => EXIT
    }

    $user = new User($gDb, $gProfileFields, $row['usr_id']);
    $gNavigation->clear();

    if (!empty($_POST['new_password'])) {
        try {
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

            // check password and save new password in database

            $newPassword        = $_POST['new_password'];
            $newPasswordConfirm = $_POST['new_password_confirm'];


            // Handle form input

            if ($newPassword !== '' && $newPasswordConfirm !== '') {
                if (strlen($newPassword) >= PASSWORD_MIN_LENGTH) {
                    if (PasswordUtils::passwordStrength($newPassword, $user->getPasswordUserData()) >= $gSettingsManager->getInt('password_min_strength')) {
                        if ($newPassword === $newPasswordConfirm) {
                            $user->saveChangesWithoutRights();
                            $user->setPassword($newPassword);
                            $user->setValue('usr_pw_reset_id', '');
                            $user->setValue('usr_pw_reset_timestamp', '');
                            $user->save();

                            // if user has tried login several times we should reset the invalid counter,
                            // so he could login with the new password immediately
                            $user->resetInvalidLogins();

                            $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_SYSTEM.'/login.php', 2000);
                            $gMessage->show($gL10n->get('SYS_PASSWORD_RESET_SAVED'));
                        // => EXIT
                        } else {
                            $phrase = $gL10n->get('SYS_PASSWORDS_NOT_EQUAL');
                        }
                    } else {
                        $phrase = $gL10n->get('PRO_PASSWORD_NOT_STRONG_ENOUGH');
                    }
                } else {
                    $phrase = $gL10n->get('PRO_PASSWORD_LENGTH');
                }
            } else {
                $phrase = $gL10n->get('SYS_FIELDS_EMPTY');
            }
        } catch (AdmException $exception) {
            $exception->showHtml();
            // => EXIT
        }

        $gMessage->show($phrase);
    // => EXIT
    } else {
        // show dialog to change password

        $page = new HtmlPage('admidio-profile-photo-edit', $gL10n->get('SYS_CHANGE_PASSWORD'));

        // show form
        $form = new HtmlForm('password_form', SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_SYSTEM.'/password_reset.php', array('user_uuid' => $getUserUuid, 'id' => $getResetId)), $page);
        $form->addInput(
            'new_password',
            $gL10n->get('PRO_NEW_PASSWORD'),
            '',
            array(
                'type'             => 'password',
                'property'         => HtmlForm::FIELD_REQUIRED,
                'minLength'        => PASSWORD_MIN_LENGTH,
                'passwordStrength' => true,
                'passwordUserData' => $user->getPasswordUserData(),
                'helpTextIdInline' => 'PRO_PASSWORD_DESCRIPTION'
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
            array('icon' => 'fa-check', 'class' => ' offset-sm-3')
        );

        $page->addHtml($form->show());
        $page->show();
    }
} elseif (!empty($_POST['recipient_email'])) {
    // password reset form was send and now we should create an email for the user
    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

        // if user is not logged in and captcha is activated then check captcha
        if (!$gValidLogin && $gSettingsManager->getBool('enable_mail_captcha')) {
            FormValidation::checkCaptcha($_POST['captcha_code']);
        }

        if (StringUtils::strValidCharacters($_POST['recipient_email'], 'email')) {
            // search for user with the email address that have a valid login and membership to a role
            $sql = 'SELECT usr_id
                      FROM '.TBL_MEMBERS.'
                INNER JOIN '.TBL_ROLES.'
                        ON rol_id = mem_rol_id
                INNER JOIN '.TBL_CATEGORIES.'
                        ON cat_id = rol_cat_id
                INNER JOIN '.TBL_USERS.'
                        ON usr_id = mem_usr_id
                INNER JOIN '.TBL_USER_DATA.' AS email
                        ON email.usd_usr_id = usr_id
                       AND email.usd_usf_id = ? -- $gProfileFields->getProperty(\'EMAIL\', \'usf_id\')
                       AND email.usd_value  = ? -- $_POST[\'recipient_email\']
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
            $userStatement = $gDb->queryPrepared($sql, $queryParams);
            $count = $userStatement->rowCount();
        } else {
            // first try to find user with username. Also an email could be a username.
            $sql = 'SELECT usr_id
                      FROM '.TBL_MEMBERS.'
                INNER JOIN '.TBL_ROLES.'
                        ON rol_id = mem_rol_id
                INNER JOIN '.TBL_CATEGORIES.'
                        ON cat_id = rol_cat_id
                INNER JOIN '.TBL_USERS.'
                        ON usr_id = mem_usr_id
                     WHERE usr_login_name = ? -- $_POST[\'recipient_email\']
                       AND rol_valid  = true
                       AND usr_valid  = true
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW
                  GROUP BY usr_id';
            $queryParams = array(
                $_POST['recipient_email'],
                DATE_NOW,
                DATE_NOW
            );
            $userStatement = $gDb->queryPrepared($sql, $queryParams);
            $count = $userStatement->rowCount();
        }

        // show error if more than one user found
        if ($count > 1) {
            $gMessage->show($gL10n->get('SYS_LOSTPW_SEVERAL_EMAIL', array($_POST['recipient_email'])));
        // => EXIT
        } elseif ($count === 1) {
            // a valid username or email was found then send new password
            $user = new User($gDb, $gProfileFields, (int) $userStatement->fetchColumn());

            // create an activation id
            $passwordResetId = SecurityUtils::getRandomString(50);

            $user->setValue('usr_pw_reset_id', $passwordResetId);
            $user->setValue('usr_pw_reset_timestamp', DATETIME_NOW);

            $sysmail = new SystemMail($gDb);
            $sysmail->addRecipientsByUserId((int) $user->getValue('usr_id'));
            $sysmail->setVariable(1, SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_SYSTEM.'/password_reset.php', array('user_uuid' => $user->getValue('usr_uuid'), 'id' => $passwordResetId)));
            $sysmail->sendSystemMail('SYSMAIL_PASSWORD_RESET', $user);

            $user->saveChangesWithoutRights();
            $user->save(false);
        }

        // always show a positive feedback to prevent hackers to validate an email-address or username
        $gMessage->setForwardUrl(ADMIDIO_URL.'/adm_program/system/login.php');

        if (StringUtils::strValidCharacters($_POST['recipient_email'], 'email')) {
            $gMessage->show($gL10n->get('SYS_LOSTPW_SEND_EMAIL', array($_POST['recipient_email'])));
        // => EXIT
        } else {
            $gMessage->show($gL10n->get('SYS_LOSTPW_SEND_USERNAME', array($_POST['recipient_email'])));
            // => EXIT
        }
    } catch (AdmException $e) {
        if ($user instanceof User) {
            // initialize password reset columns
            $user->setValue('usr_pw_reset_id', '');
            $user->setValue('usr_pw_reset_timestamp', '');
            $user->saveChangesWithoutRights();
            $user->save(false);
        }

        $e->showHtml();
        // => EXIT
    }
} else {
    // HTML_PART

    $headline = $gL10n->get('SYS_PASSWORD_FORGOTTEN');

    // save url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = new HtmlPage('admidio-password-reset', $headline);

    $page->addHtml('<p class="lead">'.$gL10n->get('SYS_PASSWORD_FORGOTTEN_DESCRIPTION').'</p>');

    // show form
    $form = new HtmlForm('password_reset_form', ADMIDIO_URL.FOLDER_SYSTEM.'/password_reset.php', $page);
    $form->addInput(
        'recipient_email',
        $gL10n->get('SYS_USERNAME_OR_EMAIL'),
        '',
        array('maxLength' => 254, 'property' => HtmlForm::FIELD_REQUIRED)
    );

    // if captchas are enabled then visitors of the website must resolve this
    if (!$gValidLogin && $gSettingsManager->getBool('enable_mail_captcha')) {
        $form->addCaptcha('captcha_code');
    }

    $form->addSubmitButton('btn_send', $gL10n->get('SYS_SEND'), array('icon' => 'fa-envelope'));

    // add form to html page and show page
    $page->addHtml($form->show());
    $page->show();
}
