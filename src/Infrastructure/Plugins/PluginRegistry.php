<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Infrastructure\Exception;

/**
 * Knows which plugins exist and in which state they are.
 *
 * The registry is the only place that answers "which plugins are there" and "may this one run".
 * It reads the manifests and one row per installed plugin from the components table, and it never
 * includes plugin code: the plugin administration can therefore inspect, disable and remove a
 * plugin that would crash if it were loaded.
 *
 * A plugin has two independent states:
 *
 * - **installed** is global, because the database schema of a plugin is global. It is a row in the
 *   components table with **com_type = PLUGIN** and **com_name_intern = <plugin ID>**.
 * - **enabled** is per organization, because an Admidio installation may serve several
 *   organizations that do not want the same extensions. It is the preference
 *   **plugin_<id>_enabled**.
 *
 * Discovery and the installation states are read once per request.
 *
 * **Code example**
 * ```
 * foreach (PluginRegistry::all() as $plugin) {
 *     echo $plugin->id . ': ' . PluginRegistry::getState($plugin) . "\n";
 * }
 * ```
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PluginRegistry
{
    /** The files are there, but the plugin was never installed. */
    public const STATE_AVAILABLE = 'available';
    /** Installed, but not enabled for the current organization. */
    public const STATE_DISABLED = 'disabled';
    /** Installed and enabled for the current organization. */
    public const STATE_ENABLED = 'enabled';
    /** Installed, but the files declare a newer version than the database. */
    public const STATE_UPDATE = 'update';
    /** The manifest is missing, malformed or the requirements are not met. */
    public const STATE_BROKEN = 'broken';
    /** Installed in the database, but the files are gone. */
    public const STATE_ORPHANED = 'orphaned';

    /**
     * Component type of a plugin in the components table.
     */
    public const COMPONENT_TYPE = 'PLUGIN';

    /**
     * The plugins Admidio ships with. A new installation gets them, the 5.1 update converts them,
     * and the plugin administration refuses to delete them: their files belong to the Admidio
     * distribution, so the next core update would put back what the administrator deleted.
     *
     * Everything else below plugins/ - the example plugin, anything an administrator added - is an
     * ordinary plugin that can be removed.
     * @var array<int,string>
     */
    public const BUILT_IN = array(
        'announcement-list', 'birthday', 'calendar', 'event-list', 'latest-documents-files',
        'login-form', 'random-photo', 'who-is-online'
    );

    /**
     * Which plugin owns which preference, as name => plugin ID, or **null** while nothing has asked.
     * @var array<string,string>|null
     */
    private static ?array $settingOwners = null;

    /**
     * All plugins that were found on disk, as pluginId => Plugin, sorted by ID.
     * @var array<string,Plugin>|null
     */
    private static ?array $plugins = null;

    /**
     * One entry per installed plugin, as pluginId => array{comId: int, version: string}.
     * @var array<string,array{comId: int, version: string}>|null
     */
    private static ?array $installations = null;

    /**
     * Overrides the plugins directory. Only used by tests and by the installation of an uploaded
     * package, which validates a plugin before it is moved into place.
     * @var string|null
     */
    private static ?string $pluginsPath = null;

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Absolute path of the plugins directory.
     * @return string
     */
    public static function getPluginsPath(): string
    {
        return self::$pluginsPath ?? ADMIDIO_PATH . FOLDER_PLUGINS;
    }

    /**
     * Read every plugin directory. The result is cached for the request, so that the manifests are
     * decoded only once.
     * @return array<string,Plugin>
     */
    public static function all(): array
    {
        if (self::$plugins !== null) {
            return self::$plugins;
        }

        self::$plugins = array();
        $path = self::getPluginsPath();
        $entries = is_dir($path) ? scandir($path) : false;

        foreach ($entries === false ? array() : $entries as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir($path . '/' . $entry)) {
                continue;
            }
            self::$plugins[$entry] = Plugin::read($path . '/' . $entry);
        }

        ksort(self::$plugins);

        return self::$plugins;
    }

    /**
     * One plugin by its ID, or **null** if there is no such directory.
     * @param string $id
     * @return Plugin|null
     */
    public static function get(string $id): ?Plugin
    {
        return self::all()[$id] ?? null;
    }

    /**
     * The installed plugins, as pluginId => array{comId: int, version: string}. This is one query
     * per request instead of one per plugin.
     * @return array<string,array{comId: int, version: string}>
     * @throws Exception
     */
    public static function getInstallations(): array
    {
        global $gDb;

        if (self::$installations !== null) {
            return self::$installations;
        }

        self::$installations = array();
        $sql = 'SELECT com_id, com_uuid, com_name_intern, com_version
                  FROM ' . TBL_COMPONENTS . '
                 WHERE com_type = ?';
        $statement = $gDb->queryPrepared($sql, array(self::COMPONENT_TYPE));

        while ($row = $statement->fetch()) {
            self::$installations[(string)$row['com_name_intern']] = array(
                'comId' => (int)$row['com_id'],
                'uuid' => (string)$row['com_uuid'],
                'version' => (string)$row['com_version']
            );
        }

        return self::$installations;
    }

    /**
     * Whether the plugin has a row in the components table.
     * @param string $id
     * @return bool
     * @throws Exception
     */
    public static function isInstalled(string $id): bool
    {
        return isset(self::getInstallations()[$id]);
    }

    /**
     * Whether a plugin is part of the Admidio distribution rather than something that was added.
     * @param string $id
     * @return bool
     */
    public static function isBuiltIn(string $id): bool
    {
        return in_array($id, self::BUILT_IN, true);
    }

    /**
     * The plugin that owns a preference, or **null** if no plugin declares it.
     *
     * A plugin's settings are ordinary Admidio preferences, so a change to one is already written to
     * the changelog. What was missing was the connection back: this is what lets a log entry name the
     * plugin it belongs to, and the plugin administration link to a plugin's own history.
     * @param string $name Name of the preference.
     * @return Plugin|null
     */
    public static function getOwnerOfSetting(string $name): ?Plugin
    {
        if (self::$settingOwners === null) {
            self::$settingOwners = array();

            foreach (self::all() as $plugin) {
                if (!$plugin->isValid()) {
                    continue;
                }

                // The flag Admidio owns itself counts as the plugin's too: enabling a plugin is one
                // of the things somebody reading its history wants to see.
                self::$settingOwners[$plugin->getEnabledSettingName()] = $plugin->id;
                foreach (array_keys($plugin->settings) as $setting) {
                    self::$settingOwners[$setting] = $plugin->id;
                }
            }
        }

        return isset(self::$settingOwners[$name]) ? self::get(self::$settingOwners[$name]) : null;
    }

    /**
     * The installed version of a plugin, or an empty string if it is not installed.
     * @param string $id
     * @return string
     * @throws Exception
     */
    public static function getInstalledVersion(string $id): string
    {
        return self::getInstallations()[$id]['version'] ?? '';
    }

    /**
     * The component id of an installed plugin, or 0.
     * @param string $id
     * @return int
     * @throws Exception
     */
    public static function getComponentId(string $id): int
    {
        return self::getInstallations()[$id]['comId'] ?? 0;
    }

    /**
     * The UUID of the component record that holds an installed plugin, or an empty string when the
     * plugin has no record yet.
     *
     * It is what the changelog relates a plugin's settings to, so that the history of one plugin can
     * be read on its own.
     * @param string $id
     * @return string
     * @throws Exception
     */
    public static function getComponentUuid(string $id): string
    {
        return self::getInstallations()[$id]['uuid'] ?? '';
    }

    /**
     * Whether the plugin is enabled for the current organization. An unusable or uninstalled
     * plugin is never enabled, whatever the preference says.
     * @param string $id
     * @return bool
     * @throws Exception
     */
    public static function isEnabled(string $id): bool
    {
        global $gSettingsManager;

        $plugin = self::get($id);
        if ($plugin === null || !$plugin->isValid() || !self::isInstalled($id)) {
            return false;
        }

        $name = $plugin->getEnabledSettingName();

        return !isset($gSettingsManager) || !$gSettingsManager->has($name) || $gSettingsManager->getBool($name);
    }

    /**
     * The organizations a plugin is currently enabled in, as a list of their long names.
     *
     * Removing a plugin is an operation on the whole installation, so the administrator has to be
     * told which organizations it is taken away from - including the ones they do not administrate
     * themselves.
     *
     * The rule is the one isEnabled() applies to the current organization: an installed plugin
     * counts as enabled unless an organization has decided against it. A plugin that is not
     * installed is enabled nowhere.
     * @param string $id
     * @return array<int,string> Ordered by name, empty if the plugin is enabled nowhere.
     * @throws Exception
     */
    public static function getEnabledOrganizations(string $id): array
    {
        global $gDb;

        if (!self::isInstalled($id)) {
            return array();
        }

        $preferenceName = 'plugin_' . str_replace('-', '_', $id) . '_enabled';

        $sql = 'SELECT org_longname
                  FROM ' . TBL_ORGANIZATIONS . '
             LEFT JOIN ' . TBL_PREFERENCES . '
                    ON prf_org_id = org_id
                   AND prf_name = ? -- $preferenceName
                 WHERE prf_value IS NULL
                    OR prf_value = \'1\'
              ORDER BY org_longname';

        $statement = $gDb->queryPrepared($sql, array($preferenceName));

        return array_map(strval(...), $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * The state of a plugin, as one of the STATE_* constants.
     * @param Plugin|string $plugin A plugin or the ID of a plugin that may only exist in the
     *                              database.
     * @return string
     * @throws Exception
     */
    public static function getState(Plugin|string $plugin): string
    {
        if (is_string($plugin)) {
            $found = self::get($plugin);
            if ($found === null) {
                return self::isInstalled($plugin) ? self::STATE_ORPHANED : self::STATE_BROKEN;
            }
            $plugin = $found;
        }

        if (!$plugin->isValid()) {
            return self::STATE_BROKEN;
        }
        if (!self::isInstalled($plugin->id)) {
            return $plugin->checkRequirements(self::getEnabledVersions()) === array()
                ? self::STATE_AVAILABLE : self::STATE_BROKEN;
        }
        if (version_compare(self::getInstalledVersion($plugin->id), $plugin->version, '<')) {
            return self::STATE_UPDATE;
        }

        return self::isEnabled($plugin->id) ? self::STATE_ENABLED : self::STATE_DISABLED;
    }

    /**
     * Every plugin that should be loaded in this request: installed, enabled for the current
     * organization, not waiting for an update and with satisfied requirements. Plugin dependencies
     * are resolved first, so that a plugin is only loaded after the plugins it needs.
     * @return array<int,Plugin>
     * @throws Exception
     */
    public static function getLoadable(): array
    {
        $candidates = array();
        foreach (self::all() as $id => $plugin) {
            if ($plugin->isValid() && self::isEnabled($id)
                && !version_compare(self::getInstalledVersion($id), $plugin->version, '<')) {
                $candidates[$id] = $plugin;
            }
        }

        return self::sortByDependencies($candidates);
    }

    /**
     * Version of every installed and enabled plugin, to resolve plugin dependencies.
     * @return array<string,string>
     * @throws Exception
     */
    public static function getEnabledVersions(): array
    {
        $versions = array();
        foreach (self::all() as $id => $plugin) {
            if ($plugin->isValid() && self::isEnabled($id)) {
                $versions[$id] = self::getInstalledVersion($id);
            }
        }

        return $versions;
    }

    /**
     * Run one page of a plugin. This is what a generated stub below modules/ calls, and it is the
     * one place that decides whether the page may run at all.
     *
     * The page is resolved inside the plugin's own pages directory, so a stub can never reach
     * another file, whatever it was generated with.
     * @param string $id Plugin ID.
     * @param string $page File name of the page, e.g. **list.php**.
     * @return void
     * @throws Exception
     */
    public static function requirePage(string $id, string $page): void
    {
        $plugin = self::requireEnabled($id);

        $file = $plugin->getDirectory(Plugin::DIR_PAGES) . '/' . basename($page);
        if (!in_array(basename($page), $plugin->getPages(), true) || !is_file($file)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        require $file;
    }

    /**
     * Verify that a plugin is installed and enabled for the current organization, and return it.
     *
     * A page of a plugin is a file that a browser can request directly, so the state of the plugin
     * has to be checked when the page runs, not only when the menu is built.
     * @param string $id
     * @return Plugin
     * @throws Exception
     */
    public static function requireEnabled(string $id): Plugin
    {
        $plugin = self::get($id);
        if ($plugin === null || !$plugin->isValid() || !self::isEnabled($id)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        return $plugin;
    }

    /**
     * The ID of the plugin the currently running script belongs to, or **null** if the request did
     * not enter through a plugin directory.
     * @return string|null
     */
    public static function getScriptPluginId(): ?string
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
        if ($script === '') {
            return null;
        }

        $script = str_replace('\\', '/', (string)realpath($script));
        $root = rtrim(str_replace('\\', '/', (string)realpath(self::getPluginsPath())), '/');
        if ($script === '' || $root === '' || !str_starts_with($script, $root . '/')) {
            return null;
        }

        $id = strstr(substr($script, strlen($root) + 1), '/', true);

        return ($id !== false && Plugin::isValidId($id)) ? $id : null;
    }

    /**
     * Forget the cached discovery and installation state. This is needed after a plugin was
     * installed, updated or removed, and by tests.
     * @return void
     */
    public static function reset(): void
    {
        self::$plugins = null;
        self::$installations = null;
        self::$settingOwners = null;
    }

    /**
     * Read the plugins from another directory. Used by tests and when an uploaded package is
     * validated before it is moved into the plugins directory. Pass **null** to restore the
     * regular plugins directory.
     * @param string|null $path
     * @return void
     */
    public static function setPluginsPath(?string $path): void
    {
        self::$pluginsPath = $path === null ? null : rtrim(str_replace('\\', '/', $path), '/');
        self::$plugins = null;
        self::$settingOwners = null;
    }

    /**
     * Supply the installation state instead of reading it from the database. Used by tests and by
     * the CLI bootstrap that runs without a database.
     * @param array<string,array{comId: int, version: string}>|null $installations
     * @return void
     */
    public static function setInstallations(?array $installations): void
    {
        self::$installations = $installations;
    }

    /**
     * Order the plugins so that every plugin comes after the plugins it requires. A dependency
     * cycle cannot be ordered; the plugins in it keep their alphabetical order, because refusing to
     * load them would be a worse answer than loading them in an arbitrary but stable order.
     * @param array<string,Plugin> $plugins
     * @return array<int,Plugin>
     */
    private static function sortByDependencies(array $plugins): array
    {
        $sorted = array();
        $visiting = array();

        $visit = function (Plugin $plugin) use (&$visit, &$sorted, &$visiting, $plugins): void {
            if (isset($sorted[$plugin->id]) || isset($visiting[$plugin->id])) {
                return;
            }
            $visiting[$plugin->id] = true;
            foreach (array_keys($plugin->requires['plugins']) as $requiredId) {
                if (isset($plugins[$requiredId])) {
                    $visit($plugins[$requiredId]);
                }
            }
            unset($visiting[$plugin->id]);
            $sorted[$plugin->id] = $plugin;
        };

        foreach ($plugins as $plugin) {
            $visit($plugin);
        }

        return array_values($sorted);
    }
}
