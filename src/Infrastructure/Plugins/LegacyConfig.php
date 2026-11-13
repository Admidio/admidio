<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Infrastructure\Exception;
use Admidio\Preferences\Service\PreferencesService;
use Throwable;

/**
 * The config.php of a plugin from before the plugin system.
 *
 * Such a plugin was a folder somebody copied into adm_plugins/, and its settings were PHP variables
 * in a config.php beside its code. The version that is installed through the plugin system keeps
 * its settings as preferences, so an update step of the plugin takes the old file over once:
 * ```
 * LegacyConfig::import('birthday', array(
 *     'plg_show_age'      => array('birthday_show_age', 'boolval'),
 *     'plg_show_zeitraum' => array('birthday_show_past', 'intval')
 * ));
 * ```
 * The file was one for the whole installation, so what it holds is written into every
 * organization. An installation that never had the file keeps the defaults.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class LegacyConfig
{
    public const FILE = 'config.php';

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * The file an earlier version of the plugin left behind, or **null** if there is none.
     *
     * It is looked for in the folder of the plugin itself, where it is when the new files were
     * copied over the old ones or installed by the plugin manager (see
     * PluginPackage::moveIntoPlace()), and in adm_plugins/, where the plugins of Admidio itself
     * were and where a plugin stays that the update could not move.
     * @param string $pluginId The ID of the plugin, which is the name of its folder.
     * @param string $legacyFolder The name the folder had in adm_plugins/, if it was a different one.
     * @return string|null
     */
    public static function find(string $pluginId, string $legacyFolder = ''): ?string
    {
        $files = array(
            PluginRegistry::getPluginsPath() . '/' . $pluginId . '/' . self::FILE,
            ADMIDIO_PATH . '/adm_plugins/' . ($legacyFolder === '' ? $pluginId : $legacyFolder) . '/' . self::FILE
        );

        foreach ($files as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Write the settings of the old config.php into the preferences of every organization.
     * @param string $pluginId The ID of the plugin, which is the name of its folder.
     * @param array<string,array{0:string,1:callable}> $map For each variable of the old file the
     *                             preference that replaces it and what turns the old value into the
     *                             new one, e.g. **'intval'**. A variable the file does not set is
     *                             skipped.
     * @param string $legacyFolder The name the folder had in adm_plugins/, if it was a different one.
     * @return void
     * @throws Exception
     */
    public static function import(string $pluginId, array $map, string $legacyFolder = ''): void
    {
        $variables = self::read($pluginId, $legacyFolder);
        $preferences = array();

        foreach ($map as $variable => [$preference, $convert]) {
            if (isset($variables[$variable])) {
                $preferences[$preference] = $convert($variables[$variable]);
            }
        }

        PreferencesService::setForAllOrganizations($preferences);
    }

    /**
     * The variables the old config.php sets, as name => value.
     *
     * The file is included, which runs it - as every page view of the old plugin did. A file that
     * can no longer run is reported and counts as missing, so that it cannot keep the plugin from
     * being installed.
     * @param string $pluginId The ID of the plugin, which is the name of its folder.
     * @param string $legacyFolder The name the folder had in adm_plugins/, if it was a different one.
     * @return array<string,mixed>
     */
    public static function read(string $pluginId, string $legacyFolder = ''): array
    {
        global $gLogger;

        $file = self::find($pluginId, $legacyFolder);
        if ($file === null) {
            return array();
        }

        // A scope of its own, with the globals a config.php could rely on in the old plugins.
        $include = static function (string $__file): array {
            global $gL10n, $gDb, $gSettingsManager, $gCurrentOrganization, $gCurrentOrgId, $gCurrentUser;

            $__known = get_defined_vars();
            include $__file;

            return array_diff_key(get_defined_vars(), $__known, array('__known' => true));
        };

        try {
            $variables = $include($file);
        } catch (Throwable $exception) {
            $gLogger->warning(
                'The old configuration of the plugin "' . $pluginId . '" could not be read.',
                array('file' => $file, 'error' => $exception->getMessage())
            );

            return array();
        }

        $gLogger->notice(
            'The old configuration of the plugin "' . $pluginId . '" is taken over.',
            array('file' => $file)
        );

        return $variables;
    }
}
