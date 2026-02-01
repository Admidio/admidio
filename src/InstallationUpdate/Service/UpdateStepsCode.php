<?php

namespace Admidio\InstallationUpdate\Service;

use Admidio\Infrastructure\Plugins\PluginManager;
use Admidio\Categories\Entity\Category;
use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\Maintenance;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Text;
use DateTime;
use PDOException;
use Ramsey\Uuid\Uuid;
use Admidio\Infrastructure\Exception;
use RuntimeException;
use UnexpectedValueException;

// this must be declared for backwards compatibility. Can be removed if update scripts don't use it anymore
const TBL_DATES = TABLE_PREFIX . '_dates';

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class UpdateStepsCode
{
    /**
     * @var Database
     */
    private static Database $db;

    /**
     * Set the database
     * @param Database $database The database instance
     */
    public static function setDatabase(Database $database)
    {
        self::$db = $database;
    }

    public static function updateStep51CheckFor3rdPartyPlugins(): void
    {
        global $gLogger, $gL10n;

        $arrayOldOverviewPlugins = array('announcement-list', 'birthday', 'calendar', 'event-list', 'latest-documents-files', 'login_form', 'random_photo', 'who-is-online');
        $oldFolderPluginPath = ADMIDIO_PATH . '/adm_plugins';
        $gWarnOldPlugins = false;
        $gWarn3rdPartyPlugins = false;
        $gInfo3rdPartyPlugins = false;
        $gWarnOldPluginsFolder = false;

        // check if the old adm_plugins folder exists
        if (!is_dir($oldFolderPluginPath)) {
            return;
        }

        // get all folders inside the adm_plugins folder
        $pluginFolders = FileSystemUtils::getDirectoryContent($oldFolderPluginPath, false, true, array(FileSystemUtils::CONTENT_TYPE_DIRECTORY));
        foreach ($pluginFolders as $oldPluginPath => $type) {
            $folderName = basename($oldPluginPath);
            if (in_array($folderName, $arrayOldOverviewPlugins)) {
                // the old plugin is no longer supported, so we remove it
                try {
                    FileSystemUtils::deleteDirectoryIfExists($oldPluginPath, true);
                } catch (Exception|RuntimeException|UnexpectedValueException $exception) {
                    // no rights to delete the old folder, then continue the update process
                    $gWarnOldPlugins = true;
                    continue;
                }
            } else {
                // there is a 3rd party plugin installed, so we try to move it to the new plugin folder
                $newPluginPath = ADMIDIO_PATH . FOLDER_PLUGINS . DIRECTORY_SEPARATOR . $folderName;
                try {
                    FileSystemUtils::moveDirectory($oldPluginPath, $newPluginPath);
                    // now we need to check if there is a menu entry for this plugin and if yes we need to update the path by replacing adm_plugins with plugins
                    $sql = 'UPDATE ' . TBL_MENU . ' SET men_url = REPLACE(men_url, \'adm_plugins/' . $folderName . '\', \'' . DIRECTORY_SEPARATOR . FOLDER_PLUGINS . DIRECTORY_SEPARATOR . $folderName . '\') WHERE men_url LIKE \'%adm_plugins/' . $folderName . '%\' ';
                    self::$db->queryPrepared($sql);
                    $gInfo3rdPartyPlugins = true;
                } catch (Exception|PDOException|RuntimeException|UnexpectedValueException $exception) {
                    // no rights to move the old folder, then continue the update process
                    $gWarn3rdPartyPlugins = true;
                    continue;
                }
            }
        }

        if ($gWarnOldPlugins) {
            $gLogger->warning($gL10n->get('INS_WARNING_OLD_ADM_PLUGINS_COULD_NOT_BE_DELETED', array('adm_plugins', 'adm_plugins')));
        }
        if ($gWarn3rdPartyPlugins) {
            $gLogger->warning($gL10n->get('INS_WARNING_3RD_PARRTY_PLUGINS_COULD_NOT_BE_MOVED', array('plugins', 'adm_plugins')));
        }
        if ($gInfo3rdPartyPlugins) {
            $gLogger->info($gL10n->get('INS_INFO_3RD_PARRTY_PLUGINS_HAVE_BEEN_MOVED', array('plugins')));
        }

        if (!$gWarnOldPlugins && !$gWarn3rdPartyPlugins) {
            // if nothing happened we can delete the old adm_plugins folder
            try {
                FileSystemUtils::deleteDirectoryIfExists($oldFolderPluginPath, false);
            } catch (Exception|RuntimeException|UnexpectedValueException $exception) {
                // no rights to delete the old folder, then continue the update process
                // but warn the user that the folder could not be deleted
                $gWarnOldPluginsFolder = true;
                $gLogger->warning($gL10n->get('INS_WARNING_OLD_ADM_PLUGINS_FOLDER_COULD_NOT_BE_DELETED', array('adm_plugins', 'adm_plugins')));
            }
        }

        // set a cookie to show the warnings/information after the update process
        if ($gWarnOldPlugins || $gWarn3rdPartyPlugins || $gWarnOldPluginsFolder || $gInfo3rdPartyPlugins) {
            $cookieValue = array(
                'warn_old_plugins' => $gWarnOldPlugins,
                'warn_3rd_party_plugins' => $gWarn3rdPartyPlugins,
                'warn_old_plugins_folder' => $gWarnOldPluginsFolder,
                'info_3rd_party_plugins' => $gInfo3rdPartyPlugins
            );
            setcookie('adm_update_plugins_warnings', json_encode($cookieValue), time() + 3600, '/');
        }
    }

    public static function updateStep51InstallOverviewPlugins(): void
    {
        global $gDb;

        // because we added the new column com_overview_plugin to the components table before, we need to reload the database columns
        $gDb->initializeTableColumnProperties();

        $pluginManager = new PluginManager();
        $plugins = $pluginManager->getAvailablePlugins();

        foreach ($plugins as $plugin) {
            $instance = $plugin['interface']::getInstance();
            if ($instance->isAdmidioPlugin()) {
                // Install the overview plugin
                $instance->doInstall();
            }
        }
    }

    public static function updateStep50MoveFieldListValues()
    {
        global $gDbType;

        $sql = 'SELECT usf_id, usf_value_list
                  FROM ' . TBL_USER_FIELDS . '
                 WHERE usf_type IN (\'DROPDOWN\', \'RADIO_BUTTON\')';

        $userFieldsStatement = self::$db->queryPrepared($sql);
        while ($row = $userFieldsStatement->fetch()) {
            $values = explode("\n", $row['usf_value_list']);
            $values = array_map('trim', $values);

            // remove empty values
            $values = array_filter($values, function ($value) {
                return !empty($value);
            });

            if (count($values) > 0) {
                // insert the values into the user field options table
                foreach ($values as $key => $value) {
                    $sql = 'INSERT INTO ' . TBL_USER_FIELD_OPTIONS . ' (ufo_usf_id, ufo_value, ufo_sequence)
                             VALUES (?, ?, ?) -- $row[\'usf_id\'], -- $value, -- $key';

                    self::$db->queryPrepared($sql, array((int)$row['usf_id'], $value, $key + 1));
                }

                if ($gDbType === 'pgsql') {
                    $sqlUfoSequence = 'CAST(ufo_sequence AS CHAR)';
                } else {
                    $sqlUfoSequence = 'ufo_sequence';
                }

                // update the user field values to use the new option id
                $sql = 'UPDATE ' . TBL_USER_DATA . '
                           SET usd_value = (SELECT ufo_id
                                              FROM ' . TBL_USER_FIELD_OPTIONS . '
                                             WHERE ufo_usf_id = usd_usf_id
                                               AND ' . $sqlUfoSequence . ' = usd_value)
                         WHERE usd_usf_id = ? -- $row[\'usf_id\'] ';
                self::$db->queryPrepared($sql, array((int)$row['usf_id']));
            }
        }
    }

    /**
     * Add default fields for the inventory module.
     * @throws Exception
     */
    public static function updateStep50AddInventoryFields()
    {
        $arrItemFields = array(
            array('inf_type' => 'TEXT', 'inf_name_intern' => 'ITEMNAME', 'inf_name' => 'SYS_INVENTORY_ITEMNAME', 'inf_description' => 'SYS_INVENTORY_ITEMNAME_DESC', 'inf_required_input' => 1, 'inf_sequence' => 0),
            array('inf_type' => 'CATEGORY', 'inf_name_intern' => 'CATEGORY', 'inf_name' => 'SYS_CATEGORY', 'inf_description' => 'SYS_INVENTORY_CATEGORY_DESC', 'inf_required_input' => 1, 'inf_sequence' => 1),
            array('inf_type' => 'DROPDOWN', 'inf_name_intern' => 'STATUS', 'inf_name' => 'SYS_INVENTORY_STATUS', 'inf_description' => 'SYS_INVENTORY_STATUS_DESC', 'inf_required_input' => 1, 'inf_sequence' => 2),
            array('inf_type' => 'TEXT', 'inf_name_intern' => 'KEEPER', 'inf_name' => 'SYS_INVENTORY_KEEPER', 'inf_description' => 'SYS_INVENTORY_KEEPER_DESC', 'inf_required_input' => 0, 'inf_sequence' => 3),
            array('inf_type' => 'TEXT', 'inf_name_intern' => 'LAST_RECEIVER', 'inf_name' => 'SYS_INVENTORY_LAST_RECEIVER', 'inf_description' => 'SYS_INVENTORY_LAST_RECEIVER_DESC', 'inf_required_input' => 0, 'inf_sequence' => 4),
            array('inf_type' => 'DATE', 'inf_name_intern' => 'BORROW_DATE', 'inf_name' => 'SYS_INVENTORY_BORROW_DATE', 'inf_description' => 'SYS_INVENTORY_BORROW_DATE_DESC', 'inf_required_input' => 0, 'inf_sequence' => 5),
            array('inf_type' => 'DATE', 'inf_name_intern' => 'RETURN_DATE', 'inf_name' => 'SYS_INVENTORY_RETURN_DATE', 'inf_description' => 'SYS_INVENTORY_RETURN_DATE_DESC', 'inf_required_input' => 0, 'inf_sequence' => 6)
        );

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);
        // create item fields for each organization
        while ($row = $organizationStatement->fetch()) {
            foreach ($arrItemFields as $itemFieldData) {
                $itemField = new ItemField(self::$db);
                $itemField->saveChangesWithoutRights();
                $itemField->setValue('inf_org_id', (int)$row['org_id']);
                $itemField->setValue('inf_type', $itemFieldData['inf_type']);
                $itemField->setValue('inf_name_intern', $itemFieldData['inf_name_intern']);
                $itemField->setValue('inf_name', $itemFieldData['inf_name']);
                $itemField->setValue('inf_description', $itemFieldData['inf_description']);
                $itemField->setValue('inf_system', 1);
                $itemField->setValue('inf_required_input', (int)$itemFieldData['inf_required_input']);
                $itemField->setValue('inf_sequence', (int)$itemFieldData['inf_sequence']);
                $itemField->save();
            }
        }

        // add default options for the status field
        $sql = 'SELECT inf_id FROM ' . TBL_INVENTORY_FIELDS . '
                 WHERE inf_name_intern = \'STATUS\'';
        $statusFieldId = self::$db->queryPrepared($sql)->fetchColumn();

        if ($statusFieldId !== false) {
            $arrStatusOptions = array(
                array('inf_name' => 'SYS_INVENTORY_FILTER_IN_USE_ITEMS', 'ifo_sequence' => 1),
                array('inf_name' => 'SYS_INVENTORY_FILTER_RETIRED_ITEMS', 'ifo_sequence' => 2),
            );

            foreach ($arrStatusOptions as $statusOption) {
                $sql = 'INSERT INTO ' . TBL_INVENTORY_FIELD_OPTIONS . '
                         (ifo_inf_id, ifo_value, ifo_system, ifo_sequence)
                         VALUES (?, ?, ?, ?)';
                self::$db->queryPrepared($sql, array($statusFieldId, $statusOption['inf_name'], true, $statusOption['ifo_sequence']));
            }
        }
    }

    /**
     * Create categories for the inventory for each organization.
     * @throws Exception
     */
    public static function updateStep50InventoryCategories()
    {
        global $gL10n;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM ' . TBL_USERS . '
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = self::$db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int)$systemUserStatement->fetchColumn();

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $sql = 'INSERT INTO ' . TBL_CATEGORIES . '
                           (cat_org_id, cat_uuid, cat_type, cat_name_intern, cat_name, cat_system, cat_default, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                    VALUES (?, ?, \'IVT\', \'COMMON\', \'SYS_COMMON\', false, true, 1, ?, ?) -- $rowId, $systemUserId, DATETIME_NOW';
            self::$db->queryPrepared($sql, array((int)$row['org_id'], Uuid::uuid4(), $systemUserId, DATETIME_NOW));

            // set edit role rights to inventory categories for administrator role
            $sql = 'SELECT rol_id
                    FROM ' . TBL_ROLES . '
                    INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                    AND cat_org_id = ? -- $row[\'org_id\']
                    AND cat_type = \'ROL\'
                    WHERE rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\') ';
            $pdoStatement = self::$db->queryPrepared($sql, array($row['org_id'], $gL10n->get('SYS_ADMINISTRATOR')));
            if (($row2 = $pdoStatement->fetch()) !== false) {
                // set edit role rights to inventory categories for role administrator
                $category = new Category(self::$db);
                $category->readDataByColumns(array('cat_org_id' => (int)$row['org_id'], 'cat_type' => 'IVT'));

                $rightCategoryView = new RolesRights(self::$db, 'category_edit', $category->getValue('cat_id'));
                $rightCategoryView->saveRoles(array($row2['rol_id']));
            }
        }
    }

    /**
     * This method will update the sequence of the links in the database.
     * The sequence is used to sort the links within a category.
     * The sequence starts with 1 for each category and is incremented by 1 for each link.
     * @throws Exception
     */
    public static function updateStep50AddLinkSequence()
    {
        $sql = 'SELECT lnk_id, lnk_cat_id FROM ' . TBL_LINKS . ' ORDER BY lnk_cat_id, lnk_id';
        $statement = self::$db->queryPrepared($sql);
        $currentCatId = null;
        $sequence = 1;

        while ($row = $statement->fetch()) {
            if ($currentCatId !== $row['lnk_cat_id']) {
                $currentCatId = $row['lnk_cat_id'];
                $sequence = 1;
            }
            $updateSql = 'UPDATE ' . TBL_LINKS . ' SET lnk_sequence = ? WHERE lnk_id = ?';
            self::$db->queryPrepared($updateSql, [$sequence, $row['lnk_id']]);
            $sequence++;
        }
    }

    /**
     * Create categories for the forum and each organization.
     * @throws Exception
     */
    public static function updateStep50ForumCategories()
    {
        global $gL10n;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            // create organization depending on category for events
            $category = new Category(self::$db);
            $category->setValue('cat_org_id', (int)$row['org_id']);
            $category->setValue('cat_type', 'FOT');
            $category->setValue('cat_name_intern', 'COMMON');
            $category->setValue('cat_name', $gL10n->get('SYS_COMMON'));
            $category->setValue('cat_default', '1');
            $category->save();

            $sql = 'SELECT rol_id
                      FROM ' . TBL_ROLES . '
                     INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                       AND cat_org_id = ? -- $row[\'org_id\']
                       AND cat_type = \'ROL\'
                     WHERE rol_name = ? -- $gL10n->get(\'SYS_MEMBER\') ';
            $pdoStatement = self::$db->queryPrepared($sql, array($row['org_id'], $gL10n->get('SYS_MEMBER')));

            if (($row = $pdoStatement->fetch()) !== false) {
                // set edit role rights to forum categories for role member
                $rightCategoryView = new RolesRights(self::$db, 'category_edit', $category->getValue('cat_id'));
                $rightCategoryView->saveRoles(array($row['rol_id']));
            }
        }
    }

    /**
     * This method will add a uuid to each row of the tables adm_users and adm_roles
     * @throws Exception
     */
    public static function updateStep50AddUuid()
    {
        $updateTablesUuid = array(
            array('table' => TBL_MESSAGES_ATTACHMENTS, 'column_id' => 'msa_id', 'column_uuid' => 'msa_uuid'),
            array('table' => TBL_USER_RELATIONS, 'column_id' => 'ure_id', 'column_uuid' => 'ure_uuid')
        );

        foreach ($updateTablesUuid as $tableUuid) {
            $sql = 'SELECT ' . $tableUuid['column_id'] . '
                      FROM ' . $tableUuid['table'] . '
                     WHERE ' . $tableUuid['column_uuid'] . ' IS NULL ';
            $statement = self::$db->queryPrepared($sql);

            while ($row = $statement->fetch()) {
                $uuid = Uuid::uuid4();

                $sql = 'UPDATE ' . $tableUuid['table'] . ' SET ' . $tableUuid['column_uuid'] . ' = ? -- $uuid
                     WHERE ' . $tableUuid['column_id'] . ' = ? -- $row[$tableUuid[\'column_id\']]';
                self::$db->queryPrepared($sql, array($uuid, $row[$tableUuid['column_id']]));
            }
        }

        self::$db->initializeTableColumnProperties();
    }

    /**
     * Repair the path of the folders
     */
    public static function updateStep43RepairDocumentsPath()
    {
        $maintenance = new Maintenance(self::$db);
        $maintenance->repairDocumentsFilesPath();
    }

    /**
     * This method removes wrong configured visible roles of category Basic_Data
     * @throws Exception
     */
    public static function updateStep43RemoveInvalidVisibleRoleRights()
    {
        $sql = 'SELECT rrd_id
                  FROM ' . TBL_CATEGORIES . '
                 INNER JOIN ' . TBL_ROLES_RIGHTS . ' ON ror_name_intern = \'category_view\'
                 INNER JOIN ' . TBL_ROLES_RIGHTS_DATA . ' ON rrd_ror_id = ror_id
                   AND rrd_object_id = cat_id
                 WHERE cat_name_intern = \'BASIC_DATA\' ';
        $rolesRightsStatement = self::$db->queryPrepared($sql);

        while ($row = $rolesRightsStatement->fetch()) {
            // save roles to role right
            $rolesRights = new Entity(self::$db, TBL_ROLES_RIGHTS_DATA, 'rrd', (int)$row['rrd_id']);
            $rolesRights->delete();
        }
    }

    /**
     * This method will add a new profile field LinkedIn and Instagram to the database,
     * but only if the category social networks exists
     * @throws Exception
     */
    public static function updateStep43AddSocialNetworkProfileFields()
    {
        global $gProfileFields;

        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_name_intern = \'SOCIAL_NETWORKS\' ';
        $categoriesStatement = self::$db->queryPrepared($sql);

        if ($row = $categoriesStatement->fetch()) {
            $profileFields = $gProfileFields->getProfileFields();

            if (!array_key_exists('LINKEDIN', $profileFields)) {
                $profileFieldLinkedIn = new ProfileField(self::$db);
                $profileFieldLinkedIn->saveChangesWithoutRights();
                $profileFieldLinkedIn->setValue('usf_cat_id', (int)$row['cat_id']);
                $profileFieldLinkedIn->setValue('usf_type', 'TEXT');
                $profileFieldLinkedIn->setValue('usf_name_intern', 'LINKEDIN');
                $profileFieldLinkedIn->setValue('usf_name', 'SYS_LINKEDIN');
                $profileFieldLinkedIn->setValue('usf_description', 'SYS_SOCIAL_NETWORK_FIELD_DESC');
                $profileFieldLinkedIn->setValue('usf_icon', 'linkedin');
                $profileFieldLinkedIn->setValue('usf_url', 'https://www.linkedin.com/in/#user_content#');
                $profileFieldLinkedIn->save();
            }

            if (!array_key_exists('INSTAGRAM', $profileFields)) {
                $profileFieldInstagram = new ProfileField(self::$db);
                $profileFieldInstagram->saveChangesWithoutRights();
                $profileFieldInstagram->setValue('usf_cat_id', (int)$row['cat_id']);
                $profileFieldInstagram->setValue('usf_type', 'TEXT');
                $profileFieldInstagram->setValue('usf_name_intern', 'INSTAGRAM');
                $profileFieldInstagram->setValue('usf_name', 'SYS_INSTAGRAM');
                $profileFieldInstagram->setValue('usf_description', 'SYS_SOCIAL_NETWORK_FIELD_DESC');
                $profileFieldInstagram->setValue('usf_icon', 'instagram');
                $profileFieldInstagram->setValue('usf_url', 'https://www.instagram.com/#user_content#');
                $profileFieldInstagram->save();
            }

            if (!array_key_exists('MASTODON', $profileFields)) {
                $profileFieldInstagram = new ProfileField(self::$db);
                $profileFieldInstagram->saveChangesWithoutRights();
                $profileFieldInstagram->setValue('usf_cat_id', (int)$row['cat_id']);
                $profileFieldInstagram->setValue('usf_type', 'TEXT');
                $profileFieldInstagram->setValue('usf_name_intern', 'MASTODON');
                $profileFieldInstagram->setValue('usf_name', 'SYS_MASTODON');
                $profileFieldInstagram->setValue('usf_description', 'SYS_SOCIAL_NETWORK_FIELD_DESC');
                $profileFieldInstagram->setValue('usf_icon', 'mastodon');
                $profileFieldInstagram->setValue('usf_url', 'https://mastodon.social/#user_content#');
                $profileFieldInstagram->save();
            }
        }
    }

    /**
     * This method will add a new systemmail text to the database table **adm_texts** for each
     * organization in the database.
     * @throws Exception
     */
    public static function updateStep43AddNewNotificationText()
    {
        global $gL10n;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $textPasswordReset = new Text(self::$db);
            $textPasswordReset->setValue('txt_org_id', $row['org_id']);
            $textPasswordReset->setValue('txt_name', 'SYSMAIL_REGISTRATION_CONFIRMATION');
            $textPasswordReset->setValue('txt_text', $gL10n->get('SYS_SYSMAIL_REGISTRATION_CONFIRMATION'));
            $textPasswordReset->save();
        }
    }

    /**
     * This method only execute an sql statement but because of the use of & it could not done in our XML structure
     * @throws Exception
     */
    public static function updateStep41CleanUpRoleNames()
    {
        $sql = 'UPDATE ' . TBL_ROLES . ' SET rol_name = REPLACE(rol_name, \'&nbsp;&nbsp;\', \' \') ';
        self::$db->queryPrepared($sql);
    }

    /**
     * This method will add a new default list for the members management module. This list will be used to configure
     * and show the columns of the members management overview.
     * @throws Exception
     */
    public static function updateStep41CleanUpInternalNameProfileFields()
    {
        $sql = 'SELECT * FROM ' . TBL_USER_FIELDS;
        $userFieldsStatement = self::$db->queryPrepared($sql);

        while ($row = $userFieldsStatement->fetch()) {
            $userField = new ProfileField(self::$db);
            $userField->setArray($row);
            $userField->saveChangesWithoutRights();

            $userField->setValue('usf_name_intern',
                strtoupper(preg_replace('/[^A-Za-z0-9_]/', '',
                    str_replace(' ', '_', $userField->getValue('usf_name_intern')))));
            $userField->save();
        }
    }

    /**
     * This method will add a uuid to each row of the tables adm_users and adm_roles
     * @throws Exception
     */
    public static function updateStep41PostgreSqlSetBoolean()
    {
        $updateColumnsBoolean = array(
            array('table' => TBL_CATEGORIES, 'column' => 'cat_system'),
            array('table' => TBL_CATEGORIES, 'column' => 'cat_default'),
            array('table' => TBL_DATES, 'column' => 'dat_all_day'),
            array('table' => TBL_DATES, 'column' => 'dat_highlight'),
            array('table' => TBL_DATES, 'column' => 'dat_allow_comments'),
            array('table' => TBL_DATES, 'column' => 'dat_additional_guests'),
            array('table' => TBL_FILES, 'column' => 'fil_locked'),
            array('table' => TBL_FOLDERS, 'column' => 'fol_locked'),
            array('table' => TBL_FOLDERS, 'column' => 'fol_public'),
            array('table' => TBL_GUESTBOOK, 'column' => 'gbo_locked'),
            array('table' => TBL_GUESTBOOK_COMMENTS, 'column' => 'gbc_locked'),
            array('table' => TBL_LISTS, 'column' => 'lst_global'),
            array('table' => TBL_MEMBERS, 'column' => 'mem_leader'),
            array('table' => TBL_MENU, 'column' => 'men_node'),
            array('table' => TBL_MENU, 'column' => 'men_standard'),
            array('table' => TBL_PHOTOS, 'column' => 'pho_locked'),
            array('table' => TBL_ROLES, 'column' => 'rol_assign_roles'),
            array('table' => TBL_ROLES, 'column' => 'rol_approve_users'),
            array('table' => TBL_ROLES, 'column' => 'rol_announcements'),
            array('table' => TBL_ROLES, 'column' => 'rol_dates'),
            array('table' => TBL_ROLES, 'column' => 'rol_documents_files'),
            array('table' => TBL_ROLES, 'column' => 'rol_edit_user'),
            array('table' => TBL_ROLES, 'column' => 'rol_guestbook'),
            array('table' => TBL_ROLES, 'column' => 'rol_guestbook_comments'),
            array('table' => TBL_ROLES, 'column' => 'rol_mail_to_all'),
            array('table' => TBL_ROLES, 'column' => 'rol_photo'),
            array('table' => TBL_ROLES, 'column' => 'rol_profile'),
            array('table' => TBL_ROLES, 'column' => 'rol_weblinks'),
            array('table' => TBL_ROLES, 'column' => 'rol_all_lists_view'),
            array('table' => TBL_ROLES, 'column' => 'rol_default_registration'),
            array('table' => TBL_ROLES, 'column' => 'rol_valid'),
            array('table' => TBL_ROLES, 'column' => 'rol_system'),
            array('table' => TBL_ROLES, 'column' => 'rol_administrator'),
            array('table' => TBL_SESSIONS, 'column' => 'ses_reload'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_description_inline'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_system'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_disabled'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_hidden'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_mandatory'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_registration'),
            array('table' => TBL_USERS, 'column' => 'usr_valid'),
            array('table' => TBL_USER_RELATION_TYPES, 'column' => 'urt_edit_user')
        );

        foreach ($updateColumnsBoolean as $columnsBoolean) {
            $sql = 'ALTER TABLE ' . $columnsBoolean['table'] . ' ALTER COLUMN ' . $columnsBoolean['column'] . ' drop default';
            self::$db->queryPrepared($sql);

            $sql = 'ALTER TABLE ' . $columnsBoolean['table'] . ' ALTER COLUMN ' . $columnsBoolean['column'] . ' SET DATA TYPE boolean using ' . $columnsBoolean['column'] . '::integer::boolean';
            self::$db->queryPrepared($sql);

            if ($columnsBoolean['column'] === 'rol_valid') {
                $sql = 'ALTER TABLE ' . $columnsBoolean['table'] . ' ALTER COLUMN ' . $columnsBoolean['column'] . ' SET DEFAULT true';
            } else {
                $sql = 'ALTER TABLE ' . $columnsBoolean['table'] . ' ALTER COLUMN ' . $columnsBoolean['column'] . ' SET DEFAULT false';
            }
            self::$db->queryPrepared($sql);
        }
    }

    /**
     * This method will move the folder with the ecard templates to the adm_my_files folder
     */
    public static function updateStep41MoveEcardTemplates()
    {
        global $gLogger;

        $ecardThemeFolder = ADMIDIO_PATH . FOLDER_THEMES . '/' . $GLOBALS['gSettingsManager']->getString('theme') . '/ecard_templates';
        $ecardMyFilesFolder = ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates';

        if (is_dir($ecardThemeFolder)) {
            try {
                FileSystemUtils::copyDirectory($ecardThemeFolder, $ecardMyFilesFolder);
            } catch (RuntimeException $exception) {
                $gLogger->error('Could not copy directory from ' . $ecardThemeFolder . ' to ' . $ecardMyFilesFolder . '. Please check if Admidio have write rights within adm_my_files.');
                return;
                // => EXIT
            }

            try {
                FileSystemUtils::deleteDirectoryIfExists($ecardThemeFolder);
            } catch (RuntimeException $exception) {
                // no rights to delete the old folder, then continue the update process
                return;
                // => EXIT
            }
        }
    }

    /**
     * This method will migrate the database entries
     * from plugin Kategoriereport (table adm_plugin_preferences)
     * to module category report (table adm_category_report).
     * @throws Exception
     */
    public static function updateStep41CategoryReportMigration()
    {
        global $gL10n, $gProfileFields;

        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
        $organizationsStatement = self::$db->queryPrepared($sql);
        $organizationsArray = $organizationsStatement->fetchAll();

        foreach ($organizationsArray as $organization) {
            $orgId = (int)$organization['org_id'];
            $config = array();

            // check whether a configdata.php exists for the category report plugin
            $file = ADMIDIO_PATH . FOLDER_PLUGINS . '/kategoriereport/configdata.php';
            if (file_exists($file)) {
                include $file; // the value of $dbtoken is required here

                // check whether the table 'adm_plugin_preferences' exists
                $tableName = TABLE_PREFIX . '_plugin_preferences';
                $sql = 'SHOW TABLES LIKE \'' . $tableName . '\' ';
                $tableExistStatement = self::$db->queryPrepared($sql);

                if ($tableExistStatement->rowCount()) {
                    // Read in configuration(s) with 'PKR_...'
                    $sql = 'SELECT plp_id, plp_name, plp_value
                 	          FROM ' . $tableName . '
                 	         WHERE plp_name LIKE ?
                 	           AND (plp_org_id = ?
                     	        OR plp_org_id IS NULL ) ';
                    $statement = self::$db->queryPrepared($sql, array('PKR__%', $orgId));

                    while ($row = $statement->fetch()) {
                        $array = explode('__', $row['plp_name']);

                        if ((substr($row['plp_value'], 0, 2) == '((') && (substr($row['plp_value'], -2) == '))')) {
                            $row['plp_value'] = substr($row['plp_value'], 2, -2);
                            $config[$array[2]] = explode($dbtoken, $row['plp_value']);
                        } else {
                            $config[$array[2]] = $row['plp_value'];
                        }
                    }
                }
            }

            // if $config is still empty now, then there was no configuration data of the plugin
            // --> create sample configuration
            if (empty($config)) {
                $config['col_desc'] = array($gL10n->get('SYS_GENERAL_ROLE_ASSIGNMENT'));
                $config['col_fields'] = array('p' . $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . ',' .
                    'p' . $gProfileFields->getProperty('LAST_NAME', 'usf_id') . ',' .
                    'p' . $gProfileFields->getProperty('STREET', 'usf_id') . ',' .
                    'p' . $gProfileFields->getProperty('CITY', 'usf_id'));
                $config['selection_role'] = array('');
                $config['selection_cat'] = array('');
                $config['number_col'] = array(0);
                $config['config_default'] = 0;

                // Read out the role IDs of the "Administrator", "Board" and "Member" roles
                $role = new Entity(self::$db, TBL_ROLES, 'rol');
                $role->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'rol_cat_id');
                if ($role->readDataByColumns(array('rol_name' => $gL10n->get('SYS_ADMINISTRATOR'), 'cat_org_id' => $orgId))) {
                    $config['col_fields'][0] .= ',r' . $role->getValue('rol_id');
                }
                if ($role->readDataByColumns(array('rol_name' => $gL10n->get('INS_BOARD'), 'cat_org_id' => $orgId))) {
                    $config['col_fields'][0] .= ',r' . $role->getValue('rol_id');
                }
                if ($role->readDataByColumns(array('rol_name' => $gL10n->get('SYS_MEMBER'), 'cat_org_id' => $orgId))) {
                    $config['col_fields'][0] .= ',r' . $role->getValue('rol_id');
                }
            }

            // Write "Kategoriereport" configurations or sample configuration into adm_category_report table
            foreach ($config['col_desc'] as $i => $dummy) {
                $categoryReport = new Entity(self::$db, TBL_CATEGORY_REPORT, 'crt');

                $categoryReport->setValue('crt_org_id', $orgId);
                $categoryReport->setValue('crt_name', $config['col_desc'][$i]);
                $categoryReport->setValue('crt_col_fields', $config['col_fields'][$i]);
                $categoryReport->setValue('crt_selection_role', $config['selection_role'][$i]);
                $categoryReport->setValue('crt_selection_cat', $config['selection_cat'][$i]);
                $categoryReport->setValue('crt_number_col', $config['number_col'][$i]);
                $categoryReport->save();

                if ($config['config_default'] == $i) {
                    $sql = 'UPDATE ' . TBL_PREFERENCES . '
                               SET prf_value  = ? -- $categoryReport->getValue(\'crt_id\')
                             WHERE prf_org_id = ? -- $orgId
                               AND prf_name   = \'category_report_default_configuration\'';
                    self::$db->queryPrepared($sql, array((int)$categoryReport->getValue('crt_id'), $orgId));
                }
            }
        }
    }

    /**
     * This method will add a uuid to each row of the tables adm_users and adm_roles
     * @throws Exception
     */
    public static function updateStep41AddUuid()
    {
        $updateTablesUuid = array(
            array('table' => TBL_ANNOUNCEMENTS, 'column_id' => 'ann_id', 'column_uuid' => 'ann_uuid'),
            array('table' => TBL_CATEGORIES, 'column_id' => 'cat_id', 'column_uuid' => 'cat_uuid'),
            array('table' => TBL_DATES, 'column_id' => 'dat_id', 'column_uuid' => 'dat_uuid'),
            array('table' => TBL_FILES, 'column_id' => 'fil_id', 'column_uuid' => 'fil_uuid'),
            array('table' => TBL_FOLDERS, 'column_id' => 'fol_id', 'column_uuid' => 'fol_uuid'),
            array('table' => TBL_GUESTBOOK, 'column_id' => 'gbo_id', 'column_uuid' => 'gbo_uuid'),
            array('table' => TBL_GUESTBOOK_COMMENTS, 'column_id' => 'gbc_id', 'column_uuid' => 'gbc_uuid'),
            array('table' => TBL_LINKS, 'column_id' => 'lnk_id', 'column_uuid' => 'lnk_uuid'),
            array('table' => TBL_PHOTOS, 'column_id' => 'pho_id', 'column_uuid' => 'pho_uuid'),
            array('table' => TBL_LISTS, 'column_id' => 'lst_id', 'column_uuid' => 'lst_uuid'),
            array('table' => TBL_MENU, 'column_id' => 'men_id', 'column_uuid' => 'men_uuid'),
            array('table' => TBL_MEMBERS, 'column_id' => 'mem_id', 'column_uuid' => 'mem_uuid'),
            array('table' => TBL_MESSAGES, 'column_id' => 'msg_id', 'column_uuid' => 'msg_uuid'),
            array('table' => TBL_ORGANIZATIONS, 'column_id' => 'org_id', 'column_uuid' => 'org_uuid'),
            array('table' => TBL_ROLES, 'column_id' => 'rol_id', 'column_uuid' => 'rol_uuid'),
            array('table' => TBL_ROOMS, 'column_id' => 'room_id', 'column_uuid' => 'room_uuid'),
            array('table' => TBL_USERS, 'column_id' => 'usr_id', 'column_uuid' => 'usr_uuid'),
            array('table' => TBL_USER_FIELDS, 'column_id' => 'usf_id', 'column_uuid' => 'usf_uuid'),
            array('table' => TBL_USER_RELATION_TYPES, 'column_id' => 'urt_id', 'column_uuid' => 'urt_uuid')
        );

        foreach ($updateTablesUuid as $tableUuid) {
            $sql = 'SELECT ' . $tableUuid['column_id'] . '
                      FROM ' . $tableUuid['table'] . '
                     WHERE ' . $tableUuid['column_uuid'] . ' IS NULL ';
            $statement = self::$db->queryPrepared($sql);

            while ($row = $statement->fetch()) {
                $uuid = Uuid::uuid4();

                $sql = 'UPDATE ' . $tableUuid['table'] . ' SET ' . $tableUuid['column_uuid'] . ' = ? -- $uuid
                     WHERE ' . $tableUuid['column_id'] . ' = ? -- $row[$tableUuid[\'column_id\']]';
                self::$db->queryPrepared($sql, array($uuid, $row[$tableUuid['column_id']]));
            }
        }

        self::$db->initializeTableColumnProperties();
    }

    /**
     * This method will add a new default list for the members management module. This list will be used to configure
     * and show the columns of the members management overview.
     * @throws Exception
     */
    public static function updateStep41AddMembersManagementDefaultList()
    {
        global $gL10n, $gProfileFields;

        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
        $organizationsStatement = self::$db->queryPrepared($sql);
        $organizationsArray = $organizationsStatement->fetchAll();

        foreach ($organizationsArray as $organization) {
            // add default configuration
            $userManagementList = new ListConfiguration(self::$db);
            $userManagementList->setValue('lst_name', $gL10n->get('SYS_CONTACTS'));
            $userManagementList->setValue('lst_org_id', (int)$organization['org_id']);
            $userManagementList->setValue('lst_global', 1);
            $userManagementList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
            $userManagementList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
            $userManagementList->addColumn('usr_login_name');
            $userManagementList->addColumn((int)$gProfileFields->getProperty('GENDER', 'usf_id'));
            $userManagementList->addColumn((int)$gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
            $userManagementList->addColumn((int)$gProfileFields->getProperty('CITY', 'usf_id'));
            $userManagementList->addColumn('usr_timestamp_change');
            $userManagementList->save();

            // save default list to preferences
            $sql = 'UPDATE ' . TBL_PREFERENCES . ' SET prf_value = ? -- $userManagementList->getValue(\'lst_id\')
                     WHERE prf_org_id = ? -- $organization[\'org_id\']
                       AND prf_name = \'members_list_configuration\' ';
            self::$db->queryPrepared($sql, array($userManagementList->getValue('lst_id'), (int)$organization['org_id']));
        }
    }

    /**
     * This method will add a new systemmail text to the database table **adm_texts** for each
     * organization in the database.
     * @throws Exception
     */
    public static function updateStep41AddSystemmailText()
    {
        global $gL10n;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $textPasswordReset = new Text(self::$db);
            $textPasswordReset->setValue('txt_org_id', $row['org_id']);
            $textPasswordReset->setValue('txt_name', 'SYSMAIL_PASSWORD_RESET');
            $textPasswordReset->setValue('txt_text', $gL10n->get('SYS_SYSMAIL_PASSWORD_RESET'));
            $textPasswordReset->save();
        }
    }

    /**
     * This method will migrate the recipients of messages from the database column msg_usr_id_receiver
     * to the new table adm_messages_recipients. There each recipient will be add in a separate row that
     * reference to the message.
     * @throws Exception
     */
    public static function updateStep41MigrateMessageRecipients()
    {
        $sql = 'SELECT msg_id, msg_usr_id_receiver FROM ' . TBL_MESSAGES;
        $messagesStatement = self::$db->queryPrepared($sql);

        while ($row = $messagesStatement->fetch()) {
            $messageRecipient = new Entity(self::$db, TBL_MESSAGES_RECIPIENTS, 'msr');
            $recipientsSplit = explode('|', $row['msg_usr_id_receiver']);

            foreach ($recipientsSplit as $recipients) {
                $messageRecipient->clear();
                $messageRecipient->setValue('msr_msg_id', $row['msg_id']);

                if (str_contains($recipients, ':')) {
                    $groupSplit = explode(':', $recipients);
                    $groupIdAndStatus = explode('-', trim($groupSplit[1]));
                    $messageRecipient->setValue('msr_rol_id', $groupIdAndStatus[0]);

                    // set mode of the role (active, former, former and active)
                    if (count($groupIdAndStatus) === 1) {
                        $messageRecipient->setValue('msr_role_mode', 0);
                    } else {
                        $messageRecipient->setValue('msr_role_mode', $groupIdAndStatus[1]);
                    }
                } else {
                    $messageRecipient->setValue('msr_usr_id', (int)trim($recipients));
                }
                $messageRecipient->save();
            }
        }
    }

    /**
     * This method adds the email template to the preferences
     * @throws Exception
     */
    public static function updateStep40AddEmailTemplate()
    {
        if (file_exists(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates/template.html')) {
            $sql = 'UPDATE ' . TBL_PREFERENCES . ' SET prf_value = \'template.html\' WHERE prf_name = \'mail_template\'';
            self::$db->queryPrepared($sql);
        } elseif (file_exists(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates/default.html')) {
            $sql = 'UPDATE ' . TBL_PREFERENCES . ' SET prf_value = \'default.html\' WHERE prf_name = \'mail_template\'';
            self::$db->queryPrepared($sql);
        } else {
            $sql = 'UPDATE ' . TBL_PREFERENCES . ' SET prf_value = \'\' WHERE prf_name = \'mail_template\'';
            self::$db->queryPrepared($sql);
        }
    }

    /**
     * Rename the existing folder of the old download module to the new documents and files module
     * with the prefix 'documents' and the shortname of the current organization.
     * @throws Exception
     */
    public static function updateStep40RenameDownloadRootFolder()
    {
        global $gLogger;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $rowId = (int)$row['org_id'];

            $organization = new Organization(self::$db, $rowId);

            $sql = 'SELECT fol_id, fol_name
                      FROM ' . TBL_FOLDERS . '
                     WHERE fol_fol_id_parent IS NULL
                       AND fol_org_id = ? -- $rowId';
            $folderStatement = self::$db->queryPrepared($sql, array($rowId));

            if ($rowFolder = $folderStatement->fetch()) {
                $folder = new Folder(self::$db, $rowFolder['fol_id']);
                $folderOldName = $folder->getFullFolderPath();
                $folder->setValue('fol_name', Folder::getRootFolderName('documents', $organization->getValue('org_shortname')));
                $folder->save();

                $sql = 'UPDATE ' . TBL_FOLDERS . '
                           SET fol_path = REPLACE(fol_path, \'/' . $rowFolder['fol_name'] . '\', \'/' . Folder::getRootFolderName('documents', $organization->getValue('org_shortname')) . '\')
                         WHERE fol_org_id = ' . $rowId;
                self::$db->query($sql); // TODO add more params

                if (is_dir($folderOldName)) {
                    try {
                        //rename($folderOldName, $folder->getFullFolderPath());
                        FileSystemUtils::moveDirectory($folderOldName, $folder->getFullFolderPath());
                    } catch (RuntimeException $exception) {
                        $gLogger->error('Could not move directory!', array('from' => $folderOldName, 'to' => $folder->getFullFolderPath()));
                        // TODO
                    }
                }
            }
        }
    }

    /**
     * This method will migrate all names of the event roles from the former technical name to the name of the event
     * @throws Exception
     * @throws \Exception
     */
    public static function updateStep40RenameParticipationRoles()
    {
        global $gSettingsManager;

        $sql = 'SELECT *
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                 WHERE cat_name_intern = \'EVENTS\' ';
        $rolesStatement = self::$db->queryPrepared($sql);

        while ($row = $rolesStatement->fetch()) {
            $role = new Entity(self::$db, TBL_ROLES, 'rol');
            $role->setArray($row);
            $role->saveChangesWithoutRights();

            $sql = 'SELECT *
                      FROM ' . TABLE_PREFIX . '_dates
                     WHERE dat_rol_id = ? ';
            $eventStatement = self::$db->queryPrepared($sql, array($role->getValue('rol_id')));
            $eventRow = $eventStatement->fetch();

            $datetime = new DateTime($eventRow['dat_begin']);
            $beginDate = $datetime->format($gSettingsManager->getString('system_date')) . ' ';

            if ($eventRow['dat_all_day'] != 1) {
                $datetime = new DateTime($eventRow['dat_begin']);
                $beginDate .= $datetime->format($gSettingsManager->getString('system_time'));
            }

            $role->setValue('rol_name', $beginDate . ' ' . $eventRow['dat_headline']);
            $role->setValue('rol_description', substr($eventRow['dat_description'], 0, 3999));
            $role->save();
        }
    }

    /**
     * This method adds a new global list configuration for participants of events.
     * @throws Exception
     */
    public static function updateStep33AddDefaultParticipantList()
    {
        global $gL10n;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM ' . TBL_USERS . '
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = self::$db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int)$systemUserStatement->fetchColumn();

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $rowId = (int)$row['org_id'];

            // Add new list configuration
            $sql = 'INSERT INTO ' . TBL_LISTS . '
                           (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global)
                    VALUES (?, ?, ?, ?, 1) -- $rowId, $systemUserId, $gL10n->get(\'SYS_PARTICIPANTS\'), DATETIME_NOW';
            $params = array(
                $rowId,
                $systemUserId,
                $gL10n->get('SYS_PARTICIPANTS'),
                DATETIME_NOW
            );
            self::$db->queryPrepared($sql, $params);

            // Add list columns
            $sql = 'SELECT lst_id
                      FROM ' . TBL_LISTS . '
                     WHERE lst_name = ? -- $gL10n->get(\'SYS_PARTICIPANTS\')
                       AND lst_org_id = ? -- $rowId';
            $listStatement = self::$db->queryPrepared($sql, array($gL10n->get('SYS_PARTICIPANTS'), $rowId));
            $listId = (int)$listStatement->fetchColumn();

            $sql = 'INSERT INTO ' . TBL_LIST_COLUMNS . '
                           (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort, lsc_filter)
                    VALUES (?, 1, (SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_name_intern = \'LAST_NAME\'),  NULL, \'ASC\', NULL) -- $listId
                         , (?, 2, (SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_name_intern = \'FIRST_NAME\'), NULL, NULL,    NULL) -- $listId
                         , (?, 3, NULL, \'mem_approved\',     NULL,    NULL) -- $listId
                         , (?, 4, NULL, \'mem_comment\',      NULL,    NULL) -- $listId
                         , (?, 5, NULL, \'mem_count_guests\', NULL,    NULL) -- $listId';
            self::$db->queryPrepared($sql, array($listId, $listId, $listId, $listId, $listId));

            // Set as default configuration list
            $sql = 'UPDATE ' . TBL_PREFERENCES . '
                       SET prf_value = ? -- $listId
                     WHERE prf_name = \'dates_default_list_configuration\'
                       AND prf_org_id = ? -- $rowId';
            self::$db->queryPrepared($sql, array($listId, $rowId));
        }
    }

    /**
     * This method adds new categories for all organizations.
     * @throws Exception
     */
    public static function updateStep33AddGlobalCategories()
    {
        global $gCurrentOrganization;

        if ($gCurrentOrganization->countAllRecords() > 1) {
            $categoryAnnouncement = new Category(self::$db);
            $categoryAnnouncement->setValue('cat_type', 'ANN');
            $categoryAnnouncement->setValue('cat_name_intern', 'ANN_ALL_ORGANIZATIONS');
            $categoryAnnouncement->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryAnnouncement->save();

            $categoryEvents = new Category(self::$db);
            $categoryEvents->setValue('cat_type', 'DAT');
            $categoryEvents->setValue('cat_name_intern', 'DAT_ALL_ORGANIZATIONS');
            $categoryEvents->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryEvents->save();

            $categoryWeblinks = new Category(self::$db);
            $categoryWeblinks->setValue('cat_type', 'LNK');
            $categoryWeblinks->setValue('cat_name_intern', 'LNK_ALL_ORGANIZATIONS');
            $categoryWeblinks->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryWeblinks->save();
        }
    }

    /**
     * Update the existing category confirmation of participation and make it
     * organization depending.
     * @throws Exception
     */
    public static function updateStep33EventCategory()
    {
        global $g_organization, $gL10n;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $rowId = (int)$row['org_id'];

            if ($g_organization === $row['org_shortname']) {
                $sql = 'UPDATE ' . TBL_CATEGORIES . '
                           SET cat_name_intern = \'EVENTS\'
                             , cat_name   = ? -- $gL10n->get(\'SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION\')
                             , cat_org_id = ? -- $rowId
                         WHERE cat_org_id IS NULL
                           AND cat_type        = \'ROL\'
                           AND cat_name_intern = \'CONFIRMATION_OF_PARTICIPATION\' ';
                self::$db->queryPrepared($sql, array($gL10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION'), $rowId));
            } else {
                // create organization depending category for events
                $category = new Category(self::$db);
                $category->setValue('cat_org_id', $rowId);
                $category->setValue('cat_type', 'ROL');
                $category->setValue('cat_name', $gL10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION'));
                $category->setValue('cat_hidden', '1');
                $category->setValue('cat_system', '1');
                $category->save();

                // now set name intern explicit to EVENTS
                $category->setValue('cat_name_intern', 'EVENTS');
                $category->save();

                // all existing events of this organization must get the new category
                $sql = 'UPDATE ' . TBL_ROLES . '
                           SET rol_cat_id = ? -- $category->getValue(\'cat_id\')
                         WHERE rol_id IN (SELECT dat_rol_id
                                            FROM ' . TBL_DATES . '
                                      INNER JOIN ' . TBL_CATEGORIES . '
                                              ON cat_id = dat_cat_id
                                           WHERE dat_rol_id IS NOT NULL
                                             AND cat_org_id = ?) -- $rowId';
                self::$db->queryPrepared($sql, array((int)$category->getValue('cat_id'), $rowId));
            }
        }
    }

    /**
     * This method migrate the data of the table adm_date_role to the table adm_roles_rights_data.
     * @throws Exception
     */
    public static function updateStep33MigrateDatesRightsToFolderRights()
    {
        // migrate adm_folder_roles to adm_roles_rights
        $sql = 'SELECT ror_id
                  FROM ' . TBL_ROLES_RIGHTS . '
                 WHERE ror_name_intern = \'event_participation\'';
        $rolesRightsStatement = self::$db->queryPrepared($sql);
        $rolesRightId = (int)$rolesRightsStatement->fetchColumn();

        $sql = 'INSERT INTO ' . TBL_ROLES_RIGHTS_DATA . '
                       (rrd_ror_id, rrd_rol_id, rrd_object_id, rrd_usr_id_create, rrd_timestamp_create)
                SELECT ' . $rolesRightId . ', dtr_rol_id, dtr_dat_id, ?, ? -- $GLOBALS[\'gCurrentUserId\'], DATETIME_NOW
                  FROM ' . TABLE_PREFIX . '_date_role
                 WHERE dtr_rol_id IS NOT NULL';
        self::$db->queryPrepared($sql, array($GLOBALS['gCurrentUserId'], DATETIME_NOW));

        // if no roles were set than we must assign all default registration roles because now we need at least 1 role
        // so that someone could register to the event
        $sql = 'INSERT INTO ' . TBL_ROLES_RIGHTS_DATA . '
                       (rrd_ror_id, rrd_rol_id, rrd_object_id, rrd_usr_id_create, rrd_timestamp_create)
                SELECT ' . $rolesRightId . ', rol_id, dat_id, ?, ? -- $GLOBALS[\'gCurrentUserId\'], DATETIME_NOW
                  FROM ' . TABLE_PREFIX . '_dates
            INNER JOIN ' . TABLE_PREFIX . '_categories AS cdat
                    ON cdat.cat_id = dat_cat_id
            INNER JOIN ' . TABLE_PREFIX . '_date_role
                    ON dtr_dat_id = dat_id
            INNER JOIN ' . TABLE_PREFIX . '_categories AS rdat
                    ON rdat.cat_org_id = cdat.cat_org_id
            INNER JOIN ' . TABLE_PREFIX . '_roles
                    ON rol_cat_id = rdat.cat_id
                 WHERE dat_rol_id IS NOT NULL
                   AND dtr_rol_id IS NULL
                   AND rdat.cat_type = \'ROL\'
                   AND rol_default_registration = 1';
        self::$db->queryPrepared($sql, array($GLOBALS['gCurrentUserId'], DATETIME_NOW));
    }

    /**
     * This method update the security settings for menus to standard values
     * @throws Exception
     */
    public static function updateStep33MigrateToStandardMenu()
    {
        // add new module menu to components table
        $sql = 'INSERT INTO ' . TBL_COMPONENTS . '
                       (com_type, com_name, com_name_intern, com_version, com_beta)
                VALUES (\'MODULE\', \'SYS_MENU\', \'MENU\', ?, ?) -- ADMIDIO_VERSION, ADMIDIO_VERSION_BETA';
        self::$db->queryPrepared($sql, array(ADMIDIO_VERSION, ADMIDIO_VERSION_BETA));

        // Menu entries for the standard installation
        $sql = 'INSERT INTO ' . TBL_MENU . '
                       (men_com_id, men_men_id_parent, men_node, men_order, men_standard, men_name_intern, men_url, men_icon, men_name, men_description)
                VALUES (NULL, NULL, 1, 1, 1, \'modules\', NULL, \'\', \'SYS_MODULES\', \'\')
                     , (NULL, NULL, 1, 2, 1, \'administration\', NULL, \'\', \'SYS_ADMINISTRATION\', \'\')
                     , (NULL, NULL, 1, 3, 1, \'plugins\', NULL, \'\', \'SYS_PLUGINS\', \'\')
                     , (NULL, 1, 0, 1, 1, \'overview\', \'' . FOLDER_MODULES . '/overview.php\', \'home.png\', \'SYS_OVERVIEW\', \'\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'DOCUMENTS-FILES\'), 1, 0, 3, 1, \'documents-files\', \'' . FOLDER_MODULES . '/documents-files/documents_files.php\', \'fa-file-download\', \'SYS_DOCUMENTS_FILES\', \'SYS_DOCUMENTS_FILES_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'GROUPS-ROLES\'), 1, 0, 7, 1, \'groups-roles\', \'' . FOLDER_MODULES . '/groups-roles/groups_roles.php\', \'fa-user-tie\', \'SYS_GROUPS_ROLES\', \'SYS_GROUPS_ROLES_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'ANNOUNCEMENTS\'), 1, 0, 2, 1, \'announcements\', \'' . FOLDER_MODULES . '/announcements.php\', \'announcements.png\', \'SYS_ANNOUNCEMENTS\', \'SYS_ANNOUNCEMENTS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'PHOTOS\'), 1, 0, 5, 1, \'photo\', \'' . FOLDER_MODULES . '/photos/photos.php\', \'photo.png\', \'SYS_PHOTOS\', \'SYS_PHOTOS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'GUESTBOOK\'), 1, 0, 6, 1, \'guestbook\', \'' . FOLDER_MODULES . '/guestbook/guestbook.php\', \'guestbook.png\', \'GBO_GUESTBOOK\', \'GBO_GUESTBOOK_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'DATES\'), 1, 0, 8, 1, \'dates\', \'' . FOLDER_MODULES . '/events/events.php\', \'dates.png\', \'SYS_EVENTS\', \'SYS_EVENTS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'LINKS\'), 1, 0, 9, 1, \'weblinks\', \'' . FOLDER_MODULES . '/links/links.php\', \'weblinks.png\', \'SYS_WEBLINKS\', \'SYS_WEBLINKS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'BACKUP\'), 2, 0, 4, 1, \'dbback\', \'' . FOLDER_MODULES . '/backup/backup.php\', \'backup.png\', \'SYS_DATABASE_BACKUP\', \'SYS_DATABASE_BACKUP_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'PREFERENCES\'), 2, 0, 6, 1, \'orgprop\', \'' . FOLDER_MODULES . '/preferences.php\', \'options.png\', \'SYS_SETTINGS\', \'ORG_ORGANIZATION_PROPERTIES_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'MESSAGES\'), 1, 0, 4, 1, \'mail\', \'' . FOLDER_MODULES . '/messages/messages_write.php\', \'email.png\', \'SYS_EMAIL\', \'SYS_EMAIL_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'REGISTRATION\'), 2, 0, 1, 1, \'newreg\', \'' . FOLDER_MODULES . '/registration.php\', \'new_registrations.png\', \'SYS_NEW_REGISTRATIONS\', \'SYS_MANAGE_NEW_REGISTRATIONS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'MEMBERS\'), 2, 0, 2, 1, \'usrmgt\', \'' . FOLDER_MODULES . '/members/members.php\', \'user_administration.png\', \'SYS_USER_MANAGEMENT\', \'SYS_MEMBERS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'MENU\'), 2, 0, 5, 1, \'menu\', \'' . FOLDER_MODULES . '/menu/menu.php\', \'application_view_tile.png\', \'SYS_MENU\', \'\')';
        self::$db->query($sql);
    }

    /**
     * This method set the approval states for all members of an event in the past to confirmed.
     * @throws Exception
     */
    public static function updateStep33SetParticipantsApprovalStates()
    {
        $sql = 'UPDATE ' . TBL_MEMBERS . '
                           SET mem_approved = 2
                         WHERE mem_approved IS NULL
                           AND mem_begin < ? -- DATE_NOW
                           AND mem_rol_id IN (SELECT rol_id
                                                FROM ' . TBL_ROLES . '
                                          INNER JOIN ' . TBL_CATEGORIES . '
                                                  ON cat_id = rol_cat_id
                                               WHERE cat_name_intern = \'EVENTS\'
                                                 AND rol_id IN (SELECT dat_rol_id
                                                                  FROM ' . TBL_DATES . '
                                                                 WHERE dat_rol_id = rol_id))';

        self::$db->queryPrepared($sql, array(DATE_NOW));
    }

    /**
     * This method add all roles to the role right category_view if the role had set the flag cat_hidden = 1
     * @throws Exception
     */
    public static function updateStep33VisibleCategories()
    {
        $sql = 'SELECT cat_id, cat_org_id
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type IN (\'ANN\', \'DAT\', \'LNK\', \'USF\')
                   AND cat_org_id IS NOT NULL
                   AND cat_hidden = 1 ';
        $categoryStatement = self::$db->queryPrepared($sql);

        while ($row = $categoryStatement->fetch()) {
            $roles = array();
            $sql = 'SELECT rol_id
                      FROM ' . TBL_ROLES . '
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                     WHERE rol_valid  = true
                       AND cat_name_intern <> \'EVENTS\'
                       AND cat_org_id = ? -- $row[\'cat_org_id\']';
            $rolesStatement = self::$db->queryPrepared($sql, array((int)$row['cat_org_id']));

            while ($rowRole = $rolesStatement->fetch()) {
                $roles[] = (int)$rowRole['rol_id'];
            }

            // save roles to role right
            $rightCategoryView = new RolesRights(self::$db, 'category_view', (int)$row['cat_id']);
            $rightCategoryView->saveRoles($roles);
        }
    }

    /**
     * This method renames the download folders of the different organizations to the new secure filename pattern
     * @throws Exception
     */
    public static function updateStep33DownloadOrgFolderName()
    {
        global $gLogger;

        $sql = 'SELECT org_shortname FROM ' . TBL_ORGANIZATIONS;
        $pdoStatement = self::$db->queryPrepared($sql);

        while ($orgShortname = $pdoStatement->fetchColumn()) {
            $path = ADMIDIO_PATH . FOLDER_DATA . '/download_';
            $orgNameOld = str_replace(array(' ', '.', ',', '\'', '"', '´', '`'), '_', $orgShortname);
            $orgNameNew = FileSystemUtils::getSanitizedPathEntry($orgShortname);

            if ($orgNameOld !== $orgNameNew) {
                try {
                    FileSystemUtils::moveDirectory($path . strtolower($orgNameOld), $path . strtolower($orgNameNew));
                } catch (RuntimeException $exception) {
                    $gLogger->error('Could not move directory!', array('from' => $path . strtolower($orgNameOld), 'to' => $path . strtolower($orgNameNew)));
                    // TODO
                }
            }
        }
    }

    /**
     * This method removes expired messengers like GooglePlus, AOL Messenger and Yahoo. Messenger from the system.
     * @throws Exception
     */
    public static function updateStep33RemoveExpiredMessengers()
    {
        $sql = 'SELECT usf_id
                  FROM ' . TBL_USER_FIELDS . '
                 WHERE usf_name_intern IN (\'AOL_INSTANT_MESSENGER\', \'GOOGLE_PLUS\', \'YAHOO_MESSENGER\')';
        $messengerStatement = self::$db->queryPrepared($sql);

        while ($row = $messengerStatement->fetch()) {
            // save roles to role right
            $rightCategoryView = new ProfileField(self::$db, (int)$row['usf_id']);
            $rightCategoryView->delete();
        }
    }

    /**
     * This method add new categories for announcements to the database.
     * @throws Exception
     */
    public static function updateStep32AddAnnouncementsCategories()
    {
        global $gL10n;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM ' . TBL_USERS . '
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = self::$db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int)$systemUserStatement->fetchColumn();

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $rowId = (int)$row['org_id'];

            $sql = 'INSERT INTO ' . TBL_CATEGORIES . '
                           (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                    VALUES (?, \'ANN\', \'COMMON\',    \'SYS_COMMON\',    0, 1, 0, 1, ?, ?) -- $rowId, $systemUserId, DATETIME_NOW
                         , (?, \'ANN\', \'IMPORTANT\', \'SYS_IMPORTANT\', 0, 0, 0, 2, ?, ?) -- $rowId, $systemUserId, DATETIME_NOW';
            $params = array(
                $rowId, $systemUserId, DATETIME_NOW,
                $rowId, $systemUserId, DATETIME_NOW
            );
            self::$db->queryPrepared($sql, $params);

            $sql = 'UPDATE ' . TBL_ANNOUNCEMENTS . '
                       SET ann_cat_id = (SELECT cat_id
                                           FROM ' . TBL_CATEGORIES . '
                                          WHERE cat_type = \'ANN\'
                                            AND cat_name_intern = \'COMMON\'
                                            AND cat_org_id = ? ) -- $rowId
                     WHERE ann_org_id = ? -- $rowId';
            self::$db->queryPrepared($sql, array($rowId, $rowId));
        }
    }

    /**
     * This method installs the default user relation types
     * @throws Exception
     */
    public static function updateStep32InstallDefaultUserRelationTypes()
    {
        $sql = 'INSERT INTO ' . TBL_USER_RELATION_TYPES . '
                       (urt_id, urt_name, urt_name_male, urt_name_female, urt_id_inverse, urt_usr_id_create, urt_timestamp_create)
                VALUES (1, \'SYS_PARENT\',      \'SYS_FATHER\',           \'SYS_MOTHER\',             2, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (2, \'SYS_CHILD\',       \'SYS_SON\',              \'SYS_DAUGHTER\',           1, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (3, \'SYS_SIBLING\',     \'SYS_BROTHER\',          \'SYS_SISTER\',             3, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (4, \'SYS_SPOUSE\',      \'SYS_HUSBAND\',          \'SYS_WIFE\',               4, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (5, \'SYS_COHABITANT\',  \'SYS_COHABITANT_MALE\',  \'SYS_COHABITANT_FEMALE\',  5, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (6, \'SYS_COMPANION\',   \'SYS_BOYFRIEND\',        \'SYS_GIRLFRIEND\',         6, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (7, \'SYS_SUPERIOR\',    \'SYS_SUPERIOR_MALE\',    \'SYS_SUPERIOR_FEMALE\',    8, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (8, \'SYS_SUBORDINATE\', \'SYS_SUBORDINATE_MALE\', \'SYS_SUBORDINATE_FEMALE\', 7, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')';
        self::$db->query($sql);
    }

    /**
     * This method migrate the data of the table adm_folder_roles to the
     * new table adm_roles_rights_data.
     * @throws Exception
     */
    public static function updateStep32MigrateToFolderRights()
    {
        global $g_organization;

        // migrate adm_folder_roles to adm_roles_rights
        $sql = 'SELECT ror_id
                  FROM ' . TBL_ROLES_RIGHTS . '
                 WHERE ror_name_intern = \'folder_view\'';
        $rolesRightsStatement = self::$db->queryPrepared($sql);
        $rolesRightId = (int)$rolesRightsStatement->fetchColumn();

        $sql = 'INSERT INTO ' . TBL_ROLES_RIGHTS_DATA . '
                       (rrd_ror_id, rrd_rol_id, rrd_object_id, rrd_usr_id_create, rrd_timestamp_create)
                SELECT ' . $rolesRightId . ', flr_rol_id, flr_fol_id, ?, ? -- $gCurrentUserId, DATETIME_NOW
                  FROM ' . TABLE_PREFIX . '_folder_roles ';
        self::$db->queryPrepared($sql, array($GLOBALS['gCurrentUserId'], DATETIME_NOW));

        // add new right folder_update to adm_roles_rights
        $sql = 'SELECT fol_id
                  FROM ' . TBL_FOLDERS . '
                 WHERE fol_type = \'DOWNLOAD\'
                   AND fol_name = \'download\' ';
        $rolesRightsStatement = self::$db->queryPrepared($sql);
        $folderId = (int)$rolesRightsStatement->fetchColumn();

        $sql = 'SELECT rol_id
                  FROM ' . TBL_ROLES . '
             LEFT JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
             LEFT JOIN ' . TBL_ORGANIZATIONS . '
                    ON org_id = cat_org_id
                 WHERE rol_download  = 1
                   AND org_shortname = ? -- $g_organization';
        $rolesDownloadStatement = self::$db->queryPrepared($sql, array($g_organization));

        $rolesArray = array();
        while ($roleId = $rolesDownloadStatement->fetchColumn()) {
            $rolesArray[] = (int)$roleId;
        }

        // get recordset of current folder from database
        $folder = new Folder(self::$db, $folderId);
        $folder->addRolesOnFolder('folder_upload', $rolesArray);
    }

    /**
     * Create a unique folder name for the root folder of the download module that contains
     * the shortname of the current organization
     * @throws Exception
     */
    public static function updateStep32NewDownloadRootFolderName()
    {
        global $gLogger, $g_organization;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $rowId = (int)$row['org_id'];

            $organization = new Organization(self::$db, $rowId);

            $sql = 'SELECT fol_id, fol_name
                      FROM ' . TBL_FOLDERS . '
                     WHERE fol_fol_id_parent IS NULL
                       AND fol_org_id = ? -- $rowId';
            $folderStatement = self::$db->queryPrepared($sql, array($rowId));

            if ($rowFolder = $folderStatement->fetch()) {
                $folder = new Folder(self::$db, $rowFolder['fol_id']);
                $folderOldName = $folder->getFullFolderPath();
                $folder->setValue('fol_name', Folder::getRootFolderName('documents', $organization->getValue('org_shortname')));
                $folder->save();

                $sql = 'UPDATE ' . TBL_FOLDERS . '
                           SET fol_path = REPLACE(fol_path, \'/' . $rowFolder['fol_name'] . '\', \'/' . Folder::getRootFolderName('documents', $organization->getValue('org_shortname')) . '\')
                         WHERE fol_org_id = ' . $rowId;
                self::$db->query($sql); // TODO add more params

                if ($row['org_shortname'] === $g_organization && is_dir($folderOldName)) {
                    try {
                        FileSystemUtils::moveDirectory($folderOldName, $folder->getFullFolderPath());
                    } catch (RuntimeException $exception) {
                        $gLogger->error('Could not move directory!', array('from' => $folderOldName, 'to' => $folder->getFullFolderPath()));
                        // TODO
                    }
                }
            } else {
                $sql = 'INSERT INTO ' . TBL_FOLDERS . '
                               (fol_org_id, fol_type, fol_name, fol_path, fol_locked, fol_public, fol_timestamp)
                        VALUES (?, \'DOWNLOAD\', ?, ?, 0, 1, ?) -- $rowId, Folder::getRootFolderName(), FOLDER_DATA, DATETIME_NOW';
                $params = array(
                    $rowId,
                    Folder::getRootFolderName('documents', $organization->getValue('org_shortname')),
                    FOLDER_DATA,
                    DATETIME_NOW
                );
                self::$db->queryPrepared($sql, $params);
            }
        }
    }

    /**
     * This method renames the role 'webmaster' to 'administrator'.
     * @throws Exception
     */
    public static function updateStep32RenameWebmasterToAdministrator()
    {
        global $gL10n;

        $sql = 'UPDATE ' . TBL_ROLES . '
                   SET rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')_1
                 WHERE rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')';
        self::$db->queryPrepared($sql, array($gL10n->get('SYS_ADMINISTRATOR') . '_1', $gL10n->get('SYS_ADMINISTRATOR')));

        $sql = 'UPDATE ' . TBL_ROLES . '
                   SET rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')
                 WHERE rol_name = ? -- $gL10n->get(\'SYS_WEBMASTER\')';
        self::$db->queryPrepared($sql, array($gL10n->get('SYS_ADMINISTRATOR'), $gL10n->get('SYS_WEBMASTER')));
    }

    /**
     * Check all folders in adm_my_files and set the rights to default folder mode-rights
     * @param string $folder
     * @return bool
     */
    public static function updateStep32RewriteFolderRights(string $folder = ''): bool
    {
        if (!FileSystemUtils::isUnixWithPosix()) {
            return false;
        }

        if ($folder === '') {
            $folder = ADMIDIO_PATH . FOLDER_DATA;
        }

        try {
            FileSystemUtils::chmodDirectory($folder, FileSystemUtils::DEFAULT_MODE_DIRECTORY, true);

            return true;
        } catch (RuntimeException $exception) {
            return false;
        }
    }

    /**
     * This method set the default configuration for all organizations
     * @throws Exception
     */
    public static function updateStep31SetDefaultConfiguration()
    {
        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
        $organizationsStatement = self::$db->queryPrepared($sql);
        $organizationsArray = $organizationsStatement->fetchAll();

        foreach ($organizationsArray as $organization) {
            $orgId = (int)$organization['org_id'];

            $sql = 'SELECT lst_id
                      FROM ' . TBL_LISTS . '
                     WHERE lst_default = 1
                       AND lst_org_id  = ? -- $orgId';
            $defaultListStatement = self::$db->queryPrepared($sql, array($orgId));
            $listId = (int)$defaultListStatement->fetchColumn();

            // save default list to preferences
            $sql = 'UPDATE ' . TBL_PREFERENCES . '
                       SET prf_value  = ? -- $listId
                     WHERE prf_name   = \'lists_default_configuation\'
                       AND prf_org_id = ? -- $orgId';
            self::$db->queryPrepared($sql, array($listId, $orgId));
        }
    }

    /**
     * This method deletes all roles that belongs to still deleted events.
     * @throws Exception
     */
    public static function updateStep30DeleteDateRoles()
    {
        $sql = 'SELECT rol_id
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                 WHERE cat_name_intern = \'CONFIRMATION_OF_PARTICIPATION\'
                   AND NOT exists (SELECT 1
                                     FROM ' . TBL_DATES . '
                                    WHERE dat_rol_id = rol_id)';
        $rolesStatement = self::$db->queryPrepared($sql);

        while ($roleId = $rolesStatement->fetchColumn()) {
            $role = new Entity(self::$db, TBL_ROLES, 'rol', (int)$roleId);
            $role->delete(); // TODO Exception handling
        }
    }
}
