<?php
/**
 ***********************************************************************************************
 * Show form where user can request a new password and handle the request
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');

$headline = $gL10n->get('SYS_PASSWORD_FORGOTTEN');

// save url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// "systemmail" and "request password" must be activated
if(!$gSettingsManager->getBool('enable_system_mails') || !$gSettingsManager->getBool('enable_password_recovery'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

if($gValidLogin)
{
    $gMessage->setForwardUrl(ADMIDIO_URL.'/adm_program/', 2000);
    $gMessage->show($gL10n->get('SYS_LOSTPW_AREADY_LOGGED_ID'));
    // => EXIT
}

if(!empty($_POST['recipient_email']))
{
    try
    {
        // if user is not logged in and captcha is activated then check captcha
        if (!$gValidLogin && $gSettingsManager->getBool('enable_mail_captcha'))
        {
            FormValidation::checkCaptcha($_POST['captcha_code']);
        }

        if(strValidCharacters($_POST['recipient_email'], 'email'))
        {
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
                       AND rol_valid  = 1
                       AND usr_valid  = 1
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW
                       AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                           OR cat_org_id IS NULL )
                  GROUP BY usr_id';
            $queryParams = array(
                $gProfileFields->getProperty('EMAIL', 'usf_id'),
                $_POST['recipient_email'],
                DATE_NOW,
                DATE_NOW,
                $gCurrentOrganization->getValue('org_id')
            );
            $userStatement = $gDb->queryPrepared($sql, $queryParams);
            $count = $userStatement->rowCount();
        }
        else
        {
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
                       AND rol_valid  = 1
                       AND usr_valid  = 1
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW
                       AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                           OR cat_org_id IS NULL )
                  GROUP BY usr_id';
            $queryParams = array(
                $_POST['recipient_email'],
                DATE_NOW,
                DATE_NOW,
                $gCurrentOrganization->getValue('org_id')
            );
            $userStatement = $gDb->queryPrepared($sql, $queryParams);
            $count = $userStatement->rowCount();
        }

        // show error if more than one user found
        if($count > 1)
        {
            $gMessage->show($gL10n->get('SYS_LOSTPW_SEVERAL_EMAIL', array($_POST['recipient_email'])));
            // => EXIT
        }
        elseif($count === 1)
        {
            // a valid username or email was found then send new password
            $user = new User($gDb, $gProfileFields, (int) $userStatement->fetchColumn());

            // create and save new password and activation id
            $newPassword  = PasswordUtils::genRandomPassword(PASSWORD_GEN_LENGTH, PASSWORD_GEN_CHARS);
            $activationId = PasswordUtils::genRandomPassword(10);

            $user->setPassword($newPassword, true);
            $user->setValue('usr_activation_code', $activationId);

            $sysmail = new SystemMail($gDb);
            $sysmail->addRecipient($user->getValue('EMAIL'), $user->getValue('FIRST_NAME', 'database').' '.$user->getValue('LAST_NAME', 'database'));
            $sysmail->setVariable(1, $newPassword);
            $sysmail->setVariable(2, safeUrl(ADMIDIO_URL.'/adm_program/system/password_activation.php', array('usr_id' => $user->getValue('usr_id'), 'aid' => $activationId)));
            $sysmail->sendSystemMail('SYSMAIL_ACTIVATION_LINK', $user);

            $user->saveChangesWithoutRights();
            $user->save();
        }

        // always show a positive feedback to prevent hackers to validate an email-address or username
        $gMessage->setForwardUrl(ADMIDIO_URL.'/adm_program/system/login.php');

        if(strValidCharacters($_POST['recipient_email'], 'email'))
        {
            $gMessage->show($gL10n->get('SYS_LOSTPW_SEND_EMAIL', array($_POST['recipient_email'])));
            // => EXIT
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_LOSTPW_SEND_USERNAME', array($_POST['recipient_email'])));
            // => EXIT
        }
    }
    catch(AdmException $e)
    {
        $e->showHtml();
        // => EXIT
    }
}
else
{
    /*********************HTML_PART*******************************/

    // create html page object
    $page = new HtmlPage($headline);

    // add back link to module menu
    $lostPasswordMenu = $page->getMenu();
    $lostPasswordMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

    $page->addHtml('<p class="lead">'.$gL10n->get('SYS_PASSWORD_FORGOTTEN_DESCRIPTION').'</p>');

    // show form
    $form = new HtmlForm('lost_password_form', ADMIDIO_URL.'/adm_program/system/lost_password.php', $page);
    $form->addInput(
        'recipient_email', $gL10n->get('SYS_USERNAME_OR_EMAIL'), '',
        array('maxLength' => 254, 'property' => HtmlForm::FIELD_REQUIRED)
    );

    // if captchas are enabled then visitors of the website must resolve this
    if (!$gValidLogin && $gSettingsManager->getBool('enable_mail_captcha'))
    {
        $form->addCaptcha('captcha_code');
    }

    $form->addSubmitButton('btn_send', $gL10n->get('SYS_SEND'), array('icon' => THEME_URL.'/icons/email.png'));

    // add form to html page and show page
    $page->addHtml($form->show());
    $page->show();
}
