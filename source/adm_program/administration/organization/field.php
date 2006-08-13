<?php
/******************************************************************************
 * Profilfelder anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * usf_id: ID des Feldes, das bearbeitet werden soll
 * url :   URL von der die aufrufende Seite aufgerufen wurde
 *         (muss uebergeben werden, damit der Zurueck-Button funktioniert)
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
 ****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");

// nur Moderatoren duerfen Profilfelder erfassen & verwalten
if(!isModerator())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["usf_id"]) && is_numeric($_GET["usf_id"]) == false)
{
    $g_message->show("invalid");
}

// wenn URL uebergeben wurde zu dieser gehen, ansonsten zurueck
if(array_key_exists('url', $_GET))
{
    $url = $_GET['url'];
}
else
{
    $url = urlencode(getHttpReferer());
}

$field_type        = "";
$field_name        = "";
$field_description = "";
$field_locked      = 0;

// Wenn eine Feld-ID uebergeben wurde, soll das Feld geaendert werden
// -> Felder mit Daten des Feldes vorbelegen

if ($_GET["usf_id"] != 0)
{
    $sql    = "SELECT * FROM ". TBL_USER_FIELDS. " WHERE usf_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['usf_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (mysql_num_rows($result) > 0)
    {
        $row_usf = mysql_fetch_object($result);

        $field_type        = $row_usf->usf_type;
        $field_name        = $row_usf->usf_name;
        $field_description = $row_usf->usf_description;
        $field_locked      = $row_usf->usf_locked;
    }
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Rolle</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <form action=\"field_function.php?usf_id=". $_GET['usf_id']. "&amp;mode=1&amp;url=$url\" method=\"post\" id=\"edit_field\">
            <div class=\"formHead\" style=\"width: 400px\">";
                if($_GET['usf_id'] > 0)
                {
                    echo strspace("Feld ändern", 2);
                }
                else
                {
                    echo strspace("Feld anlegen", 2);
                }
            echo "</div>
            <div class=\"formBody\" style=\"width: 400px\">
                <div>
                    <div style=\"text-align: right; width: 28%; float: left;\">Name:</div>
                    <div style=\"text-align: left; margin-left: 29%;\">
                        <input type=\"text\" id=\"name\" name=\"name\" size=\"20\" maxlength=\"13\" value=\"". htmlspecialchars($field_name, ENT_QUOTES). "\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 28%; float: left;\">Beschreibung:</div>
                    <div style=\"text-align: left; margin-left: 29%;\">
                        <input type=\"text\" name=\"description\" size=\"38\" maxlength=\"255\" value=\"". htmlspecialchars($field_description, ENT_QUOTES). "\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 28%; float: left;\">Datentyp:</div>
                    <div style=\"text-align: left; margin-left: 29%;\">
                        <select size=\"1\" name=\"typ\">
                            <option value=\"\""; 
                                if(strlen($field_type) == 0) 
                                {
                                    echo " selected=\"selected\"";
                                }
                                echo ">&nbsp;</option>\n
                            <option value=\"TEXT\"";     
                                if($field_type == "TEXT") 
                                {
                                    echo " selected=\"selected\""; 
                                }
                                echo ">Text (30 Zeichen)</option>\n
                            <option value=\"TEXT_BIG\""; 
                                if($field_type == "TEXT_BIG") 
                                {
                                    echo " selected=\"selected\""; 
                                }
                                echo ">Text (255 Zeichen)</option>\n
                            <option value=\"NUMERIC\"";  
                                if($field_type == "NUMERIC") 
                                {
                                    echo " selected=\"selected\""; 
                                }
                                echo ">Zahl</option>\n
                            <option value=\"CHECKBOX\""; 
                                if($field_type == "CHECKBOX") 
                                {
                                    echo " selected=\"selected\""; 
                                }
                                echo ">Ja / Nein</option>\n
                        </select>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 28%; float: left;\">
                        <img src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Feld nur f&uuml;r Moderatoren sichtbar\">
                    </div>
                    <div style=\"text-align: left; margin-left: 29%;\">
                        <input type=\"checkbox\" id=\"locked\" name=\"locked\" ";
                        if($field_locked == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                        <label for=\"locked\">Feld nur f&uuml;r Moderatoren sichtbar&nbsp;</label>
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=field_locked','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>

                <hr width=\"85%\" />

                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
                    <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                    &nbsp;Zur&uuml;ck</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                    <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                    &nbsp;Speichern</button>
                </div>";
            echo "</div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--
        document.getElementById('name').focus();
    --></script>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>