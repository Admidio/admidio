<?php
/******************************************************************************
 * Termine anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * dat_id   - ID des Termins, der bearbeitet werden soll
 * headline - Ueberschrift, die ueber den Terminen steht
 *            (Default) Termine
 * calendar - der Kalender wird vorbelegt
 * copy : true - der uebergebene ID-Termin wird kopiert und kann neu gespeichert werden
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_date.php');
require_once('../../system/classes/table_rooms.php');
require_once('../../system/classes/table_roles.php');

if ($g_preferences['enable_bbcode'] == 1)
{
    require_once('../../system/bbcode.php');
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_dates_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

if(!$g_current_user->editDates())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_dat_id   = 0;
$req_calendar = '';
$req_copy     = false;

// Uebergabevariablen pruefen

if(isset($_GET['dat_id']))
{
    if(is_numeric($_GET['dat_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_dat_id = $_GET['dat_id'];
}

if(!isset($_GET['headline']))
{
    $_GET['headline'] = $g_l10n->get('DAT_DATES');
}

if(isset($_GET['calendar']))
{
    $req_calendar = $_GET['calendar'];
}
if(isset($_GET['copy']) && $_GET['copy'] == 1)
{
    $req_copy = true;
}


$_SESSION['navigation']->addUrl(CURRENT_URL);

// Terminobjekt anlegen
$date = new TableDate($g_db);

if($req_dat_id > 0)
{
    $date->readData($req_dat_id);
    
    if($req_copy)
    {
        $date->setValue('dat_id', 0);
        $req_dat_id = 0;
    }
    
    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($date->editRight() == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
}
else
{
    // bei neuem Termin Datum mit aktuellen Daten vorbelegen
    $date->setValue('dat_begin', date('Y-m-d H:00:00', time()));
    $date->setValue('dat_end', date('Y-m-d H:00:00', time()+3600));
    
    // wurde ein Kalender uebergeben, dann diesen vorbelegen
    if(strlen($req_calendar) > 0)
    {
        $sql = 'SELECT cat_id FROM '.TBL_CATEGORIES.' WHERE cat_name = "'.$req_calendar.'"';
        $g_db->query($sql);
        $row = $g_db->fetch_array();
        $date->setValue('dat_cat_id', $row['cat_id']);
    }
}

if(isset($_SESSION['dates_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['dates_request'] as $key => $value)
    {
        if(strpos($key, 'dat_') == 0)
        {
            $date->setValue($key, stripslashes($value));
        }
    }

    // ausgewaehlte Rollen vorbelegen
    $count = 1;
    $arrRoles = array();
    while(isset($_SESSION['dates_request']['role_'.$count]))
    {
        $arrRoles[] = $_SESSION['dates_request']['role_'.$count];
        $count++;
    }
    $date->setVisibleRoles($arrRoles);
    
    $date_from = $_SESSION['dates_request']['date_from'];
    $time_from = $_SESSION['dates_request']['time_from'];
    $date_to   = $_SESSION['dates_request']['date_to'];
    $time_to   = $_SESSION['dates_request']['time_to'];
    
    unset($_SESSION['dates_request']);
}
else
{
    // Zeitangaben von/bis aus Datetime-Feld aufsplitten
    $date_from = $date->getValue('dat_begin', $g_preferences['system_date']);
    $time_from = $date->getValue('dat_begin', $g_preferences['system_time']);

    // Datum-Bis nur anzeigen, wenn es sich von Datum-Von unterscheidet
    $date_to = $date->getValue('dat_end', $g_preferences['system_date']);
    $time_to = $date->getValue('dat_end', $g_preferences['system_time']);
    
    // Sichtbar fuer alle wird per Default vorbelegt
    $date->setVisibleRoles(array('-1'));
}

// Html-Kopf ausgeben
if($req_dat_id > 0)
{
    $g_layout['title'] = $g_l10n->get('SYS_PHR_EDIT', $_GET['headline']);
}
else
{
    $g_layout['title'] = $g_l10n->get('SYS_PHR_CREATE', $_GET['headline']);
}

$g_layout['header'] = '
<script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/date-functions.js"></script>
<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/calendar/calendar-popup.js"></script>
<link rel="stylesheet" href="'.THEME_PATH. '/css/calendar.css" type="text/css" />
<script type="text/javascript"><!--
    // Funktion blendet Zeitfelder ein/aus
    function setAllDay()
    {
        if(document.getElementById("dat_all_day").checked == true)
        {
            document.getElementById("time_from").style.visibility = "hidden";
            document.getElementById("time_from").style.display    = "none";
            document.getElementById("time_to").style.visibility = "hidden";
            document.getElementById("time_to").style.display    = "none";
        }
        else
        {
            document.getElementById("time_from").style.visibility = "visible";
            document.getElementById("time_from").style.display    = "";
            document.getElementById("time_to").style.visibility = "visible";
            document.getElementById("time_to").style.display    = "";
        }
    }

    // Funktion belegt das Datum-bis entsprechend dem Datum-Von
    function setDateTo()
    {
        var dateFrom = Date.parseDate($("#date_from").val(), "'.$g_preferences['system_date'].'");
        var dateTo   = Date.parseDate($("#date_to").val(), "'.$g_preferences['system_date'].'");

        if(dateFrom.getTime() > dateTo.getTime())
        {
            $("#date_to").val($("#date_from").val());
        }
    }

    var calPopup = new CalendarPopup("calendardiv");
    calPopup.setCssPrefix("calendar");
    var count = 1;

    
    function addRoleSelection(roleID)
    {
        $.ajax({url: "dates_function.php?mode=5&count="+count+"&rol_id="+roleID, type: "GET", async:   false, 
            success: function(data){
                if(count == 1)
                {
                    $("#liRoles").html($("#liRoles").html() + data);
                }
                else
                {
                    number = count-1;
                    $("#roleID_"+number).after(data);
                }
            }});
        count++;
    }
    
    function removeRoleSelection(id)
    {
        $("#"+id).hide("slow");
        $("#"+id).remove();
    }

    $(document).ready(function() 
    {
        setAllDay();
        $("#dat_headline").focus();';
        // alle Rollen anzeigen, die diesen Termin sehen duerfen
        if(count($date->getVisibleRoles()) > 0)
        {
            foreach($date->getVisibleRoles() as $key => $roleID)
            {
                $g_layout['header'] .= 'addRoleSelection('.$roleID.');';
            }
        }
        else
        {
            $g_layout['header'] .= 'addRoleSelection(0);';
        }

    if($date->getValue('dat_rol_id') > 0)
    {
        $dateRoleID = $date->getValue('dat_rol_id');
    }
    else
    {
        $dateRoleID = '0';
    }
    $g_layout['header'] .= '}); 

    var dateRoleID = '.$dateRoleID.';
    //öffnet Messagefenster
    function submitForm()
    {
        if(dateRoleID > 0 && !document.getElementById("date_login").checked)
        {
            var msg_result = confirm("'.$g_l10n->get('DAT_PHR_REMOVE_APPLICATION').'");
            if(msg_result)
            {
                $("#formDate").submit();
            }
        }
        else
        {
            $("#formDate").submit();
        }
    }
//--></script>';

//Script für BBCode laden
$javascript = "";
if ($g_preferences['enable_bbcode'] == 1)
{
    $javascript = getBBcodeJS('dat_description');
}
$g_layout['header'] .= $javascript;
require(THEME_SERVER_PATH. '/overall_header.php');
 
// Html des Modules ausgeben
echo '
<form method="post" id="formDate" action="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?dat_id='.$req_dat_id.'&amp;mode=1">
<div class="formLayout" id="edit_dates_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="dat_headline">'.$g_l10n->get('SYS_TITLE').':</label></dt>
                    <dd>
                        <input type="text" id="dat_headline" name="dat_headline" style="width: 345px;" maxlength="100" value="'. $date->getValue('dat_headline'). '" />
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>';

            // besitzt die Organisation eine Elternorga oder hat selber Kinder, so kann die Ankuendigung auf "global" gesetzt werden
            if($g_current_organization->getValue('org_org_id_parent') > 0
                || $g_current_organization->hasChildOrganizations())
            {
                echo '
                <li>
                    <dl>
                        <dt>&nbsp;</dt>
                        <dd>
                            <input type="checkbox" id="dat_global" name="dat_global" ';
                            if($date->getValue('dat_global') == 1)
                            {
                                echo ' checked="checked" ';
                            }
                            echo ' value="1" />
                            <label for="dat_global">'.$g_l10n->get('SYS_PHR_ENTRY_MULTI_ORGA').'</label>
                            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=SYS_PHR_DATA_GLOBAL&amp;inline=true"><img 
                                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=SYS_PHR_DATA_GLOBAL\',this)" onmouseout="ajax_hideTooltip()"
                                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
                        </dd>
                    </dl>
                </li>';
            }

        echo '<li><hr /></li>

            <li>
                <dl>
                    <dt><label for="date_from">'.$g_l10n->get('SYS_START').':</label></dt>
                    <dd>
                        <span>
                            <input type="text" id="date_from" name="date_from" onchange="javascript:setDateTo();" size="10" maxlength="10" value="'.$date_from.'" />
                            <a class="iconLink" id="anchor_date_from" href="javascript:calPopup.select(document.getElementById(\'date_from\'),\'anchor_date_from\',\''.$g_preferences['system_date'].'\',\'date_from\',\'date_to\',\'time_from\',\'time_to\');"><img 
                                src="'.THEME_PATH.'/icons/calendar.png" alt="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" title="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" /></a>
                            <span id="calendardiv" style="position: absolute; visibility: hidden; "></span>
                        </span>
                        <span style="margin-left: 10px;">
                            <input type="text" id="time_from" name="time_from" size="5" maxlength="5" value="'.$time_from.'" />
                            <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                        </span>
                        <span style="margin-left: 15px;">
                            <input type="checkbox" id="dat_all_day" name="dat_all_day" ';
                            if($date->getValue('dat_all_day') == 1)
                            {
                                echo ' checked="checked" ';
                            }
                            echo ' onclick="setAllDay()" value="1" />
                            <label for="dat_all_day">'.$g_l10n->get('DAT_ALL_DAY').'</label>
                        </span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="date_to">'.$g_l10n->get('SYS_END').':</label></dt>
                    <dd>
                        <span>
                            <input type="text" id="date_to" name="date_to" size="10" maxlength="10" value="'.$date_to.'" />
                            <a class="iconLink" id="anchor_date_to" href="javascript:calPopup.select(document.getElementById(\'date_to\'),\'anchor_date_to\',\''.$g_preferences['system_date'].'\',\'date_from\',\'date_to\',\'time_from\',\'time_to\');"><img 
                                src="'.THEME_PATH.'/icons/calendar.png" alt="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" title="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" /></a>
                        </span>
                        <span style="margin-left: 10px;">
                            <input type="text" id="time_to" name="time_to" size="5" maxlength="5" value="'.$time_to.'" />
                            <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'" id="timeToMandatory"';
                            if($date->getValue('dat_repeat_type') != 0)
                            {
                                echo ' style="visibility: hidden;"';
                            }
                            echo '>*</span>
                        </span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="dat_cat_id">'.$g_l10n->get('DAT_CALENDAR').':</label></dt>
                    <dd>
                        <select id="dat_cat_id" name="dat_cat_id" size="1" tabindex="4">
                            <option value=" "';
                            if($date->getValue('dat_cat_id') == 0)
                            {
                                echo ' selected="selected" ';
                            }
                            echo '>- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>';

                            $sql = 'SELECT cat_id, cat_name 
                                      FROM '. TBL_CATEGORIES. '
                                     WHERE cat_org_id = '. $g_current_organization->getValue('org_id'). '
                                       AND cat_type   = "DAT"
                                     ORDER BY cat_sequence ASC ';
                            $result = $g_db->query($sql);

                            while($row = $g_db->fetch_array($result))
                            {
                                echo '<option value="'.$row['cat_id'].'"';
                                error_log('kategorie:'.$date->getValue('dat_cat_id').'::'.$row['cat_id']);
                                    if($date->getValue('dat_cat_id') == $row['cat_id'])
                                    {
                                        echo ' selected="selected" ';
                                    }
                                echo '>'.$row['cat_name'].'</option>';
                            }
                        echo '</select>
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt>'.$g_l10n->get('DAT_REGISTRATION_POSSIBLE').':</dt>
                    <dd>
                        <input type="checkbox" id="date_login" name="date_login"';
                        if($date->getValue('dat_rol_id') > 0 
                        || (isset($_SESSION['dates_request']['date_login']) && $_SESSION['dates_request']['date_login'] == 1))
                        {
                            echo ' checked="checked" ';
                        }
                        echo ' onclick="toggleMaxMembers()" value="1" />
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DAT_PHR_LOGIN_POSSIBLE&amp;inline=true"><img 
                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DAT_PHR_LOGIN_POSSIBLE\',this)" 
                            onmouseout="ajax_hideTooltip()" class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
                    </dd>
                </dl>
            </li>';
            if($g_preferences['dates_show_rooms']==1) //nur wenn Raumauswahl aktiviert ist
            {
                echo'
                    <li id="room">
                        <dl>
                            <dt>'.$g_l10n->get('SYS_ROOM').':</dt>
                            <dd>
                                <select id="dat_room_id" name="dat_room_id" size="1" tabindex="6" onchange="toggleRolId()">
                                    <option value="0"';
                                    if($date->getValue('dat_room_id') == 0)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$g_l10n->get('SYS_NONE').'</option>';
        
                                    $sql = 'SELECT room_id, room_name, room_capacity, room_overhang 
                                              FROM '.TBL_ROOMS.'
                                             ORDER BY room_name';
                                    $result = $g_db->query($sql);
        
                                    while($row = $g_db->fetch_array($result))
                                    {
                                        echo '<option value="'.$row['room_id'].'"';
                                            if($date->getValue('dat_room_id') == $row['room_id'])
                                            {
                                                echo ' selected="selected" ';
                                            }
                                        echo '>'.$row['room_name'].' ('.$row['room_capacity'].'+'.$row['room_overhang'].')</option>';
                                    }
                                echo '</select>
                            </dd>
                        </dl>
                    </li>';
            }
          
            echo'
            <li id="max_members">
                <dl>
                    <dt>'.$g_l10n->get('DAT_PARTICIPANTS_LIMIT').':</dt>
                    <dd>
                        <input type="text" id="dat_max_members" name="dat_max_members" style="width: 50px;" maxlength="5" value="'.($date->getValue('dat_max_members') ? $date->getValue('dat_max_members') : '').'" />
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DAT_PHR_MAX_MEMBERS&amp;inline=true"><img 
                            onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DAT_PHR_MAX_MEMBERS\',this)" 
                            onmouseout="ajax_hideTooltip()" class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
                    </dd>
                </dl>
            </li>
            <li id="liRoles"></li>
            <li>
                <dl>
                    <dt></dt>
                    <dd><span id="add_attachment" class="iconTextLink">
                            <a href="javascript:addRoleSelection(0)"><img
                            src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('DAT_ADD_ROLE').'" /></a>
                            <a href="javascript:addRoleSelection(0)">'.$g_l10n->get('DAT_ADD_ROLE').'</a>
                        </span></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="dat_location">'.$g_l10n->get('DAT_LOCATION').':</label></dt>
                    <dd>
                        <input type="text" id="dat_location" name="dat_location" style="width: 345px;" maxlength="50" value="'. $date->getValue('dat_location'). '" />';
                        if($g_preferences['dates_show_map_link'])
                        {
                            echo '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DAT_PHR_LOCATION_LINK&amp;inline=true"><img 
                                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DAT_PHR_LOCATION_LINK\',this)" 
                                onmouseout="ajax_hideTooltip()" class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>';
                        }
                    echo '</dd>
                </dl>
            </li>';
            if($g_preferences['dates_show_map_link'])
            {
                if(strlen($date->getValue('dat_country')) == 0)
                {
                    $date->setValue('dat_country', $g_preferences['default_country']);
                }
                echo '<li>
                    <dl>
                        <dt>&nbsp;</dt>
                        <dd>
                            <select size="1" id="dat_country" name="dat_country">';
                                // Datei mit Laenderliste oeffnen und alle Laender einlesen
                                $country_list = fopen('../../system/staaten.txt', 'r');
                                $country = trim(fgets($country_list));
                                while (!feof($country_list))
                                {
                                    echo '<option value="'.$country.'"';
                                    if($country == $date->getValue('dat_country'))
                                    {
                                        echo ' selected="selected" ';
                                    }
                                    echo '>'.$country.'</option>';
                                    $country = trim(fgets($country_list));
                                }
                                fclose($country_list);
                            echo '</select>
                        </dd>
                    </dl>
                </li>';
            }
            
            if ($g_preferences['enable_bbcode'] == 1)
            {
               printBBcodeIcons();
            }
            echo '
            <li>
                <dl>
                    <dt><label for="dat_description">'.$g_l10n->get('SYS_DESCRIPTION').':</label>';
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            printEmoticons();
                        }
                    echo '</dt>
                    <dd>
                        <textarea id="dat_description" name="dat_description" style="width: 345px;" rows="10" cols="40">'. $date->getValue('dat_description'). '</textarea>
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />';

        if($date->getValue('dat_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($g_db, $date->getValue('dat_usr_id_create'));
                echo $g_l10n->get('SYS_PHR_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $date->getValue('dat_timestamp_create'));

                if($date->getValue('dat_usr_id_change') > 0)
                {
                    $user_change = new User($g_db, $date->getValue('dat_usr_id_change'));
                    echo '<br />'.$g_l10n->get('SYS_PHR_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $date->getValue('dat_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button id="btnSave" type="button" onclick="javascript:submitForm();"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$g_l10n->get('SYS_SAVE').'" />&nbsp;'.$g_l10n->get('SYS_SAVE').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>

<script type="text/javascript">
    toggleMaxMembers();
</script>
';

require(THEME_SERVER_PATH. '/overall_footer.php');
?>