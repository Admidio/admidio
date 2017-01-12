<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_folders
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableFolder
 * Diese Klasse dient dazu ein Folderobjekt zu erstellen.
 * Ein Ordner kann ueber diese Klasse in der Datenbank verwaltet werden
 */
class TableFolder extends TableAccess
{
    protected $folderPath;
    protected $folderViewRolesObject;   ///< Object with all roles that could view the current folder
    protected $folderUploadRolesObject; ///< Object with all roles that could upload files the current folder

    /**
     * Constructor that will create an object of a recordset of the table adm_folders.
     * If the id is set than the specific folder will be loaded.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $folId    The recordset of the folder with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $folId = 0)
    {
        parent::__construct($database, TBL_FOLDERS, 'fol', $folId);

        $this->folderPath = new Folder();
    }

    /**
     * Return PDOStatement with all subfolders of a parent folder id
     * @param int      $folderId Folder ID
     * @param string[] $columns  The columns that should be in the statement
     * @return \PDOStatement SubfolderStatement with fol_id column
     */
    private function getSubfolderStatement($folderId, array $columns = array('fol_id'))
    {
        // select all subfolders of the current folder
        $sqlSubfolders = 'SELECT ' . implode(',', $columns) . '
                            FROM '.TBL_FOLDERS.'
                           WHERE fol_fol_id_parent = ' . $folderId;
        return $this->db->query($sqlSubfolders);
    }

    /**
     * Add all roles of the array to the current folder and all of the subfolders. The
     * roles will be assigned to the right that was set through parameter $rolesRightNameIntern.
     * @param string $mode                 "mode" could be "add" or "remove"
     * @param string $rolesRightNameIntern Name of the right where the roles should be added
     * @param int[]  $rolesArray
     * @param int    $folderId
     */
    private function editRolesOnFolder($mode, $rolesRightNameIntern, array $rolesArray, $folderId = 0)
    {
        if (count($rolesArray) === 0)
        {
            return;
        }

        if ($folderId === 0)
        {
            $folderId = (int) $this->getValue('fol_id');
        }

        $this->db->startTransaction();

        $subfoldersStatement = $this->getSubfolderStatement($folderId);

        while ($folId = (int) $subfoldersStatement->fetchColumn())
        {
            // recursive call for every subfolder
            $this->editRolesOnFolder($mode, $rolesRightNameIntern, $rolesArray, $folId);
        }

        // add new rights to folder
        $folderRolesRights = new RolesRights($this->db, $rolesRightNameIntern, $folderId);
        if ($mode === 'add')
        {
            $folderRolesRights->addRoles($rolesArray);
        }
        else
        {
            $folderRolesRights->removeRoles($rolesArray);
        }

        $this->db->endTransaction();
    }

    /**
     * Add all roles of the array to the current folder and all of the subfolders. The
     * roles will be assigned to the right that was set through parameter $rolesRightNameIntern.
     * @param string $rolesRightNameIntern Name of the right where the roles should be added
     * @param int[]  $rolesArray
     * @param int    $folderId
     */
    public function addRolesOnFolder($rolesRightNameIntern, array $rolesArray, $folderId = 0)
    {
        $this->editRolesOnFolder('add', $rolesRightNameIntern, $rolesArray, $folderId);
    }

    /**
     * Remove all roles of the array from the current folder and all of the subfolders. The
     * roles will be removed from the right that was set through parameter $rolesRightNameIntern.
     * @param string $rolesRightNameIntern Name of the right where the roles should be removed
     * @param int[]  $rolesArray
     * @param int    $folderId
     */
    public function removeRolesOnFolder($rolesRightNameIntern, array $rolesArray, $folderId = 0)
    {
        $this->editRolesOnFolder('remove', $rolesRightNameIntern, $rolesArray, $folderId);
    }

    /**
     * Legt einen neuen Ordner im Dateisystem an
     * @param string $folderName
     * @return string[]|null
     */
    public function createFolder($folderName)
    {
        $this->folderPath->setFolder($this->getCompletePathOfFolder());
        $this->folderPath->createFolder($folderName, true);

        if ($this->folderPath->createFolder($folderName, true))
        {
            return null;
        }

        return array(
            'text' => 'SYS_FOLDER_NOT_CREATED',
            'path' => $this->getCompletePathOfFolder() . '/' . $folderName
        );
    }

