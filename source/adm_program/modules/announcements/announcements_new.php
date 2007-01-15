<?php
/******************************************************************************
 * Ankuendigungen anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * ann_id        - ID der Ankuendigung, die bearbeitet werden soll
 * headline      - Ueberschrift, die ueber den Ankuendigungen steht
 *                 (Default) Ankuendigungen
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

require("../../system/common.php");
require("../../system/login_valid.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_announcements_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

if(!editAnnouncements())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["ann_id"]))
{
    if(is_numeric($_GET["ann_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $ann_id = $_GET["ann_id"];
}
else
{
    $ann_id = 0;
}

if(array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "Ank&uuml;ndigungen";
}

$_SESSION['navigation']->addUrl($g_current_url);

if(isset($_SESSION['announcements_request']))
{
    $form_values = $_SESSION['announcements_request'];
    unset($_SESSION['announcements_request']);
}
else
{
    $form_values['headline']    = "";
    $form_values['description'] = "";
    $form_values['global']      = 0;

    // Wenn eine Ankuendigungs-ID uebergeben wurde, soll die Ankuendigung geaendert werden
    // -> Felder mit Daten der Ankuendigung vorbelegen

    if ($ann_id > 0)
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

            $form_values['headline']    = $row_ba->ann_headline;
            $form_values['description'] = $row_ba->ann_description;
            $form_values['global']      = $row_ba->ann_global;
        }
        else
        {
            $g_message->show("norights");
        }
    }
}

echo "
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org - Version: ". ADMIDIO_VERSION. " -->\n
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
        <form name=\"form\" action=\"announcements_function.php?ann_id=$ann_id&amp;headline=". $_GET['headline']. "&amp;mode=";
            if($ann_id > 0)
            {
                echo "3";
            }
            else
            {
                echo "1";
            }
            echo "\" method=\"post\" name=\"AnkuendigungAnlegen\">

            <div class=\"formHead\">";
                if($ann_id > 0)
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
                        <input type=\"text\" name=\"headline\" style=\"width: 350px;\" tabindex=\"1\" maxlength=\"100\" value=\"". htmlspecialchars($form_values['headline'], ENT_QUOTES). "\">
                        <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
                    </div>
                </div>

                <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 25%; float: left;\">Beschreibung:";
                    if($g_preferences['enable_bbcode'] == 1)
                    {
                      echo "<br><br>
                      <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=500,left=310,top=200,scrollbars=yes')\" tabindex=\"6\">Text formatieren</a>";
                    }
                    echo "</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <textarea  name=\"description\" style=\"width: 350px;\" tabindex=\"2\" rows=\"10\" cols=\"40\">". htmlspecialchars($form_values['description'], ENT_QUOTES). "</textarea>
                        <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
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
                            if(isset($form_values['global']) && $form_values['global'] == 1)
                            {
                                echo " checked=\"checked\" ";
                            }
                            echo " value=\"1\" />
                            <label for=\"global\">". $_GET["headline"]. " f&uuml;r mehrere Organisationen sichtbar</label>&nbsp;
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=termin_global','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                        </div>
                    </div>";
                }

                echo "<hr width=\"85%\" />

                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" tabindex=\"4\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\" tabindex=\"5\">
                        <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\"
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                        &nbsp;Zur&uuml;ck</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button name=\"speichern\" type=\"submit\" tabindex=\"5\" value=\"speichern\" tabindex=\"4\">
                        <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\"
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                        &nbsp;Speichern</button>
                </div>
            </div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--
        document.form.headline.focus();
    --></script>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>