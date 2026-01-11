<?php
namespace Plugins\RandomPhoto\classes\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Plugins\RandomPhoto\classes\RandomPhoto;
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

        $pluginRandomPhoto = RandomPhoto::getInstance();
        $configValues = $pluginRandomPhoto::getPluginConfig();

        // check if there is a config.php file with previous settings in the random_photo plugin folder
        $configFile = dirname(__DIR__, 4) . '/adm_plugins/random_photo/config.php';
        if (file_exists($configFile)) {
            // include the config file to get the previous settings
            include $configFile;

            // update the settings in the database with the previous settings from the config file if they are different to the current settings
            foreach (get_defined_vars() as $varName => $varValue) {
                if ($varName === 'plg_max_char_per_word') {
                    if ($gSettingsManager->getInt('random_photo_max_char_per_word') !== (int)$varValue) {
                        $gSettingsManager->set('random_photo_max_char_per_word', (int)$varValue);
                    }
                } elseif ($varName === 'plg_photos_max_width') {
                    if ($gSettingsManager->getInt('random_photo_max_width') !== (int)$varValue) {
                        $gSettingsManager->set('random_photo_max_width', (int)$varValue);
                    }
                } elseif ($varName === 'plg_photos_max_height') {
                    if ($gSettingsManager->getInt('random_photo_max_height') !== (int)$varValue) {
                        $gSettingsManager->set('random_photo_max_height', (int)$varValue);
                    }
                } elseif ($varName === 'plg_photos_albums') {
                    if ($gSettingsManager->getInt('random_photo_albums') !== (int)$varValue) {
                        $gSettingsManager->set('random_photo_albums', (int)$varValue);
                    }
                } elseif ($varName === 'plg_photos_picnr') {
                    if ($gSettingsManager->getInt('random_photo_album_photo_number') !== (int)$varValue) {
                        $gSettingsManager->set('random_photo_album_photo_number', (int)$varValue);
                    }
                } elseif ($varName === 'plg_photos_show_link') {
                    if ($gSettingsManager->getBool('random_photo_show_album_link') !== (bool)$varValue) {
                        $gSettingsManager->set('random_photo_show_album_link', (bool)$varValue);
                    }
                }
            }
        }
    }
}