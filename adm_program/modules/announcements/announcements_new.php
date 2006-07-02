<?php
/******************************************************************************
 * Ankuendigungen anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * ann_id         - ID der Ankuendigung, die bearbeitet werden soll
 * headline      - Ueberschrift, die ueber den Ankuendigungen steht
 *                 (Default) Ankuendigungen
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

if(!editAnnouncements())
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
    header($location);
    exit();
}

if(!array_key_exists("headline", $_GET))
{
    $_GET["headline"] = "Ank&uuml;ndigungen";
}

$global        = 0;
$headline      = "";
$description   = "";

// Wenn eine Ankuendigungs-ID uebergeben wurde, soll die Ankuendigung geaendert werden
// -> Felder mit Daten der Ankuendigung vorbelegen

if ($_GET["ann_id"] != 0)
{
    $sql    = "SELECT * FROM ". TBL_ANNOUNCEMENTS. " 
                WHERE ann_id = {0}
                  AND (  ann_org_shortname = '$g_organization'
                      OR ann_global = 1) ";
    $sql    = prepareSQL($sql, array($_GET['ann_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (mysql_num_rows($result) > 0)
    {
        $row_ba = mysql_fetch_object($result);

        $global        = $row_ba->ann_global;
        $headline      = $row_ba->ann_headline;
        $description   = $row_ba->ann_description;
    }
    else
    {
        $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
        header($location);
        exit();
    }
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - ". $_GET["headline"]. "</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <form action=\"announcements_function.php?ann_id=". $_GET["ann_id"]. "&amp;headline=". $_GET['headline']. "&amp;mode=";
            if($_GET["ann_id"] > 0)
            {
                echo "3";
            }
            else
            {
                echo "1";
            }
            echo "\" method=\"post\" name=\"AnkuendigungAnlegen\">

            <div class=\"formHead\">";
                if($_GET["ann_id"] > 0)
                {
                    $formHeadline = $_GET["headline"]. " &auml;ndern";
                }
                else
                {
                    $formHeadline = $_GET["headline"]. " anlegen";
                }
                echo strspace($formHeadline, 2);
            echo "</div>
            <div class=\"formBody\">
                <div>
                    <div style=\"text-align: right; width: 25%; float: left;\">&Uuml;berschrift:</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <input type=\"text\" id=\"headline\" name=\"ueberschrift\" tabindex=\"1\" size=\"53\" maxlength=\"100\" value=\"". htmlspecialchars($headline, ENT_QUOTES). "\">
                    </div>
                </div>

                <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 25%; float: left;\">Beschreibung:";
                    if($g_preferences['enable_bbcode'] == 1)
                    {
                      echo "<br><br>
                      <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=400,left=310,top=200,scrollbars=yes')\" tabindex=\"6\">Text formatieren</a>";
                    }
                    echo "</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <textarea  name=\"beschreibung\" tabindex=\"2\" rows=\"10\" cols=\"40\">". htmlspecialchars($description, ENT_QUOTES). "</textarea>
                    </div>
                </div>";

                // bei mehr als einer Organisation, Checkbox anzeigen, ob Ankuendigung bei anderen angezeigt werden soll
                $sql = "SELECT COUNT(1) FROM ". TBL_ORGANIZATIONS. "
                         WHERE org_org_id_parent IS NOT NULL ";
                $result = mysql_query($sql, $g_adm_con);
                db_error($result);
                $row = mysql_fetch_array($result);

                if($row[0] > 0)
                {
                    echo "
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 25%; float: left;\">&nbsp;</div>
                        <div style=\"text-align: left; margin-left: 27%;\">
                            <input type=\"checkbox\" id=\"global\" name=\"global\" tabindex=\"3\" ";
                            if($global == 1)
                            {
                                echo " checked=\"checked\" ";
                            }
                            echo " value=\"1\" />
                            <label for=\"global\">". $_GET["headline"]. " f&uuml;r mehrere Organisationen sichtbar</label>&nbsp;
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=termin_global','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\">
                        </div>
                    </div>";
                }

                echo "<hr width=\"85%\" />

                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\" tabindex=\"5\">
                        <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" 
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                        &nbsp;Zur&uuml;ck</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button name=\"speichern\" type=\"submit\" value=\"speichern\" tabindex=\"4\">
                        <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" 
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                        &nbsp;Speichern</button>
                </div>";
                if($row_ba->ann_usr_id_change > 0)
                {
                    // Angabe &uuml;ber die letzten Aenderungen
                    $sql    = "SELECT usr_first_name, usr_last_name
                                 FROM ". TBL_USERS. "
                                WHERE usr_id = $row_ba->ann_usr_id_change ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result);
                    $row = mysql_fetch_array($result);

                    echo "<div style=\"margin-top: 6px;\">
                        <span style=\"font-size: 10pt\">
                            Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $row_ba->ann_last_change).
                            " durch $row[0] $row[1]
                        </span>
                    </div>";
                }
            echo "</div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--
        document.getElementById('headline').focus();
    --></script>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>