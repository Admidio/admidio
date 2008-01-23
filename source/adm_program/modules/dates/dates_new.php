<?php
/******************************************************************************
 * Termine anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
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
if ($g_preferences['enable_dates_module'] == 0)
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

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Terminobjekt anlegen
$date = new Date($g_db);

if($req_dat_id > 0)
{
    $date->getDate($req_dat_id);
    
    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($date->editRight() == false)
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

require(THEME_SERVER_PATH. "/overall_header.php");
echo"<script language=\"javascript\" type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/calendar/CalendarPopup.js\"></script>
<link rel=\"stylesheet\" href=\"".THEME_PATH. "/calendar.css\" type=\"text/css\" />";

// Html des Modules ausgeben
echo "
<form method=\"post\" action=\"$g_root_path/adm_program/modules/dates/dates_function.php?dat_id=$req_dat_id&amp;mode=1\">
<div class=\"formLayout\" id=\"edit_dates_form\">
    <div class=\"formHead\">";
        if($req_dat_id > 0)
        {
            echo $_GET['headline']. " ändern";
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
                    <dt><label for=\"dat_headline\">Überschrift:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"dat_headline\" name=\"dat_headline\" style=\"width: 350px;\" maxlength=\"100\" value=\"". $date->getValue("dat_headline"). "\" />
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
                            <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=termin_global','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\" />
                        </dd>
                    </dl>
                </li>";
            }

            echo "<li><hr /></li>

            <li>
                <dl>
                    <dt><label for=\"date_from\">Datum Beginn:</label></dt>
                    <dd>
						<form>
						<script language=\"javascript\" type=\"text/javascript\" id=\"js18\">
							var cal18 = new CalendarPopup(\"calendardiv\");
							cal18.setCssPrefix(\"calendar\");
							writeSource(\"js18\");
						</script>
						<input type=\"text\" id=\"date_from\" name=\"date_from\" size=\"10\" maxlength=\"10\" value=\"$date_from\">
						<img src=\"". THEME_PATH. "/icons/date.png\" alt=\"Kalender\" onclick=\"javascript:cal18.select(document.forms[0].date_from,'anchor18','dd.MM.yyyy'); return false;\" name=\"anchor18\" id=\"anchor18\" style=\"vertical-align:middle;\" />
						</form>
						<div id=\"calendardiv\" style=\"position: absolute; visibility: hidden; \"></div>

                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                        <span style=\"margin-left: 40px;\">
                            <label for=\"time_from\">Uhrzeit Beginn:</label>
                        </span>
                        <span style=\"margin-left: 10px;\">
                            <input type=\"text\" id=\"time_from\" name=\"time_from\" size=\"5\" maxlength=\"5\" value=\"$time_from\" />
                        </span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"date_to\">Datum Ende:</label></dt>
                    <dd>
						<script language=\"javascript\" type=\"text/javascript\">
							writeSource(\"js18\");
						</script>
						<input type=\"text\" id=\"date_to\" name=\"date_to\" size=\"10\" maxlength=\"10\" value=\"$date_from\">
						<img src=\"". THEME_PATH. "/icons/date.png\" alt=\"Kalender\" onclick=\"javascript:cal18.select(document.forms[0].date_to,'anchor17','dd.MM.yyyy'); return false;\" name=\"anchor17\" id=\"anchor17\" style=\"vertical-align:middle;\" />
						</form>
                        &nbsp;&nbsp;
                        <span style=\"margin-left: 40px;\">
                            <label for=\"time_to\">Uhrzeit Ende:</label>
                        </span>
                        <span style=\"margin-left: 20px;\">
                            <input type=\"text\" id=\"time_to\" name=\"time_to\" size=\"5\" maxlength=\"5\" value=\"$time_to\" />
                        </span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"dat_location\">Treffpunkt:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"dat_location\" name=\"dat_location\" style=\"width: 350px;\" maxlength=\"50\" value=\"". $date->getValue("dat_location"). "\" />
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"dat_description\">Beschreibung:</label>";
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            echo "<br /><br />
                            <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\" tabindex=\"6\">Text formatieren</a>";
                        }
                    echo "</dt>
                    <dd>
                        <textarea id=\"dat_description\" name=\"dat_description\" style=\"width: 350px;\" rows=\"10\" cols=\"40\">". $date->getValue("dat_description"). "</textarea>
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>            
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                <img src=\"". THEME_PATH. "/icons/disk.png\" alt=\"Speichern\" />
                &nbsp;Speichern</button>
        </div>
    </div>
</div>
</form>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
        </span>
    </li>
</ul>

<script type=\"text/javascript\"><!--
    document.getElementById('dat_headline').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>