<?php
namespace EventList\classes\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use EventList\classes\EventList;
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

        $pluginEventList = EventList::getInstance();
        $configValues = $pluginEventList::getPluginConfig();

        // check if there is a config.php file with previous settings in the event-list plugin folder
        $configFile = dirname(__DIR__, 4) . '/adm_plugins/event-list/config.php';
        if (file_exists($configFile)) {
            // include the config file to get the previous settings
            include $configFile;

            // update the settings in the database with the previous settings from the config file if they are different to the current settings
            foreach (get_defined_vars() as $varName => $varValue) {
                if ($varName === 'plg_max_number_events_shown') {
                    if ($gSettingsManager->getInt('event_list_events_count') !== (int)$varValue) {
                        $gSettingsManager->set('event_list_events_count', (int)$varValue);
                    }
                } elseif ($varName === 'plg_show_date_end') {
                    if ($gSettingsManager->getBool('event_list_show_event_date_end') !== (bool)$varValue) {
                        $gSettingsManager->set('event_list_show_event_date_end', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_events_show_preview') {
                    if ($gSettingsManager->getInt('event_list_show_preview_chars') !== (int)$varValue) {
                        $gSettingsManager->set('event_list_show_preview_chars', (int)$varValue);
                    }
                } elseif ($varName === 'plgShowFullDescription') {
                    if ($gSettingsManager->getBool('event_list_show_full_description') !== (bool)$varValue) {
                        $gSettingsManager->set('event_list_show_full_description', (bool)$varValue);
                    }
                }elseif ($varName === 'plg_max_char_per_word') {
                    if ($gSettingsManager->getInt('event_list_chars_before_linebreak') !== (int)$varValue) {
                        $gSettingsManager->set('event_list_chars_before_linebreak', (int)$varValue);
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
                    if ($gSettingsManager->get('event_list_displayed_categories') !== $categoriesString) {
                        $gSettingsManager->set('event_list_displayed_categories', $categoriesString);
                    }
                }
            }
        }
    }
}