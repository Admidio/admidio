<?php
namespace Admidio\Components\Entity;

use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\Infrastructure\Plugins\PluginManager;
use Admidio\UI\Presenter\InventoryPresenter;

/**
 * @brief Handle different components of Admidio (e.g. system, plugins or modules) and manage them in the database
 *
 * The class search in the database table **adm_components** for a specific component
 * and loads the data into this object. A component could be per default the **SYSTEM**
 * itself, a module or a plugin. There are methods to check the version of the system.
 *
 * **Code example**
 * ```
 * // check if database and filesystem have same version
 * try {
 *     $systemComponent = new Component($gDb);
 *     $systemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
 *     $systemComponent->checkDatabaseVersion(true, 'administrator@example.com');
 * } catch(Throwable $e) {
 *     $e->showHtml();
 * }
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Component extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_component.
     * If the id is set than the specific component will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $comId The recordset of the component with this id will be loaded.
     *                           If com_id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $comId = 0)
    {
        parent::__construct($database, TBL_COMPONENTS, 'com', $comId);
    }

    /**
     * Check version of component in database against the version of the file system.
     * There will be different messages shown if versions aren't equal. If database has minor
     * version than a link to update the database will be shown. If filesystem has minor version
     * than a link to download current version will be shown.
     * @throws Exception SYS_DATABASE_VERSION_INVALID
     *                      SYS_FILESYSTEM_VERSION_INVALID
     * @return void Nothing will be returned. If the versions aren't equal a message will be shown.
     */
    public function checkDatabaseVersion()
    {
        global $gLogger;

        $dbVersion = $this->getValue('com_version');
        if ($this->getValue('com_beta') > 0) {
            $dbVersion .= '-Beta.' . $this->getValue('com_beta');
        }

        $filesystemVersion = ADMIDIO_VERSION;
        if (ADMIDIO_VERSION_BETA > 0) {
            $filesystemVersion .= '-Beta.' . ADMIDIO_VERSION_BETA;
        }

        if ($this->getValue('com_update_completed') === '') {
            $errorMessage = 'The update to version ' . $filesystemVersion . ' must be done!<br />
                The last update step was step ' . $this->getValue('com_update_step') .
                ' of version ' . $dbVersion . '.';
            $gLogger->warning($errorMessage);

            throw new Exception($errorMessage);
        } elseif ($this->getValue('com_update_completed') !== true) {
            $errorMessage = 'The update to version ' . $filesystemVersion . ' was not successfully finished!<br />
                The last update step that was successfully performed was step ' . $this->getValue('com_update_step') .
                ' of version ' . $dbVersion . '.';
            $gLogger->warning($errorMessage);

            throw new Exception($errorMessage);
        }

        $returnCode = version_compare($dbVersion, $filesystemVersion);

        if ($returnCode === -1) { // database has minor version
            $gLogger->warning(
                'UPDATE: Database-Version is lower than the filesystem!',
                array('versionDB' => $dbVersion, 'versionFileSystem' => $filesystemVersion)
            );

            throw new Exception('SYS_DATABASE_VERSION_INVALID', array($dbVersion, ADMIDIO_VERSION_TEXT,
                                   '<a href="' . ADMIDIO_URL . FOLDER_INSTALLATION . '/update.php">', '</a>'));
        }
        if ($returnCode === 1) { // filesystem has minor version
            $gLogger->warning(
                'UPDATE: Filesystem-Version is lower than the database!',
                array('versionDB' => $dbVersion, 'versionFileSystem' => $filesystemVersion)
            );

            throw new Exception('SYS_FILESYSTEM_VERSION_INVALID', array($dbVersion, ADMIDIO_VERSION_TEXT,
                                   '<a href="' . ADMIDIO_HOMEPAGE . 'download.php">', '</a>'));
        }
    }

    /**
     * This method checks if the current user is allowed to edit and administrate the component. Therefore,
     * special checks for each component were done.
     * @param string $componentName The name of the component that is stored in the column com_name_intern e.g. GROUPS-ROLES
     * @return bool Return true if the current user is allowed to view the component
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException|Exception
     */
    public static function isAdministrable(string $componentName): bool
    {
        global $gCurrentUser;

        if (self::isVisible($componentName)) {
            switch ($componentName) {
                case 'ANNOUNCEMENTS':
                    if ($gCurrentUser->isAdministratorAnnouncements()) {
                        return true;
                    }
                    break;

                case 'CATEGORY-REPORT':
                    if ($gCurrentUser->checkRolesRight('rol_all_lists_view')) {
                        return true;
                    }
                    break;

                case 'EVENTS':
                    if ($gCurrentUser->isAdministratorEvents()) {
                        return true;
                    }
                    break;

                case 'DOCUMENTS-FILES':
                    if ($gCurrentUser->isAdministratorDocumentsFiles()) {
                        return true;
                    }
                    break;

                case 'INVENTORY':
                    if ($gCurrentUser->isAdministratorInventory()) {
                        return true;
                    }
                    break;

                case 'FORUM':
                    if ($gCurrentUser->isAdministratorForum()) {
                        return true;
                    }
                    break;

                case 'LINKS':
                    if ($gCurrentUser->isAdministratorWeblinks()) {
                        return true;
                    }
                    break;

                case 'GROUPS-ROLES':
                    if ($gCurrentUser->isAdministratorRoles()) {
                        return true;
                    }
                    break;

                case 'CONTACTS':
                    if ($gCurrentUser->isAdministratorUsers()) {
                        return true;
                    }
                    break;

                case 'PHOTOS':
                    if ($gCurrentUser->isAdministratorPhotos()) {
                        return true;
                    }
                    break;

                case 'PROFILE':
                    if ($gCurrentUser->hasRightEditProfile($gCurrentUser)) {
                        return true;
                    }
                    break;

                case 'REGISTRATION':
                    if ($gCurrentUser->isAdministratorRegistration()) {
                        return true;
                    }
                    break;

                case 'CORE': // fallthrough
                case 'CATEGORIES': // fallthrough
                case 'MENU': // fallthrough
                case 'MESSAGES': // fallthrough
                case 'ORGANIZATIONS': // fallthrough
                case 'PREFERENCES': // fallthrough
                case 'PLUGINS': // fallthrough
                case 'ROOMS':
                    if ($gCurrentUser->isAdministrator()) {
                        return true;
                    }
                    break;
            }
        }

        return false;
    }

    /**
     * This method checks if the current user is allowed to view the component. Therefore,
     * special checks for each component were done.
     * @param string $componentName The name of the component that is stored in the column com_name_intern e.g. GROUPS-ROLES
     * @return bool Return true if the current user is allowed to view the component
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException|Exception
     */
    public static function isVisible(string $componentName): bool
    {
        global $gValidLogin, $gCurrentUser, $gSettingsManager, $gDb;

        switch ($componentName) {
            case 'CORE': // fallthrough
            case 'CONTACTS':
                if ($gValidLogin) {
                    return true;
                }
                break;

            case 'ANNOUNCEMENTS':
                if ($gSettingsManager->getInt('announcements_module_enabled') === 1
                || ($gSettingsManager->getInt('announcements_module_enabled') === 2 && $gValidLogin)) {
                    return true;
                }
                break;

            case 'CATEGORY-REPORT':
                if ($gCurrentUser->checkRolesRight('rol_all_lists_view')) {
                    return true;
                }
                break;

            case 'EVENTS':
                if ($gSettingsManager->getInt('events_module_enabled') === 1
                || ($gSettingsManager->getInt('events_module_enabled') === 2 && $gValidLogin)) {
                    return true;
                }
                break;

            case 'DOCUMENTS-FILES':
                if ($gSettingsManager->getInt('documents_files_module_enabled') === 1
                || ($gSettingsManager->getInt('documents_files_module_enabled') === 2 && $gValidLogin)) {
                    try {
                        $documentsRootFolder = new Folder($gDb);
                        $documentsRootFolder->getFolderForDownload('');
                        return true;
                    } catch (Exception $e) {
                        return false;
                    }
                }
                break;

            case 'INVENTORY':
                if ($gSettingsManager->getInt('inventory_module_enabled') === 1
                || ($gSettingsManager->getInt('inventory_module_enabled') === 2 && $gValidLogin)
                || ($gSettingsManager->getInt('inventory_module_enabled') === 3 && $gCurrentUser->isAdministratorInventory())
                || ($gSettingsManager->getInt('inventory_module_enabled') === 4 && ($gCurrentUser->isAdministratorInventory() || InventoryPresenter::isCurrentUserKeeper()))
                || ($gSettingsManager->getInt('inventory_module_enabled') === 5 && $gCurrentUser->isAllowedToSeeInventory())) {
                    return true;
                }
                break;

            case 'FORUM':
                if ($gSettingsManager->getInt('forum_module_enabled') === 1
                || ($gSettingsManager->getInt('forum_module_enabled') === 2 && $gValidLogin)) {
                    return true;
                }
                break;

            case 'LINKS':
                if ($gSettingsManager->getInt('weblinks_module_enabled') === 1
                || ($gSettingsManager->getInt('weblinks_module_enabled') === 2 && $gValidLogin)) {
                    return true;
                }
                break;

            case 'GROUPS-ROLES':
                if ($gSettingsManager->getBool('groups_roles_module_enabled') && $gValidLogin) {
                    return true;
                }
                break;

            case 'MESSAGES':
                if (($gSettingsManager->getInt('mail_module_enabled') === 1 || ($gSettingsManager->getInt('mail_module_enabled') === 2 && $gValidLogin))
                || ($gSettingsManager->getBool('pm_module_enabled') && $gValidLogin)) {
                    return true;
                }
                break;

            case 'PHOTOS':
                if ($gSettingsManager->getInt('photo_module_enabled') === 1
                || ($gSettingsManager->getInt('photo_module_enabled') === 2 && $gValidLogin)) {
                    return true;
                }
                break;

            case 'PROFILE':
                if ($gCurrentUser->hasRightViewProfile($gCurrentUser)) {
                    return true;
                }
                break;

            case 'REGISTRATION':
                if ($gSettingsManager->getBool('registration_module_enabled') && $gCurrentUser->isAdministratorRegistration()) {
                    return true;
                }
                break;

            case 'CATEGORIES': // fallthrough
            case 'MENU': // fallthrough
            case 'ORGANIZATIONS': // fallthrough
            case 'PREFERENCES': // fallthrough
            case 'PLUGINS': // fallthrough
            case 'ROOMS':
                if ($gCurrentUser->isAdministrator()) {
                    return true;
                }
                break;

            default:
                // check if the component is a plugin and it is visible
                $pluginManager = new PluginManager();
                $plugin = $pluginManager->getPluginByName($componentName);
                if ($plugin) {
                    return ($plugin instanceof PluginAbstract) ? $plugin::getInstance()->isVisible() : false;
                }
                break;
        }

        return false;
    }
}
