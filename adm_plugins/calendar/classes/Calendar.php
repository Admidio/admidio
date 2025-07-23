<?php

namespace Plugins\Calendar\classes;

use Admidio\Roles\Service\RolesService;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Plugins\PluginAbstract;

use InvalidArgumentException;
use Exception;
use Throwable;
use DateTime;

/**
 ***********************************************************************************************
 * Calendar
 *
 * Plugin shows the actual month with all the events and birthdays that are
 * coming. This plugin can be used to show the Admidio events and birthdays in a
 * sidebar within Admidio or in an external website.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class Calendar extends PluginAbstract
{
    private static bool $calendarShowNames = false;
    private static array $months = array();
    private static string $currentMonth = '';
    private static string $currentYear = '';
    private static int $today = 0;
    private static int $lastDayCurrentMonth = 0;

    private static array $pluginConfig = array();

    /** 
     * Get the plugin configuration
     * @return array Returns the plugin configuration
     */
    public static function getPluginConfig() : array
    {
        global $gCurrentUser;

        // get the plugin config from the parent class
        $config = parent::getPluginConfig();

        // if the key equals 'calendar_show_categories' and the value is still the default value, retrieve the roles from the database
        if (array_key_exists('calendar_show_categories', $config) && $config['calendar_show_categories']['value'] === self::$defaultConfig['calendar_show_categories']['value']) {
            $config['calendar_show_categories']['value'] = $gCurrentUser->getAllVisibleCategories('EVT');
        }

        // if the key equals 'calendar_roles_view_plugin' and the value is still the default value, retrieve the roles from the database
        if (array_key_exists('calendar_roles_view_plugin', $config) && $config['calendar_roles_view_plugin']['value'] === self::$defaultConfig['calendar_roles_view_plugin']['value']) {
            $config['calendar_roles_view_plugin']['value'] = self::getAvailableRoles(1, true);
        } 
        // if the key equals 'calendar_roles_sql' and the value is still the default value, retrieve the roles from the database
        if (array_key_exists('calendar_roles_sql', $config) && $config['calendar_roles_sql']['value'] === self::$defaultConfig['calendar_roles_sql']['value']) {
            $config['calendar_roles_sql']['value'] = self::getAvailableRoles(1, true);
        }
        return $config;
    }
    
    /**
     * Get the plugin configuration values
     * @return array Returns the plugin configuration values
     */
    public static function getPluginConfigValues() : array
    {
        global $gCurrentUser;

        // get the plugin config values from the parent class
        $config = parent::getPluginConfigValues();

        // if the key equals 'calendar_show_categories' and the value is still the default value, retrieve the roles from the database
        if (array_key_exists('calendar_show_categories', $config) && $config['calendar_show_categories'] === self::$defaultConfig['calendar_show_categories']['value']) {
            $config['calendar_show_categories'] = $gCurrentUser->getAllVisibleCategories('EVT');
        }
        // if the key equals 'calendar_roles_view_plugin' and the value is still the default value, retrieve the roles from the database
        if (array_key_exists('calendar_roles_view_plugin', $config) && $config['calendar_roles_view_plugin'] === self::$defaultConfig['calendar_roles_view_plugin']['value']) {
            $config['calendar_roles_view_plugin'] = self::getAvailableRoles(1, true);
        }
        // if the key equals 'calendar_roles_sql' and the value is still the default value, retrieve the roles from the database
        if (array_key_exists('calendar_roles_sql', $config) && $config['calendar_roles_sql'] === self::$defaultConfig['calendar_roles_sql']['value']) {
            $config['calendar_roles_sql'] = self::getAvailableRoles(1, true);
        }

        return $config;
    }

    /**
     * Get the available roles for the calendar plugin
     * @param int $roleType The type of roles to retrieve (0 for inactive, 1 for active, 2 for only event participation roles)
     * @param bool $onlyIds If true, only the IDs of the roles are returned
     * @return array Returns an array with the available roles
     */
    public static function getAvailableRoles($roleType = 1, bool $onlyIds = false): array {
        global $gDb;

        $allRolesSet = array();
        $rolesService = new RolesService($gDb);
        $data = $rolesService->findAll($roleType);

        foreach ($data as $rowViewRoles) {
            if ($onlyIds) {
                // If only the IDs are requested, return an array with the role IDs
                $allRolesSet[] = $rowViewRoles['rol_id'];
            } else {
                // Each role is now added to this array
                $allRolesSet[] = array(
                    $rowViewRoles['rol_id'], // ID 
                    $rowViewRoles['rol_name']
                );
            }
        }
        return $allRolesSet;
    }

    private static function createCalendar(array $eventsMonthDayArray, array $birthdaysMonthDayArray) : string
    {
        global $gSettingsManager, $gL10n, $gValidLogin;
        // Kalender erstellen
        $firstWeekdayOfMonth = (int)date('w', mktime(0, 0, 0, self::$currentMonth, 1, self::$currentYear));
        self::$months = explode(',', $gL10n->get('PLG_CALENDAR_MONTH'));

        if ($firstWeekdayOfMonth === 0) {
            $firstWeekdayOfMonth = 7;
        }

        $tableContent = '<tr>';
        $i = 1;
        while ($i < $firstWeekdayOfMonth) {
            $tableContent .= '<td>&nbsp;</td>';
            ++$i;
        }

        $currentDay = 1;
        $boolNewStart = false;

        while ($currentDay <= self::$lastDayCurrentMonth) {
            $terLink = '';
            $gebLink = '';
            $htmlContent = '';
            $textContent = '';
            $hasEvents = false;
            $hasBirthdays = false;
            $countEvents = 0;

            $dateObj = DateTime::createFromFormat('Y-m-j', self::$currentYear . '-' . self::$currentMonth . '-' . $currentDay);

            // add events to the calendar
            if (self::$pluginConfig['calendar_show_events']) {
                // only show events in dependence of the events module view settings
                if (array_key_exists($currentDay, $eventsMonthDayArray)
                    && ($gSettingsManager->getInt('events_module_enabled') === 1
                        || ($gSettingsManager->getInt('events_module_enabled') === 2 && $gValidLogin))) {
                    $hasEvents = true;

                    foreach ($eventsMonthDayArray[$currentDay] as $eventArray) {
                        if ($eventArray['location'] !== '') {
                            $eventArray['location'] = ', ' . $eventArray['location'];
                        }

                        if ($htmlContent !== '') {
                            $htmlContent .= '<br />';
                        }
                        if ($eventArray['all_day'] == 1) {
                            if ($eventArray['one_day']) {
                                $htmlContent .= '<strong>' . $gL10n->get('SYS_ALL_DAY') . '</strong> ' . $eventArray['headline'] . $eventArray['location'];
                                $textContent .= $gL10n->get('SYS_ALL_DAY') . ' ' . $eventArray['headline'] . $eventArray['location'];
                            } else {
                                $htmlContent .= '<strong>' . $gL10n->get('PLG_CALENDAR_SEVERAL_DAYS') . '</strong> ' . $eventArray['headline'] . $eventArray['location'];
                                $textContent .= $gL10n->get('PLG_CALENDAR_SEVERAL_DAYS') . ' ' . $eventArray['headline'] . $eventArray['location'];
                            }
                        } else {
                            $htmlContent .= '<strong>' . $eventArray['time'] . ' ' . $gL10n->get('SYS_CLOCK') . '</strong> ' . $eventArray['headline'] . $eventArray['location'];
                            $textContent .= $eventArray['time'] . ' ' . $gL10n->get('SYS_CLOCK') . ' ' . $eventArray['headline'] . $eventArray['location'];
                        }
                        ++$countEvents;
                    }

                    if ($countEvents > 0) {
                        $plgLink = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events/events.php', array('date_from' => $dateObj->format('Y-m-d'), 'date_to' => $dateObj->format('Y-m-d')));
                    }
                }
            }

            // add users birthdays to the calendar
            if (self::$pluginConfig['calendar_show_birthdays']) {
                if (array_key_exists($currentDay, $birthdaysMonthDayArray) && self::$calendarShowNames) {
                    foreach ($birthdaysMonthDayArray[$currentDay] as $birthdayArray) {
                        $hasBirthdays = true;

                        if ($htmlContent !== '') {
                            $htmlContent .= '<br />';
                            $textContent .= ', ';
                        }

                        if (self::$pluginConfig['calendar_show_birthday_icon']) {
                            $icon = '<i class="admidio-icon-chain bi bi-cake2-fill"></i>';
                        } else {
                            $icon = '';
                        }

                        $htmlContent .= $icon . $birthdayArray['name'] . ' (' . $birthdayArray['age'] . ')';
                        $textContent .= $birthdayArray['name'] . ' (' . $birthdayArray['age'] . ')';
                    }
                }
            }

            // First pre-assignment of the weekday classes
            $plgLinkClassSaturday = 'plgCalendarSaturday';
            $plgLinkClassSunday = 'plgCalendarSunday';
            $plgLinkClassWeekday = 'plgCalendarDay';

            if (!$hasEvents && $hasBirthdays) { // no events but birthdays
                $plgLinkClass = 'geb';
                $plgLinkClassSaturday .= ' plgCalendarBirthDay';
                $plgLinkClassSunday .= ' plgCalendarBirthDay';
                $plgLinkClassWeekday .= ' plgCalendarBirthDay';
            }

            if ($hasEvents && !$hasBirthdays) { // events but no birthdays
                $plgLinkClass = 'date';
                $plgLinkClassSaturday .= ' plgCalendarDateDay';
                $plgLinkClassSunday .= ' plgCalendarDateDay';
                $plgLinkClassWeekday .= ' plgCalendarDateDay';
            }

            if ($hasEvents && $hasBirthdays) { // events and birthdays
                $plgLinkClass = 'merge';
                $plgLinkClassSaturday .= ' plgCalendarMergeDay';
                $plgLinkClassSunday .= ' plgCalendarMergeDay';
                $plgLinkClassWeekday .= ' plgCalendarMergeDay';
            }

            if ($boolNewStart) {
                $tableContent .= '<tr>';
                $boolNewStart = false;
            }
            $rest = ($currentDay + $firstWeekdayOfMonth - 1) % 7;
            if ($currentDay === self::$today) {
                $tableContent .=  '<td class="plgCalendarToday">';
            } elseif ($rest === 6) {
                $tableContent .=  '<td class="' . $plgLinkClassSaturday . '">';
            } elseif ($rest === 0) {
                $tableContent .=  '<td class="' . $plgLinkClassSunday . '">';
            } else {
                $tableContent .=  '<td class="' . $plgLinkClassWeekday . '">';
            }

            if ($currentDay === self::$today || $hasEvents || $hasBirthdays) {
                if (!$hasEvents && $hasBirthdays) {
                    // Switch off link URL for birthday by #.
                    $plgLink = '#';
                }

                if ($hasEvents || $hasBirthdays) {
                    if ($terLink !== '' && $gebLink !== '') {
                        $gebLink = '&' . $gebLink;
                    }

                    // plg_link_class bestimmt das Erscheinungsbild des jeweiligen Links
                    $tableContent .=  '<a class="admidio-calendar-link ' . $plgLinkClass . '" href="' . $plgLink . '" data-bs-toggle="popover" data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                    title="' . $dateObj->format($gSettingsManager->getString('system_date')) . '" data-bs-content="' . SecurityUtils::encodeHTML($htmlContent) . '">' . $currentDay . '</a>';
                } elseif ($currentDay === self::$today) {
                    $tableContent .=  '<span class="plgCalendarToday">' . $currentDay . '</span>';
                }
            } elseif ($rest === 6) {
                $tableContent .=  '<span class="plgCalendarSaturday">' . $currentDay . '</span>';
            } elseif ($rest === 0) {
                $tableContent .=  '<span class="plgCalendarSunday">' . $currentDay . '</span>';
            } else {
                $tableContent .=  $currentDay;
            }
            $tableContent .=  '</td>';
            if ($rest === 0 || $currentDay === self::$lastDayCurrentMonth) {
                $tableContent .=  '</tr>';
                $boolNewStart = true;
            }

            ++$currentDay;
        }

        return $tableContent;
    }

    private static function getCalendarsData() : string
    {
        global $gSettingsManager, $gCurrentUser, $gDb, $gL10n, $gProfileFields, $gValidLogin, $gDbType, $gCurrentOrgId;

        self::$pluginConfig = self::getPluginConfigValues();

        // check if only members of configured roles could view birthday
        if ($gValidLogin) {
            if (isset(self::$pluginConfig['calendar_roles_view_plugin']) && count(self::$pluginConfig['calendar_roles_view_plugin']) > 0) {
                // current user must be member of at least one listed role
                if (count(array_intersect(self::$pluginConfig['calendar_roles_view_plugin'], $gCurrentUser->getRoleMemberships())) > 0) {
                    self::$calendarShowNames = true;
                }
            }
        } else {
            if (self::$pluginConfig['calendar_show_birthdays_to_guests']) {
                // every visitor is allowed to view birthdays
                self::$calendarShowNames = true;
            }
        }

        // Check if the role condition has been set
        if (isset(self::$pluginConfig['calendar_roles_sql']) && is_array(self::$pluginConfig['calendar_roles_sql']) && count(self::$pluginConfig['calendar_roles_sql']) > 0) {
            $sqlRoleIds = 'IN (' . implode(',', self::$pluginConfig['calendar_roles_sql']) . ')';
        } else {
            $sqlRoleIds = 'IS NOT NULL';
        }

        $dateMonthStart = self::$currentYear . '-' . self::$currentMonth . '-01 00:00:01';    // add 1 second to ignore all day events that end at 00:00:00
        $dateMonthEnd = self::$currentYear . '-' . self::$currentMonth . '-' . self::$lastDayCurrentMonth . ' 23:59:59';
        $eventsMonthDayArray = array();
        $birthdaysMonthDayArray = array();

        // query of all events
        if (self::$pluginConfig['calendar_show_events']) {
            $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('EVT'));
            $queryParams = array_merge($catIdParams, array($dateMonthEnd, $dateMonthStart));

            // check if special calendars should be shown
            $allCategories = $gCurrentUser->getAllVisibleCategories('EVT');
            $selectedCategories = self::$pluginConfig['calendar_show_categories'];

            sort($allCategories);
            sort($selectedCategories);

            if ($allCategories == $selectedCategories) {
                // show all calendars
                $sqlSyntax = '';
            } else {
                // show only calendars of the parameter calendar_show_categories
                $sqlSyntax = ' AND cat_name IN (' . Database::getQmForValues(self::$pluginConfig['calendar_show_categories']) . ')';
                $queryParams = array_merge($queryParams, self::$pluginConfig['calendar_show_categories']);
            }

            $sql = 'SELECT DISTINCT dat_id, dat_cat_id, cat_name, dat_begin, dat_end, dat_all_day, dat_location, dat_headline
                FROM ' . TBL_EVENTS . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = dat_cat_id
                WHERE cat_id IN (' . Database::getQmForValues($catIdParams) . ')
                AND dat_begin <= ? -- $dateMonthEnd
                AND dat_end   >= ? -- $dateMonthStart
                    ' . $sqlSyntax . '
            ORDER BY dat_begin ASC';
            $datesStatement = $gDb->queryPrepared($sql, $queryParams);

            while ($row = $datesStatement->fetch()) {
                $startDate = new DateTime($row['dat_begin']);
                $endDate = new DateTime($row['dat_end']);

                // set custom name of plugin for calendar or use default Admidio name
                if (self::$pluginConfig['calendar_show_categories_names']) {
                    if ($row['cat_name'][3] === '_') {
                        $calendarName = $gL10n->get($row['cat_name']);
                    } else {
                        $calendarName = $row['cat_name'];
                    }
                    $row['dat_headline'] = $calendarName . ': ' . $row['dat_headline'];
                }

                if ($startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
                    // event only within one day
                    $eventsMonthDayArray[$startDate->format('j')][] = array(
                        'dat_id' => $row['dat_id'],
                        'time' => $startDate->format($gSettingsManager->getString('system_time')),
                        'all_day' => $row['dat_all_day'],
                        'location' => $row['dat_location'],
                        'headline' => $row['dat_headline'],
                        'one_day' => true
                    );
                } else {
                    // event within several days

                    if ($startDate->format('m') !== self::$currentMonth) {
                        $firstDay = 1;
                    } else {
                        $firstDay = $startDate->format('j');
                    }

                    if ($endDate->format('m') !== self::$currentMonth) {
                        $lastDay = self::$lastDayCurrentMonth;
                    } else {
                        $lastDay = $endDate->format('j');
                    }

                    // now add event to every relevant day of month
                    for ($i = $firstDay; $i <= $lastDay; ++$i) {
                        $eventsMonthDayArray[$i][] = array(
                            'dat_id' => $row['dat_id'],
                            'time' => $startDate->format($gSettingsManager->getString('system_time')),
                            'all_day' => $row['dat_all_day'],
                            'location' => $row['dat_location'],
                            'headline' => $row['dat_headline'],
                            'one_day' => false
                        );
                    }
                }
            }
        }

        // query of all birthdays
        if (self::$pluginConfig['calendar_show_birthdays']) {
            if (DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
                $sqlYearOfBirthday = ' EXTRACT(YEAR FROM TO_TIMESTAMP(birthday.usd_value, \'YYYY-MM-DD\')) ';
                $sqlMonthOfBirthday = ' EXTRACT(MONTH FROM TO_TIMESTAMP(birthday.usd_value, \'YYYY-MM-DD\')) ';
                $sqlDayOfBirthday = ' EXTRACT(DAY FROM TO_TIMESTAMP(birthday.usd_value, \'YYYY-MM-DD\')) ';
            } else {
                $sqlYearOfBirthday = ' YEAR(birthday.usd_value) ';
                $sqlMonthOfBirthday = ' MONTH(birthday.usd_value) ';
                $sqlDayOfBirthday = ' DayOfMonth(birthday.usd_value) ';
            }

            switch (self::$pluginConfig['calendar_show_birthday_names']) {
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
                FROM ' . TBL_MEMBERS . '
            INNER JOIN ' . TBL_ROLES . '
                    ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
            INNER JOIN ' . TBL_USERS . '
                    ON usr_id = mem_usr_id
            INNER JOIN ' . TBL_USER_DATA . ' AS birthday
                    ON birthday.usd_usr_id = usr_id
                AND birthday.usd_usf_id = ? -- $gProfileFields->getProperty(\'BIRTHDAY\', \'usf_id\')
                AND ' . $sqlMonthOfBirthday . ' = ? -- $currentMonth
            LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                    ON last_name.usd_usr_id = usr_id
                AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
            LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                    ON first_name.usd_usr_id = usr_id
                AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                WHERE usr_valid  = true
                AND cat_org_id = ? -- $gCurrentOrgId
                AND rol_id ' . $sqlRoleIds . '
                AND mem_begin <= ? -- DATE_NOW
                AND mem_end    > ? -- DATE_NOW
                ORDER BY birthday_year DESC, birthday_month DESC, birthday_day DESC, ' . $sqlOrderName;

            $queryParams = array(
                $gProfileFields->getProperty('BIRTHDAY', 'usf_id'),
                self::$currentMonth,
                $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
                $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
                $gCurrentOrgId,
                DATE_NOW,
                DATE_NOW
            );
            $birthdayStatement = $gDb->queryPrepared($sql, $queryParams);

            while ($row = $birthdayStatement->fetch()) {
                $birthdayDate = new DateTime($row['birthday']);

                switch (self::$pluginConfig['calendar_show_birthday_names']) {
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
                    'age' => self::$currentYear - $birthdayDate->format('Y'),
                    'name' => $name
                );
            }
        }

        return self::createCalendar($eventsMonthDayArray, $birthdaysMonthDayArray);
    }

    public static function initParams(array $params = array()) : bool
    {
        // check if params is an array
        if (!is_array($params))
        {
            throw new InvalidArgumentException('Config must be an "array".');
        }

        // init parameters
        if (isset($params['date_id']) && $params['date_id'] !== '') {
            // Read Date ID or generate current month and year
            self::$currentMonth = substr($params['date_id'], 0, 2);
            self::$currentYear = substr($params['date_id'], 2, 4);
            $_SESSION['plugin_calendar_last_month'] = self::$currentMonth . self::$currentYear;
        } elseif (isset($_SESSION['plugin_calendar_last_month'])) {
            // Show last selected month
            self::$currentMonth = substr($_SESSION['plugin_calendar_last_month'], 0, 2);
            self::$currentYear = substr($_SESSION['plugin_calendar_last_month'], 2, 4);
        } else {
            // show current month
            self::$currentMonth = date('m');
            self::$currentYear = date('Y');
        }

        if (self::$currentMonth === date('m') && self::$currentYear === date('Y')) {
            self::$today = (int)date('d');
        }

        self::$lastDayCurrentMonth = (int)date('t', mktime(0, 0, 0, self::$currentMonth, 1, self::$currentYear));

        return true;
    }

    /**
     * @param PagePresenter $page
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doRender($page = null) : bool
    {
        global $gSettingsManager, $gL10n, $gValidLogin;

        // show the announcement list
        try {
            $rootPath = dirname(__DIR__, 3);
            $pluginFolder = basename(self::$pluginPath);

            require_once($rootPath . '/system/common.php');

            $calendarPlugin = new Overview($pluginFolder);
            // check if the plugin is installed
            if (!self::isInstalled()) {
                throw new InvalidArgumentException($gL10n->get('SYS_PLUGIN_NOT_INSTALLED'));
            }
            if ($gSettingsManager->getInt('events_module_enabled') > 0 && $gSettingsManager->getInt('announcements_module_enabled') > 0) {
                if ($gSettingsManager->getInt('calendar_plugin_enabled') === 1 || ($gSettingsManager->getInt('calendar_plugin_enabled') === 2 && $gValidLogin)) {

                    $tableContent = self::getCalendarsData();

                    header('Content-Type: text/html; charset=utf-8');

                    $calendarPlugin->assignTemplateVariable('pluginFolder', $pluginFolder);
                    $calendarPlugin->assignTemplateVariable('monthYearHeadline', self::$months[(int) self::$currentMonth - 1] . ' ' . self::$currentYear);
                    $calendarPlugin->assignTemplateVariable('monthYear', self::$currentMonth . self::$currentYear);
                    $calendarPlugin->assignTemplateVariable('currentMonthYear', date('mY'));
                    $calendarPlugin->assignTemplateVariable('dateIdLastMonth', date('mY', mktime(0, 0, 0, self::$currentMonth - 1, 1, self::$currentYear)));
                    $calendarPlugin->assignTemplateVariable('dateIdNextMonth', date('mY', mktime(0, 0, 0, self::$currentMonth + 1, 1, self::$currentYear)));
                    $calendarPlugin->assignTemplateVariable('tableContent', $tableContent);
                } else {
                    $calendarPlugin->assignTemplateVariable('message',$gL10n->get('PLG_BIRTHDAY_NO_ENTRIES_VISITORS'));
                }
            } else {
                $calendarPlugin->assignTemplateVariable('message', $gL10n->get('SYS_MODULE_DISABLED'));
            }

            if (isset($page)) {
                echo $calendarPlugin->html('plugin.calendar.tpl');
            } else {
                $calendarPlugin->showHtmlPage('plugin.calendar.tpl');
            }
        } catch (Throwable $e) {
            echo $e->getMessage();
        }

        return true;
    }
}