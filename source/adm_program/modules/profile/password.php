<?php
/******************************************************************************
 * Passwort neu vergeben
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id     -   Passwort der übergebenen user_id aendern
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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
 
require("../../system/common.php");
require("../../system/login_valid.php");
 
// nur Webmaster d&uuml;rfen fremde Passwoerter aendern
if(!hasRole("Webmaster") && $g_current_user->id != $_GET['user_id'])
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]) && is_numeric($_GET["user_id"]) == false)
{
    $g_message->show("invalid");
}

echo "
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
    <title>Passwort &auml;ndern</title>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\">
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->
</head>

<body>
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <form action=\"password_save.php?user_id=". $_GET['user_id']. "\" method=\"post\" name=\"Anmeldung\">
            <div class=\"formHead\" style=\"width: 300px\">". strspace("Passwort &auml;ndern"). "</div>
            <div class=\"formBody\" style=\"width: 300px\">
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 150px; float: left;\">Altes Passwort:</div>
                    <div style=\"text-align: left; margin-left: 160px;\">
                        <input type=\"password\" id=\"old_password\" name=\"old_password\" size=\"10\" maxlength=\"20\" />
                    </div>
                </div>

                <hr width=\"80%\" />

                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 150px; float: left;\">Neues Passwort:</div>
                    <div style=\"text-align: left; margin-left: 160px;\">
                        <input type=\"password\" name=\"new_password\" size=\"10\" maxlength=\"20\" />
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 150px; float: left;\">Wiederholen:</div>
                    <div style=\"text-align: left; margin-left: 160px;\">
                        <input type=\"password\" name=\"new_password2\" size=\"10\" maxlength=\"20\" />
                    </div>
                </div>

                <hr width=\"80%\" />

                <div style=\"margin-top: 6px;\">
                    <button name=\"schliessen\" type=\"button\" value=\"schliessen\" onclick=\"window.close()\">
                        <img src=\"$g_root_path/adm_program/images/door_in.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" 
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Schlie&szlig;en\">
                        &nbsp;Schlie&szlig;en</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                        <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" 
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                        &nbsp;Speichern</button>
                </div>
            </div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--
        document.getElementById('old_password').focus();
    --></script>
</body>
</html>";
?>