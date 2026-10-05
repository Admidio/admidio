<?php

namespace AdmidioPlugin\Calendar;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Service\RolesService;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\Calendar\Presenter\CalendarPreferencesPresenter;

use Exception;
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
final class Calendar
{
    private static bool $calendarShowNames = false;
    private static array $months = array();
    private static string $currentMonth = '';
    private static string $currentYear = '';
    private static int $today = 0;
    private static int $lastDayCurrentMonth = 0;
    private static bool $getDatId = false;

    private static array $pluginConfig = array();

    /**
     * The directory of this plugin, which is its only identity.
     */
    public const PLUGIN_ID = 'calendar';

    /**
     * Where the widget is placed on the overview page as long as nobody moved it.
     */
    public const DEFAULT_SEQUENCE = 3;

    /**
     * The value a category or role list has as long as nobody narrowed it down.
     */
    private const ALL = array('All');

    /**
     * Announce the widget and the preferences panel. This is what plugin.php calls.
     * @return void
     */
    public static function register(): void
    {
        $plugin = PluginRegistry::get(self::PLUGIN_ID);
        if ($plugin === null) {
            return;
        }

        PluginWidget::register($plugin, array(self::class, 'renderWidget'), array(
            'sequence' => self::DEFAULT_SEQUENCE
        ));

        Hooks::addFilter(
            PluginPanel::HOOK,
            static function (array $panels) use ($plugin): array {
                global $gL10n;

                $panels[] = array(
                    'id' => PluginPanel::normalizeId($plugin->id),
                    'title' => $gL10n->get($plugin->name),
                    'icon' => $plugin->icon,
                    'sequence' => self::DEFAULT_SEQUENCE,
                    'create' => array(CalendarPreferencesPresenter::class, 'createForm')
                );

                return $panels;
            },
            PluginPanel::DEFAULT_SEQUENCE,
            1,
            $plugin->id
        );
    }

    /**
     * The settings of the plugin, with the category and the two role lists resolved.
     *
     * A list that nobody narrowed down holds the sentinel "All", which means everything the current
     * user may see and is turned into the actual IDs here.
     * @param Plugin $plugin
     * @return array<string,mixed>
     * @throws Exception
     */
    public static function getConfig(Plugin $plugin): array
    {
        global $gCurrentUser;

        $config = $plugin->getSettingValues();

        if (($config['calendar_show_categories'] ?? null) === self::ALL) {
            $config['calendar_show_categories'] = $gCurrentUser->getAllVisibleCategories('EVT');
        }
        foreach (array('calendar_roles_view_plugin', 'calendar_roles_sql') as $key) {
            if (($config[$key] ?? null) === self::ALL) {
                $config[$key] = self::getAvailableRoles(1, true);
            }
        }

        return $config;
    }

    /**
     * Build the widget of the overview page, and the fragment that the month buttons of the calendar
     * request. Whether it is shown at all was decided before this is called, by the preference
     * **calendar_plugin_enabled**.
     * @param PagePresenter $page
     * @param Plugin $plugin
     * @param string $dateId Month and year the calendar should show, as **mmyyyy**. Empty for the
     *                       month the visitor last looked at, or the current one.
     * @return string
     * @throws Exception|\Smarty\Exception
     */
    public static function renderWidget(PagePresenter $page, Plugin $plugin, string $dateId = ''): string
    {
        global $gSettingsManager, $gL10n;

        $variables = array('name' => $plugin->id, 'message' => '');

        if ($gSettingsManager->getInt('events_module_enabled') === 0
            || $gSettingsManager->getInt('announcements_module_enabled') === 0) {
            $variables['message'] = $gL10n->get('SYS_MODULE_DISABLED');

            return $plugin->renderTemplate($page, 'plugin.calendar.tpl', $variables);
        }

        self::initParams(array('date_id' => $dateId));
        self::$pluginConfig = self::getConfig($plugin);
        $tableContent = self::getCalendarsData();

        $variables['calendarUrl'] = $plugin->getUrl('index.php');
        $variables['monthYearHeadline'] = self::$months[(int) self::$currentMonth - 1] . ' ' . self::$currentYear;
        $variables['monthYear'] = self::$currentMonth . self::$currentYear;
        $variables['currentMonthYear'] = date('mY');
        $variables['dateIdLastMonth'] = date('mY', mktime(0, 0, 0, (int)self::$currentMonth - 1, 1, (int)self::$currentYear));
        $variables['dateIdNextMonth'] = date('mY', mktime(0, 0, 0, (int)self::$currentMonth + 1, 1, (int)self::$currentYear));
        $variables['tableContent'] = $tableContent;

        return $plugin->renderTemplate($page, 'plugin.calendar.tpl', $variables);
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
                        $plgLink = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', array('date_from' => $dateObj->format('Y-m-d'), 'date_to' => $dateObj->format('Y-m-d')));
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
        if (!empty(self::$pluginConfig['calendar_roles_sql'])) {
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
            if (DB_TYPE === Database::PDO_ENGINE_PGSQL) {
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

        // reset get date id flag before init
        self::$getDatId = false;

        // init parameters
        if (isset($params['date_id']) && $params['date_id'] !== '') {
            self::$getDatId = true;
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
}
