<?php
/******************************************************************************
 * Loginseite
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require("common.php");
require("role_class.php");

// Url merken (wird in cookie_check wieder entfernt)
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Rollenobjekt fuer 'Webmaster' anlegen
$role_webmaster = new Role($g_db, 'Webmaster');

// Html-Kopf ausgeben
$g_layout['title']  = "Login";

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<br /><br /><br />
<form action=\"$g_root_path/adm_program/system/login_check.php\" name=\"Login\" method=\"get\">
<div class=\"formLayout\" id=\"login_form\" style=\"width: 260px\">
    <div class=\"formHead\">Login</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt><label for=\"usr_login_name\">Benutzername:</label></dt>
                    <dd><input type=\"text\" id=\"usr_login_name\" name=\"usr_login_name\" size=\"14\" maxlength=\"35\" /></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"usr_password\">Passwort:</label></dt>
                    <dd><input type=\"password\" id=\"usr_password\" name=\"usr_password\" size=\"14\" maxlength=\"20\" /></dd>
                </dl>
            </li>";
            
            if($g_preferences['enable_auto_login'] == 1)
            {
                echo "
                <li>
                    <dl>
                        <dt><input type=\"checkbox\" id=\"auto_login\" name=\"auto_login\" value=\"1\"> 
                            <label for=\"auto_login\">dauerhaft angemeldet bleiben</label></dt>
                        <dd>&nbsp;</dd>
                    </dl>
                </li>";
            }
        echo "</ul>
        
        <div class=\"formSubmit\">
            <button name=\"login\" type=\"submit\" value=\"login\">
            <img src=\"$g_root_path/adm_program/images/key.png\" alt=\"Login\">
            &nbsp;Login</button>
        </div>";
        
        if($g_preferences['registration_mode'] > 0)
        {
            echo "<div class=\"smallFontSize\" style=\"margin-top: 5px;\">
                <a href=\"$g_root_path/adm_program/system/registration.php\">Ich m&ouml;chte mich registrieren!</a>
            </div>";
        }
        
        // E-Mail intern oder extern verschicken
        if($g_preferences['enable_mail_module'] != 1 
        || $role_webmaster->getValue("rol_mail_logout") != 1 )
        {
            $mail_link = "mailto:". $g_preferences['email_administrator']. "?subject=Loginprobleme";
        }
        else
        {
            $mail_link = "$g_root_path/adm_program/modules/mail/mail.php?rol_id=". $role_webmaster->getValue("rol_id"). "&subject=Loginprobleme";
        }
        echo "<div class=\"smallFontSize\" style=\"margin-top: 5px;\">
            <a href=\"$mail_link\">Ich habe mein Passwort vergessen!</a>
        </div>
        <div class=\"smallFontSize\" style=\"margin-top: 20px;\">
            Powered by <a href=\"http://www.admidio.org\" target=\"_blank\">Admidio</a>
        </div>
    </div>
</div>
</form>

<script type=\"text/javascript\"><!--
    document.getElementById('usr_login_name').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>