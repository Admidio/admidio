<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class ComponentUpdate
 * @brief Manage the update of a component from the actual version to the target version
 *
 * The class is an extension to the component class and will handle the update of a
 * component. It will read the database version from the component and set this as
 * source version. Then you should set the target version. The class will then search
 * search for specific update xml files in special directories. For the system this should be
 * @b adm_program/installation/db_scripts and for plugins there should be an install folder within the
 * plugin directory. The xml files should have the prefix update and than the main und subversion
 * within their filename e.g. @b update_3_0.xml .
 * @par Examples
 * @code // update the system module to the actual filesystem version
 * $componentUpdateHandle = new ComponentUpdate($gDb);
 * $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
 * $componentUpdateHandle->setTargetVersion(ADMIDIO_VERSION);
 * $componentUpdateHandle->update(); @endcode
 */
class ComponentUpdate extends Component
{
    const UPDATE_STEP_STOP = 'stop';

    /**
     * @var bool Flag that will store if the update process of this version was successfully finished
     */
    private $updateFinished;
    /**
     * @var \SimpleXMLElement The SimpleXML object with all the update steps
     */
    private $xmlObject;
    /**
     * @var array<int,int> This is the version the component has actually before update. Each array element contains one part of the version.
     */
    private $currentVersionArray;
    /**
     * @var array<int,int> This is the version that is stored in the files of the component. Each array element contains one part of the version.
     */
    private $targetVersionArray;

    /**
     * Constructor that will create an object for component updating.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     */
    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    /**
     * Gets the version parts of a version string
     * @param string $versionString A version string
     * @return array<int,int> Returns an array with the version parts
     */
    private static function getVersionArrayFromVersion($versionString)
    {
        return array_map('intval', explode('.', $versionString));
    }

    /**
     * Will open a XML file of a specific version that contains all the update steps that
     * must be passed to successfully update Admidio to this version
     * @param int $mainVersion Contains a string with the main version number e.g. 2 or 3 from 2.x or 3.x.
     * @param int $subVersion  Contains a string with the main version number e.g. 1 or 2 from x.1 or x.2.
     * @return bool
     */
    private function createXmlObject($mainVersion, $subVersion)
    {
        // update of Admidio core has another path for the xml files as plugins
        if($this->getValue('com_type') === 'SYSTEM')
        {
            $updateFile = ADMIDIO_PATH.'/adm_program/installation/db_scripts/update_'.$mainVersion.'_'.$subVersion.'.xml';

            if(is_file($updateFile))
            {
                $this->xmlObject = new \SimpleXMLElement($updateFile, null, true);
                return true;
            }
        }
        return false;
    }

    /**
     * Will execute the specific update step that is set through the parameter $xmlNode.
     * If the step was successfully done the id will be stored in the component recordset
     * so if the whole update crashs later we know that this step was successfully executed.
     * When the node has an attribute @b database than this sql statement will only executed
     * if the value of the attribute is equal to your current @b $gDbType . If the node has
     * an attribute @b error and this is set to @b ignore than an sql error will not stop
     * the update script.
     * @param \SimpleXMLElement $xmlNode A SimpleXML node of the current update step.
     */
    private function executeStep(\SimpleXMLElement $xmlNode)
    {
        global $g_tbl_praefix, $gDbType;

        // for backwards compatibility "postgresql"
        $dbType = $gDbType;
        if ($gDbType === 'postgresql')
        {
            $dbType = Database::PDO_ENGINE_PGSQL;
        }

        $updateStepContent = trim((string) $xmlNode);

        if ($updateStepContent === '')
        {
            return;
        }

        $executeSql = true;
        $showError  = true;

        // if the sql statement is only for a special database and you do
        // not have this database then don't execute this statement
        if (isset($xmlNode['database']) && (string) $xmlNode['database'] !== $dbType)
        {
            $executeSql = false;
        }

        // if the attribute error was set to "ignore" then don't show errors that occures on sql execution
        if (isset($xmlNode['error']) && (string) $xmlNode['error'] === 'ignore')
        {
            $showError = false;
        }

        // if a method of this class was set in the update step
        // then call this function and don't execute a SQL statement
        if (strpos($updateStepContent, 'ComponentUpdate') !== false)
        {
            $executeSql = false;

            // get the method name
            $function = substr($updateStepContent, strpos($updateStepContent, '::') + 2);
            // now call the method
            $this->{$function}();
        }

        if ($executeSql)
        {
            // replace prefix with installation specific table prefix
            $sql = str_replace('%PREFIX%', $g_tbl_praefix, $updateStepContent);

            $this->db->query($sql, $showError); // TODO add more params
        }

        // save the successful executed update step in database
        $this->setValue('com_update_step', (int) $xmlNode['id']);
        $this->save();
    }