    /**
     * Deletes the selected record of the table and all references in other tables.
     * Also all files, subfolders and the selected folder will be deleted in the file system.
     * After that the class will be initialize.
     * @param int $folderId
     * @return bool @b true if no error occurred
     */
    public function delete($folderId = 0)
    {
        $folId = (int) $this->getValue('fol_id');
        $folderPath = '';

        if ($folderId === 0)
        {
            if ($this->getValue('fol_name') === '')
            {
                return false;
            }

            $folderId = (int) $this->getValue('fol_id');
            $folderPath = $this->getCompletePathOfFolder();
        }

        $this->db->startTransaction();

        $subfoldersStatement = $this->getSubfolderStatement($folderId);

        while ($rowFolId = (int) $subfoldersStatement->fetchColumn())
        {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->delete($rowFolId);
        }

        // In der DB die Files der aktuellen folder_id loeschen
        $sqlDeleteFiles = 'DELETE FROM '.TBL_FILES.'
                            WHERE fil_fol_id = '.$folderId;
        $this->db->query($sqlDeleteFiles);

        // delete all roles assignments that have the right to view this folder
        if ($folderId === $folId)
        {
            $this->folderViewRolesObject->delete();
            $this->folderUploadRolesObject->delete();
        }
        else
        {
            $folderViewRoles = new RolesRights($this->db, 'folder_view', $folderId);
            $folderViewRoles->delete();
            $folderUploadRoles = new RolesRights($this->db, 'folder_upload', $folderId);
            $folderUploadRoles->delete();
        }

        // In der DB den Eintrag des Ordners selber loeschen
        $sqlDeleteFolder = 'DELETE FROM '.TBL_FOLDERS.'
                             WHERE fol_id = '.$folderId;
        $this->db->query($sqlDeleteFolder);

        // Jetzt noch das Verzeichnis physikalisch von der Platte loeschen
        if ($folderPath !== '')
        {
            $this->folderPath->setFolder($folderPath);
            $this->folderPath->delete($folderPath);
        }

        $returnCode = true;

        // Auch wenn das physikalische Löschen fehl schlägt, wird in der DB alles gelöscht...
        if ($folderId === $folId)
        {
            $returnCode = parent::delete();
        }

        $this->db->endTransaction();

        return $returnCode;
    }

    /**
     * Setzt das Lockedflag (0 oder 1) auf einer vorhandenen Ordnerinstanz
     * und allen darin enthaltenen Unterordnern und Dateien rekursiv
     * @param bool $lockedFlag
     * @param int $folderId
     */
    public function editLockedFlagOnFolder($lockedFlag, $folderId = 0)
    {
        if ($folderId === 0)
        {
            $folderId = (int) $this->getValue('fol_id');
            $this->setValue('fol_locked', $lockedFlag);
        }

        $this->db->startTransaction();

        $subfoldersStatement = $this->getSubfolderStatement($folderId);

        while ($folId = (int) $subfoldersStatement->fetchColumn())
        {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->editLockedFlagOnFolder($lockedFlag, $folId);
        }

        // Jetzt noch das Flag in der DB setzen fuer die aktuelle folder_id...
        $sqlUpdate = 'UPDATE '.TBL_FOLDERS.'
                         SET fol_locked = \''.$lockedFlag.'\'
                       WHERE fol_id = '.$folderId;
        $this->db->query($sqlUpdate);

        //...und natuerlich auch fuer alle Files die in diesem Ordner sind
        $sqlUpdate = 'UPDATE '.TBL_FILES.'
                         SET fil_locked = \''.$lockedFlag.'\'
                       WHERE fil_fol_id = '.$folderId;
        $this->db->query($sqlUpdate);

        $this->db->endTransaction();
    }

