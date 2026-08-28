<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Infrastructure\Exception;
use Admidio\Preferences\Service\PreferenceDefinitions;

/**
 * Publishes the pages of a plugin under the ordinary module URL.
 *
 * A plugin keeps its pages inside its own directory:
 *
 * ```text
 * plugins/hello/modules/list.php
 * ```
 *
 * Admidio has no front controller and no rewrite rules - every module URL is a real file - so the
 * only way to reach that page at
 *
 * ```text
 * /modules/hello/list.php
 * ```
 *
 * is a real file at that path. This class writes one generated stub per page, which loads the
 * Admidio bootstrap and hands over to the page inside the plugin. The page itself is unchanged and
 * works through either URL, because **__DIR__** is per file: the relative includes of the page still
 * resolve against its real location.
 *
 * Publishing writes into the Admidio core tree, which a Git-managed or read-only deployment does not
 * want, so it is off by default and the administrator turns it on with the preference
 * **plugin_module_pages**. When it is off, the pages of a plugin stay reachable below
 * **plugins/**; nothing may therefore hardcode either form. Plugin::getUrl() answers which one is
 * currently live.
 *
 * A generated directory carries a marker file that names the plugin it belongs to. Nothing without
 * that marker is ever deleted, so a core module directory cannot be removed by a plugin, whatever
 * the plugin is called.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PluginPages
{
    /**
     * The preference that allows Admidio to write the stubs into the modules directory.
     */
    public const SETTING = 'plugin_module_pages';

    /**
     * The value of SETTING that is currently reflected on disk. Comparing the two is one integer
     * comparison on preferences that are loaded anyway, and it lets the expensive reconciliation run
     * exactly once after the administrator changed the setting.
     */
    public const SETTING_APPLIED = 'plugin_module_pages_applied';

    /**
     * Marks a directory below modules/ as generated, and names its owner. Only a directory that
     * carries this file is ever removed again.
     */
    public const MARKER_FILE = '.admidio-plugin';

    /**
     * Overrides the modules directory. Only used by tests.
     * @var string|null
     */
    private static ?string $modulesPath = null;

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Absolute path of the modules directory.
     * @return string
     */
    public static function getModulesPath(): string
    {
        return self::$modulesPath ?? str_replace('\\', '/', ADMIDIO_PATH . FOLDER_MODULES);
    }

    /**
     * Write the stubs into another directory. Only used by tests. Pass **null** to restore the
     * regular modules directory.
     * @param string|null $path
     * @return void
     */
    public static function setModulesPath(?string $path): void
    {
        self::$modulesPath = $path === null ? null : rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Whether the administrator allows Admidio to publish plugin pages below modules/.
     * @return bool
     */
    public static function isAllowed(): bool
    {
        return self::readFlag(self::SETTING);
    }

    /**
     * Read one of the two flags of this class.
     *
     * Both are ordinary core preferences, so an installation gets its rows from the installer or
     * from the next Update::updateOrgPreferences(). Between deploying this code and running that
     * update the rows do not exist yet, and SettingsManager::get() refuses a name it has no value
     * for, so the declared default answers until then.
     * @param string $name
     * @return bool
     */
    private static function readFlag(string $name): bool
    {
        global $gSettingsManager;

        if (!isset($gSettingsManager) || !$gSettingsManager->has($name)) {
            return (string)(PreferenceDefinitions::all()[$name]['default'] ?? '0') === '1';
        }

        return $gSettingsManager->getBool($name);
    }

    /**
     * Bring the files in line with the preference if the administrator changed it since the last
     * request that noticed.
     *
     * The applied value is written before the files are, so a deployment that cannot write into the
     * modules directory reports the obstacle once instead of retrying on every request.
     * @return array<string,string> The report of syncAll(), or an empty array if nothing was to do.
     * @throws Exception
     */
    public static function reconcile(): array
    {
        global $gSettingsManager;

        if (!isset($gSettingsManager)) {
            return array();
        }

        $wanted = self::readFlag(self::SETTING);
        if (self::readFlag(self::SETTING_APPLIED) === $wanted) {
            // Nothing changed. On an installation that has neither preference yet, both are false,
            // so a plain request writes nothing.
            return array();
        }

        $gSettingsManager->set(self::SETTING_APPLIED, $wanted ? '1' : '0');

        return self::syncAll();
    }

    /**
     * Whether the modules directory can be written. Publishing is impossible on a read-only
     * deployment, and that is a supported situation, not an error.
     * @return bool
     */
    public static function isWritable(): bool
    {
        return is_dir(self::getModulesPath()) && is_writable(self::getModulesPath());
    }

    /**
     * Why the pages of this plugin cannot be published, or **null** if they can.
     * @param Plugin $plugin
     * @return string|null An English diagnostic for the administrator.
     */
    public static function getObstacle(Plugin $plugin): ?string
    {
        if (!$plugin->hasPages()) {
            return 'The plugin has no pages.';
        }
        if (!self::isWritable()) {
            return 'The modules directory is not writable.';
        }

        $target = self::getTargetPath($plugin->id);
        if (file_exists($target . '.php')) {
            return 'The module "' . $plugin->id . '.php" already belongs to Admidio.';
        }
        if (is_dir($target) && !self::isGenerated($plugin->id)) {
            return 'The module directory "' . $plugin->id . '" already belongs to Admidio.';
        }

        return null;
    }

    /**
     * Whether the pages of this plugin are currently published below modules/.
     * @param Plugin|string $plugin
     * @return bool
     */
    public static function isPublished(Plugin|string $plugin): bool
    {
        return self::isGenerated($plugin instanceof Plugin ? $plugin->id : $plugin);
    }

    /**
     * The URL of one page of a plugin, in whichever form is currently live.
     * @param Plugin $plugin
     * @param string $page File name of the page, e.g. **list.php**.
     * @return string
     */
    public static function getUrl(Plugin $plugin, string $page): string
    {
        $page = ltrim($page, '/');

        if (self::isPublished($plugin)) {
            return ADMIDIO_URL . FOLDER_MODULES . '/' . $plugin->id . '/' . $page;
        }

        return ADMIDIO_URL . FOLDER_PLUGINS . '/' . $plugin->id . '/' . Plugin::DIR_PAGES . '/' . $page;
    }

    /**
     * Publish the pages of a plugin, if that is allowed and possible. Publishing is idempotent: the
     * directory is rewritten from the current page list, so a plugin update that adds or removes a
     * page is picked up.
     * @param Plugin $plugin
     * @return bool Whether the pages are published afterwards.
     * @throws Exception if the pages should be published but the files cannot be written.
     */
    public static function publish(Plugin $plugin): bool
    {
        if (!self::isAllowed() || self::getObstacle($plugin) !== null) {
            return false;
        }

        $target = self::getTargetPath($plugin->id);
        if (!is_dir($target) && !mkdir($target, 0755) && !is_dir($target)) {
            throw new Exception('SYS_FOLDER_NOT_CREATED', array($target));
        }

        // Rewrite the directory from scratch, so a page that the plugin no longer has disappears.
        self::removeGeneratedFiles($target);

        foreach ($plugin->getPages() as $page) {
            if (file_put_contents($target . '/' . $page, self::getStubSource($plugin->id, $page)) === false) {
                throw new Exception('SYS_FILE_NOT_CREATED', array($target . '/' . $page));
            }
        }

        // The core module directories carry the same guard against a directory listing.
        file_put_contents($target . '/index.html', '');
        // Generated files do not belong in the version control of a Git-managed deployment. The
        // pattern also covers this file, so the whole directory disappears from git status.
        file_put_contents($target . '/.gitignore', "*
");
        file_put_contents($target . '/' . self::MARKER_FILE, $plugin->id . "\n");

        return true;
    }

    /**
     * Remove the published pages of a plugin. A directory without the marker file is left alone,
     * whatever the plugin is called.
     * @param Plugin|string $plugin
     * @return void
     */
    public static function unpublish(Plugin|string $plugin): void
    {
        $id = $plugin instanceof Plugin ? $plugin->id : $plugin;
        if (!self::isGenerated($id)) {
            return;
        }

        $target = self::getTargetPath($id);
        self::removeGeneratedFiles($target);
        @unlink($target . '/index.html');
        @unlink($target . '/.gitignore');
        @unlink($target . '/' . self::MARKER_FILE);
        @rmdir($target);
    }

    /**
     * Bring the published pages of every plugin in line with the current preference and the current
     * plugin states. This is what runs after the administrator changed the preference.
     * @return array<string,string> One entry per plugin whose state changed or could not be
     *                              changed, as pluginId => English diagnostic.
     * @throws Exception
     */
    public static function syncAll(): array
    {
        $report = array();
        $allowed = self::isAllowed();

        foreach (PluginRegistry::all() as $id => $plugin) {
            $shouldPublish = $allowed && $plugin->isValid() && PluginRegistry::isInstalled($id)
                && $plugin->hasPages() && self::getObstacle($plugin) === null;

            if ($shouldPublish) {
                self::publish($plugin);
                $report[$id] = 'published';
            } elseif (self::isPublished($id)) {
                self::unpublish($id);
                $report[$id] = 'unpublished';
            } elseif ($allowed && $plugin->hasPages() && PluginRegistry::isInstalled($id)) {
                $report[$id] = (string)self::getObstacle($plugin);
            }
        }

        // A plugin whose files are gone leaves its stubs behind; they are removed here as well.
        foreach (self::getGeneratedDirectories() as $id) {
            if (PluginRegistry::get($id) === null) {
                self::unpublish($id);
                $report[$id] = 'unpublished';
            }
        }

        return $report;
    }

    /**
     * The source of one generated stub.
     * @param string $id
     * @param string $page
     * @return string
     */
    private static function getStubSource(string $id, string $page): string
    {
        return "<?php\n"
            . "/**\n"
            . " * Generated by Admidio for the plugin \"" . $id . "\". Do not edit and do not commit.\n"
            . " * The page itself is " . ltrim(FOLDER_PLUGINS, '/') . '/' . $id . '/' . Plugin::DIR_PAGES . '/' . $page . ",\n"
            . " * and it is removed again when the plugin is uninstalled or the preference\n"
            . " * " . self::SETTING . " is switched off.\n"
            . " */\n\n"
            . "require_once(__DIR__ . '/../../system/common.php');\n\n"
            . "Admidio\\Infrastructure\\Plugins\\PluginRegistry::requirePage('" . $id . "', '" . $page . "');\n";
    }

    /**
     * Whether the directory below modules/ was generated for this plugin.
     * @param string $id
     * @return bool
     */
    private static function isGenerated(string $id): bool
    {
        if (!Plugin::isValidId($id)) {
            return false;
        }

        $marker = self::getTargetPath($id) . '/' . self::MARKER_FILE;

        return is_file($marker) && trim((string)file_get_contents($marker)) === $id;
    }

    /**
     * The IDs of all generated directories below modules/.
     * @return array<int,string>
     */
    private static function getGeneratedDirectories(): array
    {
        $ids = array();
        $entries = is_dir(self::getModulesPath()) ? scandir(self::getModulesPath()) : false;

        foreach ($entries === false ? array() : $entries as $entry) {
            if ($entry !== '.' && $entry !== '..' && self::isGenerated($entry)) {
                $ids[] = $entry;
            }
        }

        return $ids;
    }

    /**
     * Absolute path of the directory a plugin is published to.
     * @param string $id
     * @return string
     */
    private static function getTargetPath(string $id): string
    {
        return self::getModulesPath() . '/' . $id;
    }

    /**
     * Remove the generated PHP files of a directory, but nothing else.
     * @param string $target
     * @return void
     */
    private static function removeGeneratedFiles(string $target): void
    {
        foreach (glob($target . '/*.php') ?: array() as $file) {
            @unlink($file);
        }
    }
}
