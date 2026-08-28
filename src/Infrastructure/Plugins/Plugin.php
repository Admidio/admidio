<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\UI\Presenter\PagePresenter;

/**
 * One plugin as it exists on disk.
 *
 * A plugin is a directory below **plugins/** that contains a manifest **plugin.json** and an entry
 * file **plugin.php**. Everything else is optional and follows the directory conventions below.
 * The directory name is the plugin ID and the only identity a plugin ever has; the display name may
 * change, be translated or be duplicated by another plugin without any consequence.
 *
 * ```text
 * plugins/hello/
 * ├── plugin.json     manifest, the only required metadata
 * ├── plugin.php      entry file, included once when the plugin is loaded
 * ├── modules/        optional pages, ordinary Admidio module files
 * ├── src/            optional classes, reached through the "autoload" mapping
 * ├── languages/      optional language files, added to the language search path
 * ├── templates/      optional Smarty templates, added to the template search path
 * ├── assets/         optional css/js/images, reachable through assetUrl()
 * └── db_scripts/     optional install.sql, uninstall.sql and update_x_y.xml
 * ```
 *
 * Reading a plugin never executes plugin code and never throws. An unreadable, malformed or
 * incompatible manifest produces an object whose **error** describes the problem, so the plugin
 * administration can list and remove a broken plugin instead of breaking with it.
 *
 * **Code example**
 * ```
 * $plugin = Plugin::read(ADMIDIO_PATH . FOLDER_PLUGINS . '/hello');
 * if ($plugin->isValid()) {
 *     echo $plugin->id . ' ' . $plugin->version;
 * }
 * ```
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Plugin
{
    /**
     * Name of the manifest file inside the plugin directory.
     */
    public const MANIFEST_FILE = 'plugin.json';

    /**
     * Name of the entry file that is included once when the plugin is loaded.
     */
    public const ENTRY_FILE = 'plugin.php';

    /**
     * Conventional subdirectories. None of them has to exist and none has to be declared.
     */
    public const DIR_PAGES = 'modules';
    public const DIR_LANGUAGES = 'languages';
    public const DIR_TEMPLATES = 'templates';
    public const DIR_ASSETS = 'assets';
    public const DIR_DB_SCRIPTS = 'db_scripts';

    /**
     * The namespace prefix of the Admidio core. A plugin must not map it, otherwise it could
     * shadow a core class.
     */
    public const RESERVED_NAMESPACE = 'Admidio\\';

    /**
     * Plugin ID, which is the directory name. Lowercase letters, digits and single hyphens.
     */
    public readonly string $id;

    /**
     * Absolute path of the plugin directory, without a trailing separator.
     */
    public readonly string $path;

    /**
     * The decoded manifest, so that a plugin may carry metadata this class does not interpret.
     * @var array<string,mixed>
     */
    public readonly array $manifest;

    /**
     * Display name. May be a language string ID such as **PLG_BIRTHDAY_NAME**.
     */
    public readonly string $name;

    /**
     * Description. May be a language string ID.
     */
    public readonly string $description;

    /**
     * Version of the files, as declared in the manifest.
     */
    public readonly string $version;

    /**
     * Bootstrap icon name, e.g. **bi-cake2**.
     */
    public readonly string $icon;

    public readonly string $author;

    public readonly string $homepage;

    /**
     * PSR-4 mappings of the plugin, as namespace prefix => absolute directory. Every directory is
     * verified to be inside the plugin directory.
     * @var array<string,string>
     */
    public readonly array $autoload;

    /**
     * The preferences this plugin owns, as name => definition. A definition holds at least
     * **type** and **default**.
     * @var array<string,array<string,mixed>>
     */
    public readonly array $settings;

    /**
     * Requirements of the plugin: **admidio** and **php** version constraints, **extensions** as a
     * list of PHP extension names and **plugins** as pluginId => version constraint.
     * @var array{admidio: string, php: string, extensions: array<int,string>, plugins: array<string,string>}
     */
    public readonly array $requires;

    /**
     * Why the plugin cannot be used, or **null** if the manifest is sound. This is an English
     * diagnostic for the administrator, not a translated user message.
     */
    public readonly ?string $error;

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,string> $autoload
     * @param array<string,array<string,mixed>> $settings
     * @param array{admidio: string, php: string, extensions: array<int,string>, plugins: array<string,string>} $requires
     */
    private function __construct(
        string $id,
        string $path,
        array $manifest,
        array $autoload,
        array $settings,
        array $requires,
        ?string $error
    ) {
        $this->id = $id;
        $this->path = $path;
        $this->manifest = $manifest;
        $this->name = (string)($manifest['name'] ?? $id);
        $this->description = (string)($manifest['description'] ?? '');
        $this->version = (string)($manifest['version'] ?? '0.0.0');
        $this->icon = (string)($manifest['icon'] ?? 'bi-puzzle-fill');
        $this->author = (string)($manifest['author'] ?? '');
        $this->homepage = (string)($manifest['url'] ?? '');
        $this->autoload = $autoload;
        $this->settings = $settings;
        $this->requires = $requires;
        $this->error = $error;
    }

    /**
     * Read the plugin in the given directory. The method only touches the filesystem, it never
     * includes plugin code, and it never throws: a plugin that cannot be used is returned with a
     * populated **error**.
     * @param string $directory Absolute path of the plugin directory.
     * @return self
     */
    public static function read(string $directory): self
    {
        $path = rtrim(str_replace('\\', '/', $directory), '/');
        $id = basename($path);
        $empty = array('admidio' => '', 'php' => '', 'extensions' => array(), 'plugins' => array());

        if (!self::isValidId($id)) {
            return new self($id, $path, array(), array(), array(), $empty,
                'The plugin directory name "' . $id . '" must consist of lowercase letters, digits and single hyphens.');
        }

        $manifestFile = $path . '/' . self::MANIFEST_FILE;
        if (!is_file($manifestFile)) {
            return new self($id, $path, array(), array(), array(), $empty,
                'The manifest ' . self::MANIFEST_FILE . ' is missing.');
        }

        $manifest = json_decode((string)file_get_contents($manifestFile), true);
        if (!is_array($manifest)) {
            return new self($id, $path, array(), array(), array(), $empty,
                'The manifest ' . self::MANIFEST_FILE . ' is not a valid JSON object.');
        }

        if (!is_file($path . '/' . self::ENTRY_FILE)) {
            return new self($id, $path, $manifest, array(), array(), $empty,
                'The entry file ' . self::ENTRY_FILE . ' is missing.');
        }

        if (!isset($manifest['version']) || !is_string($manifest['version']) || $manifest['version'] === '') {
            return new self($id, $path, $manifest, array(), array(), $empty,
                'The manifest does not declare a version.');
        }

        $error = null;
        $autoload = self::readAutoload($path, $manifest, $error);

        return new self($id, $path, $manifest, $autoload, self::readSettings($manifest), self::readRequires($manifest), $error);
    }

    /**
     * A plugin ID is the directory name and is used in preference names, URLs and the components
     * table, so it is restricted to a form that is safe everywhere.
     * @param string $id
     * @return bool
     */
    public static function isValidId(string $id): bool
    {
        return (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id);
    }

    /**
     * Whether the plugin can be used at all. An invalid plugin is still listed by the
     * administration, but it is never loaded and cannot be installed.
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->error === null;
    }

    /**
     * Absolute path of the entry file.
     * @return string
     */
    public function getEntryFile(): string
    {
        return $this->path . '/' . self::ENTRY_FILE;
    }

    /**
     * Absolute path of a conventional subdirectory, or **null** if the plugin does not have it.
     * @param string $directory One of the DIR_* constants.
     * @return string|null
     */
    public function getDirectory(string $directory): ?string
    {
        $path = $this->path . '/' . $directory;

        return is_dir($path) ? $path : null;
    }

    /**
     * The pages of this plugin: the plain PHP file names directly below **modules/**. The directory
     * is not searched recursively, so what a page includes stays private to the plugin.
     * @return array<int,string>
     */
    public function getPages(): array
    {
        $directory = $this->getDirectory(self::DIR_PAGES);
        if ($directory === null) {
            return array();
        }

        $pages = array();
        foreach (scandir($directory) ?: array() as $entry) {
            if (str_ends_with($entry, '.php') && is_file($directory . '/' . $entry)) {
                $pages[] = $entry;
            }
        }
        sort($pages);

        return $pages;
    }

    /**
     * Whether the plugin brings pages of its own.
     * @return bool
     */
    public function hasPages(): bool
    {
        return $this->getPages() !== array();
    }

    /**
     * Whether the plugin should get a menu entry below "Extensions" when it is installed.
     *
     * A plugin with a page gets one, because that is where the entry would lead. A plugin whose
     * pages are not destinations - the calendar answers one month of itself for its own buttons -
     * says **"menu": false** in its manifest and is left out of the menu.
     * @return bool
     */
    public function wantsMenuEntry(): bool
    {
        return $this->hasPages() && ($this->manifest['menu'] ?? true) !== false;
    }

    /**
     * URL of a page of this plugin, in whichever form is currently live - see PluginPages.
     *
     * Without a page the URL a menu entry should use is returned: the **index.php** of the plugin if
     * it has one, and the entry file otherwise.
     * @param string $page File name of the page, e.g. **list.php**.
     * @return string
     */
    public function getUrl(string $page = ''): string
    {
        if ($page === '') {
            return in_array('index.php', $this->getPages(), true)
                ? PluginPages::getUrl($this, 'index.php')
                : ADMIDIO_URL . FOLDER_PLUGINS . '/' . $this->id . '/' . self::ENTRY_FILE;
        }

        return PluginPages::getUrl($this, $page);
    }

    /**
     * The current value of every preference the manifest declares, as name => value.
     *
     * A value is cast to the type the manifest gives it, so a plugin reads its settings in the shape
     * it declared them instead of casting at every call site. A preference this organization has no
     * row for answers the declared default, so a plugin can be read before its preferences are
     * seeded - during its own installation, for instance.
     *
     * This asks the database and is therefore not part of reading a plugin; discovery stays a
     * directory scan and a json_decode.
     * @return array<string,mixed>
     * @throws \Admidio\Infrastructure\Exception
     */
    public function getSettingValues(): array
    {
        global $gSettingsManager;

        $values = array();

        foreach ($this->settings as $name => $definition) {
            if (!isset($gSettingsManager) || !$gSettingsManager->has($name)) {
                $values[$name] = $definition['default'];
                continue;
            }

            $values[$name] = match ($definition['type']) {
                'int', 'integer' => $gSettingsManager->getInt($name),
                'bool', 'boolean' => $gSettingsManager->getBool($name),
                'array' => self::readArraySetting($name),
                default => $gSettingsManager->getString($name)
            };
        }

        return $values;
    }

    /**
     * Read a preference that holds a list.
     *
     * A list is stored as one comma-separated string. A list whose entries need keys of their own -
     * the ranks of the login form - stores them in a second preference **&lt;name&gt;_keys**, and the
     * two are zipped back together here.
     * @param string $name
     * @return array<int|string,string>
     * @throws \Admidio\Infrastructure\Exception
     */
    private static function readArraySetting(string $name): array
    {
        global $gSettingsManager;

        $value = $gSettingsManager->getString($name);
        if ($value === '') {
            return array();
        }

        if ($gSettingsManager->has($name . '_keys')) {
            return array_combine(explode(',', $gSettingsManager->getString($name . '_keys')), explode(',', $value));
        }

        return explode(',', $value);
    }

    /**
     * Render a template of this plugin through the Smarty object of a page.
     *
     * The template is fetched through the page, so the template directories of the theme and its
     * fallback are already in place and a theme can override the template of a plugin by putting a
     * file of the same name into **themes/&lt;theme&gt;/templates/plugins/&lt;plugin ID&gt;/**.
     * @param PagePresenter $page The page the output belongs to. The preferences page is one, so a
     *                            preference panel renders its template exactly like a widget does.
     * @param string $template File name of the template, e.g. **plugin.birthday.tpl**.
     * @param array<string,mixed> $variables Variables the template should receive.
     * @return string
     * @throws \Smarty\Exception
     */
    public function renderTemplate(PagePresenter $page, string $template, array $variables = array()): string
    {
        $smarty = $page->getSmartyTemplate();

        $templates = $this->getDirectory(self::DIR_TEMPLATES);
        if ($templates !== null) {
            $smarty->addTemplateDir($templates);
        }

        foreach ($variables as $name => $value) {
            $smarty->assign($name, $value);
        }

        return $smarty->fetch($template);
    }

    /**
     * URL of a static file below **assets/**.
     * @param string $file Relative path below the assets directory, e.g. **css/hello.css**.
     * @return string
     */
    public function getAssetUrl(string $file): string
    {
        return ADMIDIO_URL . FOLDER_PLUGINS . '/' . $this->id . '/' . self::DIR_ASSETS . '/' . ltrim($file, '/');
    }

    /**
     * The name of the preference that enables this plugin for an organization.
     * @return string
     */
    public function getEnabledSettingName(): string
    {
        return 'plugin_' . str_replace('-', '_', $this->id) . '_enabled';
    }

    /**
     * Check the requirements of the manifest against the running system.
     * @param array<string,string> $installedPlugins Version of every installed and enabled plugin,
     *                                               as pluginId => version, to resolve plugin
     *                                               dependencies.
     * @return array<int,string> One English diagnostic per unsatisfied requirement, empty if the
     *                           plugin can run here.
     */
    public function checkRequirements(array $installedPlugins = array()): array
    {
        $problems = array();

        if ($this->error !== null) {
            $problems[] = $this->error;
        }

        if ($this->requires['admidio'] !== '' && !self::versionMatches(ADMIDIO_VERSION, $this->requires['admidio'])) {
            $problems[] = 'Admidio ' . $this->requires['admidio'] . ' is required, but this is Admidio ' . ADMIDIO_VERSION . '.';
        }

        if ($this->requires['php'] !== '' && !self::versionMatches(PHP_VERSION, $this->requires['php'])) {
            $problems[] = 'PHP ' . $this->requires['php'] . ' is required, but this is PHP ' . PHP_VERSION . '.';
        }

        foreach ($this->requires['extensions'] as $extension) {
            if (!extension_loaded($extension)) {
                $problems[] = 'The PHP extension "' . $extension . '" is not available.';
            }
        }

        foreach ($this->requires['plugins'] as $pluginId => $constraint) {
            if (!isset($installedPlugins[$pluginId])) {
                $problems[] = 'The plugin "' . $pluginId . '" is required, but it is not installed and enabled.';
            } elseif ($constraint !== '' && !self::versionMatches($installedPlugins[$pluginId], $constraint)) {
                $problems[] = 'The plugin "' . $pluginId . '" ' . $constraint . ' is required, but version '
                    . $installedPlugins[$pluginId] . ' is installed.';
            }
        }

        return $problems;
    }

    /**
     * Check a version against a constraint. A constraint is a space separated list of terms that
     * all have to match, e.g. **>=5.1 <6.0**. A term is an operator (**>=**, **>**, **<=**, **<**,
     * **=**, **!=**) followed by a version; a term without an operator means **>=**.
     * @param string $version
     * @param string $constraint
     * @return bool
     */
    public static function versionMatches(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        foreach (preg_split('/[\s,]+/', $constraint) as $term) {
            if ($term === '') {
                continue;
            }
            if (preg_match('/^(>=|<=|!=|>|<|=)?\s*(\d[\w.\-]*)$/', $term, $matches) !== 1) {
                // An unreadable constraint must not silently pass.
                return false;
            }
            if (!version_compare($version, $matches[2], $matches[1] === '' || $matches[1] === null ? '>=' : $matches[1])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read and validate the PSR-4 mappings. A mapping has to stay inside the plugin directory and
     * must not claim the core namespace. The whole plugin is rejected if one mapping is unsafe,
     * because a plugin whose classes cannot be trusted must not be loaded at all.
     * @param string $path
     * @param array<string,mixed> $manifest
     * @param string|null $error Set to the first problem that was found.
     * @return array<string,string>
     */
    private static function readAutoload(string $path, array $manifest, ?string &$error): array
    {
        $declared = $manifest['autoload'] ?? array();
        if (!is_array($declared)) {
            $error = 'The "autoload" entry of the manifest must be an object of namespace prefix to directory.';
            return array();
        }

        $autoload = array();
        foreach ($declared as $prefix => $relativePath) {
            if (!is_string($prefix) || !is_string($relativePath)) {
                $error = 'Every "autoload" entry must map a namespace prefix to a directory.';
                return array();
            }

            $prefix = rtrim($prefix, '\\') . '\\';
            if (preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)+$/', $prefix) !== 1) {
                $error = 'The namespace prefix "' . $prefix . '" is not a valid PHP namespace.';
                return array();
            }
            if (str_starts_with($prefix, self::RESERVED_NAMESPACE)) {
                $error = 'The namespace "' . self::RESERVED_NAMESPACE . '" belongs to the Admidio core. Use an own '
                    . 'namespace such as "AdmidioPlugin\\' . str_replace('-', '', ucwords(basename($path), '-')) . '\\".';
                return array();
            }

            $directory = self::resolveInside($path, $relativePath);
            if ($directory === null) {
                $error = 'The "autoload" directory "' . $relativePath . '" is not a directory inside the plugin.';
                return array();
            }

            $autoload[$prefix] = $directory;
        }

        return $autoload;
    }

    /**
     * Resolve a relative path against the plugin directory and verify that it stays inside it.
     * @param string $path
     * @param string $relativePath
     * @return string|null The absolute directory, or **null** if it does not exist or escapes.
     */
    private static function resolveInside(string $path, string $relativePath): ?string
    {
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            return null;
        }

        $resolved = realpath($path . '/' . trim(str_replace('\\', '/', $relativePath), '/'));
        if ($resolved === false || !is_dir($resolved)) {
            return null;
        }

        $resolved = rtrim(str_replace('\\', '/', $resolved), '/');
        $root = rtrim(str_replace('\\', '/', (string)realpath($path)), '/');

        return ($resolved === $root || str_starts_with($resolved, $root . '/')) ? $resolved : null;
    }

    /**
     * Read the preference definitions of the manifest.
     * @param array<string,mixed> $manifest
     * @return array<string,array<string,mixed>>
     */
    private static function readSettings(array $manifest): array
    {
        $declared = $manifest['settings'] ?? array();
        if (!is_array($declared)) {
            return array();
        }

        $settings = array();
        foreach ($declared as $name => $definition) {
            if (!is_string($name) || !is_array($definition) || !array_key_exists('default', $definition)) {
                continue;
            }
            $settings[$name] = array(
                'type' => (string)($definition['type'] ?? 'string'),
                'default' => $definition['default'],
                'values' => is_array($definition['values'] ?? null) ? $definition['values'] : array(),
                'label' => (string)($definition['label'] ?? ''),
                'description' => (string)($definition['description'] ?? '')
            );
        }

        return $settings;
    }

    /**
     * Read the requirements of the manifest and normalize them.
     * @param array<string,mixed> $manifest
     * @return array{admidio: string, php: string, extensions: array<int,string>, plugins: array<string,string>}
     */
    private static function readRequires(array $manifest): array
    {
        $declared = is_array($manifest['requires'] ?? null) ? $manifest['requires'] : array();

        $extensions = array();
        foreach ((array)($declared['extensions'] ?? array()) as $extension) {
            if (is_string($extension) && $extension !== '') {
                $extensions[] = $extension;
            }
        }

        $plugins = array();
        foreach ((array)($declared['plugins'] ?? array()) as $pluginId => $constraint) {
            if (is_string($pluginId) && self::isValidId($pluginId)) {
                $plugins[$pluginId] = is_string($constraint) ? $constraint : '';
            }
        }

        return array(
            'admidio' => is_string($declared['admidio'] ?? null) ? $declared['admidio'] : '',
            'php' => is_string($declared['php'] ?? null) ? $declared['php'] : '',
            'extensions' => $extensions,
            'plugins' => $plugins
        );
    }
}
