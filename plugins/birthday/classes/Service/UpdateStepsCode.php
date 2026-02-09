<?php
namespace Birthday\classes\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Birthday\classes\Birthday;
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

        $pluginBirthday = Birthday::getInstance();
        $configValues = $pluginBirthday::getPluginConfig();

        // check if there is a config.php file with previous settings in the birthday plugin folder
        $configFile = dirname(__DIR__, 4) . '/adm_plugins/birthday/config.php';
        if (file_exists($configFile)) {
            // include the config file to get the previous settings
            include $configFile;

            // update the settings in the database with the previous settings from the config file if they are different to the current settings
            foreach (get_defined_vars() as $varName => $varValue) {
                if ($varName === 'plg_show_names_extern') {
                    if ($gSettingsManager->getBool('birthday_show_names_extern') !== (bool)$varValue) {
                        $gSettingsManager->set('birthday_show_names_extern', (bool)$varValue);
                    }
                } elseif ($varName === 'birthday_show_names') {
                    if ($gSettingsManager->getInt('birthday_show_names') !== (int)$varValue) {
                        $gSettingsManager->set('birthday_show_names', (int)$varValue);
                    }
                } elseif ($varName === 'plg_show_age') {
                    if ($gSettingsManager->getBool('birthday_show_age') !== (bool)$varValue) {
                        $gSettingsManager->set('birthday_show_age', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_show_alter_anrede') {
                    if ($gSettingsManager->getInt('birthday_show_age_salutation') !== (int)$varValue) {
                        $gSettingsManager->set('birthday_show_age_salutation', (int)$varValue);
                    }
                } elseif ($varName === 'plg_show_hinweis_keiner') {
                    if ($gSettingsManager->getBool('birthday_show_notice_none') !== (bool)$varValue) {
                        $gSettingsManager->set('birthday_show_notice_none', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_show_zeitraum') {
                    if ($gSettingsManager->getInt('birthday_show_past') !== (int)$varValue) {
                        $gSettingsManager->set('birthday_show_past', (int)$varValue);
                    }
                } elseif ($varName === 'plg_show_future') {
                    if ($gSettingsManager->getInt('birthday_show_future') !== (int)$varValue) {
                        $gSettingsManager->set('birthday_show_future', (int)$varValue);
                    }
                } elseif ($varName === 'plg_show_display_limit') {
                    if ($gSettingsManager->getInt('birthday_show_display_limit') !== (int)$varValue) {
                        $gSettingsManager->set('birthday_show_display_limit', (int)$varValue);
                    }
                } elseif ($varName === 'plg_show_email_extern') {
                    if ($gSettingsManager->getInt('birthday_show_email_extern') !== (int)$varValue) {
                        $gSettingsManager->set('birthday_show_email_extern', (int)$varValue);
                    }
                } elseif ($varName === 'plg_birthday_roles_view_plugin') {
                    $categories = (is_array($varValue) && !empty($varValue)) ? $varValue : array('All');
                    $categoriesString = implode(',', $categories);
                    if ($gSettingsManager->get('birthday_roles_view_plugin') !== $categoriesString) {
                        $gSettingsManager->set('birthday_roles_view_plugin', $categoriesString);
                    }
                } elseif ($varName === 'plg_rolle_sql') {
                    $categories = (is_array($varValue) && !empty($varValue)) ? $varValue : array('All');
                    $categoriesString = implode(',', $categories);
                    if ($gSettingsManager->get('birthday_roles_sql') !== $categoriesString) {
                        $gSettingsManager->set('birthday_roles_sql', $categoriesString);
                    }
                } elseif ($varName === 'plg_sort_sql') {
                    if (strtolower($gSettingsManager->getString('birthday_sort_sql')) !== strtolower((string)$varValue)) {
                        $gSettingsManager->set('birthday_sort_sql', (string)$varValue);
                    }
                }
            }
        }
    }
}