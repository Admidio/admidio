<?php
/******************************************************************************
 * Registrieren
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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

require("common.php");

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Registrierung</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../adm_config/header.php");
echo "</head>";

require("../../adm_config/body_top.php");
    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\"><br>
        <form action=\"registration_save.php\" method=\"post\" name=\"Anmeldung\">
            <div class=\"formHead\" style=\"width: 360px\">". strspace("Registrieren"). "</div>
            <div class=\"formBody\" style=\"width: 360px\">
                <div>
                    <div style=\"text-align: right; width: 130; float: left;\">Nachname:</div>
                    <div style=\"text-align: left; margin-left: 140px;\">
                        <input type=\"text\" id=\"last_name\" name=\"nachname\" size=\"20\" maxlength=\"30\" />
                    </div>
                </div>
                <div style=\"margin-top: 8px;\">
                    <div style=\"text-align: right; width: 130; float: left;\">Vorname:</div>
                    <div style=\"text-align: left; margin-left: 140px;\">
                        <input type=\"text\" name=\"vorname\" size=\"20\" maxlength=\"30\" />
                    </div>
                </div>
                <div style=\"margin-top: 8px;\">
                    <div style=\"text-align: right; width: 130; float: left;\">E-Mail:</div>
                    <div style=\"text-align: left; margin-left: 140px;\">
                        <input type=\"text\" name=\"email\" size=\"24\" maxlength=\"50\" />&nbsp;
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=email','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>

                <hr width=\"80%\" />

                <div style=\"margin-top: 8px;\">
                    <div style=\"text-align: right; width: 130; float: left;\">Benutzername:</div>
                    <div style=\"text-align: left; margin-left: 140px;\">
                        <input type=\"text\" name=\"benutzername\" size=\"20\" maxlength=\"20\" />&nbsp;
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=nickname','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>
                <div style=\"margin-top: 8px;\">
                    <div style=\"text-align: right; width: 130; float: left;\">Passwort:</div>
                    <div style=\"text-align: left; margin-left: 140px;\">
                        <input type=\"password\" name=\"passwort\" size=\"10\" maxlength=\"20\" />&nbsp;
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=password','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>
                <div style=\"margin-top: 8px;\">
                    <div style=\"text-align: right; width: 130; float: left;\">Passwort (Wdh):</div>
                    <div style=\"text-align: left; margin-left: 140px;\">
                        <input type=\"password\" name=\"passwort2\" size=\"10\" maxlength=\"20\" />
                    </div>
                </div>

                <hr width=\"80%\" />

                <div style=\"margin-top: 8px;\">
                    <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
                        <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                        &nbsp;Zur&uuml;ck</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button name=\"abschicken\" type=\"submit\" value=\"abschicken\">
                        <img src=\"$g_root_path/adm_program/images/mail.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Abschicken\">
                        &nbsp;Abschicken</button>
                </div>
            </div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--
        document.getElementById('last_name').focus();
    --></script>";

    require("../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>