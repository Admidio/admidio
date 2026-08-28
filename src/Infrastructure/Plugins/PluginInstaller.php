<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Components\Entity\Component;
use Admidio\Components\Entity\ComponentUpdate;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
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
     * Install a plugin: run its installation SQL, create its component record, give its
     * preferences a row in every organization and add its menu entry.
     * @param Plugin $plugin
     * @param bool $addMenuEntry Whether the plugin should get a menu entry below "Extensions".
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

        self::executeSqlFile($plugin, 'install.sql');

        $component = new Component($gDb);
        $component->setValue('com_type', PluginRegistry::COMPONENT_TYPE);
        $component->setValue('com_name', $plugin->name);
        // The update mechanism resolves db_scripts/update_x_y.xml through com_name_intern, so it
        // has to be the directory name and nothing else.
        $component->setValue('com_name_intern', $plugin->id);
        $component->setValue('com_version', $plugin->version);
        $component->save();

        PluginRegistry::reset();

        /*
         * The preferences have to be registered before they can be seeded, and a plugin that is not
         * loaded yet has not registered them.
         */
        PluginLoader::load($plugin);
        PreferencesService::seedDefaults(self::getPreferenceNames($plugin));

        PluginPages::publish($plugin);

        if ($addMenuEntry) {
            self::addMenuEntry($plugin, (int)$component->getValue('com_id'));
        }
    }

    /**
     * Run the update scripts of a plugin up to the version its files declare.
     * @param Plugin $plugin
     * @return void
     * @throws Exception
     */
    public static function update(Plugin $plugin): void
    {
        global $gDb;

        if (!PluginRegistry::isInstalled($plugin->id)) {
            throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($plugin->id));
        }

        $componentUpdate = new ComponentUpdate($gDb);
        $componentUpdate->readDataByColumns(array(
            'com_type' => PluginRegistry::COMPONENT_TYPE,
            'com_name_intern' => $plugin->id
        ));
        $componentUpdate->updatePlugin($plugin->version, self::getUpdateNamespace($plugin));

        PluginRegistry::reset();

        // A new version may bring new preferences; the ones an organization already decided stay.
        PluginLoader::load($plugin);
        PreferencesService::seedDefaults(self::getPreferenceNames($plugin));

        // The new version may bring or drop pages, so the stubs are written from the current list.
        PluginPages::publish($plugin);
        self::updateMenuEntries($plugin, PluginRegistry::getComponentId($plugin->id));
    }

    /**
     * Uninstall a plugin: remove its menu entry, its preferences and its component record.
     * @param Plugin|string $plugin The plugin, or the ID of a plugin whose files are already gone.
     * @param bool $removeData Whether db_scripts/uninstall.sql should run as well. This destroys
     *                         the data of the plugin and is therefore never the default.
     * @return void
     * @throws Exception
     */
    public static function uninstall(Plugin|string $plugin, bool $removeData = false): void
    {
        global $gDb;

        $id = $plugin instanceof Plugin ? $plugin->id : $plugin;
        $componentId = PluginRegistry::getComponentId($id);
        if ($componentId === 0) {
            throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($id));
        }

        PluginPages::unpublish($plugin);
        self::removeMenuEntries($componentId);

        if ($plugin instanceof Plugin) {
            PreferencesService::removePreferences(self::getPreferenceNames($plugin));
            if ($removeData) {
                self::executeSqlFile($plugin, 'uninstall.sql');
            }
        } else {
            // The files are gone, so the only preference whose name is still known is the one that
            // Admidio owns itself.
            PreferencesService::removePreferences(array('plugin_' . str_replace('-', '_', $id) . '_enabled'));
        }

        $component = new Component($gDb, $componentId);
        $component->delete();

        PluginRegistry::reset();
    }

    /**
     * Enable or disable a plugin for the current organization. The plugin keeps everything it has;
     * only the decision whether it is loaded changes.
     * @param Plugin $plugin
     * @param bool $enabled
     * @return void
     * @throws Exception
     */
    public static function setEnabled(Plugin $plugin, bool $enabled): void
    {
        global $gSettingsManager;

        if (!PluginRegistry::isInstalled($plugin->id)) {
            throw new Exception('SYS_PLUGIN_NOT_INSTALLED', array($plugin->id));
        }

        $gSettingsManager->set($plugin->getEnabledSettingName(), $enabled ? '1' : '0');
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
     * Add the menu entry of the plugin below the "Extensions" node. The entry belongs to the
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

        $menuEntry = new MenuEntry($gDb);
        $menuEntry->setValue('men_men_id_parent', 3); // the "Extensions" node
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
    private static function updateMenuEntries(Plugin $plugin, int $componentId): void
    {
        global $gDb;

        if ($componentId === 0) {
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
    private static function removeMenuEntries(int $componentId): void
    {
        global $gDb;

        $statement = $gDb->queryPrepared('SELECT men_id FROM ' . TBL_MENU . ' WHERE men_com_id = ?', array($componentId));

        while ($menuId = $statement->fetchColumn()) {
            $menuEntry = new MenuEntry($gDb, (int)$menuId);
            $menuEntry->delete();
        }
    }
}
