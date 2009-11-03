<?php
/******************************************************************************
 * Termine auflisten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
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

// pruefen ob das Modul ueberhaupt aktiviert ist
if($g_preferences['enable_dates_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show('module_disabled');
}
elseif($g_preferences['enable_dates_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require('../../system/login_valid.php');
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_mode     = 'actual';
$req_start    = 0;
$req_headline = 'Termine';
$req_id       = 0;
$sql_datum    = '';
$req_calendar = '';

// Uebergabevariablen pruefen

//Kontrolle ob nur ein Kalender angezeigt werden soll
if(isset($_GET['calendar']))
{
    $req_calendar = $_GET['calendar'];
}

if(isset($_GET['mode']))
{
    if($_GET['mode'] != 'actual' && $_GET['mode'] != 'old')
    {
        $g_message->show('invalid');
    }
    $req_mode = $_GET['mode'];
}

if(isset($_GET['start']))
{
    if(is_numeric($_GET['start']) == false)
    {
        $g_message->show('invalid');
    }
    $req_start = $_GET['start'];
}

if(isset($_GET['headline']))
{
    $req_headline = strStripTags($_GET['headline']);
}

if(isset($_GET['id']))
{
    if(is_numeric($_GET['id']) == false)
    {
        $g_message->show('invalid');
    }
    $req_id = $_GET['id'];
}

if(array_key_exists('date', $_GET))
{
    if(is_numeric($_GET['date']) == false)
    {
        $g_message->show('invalid');
    }
    else
    {
        $sql_datum = substr($_GET['date'],0,4). '-'. substr($_GET['date'],4,2). '-'. substr($_GET['date'],6,2);
    }
}

// Prüfen ob Box angezeigt werden soll und setzten des Flags
if ($g_preferences['dates_show_calendar_select'] == 1) {
   $dates_show_calendar_select = 1;
} else {
   $dates_show_calendar_select = 0;
}
if(isset($_GET['calendar-selection']) != false)
{
    if($_GET['calendar-selection'] == 1)
    {
        $dates_show_calendar_select = 1;
    }
    else if ($_GET['calendar-selection'] == 0)
    {
        $dates_show_calendar_select = 0;
    }
}

unset($_SESSION['dates_request']);
$act_date = date('Y-m-d', time());
// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
if(strlen($req_calendar) > 0)
{
    $g_layout['title'] = $req_headline. ' - '. $req_calendar;
}
else
{
    $g_layout['title'] = $req_headline;
}
if($req_mode == 'old')
{
    $g_layout['title'] = 'Vergangene '. $g_layout['title'];
}
$g_layout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/delete.js"></script>';

if($g_preferences['enable_rss'] == 1 && $g_preferences['enable_dates_module'] == 1)
{
    $g_layout['header'] .=  '<link type="application/rss+xml" rel="alternate" title="'. $g_current_organization->getValue('org_longname'). ' - Termine"
        href="'.$g_root_path.'/adm_program/modules/dates/rss_dates.php" />';
};

require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo ' 
<script type="text/javascript"><!--
    function showCalendar()
    {
        var calendar = "";
        if (document.getElementById("calendar").selectedIndex != 0)
        {
            var calendar = document.getElementById("calendar").value;
        } 
        self.location.href = "dates.php?mode='.$req_mode.'&headline='.$req_headline.'&calendar=" + calendar;
    }
//--></script>
<h1 class="moduleHeadline">'. $g_layout['title']. '</h1>';

// alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
$organizations = '';
$hidden = '';
$conditions = '';
$condition_calendar = '';
$order_by = '';
$arr_ref_orgas = $g_current_organization->getReferenceOrganizations(true, true);

foreach($arr_ref_orgas as $org_id => $value)
{
    $organizations = $organizations. $org_id. ', ';
}
$organizations = $organizations. $g_current_organization->getValue('org_id');

if ($g_valid_login == false)
{
    // Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
    $hidden = ' AND cat_hidden = 0 ';
}

// falls eine id fuer ein bestimmtes Datum uebergeben worden ist...(Aber nur, wenn der User die Berechtigung hat
if($req_id > 0)
{
    $conditions .= ' AND dat_id = '.$req_id.' '.$hidden;
}
//...ansonsten alle fuer die Gruppierung passenden Termine aus der DB holen.
else
{
    if (strlen($req_calendar) > 0)
    {
        // alle Termine zu einer Kategorie anzeigen
        $condition_calendar .= ' AND cat_name   = "'. $req_calendar. '" ';
    }

    // Termine an einem Tag suchen
    if(strlen($sql_datum) > 0)
    {
        $conditions .= ' AND DATE_FORMAT(dat_begin, "%Y-%m-%d")       <= "'.$sql_datum.'"
                         AND DATE_FORMAT(dat_end, "%Y-%m-%d %H:%i:%s") > "'.$sql_datum.' 00:00:00"
                       '.$hidden;
        $order_by .= ' ORDER BY dat_begin ASC ';
    }
    //fuer alte Termine...
    elseif($req_mode == 'old')
    {
        $conditions .= ' AND DATE_FORMAT(dat_begin, "%Y-%m-%d") < "'.$act_date.'"
                         AND DATE_FORMAT(dat_end, "%Y-%m-%d")   < "'.$act_date.'"
                       '.$hidden;
        $order_by .= ' ORDER BY dat_begin DESC ';
    }
    //... ansonsten fuer kommende Termine
    else
    {
        $conditions .= ' AND (  DATE_FORMAT(dat_begin, "%Y-%m-%d")       >= "'.$act_date.'"
                             OR DATE_FORMAT(dat_end, "%Y-%m-%d %H:%i:%s") > "'.$act_date.' 00:00:00" )
                       '.$hidden;
        $order_by .= ' ORDER BY dat_begin ASC ';
    }

}

if($req_id == 0)
{
    // Bedingungen fuer die Rollenfreigabe hinzufuegen
    if($g_current_user->getValue('usr_id') > 0)
    {
        $login_sql = 'AND ( dtr_rol_id IS NULL OR dtr_rol_id IN (SELECT mem_rol_id FROM '.TBL_MEMBERS.' WHERE mem_usr_id = '.$g_current_user->getValue('usr_id').') )';
    }
    else
    {
        $login_sql = 'AND dtr_rol_id IS NULL';
    }
    
    // Gucken wieviele Datensaetze die Abfrage ermittelt kann...
    $sql = 'SELECT COUNT(DISTINCT dat_id) as count
            FROM '.TBL_DATE_ROLE.', '. TBL_DATES. ', '. TBL_CATEGORIES. '
            WHERE dat_cat_id = cat_id
                AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
                    OR (   dat_global   = 1
                        AND cat_org_id IN ("'.$organizations.'") 
                    )
                )
                AND dat_id = dtr_dat_id
                '.$login_sql.'
                '.$conditions. $condition_calendar;

    $result = $g_db->query($sql);
    $row    = $g_db->fetch_array($result);
    $num_dates = $row['count'];
}
else
{
    $num_dates = 1;
}

// Anzahl Ankuendigungen pro Seite
if($g_preferences['dates_per_page'] > 0)
{
    $dates_per_page = $g_preferences['dates_per_page'];
}
else
{
    $dates_per_page = $num_dates;
}

// nun die Ankuendigungen auslesen, die angezeigt werden sollen
$sql = 'SELECT DISTINCT cat.*, dat.*, 
            cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
            cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname
        FROM '.TBL_DATE_ROLE.' dtr, '. TBL_CATEGORIES. ' cat, '. TBL_DATES. ' dat
            LEFT JOIN '. TBL_USER_DATA .' cre_surname 
                ON cre_surname.usd_usr_id = dat_usr_id_create
            AND cre_surname.usd_usf_id = '.$g_current_user->getProperty('Nachname', 'usf_id').'
            LEFT JOIN '. TBL_USER_DATA .' cre_firstname 
                ON cre_firstname.usd_usr_id = dat_usr_id_create
            AND cre_firstname.usd_usf_id = '.$g_current_user->getProperty('Vorname', 'usf_id').'
            LEFT JOIN '. TBL_USER_DATA .' cha_surname
                ON cha_surname.usd_usr_id = dat_usr_id_change
            AND cha_surname.usd_usf_id = '.$g_current_user->getProperty('Nachname', 'usf_id').'
            LEFT JOIN '. TBL_USER_DATA .' cha_firstname
                ON cha_firstname.usd_usr_id = dat_usr_id_change
            AND cha_firstname.usd_usf_id = '.$g_current_user->getProperty('Vorname', 'usf_id').'
        WHERE dat_cat_id = cat_id
            AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
            OR (   dat_global   = 1
            AND cat_org_id IN ('.$organizations.') ))
            AND dat_id = dtr_dat_id
            '.$login_sql.'
            '.$conditions. $condition_calendar. $order_by. '
        LIMIT '.$req_start.', '.$dates_per_page;
$dates_result = $g_db->query($sql);


//Abfrage ob die Box angezeigt werden soll, falls nicht nur ein Termin gewählt wurde
if((($dates_show_calendar_select == 1) && ($req_id == 0)) || $g_current_user->editDates())
{
    //Neue Termine anlegen
    if($g_current_user->editDates())
    {
        $topNavigation .= '
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?headline='.$req_headline.'"><img
                src="'. THEME_PATH. '/icons/add.png" alt="Termin anlegen" title="Termin anlegen"/></a>
                <a href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?headline='.$req_headline.'">Termin anlegen</a>
            </span>
        </li>';
    }
    if(($dates_show_calendar_select == 1) && ($req_id == 0))   
    {
        // Combobox mit allen Kalendern anzeigen, denen auch Termine zugeordnet sind
        $sql = 'SELECT DISTINCT cat_name
                FROM '. TBL_CATEGORIES. ', '. TBL_DATES. ' dat
                WHERE cat_type   = "DAT"
                    AND dat_cat_id = cat_id 
                    AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
                        OR (   dat_global   = 1
                            AND cat_org_id IN ('.$organizations.')
                        )
                    )
                    '.$conditions;
        if($g_valid_login == false)
        {
          $sql .= ' AND cat_hidden = 0 ';
        }
        $sql .= ' ORDER BY cat_sequence ASC ';
        $result = $g_db->query($sql);
        
        if($g_db->num_rows($result) > 1)
        {
            $topNavigation .= '<li>Kalender:&nbsp;&nbsp;
            <select size="1" id="calendar" onchange="showCalendar()">
                <option value="Alle" ';
                if(strlen($req_calendar) == 0)
                {
                    $topNavigation .= ' selected="selected" ';
                }
                $topNavigation .= '>Alle</option>';
        
                while($row = $g_db->fetch_object($result))
                {
                    $topNavigation .= '<option value="'. urlencode($row->cat_name). '"';
                    if($req_calendar == $row->cat_name)
                    {
                        $topNavigation .= ' selected="selected" ';
                    }
                    $topNavigation .= '>'.$row->cat_name.'</option>';
                }
            $topNavigation .=  '</select>';
            if($g_current_user->editDates())
            {
                $topNavigation .= '<a  class="iconLink" href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=DAT&amp;title=Kalender"><img
                     src="'. THEME_PATH. '/icons/options.png" alt="Kalender pflegen" title="Kalender pflegen" /></a>';
            }
            $topNavigation .= '</li>';
        }
        elseif($g_current_user->editDates())
        {
            $topNavigation .= '
            <li><span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=DAT&amp;title=Kalender"><img
                    src="'. THEME_PATH. '/icons/application_double.png" alt="Kalender pflegen" title="Kalender pflegen"/></a>
                <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=DAT&amp;title=Kalender">Kalender pflegen</a>
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

if($g_db->num_rows($dates_result) == 0)
{
    // Keine Termine gefunden
    if($req_id > 0)
    {
        echo '<p>Der angeforderte Eintrag existiert nicht (mehr) in der Datenbank.</p>';
    }
    else
    {
        echo '<p>Es sind keine Einträge vorhanden.</p>';
    }
}
else
{
    // Rollen von Benutzer holen, mit denen man sich zu den Terminen anmelden kann
    if($g_current_user->getValue('usr_id'))
    {
        $user_roles_base = array();
        $sql = 'SELECT *
                  FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_ORGANIZATIONS. '
                 WHERE mem_rol_id = rol_id
                   AND mem_begin <= "'.DATE_NOW.'"
                   AND mem_end    > "'.DATE_NOW.'"
                   AND mem_usr_id = "'.$g_current_user->getValue('usr_id').'"
                   AND rol_valid  = 1
                   AND rol_cat_id = cat_id
                   AND cat_org_id = org_id
                   AND org_id     = "'. $g_current_organization->getValue('org_id'). '"
                   AND rol_id NOT IN(SELECT rol_id FROM '.TBL_ROLES.', '.TBL_DATES.' WHERE rol_id = dat_rol_id)
                 ORDER BY rol_id, rol_name';
        $result_role = $g_db->query($sql);
        while($row = $g_db->fetch_array($result_role))
        {
            $user_roles_base[$row['rol_id']] = $row['rol_name'];
        }
        if(!count($user_roles_base)) $user_roles_base = array('0' => 'Gast');
    }
    else
    {
        $user_roles_base = array('0' => 'Gast');
    }
    

    $date = new TableDate($g_db);

    // Termine auflisten
    while($row = $g_db->fetch_array($dates_result))
    {
        // GB-Objekt initialisieren und neuen DS uebergeben
        $date->clear();
        $date->setArray($row);
        $date->readData($date->getValue('dat_id'));
        
        // HTML-code für Teilnehmeranzeige generieren
        $participants_html = '';
        if(($date->getValue('dat_rol_id'))!=null)
        {
            if($date->getValue('dat_max_members')!=0)
            {
                $sql = 'SELECT DISTINCT mem_usr_id FROM '.TBL_MEMBERS.' WHERE mem_rol_id="'.$date->getValue('dat_rol_id').'"';
                $result = $g_db->query($sql);
                $row2 = $g_db->num_rows($result);
                
                $sql = 'SELECT mem_id FROM '.TBL_MEMBERS.' WHERE mem_rol_id ="'.$date->getValue('dat_rol_id').'" AND mem_leader = 1';
                $result = $g_db->query($sql);
                $row3 = $g_db->num_rows($result);
                            
                $participants_html = '
                    <tr>
                        <td>Teilnehmer:</td>
                        <td>
                            <strong>'.$row2.'</strong> (davon '.$row3. ((intval($row3)==1)?' Organisator':' Organisatoren') . ')
                        </td>
                    </tr>';
            }
            else 
            {
                $participants_html = '
                    <tr>
                        <td>Teilnehmer:</td>
                        <td><strong>unbegrenzt</strong></td>
                    </tr>';
            }
        }

        echo '
        <div class="boxLayout" id="dat_'.$date->getValue('dat_id').'">
            <div class="boxHead">
                <div class="boxHeadLeft">
                    <img src="'. THEME_PATH. '/icons/dates.png" alt="'. $date->getValue('dat_headline'). '" />'
                    . mysqldatetime('d.m.y', $date->getValue('dat_begin'));
                    if(mysqldatetime('d.m.y', $date->getValue('dat_begin')) != mysqldatetime('d.m.y', $date->getValue('dat_end')))
                    {
                        echo ' - '. mysqldatetime('d.m.y', $date->getValue('dat_end'));
                    }
                    echo ' ' . $date->getValue('dat_headline'). '
                </div>
                <div class="boxHeadRight">';
                    $sql = 'SELECT mem_id FROM '.TBL_MEMBERS.' WHERE mem_usr_id="'.$g_current_user->getValue('usr_id').'" AND mem_rol_id = "'.$date->getValue('dat_rol_id').'" AND mem_leader=1';
                    $result = $g_db->query($sql);
                    $row2 = $g_db->num_rows($result);
                    
                    if($row2>0) 
                    {
                        echo ' <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='.$date->getValue('dat_rol_id').'"  ><img src="'. THEME_PATH. '/icons/list.png" alt="Mitglieder" title="Mitglieder" /></a>';
                    }
                    echo'  <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?dat_id='. $date->getValue('dat_id'). '&amp;mode=4"><img
                    src="'. THEME_PATH. '/icons/database_out.png" alt="Exportieren (iCal)" title="Exportieren (iCal)" /></a>';

                    // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                    if ($g_current_user->editDates())
                    {
                        if($date->editRight() == true)
                        {
                            echo '
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='. $date->getValue('dat_id'). '&amp;headline='.$req_headline.'"><img
                                src="'. THEME_PATH. '/icons/edit.png" alt="Bearbeiten" title="Bearbeiten" /></a>';
                        }

                        // Loeschen darf man nur Termine der eigenen Gliedgemeinschaft
                        if($date->getValue('cat_org_id') == $g_current_organization->getValue('org_id'))
                        {
                            echo '
                            <a class="iconLink" href="#" onclick="deleteObject(\'dat\', \'dat_'.$date->getValue('dat_id').'\','.$date->getValue('dat_id').',\''.$date->getValue('dat_headline').'\')"><img
                                src="'. THEME_PATH. '/icons/delete.png" alt="Löschen" title="Löschen" /></a>';
                        }
                    }
                echo'</div>
            </div>

            <div class="boxBody">';
                // Uhrzeit und Ort anzeigen, falls vorhanden
                if ($date->getValue("dat_all_day") == 0 || strlen($date->getValue("dat_location")) > 0)
                {
                    echo '<div class="date_info_block">';
                    $margin_left_location = "0";
                    if ($date->getValue("dat_all_day") == 0)
                    {
                        echo '
                        <table style="float:left; width: 250px;">
                            <tr>
                                <td>Beginn:</td>
                                <td><strong>'. mysqldatetime('h:i', $date->getValue('dat_begin')). '</strong> Uhr</td>
                            </tr>
                            <tr>
                                <td>Ende:</td>
                                <td><strong>'. mysqldatetime('h:i', $date->getValue('dat_end')). '</strong> Uhr</td>
                            </tr>';
                        echo $participants_html;
                        echo '</table>';
                        $margin_left_location = '40';
                    }

                    if (strlen($date->getValue('dat_location')) > 0)
                    {
                        echo '
                        <table style="padding-left: '. $margin_left_location. 'px;">
                            <tr>
                                <td>Kalender:</td>
                                <td><strong>'. $date->getValue("cat_name"). '</strong></td>
                            </tr>
                            <tr>
                                <td>Ort:</td>
                                <td>';
                                    // Karte- und Routenlink anzeigen, sobald 2 Woerter vorhanden sind,
                                    // die jeweils laenger als 3 Zeichen sind
                                    $map_info_count = 0;
                                    foreach(split('[,; ]', $date->getValue('dat_location')) as $key => $value)
                                    {
                                        if(strlen($value) > 3)
                                        {
                                            $map_info_count++;
                                        }
                                    }

                                    if($g_preferences['dates_show_map_link'] == true
                                        && $map_info_count > 1)
                                    {
                                        // Google-Maps-Link fuer den Ort zusammenbauen
                                        $location_url = 'http://maps.google.com/?q='. $date->getValue('dat_location');
                                        if(strlen($date->getValue('dat_country')) > 0)
                                        {
                                            // Zusammen mit dem Land koennen Orte von Google besser gefunden werden
                                            $location_url .= ',%20'. $date->getValue('dat_country');
                                        }
                                        echo '<a href="'. $location_url. '" target="_blank" title="Auf Karte zeigen"/><strong>'.$date->getValue("dat_location").'</strong></a>';

                                        // bei gueltigem Login und genuegend Adressdaten auch noch Route anbieten
                                        if($g_valid_login && strlen($g_current_user->getValue("Adresse")) > 0
                                            && (  strlen($g_current_user->getValue("PLZ"))  > 0 || strlen($g_current_user->getValue("Ort"))  > 0 ))
                                        {
                                            $route_url = 'http://maps.google.com/?f=d&amp;saddr='. urlencode($g_current_user->getValue('Adresse'));
                                            if(strlen($g_current_user->getValue('PLZ'))  > 0)
                                            {
                                                $route_url .= ',%20'. urlencode($g_current_user->getValue('PLZ'));
                                            }
                                            if(strlen($g_current_user->getValue('Ort'))  > 0)
                                            {
                                                $route_url .= ',%20'. urlencode($g_current_user->getValue('Ort'));
                                            }
                                            if(strlen($g_current_user->getValue('Land'))  > 0)
                                            {
                                                $route_url .= ',%20'. urlencode($g_current_user->getValue('Land'));
                                            }

                                            $route_url .= '&amp;daddr='. urlencode($date->getValue('dat_location'));
                                            if(strlen($date->getValue('dat_country')) > 0)
                                            {
                                                // Zusammen mit dem Land koennen Orte von Google besser gefunden werden
                                                $route_url .= ',%20'. $date->getValue('dat_country');
                                            }
                                            echo '
                                                <span class="iconTextLink">&nbsp;&nbsp;<a href="'. $route_url. '" target="_blank">
                                                    <img src="'. THEME_PATH. '/icons/map.png" alt="Route anzeigen" title="Route anzeigen"/></a>
                                                </span>';
                                        }
                                    } 
                                    else
                                    {
                                        
                                        echo '<strong>'. $date->getValue('dat_location'). '</strong>';
                                    }
                                    if($date->getValue('dat_room_id')>0)
                                    {
                                        $room = new TableRooms($g_db);
                                        $room->readData($date->getValue('dat_room_id'));
                                        $room_name = $room->getValue('room_name');
                                        echo '<strong> (<a class="thickbox" href="'. $g_root_path. '/adm_program/system/msg_window.php?err_code=room_detail&amp;room_id='.$date->getValue('dat_room_id').'&amp;window=true&amp;KeepThis=true&amp;TB_iframe=true&amp;height=300&amp;width=580&amp;modal=true">'.$room_name.'</a>)</strong>';
                                    }
                                    echo '
                                </td>
                            </tr>';
                        
                        if ($date->getValue("dat_all_day") != 0)
                        {
                            echo $participants_html;
                        }
                        echo' </table>';
                    }
                    else 
                    {
                        echo '<table style="padding-left: '. $margin_left_location. 'px;">
                            <tr>
                                <td>Kalender:</td>
                                <td><strong>'. $date->getValue('cat_name'). '</strong></td>
                            </tr>';
                        if($date->getValue('dat_room_id')>0)
                        {
                            $room = new TableRooms($g_db);
                            $room->readData($date->getValue('dat_room_id'));
                            $room_name = $room->getValue('room_name');
                            echo '
                            <tr>
                                <td>Ort:</td>
                                <td>
                                    <strong><a class="thickbox" href="'. $g_root_path. '/adm_program/system/msg_window.php?err_code=room_detail&amp;room_id='.$date->getValue('dat_room_id').'&amp;window=true&amp;KeepThis=true&amp;TB_iframe=true&amp;height=300&amp;width=580&amp;modal=true">'.$room_name.'</a></strong>
                                </td>
                            </tr>';
                            if ($date->getValue("dat_all_day") != 0)
                            {
                                echo $participants_html;
                            }
                        }
                        echo '    </table>';
                    }
                    echo '</div>';
                }
                else 
                {
                    echo '<div class="date_info_block">';
                    $margin_left_location = "0";
                    echo '  <table>
                                <tr>
                                    <td>Kalender:</td>
                                    <td><strong>'.$date->getValue('cat_name'). '</strong></td>
                                </tr>';
                    if($date->getValue('dat_room_id')>0)
                    {
                        $room = new TableRooms($g_db);
                        $room->readData($date->getValue('dat_room_id'));
                        $room_name = $room->getValue('room_name');
                        echo '
                        <tr>
                            <td>Ort:</td>
                            <td>
                                <strong><a class="thickbox" href="'. $g_root_path. '/adm_program/system/msg_window.php?err_code=room_detail&amp;room_id='.$date->getValue('dat_room_id').'&amp;window=true&amp;KeepThis=true&amp;TB_iframe=true&amp;height=300&amp;width=580&amp;modal=true">'.$room_name.'</a></strong>
                            </td>
                        </tr>';
                    }
                    echo $participants_html;
                    echo '</table>';
                          
                    echo '</div>';
                }
                
                
            
                echo '<div class="date_description" style="clear: left;">'.$date->getDescription('HTML').'</div>
                <div class="editInformation">
                    Angelegt von '. $row['create_firstname']. ' '. $row['create_surname'].
                    ' am '. mysqldatetime('d.m.y h:i', $date->getValue('dat_timestamp_create'));

                    if($date->getValue('dat_usr_id_change') > 0)
                    {
                        echo '<br />Zuletzt bearbeitet von '. $row['change_firstname']. ' '. $row['change_surname'].
                        ' am '. mysqldatetime('d.m.y h:i', $date->getValue('dat_timestamp_change'));
                    }
               
                $sql = 'SELECT * FROM '.TBL_MEMBERS.' WHERE mem_rol_id ="'.$date->getValue('dat_rol_id').'" AND mem_usr_id="'.$g_current_user->getValue('usr_id').'"';
                $result = $g_db->query($sql);
                $row = $g_db->fetch_array($result);
                
//                 echo '<pre>';
//                 echo $date->getValue('dat_max_members').'<br>';
//                 print_r($date->visible_for);
//                 print_r($date->max_members_role);
//                 echo '</pre>';
                if($row['mem_leader']!=1)
                {
                    if($date->getValue('dat_rol_id')!=null && $row == null)
                    {
                        $available_signin = true;
                        $non_available_rols = array();
                        if($date->getValue('dat_max_members'))
                        {
                            // Teilnehmerbegrenzung allgemein
                            $sql = 'SELECT DISTINCT mem_usr_id FROM '.TBL_MEMBERS.'
                                    WHERE mem_rol_id="'.$date->getValue('dat_rol_id').'" AND mem_leader = 0';
                            $res_num = $g_db->query($sql);
                            $row_num = $g_db->num_rows($res_num);
                            if($row_num >= $date->getValue('dat_max_members'))
                            {
                                $available_signin = false;
                            }
                        }
                        if(count($date->max_members_role))
                        {
                            // Teilnehmerbegrenzungen auf Rolle aufgeteilt
                            $sql = 'SELECT mem_from_rol_id, COUNT(DISTINCT mem_usr_id) AS num FROM '.TBL_MEMBERS.'
                                    WHERE mem_rol_id="'.$date->getValue('dat_rol_id').'" AND mem_leader = 0 GROUP BY mem_from_rol_id';
                            $res_num = $g_db->query($sql);
                            while($limit = $g_db->fetch_array($res_num))
                            {
                                // Wenn Rollenplätze bereits voll sind, in Array aufnehmen
                                if($limit['num'] >= $date->max_members_role[$limit['mem_from_rol_id']]) $non_available_rols[$limit['mem_from_rol_id']] = $limit['num'];
                            }
                        }

                        echo '<div style="text-align:right">';
                        if($available_signin)
                        {
                            $content_signin = '';
                            foreach($user_roles_base as $k => $v)
                            {
                                if(in_array($k,$date->visible_for) && !isset($non_available_rols[$k]))
                                {
                                    $content_signin .= '&nbsp;<form action=';
                                    if($g_current_user->getValue('usr_id')!=null)
                                    {
                                        $content_signin .= '"'.$g_root_path.'/adm_program/modules/dates/dates_login.php" style="float:right">
                                            <input type="hidden" name="dat_id" value="'.$date->getValue('dat_id'). '" />
                                            <input type="hidden" name="headline" value="'.$req_headline.'" />
                                            <input type="hidden" name="login" value="1" />
                                            <input type="hidden" name="from_rol_id" value="'.$k.'" />
                                        ';
                                    }
                                    else
                                    {
                                        $content_signin .= '"'.$g_root_path.'/adm_program/modules/profile/profile_new.php" style="float:right">
                                            <input type="hidden" name="new_user" value="2" />
                                            <input type="hidden" name="date" value="'.$date->getValue('dat_rol_id').'" />
                                        ';
                                    }
                                    $content_signin .= '<button name="loginDate" type="submit" value="loginDate" tabindex="4"><img src="'. THEME_PATH. '/icons/ok.png" alt="login Date" />&nbsp;'.$v.'</button></form>';
                                }
                            }
                            if($content_signin) echo '<b>Anmelden als:</b>'.$content_signin;
                            else echo 'Keine Anmeldung mehr möglich.';
                        }
                        else
                        {
                            echo 'Keine Anmeldung mehr möglich.';
                        }
                        echo '</div>';

                    }
                    else if($date->getValue('dat_rol_id')!=null && $row != null)
                    {
                        if($user_roles_base[0])
                        {
                            // Gast darf sich nicht mehr abmelden, da er sonst keiner Rolle mehr angehört
                            echo '<div style="text-align:right"><b>Sie sind als Teilnehmer für diesen Termin angemeldet.</b></div>';
                        }
                        else
                        {
                            echo '<div style="text-align:right">

                            <form action=';

                            echo '"'.$g_root_path.'/adm_program/modules/dates/dates_login.php">
                                <input type="hidden" name="dat_id" value="'. $date->getValue('dat_id'). '" />
                                <input type="hidden" name="headline" value="'.$req_headline.'" />
                                <input type="hidden" name="login" value="0" />
                                <button name="logoutDate" type="submit" value="logoutDate" tabindex="4"><img src="'. THEME_PATH. '/icons/no.png"
                                alt="login Date" />&nbsp;austragen</button>
                            </form>
                            </div>';
                        }
                    }
                }
               echo '</div>';
          echo '</div>
        </div>';
    }  // Ende While-Schleife
}

// Navigation mit Vor- und Zurueck-Buttons
// erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
$base_url = $g_root_path.'/adm_program/modules/dates/dates.php?mode='.$req_mode.'&headline='.$req_headline;
echo generatePagination($base_url, $num_dates, $dates_per_page, $req_start, TRUE);

require(THEME_SERVER_PATH. '/overall_footer.php');

?>