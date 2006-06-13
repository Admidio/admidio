<?php
/******************************************************************************
 * Import-Assistent fuer Benutzerdaten
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

require("../../system/common.php");
require("../../system/login_valid.php");

// nur Moderatoren duerfen User importieren
if(!isModerator())
{
    $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
    header($location);
    exit();
}

if($_GET['mode'] == 2)
{
    if(strlen($_FILES['userfile']['tmp_name']) == 0)
    {
        $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=feld&err_text=Datei";
        header($location);
        exit();
    }
     
    session_start();
    $_SESSION["file_lines"] = file($_FILES['userfile']['tmp_name']);
    $_SESSION["value_separator"] = ",";

    if(count($_SESSION["file_lines"]) == 0)
    {
        $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=file_not_exist";
        header($location);
        exit();
    }
}

//Beginn der Seite
echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Benutzer importieren</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">
    
    <script type=\"text/javascript\"><!--
        function submitForm()
        {
            document.forms.form_import.action = 'import_csv_config.php';            
            document.forms.form_import.submit();
        }
    --></script>

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    //Beginn des Inhaltes
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <p>&nbsp;</p>
        <form id=\"form_import\" action=\"import_function.php\" method=\"post\" enctype=\"multipart/form-data\">
            <div class=\"formHead\">Benutzer aus Datei importieren</div>
            <div class=\"formBody\">
                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Format:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">CVS
                    </div>
                </div>
                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Datei ausw&auml;hlen:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">
                        <input id=\"userfile\" name=\"userfile\" size=\"30\" type=\"file\">
                    </div>
                </div>
                <div style=\"margin-top: 15px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Rolle zuordnen:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">
                        <select size=\"1\" id=\"role\" name=\"role\">
                            <option value=\"\" selected=\"selected\">- Bitte w&auml;hlen -</option>";

                            $sql    = "SELECT rol_name FROM ". TBL_ROLES. "
                                        WHERE rol_org_shortname  = '$g_organization'
                                          AND rol_valid       = 1
                                        ORDER BY rol_name ";
                            $result = mysql_query($sql, $g_adm_con);
                            db_error($result);

                            while ($row = mysql_fetch_array($result))
                            {
                                echo "<option value=\"$row[0]\">$row[0]</option>";
                            }
                        echo "</select>&nbsp;
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=role_assign','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>
                <div style=\"margin-top: 15px;\">
                    Bereits existierende Benutzer&nbsp;
                    <select size=\"1\" id=\"user_import_mode\" name=\"user_import_mode\">
                        <option value=\"1\" selected>behalten</option>
                        <option value=\"2\">duplizieren</option>
                        <option value=\"3\">ersetzen</option>
                    </select>
                </div>

                <hr style=\"margin-top: 10px; margin-bottom: 10px;\" width=\"85%\" />

                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
                    <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                    &nbsp;Zur&uuml;ck</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button id=\"weiter\" type=\"submit\" value=\"weiter\" tabindex=\"2\">Weiter&nbsp;
                    <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Weiter\"></button>
                </div>
            </div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--
        document.getElementById('userfile').focus();
    --></script>";  
    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>