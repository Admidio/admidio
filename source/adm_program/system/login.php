<?php
/******************************************************************************
 * Login page
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');
require_once('classes/form_elements.php');
require_once('classes/table_roles.php');

// Url merken (wird in cookie_check wieder entfernt)
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Rollenobjekt fuer 'Webmaster' anlegen
$roleWebmaster = new TableRoles($gDb, $gL10n->get('SYS_WEBMASTER'));

// Html-Kopf ausgeben
$gLayout['title']  = $gL10n->get('SYS_LOGIN');
$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#usr_login_name").focus();
        }); 
    //--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<form action="'.$g_root_path.'/adm_program/system/login_check.php" method="post">
<div class="formLayout" id="login_form" style="width: 300px; margin-top: 60px;">
    <div class="formHead">'.$gL10n->get('SYS_LOGIN').'</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="usr_login_name">'.$gL10n->get('SYS_USERNAME').':</label></dt>
                    <dd><input type="text" id="usr_login_name" name="usr_login_name" style="width: 120px;" maxlength="35" /></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="usr_password">'.$gL10n->get('SYS_PASSWORD').':</label></dt>
                    <dd><input type="password" id="usr_password" name="usr_password" style="width: 120px;" maxlength="20" /></dd>
                </dl>
            </li>';

			// show selectbox with all organizations of database
			if($gPreferences['system_organization_select'] == 1)
			{
				echo '<li>
					<dl>
						<dt><label for="org_id">'.$gL10n->get('SYS_ORGANIZATION').':</label></dt>
						<dd>'.FormElements::generateOrganizationSelectBox($g_organization, 'org_id').'</dd>
					</dl>
				</li>';
			}
            
            if($gPreferences['enable_auto_login'] == 1)
            {
                echo '
                <li>
                    <dl>
                        <dt><label for="auto_login">'.$gL10n->get('SYS_REMEMBER_ME').':</label></dt>
                        <dd><input type="checkbox" id="auto_login" name="auto_login" value="1" /></dd>
                    </dl>
                </li>';
            }
        echo '</ul>
        
        <div class="formSubmit">
            <button id="btnLogin" type="submit"><img src="'. THEME_PATH. '/icons/key.png" alt="'.$gL10n->get('SYS_LOGIN').'" />&nbsp;'.$gL10n->get('SYS_LOGIN').'</button>
        </div>';
        
        if($gPreferences['registration_mode'] > 0)
        {
            echo '<div class="smallFontSize" style="margin-top: 5px;">
                <a href="'.$g_root_path.'/adm_program/system/registration.php">'.$gL10n->get('SYS_WANT_REGISTER').'</a>
            </div>';
        }

        // Link bei Loginproblemen
        if($gPreferences['enable_password_recovery'] == 1
        && $gPreferences['enable_system_mails'] == 1)
        {
            // neues Passwort zusenden
            $mail_link = $g_root_path.'/adm_program/system/lost_password.php';
        }
        elseif($gPreferences['enable_mail_module'] == 1 
        && $roleWebmaster->getValue('rol_mail_this_role') == 3)
        {
            // Mailmodul aufrufen mit Webmaster als Ansprechpartner
            $mail_link = $g_root_path.'/adm_program/modules/mail/mail.php?rol_id='. $roleWebmaster->getValue('rol_id'). '&amp;subject='.$gL10n->get('SYS_LOGIN_PROBLEMS');
        }
        else
        {
            // direkte Mail an den Webmaster ueber einen externen Mailclient
            $mail_link = 'mailto:'. $gPreferences['email_administrator']. '?subject='.$gL10n->get('SYS_LOGIN_PROBLEMS');
        }

        echo '<div class="smallFontSize" style="margin-top: 5px;">
            <a href="'.$mail_link.'">'.$gL10n->get('SYS_FORGOT_MY_PASSWORD').'</a>
        </div>
        <div class="smallFontSize" style="margin-top: 20px;">
            Powered by <a href="http://www.admidio.org">Admidio</a>
        </div>
    </div>
</div>
</form>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>