<?php
/******************************************************************************
 * Calendar
 *
 * Version 1.9.0
 *
 * Plugin das den aktuellen Monatskalender auflistet und die Termine und Geburtstage
 * des Monats markiert und so ideal in einer Seitenleiste eingesetzt werden kann
 *
 * Compatible with Admidio version 2.3
 *
 * Übergaben: date_id (Format MMJJJJ Beispiel: 052011 = Mai 2011)
 *            ajax_change (ist gesetzt bei Monatswechsel per Ajax)
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// create path to plugin
$plugin_folder_pos = strpos(__FILE__, 'adm_plugins') + 11;
$plugin_file_pos   = strpos(__FILE__, 'calendar.php');
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. '/../adm_program/system/common.php');
require_once(PLUGIN_PATH. '/'.$plugin_folder.'/config.php');

if(isset($_GET['ajax_change']) && $plg_ajax_change == 1)
{
    // Header kodieren
    header('Content-Type: text/html; charset=UTF-8');
}

// Auf gesetzte Standardwerte aus config.php überprüfen und notfalls setzen
if(isset($plg_ajaxbox) == false)
{
    $plg_ajaxbox = 1;
}
if(isset($plg_link_target_termin) == false)
{
    $plg_link_target_termin = '_self';
}
if(isset($plg_link_target_geb) == false)
{
    $plg_link_target_geb = '_self';
}
if(isset($plg_ter_aktiv) == false)
{
    $plg_ter_aktiv = 1;
}
if(isset($plg_ter_login) == false)
{
    $plg_ter_login = 0;
}
if(isset($plg_geb_aktiv) == false)
{
    $plg_geb_aktiv = 0;
}
if(isset($plg_geb_login) == false)
{
    $plg_geb_login = 0;
}
if(isset($plg_geb_icon) == false)
{
    $plg_geb_icon = 0;
}
if(isset($plg_kal_cat) == false)
{
    $plg_kal_cat = array('all');
}
if(isset($plg_kal_cat_show) == false)
{
    $plg_kal_cat_show = 1;
}

// Prüfen ob the Link-URL gesetzt wurde oder leer ist
// wenn leer, dann Standardpfad zum Admidio-Modul

if(isset($plg_link_url) == false || ($plg_link_url) =='')
{
    $plg_link_url = $g_root_path.'/adm_program/modules/dates/dates.php';
}

// ///////////////////////////////////////////////////// //
// Prüfen ob the CSS Link-Klassen gesetzt wurden         //
// ///////////////////////////////////////////////////// //

if(isset($plg_link_class_geb) == false || ($plg_link_class_geb) =='')
{
    $plg_link_class_geb = 'geb';
}
if(isset($plg_link_class_date) == false || ($plg_link_class_date) =='')
{
    $plg_link_class_date = 'date';
}
if(isset($plg_link_class_merge) == false || ($plg_link_class_merge) =='')
{
    $plg_link_class_date = 'merge';
}

// /////////////////////////////////////////////////////// //
// Prüfen, ob die Rollenbedingung gesetzt wurde            //
// /////////////////////////////////////////////////////// //
if(isset($plg_rolle_sql) == 'all' || ($plg_rolle_sql) == '')
{
    $rol_sql = 'is not null';
}
else
{
    $rol_sql = 'in '.$plg_rolle_sql;
}

// Sprachdatei des Plugins einbinden
$gL10n->addLanguagePath(PLUGIN_PATH. '/'.$plugin_folder.'/languages');

// Nun noch einige Variablen initialisieren

$geb_link = '';
$plg_link = '';

// Date ID auslesen oder aktuellen Monat und Jahr erzeugen
$heute = 0;
if(array_key_exists('date_id', $_GET))
{
    if(is_numeric($_GET['date_id']) == false)
    {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    else
    {
        $date_id = $_GET['date_id'];
        $monat = substr($date_id, 0, 2);
        $jahr = substr($date_id, 2, 4);
        $_SESSION['plugin_calendar_last_month'] = $monat.$jahr;

        if($monat == date('m') and $jahr == date('Y'))
        {
            $heute = date('d');
        }
    }
}
elseif(isset($_SESSION['plugin_calendar_last_month']))
{
    // Zuletzt gewählten Monat anzeigen
    $monat = substr($_SESSION['plugin_calendar_last_month'], 0, 2);
    $jahr = substr($_SESSION['plugin_calendar_last_month'], 2, 4);
    if($monat == date('m') and $jahr == date('Y'))
    {
        $heute = date('d');
    }
}
else
{
    // Aktuellen Monat anzeigen
    $monat = date('m');
    $jahr = date('Y');
    $heute = date('d');
}
$sql_dat = $jahr. '-'. $monat;

// set database to admidio, sometimes the user has other database connections at the same time
$gDb->setCurrentDB();

// Abfrage der Termine
if($plg_ter_aktiv == 1)
{
    // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
    $plg_organizations = '';
    $plg_arr_orgas = $gCurrentOrganization->getOrganizationsInRelationship(true, true);

    foreach($plg_arr_orgas as $key => $value)
    {
        $plg_organizations = $plg_organizations. $key. ', ';
    }
    $plg_organizations = $plg_organizations. $gCurrentOrganization->getValue('org_id');

    // Ermitteln, welche Kalender angezeigt werden sollen
    if(in_array('all', $plg_kal_cat))
    {
        // alle Kalender anzeigen
        $sql_syntax = '';
    }
    else
    {
        // nur bestimmte Kalender anzeigen
        $sql_syntax = ' AND cat_type = \'DAT\' AND ( ';
        for($i=0;$i<count($plg_kal_cat);$i++)
        {
            $sql_syntax = $sql_syntax. 'cat_name = \''.$plg_kal_cat[$i].'\' OR ';
        }
        $sql_syntax = substr($sql_syntax, 0, -4). ') ';
    }


    // Dummy-Zähler für Schleifen definieren
    $ter = 1;
    $ter_anzahl = 0;
    $ter_aktuell = 0;

    // Datenbankabfrage mit Datum (Monat / Jahr)
    if($gCurrentUser->getValue('usr_id') > 0)
    {
        $login_sql = 'AND ( dtr_rol_id IS NULL OR dtr_rol_id IN (SELECT mem_rol_id FROM '.TBL_MEMBERS.' WHERE mem_usr_id = '.$gCurrentUser->getValue('usr_id').') )';
    }
    else
    {
        $login_sql = 'AND dtr_rol_id IS NULL';
    }
    $sql = 'SELECT DISTINCT dat_id, dat_cat_id, cat_name, dat_begin, dat_all_day, dat_location, dat_headline
            FROM '. TBL_DATE_ROLE.', '. TBL_DATES. ', '.TBL_CATEGORIES.'
            WHERE dat_id = dtr_dat_id
                '.$login_sql.'
                AND DATE_FORMAT(dat_begin, \'%Y-%m\') = \''.$sql_dat.'\'
                '.$sql_syntax.'
            AND dat_cat_id = cat_id
            ORDER BY dat_begin ASC';
    $result = $gDb->query($sql);

    while($row = $gDb->fetch_array($result))
    {
        $startDate = new DateTimeExtended($row['dat_begin'], 'Y-m-d H:i:s');
        $termin_id[$ter]       = $row['dat_id'];
        $termin_tag[$ter]      = $startDate->format('d');
        $termin_uhr[$ter]      = $startDate->format($gPreferences['system_time']);
        $termin_ganztags[$ter] = $row['dat_all_day'];
        $termin_ort[$ter]      = $row['dat_location'];
        $termin_titel[$ter]    = $row['dat_headline'];

        // Name der Standardkalender umsetzen, sonst Name lt. Datenbank
        if($plg_kal_cat_show == 1)
        {
            if(substr($row['cat_name'], 3, 1)=='_')
            {$calendar_name = $gL10n->get($row['cat_name']);}
            else
            {$calendar_name = $row['cat_name'];}
            $termin_titel[$ter]= $calendar_name. ': '. $termin_titel[$ter];
        }

        $ter++;
    }
}

// Abfrage der Geburtstage
if($plg_geb_aktiv == 1)
{
    // Dummy-Zähler für Schleifen definieren
    $geb = 1;
    $geb_anzahl = 0;
    $geb_aktuell = 0;

    // Datenbankabfrage nach Geburtstagen im Monat
    $sql = 'SELECT DISTINCT
                   usr_id, last_name.usd_value AS last_name, first_name.usd_value AS first_name,
                   birthday.usd_value AS birthday
              FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. ', '. TBL_USERS. '
              JOIN '. TBL_USER_DATA. ' AS birthday ON birthday.usd_usr_id = usr_id
               AND birthday.usd_usf_id = '. $gProfileFields->getProperty('BIRTHDAY', 'usf_id'). '
               AND MONTH(birthday.usd_value) = '.$monat.'
              LEFT JOIN '. TBL_USER_DATA. ' AS last_name ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
              LEFT JOIN '. TBL_USER_DATA. ' AS first_name ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
             WHERE rol_cat_id = cat_id
               AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               AND rol_id     = mem_rol_id
               AND mem_usr_id = usr_id
               AND mem_begin <= \''.DATE_NOW.'\'
               AND mem_end    > \''.DATE_NOW.'\'
               AND usr_valid  = 1
             ORDER BY Month(birthday.usd_value) ASC, DayOfMonth(birthday.usd_value) ASC, last_name, first_name';

    $result = $gDb->query($sql);
    $anz_geb = $gDb->num_rows($result);

    while($row = $gDb->fetch_array($result))
    {
        $birthdayDate   = new DateTimeExtended($row['birthday'], 'Y-m-d', 'date');
        $geb_day[$geb]  = $birthdayDate->format('d');
        $geb_year[$geb] = $birthdayDate->format('Y');
        $alter[$geb]    = $jahr-$geb_year[$geb];
        $geb_name[$geb] = $row['first_name']. ' '. $row['last_name'];
        $geb++;
    }
}

// Kalender erstellen
$erster = date('w', mktime(0, 0, 0, $monat, 1, $jahr));
$insgesamt = date('t', mktime(0, 0, 0, $monat, 1, $jahr));
$monate = explode(',', $gL10n->get('PLG_CALENDAR_MONTH'));
if($erster == 0)
{
    $erster = 7;
}
echo '<div id="plgCalendarContent" class="admidio-plugin-content">
<h3>'.$gL10n->get('DAT_CALENDAR').'</h3>

<script type="text/javascript"><!--
    if ( typeof gTranslations == "undefined")
    {
        var gTranslations = new Array("'.$gL10n->get('SYS_MON').'","'.$gL10n->get('SYS_TUE').'","'.$gL10n->get('SYS_WED').'","'.$gL10n->get('SYS_THU').'","'.$gL10n->get('SYS_FRI').'","'.$gL10n->get('SYS_SAT').'","'.$gL10n->get('SYS_SUN').'","'.$gL10n->get('SYS_TODAY').'","'.$gL10n->get('SYS_LOADING_CONTENT').'");
    }
--></script>

<table border="0" id="plgCalendarTable">
    <tr>';
        if($plg_ajax_change == 1)
        {
            echo "<th align=\"center\" class=\"plgCalendarHeader\"><a href=\"#\" onclick=\"$.ajax({
                type: 'GET',
                url: '".$g_root_path."/adm_plugins/$plugin_folder/calendar.php',
                cache: false,
                data: 'ajax_change&amp;date_id=".date('mY', mktime(0, 0, 0, $monat-1, 1, $jahr))."',
                success: function(html){
                    $('#plgCalendarContent').replaceWith(html);
                }
            }); return false;\">&laquo;</a></th>";
            echo '<th colspan="5" align="center" class="plgCalendarHeader">'.$monate[$monat-1].' '.$jahr.'</th>';
            echo "<th align=\"center\" class=\"plgCalendarHeader\"><a href=\"#\" onclick=\"$.ajax({
                type: 'GET',
                url: '".$g_root_path."/adm_plugins/$plugin_folder/calendar.php',
                cache: false,
                data: 'ajax_change&amp;date_id=".date('mY', mktime(0, 0, 0, $monat+1, 1, $jahr))."',
                success: function(html){
                    $('#plgCalendarContent').replaceWith(html);
                }
            }); return false;\">&raquo;</a></th>";
        }
        else
        {
            echo '<th colspan="7" align="center" class="plgCalendarHeader">'.$monate[$monat-1].' '.$jahr.'</th>';
        }
    echo '</tr>
    <tr>
        <td class="plgCalendarWeekday"><b>'.$gL10n->get('PLG_CALENDAR_MONDAY_SHORT').'</b></td><td class="plgCalendarWeekday"><b>'.$gL10n->get('PLG_CALENDAR_TUESDAY_SHORT').'</b></td>
        <td class="plgCalendarWeekday"><b>'.$gL10n->get('PLG_CALENDAR_WEDNESDAY_SHORT').'</b></td><td class="plgCalendarWeekday"><b>'.$gL10n->get('PLG_CALENDAR_THURSDAY_SHORT').'</b></td>
        <td class="plgCalendarWeekday"><b>'.$gL10n->get('PLG_CALENDAR_FRIDAY_SHORT').'</b></td><td class="plgCalendarWeekdaySaturday"><b>'.$gL10n->get('PLG_CALENDAR_SATURDAY_SHORT').'</b></td>
        <td class="plgCalendarWeekdaySunday"><b>'.$gL10n->get('PLG_CALENDAR_SUNDAY_SHORT').'</b></td>
    </tr>
    <tr>';

$i = 1;
while($i<$erster)
{
    echo '<td>&nbsp;</td>';
    $i++;
}
$i = 1;
$boolNewStart = false;
while($i<=$insgesamt)
{
    $ter_link  = '';
    $ter_title = '';
    $geb_link  = '';
    $geb_title = '';
    // Terminanzeige generieren
    if($plg_ter_aktiv == 1)
    {
        $ter_valid = 0;
        if($plg_ter_login == 0)
        {
            $ter_valid = 1;
        }
        if($plg_ter_login == 1)
        {
            if($gValidLogin == TRUE)
            {
                $ter_valid = 1;
            }
        }
        if($ter_valid == 1)
        {
            for($j=1;$j<=$ter-1;$j++)
            {
                if($i==$termin_tag[$j])
                {
                    if($ter_anzahl == 0)
                    {
                        $ter_aktuell = $termin_tag[$j];
                        if($plg_ajaxbox == 1)
                        {
                            $ter_link = 'titel='.$termin_titel[$j].'&uhr='.$termin_uhr[$j].'&ort='.$termin_ort[$j];
                            if($termin_ganztags[$j] == 1)
                            {
                                $ter_link = $ter_link. '&ganztags=1';
                            }
                        }
                        else
                        {
                            if($termin_ort[$j] != '')
                            {
                                $termin_ort[$j] = ', '. $termin_ort[$j];
                            }
                            if($termin_ganztags[$j] == 1)
                            {
                                $ter_title = $termin_titel[$j].$termin_ort[$j].', '.$gL10n->get('DAT_ALL_DAY');
                            }
                            else
                            {
                                $ter_title = $termin_titel[$j].', '.$termin_uhr[$j].' '.$gL10n->get('SYS_CLOCK').$termin_ort[$j];
                            }
                        }
                    }
                    $ter_anzahl++;
                }
            }
            if($ter_anzahl >> 0)
            {
                // Link_Target auf Termin-Vorgabe einstellen
                $plg_link_target = $plg_link_target_termin;

                if($i <= 9)
                {
                    $plg_link = $plg_link_url.'?date='.$jahr.$monat.'0'. $i;
                }
                else
                {
                    $plg_link = $plg_link_url.'?date='.$jahr.$monat.$i;
                }
            }
            if($ter_anzahl >> 1)
            {
                if($plg_ajaxbox == 1)
                {
                    $ter_link = $ter_link. '&weitere=1';
                }
                else
                {
                    $ter_title = $ter_title. $gL10n->get('PLG_CALENDAR_MORE');
                }
            }
        }
    }

    // Geburtstagsanzeige generieren
    if($plg_geb_aktiv == 1)
    {
        $geb_valid = 0;
        if($plg_geb_login == 0)
        {
            $geb_valid = 1;
        }
        if($plg_geb_login == 1)
        {
            if($gValidLogin == TRUE)
            {
                $geb_valid = 1;
            }
        }
        if($geb_valid == 1)
        {
            for($k=1;$k<=$geb-1;$k++)
            {
                if($i==$geb_day[$k])
                {
                    $geb_aktuell = $geb_day[$k];
                    if($plg_ajaxbox == 1)
                    {
                        if($geb_anzahl >> 0)
                        {
                            $geb_link = $geb_link. '&';
                        }
                        $geb_anzahl++;
                        $geb_link = $geb_link. 'gebname'.$geb_anzahl.'='.$geb_name[$k].'&gebalter'.$geb_anzahl.'='.$alter[$k];
                    }
                    else
                    {
                        if($geb_anzahl >> 0)
                        {
                            $geb_title = $geb_title. ', ';
                        }
                        $geb_anzahl++;
                        $geb_title = $geb_title. $geb_name[$k]. ' ('.$alter[$k].')';
                    }
                }
            }
            if($plg_ajaxbox == 1)
            {
                $geb_link = 'gebanzahl='.$geb_anzahl.'&'.$geb_link;
            }
        }
    }

    // Hier erfolgt nun die Bestimmung der Linkklasse
// Dabei werden 3 Linkklassen verwendet:
// geb (Geburtstage), date (Termine) und merge (gleichzeitig Geburtstage und Termine

// Zuerst Vorbelegung der Wochentagsklassen
$plg_link_class_saturday = 'plgCalendarSaturday';
$plg_link_class_sunday = 'plgCalendarSunday';
$plg_link_class_weekday = 'plgCalendarDay';

if($i != $ter_aktuell && $i == $geb_aktuell) // Geburstag aber kein Termin
    {
        $plg_link_class = 'geb';
        $plg_link_class_saturday = 'plgCalendarBirthSaturday';
        $plg_link_class_sunday = 'plgCalendarBirthSunday';
        $plg_link_class_weekday = 'plgCalendarBirthDay';

    }

if($i == $ter_aktuell && $i!= $geb_aktuell) // Termin aber kein Geburtstag
    {
        $plg_link_class = 'date';
        $plg_link_class_saturday = 'plgCalendarDateSaturday';
        $plg_link_class_sunday = 'plgCalendarDateSunday';
        $plg_link_class_weekday = 'plgCalendarDateDay';

    }
if($i == $ter_aktuell && $i == $geb_aktuell) // Termin und Geburtstag
    {
        $plg_link_class = 'merge';
        $plg_link_class_saturday = 'plgCalendarMergeSaturday';
        $plg_link_class_sunday = 'plgCalendarMergeSunday';
        $plg_link_class_weekday = 'plgCalendarMergeDay';

    }
// Ende der Linklassenbestimmung

    if ($boolNewStart)
    {
        echo '<tr>
        ';
        $boolNewStart = false;
    }
    $rest = ($i+$erster-1)%7;
    if($i == $heute)
    {
        echo '<td align="center" class="plgCalendarToday">';
    }
    elseif($rest == 6)
    {
        // CSS aus der Linkklassenbestimmung verwenden
        echo '<td align="center" class="'.$plg_link_class_saturday.'">';
    }
    elseif($rest == 0)
    {
        // CSS aus der Linkklassenbestimmung verwenden
        echo '<td align="center" class="'.$plg_link_class_sunday.'">';
    }
    else
    {
        echo '<td align="center" class="'.$plg_link_class_weekday.'">';
    }

    if($i == $heute or $i == $ter_aktuell or $i == $geb_aktuell)
    {
        if($i != $ter_aktuell && $i == $geb_aktuell)
        {
            // Link-URL bei Geburtstag durch # abschalten
            $plg_link = '#';
            // Link_Target für Geburtstagvorgabe einstellen
            $plg_link_target = $plg_link_target_geb;
        }

        if($i == $ter_aktuell || $i == $geb_aktuell)
        {
            if($plg_ajaxbox == 1)
            {
                if($ter_link != '' && $geb_link != '')
                {
                    $geb_link = '&'. $geb_link;
                }

                // plg_link_class bestimmt das Erscheinungsbild des jeweiligen Links
                echo '<a class="'.$plg_link_class.'" href="'.$plg_link.'" target="'.$plg_link_target.'"
                    onmouseover="ajax_showTooltip(event,\''.htmlspecialchars(addcslashes($g_root_path.'/adm_plugins/'.$plugin_folder.'/calendar_msg.php?'.$ter_link.$geb_link, "'")).'\',this)"
                    onmouseout="ajax_hideTooltip()">'.$i.'</a>';
            }
            else
            {
                if($ter_title != '' && $geb_title != '')
                {
                    $geb_title = ', '. $geb_title;
                }

                echo '<a class="'.$plg_link_class.'" href="'.$plg_link.'" title="'.$ter_title.$geb_title.'"
                    href="'.$plg_link.'" target="'.$plg_link_target.'">'.$i.'</a>';
            }
        }
        elseif($i == $heute)
        {
            echo '<span class="plgCalendarToday">'.$i.'</span>';
        }
    }
    elseif($rest == 6)
    {
        echo '<span class="plgCalendarSaturday">'.$i.'</span>';
    }
    elseif($rest == 0)
    {
        echo '<span class="plgCalendarSunday">'.$i.'</span>';
    }
    else
    {
        echo $i;
    }
    echo '</td>';
    if($rest == 0 || $i == $insgesamt)
    {
        echo '</tr>
        ';
        $boolNewStart = true;
    }
    $i++;
    $ter_anzahl = 0;
    $geb_anzahl = 0;
}
echo '</table>
';
if($monat.$jahr != date('mY'))
{
    echo '<div id="plgCalendarReset"><a href="#" onclick="$.ajax({
            type: \'GET\',
            url: \''.$g_root_path.'/adm_plugins/'.$plugin_folder.'/calendar.php\',
            cache: false,
            data: \'ajax_change&amp;date_id='.date('mY').'\',
            success: function(html){
                $(\'#plgCalendarContent\').replaceWith(html);
            }
        }); return false;">'.$gL10n->get('PLG_CALENDAR_CURRENT_MONTH').'</a></div>';
}
echo '</div>';

?>
