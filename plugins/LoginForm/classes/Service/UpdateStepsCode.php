<?php
namespace Plugins\LoginForm\classes\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Plugins\LoginForm\classes\LoginForm;
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

        $pluginLoginForm = LoginForm::getInstance();
        $configValues = $pluginLoginForm::getPluginConfig();

        // check if there is a config.php file with previous settings in the login_form plugin folder
        $configFile = dirname(__DIR__, 4) . '/adm_plugins/login_form/config.php';
        if (file_exists($configFile)) {
            // include the config file to get the previous settings
            include $configFile;

            // update the settings in the database with the previous settings from the config file if they are different to the current settings
            foreach (get_defined_vars() as $varName => $varValue) {
                if ($varName === 'plg_show_register_link') {
                    if ($gSettingsManager->getBool('login_form_show_register_link') !== (bool)$varValue) {
                        $gSettingsManager->set('login_form_show_register_link', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_show_email_link') {
                    if ($gSettingsManager->getBool('login_form_show_email_link') !== (bool)$varValue) {
                        $gSettingsManager->set('login_form_show_email_link', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_show_logout_link') {
                    if ($gSettingsManager->getBool('login_form_show_logout_link') !== (bool)$varValue) {
                        $gSettingsManager->set('login_form_show_logout_link', (bool)$varValue);
                    }
                } elseif ($varName === 'plg_rank') {
                    $ranks = (is_array($varValue) && !empty($varValue)) ? $varValue : array();
                    // if there are no ranks defined disable the rank feature
                    // in previous versions this was done by defining an empty array
                    if (empty($ranks)) {
                        $gSettingsManager->set('login_form_enable_ranks', false);
                    } else {
                        $gSettingsManager->set('login_form_enable_ranks', true);

                        // normally we would also use the defined rank titles from the config file but because they are
                        // not translatable and will be different to the new translation strings we don't update them here
                        /*
                        foreach ($ranks as $key => $rank) {
                            $gSettingsManager->set($key, implode(',', $value));
                            // check if the value contains keys
                            if (array_keys($value) !== range(0, count($value) - 1)) {
                                // if the value is an associative array, store the keys separately
                                $gSettingsManager->set($key . '_keys', implode(',', array_keys($value)));
                            }
                        }
                        */
                    }
                }
            }
        }
    }
}