    /**
     * Set the public flag to a folder and all subfolders.
     * @param bool $publicFlag If set to @b 1 then all users could see this folder.
     * @param int  $folderId   The id of the folder where the public flag should be set.
     */
    public function editPublicFlagOnFolder($publicFlag, $folderId = 0)
    {
        if ($folderId === 0)
        {
            $folderId = (int) $this->getValue('fol_id');
            $this->setValue('fol_public', (int) $publicFlag);
        }

        $subfoldersStatement = $this->getSubfolderStatement($folderId);

        while ($folId = (int) $subfoldersStatement->fetchColumn())
        {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->editPublicFlagOnFolder($publicFlag, $folId);
        }

        // Jetzt noch das Flag in der DB setzen fuer die aktuelle folder_id...
        $sqlUpdate = 'UPDATE '.TBL_FOLDERS.'
                         SET fol_public = \''.(int) $publicFlag.'\'
                       WHERE fol_id = '.$folderId;
        $this->db->query($sqlUpdate);

    }

    /**
     * Reads the folder recordset from database table @b adm_folders and throws an
     * AdmException if the user has no right to see the folder or the folder id doesn't exists.
     * @param int $folderId The id of the folder. If the id is 0 then the root folder will be shown.
     * @throws AdmException
     * @return true Returns @b true if everything is ok otherwise an AdmException is thrown.
     */
    public function getFolderForDownload($folderId)
    {
        global $gCurrentOrganization, $gCurrentUser;

        if ($folderId > 0)
        {
            // get folder of the parameter
            $condition = ' fol_id   = '.$folderId.'
                       AND fol_type = \'DOWNLOAD\' ';
        }
        else
        {
            // get first folder of current organization
            $condition = ' fol_fol_id_parent IS NULL
                       AND fol_type   = \'DOWNLOAD\'
                       AND fol_org_id = '.$gCurrentOrganization->getValue('org_id');
        }
        $this->readData($condition);

        // Check if a dataset is found
        if ((int) $this->getValue('fol_id') === 0)
        {
            throw new AdmException('DOW_FOLDER_NOT_FOUND', $folderId);
        }

        // If current user has download-admin-rights => allow
        if ($gCurrentUser->editDownloadRight())
        {
            return true;
        }

        // If folder is locked (and no download-admin-rights) => throw exception
        if ($this->getValue('fol_locked'))
        {
            $this->clear();
            throw new AdmException('DOW_FOLDER_NO_RIGHTS');
        }

        // If folder is public (and file is not locked) => allow
        if ($this->getValue('fol_public'))
        {
            return true;
        }

        // check if user has a membership in a role that is assigned to the current folder
        if ($this->folderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships()))
        {
            return true;
        }

