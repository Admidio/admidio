<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\UI\Presenter\PagePresenter;
use Throwable;

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
 * Admidio bootstrap, asks PluginRegistry to validate and resolve the plugin page, and requires the
 * returned file itself. The require deliberately stays in the generated top-level script so that
 * the plugin page inherits the same bootstrap globals as an ordinary module. The page itself is
 * unchanged and works through either URL, because **__DIR__** is per file: the relative includes of
 * the page still resolve against its real location.
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
     * The format of the stubs on disk, against STUB_FORMAT. A core that writes its stubs
     * differently raises STUB_FORMAT, and the next request rewrites every stub once.
     */
    public const SETTING_FORMAT = 'plugin_module_pages_format';

    /**
     * The format getStubSource() writes. 1 required PluginRegistry::resolvePage() directly, 2 goes
     * through resolve(), which answers a page that may not run instead of failing with a PHP error.
     */
    public const STUB_FORMAT = 2;

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
        $formatCurrent = !$wanted || self::readFormat() === self::STUB_FORMAT;
        if (self::readFlag(self::SETTING_APPLIED) === $wanted && $formatCurrent) {
            // Nothing changed. On an installation that has neither preference yet, both are false,
            // so a plain request writes nothing.
            return array();
        }

        $gSettingsManager->set(self::SETTING_APPLIED, $wanted ? '1' : '0');
        $gSettingsManager->set(self::SETTING_FORMAT, (string)self::STUB_FORMAT);

        return self::syncAll();
    }

    /**
     * The format of the stubs on disk. An installation without the preference has the stubs of
     * the format before it was introduced, which is what the declared default says.
     * @return int
     */
    private static function readFormat(): int
    {
        global $gSettingsManager;

        if (!isset($gSettingsManager) || !$gSettingsManager->has(self::SETTING_FORMAT)) {
            return (int)(PreferenceDefinitions::all()[self::SETTING_FORMAT]['default'] ?? 1);
        }

        return $gSettingsManager->getInt(self::SETTING_FORMAT);
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

            // The stored menu URL follows whichever form is live now, in both directions.
            if ($plugin->isValid() && $plugin->hasPages() && PluginRegistry::isInstalled($id)) {
                PluginInstaller::updateMenuEntries($plugin, PluginRegistry::getComponentId($id));
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
     * Resolve one page of a plugin for a generated stub, and answer a page that may not run.
     *
     * This is PluginRegistry::resolvePage() for the one caller that has nobody to catch its
     * exception: the stub is the entry point of the request, so a refusal there - a disabled plugin,
     * a plugin waiting for its update - ended as an uncaught PHP error. It is answered by refuse().
     * @param string $id Plugin ID.
     * @param string $page File name of the page, e.g. **list.php**.
     * @return string Absolute file name of the page.
     */
    public static function resolve(string $id, string $page): string
    {
        try {
            return PluginRegistry::resolvePage($id, $page);
        } catch (Throwable $exception) {
            self::refuse($id, $exception);
        }
    }

    /**
     * Answer a request for a page of a plugin that may not run, and end the request.
     *
     * An administrator who opens a page of a plugin that waits for its update gets a page that can
     * run the update and then loads the page that was asked for, instead of only being told to go to
     * the plugin manager. Everybody else, and every other refusal, gets the message the exception
     * carries: as JSON for a request of a script, as a message page otherwise.
     * @param string $id Plugin ID.
     * @param Throwable $exception Why the page may not run.
     * @return never
     */
    public static function refuse(string $id, Throwable $exception): never
    {
        global $gCurrentUser;

        $ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        $get = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';

        if (!$ajax && $get && isset($gCurrentUser) && $gCurrentUser->isAdministrator()
            && PluginRegistry::isEnabled($id)
            && PluginRegistry::getState($id) === PluginRegistry::STATE_UPDATE) {
            try {
                self::showUpdatePage(PluginRegistry::get($id));
            } catch (Throwable $pageException) {
                handleException($pageException);
            }
            exit();
        }

        handleException($exception, $ajax);
        exit();
    }

    /**
     * The page that tells an administrator that a plugin must be updated and lets them do it here.
     * The button runs the update of the plugin manager and loads this page again, which is then the
     * page that was asked for.
     * @param Plugin $plugin
     * @return void
     * @throws Exception|\Smarty\Exception
     */
    private static function showUpdatePage(Plugin $plugin): void
    {
        global $gL10n, $gCurrentSession;

        $managerUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/plugins.php');
        $updateUrl = SecurityUtils::encodeUrl(
            ADMIDIO_URL . FOLDER_MODULES . '/plugins.php',
            array('mode' => 'update', 'plugin' => $plugin->id)
        );

        $page = PagePresenter::withHtmlIDAndHeadline('adm_plugin_update_required', $gL10n->get('SYS_PLUGIN_UPDATE'));
        $page->hideBackLink();
        $page->assignSmartyVariable('message', $gL10n->get('SYS_PLUGIN_UPDATE_REQUIRED', array(
            SecurityUtils::encodeHTML($gL10n->get($plugin->name)),
            '<a href="' . $managerUrl . '">',
            '</a>'
        )));
        $page->assignSmartyVariable('installedVersion', SecurityUtils::encodeHTML(PluginRegistry::getInstalledVersion($plugin->id)));
        $page->assignSmartyVariable('fileVersion', SecurityUtils::encodeHTML($plugin->version));
        $page->addTemplateFile('system/plugin-update-required.tpl');
        $page->addJavascript('
            $("#adm_button_plugin_update").on("click", function() {
                const $button = $(this).prop("disabled", true);
                $.post(' . json_encode($updateUrl) . ', { "adm_csrf_token": ' . json_encode($gCurrentSession->getCsrfToken()) . ' }, function(data) {
                    let result;
                    try {
                        result = typeof data === "string" ? JSON.parse(data) : data;
                    } catch (e) {
                        result = { status: "error", message: data };
                    }
                    const success = result.status === "success";
                    $("#adm_plugin_update_result")
                        .removeClass("d-none alert-success alert-danger")
                        .addClass(success ? "alert-success" : "alert-danger")
                        .html(result.message);
                    if (success) {
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $button.prop("disabled", false);
                    }
                });
            });', true);
        $page->show();
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
            . "require Admidio\\Infrastructure\\Plugins\\PluginPages::resolve('" . $id . "', '" . $page . "');\n";
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