    /**
     * Goes step by step through the update xml file of the current database version and search for the maximum step.
     * If the last step is found than the id of this step will be returned.
     * @return int Return the number of the last update step that was found in xml file of the current version.
     */
    public function getMaxUpdateStep()
    {
        $maxUpdateStep = 0;
        $this->currentVersionArray = self::getVersionArrayFromVersion($this->getValue('com_version'));

        // open xml file for this version
        if($this->createXmlObject($this->currentVersionArray[0], $this->currentVersionArray[1]))
        {
            // go step by step through the SQL statements until the last one is found
            foreach($this->xmlObject->children() as $updateStep)
            {
                if((string) $updateStep !== self::UPDATE_STEP_STOP)
                {
                    $maxUpdateStep = $updateStep['id'];
                }
            }
        }
        return (int) $maxUpdateStep;
    }

    /**
     * Set the target version for the component after update.
     * This information should be read from the files of the component.
     * @param string $version Target version of the component after update
     */
    public function setTargetVersion($version)
    {
        $this->targetVersionArray = self::getVersionArrayFromVersion($version);
    }

    /**
     * Do a loop through all versions start with the current version and end with the target version.
     * Within every subversion the method will search for an update xml file and execute all steps
     * in this file until the end of file is reached. If an error occurred then the update will be stopped.
     */
    public function update()
    {
        global $gLogger;

        $this->updateFinished = false;
        $this->currentVersionArray = self::getVersionArrayFromVersion($this->getValue('com_version'));
        $initialSubVersion = $this->currentVersionArray[1];

        for($mainVersion = $this->currentVersionArray[0]; $mainVersion <= $this->targetVersionArray[0]; ++$mainVersion)
        {
            // Set max subversion for iteration. If we are in the loop of the target main version
            // then set target subversion to the max version
            if($mainVersion === $this->targetVersionArray[0])
            {
                $maxSubVersion = $this->targetVersionArray[1];
            }
            else
            {
                $maxSubVersion = 20;
            }

            for($subVersion = $initialSubVersion; $subVersion <= $maxSubVersion; ++$subVersion)
            {
                // if version is not equal to current version then start update step with 0
                if($mainVersion !== $this->currentVersionArray[0] || $subVersion !== $this->currentVersionArray[1])
                {
                    $this->setValue('com_update_step', 0);
                    $this->save();
                }

                // output of the version number for better debugging
                $gLogger->info('Update to version '.$mainVersion.'.'.$subVersion);

                // open xml file for this version
                if($this->createXmlObject($mainVersion, $subVersion))
                {
                    // go step by step through the SQL statements and execute them
                    foreach($this->xmlObject->children() as $updateStep)
                    {
                        if($updateStep['id'] > $this->getValue('com_update_step'))
                        {
                            $this->executeStep($updateStep);
                        }
                        elseif((string) $updateStep === self::UPDATE_STEP_STOP)
                        {
                            $this->updateFinished = true;
                        }
                    }
                }

                // check if an php update file exists and then execute the script
                $phpUpdateFile = ADMIDIO_PATH.'/adm_program/installation/db_scripts/upd_'.$mainVersion.'_'.$subVersion.'_0_conv.php';

                if(is_file($phpUpdateFile))
                {
                    require_once($phpUpdateFile);
                }

                // save current version to system component
                $this->setValue('com_version', ADMIDIO_VERSION);
                $this->setValue('com_beta', ADMIDIO_VERSION_BETA);
                $this->save();

                // save current version to all modules
                $sql = 'UPDATE '.TBL_COMPONENTS.'
                           SET com_version = ? -- ADMIDIO_VERSION
                             , com_beta    = ? -- ADMIDIO_VERSION_BETA
                         WHERE com_type = \'MODULE\'';
                $this->db->queryPrepared($sql, array(ADMIDIO_VERSION, ADMIDIO_VERSION_BETA));
            }

            // reset subversion because we want to start update for next main version with subversion 0
            $initialSubVersion = 0;
        }
    }

