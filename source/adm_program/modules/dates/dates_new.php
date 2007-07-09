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
 * dat_id   - ID des Termins, der bearbeitet werden soll
 * headline - Ueberschrift, die ueber den Terminen steht
 *            (Default) Termine
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
require("../../system/date_class.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_dates_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

if(!$g_current_user->editDates())
{
    $g_message->show("norights");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_dat_id   = 0;
$req_headline = "Termine";

// Uebergabevariablen pruefen

if(isset($_GET['dat_id']))
{
    if(is_numeric($_GET['dat_id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_dat_id = $_GET['dat_id'];
}

if(isset($_GET['headline']))
{
    $req_headline = strStripTags($_GET["headline"]);
}

$_SESSION['navigation']->addUrl($g_current_url);

// Rollenobjekt anlegen
$date = new Date($g_adm_con);

if($req_dat_id > 0)
{
    $date->getDate($req_dat_id);
    
    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($date->getValue("dat_org_shortname") != $g_organization
    && $date->getValue("dat_global") == 0 )
    {
        $g_message->show("norights");
    }
}

if(isset($_SESSION['dates_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['dates_request'] as $key => $value)
    {
        if(strpos($key, "dat_") == 0)
        {
            $date->setValue($key, $value);
        }        
    }
    $date_from = $_SESSION['dates_request']['date_from'];
    $time_from = $_SESSION['dates_request']['time_from'];
    $date_to   = $_SESSION['dates_request']['date_to'];
    $time_to   = $_SESSION['dates_request']['time_to'];
    unset($_SESSION['dates_request']);
}
else
{
    // Zeitangaben von/bis aus Datetime-Feld aufsplitten
    $date_from = mysqldatetime("d.m.y", $date->getValue("dat_begin"));
    $time_from = mysqldatetime("h:i",   $date->getValue("dat_begin"));
    $date_to   = null;
    $time_to   = null;
    if($date->getValue("dat_begin") != $date->getValue("dat_end"))
    {
        // Datum-Bis nur anzeigen, wenn es sich von Datum-Von unterscheidet
        $date_to = mysqldatetime("d.m.y", $date->getValue("dat_end"));
        $time_to = mysqldatetime("h:i",   $date->getValue("dat_end"));
    }
}

// Html-Kopf ausgeben
$g_layout['title'] = $_GET["headline"];

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<form name=\"form\" action=\"$g_root_path/adm_program/modules/dates/dates_function.php?dat_id=$req_dat_id&amp;mode=";
    if($req_dat_id > 0)
    {
        echo "3";
    }
    else
    {
        echo "1";
    }
    echo "\" method=\"post\" name=\"TerminAnlegen\">

    <div class=\"formHead\">";
        if($req_dat_id > 0)
        {
            echo "$req_headline &auml;ndern";
        }
        else
        {
            echo "$req_headline anlegen";
        }
    echo "</div>
    <div class=\"formBody\">
        <div>
            <div style=\"text-align: right; width: 25%; float: left;\">&Uuml;berschrift:</div>
            <div style=\"text-align: left; margin-left: 27%;\">
                <input type=\"text\" name=\"dat_headline\" style=\"width: 350px;\" maxlength=\"100\" value=\"". htmlspecialchars($date->getValue("dat_headline"), ENT_QUOTES). "\">
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>";

        // bei mehr als einer Organisation, Checkbox anzeigen, ob Termin bei anderen angezeigt werden soll
        $sql = "SELECT org_id FROM ". TBL_ORGANIZATIONS. "
                 WHERE org_org_id_parent IS NOT NULL ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);

        if(mysql_num_rows($result) > 0)
        {
            echo "
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 25%; float: left;\">&nbsp;</div>
                <div style=\"text-align: left; margin-left: 27%;\">
                    <input type=\"checkbox\" id=\"dat_global\" name=\"dat_global\" ";
                    if($date->getValue("dat_global") == 1)
                    {
                        echo " checked=\"checked\" ";
                    }
                    echo " value=\"1\" />
                    <label for=\"dat_global\">$req_headline ist f&uuml;r mehrere Organisationen sichtbar</label>&nbsp;
                    <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" alt=\"Hilfe\" title=\"Hilfe\"
                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=termin_global','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                </div>
            </div>";
        }

        echo "<hr class=\"formLine\" width=\"85%\" />

        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Datum Beginn:</div>
            <div style=\"text-align: left; width: 75%; position: relative; left: 2%;\">
                <input type=\"text\" name=\"date_from\" size=\"10\" maxlength=\"10\" value=\"$date_from\">
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Uhrzeit Beginn:&nbsp;
                <input type=\"text\" name=\"time_from\" size=\"5\" maxlength=\"5\" value=\"$time_from\">
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
        <div style=\"text-align: right; width: 25%; float: left;\">Datum Ende:</div>
            <div style=\"text-align: left; width: 75%; position: relative; left: 2%;\">
                <input type=\"text\" name=\"date_to\" size=\"10\" maxlength=\"10\" value=\"$date_to\">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Uhrzeit Ende:&nbsp;
                <input type=\"text\" name=\"time_to\" size=\"5\" maxlength=\"5\" value=\"$time_to\">
            </div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Treffpunkt:</div>
            <div style=\"text-align: left; margin-left: 27%;\">
                <input type=\"text\" name=\"dat_location\" style=\"width: 350px;\" maxlength=\"50\" value=\"". htmlspecialchars($date->getValue("dat_location"), ENT_QUOTES). "\">
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
                <textarea  name=\"dat_description\" style=\"width: 350px;\" rows=\"10\" cols=\"40\">". htmlspecialchars($date->getValue("dat_description"), ENT_QUOTES). "</textarea>
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>

        <hr class=\"formLine\" width=\"85%\" />

        <div style=\"margin-top: 6px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\">
                <img src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\">
                &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                <img src=\"$g_root_path/adm_program/images/disk.png\" alt=\"Speichern\">
                &nbsp;Speichern</button>
        </div>
    </div>
</form>

<script type=\"text/javascript\"><!--
    document.form.headline.focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>