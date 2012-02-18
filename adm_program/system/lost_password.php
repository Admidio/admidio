<?php
/******************************************************************************
 * Show form where user can request a new password and handle the request
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/
 
require_once('common.php');
require_once('classes/system_mail.php');

// Falls das Catpcha in den Orgaeinstellungen aktiviert wurde und die Ausgabe als
// Rechenaufgabe eingestellt wurde, muss die Klasse für nicht eigeloggte Benutzer geladen werden
if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1 && $gPreferences['captcha_type']=='calc')
{
	require_once('classes/captcha.php');
}

// save url to navigation stack
$_SESSION['navigation']->addUrl(CURRENT_URL);

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
    if($sysmail->sendSystemMail('SYSMAIL_ACTIVATION_LINK', $user) == true)
    {
        $user->save();

        $gMessage->setForwardUrl($g_root_path.'/adm_program/system/login.php');
        $gMessage->show($gL10n->get('SYS_LOSTPW_SEND',$_POST['recipient_email']));
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_LOSTPW_SEND_ERROR',$_POST['recipient_email'])); 
    }
}
else
{
    /*********************HTML_TEIL*******************************/

    // Html-Kopf ausgeben
    $gLayout['title'] = $gL10n->get('SYS_PASSWORD_FORGOTTEN').'?';

    require(SERVER_PATH. '/adm_program/system/overall_header.php');

    echo'
    <div class="formLayout" id="profile_form">
        <div class="formHead">'.$gLayout['title'].'?</div>
            <div class="formBody">
            <form name="password_form" action="'.$g_root_path.'/adm_program/system/lost_password.php" method="post">
                <ul class="formFieldList">
                    <li>
                        <div>
                          '.$gL10n->get('SYS_PASSWORD_FORGOTTEN_DESCRIPTION').'
                        </div>
                    </li>
                    <li>&nbsp;</li>
                    <li>
                        <dl>
                            <dt>
                                <label>'.$gL10n->get('SYS_EMAIL').':</label>
                            </dt>
                            <dd>
                                <input type="text" name="recipient_email" style="width: 300px;" maxlength="50" />
                            </dd>
                        </dl>
                    </li>';
                // Nicht eingeloggte User bekommen jetzt noch das Captcha praesentiert,
                // falls es in den Orgaeinstellungen aktiviert wurde...
                if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1)
                {
                    echo '
                    <li>&nbsp;</li>
                    <li>
                        <dl>
                            <dt>&nbsp;</dt>
                            <dd>
							';
					if($gPreferences['captcha_type']=='pic')
					{
						echo '<img src="'.$g_root_path.'/adm_program/system/classes/captcha.php?id='. time(). '&type=pic" alt="'.$gL10n->get('SYS_CAPTCHA').'" />';
						$captcha_label = $gL10n->get('SYS_CAPTCHA_CONFIRMATION_CODE');
						$captcha_description = 'SYS_CAPTCHA_DESCRIPTION';
					}
					else if($gPreferences['captcha_type']=='calc')
					{
						$captcha = new Captcha();
						$captcha->getCaptchaCalc($gL10n->get('SYS_CAPTCHA_CALC_PART1'),$gL10n->get('SYS_CAPTCHA_CALC_PART2'),$gL10n->get('SYS_CAPTCHA_CALC_PART3_THIRD'),$gL10n->get('SYS_CAPTCHA_CALC_PART3_HALF'),$gL10n->get('SYS_CAPTCHA_CALC_PART4'));
						$captcha_label = $gL10n->get('SYS_CAPTCHA_CALC');
						$captcha_description = 'SYS_CAPTCHA_CALC_DESCRIPTION';
					}
					echo '
                            </dd>
                        </dl>
                    </li>
                    <li>
                        <dl>
                            <dt><label for="captcha">'.$captcha_label.':</label></dt>
                            <dd>
                                <input type="text" id="captcha" name="captcha" style="width: 200px;" maxlength="8" value="" />
                                <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                                <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id='.$captcha_description.'&amp;inline=true"><img 
					                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id='.$captcha_description.'\',this)" onmouseout="ajax_hideTooltip()"
					                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="'.$gL10n->get('SYS_HELP').'" title="" /></a>
                            </dd>
                        </dl>
                    </li>';
                }
                echo'<hr />                                 
                <button id="btnSend" type="submit"><img src="'. THEME_PATH.'/icons/email.png" alt="'.$gL10n->get('SYS_SEND').'" />&nbsp;'.$gL10n->get('SYS_SEND_NEW_PW').'</button>
                </ul>
            </form>
            </div>
        </div>
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="$g_root_path/adm_program/system/back.php"><img 
                src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'"></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
            </span>
        </li>
    </ul>';

    require(SERVER_PATH. '/adm_program/system/overall_footer.php');
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