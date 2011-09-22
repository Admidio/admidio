<?php
/******************************************************************************
 * Show a list of all dates
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode: actual       - (Default) Alle aktuellen und zukuenftige Termine anzeigen
 *       old          - Alle bereits erledigten
 * start              - Angabe, ab welchem Datensatz Termine angezeigt werden sollen
 * headline           - Ueberschrift, die ueber den Terminen steht
 *                      (Default) Termine
 * calendar           - Angabe der Kategorie damit auch nur eine angezeigt werden kann.
 * id                 - Nur einen einzigen Termin anzeigen lassen.
 * date               - Alle Termine zu einem Datum werden aufgelistet
 *                      Uebergabeformat: YYYYMMDD
 * calendar-selection - 1: Es wird die Box angezeigt
 *                      0: Es wird keine Box angezeigt
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_date.php');
require_once('../../system/classes/table_rooms.php');
unset($_SESSION['dates_request']);

// pruefen ob das Modul ueberhaupt aktiviert ist
if($gPreferences['enable_dates_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_dates_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Initialize and check the parameters
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'string', 'actual', false, array('actual', 'old'));
$getStart    = admFuncVariableIsValid($_GET, 'start', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('DAT_DATES'));
$getDateId   = admFuncVariableIsValid($_GET, 'id', 'numeric', 0);
$getDate     = admFuncVariableIsValid($_GET, 'date', 'numeric');
$getCalendar = admFuncVariableIsValid($_GET, 'calendar', 'string');
$getCalendarSelection = admFuncVariableIsValid($_GET, 'calendar-selection', 'boolean', $gPreferences['dates_show_calendar_select']);

if(strlen($getDate) > 0)
{
	$getDate = substr($getDate,0,4). '-'. substr($getDate,4,2). '-'. substr($getDate,6,2);
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
if(strlen($getCalendar) > 0)
{
    $gLayout['title'] = $getHeadline. ' - '. $getCalendar;
}
else
{
    $gLayout['title'] = $getHeadline;
}
if($getMode == 'old')
{
    $gLayout['title'] = $gL10n->get('DAT_PREVIOUS_DATES', ' '.$gLayout['title']);
}

if($gPreferences['enable_rss'] == 1 && $gPreferences['enable_dates_module'] == 1)
{
    $gLayout['header'] =  '<link rel="alternate" type="application/rss+xml" title="'.$gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname'). ' - '.$getHeadline).'"
        href="'.$g_root_path.'/adm_program/modules/dates/rss_dates.php?headline='.$getHeadline.'" />';
};

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo ' 
<script type="text/javascript"><!--
    $(document).ready(function() 
    {
        $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});
    }); 

    function showCalendar()
    {
        var calendar = "";
        if (document.getElementById("calendar").selectedIndex != 0)
        {
            var calendar = document.getElementById("calendar").value;
        } 
        self.location.href = "dates.php?mode='.$getMode.'&headline='.$getHeadline.'&calendar=" + calendar;
    }
//--></script>
<h1 class="moduleHeadline">'. $gLayout['title']. '</h1>';

// alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
$topNavigation = '';
$organizations = '';
$sqlConditions = '';
$sqlConditionCalendar = '';
$sqlConditionLogin = '';
$sqlOrderBy = '';
$arr_ref_orgas = $gCurrentOrganization->getReferenceOrganizations(true, true);

foreach($arr_ref_orgas as $org_id => $value)
{
    $organizations = $organizations. $org_id. ', ';
}
$organizations = $organizations. $gCurrentOrganization->getValue('org_id');

if ($gValidLogin == false)
{
    // Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
    $sqlConditions .= ' AND cat_hidden = 0 ';
}

// falls eine id fuer ein bestimmtes Datum uebergeben worden ist...(Aber nur, wenn der User die Berechtigung hat
if($getDateId > 0)
{
    $sqlConditions .= ' AND dat_id = '.$getDateId;
}
//...ansonsten alle fuer die Gruppierung passenden Termine aus der DB holen.
else
{
    if (strlen($getCalendar) > 0)
    {
        // alle Termine zu einer Kategorie anzeigen
        $sqlConditionCalendar .= ' AND cat_name   = \''. $getCalendar. '\' ';
    }
	
    // Termine an einem Tag suchen
    if(strlen($getDate) > 0)
    {
        $sqlConditions .= ' AND dat_begin <= \''.$getDate.'\'
                            AND dat_end   >  \''.$getDate.' 00:00:00\'';;
        $sqlOrderBy .= ' ORDER BY dat_begin ASC ';
    }
    //fuer alte Termine...
    elseif($getMode == 'old')
    {
        $sqlConditions .= ' AND dat_begin <  \''.DATE_NOW.'\'
                            AND dat_end   <= \''.DATE_NOW.' 00:00:00\'';
        $sqlOrderBy .= ' ORDER BY dat_begin DESC ';
    }
    //... ansonsten fuer kommende Termine
    else
    {
        $sqlConditions .= ' AND (  dat_begin >= \''.DATE_NOW.'\'
                                OR dat_end   >  \''.DATE_NOW.' 00:00:00\' )';
        $sqlOrderBy .= ' ORDER BY dat_begin ASC ';
    }
}

if($getDateId == 0)
{
    // Bedingungen fuer die Rollenfreigabe hinzufuegen
    if($gCurrentUser->getValue('usr_id') > 0)
    {
        $sqlConditionLogin = '
        AND (  dtr_rol_id IS NULL 
            OR dtr_rol_id IN (SELECT mem_rol_id 
                                FROM '.TBL_MEMBERS.' mem2
                               WHERE mem2.mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
                                 AND mem2.mem_begin  <= dat_begin
                                 AND mem2.mem_end    >= dat_end) ) ';
    }
    else
    {
        $sqlConditionLogin = ' AND dtr_rol_id IS NULL ';
    }
    
    // Gucken wieviele Datensaetze die Abfrage ermittelt kann...
    $sql = 'SELECT COUNT(DISTINCT dat_id) as count
              FROM '.TBL_DATE_ROLE.', '. TBL_DATES. ', '. TBL_CATEGORIES. '
             WHERE dat_cat_id = cat_id
               AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                   OR (   dat_global   = 1
                      AND cat_org_id IN (\''.$organizations.'\') 
                      )
                   )
               AND dat_id = dtr_dat_id
                   '.$sqlConditionLogin. $sqlConditions. $sqlConditionCalendar;

    $result = $gDb->query($sql);
    $row    = $gDb->fetch_array($result);
    $num_dates = $row['count'];
}
else
{
    $num_dates = 1;
}

// Anzahl Termine pro Seite
if($gPreferences['dates_per_page'] > 0)
{
    $dates_per_page = $gPreferences['dates_per_page'];
}
else
{
    $dates_per_page = $num_dates;
}

// nun die Termine auslesen, die angezeigt werden sollen
$sql = 'SELECT DISTINCT cat.*, dat.*, mem.mem_usr_id as member_date_role, mem.mem_leader,
               cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
               cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname
          FROM '.TBL_DATE_ROLE.' dtr, '. TBL_CATEGORIES. ' cat, '. TBL_DATES. ' dat
          LEFT JOIN '. TBL_USER_DATA .' cre_surname 
            ON cre_surname.usd_usr_id = dat_usr_id_create
           AND cre_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cre_firstname 
            ON cre_firstname.usd_usr_id = dat_usr_id_create
           AND cre_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_surname
            ON cha_surname.usd_usr_id = dat_usr_id_change
           AND cha_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_firstname
            ON cha_firstname.usd_usr_id = dat_usr_id_change
           AND cha_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_MEMBERS. ' mem
            ON mem.mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
           AND mem.mem_rol_id = dat_rol_id
           AND mem_begin <= \''.DATE_NOW.'\'
           AND mem_end    > \''.DATE_NOW.'\'
         WHERE dat_cat_id = cat_id
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               OR (   dat_global   = 1
                  AND cat_org_id IN ('.$organizations.') ))
           AND dat_id = dtr_dat_id
               '.$sqlConditionLogin.'
               '.$sqlConditions. $sqlConditionCalendar. $sqlOrderBy. '
         LIMIT '.$dates_per_page.' OFFSET '.$getStart;
$dates_result = $gDb->query($sql);


//Abfrage ob die Box angezeigt werden soll, falls nicht nur ein Termin gewählt wurde
if((($getCalendarSelection == 1) && ($getDateId == 0)) || $gCurrentUser->editDates())
{
    $topNavigation = '';

    //Neue Termine anlegen
    if($gCurrentUser->editDates())
    {
        $topNavigation .= '
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?headline='.$getHeadline.'"><img
                src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('SYS_CREATE_VAR', $getHeadline).'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?headline='.$getHeadline.'">'.$gL10n->get('SYS_CREATE_VAR', $getHeadline).'</a>
            </span>
        </li>';
    }
    if(($getCalendarSelection == 1) && ($getDateId == 0))   
    {
        // Combobox mit allen Kalendern anzeigen, denen auch Termine zugeordnet sind
        $sql = 'SELECT DISTINCT cat_name, cat_sequence
                FROM '. TBL_CATEGORIES. ', '. TBL_DATES. ' dat
                WHERE cat_type   = \'DAT\'
                    AND dat_cat_id = cat_id 
                    AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                        OR (   dat_global   = 1
                            AND cat_org_id IN ('.$organizations.')
                        )
                    )
                    '.$sqlConditions;
        if($gValidLogin == false)
        {
          $sql .= ' AND cat_hidden = 0 ';
        }
        $sql .= ' ORDER BY cat_sequence ASC ';
        $result = $gDb->query($sql);
        
        if($gDb->num_rows($result) > 1)
        {
            $topNavigation .= '<li>'.$gL10n->get('DAT_CALENDAR').':&nbsp;&nbsp;
            <select size="1" id="calendar" onchange="showCalendar()">
                <option value="0" ';
                if(strlen($getCalendar) == 0)
                {
                    $topNavigation .= ' selected="selected" ';
                }
                $topNavigation .= '>'.$gL10n->get('SYS_ALL').'</option>';
        
                while($row = $gDb->fetch_object($result))
                {
                    $topNavigation .= '<option value="'. urlencode($row->cat_name). '"';
                    if($getCalendar == $row->cat_name)
                    {
                        $topNavigation .= ' selected="selected" ';
                    }
                    $topNavigation .= '>'.$row->cat_name.'</option>';
                }
            $topNavigation .=  '</select>';
            if($gCurrentUser->editDates())
            {
                $topNavigation .= '<a  class="iconLink" href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=DAT&amp;title='.$gL10n->get('DAT_CALENDAR').'"><img
                     src="'. THEME_PATH. '/icons/options.png" alt="'.$gL10n->get('DAT_MANAGE_CALENDARS').'" title="'.$gL10n->get('DAT_MANAGE_CALENDARS').'" /></a>';
            }
            $topNavigation .= '</li>';
        }
        elseif($gCurrentUser->editDates())
        {
            $topNavigation .= '
            <li><span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=DAT&amp;title='.$gL10n->get('DAT_CALENDAR').'"><img
                    src="'. THEME_PATH. '/icons/application_double.png" alt="'.$gL10n->get('DAT_MANAGE_CALENDARS').'" title="'.$gL10n->get('DAT_MANAGE_CALENDARS').'"/></a>
                <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=DAT&amp;title='.$gL10n->get('DAT_CALENDAR').'">'.$gL10n->get('DAT_MANAGE_CALENDARS').'</a>
            </span></li>';
        }
    }
    
    if(strlen($topNavigation) > 0)
    {
        echo '<ul class="iconTextLinkList">';
        echo $topNavigation;
        echo '</ul>';
    }
}

if($gDb->num_rows($dates_result) == 0)
{
    // Keine Termine gefunden
    if($getDateId > 0)
    {
        echo '<p>'.$gL10n->get('SYS_NO_ENTRY').'</p>';
    }
    else
    {
        echo '<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>';
    }
}
else
{
    $date = new TableDate($gDb);

    // Termine auflisten
    while($row = $gDb->fetch_array($dates_result))
    {
        // GB-Objekt initialisieren und neuen DS uebergeben
        //$date->clear();
        //$date->setArray($row);
        $date->readData($row['dat_id']);

        echo '
        <div class="boxLayout" id="dat_'.$date->getValue('dat_id').'">
            <div class="boxHead">
                <div class="boxHeadLeft">
                    <img src="'. THEME_PATH. '/icons/dates.png" alt="'. $date->getValue('dat_headline'). '" />'
                    . $date->getValue('dat_begin', $gPreferences['system_date']);
                    if($date->getValue('dat_begin', $gPreferences['system_date']) != $date->getValue('dat_end', $gPreferences['system_date']))
                    {
                        echo ' - '. $date->getValue('dat_end', $gPreferences['system_date']);
                    }
                    echo ' ' . $date->getValue('dat_headline'). '
                </div>
                <div class="boxHeadRight">
                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?dat_id='. $date->getValue('dat_id'). '&amp;mode=6"><img
                        src="'. THEME_PATH. '/icons/database_out.png" alt="'.$gL10n->get('DAT_EXPORT_ICAL').'" title="'.$gL10n->get('DAT_EXPORT_ICAL').'" /></a>';

                    // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                    if ($gCurrentUser->editDates())
                    {
                        if($date->editRight() == true)
                        {
                            echo '
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='. $date->getValue('dat_id'). '&amp;copy=1&amp;headline='.$getHeadline.'"><img
                                src="'. THEME_PATH. '/icons/application_double.png" alt="'.$gL10n->get('SYS_COPY').'" title="'.$gL10n->get('SYS_COPY').'" /></a>
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='. $date->getValue('dat_id'). '&amp;headline='.$getHeadline.'"><img
                                src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';
                        }

                        // Loeschen darf man nur Termine der eigenen Gliedgemeinschaft
                        if($date->getValue('cat_org_id') == $gCurrentOrganization->getValue('org_id'))
                        {
                            echo '
                            <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=dat&amp;element_id=dat_'.
                                $date->getValue('dat_id').'&amp;name='.urlencode($date->getValue('dat_begin', $gPreferences['system_date']).' '.$date->getValue('dat_headline')).'&amp;database_id='.$date->getValue('dat_id').'"><img 
                                src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
                        }
                    }
                echo'</div>
            </div>

            <div class="boxBody">';
                $dateElements = array();
                $firstElement = true;

                if ($date->getValue('dat_all_day') == 0)
                {
                    // Beginn in Ausgabe-Array schreiben
                    $dateElements[] = array($gL10n->get('SYS_START'), '<strong>'. $date->getValue('dat_begin', $gPreferences['system_time']). '</strong> '.$gL10n->get('SYS_CLOCK'));
                    // Ende in Ausgabe-Array schreiben
                    $dateElements[] = array($gL10n->get('SYS_END'), '<strong>'. $date->getValue('dat_end', $gPreferences['system_time']). '</strong> '.$gL10n->get('SYS_CLOCK'));
                }
                // Kalender in Ausgabe-Array schreiben
                $dateElements[] = array($gL10n->get('DAT_CALENDAR'), '<strong>'. $date->getValue('cat_name'). '</strong>');

                if (strlen($date->getValue('dat_location')) > 0)
                {
                    // Karte- und Routenlink anzeigen, sobald 2 Woerter vorhanden sind,
                    // die jeweils laenger als 3 Zeichen sind
                    $map_info_count = 0;
                    foreach(preg_split('/[,; ]/', $date->getValue('dat_location')) as $key => $value)
                    {
                        if(strlen($value) > 3)
                        {
                            $map_info_count++;
                        }
                    }

                    if($gPreferences['dates_show_map_link'] == true
                        && $map_info_count > 1)
                    {
                        // Google-Maps-Link fuer den Ort zusammenbauen
                        $location_url = 'http://maps.google.com/?q='. $date->getValue('dat_location');
                        if(strlen($date->getValue('dat_country')) > 0)
                        {
                            // Zusammen mit dem Land koennen Orte von Google besser gefunden werden
                            $location_url .= ',%20'. $date->getValue('dat_country');
                        }
                        $locationHtml = '<a href="'. $location_url. '" target="_blank" title="'.$gL10n->get('DAT_SHOW_ON_MAP').'"/><strong>'.$date->getValue("dat_location").'</strong></a>';

                        // bei gueltigem Login und genuegend Adressdaten auch noch Route anbieten
                        if($gValidLogin && strlen($gCurrentUser->getValue('ADDRESS')) > 0
                        && (  strlen($gCurrentUser->getValue('POSTCODE'))  > 0 || strlen($gCurrentUser->getValue('CITY'))  > 0 ))
                        {
                            $route_url = 'http://maps.google.com/?f=d&amp;saddr='. urlencode($gCurrentUser->getValue('ADDRESS'));
                            if(strlen($gCurrentUser->getValue('POSTCODE'))  > 0)
                            {
                                $route_url .= ',%20'. urlencode($gCurrentUser->getValue('POSTCODE'));
                            }
                            if(strlen($gCurrentUser->getValue('CITY'))  > 0)
                            {
                                $route_url .= ',%20'. urlencode($gCurrentUser->getValue('CITY'));
                            }
                            if(strlen($gCurrentUser->getValue('COUNTRY'))  > 0)
                            {
                                $route_url .= ',%20'. urlencode($gCurrentUser->getValue('COUNTRY'));
                            }

                            $route_url .= '&amp;daddr='. urlencode($date->getValue('dat_location'));
                            if(strlen($date->getValue('dat_country')) > 0)
                            {
                                // Zusammen mit dem Land koennen Orte von Google besser gefunden werden
                                $route_url .= ',%20'. $date->getValue('dat_country');
                            }
                            $locationHtml .= '
                                <span class="iconTextLink">&nbsp;&nbsp;<a href="'. $route_url. '" target="_blank">
                                    <img src="'. THEME_PATH. '/icons/map.png" alt="'.$gL10n->get('SYS_SHOW_ROUTE').'" title="'.$gL10n->get('SYS_SHOW_ROUTE').'"/></a>
                                </span>';
                        }

                        // falls eingestellt noch den entsprechenden Raum ausgeben
                        if($date->getValue('dat_room_id') > 0)
                        {
                            $room = new TableRooms($gDb, $date->getValue('dat_room_id'));
                            $roomLink = $g_root_path. '/adm_program/system/msg_window.php?message_id=room_detail&amp;message_title=DAT_ROOM_INFORMATIONS&amp;message_var1='.$date->getValue('dat_room_id').'&amp;inline=true';
                            $locationHtml .= ' <strong>(<a rel="colorboxHelp" href="'.$roomLink.'">'.$room->getValue('room_name').'</a>)</strong>';
                        }
                    } 
                    else
                    {
                        $locationHtml = '<strong>'. $date->getValue('dat_location'). '</strong>';
                    }

                    $dateElements[] = array($gL10n->get('DAT_LOCATION'), $locationHtml);
                }
                elseif($date->getValue('dat_room_id') > 0)
                {
                    // falls eingestellt noch den entsprechenden Raum ausgeben
                    $room = new TableRooms($gDb, $date->getValue('dat_room_id'));
                    $roomLink = $g_root_path. '/adm_program/system/msg_window.php?message_id=room_detail&amp;message_title=DAT_ROOM_INFORMATIONS&amp;message_var1='.$date->getValue('dat_room_id').'&amp;inline=true';
                    $locationHtml = '<strong><a rel="colorboxHelp" href="'.$roomLink.'">'.$room->getValue('room_name').'</a></strong>';
                    $dateElements[] = array($gL10n->get('DAT_LOCATION'), $locationHtml);
                }

                // Teilnehmeranzeige in Ausgabe-Array schreiben
                if($date->getValue('dat_rol_id') > 0)
                {
                    if($date->getValue('dat_max_members')!=0)
                    {
                        $sql = 'SELECT DISTINCT mem_usr_id 
                                  FROM '.TBL_MEMBERS.' 
                                 WHERE mem_rol_id="'.$date->getValue('dat_rol_id').'"';
                        $result = $gDb->query($sql);
                        $row_count = $gDb->num_rows($result);
                                    
                        $participantsHtml = '<strong>'.$row_count.'</strong>';
                    }
                    else 
                    {
                        $participantsHtml = '<strong>'.$gL10n->get('SYS_UNLIMITED').'</strong>';
                    }
                    $dateElements[] = array($gL10n->get('SYS_PARTICIPANTS'), $participantsHtml);
                }

                // Ausgabe der einzelnen Elemente 
                // immer 2 nebeneinander und dann ein Zeilenwechsel
                echo '<table style="width: 100%; border-width: 0px;">';
                foreach($dateElements as $element)
                {
                    if($firstElement)
                    {
                        echo '<tr>';
                    }
                
                    echo '<td style="width: 15%">'.$element[0].':</td>
                    <td style="width: 35%">'.$element[1].'</td>';
                
                    if($firstElement)
                    {
                        $firstElement = false;
                    }
                    else
                    {
                        echo '</tr>';
                        $firstElement = true;
                    }
                }
                echo '</table>';

                // Beschreibung anzeigen
                echo '<div class="date_description" style="clear: left;">'.$date->getValue('dat_description').'</div>';

                if($date->getValue('dat_rol_id') > 0)
                {
                    // Link zum An- und Abmelden zu Terminen in Ausgabe-Array schreiben
                    
                    if($date->getValue('dat_rol_id') > 0)
                    {
                        if($row['member_date_role'] > 0)
                        {
                            $registrationHtml = '<span class="iconTextLink">
                                    <a href="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?mode=4&amp;dat_id='.$date->getValue('dat_id').'"><img 
                                        src="'. THEME_PATH. '/icons/no.png" alt="'.$gL10n->get('DAT_CANCEL').'" /></a>
                                    <a href="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?mode=4&amp;dat_id='.$date->getValue('dat_id').'">'.$gL10n->get('DAT_CANCEL').'</a>
                                </span>';
                        }
                        else
                        {
                            $available_signin = true;
                            $non_available_rols = array();
                            if($date->getValue('dat_max_members'))
                            {
                                // Teilnehmerbegrenzung allgemein
                                $sql = 'SELECT DISTINCT mem_usr_id FROM '.TBL_MEMBERS.'
                                         WHERE mem_rol_id = \''.$date->getValue('dat_rol_id').'\' 
										   AND mem_leader = 0';
                                $res_num = $gDb->query($sql);
                                $row_num = $gDb->num_rows($res_num);
                                if($row_num >= $date->getValue('dat_max_members'))
                                {
                                    $available_signin = false;
                                }
                            }

                            if($available_signin)
                            {
                                $buttonURL = $g_root_path.'/adm_program/modules/dates/dates_function.php?mode=3&amp;dat_id='.$date->getValue('dat_id');

                                $registrationHtml = '<span class="iconTextLink">
                                    <a href="'.$buttonURL.'"><img src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('DAT_PARTICIPATE_AT_DATE').'" /></a>
                                    <a href="'.$buttonURL.'">'.$gL10n->get('DAT_PARTICIPATE_AT_DATE').'</a>
                                </span>';
                            }
                            else
                            {
                                $registrationHtml = $gL10n->get('DAT_REGISTRATION_NOT_POSSIBLE');
                            }
                        }
                        
						// Link mit Teilnehmerliste anzeigen
                        if($gValidLogin)
                        {
                            $registrationHtml .= '&nbsp;
                            <span class="iconTextLink">
                                <a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='.$date->getValue('dat_rol_id').'"><img 
                                    src="'. THEME_PATH. '/icons/list.png" alt="'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'" /></a>
                                 <a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='.$date->getValue('dat_rol_id').'">'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'</a>
                            </span>';
                        }

						// Link mit Zuordnung neuer Teilnehmer
                        if($row['mem_leader'] == 1)
                        {
                            $registrationHtml .= '&nbsp;
                            <span class="iconTextLink">
                                <a href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$date->getValue('dat_rol_id').'"><img 
                                    src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'" /></a>
                                 <a href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$date->getValue('dat_rol_id').'">'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'</a>
                            </span>';
                        }

                        echo '<div>'.$registrationHtml.'</div>';
                    }
                }

                // Erstell-/ Änderungsdaten anzeigen
                echo '<div class="editInformation">'.
                    $gL10n->get('SYS_CREATED_BY', $row['create_firstname']. ' '. $row['create_surname'], $date->getValue('dat_timestamp_create'));

                    if($date->getValue('dat_usr_id_change') > 0)
                    {
                        echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $row['change_firstname']. ' '. $row['change_surname'], $date->getValue('dat_timestamp_change'));
                    }
                echo '</div>
            </div>
        </div>';
    }  // Ende While-Schleife
}

// Navigation mit Vor- und Zurueck-Buttons
$base_url = $g_root_path.'/adm_program/modules/dates/dates.php?mode='.$getMode.'&headline='.$getHeadline.'&calendar='.$getCalendar;
echo admFuncGeneratePagination($base_url, $num_dates, $dates_per_page, $getStart, TRUE);

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>