<?php
/******************************************************************************
 * Termine anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * dat_id: ID des Termins, der bearbeitet werden soll
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
if ($g_preferences['enable_dates_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}


if(!editDate())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["dat_id"]))
{
    if(is_numeric($_GET["dat_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $dat_id = $_GET["dat_id"];
}
else
{
    $dat_id = 0;
}

$_SESSION['navigation']->addUrl($g_current_url);

if(isset($_SESSION['dates_request']))
{
    // alte Werte nach Fehlermeldung wiederherstellen
    $form_values = $_SESSION['dates_request'];
    unset($_SESSION['dates_request']);
}
else
{
    $form_values['headline']      = "";
    $form_values['global']        = 0;
    $form_values['date_from']     = "";
    $form_values['time_from']     = "";
    $form_values['date_to']       = "";
    $form_values['time_to']       = "";
    $form_values['meeting_point'] = "";
    $form_values['description']   = "";

    // Wenn eine Termin-ID uebergeben wurde, soll der Termin geaendert werden
    // -> Felder mit Daten des Termins vorbelegen

    if ($dat_id > 0)
    {
        $sql    = "SELECT * FROM ". TBL_DATES. "
                    WHERE dat_id = {0}
                      AND (  dat_org_shortname = '$g_organization'
                          OR dat_global = 1) ";
        $sql    = prepareSQL($sql, array($dat_id));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        if (mysql_num_rows($result) > 0)
        {
            $row_bt = mysql_fetch_object($result);

            $form_values['global']    = $row_bt->dat_global;
            $form_values['headline']  = $row_bt->dat_headline;
            $form_values['date_from'] = mysqldatetime("d.m.y", $row_bt->dat_begin);
            $form_values['time_from'] = mysqldatetime("h:i", $row_bt->dat_begin);
            if($row_bt->dat_begin != $row_bt->dat_end)
            {
                // Datum-Bis nur anzeigen, wenn es sich von Datum-Von unterscheidet
                $form_values['date_to'] = mysqldatetime("d.m.y", $row_bt->dat_end);
                $form_values['time_to'] = mysqldatetime("h:i", $row_bt->dat_end);
            }
            if ($form_values['time_from'] == "00:00")
            {
                $form_values['time_from'] = "";
            }
            if ($form_values['time_to'] == "00:00")
            {
                $form_values['time_to'] = "";
            }
            $form_values['meeting_point'] = $row_bt->dat_location;
            $form_values['description']   = $row_bt->dat_description;
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
    <title>$g_current_organization->longname - Termin</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <form name=\"form\" action=\"dates_function.php?dat_id=$dat_id&amp;mode=";
            if($dat_id > 0)
            {
                echo "3";
            }
            else
            {
                echo "1";
            }
            echo "\" method=\"post\" name=\"TerminAnlegen\">

            <div class=\"formHead\">";
                if($dat_id > 0)
                {
                    echo strspace("Termin &auml;ndern", 2);
                }
                else
                {
                    echo strspace("Termin anlegen", 2);
                }
            echo "</div>
            <div class=\"formBody\">
                <div>
                    <div style=\"text-align: right; width: 25%; float: left;\">&Uuml;berschrift:</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <input type=\"text\" name=\"headline\" style=\"width: 350px;\" maxlength=\"100\" value=\"". htmlspecialchars($form_values['headline'], ENT_QUOTES). "\">
                        <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
                    </div>
                </div>";

                // bei mehr als einer Organisation, Checkbox anzeigen, ob Termin bei anderen angezeigt werden soll
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
                            <input type=\"checkbox\" id=\"global\" name=\"global\" ";
                            if(isset($form_values['global']) && $form_values['global'] == 1)
                            {
                                echo " checked=\"checked\" ";
                            }
                            echo " value=\"1\" />
                            <label for=\"global\">Termin ist f&uuml;r mehrere Organisationen sichtbar</label>&nbsp;
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=termin_global','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                        </div>
                    </div>";
                }

                echo "<hr width=\"85%\" />

                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 25%; float: left;\">Datum Beginn:</div>
                    <div style=\"text-align: left; width: 75%; position: relative; left: 2%;\">
                        <input type=\"text\" name=\"date_from\" size=\"10\" maxlength=\"10\" value=\"". $form_values['date_from']. "\">
                        <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Uhrzeit Beginn:&nbsp;
                        <input type=\"text\" name=\"time_from\" size=\"5\" maxlength=\"5\" value=\"". $form_values['time_from']. "\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 25%; float: left;\">Datum Ende:</div>
                    <div style=\"text-align: left; width: 75%; position: relative; left: 2%;\">
                        <input type=\"text\" name=\"date_to\" size=\"10\" maxlength=\"10\" value=\"". $form_values['date_to']. "\">
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Uhrzeit Ende:&nbsp;
                        <input type=\"text\" name=\"time_to\" size=\"5\" maxlength=\"5\" value=\"". $form_values['time_to']. "\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 25%; float: left;\">Treffpunkt:</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <input type=\"text\" name=\"meeting_point\" style=\"width: 350px;\" maxlength=\"50\" value=\"". htmlspecialchars($form_values['meeting_point'], ENT_QUOTES). "\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 25%; float: left;\">Beschreibung:";
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            echo "<br><br>
                            <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\" tabindex=\"6\">Text formatieren</a>";
                        }
                    echo "</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <textarea  name=\"description\" style=\"width: 350px;\" rows=\"10\" cols=\"40\">". htmlspecialchars($form_values['description'], ENT_QUOTES). "</textarea>
                        <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
                    </div>
                </div>

                <hr width=\"85%\" />

                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\">
                        <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\"
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                        &nbsp;Zur&uuml;ck</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button name=\"speichern\" type=\"submit\" value=\"speichern\">
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