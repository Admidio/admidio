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
else
{
    // bei neuem Termin Datum mit aktuellen Daten vorbelegen
    $date->setValue("dat_begin", date("Y-m-d H:00:00", time()));
    $date->setValue("dat_end", date("Y-m-d H:00:00", time()+3600));
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
    
    // Datum-Bis nur anzeigen, wenn es sich von Datum-Von unterscheidet
    $date_to = mysqldatetime("d.m.y", $date->getValue("dat_end"));
    $time_to = mysqldatetime("h:i",   $date->getValue("dat_end"));
}

// Html-Kopf ausgeben
if($req_dat_id > 0)
{
    $g_layout['title'] = $_GET['headline']. " ändern";
}
else
{
    $g_layout['title'] = $_GET['headline']. " anlegen";
}
$g_layout['header'] = "
    <script type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/calendar/CalendarPopup.js\"></script>
    <link rel=\"stylesheet\" href=\"".THEME_PATH. "/calendar.css\" type=\"text/css\" />
    
    <script type=\"text/javascript\">
        // Funktion blendet Zeitfelder ein/aus
        function setAllDay()
        {
            if(document.getElementById('dat_all_day').checked == true)
            {
                document.getElementById('time_from').style.visibility = 'hidden';
                document.getElementById('time_from').style.display    = 'none';
                document.getElementById('time_to').style.visibility = 'hidden';
                document.getElementById('time_to').style.display    = 'none';
            }
            else
            {
                document.getElementById('time_from').style.visibility = 'visible';
                document.getElementById('time_from').style.display    = '';
                document.getElementById('time_to').style.visibility = 'visible';
                document.getElementById('time_to').style.display    = '';
            }
        }
        
        // Funktion belegt das Datum-bis entsprechend dem Datum-Von
        function setDateTo()
        {
            alert(document.getElementById('time_from').value);
            //dateFrom = new Date(
        } 
    </script>";

require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<form method=\"post\" action=\"$g_root_path/adm_program/modules/dates/dates_function.php?dat_id=$req_dat_id&amp;mode=1\">
<div class=\"formLayout\" id=\"edit_dates_form\">
    <div class=\"formHead\">". $g_layout['title']. "</div>
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
                            <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=termin_global',this);\" onmouseout=\"ajax_hideTooltip()\" />
                        </dd>
                    </dl>
                </li>";
            }

            echo "<li><hr /></li>

            <li>
                <dl>
                    <dt><label for=\"date_from\">Beginn:</label></dt>
                    <dd>
						<script type=\"text/javascript\" id=\"js18\">
							var calPopUp = new CalendarPopup(\"calendardiv\");
							calPopUp.setCssPrefix(\"calendar\");
						</script>
						<span>
    						<input type=\"text\" id=\"date_from\" name=\"date_from\" onchange=\"setDateTo()\" size=\"10\" maxlength=\"10\" value=\"$date_from\" />
    						<img id=\"ico_cal_date_from\" src=\"". THEME_PATH. "/icons/date.png\" onclick=\"javascript:calPopUp.select(document.getElementById('date_from'),'ico_cal_date_from','dd.MM.yyyy');\" style=\"vertical-align:middle; cursor:pointer;\" alt=\"Kalender anzeigen\" title=\"Kalender anzeigen\" />
    						<span id=\"calendardiv\" style=\"position: absolute; visibility: hidden; \"></span>
    				    </span>
                        <span style=\"margin-left: 10px;\">
                            <input type=\"text\" id=\"time_from\" name=\"time_from\" size=\"5\" maxlength=\"5\" value=\"$time_from\" />
                            <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                        </span>
                        <span style=\"margin-left: 15px;\">
                            <input type=\"checkbox\" id=\"dat_all_day\" name=\"dat_all_day\" ";
                            if($date->getValue("dat_all_day") == 1)
                            {
                                echo " checked=\"checked\" ";
                            }
                            echo " onclick=\"setAllDay()\" value=\"1\" />
                            <label for=\"dat_all_day\">Ganztägig</label>
                        </span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"date_to\">Ende:</label></dt>
                    <dd>
						<span>
						    <input type=\"text\" id=\"date_to\" name=\"date_to\" size=\"10\" maxlength=\"10\" value=\"$date_to\" />
						    <img id=\"ico_cal_date_to\" src=\"". THEME_PATH. "/icons/date.png\" onclick=\"javascript:calPopUp.select(document.getElementById('date_to'),'ico_cal_date_to','dd.MM.yyyy');\" style=\"vertical-align:middle; cursor:pointer;\" alt=\"Kalender anzeigen\" title=\"Kalender anzeigen\" />
						</span>
                        <span style=\"margin-left: 10px;\">
                            <input type=\"text\" id=\"time_to\" name=\"time_to\" size=\"5\" maxlength=\"5\" value=\"$time_to\" />
                            <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
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
                            <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode&amp;window=true','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\" tabindex=\"6\">Text formatieren</a>";
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

<script type=\"text/javascript\">
    document.getElementById('dat_headline').focus();
    setAllDay();
</script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>