<?php
/**
 ***********************************************************************************************
 * Calendar
 *
 * Version 2.2.0
 *
 * Plugin shows the actual month with all the events and birthdays that are
 * coming. This plugin can be used to show the Admidio events and birthdays in a
 * sidebar within Admidio or in an external website.
 *
 * Compatible with Admidio version 3.2
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// create path to plugin
$plugin_folder_pos = strpos(__FILE__, 'adm_plugins') + 11;
$plugin_file_pos   = strpos(__FILE__, 'calendar.php');
$plugin_folder     = substr(__FILE__, $plugin_folder_pos + 1, $plugin_file_pos - $plugin_folder_pos - 2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. '/../adm_program/system/common.php');
require_once(PLUGIN_PATH. '/'.$plugin_folder.'/config.php');

// Initialize and check the parameters
$getDateId = admFuncVariableIsValid($_GET, 'date_id',   'string');

if(isset($_GET['ajax_change']) && $plg_ajax_change)
{
    // Header kodieren
    header('Content-Type: text/html; charset=UTF-8');
}

// Auf gesetzte Standardwerte aus config.php überprüfen und notfalls setzen
if(!isset($plg_ajaxbox))
{
    $plg_ajaxbox = 1;
}
if(!isset($plg_link_target_termin))
{
    $plg_link_target_termin = '_self';
}
if(!isset($plg_link_target_geb))
{
    $plg_link_target_geb = '_self';
}
if(!isset($plg_ter_aktiv))
{
    $plg_ter_aktiv = 1;
}
if(!isset($plg_ter_login))
{
    $plg_ter_login = 0;
}
if(!isset($plg_geb_aktiv))
{
    $plg_geb_aktiv = 0;
}
if(!isset($plg_geb_login))
{
    $plg_geb_login = 0;
}
if(!isset($plg_geb_icon))
{
    $plg_geb_icon = 0;
}
if(!isset($plg_kal_cat))
{
    $plg_kal_cat = array('all');
}
if(!isset($plg_kal_cat_show))
{
    $plg_kal_cat_show = 1;
}

// Prüfen ob the Link-URL gesetzt wurde oder leer ist
// wenn leer, dann Standardpfad zum Admidio-Modul

if(!isset($plg_link_url) || $plg_link_url === '')
{
    $plg_link_url = ADMIDIO_URL . FOLDER_MODULES . '/dates/dates.php';
}

// ///////////////////////////////////////////////////// //
// Prüfen ob the CSS Link-Klassen gesetzt wurden         //
// ///////////////////////////////////////////////////// //

if(!isset($plg_link_class_geb) || $plg_link_class_geb === '')
{
    $plg_link_class_geb = 'geb';
}
if(!isset($plg_link_class_date) || $plg_link_class_date === '')
{
    $plg_link_class_date = 'date';
}
if(!isset($plg_link_class_merge) || $plg_link_class_merge === '')
{
    $plg_link_class_date = 'merge';
}

// /////////////////////////////////////////////////////// //
// Prüfen, ob die Rollenbedingung gesetzt wurde            //
// /////////////////////////////////////////////////////// //
if(!isset($plg_rolle_sql) || $plg_rolle_sql === 'all' || $plg_rolle_sql === '')
{
    $sqlRoleIds = ' IS NOT NULL ';
}
else
{
    $sqlRoleIds = ' IN '.$plg_rolle_sql;
}

// Sprachdatei des Plugins einbinden
$gL10n->addLanguagePath(PLUGIN_PATH. '/'.$plugin_folder.'/languages');

// Nun noch einige Variablen initialisieren

$geb_link = '';
$plg_link = '';
$currentMonth = '';
$currentYear  = '';
$today        = 0;

// Date ID auslesen oder aktuellen Monat und Jahr erzeugen
if($getDateId !== '')
{
    $currentMonth = substr($getDateId, 0, 2);
    $currentYear  = substr($getDateId, 2, 4);
    $_SESSION['plugin_calendar_last_month'] = $currentMonth.$currentYear;
}
elseif(isset($_SESSION['plugin_calendar_last_month']))
{
    // Zuletzt gewählten Monat anzeigen
    $currentMonth = substr($_SESSION['plugin_calendar_last_month'], 0, 2);
    $currentYear  = substr($_SESSION['plugin_calendar_last_month'], 2, 4);
}
else
{
    // show current month
    $currentMonth = date('m');
    $currentYear  = date('Y');
}

if($currentMonth === date('m') && $currentYear === date('Y'))
{
    $today = (int) date('d');
}

$lastDayCurrentMonth = (int) date('t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$dateMonthStart = $currentYear.'-'.$currentMonth.'-01 00:00:01';    // add 1 second to ignore all day events that end at 00:00:00
$dateMonthEnd   = $currentYear.'-'.$currentMonth.'-'.$lastDayCurrentMonth.' 23:59:59';
$eventsMonthDayArray    = array();
$birthdaysMonthDayArray = array();

// if page object is set then integrate css file of this plugin
global $page;
if(isset($page) && $page instanceof \HtmlPage)
{
    $page->addCssFile(ADMIDIO_URL . FOLDER_PLUGINS . '/calendar/calendar.css');
}

// query of all events
if($plg_ter_aktiv)
{
    // alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
    $plg_organizations = '';
    $plg_arr_orgas = $gCurrentOrganization->getOrganizationsInRelationship(true, true);

    foreach($plg_arr_orgas as $key => $value)
    {
        $plg_organizations .= $key. ', ';
    }
    $plg_organizations .= $gCurrentOrganization->getValue('org_id');

    // Ermitteln, welche Kalender angezeigt werden sollen
    if(in_array('all', $plg_kal_cat, true))
    {
        // alle Kalender anzeigen
        $sqlSyntax = '';
    }
    else
    {
        // nur bestimmte Kalender anzeigen
        $sqlSyntax = ' AND cat_type = \'DAT\' AND ( ';
        for($i = 0, $iMax = count($plg_kal_cat); $i < $iMax; ++$i)
        {
            $sqlSyntax .= 'cat_name = \''.$plg_kal_cat[$i].'\' OR ';
        }
        $sqlSyntax = substr($sqlSyntax, 0, -4). ') ';
    }

    // search for all events in database within the given month
    if($gCurrentUser->getValue('usr_id') > 0)
    {
        $sqlLogin = 'AND (  dtr_rol_id IS NULL
                         OR dtr_rol_id IN (SELECT mem_rol_id
                                             FROM '.TBL_MEMBERS.'
                                            WHERE mem_usr_id = '.$gCurrentUser->getValue('usr_id').') )';
    }
    else
    {
        $sqlLogin = 'AND dtr_rol_id IS NULL';
    }

    $sql = 'SELECT DISTINCT dat_id, dat_cat_id, cat_name, dat_begin, dat_end, dat_all_day, dat_location, dat_headline
              FROM '.TBL_DATE_ROLE.'
        INNER JOIN '.TBL_DATES.'
                ON dat_id = dtr_dat_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = dat_cat_id
             WHERE dat_begin <= \''.$dateMonthEnd.'\'
               AND dat_end   >= \''.$dateMonthStart.'\'
                   '.$sqlLogin.'
                   '.$sqlSyntax.'
          ORDER BY dat_begin ASC';
    $datesStatement = $gDb->query($sql);

    while($row = $datesStatement->fetch())
    {
        $startDate = new DateTime($row['dat_begin']);
        $endDate   = new DateTime($row['dat_end']);

        // set custom name of plugin for calendar or use default Admidio name
        if($plg_kal_cat_show)
        {
            if($row['cat_name'][3] === '_')
            {
                $calendarName = $gL10n->get($row['cat_name']);
            }
            else
            {
                $calendarName = $row['cat_name'];
            }
            $row['dat_headline'] = $calendarName. ': '. $row['dat_headline'];
        }

        if($startDate->format('Y-m-d') === $endDate->format('Y-m-d'))
        {
            // event only within one day
            $eventsMonthDayArray[$startDate->format('j')][] = array(
                'dat_id'   => $row['dat_id'],
                'time'     => $startDate->format($gPreferences['system_time']),
                'all_day'  => $row['dat_all_day'],
                'location' => $row['dat_location'],
                'headline' => $row['dat_headline'],
                'one_day'  => false
            );
        }
        else
        {
            // event within several days

            $oneDayDate = false;

            if($startDate->format('m') !== $currentMonth)
            {
                $firstDay = 1;
            }
            else
            {
                $firstDay = $startDate->format('j');
            }

            if($endDate->format('m') !== $currentMonth)
            {
                $lastDay = $lastDayCurrentMonth;
            }
            else
            {
                if($row['dat_all_day'] == 1)
                {
                    $oneDay  = new DateInterval('P1D');
                    $endDate = $endDate->sub($oneDay);
                }

                $lastDay = $endDate->format('j');
            }

            if($startDate->format('Y-m-d') === $endDate->format('Y-m-d'))
            {
                $oneDayDate = true;
            }

            // now add event to every relevant day of month
            for($i = $firstDay; $i <= $lastDay; ++$i)
            {
                $eventsMonthDayArray[$i][] = array(
                    'dat_id'   => $row['dat_id'],
                    'time'     => $startDate->format($gPreferences['system_time']),
                    'all_day'  => $row['dat_all_day'],
                    'location' => $row['dat_location'],
                    'headline' => $row['dat_headline'],
                    'one_day'  => $oneDayDate
                );
            }
        }
    }
}

// query of all birthdays
if($plg_geb_aktiv)
{
    if($gDbType === 'pgsql' || $gDbType === 'postgresql') // for backwards compatibility "postgresql"
    {
        $sqlMonthOfBirthday = ' date_part(\'month\', timestamp birthday.usd_value) ';
        $sqlDayOfBirthday   = ' date_part(\'day\', timestamp birthday.usd_value) ';
    }
    else
    {
        $sqlMonthOfBirthday = ' MONTH(birthday.usd_value) ';
        $sqlDayOfBirthday   = ' DayOfMonth(birthday.usd_value) ';
    }

    // database query for all birthdays of this month
    $sql = 'SELECT DISTINCT
                   usr_id, last_name.usd_value AS last_name, first_name.usd_value AS first_name,
                   birthday.usd_value AS birthday
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
        INNER JOIN '.TBL_USERS.'
                ON usr_id = mem_usr_id
        INNER JOIN '.TBL_USER_DATA.' AS birthday
                ON birthday.usd_usr_id = usr_id
               AND birthday.usd_usf_id = '.$gProfileFields->getProperty('BIRTHDAY', 'usf_id').'
               AND '.$sqlMonthOfBirthday.' = '.$currentMonth.'
         LEFT JOIN '.TBL_USER_DATA.' AS last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
         LEFT JOIN '.TBL_USER_DATA.' AS first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
             WHERE usr_valid  = 1
               AND cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
               AND rol_id '.$sqlRoleIds.'
               AND mem_begin <= \''.DATE_NOW.'\'
               AND mem_end    > \''.DATE_NOW.'\'
          ORDER BY '.$sqlMonthOfBirthday.' ASC, '.$sqlMonthOfBirthday.' ASC, last_name, first_name';

    $birthdayStatement = $gDb->query($sql);

    while($row = $birthdayStatement->fetch())
    {
        $birthdayDate   = new DateTime($row['birthday']);

        $birthdaysMonthDayArray[$birthdayDate->format('j')][] = array(
            'year' => $birthdayDate->format('Y'),
            'age'  => $currentYear - $birthdayDate->format('Y'),
            'name' => $row['last_name']. ', '. $row['first_name']
        );
    }
}

// Kalender erstellen
$firstWeekdayOfMonth = (int) date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$months = explode(',', $gL10n->get('PLG_CALENDAR_MONTH'));

if($firstWeekdayOfMonth === 0)
{
    $firstWeekdayOfMonth = 7;
}

echo '<div id="plgCalendarContent" class="admidio-plugin-content">
<h3>'.$gL10n->get('DAT_CALENDAR').'</h3>

<script type="text/javascript">
    if (typeof gTranslations === "undefined") {
        var gTranslations = [
            "'.$gL10n->get('SYS_MON').'",
            "'.$gL10n->get('SYS_TUE').'",
            "'.$gL10n->get('SYS_WED').'",
            "'.$gL10n->get('SYS_THU').'",
            "'.$gL10n->get('SYS_FRI').'",
            "'.$gL10n->get('SYS_SAT').'",
            "'.$gL10n->get('SYS_SUN').'",
            "'.$gL10n->get('SYS_TODAY').'",
            "'.$gL10n->get('SYS_LOADING_CONTENT').'"
        ];
    }
</script>

<table border="0" id="plgCalendarTable">
    <tr>';
        if($plg_ajax_change)
        {
            echo '<th style="text-align: center;" class="plgCalendarHeader"><a href="#" onclick="$.get({
                url: \'' . ADMIDIO_URL . FOLDER_PLUGINS . '/' . $plugin_folder . '/calendar.php\',
                cache: false,
                data: \'ajax_change&amp;date_id='.date('mY', mktime(0, 0, 0, $currentMonth - 1, 1, $currentYear)).'\',
                success: function(html) {
                    $(\'#plgCalendarContent\').replaceWith(html);
                    $(\'.admidio-calendar-link\').popover();
                }
            }); return false;">&laquo;</a></th>';
            echo '<th colspan="5" style="text-align: center;" class="plgCalendarHeader">'.$months[$currentMonth - 1].' '.$currentYear.'</th>';
            echo '<th style="text-align: center;" class="plgCalendarHeader"><a href="#" onclick="$.get({
                url: \'' . ADMIDIO_URL . FOLDER_PLUGINS . '/' . $plugin_folder . '/calendar.php\',
                cache: false,
                data: \'ajax_change&amp;date_id='.date('mY', mktime(0, 0, 0, $currentMonth + 1, 1, $currentYear)).'\',
                success: function(html) {
                    $(\'#plgCalendarContent\').replaceWith(html);
                    $(\'.admidio-calendar-link\').popover();
                }
            }); return false;">&raquo;</a></th>';
        }
        else
        {
            echo '<th colspan="7" align="center" class="plgCalendarHeader">'.$months[$currentMonth - 1].' '.$currentYear.'</th>';
        }
    echo '</tr>
    <tr>
        <td class="plgCalendarWeekday"><strong>'.$gL10n->get('PLG_CALENDAR_MONDAY_SHORT').'</strong></td>
        <td class="plgCalendarWeekday"><strong>'.$gL10n->get('PLG_CALENDAR_TUESDAY_SHORT').'</strong></td>
        <td class="plgCalendarWeekday"><strong>'.$gL10n->get('PLG_CALENDAR_WEDNESDAY_SHORT').'</strong></td>
        <td class="plgCalendarWeekday"><strong>'.$gL10n->get('PLG_CALENDAR_THURSDAY_SHORT').'</strong></td>
        <td class="plgCalendarWeekday"><strong>'.$gL10n->get('PLG_CALENDAR_FRIDAY_SHORT').'</strong></td>
        <td class="plgCalendarWeekdaySaturday"><strong>'.$gL10n->get('PLG_CALENDAR_SATURDAY_SHORT').'</strong></td>
        <td class="plgCalendarWeekdaySunday"><strong>'.$gL10n->get('PLG_CALENDAR_SUNDAY_SHORT').'</strong></td>
    </tr>
    <tr>';

$i = 1;
while($i < $firstWeekdayOfMonth)
{
    echo '<td>&nbsp;</td>';
    ++$i;
}

$currentDay = 1;
$boolNewStart = false;

while($currentDay <= $lastDayCurrentMonth)
{
    $ter_link  = '';
    $geb_link  = '';
    $htmlContent  = '';
    $textContent  = '';
    $hasEvents    = false;
    $hasBirthdays = false;
    $countEvents  = 0;

    $dateObj = DateTime::createFromFormat('Y-m-j', $currentYear.'-'.$currentMonth.'-'.$currentDay);

    // Terminanzeige generieren
    if($plg_ter_aktiv)
    {
        $terValid = false;
        if(!$plg_ter_login)
        {
            $terValid = true;
        }
        if($plg_ter_login && $gValidLogin)
        {
            $terValid = true;
        }

        if($terValid && array_key_exists($currentDay, $eventsMonthDayArray))
        {
            $hasEvents = true;

            foreach($eventsMonthDayArray[$currentDay] as $eventArray)
            {
                if($plg_ajaxbox || $countEvents === 0)
                {
                    if($eventArray['location'] !== '')
                    {
                        $eventArray['location'] = ', '. $eventArray['location'];
                    }

                    if($htmlContent !== '' && $plg_ajaxbox)
                    {
                        $htmlContent .= '<br />';
                    }
                    if($eventArray['all_day'] == 1)
                    {
                        if($eventArray['one_day'] == true)
                        {
                            $htmlContent .= '<strong>'.$gL10n->get('DAT_ALL_DAY').'</strong> '.$eventArray['headline'].$eventArray['location'];
                            $textContent .= $gL10n->get('DAT_ALL_DAY').' '.$eventArray['headline'].$eventArray['location'];
                        }
                        else
                        {
                            $htmlContent .= '<strong>'.$gL10n->get('PLG_CALENDAR_SEVERAL_DAYS').'</strong> '.$eventArray['headline'].$eventArray['location'];
                            $textContent .= $gL10n->get('PLG_CALENDAR_SEVERAL_DAYS').' '.$eventArray['headline'].$eventArray['location'];
                        }
                    }
                    else
                    {
                        $htmlContent .= '<strong>'.$eventArray['time'].' '.$gL10n->get('SYS_CLOCK').'</strong> '.$eventArray['headline'].$eventArray['location'];
                        $textContent .= $eventArray['time'].' '.$gL10n->get('SYS_CLOCK').' '.$eventArray['headline'].$eventArray['location'];
                    }
                }
                ++$countEvents;
            }

            if($countEvents > 0)
            {
                // Link_Target auf Termin-Vorgabe einstellen
                $plg_link_target = $plg_link_target_termin;
                $plg_link = $plg_link_url.'?date_from='.$dateObj->format('Y-m-d').'&date_to='.$dateObj->format('Y-m-d');
            }

            if($plg_ajaxbox !== 1 && count($eventsMonthDayArray[$currentDay]) > 1)
            {
                $textContent .= $gL10n->get('PLG_CALENDAR_MORE');
            }
        }
    }

    // Geburtstagsanzeige generieren
    if($plg_geb_aktiv)
    {
        $gebValid = false;

        if(!$plg_geb_login)
        {
            $gebValid = true;
        }
        if($plg_geb_login && $gValidLogin)
        {
            $gebValid = true;
        }

        if($gebValid && array_key_exists($currentDay, $birthdaysMonthDayArray))
        {
            foreach($birthdaysMonthDayArray[$currentDay] as $birthdayArray)
            {
                $hasBirthdays = true;

                if($htmlContent !== '')
                {
                    $htmlContent .= '<br />';
                    $textContent .= ', ';
                }

                if($plg_geb_icon)
                {
                    $icon = '<img src=\''.ADMIDIO_URL . FOLDER_PLUGINS . '/' . $plugin_folder . '/cake.png\' alt=\'Birthday\' /> ';
                }
                else
                {
                    $icon = '';
                }

                $htmlContent .= $icon.$birthdayArray['name']. ' ('.$birthdayArray['age'].')';
                $textContent .= $birthdayArray['name']. ' ('.$birthdayArray['age'].')';
            }
        }
    }

    // Hier erfolgt nun die Bestimmung der Linkklasse
    // Dabei werden 3 Linkklassen verwendet:
    // geb (Geburtstage), date (Termine) und merge (gleichzeitig Geburtstage und Termine

    // Zuerst Vorbelegung der Wochentagsklassen
    $plg_link_class_saturday = 'plgCalendarSaturday';
    $plg_link_class_sunday   = 'plgCalendarSunday';
    $plg_link_class_weekday  = 'plgCalendarDay';

    if(!$hasEvents && $hasBirthdays) // no events but birthdays
    {
        $plg_link_class = 'geb';
        $plg_link_class_saturday = 'plgCalendarBirthSaturday';
        $plg_link_class_sunday   = 'plgCalendarBirthSunday';
        $plg_link_class_weekday  = 'plgCalendarBirthDay';

    }

    if($hasEvents && !$hasBirthdays) // events but no birthdays
    {
        $plg_link_class = 'date';
        $plg_link_class_saturday = 'plgCalendarDateSaturday';
        $plg_link_class_sunday   = 'plgCalendarDateSunday';
        $plg_link_class_weekday  = 'plgCalendarDateDay';

    }

    if($hasEvents && $hasBirthdays) // events and birthdays
    {
        $plg_link_class = 'merge';
        $plg_link_class_saturday = 'plgCalendarMergeSaturday';
        $plg_link_class_sunday   = 'plgCalendarMergeSunday';
        $plg_link_class_weekday  = 'plgCalendarMergeDay';

    }
    // Ende der Linklassenbestimmung

    if ($boolNewStart)
    {
        echo '<tr>';
        $boolNewStart = false;
    }
    $rest = ($currentDay + $firstWeekdayOfMonth - 1) % 7;
    if($currentDay === $today)
    {
        echo '<td align="center" class="plgCalendarToday">';
    }
    elseif($rest === 6)
    {
        // CSS aus der Linkklassenbestimmung verwenden
        echo '<td align="center" class="'.$plg_link_class_saturday.'">';
    }
    elseif($rest === 0)
    {
        // CSS aus der Linkklassenbestimmung verwenden
        echo '<td align="center" class="'.$plg_link_class_sunday.'">';
    }
    else
    {
        echo '<td align="center" class="'.$plg_link_class_weekday.'">';
    }

    if($currentDay === $today || $hasEvents || $hasBirthdays)
    {
        if(!$hasEvents && $hasBirthdays)
        {
            // Link-URL bei Geburtstag durch # abschalten
            $plg_link = '#';
            // Link_Target für Geburtstagvorgabe einstellen
            $plg_link_target = $plg_link_target_geb;
        }

        if($hasEvents || $hasBirthdays)
        {
            if($plg_ajaxbox)
            {
                if($ter_link !== '' && $geb_link !== '')
                {
                    $geb_link = '&'. $geb_link;
                }

                // plg_link_class bestimmt das Erscheinungsbild des jeweiligen Links
                echo '<a class="admidio-calendar-link '.$plg_link_class.'" href="'.$plg_link.'" data-toggle="popover" data-html="true" data-trigger="hover" data-placement="auto"
                    title="'.$dateObj->format($gPreferences['system_date']).'" data-content="'.htmlspecialchars($htmlContent).'" target="'.$plg_link_target.'">'.$currentDay.'</a>';
            }
            else
            {
                echo '<a class="'.$plg_link_class.'" href="'.$plg_link.'" title="'.str_replace('"', '', $textContent).'"
                    href="'.$plg_link.'" target="'.$plg_link_target.'">'.$currentDay.'</a>';
            }
        }
        elseif($currentDay === $today)
        {
            echo '<span class="plgCalendarToday">'.$currentDay.'</span>';
        }
    }
    elseif($rest === 6)
    {
        echo '<span class="plgCalendarSaturday">'.$currentDay.'</span>';
    }
    elseif($rest === 0)
    {
        echo '<span class="plgCalendarSunday">'.$currentDay.'</span>';
    }
    else
    {
        echo $currentDay;
    }
    echo '</td>';
    if($rest === 0 || $currentDay === $lastDayCurrentMonth)
    {
        echo '</tr>';
        $boolNewStart = true;
    }

    ++$currentDay;
}
echo '</table>';

if($currentMonth.$currentYear !== date('mY'))
{
    echo '<div id="plgCalendarReset"><a href="#" onclick="$.get({
            url: "' . ADMIDIO_URL . FOLDER_PLUGINS . '/' . $plugin_folder . '/calendar.php",
            cache: false,
            data: "ajax_change&amp;date_id='.date('mY').'",
            success: function(html) {
                $("#plgCalendarContent").replaceWith(html);
                $(".admidio-calendar-link").popover();
            }
        }); return false;">'.$gL10n->get('PLG_CALENDAR_CURRENT_MONTH').'</a></div>';
}
echo '</div>';
