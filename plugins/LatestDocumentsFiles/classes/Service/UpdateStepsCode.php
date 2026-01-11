<?php
namespace Plugins\LatestDocumentsFiles\classes\Service;

use Admidio\Infrastructure\Database;
use Plugins\LatestDocumentsFiles\classes\LatestDocumentsFiles;

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

        $pluginLatestDocumentsFiles = LatestDocumentsFiles::getInstance();
        $configValues = $pluginLatestDocumentsFiles::getPluginConfig();

        // check if there is a config.php file with previous settings in the latest-documents-files plugin folder
        $configFile = dirname(__DIR__, 4) . '/adm_plugins/latest-documents-files/config.php';
        if (file_exists($configFile)) {
            // include the config file to get the previous settings
            include $configFile;

            // update the settings in the database with the previous settings from the config file if they are different to the current settings
            foreach (get_defined_vars() as $varName => $varValue) {
                if ($varName === 'plgCountFiles') {
                    if ($gSettingsManager->getInt('latest_documents_files_files_count') !== (int)$varValue) {
                        $gSettingsManager->set('latest_documents_files_files_count', (int)$varValue);
                    }
                } elseif ($varName === 'plgMaxCharsFilename') {
                    if ($gSettingsManager->getInt('latest_documents_files_max_chars_filename') !== (int)$varValue) {
                        $gSettingsManager->set('latest_documents_files_max_chars_filename', (int)$varValue);
                    }
                } elseif ($varName === 'plg_show_upload_timestamp') {
                    if ($gSettingsManager->getBool('latest_documents_files_show_upload_timestamp') !== (bool)$varValue) {
                        $gSettingsManager->set('latest_documents_files_show_upload_timestamp', (bool)$varValue);
                    }
                }
            }
        }
    }
}