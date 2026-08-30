<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Cli\CliTaskRegistry;
use Admidio\Infrastructure\Exception;
use Admidio\Preferences\Service\PreferenceDefinitions;
use RuntimeException;
use Throwable;

/**
 * Loads the plugins that are enabled for the current request.
 *
 * Loading one plugin means four things and nothing else:
 *
 * 1. its **autoload** mappings become resolvable classes;
 * 2. its **languages/** directory is added to the language search path;
 * 3. its **settings** are registered as ordinary Admidio preferences;
 * 4. its **plugin.php** is included once.
 *
 * The entry file does not have to return anything, implement anything or extend anything. It runs
 * inside the fully initialized Admidio request, so it can call any public Admidio API - normally it
 * registers hooks:
 *
 * ```
 * <?php
 * // plugins/hello/plugin.php
 * use Admidio\Hooks\Hooks;
 *
 * Hooks::addAction('overview_widgets', array(AdmidioPlugin\Hello\Widget::class, 'show'));
 * ```
 *
 * A plugin that fails while it is being loaded is reported and skipped. A broken extension must not
 * be able to take the whole installation down, because then it could not be disabled any more.
 * This is the opposite of the policy for hooks, where a failing callback fails the operation it
 * extends: an operation can be aborted, a request that never reaches the plugin administration
 * cannot be repaired.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PluginLoader
{
    /**
     * The plugins that were loaded in this request, as pluginId => Plugin.
     * @var array<string,Plugin>
     */
    private static array $loaded = array();

    /**
     * The plugins that could not be loaded, as pluginId => reason.
     * @var array<string,string>
     */
    private static array $failed = array();

    /**
     * PSR-4 mappings of all loaded plugins, as namespace prefix => absolute directory.
     * @var array<string,string>
     */
    private static array $classMap = array();

    private static bool $autoloaderRegistered = false;

    private static bool $done = false;

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Load every plugin that is installed and enabled for the current organization. The method does
     * its work once per request; calling it again is harmless.
     * @return void
     */
    public static function loadEnabled(): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;

        try {
            self::registerEnabledFlags();
            $plugins = PluginRegistry::getLoadable();
        } catch (Throwable $exception) {
            self::report('', 'The plugins could not be determined: ' . $exception->getMessage());
            return;
        }

        foreach ($plugins as $plugin) {
            self::load($plugin);
        }

        try {
            // The administrator may have changed whether plugin pages are published below modules/.
            PluginPages::reconcile();
        } catch (Throwable $exception) {
            self::report('', 'The plugin pages could not be published: ' . $exception->getMessage());
        }

        self::guardPluginScript();

        Hooks::doAction('plugins_loaded', self::$loaded);
    }

    /**
     * Make **plugin_&lt;id&gt;_enabled** a known preference for every installed plugin.
     *
     * The flag belongs to Admidio and not to the plugin, so it cannot be registered by loading the
     * plugin: a disabled plugin is never loaded, its name would stay unknown to the preference
     * registry, and the administrator could not enable it again - neither in the preferences nor
     * through **config:set**. A plugin whose files are gone is skipped, because there is nothing
     * left to enable; the plugin administration removes such a row as an orphan.
     * @return void
     * @throws Exception
     */
    private static function registerEnabledFlags(): void
    {
        foreach (array_keys(PluginRegistry::getInstallations()) as $id) {
            $plugin = PluginRegistry::get((string)$id);
            if ($plugin !== null && Plugin::isValidId($plugin->id)) {
                PreferenceDefinitions::register($plugin->getEnabledSettingName(), array('default' => '1', 'type' => 'bool'));
            }
        }
    }

    /**
     * Refuse a request that entered a plugin directory through anything but a page of a plugin that
     * is enabled here.
     *
     * A file below plugins/ is a file the browser can request directly, so being disabled has to
     * mean something at the moment it runs, and only the declared pages of a plugin are entry points
     * at all. The same request through a generated stub below modules/ is checked by
     * PluginRegistry::requirePage() in exactly the same way.
     *
     * This can only protect a file that reaches the Admidio bootstrap. A file that does not - the
     * entry file, a class below src/ - never gets this far; it cannot do anything without the
     * bootstrap either, but it should carry the one-line guard that plugin.php of the example plugin
     * shows, so that a direct request ends with a message instead of a PHP error.
     * @return void
     * @throws Exception
     */
    private static function guardPluginScript(): void
    {
        $id = PluginRegistry::getScriptPluginId();
        if ($id === null) {
            return;
        }

        $plugin = PluginRegistry::requireEnabled($id);
        $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
        $pages = $plugin->getDirectory(Plugin::DIR_PAGES);

        if ($script === false || $pages === null
            || !in_array(basename($script), $plugin->getPages(), true)
            || realpath($pages . '/' . basename($script)) !== $script) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
    }

    /**
     * Load one plugin, whatever its state is. Use loadEnabled() for the regular request; this
     * method exists for the plugin administration, which loads a single plugin on purpose, and for
     * tests.
     * @param Plugin $plugin
     * @return bool Whether the plugin was loaded.
     */
    public static function load(Plugin $plugin): bool
    {
        if (isset(self::$loaded[$plugin->id])) {
            return true;
        }
        if (!$plugin->isValid()) {
            self::report($plugin->id, (string)$plugin->error);
            return false;
        }

        try {
            self::registerClasses($plugin);
            self::registerLanguages($plugin);
            self::registerSettings($plugin);

            self::$loaded[$plugin->id] = $plugin;

            /*
             * A plugin owns the CLI command namespace named after its directory, exactly like a
             * module. Announcing the context has no effect outside the command line.
             */
            CliTaskRegistry::setPluginContext($plugin->id);
            try {
                self::includeEntryFile($plugin->getEntryFile());
            } finally {
                CliTaskRegistry::setPluginContext(null);
            }
        } catch (Throwable $exception) {
            unset(self::$loaded[$plugin->id]);
            self::report($plugin->id, $exception->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Include the entry file of a plugin in a scope of its own.
     *
     * Top-level code in an included file runs in the scope of whatever included it, so an entry file
     * that assigns a variable would otherwise overwrite a local of load(). The closure gives the
     * plugin an empty scope; globals, functions and classes are unaffected.
     *
     * Not include_once: the caller's own guard is the authoritative one, and it can be released
     * again by reset().
     * @param string $file
     * @return void
     */
    private static function includeEntryFile(string $file): void
    {
        (static function () use ($file): void {
            include $file;
        })();
    }

    /**
     * The plugins that are loaded in this request.
     * @return array<string,Plugin>
     */
    public static function getLoaded(): array
    {
        return self::$loaded;
    }

    /**
     * Whether the plugin is loaded in this request. A plugin uses this to find out whether an
     * optional companion is present.
     * @param string $id
     * @return bool
     */
    public static function isLoaded(string $id): bool
    {
        return isset(self::$loaded[$id]);
    }

    /**
     * The plugins that failed while they were loaded, as pluginId => reason. The plugin
     * administration shows them so that the problem does not stay invisible.
     * @return array<string,string>
     */
    public static function getFailures(): array
    {
        return self::$failed;
    }

    /**
     * The template directories of the loaded plugins, so that a plugin template can be included by
     * its plain file name. A theme overrides a plugin template by placing a file of the same name
     * in **themes/&lt;theme&gt;/templates/plugins/&lt;plugin ID&gt;/**.
     * @return array<int,string> Directories in search order, the theme overrides first.
     */
    public static function getTemplateDirectories(): array
    {
        $directories = array();

        foreach (self::$loaded as $plugin) {
            if (defined('THEME_PATH') && is_dir(THEME_PATH . '/templates/plugins/' . $plugin->id)) {
                $directories[] = THEME_PATH . '/templates/plugins/' . $plugin->id;
            }
            $templates = $plugin->getDirectory(Plugin::DIR_TEMPLATES);
            if ($templates !== null) {
                $directories[] = $templates;
            }
        }

        return $directories;
    }

    /**
     * Forget everything that was loaded, so that the plugins can be loaded again. Only tests need
     * this, and only they may call it: an entry file that declares a function or a class would fail
     * when it is included a second time in the same process.
     * @return void
     */
    public static function reset(): void
    {
        self::$loaded = array();
        self::$failed = array();
        self::$classMap = array();
        self::$done = false;
    }

    /**
     * Make the classes of a plugin resolvable.
     *
     * Admidio registers one autoloader for all plugins instead of adding prefixes to the Composer
     * loader, so that plugin classes never depend on the internals of the Composer runtime and a
     * mapping can be verified before it becomes effective.
     * @param Plugin $plugin
     * @return void
     */
    private static function registerClasses(Plugin $plugin): void
    {
        foreach ($plugin->autoload as $prefix => $directory) {
            if (isset(self::$classMap[$prefix]) && self::$classMap[$prefix] !== $directory) {
                throw new RuntimeException('The namespace "' . $prefix . '" is already mapped to '
                    . self::$classMap[$prefix] . ' by another plugin.');
            }
            self::$classMap[$prefix] = $directory;
        }

        if (!self::$autoloaderRegistered) {
            spl_autoload_register(array(self::class, 'autoload'));
            self::$autoloaderRegistered = true;
        }
    }

    /**
     * PSR-4 autoloader for the classes of all loaded plugins.
     * @param string $class
     * @return void
     */
    public static function autoload(string $class): void
    {
        foreach (self::$classMap as $prefix => $directory) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $file = $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                include_once $file;
                return;
            }
        }
    }

    /**
     * Add the language directory of the plugin to the language search path, so that the language
     * keys of the plugin can be read with $gL10n->get() like any other string.
     *
     * Loading a plugin does this, but the plugin administration needs it for plugins that are not
     * loaded: it lists every plugin there is, and the name and description of a plugin that has not
     * been enabled are language keys of a file nothing has read yet.
     * @param Plugin $plugin
     * @return void
     */
    public static function registerLanguages(Plugin $plugin): void
    {
        global $gL10n;

        $languages = $plugin->getDirectory(Plugin::DIR_LANGUAGES);
        if ($languages !== null && isset($gL10n)) {
            $gL10n->addLanguageFolderPath($languages);
        }
    }

    /**
     * Register the preferences that the manifest of the plugin declares, so that they behave like
     * core preferences from here on. The flag that enables the plugin is not one of them; it is
     * Admidio's own and is registered by registerEnabledFlags().
     * @param Plugin $plugin
     * @return void
     */
    private static function registerSettings(Plugin $plugin): void
    {
        foreach ($plugin->settings as $name => $definition) {
            $default = $definition['default'];
            if (is_bool($default)) {
                $default = $default ? '1' : '0';
            } elseif (is_array($default)) {
                $default = implode(',', $default);
            }

            /*
             * A manifest is written by hand, so the spelling a developer expects is accepted and
             * mapped to the types the preference registry knows.
             */
            $registered = array(
                'default' => (string)$default,
                'type' => match ($definition['type']) {
                    'bool', 'boolean' => 'bool',
                    'int', 'integer' => 'int',
                    'enum' => 'enum',
                    default => 'string'
                }
            );
            if ($registered['type'] === 'enum') {
                $registered['values'] = $definition['values'];
            }

            PreferenceDefinitions::register($name, $registered);
        }
    }

    /**
     * Record and log that a plugin could not be loaded.
     * @param string $id
     * @param string $reason
     * @return void
     */
    private static function report(string $id, string $reason): void
    {
        global $gLogger;

        self::$failed[$id] = $reason;

        if (isset($gLogger)) {
            $gLogger->error('PLUGIN: ' . ($id === '' ? 'Loading the plugins failed.' : 'Plugin "' . $id . '" was not loaded.'),
                array('plugin' => $id, 'reason' => $reason));
        }
    }
}
