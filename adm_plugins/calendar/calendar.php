<?php
/**
 ***********************************************************************************************
 * Calendar
 *
 * Plugin shows the actual month with all the events and birthdays that are
 * coming. This plugin can be used to show the Admidio events and birthdays in a
 * sidebar within Admidio or in an external website.
 *
 * Compatible with Admidio version 4.1
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
$rootPath = dirname(__DIR__, 2);
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');

// only include config file if it exists
if (is_file(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
}

// Initialize and check the parameters
$getDateId = admFuncVariableIsValid($_GET, 'date_id', 'string');

// global variable to show names of the members who have birthday
$plgCalendarShowNames = false;

// set default values if there is no value has been stored in the config.php
if (!isset($plg_ajaxbox)) {
    $plg_ajaxbox = 1;
}
if (!isset($plg_link_target_termin)) {
    $plg_link_target_termin = '_self';
}
if (!isset($plg_link_target_geb)) {
    $plg_link_target_geb = '_self';
}
if (!isset($plg_ter_aktiv)) {
    $plg_ter_aktiv = 1;
}
if (!isset($plg_geb_aktiv)) {
    $plg_geb_aktiv = 1;
}
if (!isset($plg_geb_login)) {
    $plg_geb_login = 1;
}
if (!isset($plg_geb_icon)) {
    $plg_geb_icon = 1;
}
if (!isset($plg_geb_displayNames)) {
    $plg_geb_displayNames = 1;
}
if (!isset($plg_kal_cat)) {
    $plg_kal_cat = array('all');
}
if (!isset($plg_kal_cat_show)) {
    $plg_kal_cat_show = 1;
}

// check if only members of configured roles could view birthday
if ($gValidLogin) {
    if (isset($plg_calendar_roles_view_plugin) && count($plg_calendar_roles_view_plugin) > 0) {
        // current user must be member of at least one listed role
        if(count(array_intersect($plg_calendar_roles_view_plugin, $gCurrentUser->getRoleMemberships())) > 0) {
            $plgCalendarShowNames = true;
        }
    } else {
        // every member could view birthdays
        $plgCalendarShowNames = true;
    }
} else {
    if ($plg_geb_login === 0) {
        // every visitor is allowed to view birthdays
        $plgCalendarShowNames = true;
    }
}

// check if role conditions where set
if (isset($plg_rolle_sql) && is_array($plg_rolle_sql) && count($plg_rolle_sql) > 0) {
    $sqlRoleIds = 'IN (' . implode(',', $plg_rolle_sql) . ')';
} else {
    $sqlRoleIds = 'IS NOT NULL';
}

// check if the link url was set or is empty
// otherwise the default url to the Admidio event module will be set
if (!isset($plg_link_url) || $plg_link_url === '') {
    $plg_link_url = ADMIDIO_URL . FOLDER_MODULES . '/dates/dates.php';
}

// add header content type if in ajax mode
if ($plg_ajaxbox) {
    header('Content-Type: text/html; charset=utf-8');
}

// initialize some variables
$gebLink = '';
$plgLink = '';
$currentMonth = '';
$currentYear  = '';
$today        = 0;

if ($getDateId !== '') {
    // Read Date ID or generate current month and year
    $currentMonth = substr($getDateId, 0, 2);
    $currentYear  = substr($getDateId, 2, 4);
    $_SESSION['plugin_calendar_last_month'] = $currentMonth.$currentYear;
} elseif (isset($_SESSION['plugin_calendar_last_month'])) {
    // Show last selected month
    $currentMonth = substr($_SESSION['plugin_calendar_last_month'], 0, 2);
    $currentYear  = substr($_SESSION['plugin_calendar_last_month'], 2, 4);
} else {
    // show current month
    $currentMonth = date('m');
    $currentYear  = date('Y');
}

if ($currentMonth === date('m') && $currentYear === date('Y')) {
    $today = (int) date('d');
}

$lastDayCurrentMonth = (int) date('t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$dateMonthStart = $currentYear.'-'.$currentMonth.'-01 00:00:01';    // add 1 second to ignore all day events that end at 00:00:00
$dateMonthEnd   = $currentYear.'-'.$currentMonth.'-'.$lastDayCurrentMonth.' 23:59:59';
$eventsMonthDayArray    = array();
$birthdaysMonthDayArray = array();

// if page object is set then integrate css file of this plugin
global $page;
if (isset($page) && $page instanceof HtmlPage) {
    $page->addCssFile(ADMIDIO_URL . FOLDER_PLUGINS . '/calendar/calendar.css');
}

// query of all events
if ($plg_ter_aktiv) {
    $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('DAT'));
    $queryParams = array_merge($catIdParams, array($dateMonthEnd, $dateMonthStart));

    // check if special calendars should be shown
    if (in_array('all', $plg_kal_cat, true)) {
        // show all calendars
        $sqlSyntax = '';
    } else {
        // show only calendars of the parameter $plg_kal_cat
        $sqlSyntax = ' AND cat_name IN (' . Database::getQmForValues($plg_kal_cat) . ')';
        $queryParams = array_merge($queryParams, $plg_kal_cat);
    }

    $sql = 'SELECT DISTINCT dat_id, dat_cat_id, cat_name, dat_begin, dat_end, dat_all_day, dat_location, dat_headline
              FROM '.TBL_DATES.'
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = dat_cat_id
             WHERE cat_id IN ('.Database::getQmForValues($catIdParams).')
               AND dat_begin <= ? -- $dateMonthEnd
               AND dat_end   >= ? -- $dateMonthStart
                   '.$sqlSyntax.'
          ORDER BY dat_begin ASC';
    $datesStatement = $gDb->queryPrepared($sql, $queryParams);

    while ($row = $datesStatement->fetch()) {
        $startDate = new DateTime($row['dat_begin']);
        $endDate   = new DateTime($row['dat_end']);

        // set custom name of plugin for calendar or use default Admidio name
        if ($plg_kal_cat_show) {
            if ($row['cat_name'][3] === '_') {
                $calendarName = $gL10n->get($row['cat_name']);
            } else {
                $calendarName = $row['cat_name'];
            }
            $row['dat_headline'] = $calendarName. ': '. $row['dat_headline'];
        }

        if ($startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
            // event only within one day
            $eventsMonthDayArray[$startDate->format('j')][] = array(
                'dat_id'   => $row['dat_id'],
                'time'     => $startDate->format($gSettingsManager->getString('system_time')),
                'all_day'  => $row['dat_all_day'],
                'location' => $row['dat_location'],
                'headline' => $row['dat_headline'],
                'one_day'  => true
            );
        } else {
            // event within several days

            if ($startDate->format('m') !== $currentMonth) {
                $firstDay = 1;
            } else {
                $firstDay = $startDate->format('j');
            }

            if ($endDate->format('m') !== $currentMonth) {
                $lastDay = $lastDayCurrentMonth;
            } else {
                $lastDay = $endDate->format('j');
            }

            // now add event to every relevant day of month
            for ($i = $firstDay; $i <= $lastDay; ++$i) {
                $eventsMonthDayArray[$i][] = array(
                    'dat_id'   => $row['dat_id'],
                    'time'     => $startDate->format($gSettingsManager->getString('system_time')),
                    'all_day'  => $row['dat_all_day'],
                    'location' => $row['dat_location'],
                    'headline' => $row['dat_headline'],
                    'one_day'  => false
                );
            }
        }
    }
}

// query of all birthdays
if ($plg_geb_aktiv) {
    if (DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
        $sqlYearOfBirthday  = ' EXTRACT(YEAR FROM TO_TIMESTAMP(birthday.usd_value, \'YYYY-MM-DD\')) ';
        $sqlMonthOfBirthday = ' EXTRACT(MONTH FROM TO_TIMESTAMP(birthday.usd_value, \'YYYY-MM-DD\')) ';
        $sqlDayOfBirthday   = ' EXTRACT(DAY FROM TO_TIMESTAMP(birthday.usd_value, \'YYYY-MM-DD\')) ';
    } else {
        $sqlYearOfBirthday  = ' YEAR(birthday.usd_value) ';
        $sqlMonthOfBirthday = ' MONTH(birthday.usd_value) ';
        $sqlDayOfBirthday   = ' DayOfMonth(birthday.usd_value) ';
    }

    switch ($plg_geb_displayNames) {
        case 1:
            $sqlOrderName = 'first_name';
            break;
        case 2:
            $sqlOrderName = 'last_name';
            break;
        case 0: // fallthrough
        default:
            $sqlOrderName = 'last_name, first_name';
    }

    // database query for all birthdays of this month
    $sql = 'SELECT DISTINCT
                   usr_id, last_name.usd_value AS last_name, first_name.usd_value AS first_name, birthday.usd_value AS birthday,
                   ' . $sqlYearOfBirthday . ' AS birthday_year, ' . $sqlMonthOfBirthday . ' AS birthday_month,
                   ' . $sqlDayOfBirthday . ' AS birthday_day
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
        INNER JOIN '.TBL_USERS.'
                ON usr_id = mem_usr_id
        INNER JOIN '.TBL_USER_DATA.' AS birthday
                ON birthday.usd_usr_id = usr_id
               AND birthday.usd_usf_id = ? -- $gProfileFields->getProperty(\'BIRTHDAY\', \'usf_id\')
               AND '.$sqlMonthOfBirthday.' = ? -- $currentMonth
         LEFT JOIN '.TBL_USER_DATA.' AS last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
         LEFT JOIN '.TBL_USER_DATA.' AS first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
             WHERE usr_valid  = true
               AND cat_org_id = ? -- $gCurrentOrgId
               AND rol_id '.$sqlRoleIds.'
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
             ORDER BY birthday_year DESC, birthday_month DESC, birthday_day DESC, ' . $sqlOrderName;

    $queryParams = array(
        $gProfileFields->getProperty('BIRTHDAY', 'usf_id'),
        $currentMonth,
        $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
        $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
        $gCurrentOrgId,
        DATE_NOW,
        DATE_NOW
    );
    $birthdayStatement = $gDb->queryPrepared($sql, $queryParams);

    while ($row = $birthdayStatement->fetch()) {
        $birthdayDate = new DateTime($row['birthday']);

        switch ($plg_geb_displayNames) {
            case 1:
                $name = $row['first_name'];
                break;
            case 2:
                $name = $row['last_name'];
                break;
            case 0: // fallthrough
            default:
                $name = $row['last_name'] . ($row['last_name'] ? ', ' : '') . $row['first_name'];
        }

        $birthdaysMonthDayArray[$birthdayDate->format('j')][] = array(
            'year' => $birthdayDate->format('Y'),
            'age'  => $currentYear - $birthdayDate->format('Y'),
            'name' => $name
        );
    }
}

// Kalender erstellen
$firstWeekdayOfMonth = (int) date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$months = explode(',', $gL10n->get('PLG_CALENDAR_MONTH'));

if ($firstWeekdayOfMonth === 0) {
    $firstWeekdayOfMonth = 7;
}

echo '<div id="plgCalendarContent" class="admidio-plugin-content">
<h3>'.$gL10n->get('DAT_CALENDAR').'</h3>

<table id="plgCalendarTable">
    <tr>';
        if ($plg_ajaxbox) {
            echo '<th style="text-align: center;" class="plgCalendarHeader"><a href="#" onclick="$.get({
                url: \'' . ADMIDIO_URL . FOLDER_PLUGINS . '/' . $pluginFolder . '/calendar.php\',
                cache: false,
                data: \'date_id='.date('mY', mktime(0, 0, 0, $currentMonth - 1, 1, $currentYear)).'\',
                success: function(html) {
                    $(\'#plgCalendarContent\').replaceWith(html);
                    $(\'.admidio-calendar-link\').popover();
                }
            }); return false;">&laquo;</a></th>
            <th colspan="5" style="text-align: center;" class="plgCalendarHeader">'.$months[$currentMonth - 1].' '.$currentYear.'</th>
            <th style="text-align: center;" class="plgCalendarHeader"><a href="#" onclick="$.get({
                url: \'' . ADMIDIO_URL . FOLDER_PLUGINS . '/' . $pluginFolder . '/calendar.php\',
                cache: false,
                data: \'date_id='.date('mY', mktime(0, 0, 0, $currentMonth + 1, 1, $currentYear)).'\',
                success: function(html) {
                    $(\'#plgCalendarContent\').replaceWith(html);
                    $(\'.admidio-calendar-link\').popover();
                }
            }); return false;">&raquo;</a></th>';
        } else {
            echo '<th colspan="7" class="plgCalendarHeader">'.$months[$currentMonth - 1].' '.$currentYear.'</th>';
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
while ($i < $firstWeekdayOfMonth) {
    echo '<td>&nbsp;</td>';
    ++$i;
}

$currentDay = 1;
$boolNewStart = false;

while ($currentDay <= $lastDayCurrentMonth) {
    $terLink  = '';
    $gebLink  = '';
    $htmlContent  = '';
    $textContent  = '';
    $hasEvents    = false;
    $hasBirthdays = false;
    $countEvents  = 0;

    $dateObj = DateTime::createFromFormat('Y-m-j', $currentYear.'-'.$currentMonth.'-'.$currentDay);

    // add events to the calendar
    if ($plg_ter_aktiv) {
        // only show events in dependence of the events module view settings
        if (array_key_exists($currentDay, $eventsMonthDayArray)
        && ($gSettingsManager->getInt('enable_dates_module') === 1
           || ($gSettingsManager->getInt('enable_dates_module') === 2 && $gValidLogin))) {
            $hasEvents = true;

            foreach ($eventsMonthDayArray[$currentDay] as $eventArray) {
                if ($plg_ajaxbox || $countEvents === 0) {
                    if ($eventArray['location'] !== '') {
                        $eventArray['location'] = ', '. $eventArray['location'];
                    }

                    if ($htmlContent !== '' && $plg_ajaxbox) {
                        $htmlContent .= '<br />';
                    }
                    if ($eventArray['all_day'] == 1) {
                        if ($eventArray['one_day']) {
                            $htmlContent .= '<strong>'.$gL10n->get('DAT_ALL_DAY').'</strong> '.$eventArray['headline'].$eventArray['location'];
                            $textContent .= $gL10n->get('DAT_ALL_DAY').' '.$eventArray['headline'].$eventArray['location'];
                        } else {
                            $htmlContent .= '<strong>'.$gL10n->get('PLG_CALENDAR_SEVERAL_DAYS').'</strong> '.$eventArray['headline'].$eventArray['location'];
                            $textContent .= $gL10n->get('PLG_CALENDAR_SEVERAL_DAYS').' '.$eventArray['headline'].$eventArray['location'];
                        }
                    } else {
                        $htmlContent .= '<strong>'.$eventArray['time'].' '.$gL10n->get('SYS_CLOCK').'</strong> '.$eventArray['headline'].$eventArray['location'];
                        $textContent .= $eventArray['time'].' '.$gL10n->get('SYS_CLOCK').' '.$eventArray['headline'].$eventArray['location'];
                    }
                }
                ++$countEvents;
            }

            if ($countEvents > 0) {
                // Link_Target auf Termin-Vorgabe einstellen
                $plgLinkTarget = $plg_link_target_termin;
                $plgLink = SecurityUtils::encodeUrl($plg_link_url, array('date_from' => $dateObj->format('Y-m-d'), 'date_to' => $dateObj->format('Y-m-d')));
            }

            if ($plg_ajaxbox !== 1 && count($eventsMonthDayArray[$currentDay]) > 1) {
                $textContent .= $gL10n->get('PLG_CALENDAR_MORE');
            }
        }
    }

    // add users birthdays to the calendar
    if ($plg_geb_aktiv) {
        if (array_key_exists($currentDay, $birthdaysMonthDayArray) && $plgCalendarShowNames) {
            foreach ($birthdaysMonthDayArray[$currentDay] as $birthdayArray) {
                $hasBirthdays = true;

                if ($htmlContent !== '') {
                    $htmlContent .= '<br />';
                    $textContent .= ', ';
                }

                if ($plg_geb_icon) {
                    $icon = '<i class="admidio-icon-chain fas fa-birthday-cake"></i>';
                } else {
                    $icon = '';
                }

                $htmlContent .= $icon.$birthdayArray['name']. ' ('.$birthdayArray['age'].')';
                $textContent .= $birthdayArray['name']. ' ('.$birthdayArray['age'].')';
            }
        }
    }

    // First pre-assignment of the weekday classes
    $plgLinkClassSaturday = 'plgCalendarSaturday';
    $plgLinkClassSunday   = 'plgCalendarSunday';
    $plgLinkClassWeekday  = 'plgCalendarDay';

    if (!$hasEvents && $hasBirthdays) { // no events but birthdays
        $plgLinkClass = 'geb';
        $plgLinkClassSaturday .= ' plgCalendarBirthDay';
        $plgLinkClassSunday   .= ' plgCalendarBirthDay';
        $plgLinkClassWeekday  .= ' plgCalendarBirthDay';
    }

    if ($hasEvents && !$hasBirthdays) { // events but no birthdays
        $plgLinkClass = 'date';
        $plgLinkClassSaturday .= ' plgCalendarDateDay';
        $plgLinkClassSunday   .= ' plgCalendarDateDay';
        $plgLinkClassWeekday  .= ' plgCalendarDateDay';
    }

    if ($hasEvents && $hasBirthdays) { // events and birthdays
        $plgLinkClass = 'merge';
        $plgLinkClassSaturday .= ' plgCalendarMergeDay';
        $plgLinkClassSunday   .= ' plgCalendarMergeDay';
        $plgLinkClassWeekday  .= ' plgCalendarMergeDay';
    }

    if ($boolNewStart) {
        echo '<tr>';
        $boolNewStart = false;
    }
    $rest = ($currentDay + $firstWeekdayOfMonth - 1) % 7;
    if ($currentDay === $today) {
        echo '<td class="plgCalendarToday">';
    } elseif ($rest === 6) {
        echo '<td class="'.$plgLinkClassSaturday.'">';
    } elseif ($rest === 0) {
        echo '<td class="'.$plgLinkClassSunday.'">';
    } else {
        echo '<td class="'.$plgLinkClassWeekday.'">';
    }

    if ($currentDay === $today || $hasEvents || $hasBirthdays) {
        if (!$hasEvents && $hasBirthdays) {
            // Switch off link URL for birthday by #.
            $plgLink = '#';
            // Set Link_Target for Birthday Default
            $plgLinkTarget = $plg_link_target_geb;
        }

        if ($hasEvents || $hasBirthdays) {
            if ($plg_ajaxbox) {
                if ($terLink !== '' && $gebLink !== '') {
                    $gebLink = '&'. $gebLink;
                }

                // plg_link_class bestimmt das Erscheinungsbild des jeweiligen Links
                echo '<a class="admidio-calendar-link '.$plgLinkClass.'" href="'.$plgLink.'" data-toggle="popover" data-html="true" data-trigger="hover click" data-placement="auto"
                    title="'.$dateObj->format($gSettingsManager->getString('system_date')).'" data-content="'.SecurityUtils::encodeHTML($htmlContent).'" target="'.$plgLinkTarget.'">'.$currentDay.'</a>';
            } else {
                echo '<a class="'.$plgLinkClass.'" href="'.$plgLink.'" title="'.str_replace('"', '', $textContent).'"
                    href="'.$plgLink.'" target="'.$plgLinkTarget.'">'.$currentDay.'</a>';
            }
        } elseif ($currentDay === $today) {
            echo '<span class="plgCalendarToday">'.$currentDay.'</span>';
        }
    } elseif ($rest === 6) {
        echo '<span class="plgCalendarSaturday">'.$currentDay.'</span>';
    } elseif ($rest === 0) {
        echo '<span class="plgCalendarSunday">'.$currentDay.'</span>';
    } else {
        echo $currentDay;
    }
    echo '</td>';
    if ($rest === 0 || $currentDay === $lastDayCurrentMonth) {
        echo '</tr>';
        $boolNewStart = true;
    }

    ++$currentDay;
}
echo '</table>';

if ($currentMonth.$currentYear !== date('mY')) {
    echo '<div id="plgCalendarReset"><a href="#" onclick="$.get({
            url: \'' . ADMIDIO_URL . FOLDER_PLUGINS . '/' . $pluginFolder . '/calendar.php\',
            cache: false,
            data: \'ajax_change&amp;date_id='.date('mY').'\',
            success: function(html) {
                $(\'#plgCalendarContent\').replaceWith(html);
                $(\'.admidio-calendar-link\').popover();
            }
        }); return false;">'.$gL10n->get('PLG_CALENDAR_CURRENT_MONTH').'</a></div>';
}
echo '</div>

<script type="text/javascript"><!--
    $(document).ready(function() {
        $(".admidio-calendar-link").popover();
    });
--></script>';
