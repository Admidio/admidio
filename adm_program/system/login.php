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

$sql    = "SELECT rol_id, rol_mail_logout
             FROM ". TBL_ROLES. "
            WHERE rol_org_shortname = '$g_current_organization->shortname'
              AND rol_name          = 'Webmaster' ";
$result = mysql_query($sql, $g_adm_con);
db_error($result);
$webmaster_row = mysql_fetch_object($result);

echo "
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Login</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../adm_config/header.php");
echo "</head>";

require("../../adm_config/body_top.php");
    echo "<div align=\"center\">
        <br /><br /><br />
        <form action=\"login_check.php\" name=\"Login\" method=\"post\">
            <div class=\"formHead\" style=\"width: 260px\">". strspace("Login"). "</div>
            <div class=\"formBody\" style=\"width: 260px\">
                <div style=\"margin-top: 7px;\">
                    <div style=\"text-align: right; width: 110px; float: left;\">Benutzername:</div>
                    <div style=\"text-align: left; margin-left: 120px;\">
                        <input type=\"text\" id=\"loginname\" name=\"loginname\" size=\"14\" maxlength=\"20\" />
                    </div>
                </div>
                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: right; width: 110px; float: left;\">Passwort:</div>
                    <div style=\"text-align: left; margin-left: 120px;\">
                        <input type=\"password\" name=\"passwort\" size=\"14\" maxlength=\"20\" />
                    </div>
                </div>
                <div style=\"margin-top: 15px; margin-bottom: 15px;\">
                    <button name=\"login\" type=\"submit\" value=\"login\">
                    <img src=\"$g_root_path/adm_program/images/key.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Login\">
                    &nbsp;Login</button>
                </div>";
                if($g_preferences['registration_mode'] > 0)
                {
                    echo "<div style=\"font-size: 8pt; margin-top: 5px;\">
                        <a href=\"registration.php\">Ich m&ouml;chte mich registrieren!</a>
                    </div>";
                }
                // E-Mail intern oder extern verschicken
                if($g_preferences['enable_mail_module'] != 1 
                || $webmaster_row->rol_mail_logout != 1 )
                {
                    $mail_link = "mailto:". $g_preferences['email_administrator']. "?subject=Loginprobleme";
                }
                else
                {
                    $mail_link = "$g_root_path/adm_program/modules/mail/mail.php?rol_id=$webmaster_row->rol_id&subject=Loginprobleme";
                }
                echo "<div style=\"font-size: 8pt; margin-top: 5px;\">
                    <a href=\"$mail_link\">Ich habe mein Passwort vergessen!</a>
                </div>
                <div style=\"font-size: 8pt; margin-top: 20px;\">
                    Powered by <a href=\"http://www.admidio.org\" target=\"_blank\">Admidio</a>
                </div>
            </div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--
        document.getElementById('loginname').focus();
    --></script>";

    require("../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>