<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Handle different components of Admidio (e.g. system, plugins or modules) and manage them in the database
 *
 * The class search in the database table **adm_components** for a specific component
 * and loads the data into this object. A component could be per default the **SYSTEM**
 * itself, a module or a plugin. There are methods to check the version of the system.
 *
 * **Code example**
 * ```
 * // check if database and filesystem have same version
 * try
 * {
 *     $systemComponent = new Component($gDb);
 *     $systemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
 *     $systemComponent->checkDatabaseVersion(true, 'administrator@example.com');
 * }
 * catch(AdmException $e)
 * {
 *     $e->showHtml();
 * }
 * ```
 */
class Component extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_component.
     * If the id is set than the specific component will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $comId    The recordset of the component with this id will be loaded.
     *                           If com_id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $comId = 0)
    {
        parent::__construct($database, TBL_COMPONENTS, 'com', $comId);
    }

    /**
     * Check version of component in database against the version of the file system.
     * There will be different messages shown if versions aren't equal. If database has minor
     * version than a link to update the database will be shown. If filesystem has minor version
     * than a link to download current version will be shown.
     * @throws AdmException SYS_DATABASE_VERSION_INVALID
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

            throw new AdmException($errorMessage);
        } elseif ($this->getValue('com_update_completed') !== true) {
            $errorMessage = 'The update to version ' . $filesystemVersion . ' was not successfully finished!<br />
                The last update step that was successfully performed was step ' . $this->getValue('com_update_step') .
                ' of version ' . $dbVersion . '.';
            $gLogger->warning($errorMessage);

            throw new AdmException($errorMessage);
        }

        $returnCode = version_compare($dbVersion, $filesystemVersion);

        if ($returnCode === -1) { // database has minor version
            $gLogger->warning(
                'UPDATE: Database-Version is lower than the filesystem!',
                array('versionDB' => $dbVersion, 'versionFileSystem' => $filesystemVersion)
            );

            throw new AdmException('SYS_DATABASE_VERSION_INVALID', array($dbVersion, ADMIDIO_VERSION_TEXT,
                                   '<a href="' . ADMIDIO_URL . FOLDER_INSTALLATION . '/update.php">', '</a>'));
        }
        if ($returnCode === 1) { // filesystem has minor version
            $gLogger->warning(
                'UPDATE: Filesystem-Version is lower than the database!',
                array('versionDB' => $dbVersion, 'versionFileSystem' => $filesystemVersion)
            );

            throw new AdmException('SYS_FILESYSTEM_VERSION_INVALID', array($dbVersion, ADMIDIO_VERSION_TEXT,
                                   '<a href="' . ADMIDIO_HOMEPAGE . 'download.php">', '</a>'));
        }
    }

    /**
     * This method checks if the current user is allowed to edit and administrate the component. Therefore
     * special checks for each component were done.
     * @param string $componentName The name of the component that is stored in the column com_name_intern e.g. GROUPS-ROLES
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @return bool Return true if the current user is allowed to view the component
     */
    public static function isAdministrable($componentName)
    {
        global $gValidLogin, $gCurrentUser, $gSettingsManager;

        if (self::isVisible($componentName)) {
            switch ($componentName) {
                case 'ANNOUNCEMENTS':
                    if ($gCurrentUser->editAnnouncements()) {
                        return true;
                    }
                    break;

                case 'CATEGORY-REPORT':
                    if ($gCurrentUser->checkRolesRight('rol_assign_roles')) {
                        return true;
                    }
                    break;

                case 'DATES':
                    if ($gCurrentUser->editDates()) {
                        return true;
                    }
                    break;

                case 'DOCUMENTS-FILES':
                    if ($gCurrentUser->adminDocumentsFiles()) {
                        return true;
                    }
                    break;

                case 'GUESTBOOK':
                    if ($gCurrentUser->editGuestbookRight()) {
                        return true;
                    }
                    break;

                case 'LINKS':
                    if ($gCurrentUser->editWeblinksRight()) {
                        return true;
                    }
                    break;

                case 'GROUPS-ROLES':
                    if ($gCurrentUser->manageRoles()) {
                        return true;
                    }
                    break;

                case 'MEMBERS':
                    if ($gCurrentUser->editUsers()) {
                        return true;
                    }
                    break;

                case 'PHOTOS':
                    if ($gCurrentUser->editPhotoRight()) {
                        return true;
                    }
                    break;

                case 'PROFILE':
                    if ($gCurrentUser->hasRightEditProfile($gCurrentUser)) {
                        return true;
                    }
                    break;

                case 'REGISTRATION':
                    if ($gCurrentUser->approveUsers()) {
                        return true;
                    }
                    break;

                case 'CORE': // fallthrough
                case 'BACKUP': // fallthrough
                case 'CATEGORIES': // fallthrough
                case 'MENU': // fallthrough
                case 'MESSAGES': // fallthrough
                case 'PREFERENCES': // fallthrough
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
     * This method checks if the current user is allowed to view the component. Therefore
     * special checks for each component were done.
     * @param string $componentName The name of the component that is stored in the column com_name_intern e.g. GROUPS-ROLES
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @return bool Return true if the current user is allowed to view the component
     */
    public static function isVisible($componentName)
    {
        global $gValidLogin, $gCurrentUser, $gSettingsManager;

        switch ($componentName) {
            case 'CORE':
                if ($gValidLogin) {
                    return true;
                }
                break;

            case 'ANNOUNCEMENTS':
                if ((int) $gSettingsManager->get('enable_announcements_module') === 1
                || ((int) $gSettingsManager->get('enable_announcements_module') === 2 && $gValidLogin)) {
                    return true;
                }
                break;

            case 'CATEGORY-REPORT':
                if ($gCurrentUser->checkRolesRight('rol_assign_roles')) {
                    return true;
                }
                break;

            case 'DATES':
                if ((int) $gSettingsManager->get('enable_dates_module') === 1
                || ((int) $gSettingsManager->get('enable_dates_module') === 2 && $gValidLogin)) {
                    return true;
                }
                break;

            case 'DOCUMENTS-FILES':
                if ($gSettingsManager->getBool('documents_files_enable_module')) {
                    return true;
                }
                break;

            case 'GUESTBOOK':
                if ((int) $gSettingsManager->get('enable_guestbook_module') === 1
                || ((int) $gSettingsManager->get('enable_guestbook_module') === 2 && $gValidLogin)) {
                    return true;
                }
                break;

            case 'LINKS':
                if ((int) $gSettingsManager->get('enable_weblinks_module') === 1
                || ((int) $gSettingsManager->get('enable_weblinks_module') === 2 && $gValidLogin)) {
                    return true;
                }
                break;

            case 'GROUPS-ROLES':
                if ($gSettingsManager->getBool('groups_roles_enable_module') && $gValidLogin) {
                    return true;
                }
                break;

            case 'MEMBERS':
                if ($gCurrentUser->editUsers()) {
                    return true;
                }
                break;

            case 'MESSAGES':
                if ($gSettingsManager->getBool('enable_mail_module')
                || ($gSettingsManager->getBool('enable_pm_module') && $gValidLogin)) {
                    return true;
                }
                break;

            case 'PHOTOS':
                if ((int) $gSettingsManager->get('enable_photo_module') === 1
                || ((int) $gSettingsManager->get('enable_photo_module') === 2 && $gValidLogin)) {
                    return true;
                }
                break;

            case 'PROFILE':
                if ($gCurrentUser->hasRightViewProfile($gCurrentUser)) {
                    return true;
                }
                break;

            case 'REGISTRATION':
                if ($gSettingsManager->getBool('registration_enable_module') && $gCurrentUser->approveUsers()) {
                    return true;
                }
                break;

            case 'BACKUP': // fallthrough
            case 'CATEGORIES': // fallthrough
            case 'MENU': // fallthrough
            case 'PREFERENCES': // fallthrough
            case 'ROOMS':
                if ($gCurrentUser->isAdministrator()) {
                    return true;
                }
                break;
        }

        return false;
    }
}