    /**
     * This method add new categories for announcements to the database.
     */
    public function updateStepAddAnnouncementsCategories()
    {
        global $gL10n;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM '.TBL_USERS.'
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = $this->db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int) $systemUserStatement->fetchColumn();

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = $this->db->queryPrepared($sql);

        while($row = $organizationStatement->fetch())
        {
            $rowId = (int) $row['org_id'];

            $sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                    VALUES (?, \'ANN\', \'COMMON\',    \'SYS_COMMON\',    0, 1, 0, 1, ?, ?) -- $rowId, $systemUserId, DATETIME_NOW
                         , (?, \'ANN\', \'IMPORTANT\', \'SYS_IMPORTANT\', 0, 0, 0, 2, ?, ?) -- $rowId, $systemUserId, DATETIME_NOW';
            $params = array(
                $rowId, $systemUserId, DATETIME_NOW,
                $rowId, $systemUserId, DATETIME_NOW
            );
            $this->db->queryPrepared($sql, $params);

            $sql = 'UPDATE '.TBL_ANNOUNCEMENTS.'
                       SET ann_cat_id = (SELECT cat_id
                                           FROM '.TBL_CATEGORIES.'
                                          WHERE cat_type = \'ANN\'
                                            AND cat_name_intern = \'COMMON\'
                                            AND cat_org_id = ? ) -- $rowId
                     WHERE ann_org_id = ? -- $rowId';
            $this->db->queryPrepared($sql, array($rowId, $rowId));
        }
    }

    /**
     * This method adds a new global list configuration for Participients of Events.
     */
    public function updateStepAddDefaultParticipantList()
    {
        global $gL10n;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM '.TBL_USERS.'
                 WHERE usr_login_name = \''.$gL10n->get('SYS_SYSTEM').'\'';
        $systemUserStatement = $this->db->queryPrepared($sql);
        $systemUserId = (int) $systemUserStatement->fetchColumn();

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = $this->db->queryPrepared($sql);

        while($row = $organizationStatement->fetch())
        {
            $rowId = (int) $row['org_id'];

            // Add new list configuration
            $sql = 'INSERT INTO '.TBL_LISTS.' (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global)
                    VALUES (?, ?, ?, ?, 1)'; // $rowId, $systemUserId, $gL10n->get('SYS_PARTICIPANTS'), DATETIME_NOW
            $params = array(
                $rowId,
                $systemUserId,
                $gL10n->get('SYS_PARTICIPANTS'),
                DATETIME_NOW
            );
            $this->db->queryPrepared($sql, $params);

            // Add list columns
            $sql = 'SELECT lst_id
                      FROM '.TBL_LISTS.'
                     WHERE lst_name = ? -- $gL10n->get(\'SYS_PARTICIPANTS\')
                       AND lst_org_id = ? -- $rowId';
            $listStatement = $this->db->queryPrepared($sql, array($gL10n->get('SYS_PARTICIPANTS'), $rowId));
            $listId = (int) $listStatement->fetchColumn();

            $sql = 'INSERT INTO '.TBL_LIST_COLUMNS.' (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort, lsc_filter)
                    VALUES (?, 1, 1,    NULL,                 \'ASC\', NULL) -- $listId
                         , (?, 2, 2,    NULL,                 NULL,    NULL) -- $listId
                         , (?, 3, NULL, \'mem_approved\',     NULL,    NULL) -- $listId
                         , (?, 4, NULL, \'mem_comment\',      NULL,    NULL) -- $listId
                         , (?, 5, NULL, \'mem_count_guests\', NULL,    NULL) -- $listId';
            $this->db->queryPrepared($sql, array($listId, $listId, $listId, $listId, $listId));

            // Set as default configuration list
            $sql = 'UPDATE ' . TBL_PREFERENCES . '
                       SET prf_value = ? -- $listId
                     WHERE prf_name = \'dates_default_list_configuration\'
                       AND prf_org_id = ? -- $rowId';
            $this->db->queryPrepared($sql, array($listId, $rowId));
        }
    }

    /**
     * This method adds new categories for all organizations.
     */
    public function updateStepAddGlobalCategories()
    {
        global $gCurrentOrganization;

        if($gCurrentOrganization->countAllRecords() > 1)
        {
            $categoryAnnouncement = new TableCategory($this->db);
            $categoryAnnouncement->setValue('cat_type', 'ANN');
            $categoryAnnouncement->setValue('cat_name_intern', 'ANN_ALL_ORGANIZATIONS');
            $categoryAnnouncement->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryAnnouncement->save();

            $categoryEvents = new TableCategory($this->db);
            $categoryEvents->setValue('cat_type', 'DAT');
            $categoryEvents->setValue('cat_name_intern', 'DAT_ALL_ORGANIZATIONS');
            $categoryEvents->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryEvents->save();

            $categoryWeblinks = new TableCategory($this->db);
            $categoryWeblinks->setValue('cat_type', 'LNK');
            $categoryWeblinks->setValue('cat_name_intern', 'LNK_ALL_ORGANIZATIONS');
            $categoryWeblinks->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryWeblinks->save();
        }
    }

    /**
     * This method deletes all roles that belongs to still deleted dates.
     */
    public function updateStepDeleteDateRoles()
    {
        $sql = 'SELECT rol_id
                  FROM '.TBL_ROLES.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE cat_name_intern = \'CONFIRMATION_OF_PARTICIPATION\'
                   AND NOT exists (SELECT 1
                                     FROM '.TBL_DATES.'
                                    WHERE dat_rol_id = rol_id)';
        $rolesStatement = $this->db->queryPrepared($sql);

        while($roleId = $rolesStatement->fetchColumn())
        {
            $role = new TableRoles($this->db, (int) $roleId);
            $role->delete(); // TODO Exception handling
        }
    }

    /**
     * Update the existing category confirmation of participation and make it
     * organization depending.
     */
    public function updateStepEventCategory()
    {
        global $g_organization, $gL10n;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = $this->db->queryPrepared($sql);

        while($row = $organizationStatement->fetch())
        {
            $rowId = (int) $row['org_id'];

            if($g_organization === $row['org_shortname'])
            {
                $sql = 'UPDATE '.TBL_CATEGORIES.'
                           SET cat_name_intern = \'EVENTS\'
                             , cat_name   = ? -- $gL10n->get(\'SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION\')
                             , cat_org_id = ? -- $rowId
                         WHERE cat_org_id IS NULL
                           AND cat_type        = \'ROL\'
                           AND cat_name_intern = \'CONFIRMATION_OF_PARTICIPATION\' ';
                $this->db->queryPrepared($sql, array($gL10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION'), $rowId));
            }
            else
            {
                // create organization depending category for events
                $category = new TableCategory($this->db);
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
                $sql = 'UPDATE '.TBL_ROLES.'
                           SET rol_cat_id = ? -- $category->getValue(\'cat_id\')
                         WHERE rol_id IN (SELECT dat_rol_id
                                            FROM '.TBL_DATES.'
                                      INNER JOIN '.TBL_CATEGORIES.'
                                              ON cat_id = dat_cat_id
                                           WHERE dat_rol_id IS NOT NULL
                                             AND cat_org_id = ?) -- $rowId';
                $this->db->queryPrepared($sql, array((int) $category->getValue('cat_id'), $rowId));
            }
        }
    }

    /**
     * This method migrate the data of the table adm_date_role to the table adm_roles_rights_data.
     */
    public function updateStepMigrateDatesRightsToFolderRights()
    {
        global $g_tbl_praefix, $g_organization, $gCurrentUser;

        // migrate adm_folder_roles to adm_roles_rights
        $sql = 'SELECT ror_id
                  FROM '.TBL_ROLES_RIGHTS.'
                 WHERE ror_name_intern = \'event_participation\'';
        $rolesRightsStatement = $this->db->queryPrepared($sql);
        $rolesRightId = (int) $rolesRightsStatement->fetchColumn();

        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id, rrd_usr_id_create, rrd_timestamp_create)
                SELECT '.$rolesRightId.', dtr_rol_id, dtr_dat_id, ?, ? -- $gCurrentUser->getValue(\'usr_id\'), DATETIME_NOW
                  FROM '.$g_tbl_praefix.'_date_role
                 WHERE dtr_rol_id IS NOT NULL';
        $this->db->queryPrepared($sql, array((int) $gCurrentUser->getValue('usr_id'), DATETIME_NOW));
    }

    /**
     * This method migrate the data of the table adm_folder_roles to the
     * new table adm_roles_rights_data.
     */
    public function updateStepMigrateToFolderRights()
    {
        global $g_tbl_praefix, $g_organization, $gCurrentUser;

        // migrate adm_folder_roles to adm_roles_rights
        $sql = 'SELECT ror_id
                  FROM '.TBL_ROLES_RIGHTS.'
                 WHERE ror_name_intern = \'folder_view\'';
        $rolesRightsStatement = $this->db->queryPrepared($sql);
        $rolesRightId = (int) $rolesRightsStatement->fetchColumn();

        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id, rrd_usr_id_create, rrd_timestamp_create)
                SELECT '.$rolesRightId.', flr_rol_id, flr_fol_id, ?, ? -- $gCurrentUser->getValue(\'usr_id\'), DATETIME_NOW
                  FROM '.$g_tbl_praefix.'_folder_roles ';
        $this->db->queryPrepared($sql, array((int) $gCurrentUser->getValue('usr_id'), DATETIME_NOW));

        // add new right folder_update to adm_roles_rights
        $sql = 'SELECT fol_id
                  FROM '.TBL_FOLDERS.'
                 WHERE fol_type = \'DOWNLOAD\'
                   AND fol_name = \'download\' ';
        $rolesRightsStatement = $this->db->queryPrepared($sql);
        $folderId = (int) $rolesRightsStatement->fetchColumn();

        $sql = 'SELECT rol_id
                  FROM '.TBL_ROLES.'
             LEFT JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
             LEFT JOIN '.TBL_ORGANIZATIONS.'
                    ON org_id = cat_org_id
                 WHERE rol_download  = 1
                   AND org_shortname = ? -- $g_organization';
        $rolesDownloadStatement = $this->db->queryPrepared($sql, array($g_organization));

        $rolesArray = array();
        while($roleId = $rolesDownloadStatement->fetchColumn())
        {
            $rolesArray[] = (int) $roleId;
        }

        try
        {
            // get recordset of current folder from database
            $folder = new TableFolder($this->db, $folderId);
            $folder->addRolesOnFolder('folder_upload', $rolesArray);
        }
        catch(AdmException $e)
        {
            $e->showText();
            // => EXIT
        }
    }

    /**
     * Create a unique folder name for the root folder of the download module that contains
     * the shortname of the current organization
     */
    public function updateStepNewDownloadRootFolderName()
    {
        global $gCurrentOrganization, $g_organization;

        $tempOrganization = $gCurrentOrganization;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = $this->db->queryPrepared($sql);

        while($row = $organizationStatement->fetch())
        {
            $rowId = (int) $row['org_id'];

            $gCurrentOrganization->readDataById($rowId);

            $sql = 'SELECT fol_id, fol_name
                      FROM '.TBL_FOLDERS.'
                     WHERE fol_fol_id_parent IS NULL
                       AND fol_org_id = ? -- $rowId';
            $folderStatement = $this->db->queryPrepared($sql, array($rowId));

            if($rowFolder = $folderStatement->fetch())
            {
                $folder = new TableFolder($this->db, $rowFolder['fol_id']);
                $folderOldName = $folder->getFullFolderPath();
                $folder->setValue('fol_name', TableFolder::getRootFolderName());
                $folder->save();

                $sql = 'UPDATE '.TBL_FOLDERS.'
                           SET fol_path = REPLACE(fol_path, \'/'.$rowFolder['fol_name'].'\', \'/'.TableFolder::getRootFolderName().'\')
                         WHERE fol_org_id = '.$rowId;
                $this->db->query($sql); // TODO add more params

                if($row['org_shortname'] === $g_organization)
                {
                    rename($folderOldName, $folder->getFullFolderPath());
                }
            }
            else
            {
                $sql = 'INSERT INTO '.TBL_FOLDERS.' (fol_org_id, fol_type, fol_name, fol_path, fol_locked, fol_public, fol_timestamp)
                        VALUES (?, \'DOWNLOAD\', ?, ?, 0, 1, ?) -- $rowId, TableFolder::getRootFolderName(), FOLDER_DATA, DATETIME_NOW';
                $params = array(
                    $rowId,
                    TableFolder::getRootFolderName(),
                    FOLDER_DATA,
                    DATETIME_NOW
                );
                $this->db->queryPrepared($sql, $params);
            }
        }

        $gCurrentOrganization = $tempOrganization;
    }

    /**
     * This method update the security settings for menus to standart values
     */
    public function updateStepMigrateToStandartMenu()
    {
        // Menu entries for the standart installation
        $sql = 'INSERT INTO '.TBL_MENU.' (men_id, men_parent_id, men_order, men_standart, men_modul_name, men_url, men_icon, men_translate_name, men_translate_desc, men_need_enable)
                   VALUES (1, NULL, 1, 1, NULL, NULL, \'\', \'SYS_MODULES\', \'\', 0)
                        , (2, NULL, 2, 1, NULL, NULL, \'\', \'SYS_ADMINISTRATION\', \'\', 1)
                        , (3, NULL, 3, 1, NULL, NULL, \'\', \'MEN_PLUGIN\', \'\', 1)
                        , (4, 1, 1, 1, \'overview\', \'/adm_program/index.php\', \'home.png\', \'SYS_OVERVIEW\', \'\', 0)
                        , (5, 1, 3, 1, \'download\', \'/adm_program/modules/downloads/downloads.php\', \'download.png\', \'DOW_DOWNLOADS\', \'DOW_DOWNLOADS_DESC\', 1)
                        , (6, 1, 7, 1, \'lists\', \'/adm_program/modules/lists/lists.php\', \'lists.png\', \'LST_LISTS\', \'LST_LISTS_DESC\', 0)
                        , (7, 1, 8, 1, \'mylist\', \'/adm_program/modules/lists/mylist.php\', \'mylist.png\', \'LST_MY_LIST\', \'\', 0)
                        , (8, 1, 2, 1, \'announcements\', \'/adm_program/modules/announcements/announcements.php\', \'announcements.png\', \'ANN_ANNOUNCEMENTS\', \'ANN_ANNOUNCEMENTS_DESC\', 1)
                        , (9, 1, 5, 1, \'photo\', \'/adm_program/modules/photos/photos.php\', \'photo.png\', \'PHO_PHOTOS\', \'PHO_PHOTOS_DESC\', 1)
                        , (10, 1, 6, 1, \'guestbook\', \'/adm_program/modules/guestbook/guestbook.php\', \'guestbook.png\', \'GBO_GUESTBOOK\', \'GBO_GUESTBOOK_DESC\', 1)
                        , (11, 1, 8, 1, \'dates\', \'/adm_program/modules/dates/dates.php\', \'dates.png\', \'DAT_DATES\', \'DAT_DATES_DESC\', 1)
                        , (12, 1, 9, 1, \'weblinks\', \'/adm_program/modules/links/links.php\', \'weblinks.png\', \'LNK_WEBLINKS\', \'LNK_WEBLINKS_DESC\', 1)
                        , (13, 2, 4, 1, \'dbback\', \'/adm_program/modules/backup/backup.php\', \'backup.png\', \'BAC_DATABASE_BACKUP\', \'BAC_DATABASE_BACKUP_DESC\', 0)
                        , (14, 2, 5, 1, \'orgprop\', \'/adm_program/modules/preferences/preferences.php\', \'options.png\', \'SYS_SETTINGS\', \'ORG_ORGANIZATION_PROPERTIES_DESC\', 0)
                        , (15, 1, 4, 1, \'mail\', \'/adm_program/modules/messages/messages_write.php\', \'email.png\', \'SYS_EMAIL\', \'MAI_EMAIL_DESC\', 0)
                        , (16, 2, 1, 1, \'newreg\', \'/adm_program/modules/registration/registration.php\', \'new_registrations.png\', \'NWU_NEW_REGISTRATIONS\', \'NWU_MANAGE_NEW_REGISTRATIONS_DESC\', 0)
                        , (17, 2, 2, 1, \'usrmgt\', \'/adm_program/modules/members/members.php\', \'user_administration.png\', \'MEM_USER_MANAGEMENT\', \'MEM_USER_MANAGEMENT_DESC\', 0)
                        , (18, 2, 3, 1, \'roladm\', \'/adm_program/modules/roles/roles.php\', \'roles.png\', \'ROL_ROLE_ADMINISTRATION\', \'ROL_ROLE_ADMINISTRATION_DESC\', 0)
                        , (19, 2, 6, 1, \'menu\', \'/adm_program/modules/menu/menu.php\', \'application_view_tile.png\', \'SYS_MENU\', \'\', 0)';
        $db->query($sql);

        // migrate adm_folder_roles to adm_roles_rights
        $sql = 'SELECT ror_id FROM '.TBL_ROLES_RIGHTS.' WHERE ror_name_intern = \'men_display\' ';
        $menuRightsStatement = $this->db->query($sql);
        $menuRightId = $menuRightsStatement->fetchColumn();

        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.TBL_MENU.' where men_modul_name = \'mail\'';
        $this->db->query($sql);

        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.TBL_MENU.' where men_modul_name = \'dbback\'';
        $this->db->query($sql);

        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.TBL_MENU.' where men_modul_name = \'orgprop\'';
        $this->db->query($sql);
    }

    /**
     * This method renames the webmaster role to administrator.
     */
    public function updateStepRenameWebmasterToAdministrator()
    {
        global $gL10n;

        $sql = 'UPDATE '.TBL_ROLES.'
                   SET rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')_1
                 WHERE rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')';
        $this->db->queryPrepared($sql, array($gL10n->get('SYS_ADMINISTRATOR') . '_1', $gL10n->get('SYS_ADMINISTRATOR')));

        $sql = 'UPDATE '.TBL_ROLES.'
                   SET rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')
                 WHERE rol_name = ? -- $gL10n->get(\'SYS_WEBMASTER\')';
        $this->db->queryPrepared($sql, array($gL10n->get('SYS_ADMINISTRATOR'), $gL10n->get('SYS_WEBMASTER')));
    }

    /**
     * Check all folders in adm_my_files and set the rights to 0777
     * @param string $folder
     * @return bool
     */
    public function updateStepRewriteFolderRights($folder = '')
    {
        $returnValue = true;

        if ($folder === '')
        {
            $folder = ADMIDIO_PATH . FOLDER_DATA;
        }

        $dirHandle = @opendir($folder);
        if ($dirHandle)
        {
            while (($entry = readdir($dirHandle)) !== false)
            {
                if ($entry !== '.' && $entry !== '..')
                {
                    $resource = $folder . '/' . $entry;

                    if (is_dir($resource))
                    {
                        // now check the subfolder
                        $returnValue = $returnValue && $this->updateStepRewriteFolderRights($resource);

                        // set rights to 0777
                        $returnValue = $returnValue && @chmod($resource, 0777);
                    }
                }
            }
            closedir($dirHandle);
        }

        return $returnValue;
    }

    /**
     * This method set the default configuration for all organizations
     */
    public function updateStepSetDefaultConfiguration()
    {
        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
        $organizationsStatement = $this->db->queryPrepared($sql);
        $organizationsArray     = $organizationsStatement->fetchAll();

        foreach($organizationsArray as $organization)
        {
            $orgId = (int) $organization['org_id'];

            $sql = 'SELECT lst_id
                      FROM '.TBL_LISTS.'
                     WHERE lst_default = 1
                       AND lst_org_id  = ? -- $orgId';
            $defaultListStatement = $this->db->queryPrepared($sql, array($orgId));
            $listId = (int) $defaultListStatement->fetchColumn();

            // save default list to preferences
            $sql = 'UPDATE '.TBL_PREFERENCES.'
                       SET prf_value  = ? -- $listId
                     WHERE prf_name   = \'lists_default_configuation\'
                       AND prf_org_id = ? -- $orgId';
            $this->db->queryPrepared($sql, array($listId, $orgId));
        }
    }

    /**
     * This method set the approval states for all members of an event in the past to confirmed.
     */
    public function updateStepSetParticipantsApprovalStates()
    {
        $sql = 'UPDATE '.TBL_MEMBERS.'
                           SET mem_approved = 2
                         WHERE mem_approved IS NULL
                           AND mem_begin < ? -- DATE_NOW
                           AND mem_rol_id IN (SELECT rol_id
                                                FROM '.TBL_ROLES.'
                                          INNER JOIN '.TBL_CATEGORIES.'
                                                  ON cat_id = rol_cat_id
                                               WHERE cat_name_intern = \'EVENTS\'
                                                 AND rol_id IN (SELECT dat_rol_id
                                                                  FROM '.TBL_DATES.'
                                                                 WHERE dat_rol_id = rol_id))';

        $this->db->queryPrepared($sql, array(DATE_NOW));
    }

    /**
     * This method installs the default user relation types
     */
    public function updateStepInstallDefaultUserRelationTypes()
    {
        global $gL10n, $gCurrentUser;

        $currUsrId = (int) $gCurrentUser->getValue('usr_id');

        $sql = 'INSERT INTO '.TBL_USER_RELATION_TYPES.' (urt_id, urt_name, urt_name_male, urt_name_female, urt_id_inverse, urt_usr_id_create, urt_timestamp_create)
                VALUES (1, \''.$gL10n->get('INS_PARENT').'\',      \''.$gL10n->get('INS_FATHER').'\',           \''.$gL10n->get('INS_MOTHER').'\',             2, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (2, \''.$gL10n->get('INS_CHILD').'\',       \''.$gL10n->get('INS_SON').'\',              \''.$gL10n->get('INS_DAUGHTER').'\',           1, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (3, \''.$gL10n->get('INS_SIBLING').'\',     \''.$gL10n->get('INS_BROTHER').'\',          \''.$gL10n->get('INS_SISTER').'\',             3, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (4, \''.$gL10n->get('INS_SPOUSE').'\',      \''.$gL10n->get('INS_HUSBAND').'\',          \''.$gL10n->get('INS_WIFE').'\',               4, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (5, \''.$gL10n->get('INS_COHABITANT').'\',  \''.$gL10n->get('INS_COHABITANT_MALE').'\',  \''.$gL10n->get('INS_COHABITANT_FEMALE').'\',  5, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (6, \''.$gL10n->get('INS_COMPANION').'\',   \''.$gL10n->get('INS_BOYFRIEND').'\',        \''.$gL10n->get('INS_GIRLFRIEND').'\',         6, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (7, \''.$gL10n->get('INS_SUPERIOR').'\',    \''.$gL10n->get('INS_SUPERIOR_MALE').'\',    \''.$gL10n->get('INS_SUPERIOR_FEMALE').'\',    8, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (8, \''.$gL10n->get('INS_SUBORDINATE').'\', \''.$gL10n->get('INS_SUBORDINATE_MALE').'\', \''.$gL10n->get('INS_SUBORDINATE_FEMALE').'\', 7, '.$currUsrId.', \''.DATETIME_NOW.'\')';
        $this->db->query($sql); // TODO add more params
    }

    /**
     * This method add all roles to the role right category_view if the role had set the flag cat_hidden = 1
     */
    public function updateStepVisibleCategories()
    {
        $sql = 'SELECT cat_id, cat_org_id
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type IN (\'ANN\', \'DAT\', \'LNK\', \'USF\')
                   AND cat_org_id IS NOT NULL
                   AND cat_hidden = 1 ';
        $categoryStatement = $this->db->queryPrepared($sql);

        while($row = $categoryStatement->fetch())
        {
            $roles = array();
            $sql = 'SELECT rol_id
                      FROM ' . TBL_ROLES . '
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                     WHERE rol_valid  = 1
                       AND cat_name_intern <> \'EVENTS\'
                       AND cat_org_id = ? -- $row[\'cat_org_id\']';
            $rolesStatement = $this->db->queryPrepared($sql, array((int) $row['cat_org_id']));

            while($rowRole = $rolesStatement->fetch())
            {
                $roles[] = (int) $rowRole['rol_id'];
            }

            // save roles to role right
            $rightCategoryView = new RolesRights($this->db, 'category_view', (int) $row['cat_id']);
            $rightCategoryView->saveRoles($roles);
        }
    }
}
