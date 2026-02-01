<?php
namespace WhoIsOnline\classes\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use WhoIsOnline\classes\WhoIsOnline;
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
        // $gL10n is needed to get the localized rank names from the config file
        global $gL10n, $gSettingsManager;

        $pluginWhoIsOnline = WhoIsOnline::getInstance();
        $configValues = $pluginWhoIsOnline::getPluginConfig();

        // check if there is a config.php file with previous settings in the rwho-is-online plugin folder
        $configFile = dirname(__DIR__, 4) . '/adm_plugins/who-is-online/config.php';
        if (file_exists($configFile)) {
            // include the config file to get the previous settings
            include $configFile;

            // update the settings in the database with the previous settings from the config file if they are different to the current settings
            foreach (get_defined_vars() as $varName => $varValue) {
                if ($varName === 'plg_time_online') {
                    if ($gSettingsManager->getInt('who_is_online_time_still_active') !== (int)$varValue) {
                        $gSettingsManager->set('who_is_online_time_still_active', (int)$varValue);
                    }
                } elseif ($varName === 'plg_show_visitors') {
                    if ($gSettingsManager->getBool('who_is_online_show_visitors') !== (bool)$varValue) {
                        $gSettingsManager->set('who_is_online_show_visitors', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_show_members') {
                    if ($gSettingsManager->getInt('who_is_online_show_members_to_visitors') !== (int)$varValue) {
                        $gSettingsManager->set('who_is_online_show_members_to_visitors', (int)$varValue);
                    }
                } elseif ($varName === 'plg_show_self') {
                    if ($gSettingsManager->getBool('who_is_online_show_self') !== (bool)$varValue) {
                        $gSettingsManager->set('who_is_online_show_self', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_show_users_side_by_side') {
                    if ($gSettingsManager->getBool('who_is_online_show_users_side_by_side') !== (bool)$varValue) {
                        $gSettingsManager->set('who_is_online_show_users_side_by_side', (bool)$varValue);
                    }
                }
            }
        }
    }
}