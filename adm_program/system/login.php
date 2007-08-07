<?php
/******************************************************************************
 * Loginseite
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("common.php");
require("role_class.php");

// Url merken (wird in cookie_check wieder entfernt)
$_SESSION['navigation']->addUrl($g_current_url);

// Rollenobjekt fuer 'Webmaster' anlegen
$role_webmaster = new Role($g_db, 'Webmaster');

// Html-Kopf ausgeben
$g_layout['title']  = "Login";

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<br /><br /><br />
<form action=\"$g_root_path/adm_program/system/login_check.php\" name=\"Login\" method=\"post\">
<div class=\"formLayout\" id=\"login_form\">
    <div class=\"formHead\" style=\"width: 260px\">Login</div>
    <div class=\"formBody\" style=\"width: 260px\">
        <ul>
            <li>
                <dl>
                    <dt><label for=\"loginname\">Benutzername:</label></dt>
                    <dd><input type=\"text\" id=\"loginname\" name=\"loginname\" size=\"14\" maxlength=\"20\" /></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"password\">Passwort:</label></dt>
                    <dd><input type=\"password\" name=\"passwort\" size=\"14\" maxlength=\"20\" /></dd>
                </dl>
            </li>
        </ul>
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
    document.getElementById('loginname').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>