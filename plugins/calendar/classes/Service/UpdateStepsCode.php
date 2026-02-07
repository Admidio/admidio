<?php
namespace Calendar\classes\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Calendar\classes\Calendar;
use ReflectionException;

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class UpdateStepsCode
{
    /**
     * @var Database
     */
    private static Database $db;

    /**
     * Set the database
     * @param Database $database The database instance
     */
    public static function setDatabase(Database $database)
    {
        self::$db = $database;
    }

    /**
     * Retrieve previous settings from config file and update the database settings accordingly.
     * @throws Exception|ReflectionException
     */
    public static function updateStep10RetrievePreviousSettings()
    {
        global  $gSettingsManager;

        $pluginCalendar = Calendar::getInstance();
        $configValues = $pluginCalendar::getPluginConfig();

        // check if there is a config.php file with previous settings in the calendar plugin folder
        $configFile = dirname(__DIR__, 4) . '/adm_plugins/calendar/config.php';
        if (file_exists($configFile)) {
            // include the config file to get the previous settings
            include $configFile;

            // update the settings in the database with the previous settings from the config file if they are different to the current settings
            foreach (get_defined_vars() as $varName => $varValue) {
                if ($varName === 'plg_ter_aktiv') {
                    if ($gSettingsManager->getBool('calendar_show_events') !== (bool)$varValue) {
                        $gSettingsManager->set('calendar_show_events', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_geb_aktiv') {
                    if ($gSettingsManager->getBool('calendar_show_birthdays') !== (bool)$varValue) {
                        $gSettingsManager->set('calendar_show_birthdays', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_geb_login') {
                    if ($gSettingsManager->getBool('calendar_show_birthdays_to_guests') !== (bool)$varValue) {
                        $gSettingsManager->set('calendar_show_birthdays_to_guests', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_geb_icon') {
                    if ($gSettingsManager->getBool('calendar_show_birthday_icon') !== (bool)$varValue) {
                        $gSettingsManager->set('calendar_show_birthday_icon', (bool)$varValue);
                    }
                }elseif ($varName === 'plg_geb_displayNames') {
                    if ($gSettingsManager->getInt('calendar_show_birthday_names') !== (int)$varValue) {
                        $gSettingsManager->set('calendar_show_birthday_names', (int)$varValue);
                    }
                } elseif ($varName === 'plg_kal_cat_show') {
                    if ($gSettingsManager->getBool('calendar_show_categories_names') !== (bool)$varValue) {
                        $gSettingsManager->set('calendar_show_categories_names', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_kal_cat') {
                    $categories = (is_array($varValue) && !empty($varValue)) ? $varValue : array('All');
                    if ($categories !== array('All')) {
                        $categoryIds = array();
                        foreach ($categories as $category) {
                            // check if the category name exists and get the category id
                            $sql = 'SELECT cat_id
                                      FROM ' . TBL_CATEGORIES . '
                                     WHERE cat_name = ?
                                       AND cat_type = \'EVT\'';
                            $pdoStatement = self::$db->queryPrepared($sql, array($category));
                            $row = $pdoStatement->fetch();
                            if ($row !== false) {
                                $categoryIds[] = (int)$row['cat_id'];
                            }
                        }
                        // if there are no valid category ids found, get all visible categories for the current user
                        if (empty($categoryIds)) {
                            $categoryIds = array('All');
                        }
                    } else {
                        $categoryIds = array('All');
                    }

                    $categoriesString = implode(',', $categoryIds);
                    // make sure to use 'All' instead of 'all' for consistency
                    $categoriesString = str_ireplace('all', 'All', $categoriesString);

                    if ($gSettingsManager->get('calendar_show_categories') !== $categoriesString) {
                        $gSettingsManager->set('calendar_show_categories', $categoriesString);
                    }
                } elseif ($varName === 'plg_calendar_roles_view_plugin') {
                    $categories = (is_array($varValue) && !empty($varValue)) ? $varValue : array('All');
                    $categoriesString = implode(',', $categories);
                    if ($gSettingsManager->get('calendar_roles_view_plugin') !== $categoriesString) {
                        $gSettingsManager->set('calendar_roles_view_plugin', $categoriesString);
                    }
                } elseif ($varName === 'plg_rolle_sql') {
                    $categories = (is_array($varValue) && !empty($varValue)) ? $varValue : array('All');
                    $categoriesString = implode(',', $categories);
                    if ($gSettingsManager->get('calendar_roles_sql') !== $categoriesString) {
                        $gSettingsManager->set('calendar_roles_sql', $categoriesString);
                    }
                }
            }
        }
    }
}