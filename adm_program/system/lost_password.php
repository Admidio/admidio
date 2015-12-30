<?php
/**
 ***********************************************************************************************
 * Show form where user can request a new password and handle the request
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once('common.php');

$headline = $gL10n->get('SYS_PASSWORD_FORGOTTEN');

// save url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// 'systemmail' and 'request password' must be activated
if($gPreferences['enable_system_mails'] != 1 || $gPreferences['enable_password_recovery'] != 1)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
// muss natuerlich der Code ueberprueft werden
if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1 && !empty($_POST['captcha']))
{
    if (!isset($_SESSION['captchacode']) || admStrToUpper($_SESSION['captchacode']) !== admStrToUpper($_POST['captcha']))
    {
        if($gPreferences['captcha_type'] === 'pic')
        {
            $gMessage->show($gL10n->get('SYS_CAPTCHA_CODE_INVALID'));
        }
        elseif($gPreferences['captcha_type'] === 'calc')
        {
            $gMessage->show($gL10n->get('SYS_CAPTCHA_CALC_CODE_INVALID'));
        }
    }
}
if($gValidLogin)
{
    $gMessage->setForwardUrl($g_root_path.'/adm_program/', 2000);
    $gMessage->show($gL10n->get('SYS_LOSTPW_AREADY_LOGGED_ID'));
}

if(!empty($_POST['recipient_email']) && !empty($_POST['captcha']))
{
    try
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
            INNER JOIN '.TBL_USER_DATA.' as email
                    ON email.usd_usr_id = usr_id
                   AND email.usd_usf_id = '.$gProfileFields->getProperty('EMAIL', 'usf_id').'
                   AND email.usd_value  = \''.$_POST['recipient_email'].'\'
                 WHERE LENGTH(usr_login_name) > 0
                   AND rol_valid  = 1
                   AND usr_valid  = 1
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'
                   AND (  cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
                       OR cat_org_id IS NULL )
                 GROUP BY usr_id';
        $userStatement = $gDb->query($sql);
        $count = $userStatement->rowCount();

        // show error if no user found or more than one user found
        if($count === 0)
        {
            $gMessage->show($gL10n->get('SYS_LOSTPW_EMAIL_ERROR', $_POST['recipient_email']));
        }
        elseif($count > 1)
        {
            $gMessage->show($gL10n->get('SYS_LOSTPW_SEVERAL_EMAIL', $_POST['recipient_email']));
        }

        $row  = $userStatement->fetch();
        $user = new User($gDb, $gProfileFields, $row['usr_id']);

        // create and save new password and activation id
        $newPassword  = PasswordHashing::genRandomPassword(8);
        $activationId = PasswordHashing::genRandomPassword(10);

        $user->setPassword($newPassword, true);
        $user->setValue('usr_activation_code', $activationId);

        $sysmail = new SystemMail($gDb);
        $sysmail->addRecipient($user->getValue('EMAIL'), $user->getValue('FIRST_NAME', 'database').' '.$user->getValue('LAST_NAME', 'database'));
        $sysmail->setVariable(1, $newPassword);
        $sysmail->setVariable(2, $g_root_path.'/adm_program/system/password_activation.php?usr_id='.$user->getValue('usr_id').'&aid='.$activationId);
        $sysmail->sendSystemMail('SYSMAIL_ACTIVATION_LINK', $user);

        $user->saveChangesWithoutRights();
        $user->save();

        $gMessage->setForwardUrl($g_root_path.'/adm_program/system/login.php');
        $gMessage->show($gL10n->get('SYS_LOSTPW_SEND', $_POST['recipient_email']));
    }
    catch(AdmException $e)
    {
        $e->showHtml();
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
    $form = new HtmlForm('lost_password_form', $g_root_path.'/adm_program/system/lost_password.php', $page);
    $form->addInput('recipient_email', $gL10n->get('SYS_EMAIL'), null, array('maxLength' => 50, 'property' => FIELD_REQUIRED));

    // if captchas are enabled then visitors of the website must resolve this
    if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1)
    {
        $form->addCaptcha('captcha', $gPreferences['captcha_type']);
    }

    $form->addSubmitButton('btn_send', $gL10n->get('SYS_SEND'), array('icon' => THEME_PATH.'/icons/email.png'));

    // add form to html page and show page
    $page->addHtml($form->show(false));
    $page->show();
}
