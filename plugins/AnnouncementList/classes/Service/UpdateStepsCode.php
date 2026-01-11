<?php
namespace Plugins\AnnouncementList\classes\Service;

use Admidio\Infrastructure\Database;
use Plugins\AnnouncementList\classes\AnnouncementList;

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
     * Add default fields for the inventory module.
     * @throws Exception
     */
    public static function updateStep10RetrievePreviousSettings()
    {
        global  $gSettingsManager;

        $pluginAnnouncementList = AnnouncementList::getInstance();
        $configValues = $pluginAnnouncementList::getPluginConfig();

        // check if there is a config.php file with previous settings in the announcement-list plugin folder
        $configFile = dirname(__DIR__, 4) . '/adm_plugins/announcement-list/config.php';
        if (file_exists($configFile)) {
            // include the config file to get the previous settings
            include $configFile;

            // update the settings in the database with the previous settings from the config file if they are different to the current settings
            foreach (get_defined_vars() as $varName => $varValue) {
                if ($varName === 'plg_announcements_count') {
                    if ($gSettingsManager->getInt('announcement_list_announcements_count') !== (int)$varValue) {
                        $gSettingsManager->set('announcement_list_announcements_count', (int)$varValue);
                    }
                } elseif ($varName === 'plg_show_preview') {
                    if ($gSettingsManager->getInt('announcement_list_show_preview_chars') !== (int)$varValue) {
                        $gSettingsManager->set('announcement_list_show_preview_chars', (int)$varValue);
                    }
                } elseif ($varName === 'plgShowFullDescription') {
                    if ($gSettingsManager->getBool('announcement_list_show_full_description') !== (bool)$varValue) {
                        $gSettingsManager->set('announcement_list_show_full_description', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_max_char_per_word') {
                    if ($gSettingsManager->getInt('announcement_list_chars_before_linebreak') !== (int)$varValue) {
                        $gSettingsManager->set('announcement_list_chars_before_linebreak', (int)$varValue);
                    }
                } elseif ($varName === 'plg_categories') {
                    $categories = (is_array($varValue) && !empty($varValue)) ? $varValue : array('All');
                    if ($categories !== array('All')) {
                        $categoryIds = array();
                        foreach ($categories as $category) {
                            // check if the category name exists and get the category id
                            $sql = 'SELECT cat_id
                                      FROM ' . TBL_CATEGORIES . '
                                     WHERE cat_name = ?
                                       AND cat_type = \'ANN\'';
                            $pdoStatement = $gDb->queryPrepared($sql, array($category));
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
                    if ($gSettingsManager->get('announcement_list_displayed_categories') !== $categoriesString) {
                        $gSettingsManager->set('announcement_list_displayed_categories', $categoriesString);
                    }
                }
            }
        }
    }
}