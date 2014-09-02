<?php
/******************************************************************************
 * Show form where user can request a new password and handle the request
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
require_once('common.php');

$headline = $gL10n->get('SYS_PASSWORD_FORGOTTEN').'?';

// save url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// 'systemmail' and 'request password' must be activated
if($gPreferences['enable_system_mails'] != 1 || $gPreferences['enable_password_recovery'] != 1)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
// muss natuerlich der Code ueberprueft werden
if (! empty($_POST['btnSend']) && !$gValidLogin && $gPreferences['enable_mail_captcha'] == 1 && !empty($_POST['captcha']))
{
    if ( !isset($_SESSION['captchacode']) || admStrToUpper($_SESSION['captchacode']) != admStrToUpper($_POST['captcha']) )
    {
		if($gPreferences['captcha_type']=='pic') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CODE_INVALID'));}
		else if($gPreferences['captcha_type']=='calc') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CALC_CODE_INVALID'));}
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
                  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. ', '. TBL_USERS. '
                  JOIN '. TBL_USER_DATA. ' as email
                    ON email.usd_usr_id = usr_id
                   AND email.usd_usf_id = '.$gProfileFields->getProperty('EMAIL', 'usf_id').'
                   AND email.usd_value  = \''.$_POST['recipient_email'].'\'
                 WHERE rol_cat_id = cat_id
                   AND rol_valid   = 1
                   AND (  cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
                       OR cat_org_id IS NULL )
                   AND rol_id     = mem_rol_id
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'
                   AND mem_usr_id = usr_id
                   AND usr_valid  = 1
    			   AND LENGTH(usr_login_name) > 0 
    			 GROUP BY usr_id';   
        $result = $gDb->query($sql);
    	$count  = $gDb->num_rows();
    
    	// show error if no user found or more than one user found
        if($count == 0)
        {
            $gMessage->show($gL10n->get('SYS_LOSTPW_EMAIL_ERROR', $_POST['recipient_email']));    
        }
    	elseif($count > 1)
    	{
            $gMessage->show($gL10n->get('SYS_LOSTPW_SEVERAL_EMAIL', $_POST['recipient_email']));    
    	}
    
        $row  = $gDb->fetch_array($result);
        $user = new User($gDb, $gProfileFields, $row['usr_id']);
    
    	// create and save new password and activation id
        $new_password  = generatePassword();
        $activation_id = generateActivationId($user->getValue('EMAIL'));
        $user->setValue('usr_new_password', $new_password);
        $user->setValue('usr_activation_code', $activation_id);
        
        $sysmail = new SystemMail($gDb);
        $sysmail->addRecipient($user->getValue('EMAIL'), $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'));
        $sysmail->setVariable(1, $new_password);
        $sysmail->setVariable(2, $g_root_path.'/adm_program/system/password_activation.php?usr_id='.$user->getValue('usr_id').'&aid='.$activation_id);
        $sysmail->sendSystemMail('SYSMAIL_ACTIVATION_LINK', $user);

        $user->save();
    
        $gMessage->setForwardUrl($g_root_path.'/adm_program/system/login.php');
        $gMessage->show($gL10n->get('SYS_LOSTPW_SEND',$_POST['recipient_email']));
    }
    catch(AdmException $e)
    {
        $e->showText();
    } 
}
else
{
    /*********************HTML_PART*******************************/

    // create html page object
    $page = new HtmlPage();
    
    // show headline of module
    $page->addHeadline($headline);

    // create module menu with back link
    $lostPasswordMenu = new HtmlNavbar('menu_lost_password');
    $lostPasswordMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
    $page->addHtml($lostPasswordMenu->show(false));

    // show form
    $form = new HtmlForm('lost_password_form', $g_root_path.'/adm_program/system/lost_password.php', $page);
    $form->addDescription($gL10n->get('SYS_PASSWORD_FORGOTTEN_DESCRIPTION'));
    $form->addTextInput('recipient_email', $gL10n->get('SYS_EMAIL'), null, 50, FIELD_MANDATORY);

    // if captchas are enabled then visitors of the website must resolve this
    if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1)
    {
        $form->addCaptcha('captcha', $gPreferences['captcha_type']);
    }

    $form->addSubmitButton('btn_send', $gL10n->get('SYS_SEND'), THEME_PATH.'/icons/email.png');

    // add form to html page and show page
    $page->addHtml($form->show(false));
    $page->show();
}

//************************* Funktionen/Unterprogramme ***********/

function generatePassword()
{
    // neues Passwort generieren
    $password = substr(md5(time()), 0, 8);
    return $password;
}

function generateActivationId($text)
{
    $aid = substr(md5(uniqid($text.time())),0,10);
    return $aid;
}
?>