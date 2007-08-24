<?php
/******************************************************************************
 * Termine anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
 *
 * Uebergaben:
 *
 * dat_id   - ID des Termins, der bearbeitet werden soll
 * headline - Ueberschrift, die ueber den Terminen steht
 *            (Default) Termine
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

// Uebergabevariablen pruefen

if(isset($_GET['dat_id']))
{
    if(is_numeric($_GET['dat_id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_dat_id = $_GET['dat_id'];
}

if(!isset($_GET['headline']))
{
    $_GET["headline"] = "Termine";
}

$_SESSION['navigation']->addUrl($g_current_url);

// Terminobjekt anlegen
$date = new Date($g_db);

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
            $date->setValue($key, stripslashes($value));
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
<form name=\"form\" method=\"post\" action=\"$g_root_path/adm_program/modules/dates/dates_function.php?dat_id=$req_dat_id&amp;mode=1\">
<div class=\"formLayout\" id=\"edit_dates_form\">
    <div class=\"formHead\">";
        if($req_dat_id > 0)
        {
            echo $_GET['headline']. " &auml;ndern";
        }
        else
        {
            echo $_GET['headline']. " anlegen";
        }
    echo "</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt><label for=\"dat_headline\">&Uuml;berschrift:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"dat_headline\" name=\"dat_headline\" style=\"width: 350px;\" maxlength=\"100\" value=\"". htmlspecialchars($date->getValue("dat_headline"), ENT_QUOTES). "\">
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>";

            // besitzt die Organisation eine Elternorga oder hat selber Kinder, so kann die Ankuendigung auf "global" gesetzt werden
            if($g_current_organization->getValue("org_org_id_parent") > 0
            || $g_current_organization->hasChildOrganizations())
            {
                echo "
                <li>
                    <dl>
                        <dt>&nbsp;</dt>
                        <dd>
                            <input type=\"checkbox\" id=\"dat_global\" name=\"dat_global\" ";
                            if($date->getValue("dat_global") == 1)
                            {
                                echo " checked=\"checked\" ";
                            }
                            echo " value=\"1\" />
                            <label for=\"dat_global\">". $_GET['headline']. " ist f&uuml;r mehrere Organisationen sichtbar</label>
                            <img class=\"iconHelpLink\" src=\"$g_root_path/adm_program/images/help.png\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=termin_global','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                        </dd>
                    </dl>
                </li>";
            }

            echo "<hr />

            <li>
                <dl>
                    <dt><label for=\"date_from\">Datum Beginn:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"date_from\" name=\"date_from\" size=\"10\" maxlength=\"10\" value=\"$date_from\">
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<label for=\"time_from\">Uhrzeit Beginn:</label>&nbsp;
                        <input type=\"text\" id=\"time_from\" name=\"time_from\" size=\"5\" maxlength=\"5\" value=\"$time_from\">
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"date_to\">Datum Ende:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"date_to\" name=\"date_to\" size=\"10\" maxlength=\"10\" value=\"$date_to\">
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<label for=\"time_to\">Uhrzeit Ende:</label>&nbsp;
                        <input type=\"text\"id=\"time_to\" name=\"time_to\" size=\"5\" maxlength=\"5\" value=\"$time_to\">
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"dat_location\">Treffpunkt:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"dat_location\" name=\"dat_location\" style=\"width: 350px;\" maxlength=\"50\" value=\"". htmlspecialchars($date->getValue("dat_location"), ENT_QUOTES). "\">
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"dat_description\">Beschreibung:</label>";
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            echo "<br><br>
                            <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\" tabindex=\"6\">Text formatieren</a>";
                        }
                    echo "</dt>
                    <dd>
                        <textarea id=\"dat_description\" name=\"dat_description\" style=\"width: 350px;\" rows=\"10\" cols=\"40\">". htmlspecialchars($date->getValue("dat_description"), ENT_QUOTES). "</textarea>
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>            
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                <img src=\"$g_root_path/adm_program/images/disk.png\" alt=\"Speichern\">
                &nbsp;Speichern</button>
        </div>
    </div>
</div>
</form>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
        </span>
    </li>
</ul>

<script type=\"text/javascript\"><!--
    document.getElementById('dat_headline').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>