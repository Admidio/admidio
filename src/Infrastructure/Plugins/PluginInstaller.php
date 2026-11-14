<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Components\Entity\Component;
use Admidio\Components\Entity\ComponentUpdate;
use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Preferences\Service\PreferencesService;
use RuntimeException;

/**
 * The lifecycle of a plugin: install, update, uninstall, enable and disable.
 *
 * The four operations are deliberately separate and mean different things:
 *
 * | operation  | scope        | touches data                                        |
 * |------------|--------------|-----------------------------------------------------|
 * | install    | installation | creates the schema and the preferences of the plugin |
 * | update     | installation | runs db_scripts/update_x_y.xml up to the new version |
 * | enable     | organization | nothing, only the preference that loads the plugin   |
 * | disable    | organization | nothing                                              |
 * | uninstall  | installation | removes the preferences, optionally the schema too   |
 *
 * Disabling is therefore always safe and reversible, and uninstalling only destroys data when the
 * administrator explicitly asks for it.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class PluginInstaller
{
    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Install a plugin: run its installation SQL and its update scripts, create its component
     * record, give its preferences a row in every organization and add its menu entry.
     *
     * The update scripts run from the first one on, whatever the plugin is installed over. That
     * makes them the one place where a plugin changes its schema and takes over what an earlier
     * version left behind - tables in an old shape, a config.php from before the plugin system (see
     * LegacyConfig), menu entries it once inserted itself. A step therefore has to cope with a
     * database that is already in the newest shape, which is what install.sql creates: a step that
     * changes a table is written with error="ignore".
     *
     * An installation whose update scripts fail keeps its component record at the last step that
     * ran: the plugin then waits for its update, and update() resumes from that step and completes
     * the installation. Starting over would run install.sql on the tables it already created.
     * @param Plugin $plugin
     * @param bool $addMenuEntry Whether the plugin should get a menu entry below "Extensions". A
     *                           plugin without a page, and one whose manifest says "menu": false,
     *                           never gets one - see Plugin::wantsMenuEntry(). Neither does a plugin
     *                           whose update scripts took over an entry of an earlier version by
     *                           giving it the component of the plugin.
     * @return void
     * @throws Exception
     */
    public static function install(Plugin $plugin, bool $addMenuEntry = true): void
    {
        global $gDb;

        if (PluginRegistry::isInstalled($plugin->id)) {
            throw new Exception('SYS_PLUGIN_ALREADY_INSTALLED', array($plugin->id));
        }

        $problems = $plugin->checkRequirements(PluginRegistry::getEnabledVersions());
        if ($problems !== array()) {
            throw new Exception(implode(' ', $problems));
        }

        // Reading the update files fails on a malformed one. That has to happen before install.sql
        // created any table, or the failed attempt leaves them behind and every retry fails on them.
        $firstUpdateVersion = self::getFirstUpdateVersion($plugin);

        self::executeSqlFile($plugin, 'install.sql');

        $component = new Component($gDb);
        $component->setValue('com_type', PluginRegistry::COMPONENT_TYPE);
        $component->setValue('com_name', $plugin->name);
        // The update mechanism resolves db_scripts/update_x_y.xml through com_name_intern, so it
        // has to be the directory name and nothing else.
        $component->setValue('com_name_intern', $plugin->id);
        $component->setValue('com_version', $firstUpdateVersion ?? $plugin->version);
        $component->save();
        $componentId = (int)$component->getValue('com_id');

        PluginRegistry::reset();

        /*
         * The preferences have to be registered before they can be seeded, and a plugin that is not
         * loaded yet has not registered them - nor the classes its update steps are written in.
         */
        PluginLoader::load($plugin);
        PreferencesService::seedDefaults(self::getPreferenceNames($plugin));

        if ($firstUpdateVersion !== null) {
            try {
                self::runUpdateScripts($plugin);
            } finally {
                // after a failure the record states the checkpoint and that the run did not end
                PluginRegistry::reset();
            }
        }

        PluginPages::publish($plugin);

        // A plugin that only brings a widget or a hook has nothing a menu entry could point at.
        if ($addMenuEntry && $plugin->wantsMenuEntry()) {
            if (self::hasMenuEntry($componentId)) {
                self::updateMenuEntries($plugin, $componentId);
            } else {
                self::addMenuEntry($plugin, $componentId);
            }
        }
    }

    /**
     * Run the update scripts of a plugin up to the version its files declare.
     *
     * A run that failed before resumes from the step it reached. If that run was the one of an
     * installation, the plugin has no menu entry yet, and gets it now - unless its update scripts
     * took one over. Only a failed earlier update of a plugin whose entry an administrator deleted
     * gets the entry back as well.
     * @param Plugin $plugin
     * @return void
     * @throws Exception
     */
    public static function update(Plugin $plugin): void
    {
        if (!PluginRegistry::isInstalled($plugin->id)) {
            throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($plugin->id));
        }

        $resumesFailedRun = !PluginRegistry::isUpdateCompleted($plugin->id);

        self::runUpdateScripts($plugin);

        PluginRegistry::reset();

        // A new version may bring new preferences; the ones an organization already decided stay.
        PluginLoader::load($plugin);
        PreferencesService::seedDefaults(self::getPreferenceNames($plugin));

        // The new version may bring or drop pages, so the stubs are written from the current list.
        PluginPages::publish($plugin);

        $componentId = PluginRegistry::getComponentId($plugin->id);
        if ($resumesFailedRun && $plugin->wantsMenuEntry() && !self::hasMenuEntry($componentId)) {
            self::addMenuEntry($plugin, $componentId);
        } else {
            self::updateMenuEntries($plugin, $componentId);
        }
    }

    /**
     * Update a plugin, replacing its files first where the store publishes something newer.
     *
     * Updating is one action whatever the plugin needs. Where newer files are published they are
     * fetched, and then the update scripts run - which is all that is needed for a plugin whose
     * files somebody replaced by hand. The administrator is told what happened, not which of the
     * two it was, so the web interface and the command line must not differ about it.
     * @param Plugin $plugin
     * @return Plugin The plugin as it is afterwards. That is a different instance than the one
     *                passed in when the files were replaced, and it carries the new version.
     * @throws Exception
     */
    public static function updateWithNewerFiles(Plugin $plugin): Plugin
    {
        if (PluginStore::getNewerRelease($plugin->id) !== null) {
            PluginStore::updateFiles($plugin->id);
            PluginRegistry::reset();

            $updated = PluginRegistry::get($plugin->id);
            if ($updated === null) {
                throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($plugin->id));
            }

            $plugin = $updated;
        }

        self::update($plugin);

        return $plugin;
    }

    /**
     * What removing the plugin would leave behind, in the words of the plugin, for the confirmation
     * of the removal. A plugin answers through the filter **plugin_removal_warnings**, which
     * receives the list of messages so far and the plugin and returns the list with its own
     * messages added:
     * ```
     * Hooks::addFilter('plugin_removal_warnings', function (array $warnings, Plugin $plugin): array {
     *     if ($plugin->id === 'example' && ExampleService::hasRemoteData()) {
     *         $warnings[] = 'The accounts on the remote system are no longer managed.';
     *     }
     *     return $warnings;
     * }, 10, 2);
     * ```
     * A warning never prevents the removal - otherwise a broken plugin could not be removed at
     * all. Only plugins that are loaded can answer, which is the case for a plugin that is enabled
     * in the current organization.
     * @param Plugin|string $plugin The plugin, or the ID of a plugin whose files are already gone.
     * @return array<int,string> The messages, plain text.
     */
    public static function getRemovalWarnings(Plugin|string $plugin): array
    {
        if (!$plugin instanceof Plugin) {
            return array();
        }

        $warnings = Hooks::applyTypedFilters('plugin_removal_warnings', array(), $plugin);
        return array_values(array_filter(
            array_map(static fn (mixed $warning): string => is_string($warning) ? trim($warning) : '', $warnings),
            static fn (string $warning): bool => $warning !== ''
        ));
    }

    /**
     * Remove a plugin completely: its menu entries, its pages, its preferences in every
     * organization, its data, its component record and its files.
     *
     * This is the destructive operation of the plugin lifecycle and the only one there is. Disabling
     * a plugin preserves everything; removing it keeps nothing, so db_scripts/uninstall.sql always
     * runs - the files are gone afterwards, and data no code can ever read again is worse than no
     * data at all.
     *
     * Removing is an operation on the installation, not on one organization: the schema and the
     * files are shared, so it takes the plugin away from every organization at once. The caller has
     * to have made that clear before it gets here.
     *
     * A plugin of the Admidio distribution is refused, because the next core update would put its
     * files back and quietly undo the decision.
     * @param Plugin|string $plugin The plugin, or the ID of a plugin whose files are already gone.
     * @return bool Whether the directory of the plugin could be deleted. A deployment that keeps
     *              plugins/ read-only still gets everything else removed, so the plugin comes back
     *              as available rather than staying half-removed.
     * @throws Exception
     */
    public static function remove(Plugin|string $plugin): bool
    {
        global $gDb;

        $id = $plugin instanceof Plugin ? $plugin->id : $plugin;

        if (PluginRegistry::isBuiltIn($id)) {
            throw new Exception('SYS_PLUGIN_REMOVE_BUILT_IN', array($id));
        }

        $componentId = PluginRegistry::getComponentId($id);

        PluginPages::unpublish($plugin);

        if ($componentId > 0) {
            self::removeMenuEntries($componentId);
        }

        if ($plugin instanceof Plugin) {
            if ($componentId > 0) {
                self::executeSqlFile($plugin, 'uninstall.sql');
            }
            PreferencesService::removePreferences(self::getPreferenceNames($plugin));
        } else {
            // The files are gone, so the only preference whose name is still known is the one that
            // Admidio owns itself.
            PreferencesService::removePreferences(array('plugin_' . Plugin::getSettingKey($id) . '_enabled'));
        }

        if ($componentId > 0) {
            $component = new Component($gDb, $componentId);
            $component->delete();
        }

        $deleted = $plugin instanceof Plugin ? self::deleteDirectory($plugin) : true;

        // A plugin that is installed again later, even in the same version, starts without a crash.
        PluginCrashGuard::remove($id);
        PluginRegistry::reset();

        return $deleted;
    }

    /**
     * Delete the directory of a plugin.
     * @param Plugin $plugin
     * @return bool **false** if the directory is still there, which is what a read-only deployment
     *              of plugins/ produces. That is not an error: everything the database held is gone
     *              either way, and the administrator is told that the files remain.
     */
    private static function deleteDirectory(Plugin $plugin): bool
    {
        global $gLogger;

        try {
            FileSystemUtils::deleteDirectoryIfExists($plugin->path, true);
        } catch (\Throwable $exception) {
            $gLogger->warning(
                'The directory of the plugin "' . $plugin->id . '" could not be deleted.',
                array('path' => $plugin->path, 'error' => $exception->getMessage())
            );

            return false;
        }

        return !is_dir($plugin->path);
    }

    /**
     * Enable a plugin for the current organization, preparing it first if that has not happened yet.
     *
     * Whether a plugin has a component record and preference rows is not a state an administrator
     * has any reason to think about: to them a plugin on disk is available and one they switched on
     * is enabled. So the first organization that enables a plugin is what installs it, and every
     * organization after that only flips its own preference.
     * @param Plugin $plugin
     * @return void
     * @throws Exception
     */
    public static function enable(Plugin $plugin): void
    {
        global $gSettingsManager, $gCurrentSession;

        if (!PluginRegistry::isInstalled($plugin->id)) {
            self::install($plugin);
        }

        $gSettingsManager->set($plugin->getEnabledSettingName(), '1');

        if (isset($gCurrentSession)) {
            /*
             * Component::isVisible() decides the menu entry of a plugin (and whether its pages may
             * be opened) from this same preference, but $gSettingsManager is cached in the session
             * of every active user - including this one, on its very next request - exactly like
             * every other organization-wide preference change (PreferencesService::save() does the
             * same). Without this, a freshly enabled plugin's menu entry only appears once something
             * else happens to invalidate that cache, e.g. logging out and back in.
             */
            $gCurrentSession->reloadAllSessions();
        }
    }

    /**
     * Disable a plugin for the current organization. It keeps its files, its data and every setting
     * it has; only the decision whether it is loaded changes.
     * @param Plugin $plugin
     * @return void
     * @throws Exception
     */
    public static function disable(Plugin $plugin): void
    {
        global $gSettingsManager, $gCurrentSession;

        if (!PluginRegistry::isInstalled($plugin->id)) {
            throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($plugin->id));
        }

        $gSettingsManager->set($plugin->getEnabledSettingName(), '0');

        if (isset($gCurrentSession)) {
            // See enable(): the menu entry (and the block on opening the plugin's pages) is decided
            // from this preference, and every active session's cached copy of it needs to catch up.
            $gCurrentSession->reloadAllSessions();
        }
    }

    /**
     * The preferences an installed plugin owns: the ones it declares plus the one that enables it.
     * @param Plugin $plugin
     * @return array<int,string>
     */
    public static function getPreferenceNames(Plugin $plugin): array
    {
        return array_merge(array($plugin->getEnabledSettingName()), array_keys($plugin->settings));
    }

    /**
     * The namespace in which the update steps of a plugin look for their **UpdateStepsCode** class.
     * It is the first namespace of the autoload mapping plus **Service\**, so a plugin that maps
     * **AdmidioPlugin\Hello\** to **src/** writes its update code in
     * **src/Service/UpdateStepsCode.php**.
     * @param Plugin $plugin
     * @return string
     */
    private static function getUpdateNamespace(Plugin $plugin): string
    {
        $prefixes = array_keys($plugin->autoload);

        return $prefixes === array() ? '' : $prefixes[0] . 'Service\\';
    }

    /**
     * Run the update scripts of a plugin, from the version and step its component record stands at
     * up to the version its files declare.
     * @param Plugin $plugin
     * @return void
     * @throws Exception
     */
    private static function runUpdateScripts(Plugin $plugin): void
    {
        global $gDb;

        $componentUpdate = new ComponentUpdate($gDb);
        $componentUpdate->readDataByColumns(array(
            'com_type' => PluginRegistry::COMPONENT_TYPE,
            'com_name_intern' => $plugin->id
        ));
        $componentUpdate->updatePlugin($plugin->version, self::getUpdateNamespace($plugin));
    }

    /**
     * The lowest version a plugin has an update script for, which is where an installation starts.
     * Every update script is read on the way, so that a malformed one fails the installation before
     * anything was changed.
     * @param Plugin $plugin
     * @return string|null The version as **x.y.0**, or **null** for a plugin without update scripts.
     * @throws Exception
     */
    private static function getFirstUpdateVersion(Plugin $plugin): ?string
    {
        $directory = $plugin->getDirectory(Plugin::DIR_DB_SCRIPTS);
        $first = null;

        foreach ($directory === null ? array() : (scandir($directory) ?: array()) as $file) {
            if (preg_match('/^update_(\d+)_(\d+)\.xml$/', $file, $matches) === 1) {
                $previous = libxml_use_internal_errors(true);
                $readable = simplexml_load_file($directory . '/' . $file) !== false;
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                if (!$readable) {
                    throw new Exception('INS_ERROR_OPEN_FILE', array($directory . '/' . $file));
                }

                $version = (int)$matches[1] . '.' . (int)$matches[2] . '.0';
                if ($first === null || version_compare($version, $first, '<')) {
                    $first = $version;
                }
            }
        }

        return $first;
    }

    /**
     * Whether the component of a plugin has a menu entry.
     * @param int $componentId
     * @return bool
     * @throws Exception
     */
    private static function hasMenuEntry(int $componentId): bool
    {
        global $gDb;

        $sql = 'SELECT COUNT(*) FROM ' . TBL_MENU . ' WHERE men_com_id = ?';

        return (int)$gDb->queryPrepared($sql, array($componentId))->fetchColumn() > 0;
    }

    /**
     * Run one SQL file of the plugin, if it has one.
     * @param Plugin $plugin
     * @param string $fileName **install.sql** or **uninstall.sql**.
     * @return void
     * @throws Exception
     */
    private static function executeSqlFile(Plugin $plugin, string $fileName): void
    {
        global $gDb;

        $directory = $plugin->getDirectory(Plugin::DIR_DB_SCRIPTS);
        if ($directory === null || !is_file($directory . '/' . $fileName)) {
            return;
        }

        try {
            $statements = Database::getSqlStatementsFromSqlFile($directory . '/' . $fileName);
        } catch (RuntimeException) {
            throw new Exception('INS_ERROR_OPEN_FILE', array($directory . '/' . $fileName));
        }

        foreach ($statements as $statement) {
            $gDb->queryPrepared($statement);
        }
    }

    /**
     * Add the menu entry of the plugin below the "Extensions" node, or below "Administration" or
     * "Modules" if the manifest says so (see Plugin::getMenuParent()). The entry belongs to the
     * component of the plugin, so it disappears with it, and an administrator may rename, move or
     * delete it afterwards like any other menu entry.
     * @param Plugin $plugin
     * @param int $componentId
     * @return void
     * @throws Exception
     */
    private static function addMenuEntry(Plugin $plugin, int $componentId): void
    {
        global $gDb;

        $parentId = (int)$gDb->queryPrepared(
            'SELECT men_id FROM ' . TBL_MENU . ' WHERE men_name_intern = ?',
            array($plugin->getMenuParent())
        )->fetchColumn();

        $menuEntry = new MenuEntry($gDb);
        $menuEntry->setValue('men_men_id_parent', $parentId > 0 ? $parentId : 3); // 3 is the "Extensions" node
        $menuEntry->setValue('men_com_id', $componentId);
        $menuEntry->setValue('men_name', $plugin->name);
        $menuEntry->setValue('men_description', $plugin->description);
        $menuEntry->setValue('men_url', self::getMenuUrl($plugin));
        $menuEntry->setValue('men_icon', $plugin->icon);
        $menuEntry->save();

        $menuEntry->readDataById((int)$menuEntry->getValue('men_id'));
        $menuEntry->setValue('men_name_intern', $plugin->id);
        $menuEntry->save();
    }

    /**
     * The menu URL of a plugin, relative to the Admidio root, because that is how a menu entry
     * stores it.
     * @param Plugin $plugin
     * @return string
     */
    private static function getMenuUrl(Plugin $plugin): string
    {
        return (string)substr($plugin->getUrl(), strlen(ADMIDIO_URL));
    }

    /**
     * Point the menu entries of a plugin at the URL that is live now.
     *
     * Whether the pages of a plugin are published below modules/ is an installation decision that
     * can change, so the stored URL is refreshed whenever the pages are written again. An entry an
     * administrator pointed somewhere else on purpose is left alone.
     * @param Plugin $plugin
     * @param int $componentId
     * @return void
     * @throws Exception
     */
    public static function updateMenuEntries(Plugin $plugin, int $componentId): void
    {
        global $gDb;

        if ($componentId === 0 || !isset($gDb)) {
            return;
        }

        $url = self::getMenuUrl($plugin);
        $ownUrls = array(
            FOLDER_PLUGINS . '/' . $plugin->id . '/' . Plugin::ENTRY_FILE,
            FOLDER_PLUGINS . '/' . $plugin->id . '/' . Plugin::DIR_PAGES . '/index.php',
            FOLDER_MODULES . '/' . $plugin->id . '/index.php'
        );

        $statement = $gDb->queryPrepared('SELECT men_id FROM ' . TBL_MENU . ' WHERE men_com_id = ?', array($componentId));

        while ($menuId = $statement->fetchColumn()) {
            $menuEntry = new MenuEntry($gDb, (int)$menuId);
            if (in_array((string)$menuEntry->getValue('men_url'), $ownUrls, true)) {
                $menuEntry->setValue('men_url', $url);
                $menuEntry->save();
            }
        }
    }

    /**
     * Remove the menu entries that belong to the component of the plugin.
     *
     * They are read and deleted through the entity, so that the deletion reaches the changelog like
     * every other deletion of a menu entry.
     * @param int $componentId
     * @return void
     * @throws Exception
     */
    public static function removeMenuEntries(int $componentId): void
    {
        global $gDb;

        $statement = $gDb->queryPrepared('SELECT men_id FROM ' . TBL_MENU . ' WHERE men_com_id = ?', array($componentId));

        while ($menuId = $statement->fetchColumn()) {
            $menuEntry = new MenuEntry($gDb, (int)$menuId);
            $menuEntry->delete();
        }
    }
}