        $this->clear();
        throw new AdmException('DOW_FOLDER_NO_RIGHTS');
    }

    /**
     * @return array[] All sub-folders with their properties
     */
    private function getSubfoldersWithProperties()
    {
        global $gCurrentOrganization, $gCurrentUser, $gValidLogin;

        $folders = array();

        // Get all subfolder of the current folder
        $sqlFolders = 'SELECT *
                         FROM '.TBL_FOLDERS.'
                        WHERE fol_type          = \'DOWNLOAD\'
                          AND fol_fol_id_parent = '. $this->getValue('fol_id'). '
                          AND fol_org_id        = '. $gCurrentOrganization->getValue('org_id'). '
                     ORDER BY fol_name';
        $foldersStatement = $this->db->query($sqlFolders);

        while ($rowFolders = $foldersStatement->fetchObject())
        {
            $folderExists = is_dir(ADMIDIO_PATH . $rowFolders->fol_path . '/' . $rowFolders->fol_name);

            $addToArray = false;

            // If user has editDownloadRight, show it
            if ($gCurrentUser->editDownloadRight())
            {
                $addToArray = true;
            }
            // If user hasn't editDownloadRight, only show if folder exists
            elseif ($folderExists)
            {
                // If folder is public and not locked, show it
                if ($rowFolders->fol_public && !$rowFolders->fol_locked)
                {
                    $addToArray = true;
                }
                // If user has a membership in a role that is assigned to the current subfolder, show it
                elseif ($gValidLogin)
                {
                    $subfolderViewRolesObject = new RolesRights($this->db, 'folder_view', $rowFolders->fol_id);

                    if($subfolderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships()))
                    {
                        $addToArray = true;
                    }
                }
            }

            if ($addToArray)
            {
                $folders[] = array(
                    'fol_id'          => $rowFolders->fol_id,
                    'fol_name'        => $rowFolders->fol_name,
                    'fol_description' => $rowFolders->fol_description,
                    'fol_path'        => $rowFolders->fol_path,
                    'fol_timestamp'   => $rowFolders->fol_timestamp,
                    'fol_public'      => $rowFolders->fol_public,
                    'fol_exists'      => $folderExists,
                    'fol_locked'      => $rowFolders->fol_locked
                );
            }
        }

        return $folders;
    }

    /**
     * @return array[] All files with their properties
     */
    private function getFilesWithProperties()
    {
        global $gCurrentUser;

        $files = array();

        // Get all files of the current folder
        $sqlFiles = 'SELECT *
                       FROM '.TBL_FILES.'
                      WHERE fil_fol_id = '. $this->getValue('fol_id'). '
                   ORDER BY fil_name';
        $filesStatement = $this->db->query($sqlFiles);

        // jetzt noch die Dateien ins Array packen:
        while ($rowFiles = $filesStatement->fetchObject())
        {
            $filePath = ADMIDIO_PATH . $this->getValue('fol_path') . '/' . $this->getValue('fol_name') . '/' . $rowFiles->fil_name;
            $fileExists = is_file($filePath);

            $fileSize = 0;
            if ($fileExists)
            {
                $fileSize = round(filesize($filePath) / 1024);
            }

            $addToArray = false;

            // If file exists and file isn't locked or user has editDownloadRight, show it
            if (($fileExists && !$rowFiles->fil_locked) || $gCurrentUser->editDownloadRight())
            {
                $addToArray = true;
            }

            if ($addToArray)
            {
                $files[] = array(
                    'fil_id'          => $rowFiles->fil_id,
                    'fil_name'        => $rowFiles->fil_name,
                    'fil_description' => $rowFiles->fil_description,
                    'fil_timestamp'   => $rowFiles->fil_timestamp,
                    'fil_locked'      => $rowFiles->fil_locked,
                    'fil_exists'      => $fileExists,
                    'fil_size'        => $fileSize,
                    'fil_counter'     => $rowFiles->fil_counter
                );
            }
        }

        return $files;
    }

    /**
     * @param array[] $completeFolder
     * @return array[]
     */
    private function addAdditionalToFolderContents($completeFolder)
    {
        global $gCurrentUser;

        // If user hasn't editDownloadRight, don't add more data
        if (!$gCurrentUser->editDownloadRight())
        {
            return $completeFolder;
        }

        // Check if folder exists
        $folderPath = $this->getCompletePathOfFolder();
        if (!is_dir($folderPath))
        {
            return $completeFolder;
        }

        // User has editDownloadRight and folder exists, so lookup the physical directory for items that aren't in the DB
        $dirHandle = @opendir($folderPath);
        if ($dirHandle)
        {
            while (($entry = readdir($dirHandle)) !== false)
            {
                if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0)
                {
                    continue;
                }

                $alreadyAdded = false;

                // Check if entry is folder or file
                $entryFolderPath = $folderPath . '/' . $entry;

                if (is_dir($entryFolderPath))
                {
                    // Check if folder is already in the regular folders
                    foreach ($completeFolder['folders'] as $folder)
                    {
                        if ($folder['fol_name'] === $entry)
                        {
                            $alreadyAdded = true;
                            break;
                        }
                    }

                    // If isn't already in, add it
                    if (!$alreadyAdded)
                    {
                        $completeFolder['additionalFolders'][] = array('fol_name' => $entry);
                    }
                }
                elseif (is_file($entryFolderPath))
                {
                    // Check if file is already in the regular files
                    foreach ($completeFolder['files'] as $file)
                    {
                        if ($file['fil_name'] === $entry)
                        {
                            $alreadyAdded = true;
                            break;
                        }
                    }

                    // If isn't already in, add it
                    if (!$alreadyAdded)
                    {
                        $completeFolder['additionalFiles'][] = array(
                            'fil_name' => $entry,
                            'fil_size' => round(filesize($entryFolderPath) / 1024)
                        );
                    }
                }
            }

            closedir($dirHandle);
        }

        return $completeFolder;
    }

    /**
     * Inhalt des aktuellen Ordners, abhaengig von den Benutzerrechten, als Array zurueckliefern...
     * @return array[]
     */
    public function getFolderContentsForDownload()
    {
        $completeFolder = array(
            'folders' => $this->getSubfoldersWithProperties(),
            'files'   => $this->getFilesWithProperties()
        );

        return $this->addAdditionalToFolderContents($completeFolder);
    }

    /**
     * Gibt den kompletten Pfad des Ordners zurueck
     * @return string
     */
    public function getCompletePathOfFolder()
    {
        // Put the path together
        $folderPath = $this->getValue('fol_path');
        $folderName = $this->getValue('fol_name');

        return ADMIDIO_PATH . $folderPath . '/' . $folderName;
    }

    /**
     * Gibt fuer das Downloadmodul eine HTML-Navigationsleiste fuer die Ordner zurueck
     * @param int    $folderId
     * @param string $currentNavigation
     * @return string
     */
    public function getNavigationForDownload($folderId = 0, $currentNavigation = '')
    {
        global $gCurrentOrganization, $gL10n;

        if ($folderId > 0)
        {
            // Get infos from requested folder
            $sqlCurrentFolder = 'SELECT fol_id, fol_fol_id_parent, fol_name
                                   FROM '.TBL_FOLDERS.'
                                  WHERE fol_id = '.$folderId;
            $currentFolderStatement = $this->db->query($sqlCurrentFolder);
            $currentFolderRow = $currentFolderStatement->fetchObject();

            if ($currentFolderRow->fol_fol_id_parent)
            {
                $currentNavigation = '<li><a href="'.ADMIDIO_URL.FOLDER_MODULES.'/downloads/downloads.php?folder_id='.
                    $currentFolderRow->fol_id.'">'.$currentFolderRow->fol_name.'</a></li>'.$currentNavigation;

                // Next call with parent folder
                return $this->getNavigationForDownload($currentFolderRow->fol_fol_id_parent, $currentNavigation);
            }

            return $currentNavigation;
        }

        $parentId = $this->getValue('fol_fol_id_parent');

        // If there is no parent folder, navigation-bar isn't necessary
        if ($parentId === '')
        {
            return '';
        }

        $currentNavigation = $this->getNavigationForDownload((int) $parentId, $currentNavigation);

        // If the folder has a parent folder we need the root folder
        $sqlRootFolder = 'SELECT fol_id
                            FROM '.TBL_FOLDERS.'
                           WHERE fol_type   = \'DOWNLOAD\'
                             AND fol_fol_id_parent IS NULL
                             AND fol_org_id = '.$gCurrentOrganization->getValue('org_id');
        $rootFolderStatement = $this->db->query($sqlRootFolder);
        $rootFolderId = $rootFolderStatement->fetchColumn();

        $link = '
            <ol class="breadcrumb">
                <li>
                    <a class="btn" href="'.ADMIDIO_URL.FOLDER_MODULES.'/downloads/downloads.php?folder_id='.$rootFolderId.'"><img
                    src="'.THEME_URL.'/icons/application_view_list.png" alt="Downloads" />'.$gL10n->get('DOW_DOWNLOADS').'</a>
                </li>'.
                $currentNavigation.
                '<li>'.$this->getValue('fol_name').'</li>
            </ol>';

        return $link;
    }

    /**
     * Returns an array with all roles ids that have the right to view the folder.
     * @return int[] Returns an array with all role ids that have the right to view the folder.
     */
    public function getRoleViewArrayOfFolder()
    {
        return $this->folderViewRolesObject->getRolesIds();
    }

    /**
     * Returns an array with all roles ids that have the right to upload files to the folder.
     * @return int[] Returns an array with all role ids that have the right to upload files to the folder.
     */
    public function getRoleUploadArrayOfFolder()
    {
        return $this->folderUploadRolesObject->getRolesIds();
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                           For text columns the format can be @b database that would return the original database value without any transformations
     * @return mixed Returns the value of the database column.
     *         If the value was manipulated before with @b setValue than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        $value = parent::getValue($columnName, $format);

        if ($columnName === 'fol_name')
        {
            // Convert HTML-entity back to letters
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        }

        return $value;
    }

    /**
     * Checks if the current user has the right to upload files to the current folder.
     * @return bool Return @b true if the user has the right to upload files
     */
    public function hasUploadRight()
    {
        global $gCurrentUser;

        return $this->folderUploadRolesObject->hasRight($gCurrentUser->getRoleMemberships()) || $gCurrentUser->editDownloadRight();
    }

    /**
     * Checks if the current user has the right to view files of the current folder.
     * @return bool Return @b true if the user has the right to view files
     */
    public function hasViewRight()
    {
        global $gCurrentUser;

        return $this->folderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships()) || $gCurrentUser->editDownloadRight();
    }

    /**
     * Reads a record out of the table in database selected by the conditions of the param @b $sqlWhereCondition out of the table.
     * If the sql will find more than one record the method returns @b false.
     * Per default all columns of the default table will be read and stored in the object.
     * @param string $sqlWhereCondition Conditions for the table to select one record
     * @return bool Returns @b true if one record is found
     * @see TableAccess#readDataById
     * @see TableAccess#readDataByColumns
     */
    protected function readData($sqlWhereCondition)
    {
        if (parent::readData($sqlWhereCondition))
        {
            $folId = (int) $this->getValue('fol_id');
            $this->folderViewRolesObject   = new RolesRights($this->db, 'folder_view', $folId);
            $this->folderUploadRolesObject = new RolesRights($this->db, 'folder_upload', $folId);

            return true;
        }

        return false;
    }

    /**
     * Benennt eine Ordnerinstanz um und sorgt dafür das bei allen Unterordnern der Pfad angepasst wird
     * @param string $newName
     * @param string $newPath
     * @param int $folderId
     */
    public function rename($newName, $newPath, $folderId = 0)
    {
        if ($folderId === 0)
        {
            $folderId = (int) $this->getValue('fol_id');
            $this->setValue('fol_name', $newName);
            $this->save();
        }

        $this->db->startTransaction();

        // Set new path in database for folderId
        $sqlUpdate = 'UPDATE '.TBL_FOLDERS.'
                         SET fol_path = \''.$newPath.'\'
                       WHERE fol_id = '.$folderId;
        $this->db->query($sqlUpdate);

        $subfoldersStatement = $this->getSubfolderStatement($folderId, array('fol_id', 'fol_name'));

        while ($rowSubfolders = $subfoldersStatement->fetchObject())
        {
            // recursive call with every subfolder
            $this->rename($rowSubfolders->fol_name, $newPath . '/' . $newName, $rowSubfolders->fol_id);
        }

        $this->db->endTransaction();
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the user, organization and timestamp will be set per default.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization, $gCurrentUser;

        if ($this->new_record)
        {
            $this->setValue('fol_timestamp', DATETIME_NOW);
            $this->setValue('fol_usr_id', $gCurrentUser->getValue('usr_id'));
            $this->setValue('fol_org_id', $gCurrentOrganization->getValue('org_id'));
        }

        return parent::save($updateFingerPrint);
    }

    /**
     * Create a unique folder name for the root folder of the download module that contains
     * the shortname of the current organization
     * @return string Returns the root foldername for the download module.
     */
    public static function getRootFolderName()
    {
        global $gCurrentOrganization;

        $replaceArray = array(
            ' '  => '_',
            '.'  => '_',
            ','  => '_',
            '\'' => '_',
            '"'  => '_',
            '´'  => '_',
            '`'  => '_'
        );
        $orgName = str_replace(array_keys($replaceArray), array_values($replaceArray), $gCurrentOrganization->getValue('org_shortname'));

        return 'download_' . strtolower($orgName);
    }
}
