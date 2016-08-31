<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
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
    private $updateFinished;        ///< Flag that will store if the update process of this version was successfully finished
    private $xmlObject;             ///< The SimpleXML object with all the update steps
    private $currentVersionArray;   ///< This is the version the component has actually before update. Each array element contains one part of the version.
    private $targetVersionArray;    ///< This is the version that is stored in the files of the component. Each array element contains one part of the version.

    /**
     * Will open a XML file of a specific version that contains all the update steps that
     * must be passed to successfully update Admidio to this version
     * @param string|int $mainVersion Contains a string with the main version number e.g. 2 or 3 from 2.x or 3.x.
     * @param string|int $subVersion  Contains a string with the main version number e.g. 1 or 2 from x.1 or x.2.
     * @return bool
     */
    private function createXmlObject($mainVersion, $subVersion)
    {
        // update of Admidio core has another path for the xml files as plugins
        if($this->getValue('com_type') === 'SYSTEM')
        {
            $updateFile = SERVER_PATH.'/adm_program/installation/db_scripts/update_'.$mainVersion.'_'.$subVersion.'.xml';

            if(file_exists($updateFile))
            {
                $this->xmlObject = new SimpleXMLElement($updateFile, null, true);
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
    private function executeStep(SimpleXMLElement $xmlNode)
    {
        global $g_tbl_praefix, $gDbType;

        $executeSql = true;
        $showError  = true;

        $updateStepContent = trim((string) $xmlNode);

        if($updateStepContent !== '')
        {
            // if the sql statement is only for a special database and you do
            // not have this database then don't execute this statement
            if(isset($xmlNode['database']) && (string) $xmlNode['database'] !== $gDbType)
            {
                $executeSql = false;
            }

            // if the attribute error was set to "ignore" then don't show errors that occures on sql execution
            if(isset($xmlNode['error']) && (string) $xmlNode['error'] === 'ignore')
            {
                $showError = false;
            }

            // if a method of this class was set in the update step
            // then call this function and don't execute a SQL statement
            if(strpos($updateStepContent, 'ComponentUpdate') !== false)
            {
                $executeSql = false;

                // get the method name
                $function = substr($updateStepContent, strpos($updateStepContent, '::')+2);
                // now call the method
                $this->{$function}();
            }

            if($executeSql)
            {
                // replace prefix with installation specific table prefix
                $sql = str_replace('%PREFIX%', $g_tbl_praefix, $updateStepContent);

                $this->db->query($sql, $showError);
            }

            // set the type if the id to integer because otherwise the system thinks it's not numeric !!!
            $stepId = $xmlNode['id'];
            settype($stepId, 'integer');

            // save the successful executed update step in database
            $this->setValue('com_update_step', $stepId);
            $this->save();
        }
    }

    /**
     * Goes step by step through the update xml file of the current database version and search for the maximum step.
     * If the last step is found than the id of this step will be returned.
     * @return int Return the number of the last update step that was found in xml file of the current version.
     */
    public function getMaxUpdateStep()
    {
        $maxUpdateStep = 0;
        $this->currentVersionArray = array_map('intval', explode('.', $this->getValue('com_version')));

        // open xml file for this version
        if($this->createXmlObject($this->currentVersionArray[0], $this->currentVersionArray[1]))
        {
            // go step by step through the SQL statements until the last one is found
            foreach($this->xmlObject->children() as $updateStep)
            {
                if((string) $updateStep !== 'stop')
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
        $this->targetVersionArray = array_map('intval', explode('.', $version));
    }

    /**
     * Do a loop through all versions start with the current version and end with the target version.
     * Within every subversion the method will search for an update xml file and execute all steps
     * in this file until the end of file is reached. If an error occurred then the update will be stopped.
     */
    public function update()
    {
        global $gDebug;

        $this->updateFinished = false;
        $this->currentVersionArray = array_map('intval', explode('.', $this->getValue('com_version')));
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
                if($gDebug)
                {
                    error_log('Update to version '.$mainVersion.'.'.$subVersion);
                }

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
                        elseif((string) $updateStep === 'stop')
                        {
                            $this->updateFinished = true;
                        }
                    }
                }

                // check if an php update file exists and then execute the script
                $phpUpdateFile = SERVER_PATH.'/adm_program/installation/db_scripts/upd_'.$mainVersion.'_'.$subVersion.'_0_conv.php';

                if(file_exists($phpUpdateFile))
                {
                    require_once($phpUpdateFile);
                }

                // save current version to system component
                $this->setValue('com_version', ADMIDIO_VERSION);
                $this->setValue('com_beta', ADMIDIO_VERSION_BETA);
                $this->save();

                // save current version to all modules
                $sql = 'UPDATE '.TBL_COMPONENTS.' SET com_version = \''.ADMIDIO_VERSION.'\'
                                                    , com_beta    = \''.ADMIDIO_VERSION_BETA.'\'
                         WHERE com_type LIKE \'MODULE\' ';
                $this->db->query($sql);
            }

            // reset subversion because we want to start update for next main version with subversion 0
            $initialSubVersion = 0;
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
                 WHERE cat_name_intern LIKE \'CONFIRMATION_OF_PARTICIPATION\'
                   AND NOT exists (SELECT 1
                                     FROM '.TBL_DATES.'
                                    WHERE dat_rol_id = rol_id)';
        $rolesStatement = $this->db->query($sql);

        while($roleId = $rolesStatement->fetchColumn())
        {
            $role = new TableRoles($this->db, (int) $roleId);
            $role->delete(); // TODO Exception handling
        }
    }

    /**
     * This method migrate the data of the table adm_folder_roles to the
     * new table adm_roles_rights_data.
     */
    public function updateStepMigrateToFolderRights()
    {
        global $g_tbl_praefix, $g_organization;

        // migrate adm_folder_roles to adm_roles_rights
        $sql = 'SELECT ror_id FROM '.TBL_ROLES_RIGHTS.' WHERE ror_name_intern = \'folder_view\' ';
        $rolesRightsStatement = $this->db->query($sql);
        $rolesRightId = $rolesRightsStatement->fetchColumn();

        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$rolesRightId.', flr_rol_id, flr_fol_id
                  FROM '.$g_tbl_praefix.'_folder_roles ';
        $this->db->query($sql);

        // add new right folder_update to adm_roles_rights
        $sql = 'SELECT fol_id FROM '.TBL_FOLDERS.'
                 WHERE fol_type = \'DOWNLOAD\'
                   AND fol_name = \'download\' ';
        $rolesRightsStatement = $this->db->query($sql);
        $folderId = (int) $rolesRightsStatement->fetchColumn();

        $sql = 'SELECT rol_id FROM '.TBL_ROLES.'
                  LEFT JOIN '.TBL_CATEGORIES.' ON cat_id = rol_cat_id
                  LEFT JOIN '.TBL_ORGANIZATIONS.' ON org_id = cat_org_id
                   AND org_shortname = \''.$g_organization.'\'
                 WHERE rol_download = 1 ';
        $rolesDownloadStatement = $this->db->query($sql);

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

        $sql = 'SELECT org_id, org_shortname FROM '.TBL_ORGANIZATIONS;
        $organizationStatement = $this->db->query($sql);

        while($row = $organizationStatement->fetch())
        {
            $gCurrentOrganization->readDataById($row['org_id']);

            $sql = 'SELECT fol_id, fol_name FROM '.TBL_FOLDERS.'
                     WHERE fol_org_id = '.$row['org_id'].'
                       AND fol_fol_id_parent IS NULL ';
            $folderStatement = $this->db->query($sql);

            if($rowFolder = $folderStatement->fetch())
            {
                $folder = new TableFolder($this->db, $rowFolder['fol_id']);
                $folderOldName = $folder->getCompletePathOfFolder();
                $folder->setValue('fol_name', TableFolder::getRootFolderName());
                $folder->save();

                $sql = 'UPDATE '.TBL_FOLDERS.' SET fol_path = REPLACE(fol_path, \'/'.$rowFolder['fol_name'].'\', \'/'.TableFolder::getRootFolderName().'\')
                         WHERE fol_org_id = '.$row['org_id'];
                $this->db->query($sql);

                if($row['org_shortname'] === $g_organization)
                {
                    rename($folderOldName, $folder->getCompletePathOfFolder());
                }
            }
            else
            {
                $sql = 'INSERT INTO '.TBL_FOLDERS.' (fol_org_id, fol_type, fol_name, fol_path,
                                                     fol_locked, fol_public, fol_timestamp)
                                             VALUES ('.$row['org_id'].', \'DOWNLOAD\', \''.TableFolder::getRootFolderName().'\', \'/adm_my_files\',
                                                     0, 1, \''.DATETIME_NOW.'\')';
                $this->db->query($sql);
            }
        }

        $gCurrentOrganization = $tempOrganization;
    }
    
    /**
     * This method update the security settings for menus to standart values
     */
    public function updateStepMigrateToStandartMenu()
    {
        global $g_tbl_praefix;

        // migrate adm_folder_roles to adm_roles_rights
        $sql = 'SELECT ror_id FROM '.TBL_ROLES_RIGHTS.' WHERE ror_name_intern = \'men_display_right\' ';
        $menuRightsStatement = $this->db->query($sql);
        $menuRightId = $menuRightsStatement->fetchColumn();

        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.$g_tbl_praefix.'_menu where men_modul_name = \'mail\'';
        $this->db->query($sql);
        
        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.$g_tbl_praefix.'_menu where men_modul_name = \'dbback\'';
        $this->db->query($sql);
        
        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.$g_tbl_praefix.'_menu where men_modul_name = \'orgprop\'';
        $this->db->query($sql);
        
        $sql = 'SELECT ror_id FROM '.TBL_ROLES_RIGHTS.' WHERE ror_name_intern = \'men_display_index\' ';
        $menuRightsStatement = $this->db->query($sql);
        $menuRightId = $menuRightsStatement->fetchColumn();

        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.$g_tbl_praefix.'_menu where men_modul_name = \'mail\'';
        $this->db->query($sql);
        
        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.$g_tbl_praefix.'_menu where men_modul_name = \'dbback\'';
        $this->db->query($sql);
        
        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.$g_tbl_praefix.'_menu where men_modul_name = \'orgprop\'';
        $this->db->query($sql);
        
        /*$displayRight = new RolesRights($db, 'men_display_boot', 11);
        $displayRight->addRoles(array(1));*/
        
        $sql = 'SELECT ror_id FROM '.TBL_ROLES_RIGHTS.' WHERE ror_name_intern = \'men_display_boot\' ';
        $menuRightsStatement = $this->db->query($sql);
        $menuRightId = $menuRightsStatement->fetchColumn();

        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.$g_tbl_praefix.'_menu where men_modul_name = \'mail\'';
        $this->db->query($sql);
        
        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.$g_tbl_praefix.'_menu where men_modul_name = \'dbback\'';
        $this->db->query($sql);
        
        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.' (rrd_ror_id, rrd_rol_id, rrd_object_id)
                SELECT '.$menuRightId.', 1, men_id
                  FROM '.$g_tbl_praefix.'_menu where men_modul_name = \'orgprop\'';
        $this->db->query($sql);
    }

    /**
     * This method renames the webmaster role to administrator.
     */
    public function updateStepRenameWebmasterToAdministrator()
    {
        global $gL10n;

        $sql = 'UPDATE '.TBL_ROLES.' SET rol_name = \''.$gL10n->get('SYS_ADMINISTRATOR').'_1\'
                 WHERE rol_name = \''.$gL10n->get('SYS_ADMINISTRATOR').'\'';
        $this->db->query($sql);

        $sql = 'UPDATE '.TBL_ROLES.' SET rol_name = \''.$gL10n->get('SYS_ADMINISTRATOR').'\'
                 WHERE rol_name = \''.$gL10n->get('SYS_WEBMASTER').'\'';
        $this->db->query($sql);
    }

    /**
     * This method set the default configuration for all organizations
     */
    public function updateStepSetDefaultConfiguration()
    {
        $sql = 'SELECT org_id FROM '.TBL_ORGANIZATIONS;
        $organizationsStatement = $this->db->query($sql);
        $organizationsArray     = $organizationsStatement->fetchAll();

        foreach($organizationsArray as $organization)
        {
            $sql = 'SELECT lst_id
                      FROM '.TBL_LISTS.'
                     WHERE lst_org_id  = '. $organization['org_id'].'
                       AND lst_default = 1 ';
            $defaultListStatement = $this->db->query($sql);
            $listId = $defaultListStatement->fetchColumn();

            // save default list to preferences
            $sql = 'UPDATE '.TBL_PREFERENCES.' SET prf_value = \''.$listId.'\'
                     WHERE prf_org_id = '.$organization['org_id'].'
                       AND prf_name   = \'lists_default_configuation\' ';
            $this->db->query($sql);
        }
    }

}
