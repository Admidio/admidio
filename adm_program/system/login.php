<?php
/******************************************************************************
 * Loginseite
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require('common.php');
require('classes/table_roles.php');

// Url merken (wird in cookie_check wieder entfernt)
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Rollenobjekt fuer 'Webmaster' anlegen
$role_webmaster = new TableRoles($g_db, $g_l10n->get('SYS_WEBMASTER'));

// Html-Kopf ausgeben
$g_layout['title']  = $g_l10n->get('SYS_LOGIN');
$g_layout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#usr_login_name").focus();
        }); 
    //--></script>';
require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '
<form action="'.$g_root_path.'/adm_program/system/login_check.php" method="post">
<div class="formLayout" id="login_form" style="width: 300px; margin-top: 60px;">
    <div class="formHead">'.$g_l10n->get('SYS_LOGIN').'</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="usr_login_name">'.$g_l10n->get('SYS_USERNAME').':</label></dt>
                    <dd><input type="text" id="usr_login_name" name="usr_login_name" style="width: 120px;" maxlength="35" tabindex="1" /></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="usr_password">'.$g_l10n->get('SYS_PASSWORD').':</label></dt>
                    <dd><input type="password" id="usr_password" name="usr_password" style="width: 120px;" maxlength="20" tabindex="2" /></dd>
                </dl>
            </li>';
            
            if($g_preferences['enable_auto_login'] == 1)
            {
                echo '
                <li>
                    <dl>
                        <dt><label for="auto_login">'.$g_l10n->get('SYS_REMEMBER_ME').':</label></dt>
                        <dd><input type="checkbox" id="auto_login" name="auto_login" value="1" tabindex="3" /></dd>
                    </dl>
                </li>';
            }
        echo '</ul>
        
        <div class="formSubmit">
            <button name="login" type="submit" value="login" tabindex="4"><img src="'. THEME_PATH. '/icons/key.png" alt="'.$g_l10n->get('SYS_LOGIN').'" />&nbsp;'.$g_l10n->get('SYS_LOGIN').'</button>
        </div>';
        
        if($g_preferences['registration_mode'] > 0)
        {
            echo '<div class="smallFontSize" style="margin-top: 5px;">
                <a href="'.$g_root_path.'/adm_program/system/registration.php">'.$g_l10n->get('SYS_PHR_WANT_REGISTER').'</a>
            </div>';
        }

        // Link bei Loginproblemen
        if($g_preferences['enable_password_recovery'] == 1
        && $g_preferences['enable_system_mails'] == 1)
        {
            // neues Passwort zusenden
            $mail_link = $g_root_path.'/adm_program/system/lost_password.php';
        }
        elseif($g_preferences['enable_mail_module'] == 1 
        && $role_webmaster->getValue('rol_mail_this_role') == 3)
        {
            // Mailmodul aufrufen mit Webmaster als Ansprechpartner
            $mail_link = $g_root_path.'/adm_program/modules/mail/mail.php?rol_id='. $role_webmaster->getValue('rol_id'). '&amp;subject='.$g_l10n->get('SYS_LOGIN_PROBLEMS');
        }
        else
        {
            // direkte Mail an den Webmaster ueber einen externen Mailclient
            $mail_link = 'mailto:'. $g_preferences['email_administrator']. '?subject='.$g_l10n->get('SYS_LOGIN_PROBLEMS');
        }

        echo '<div class="smallFontSize" style="margin-top: 5px;">
            <a href="'.$mail_link.'">'.$g_l10n->get('SYS_PHR_PASSWORD_FORGOTTEN').'</a>
        </div>
        <div class="smallFontSize" style="margin-top: 20px;">
            Powered by <a href="http://www.admidio.org">Admidio</a>
        </div>
    </div>
</div>
</form>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>