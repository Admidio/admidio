<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_folders
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Diese Klasse dient dazu ein Folderobjekt zu erstellen.
 * Ein Ordner kann ueber diese Klasse in der Datenbank verwaltet werden
 */
class TableFolder extends TableAccess
{
    /**
     * @var Folder
     */
    protected $folderPath;
    /**
     * @var RolesRights|null Object with all roles that could view the current folder
     */
    protected $folderViewRolesObject;
    /**
     * @var RolesRights|null Object with all roles that could upload files the current folder
     */
    protected $folderUploadRolesObject;

    /**
     * Constructor that will create an object of a recordset of the table adm_folders.
     * If the id is set than the specific folder will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $folId    The recordset of the folder with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $folId = 0)
    {
        parent::__construct($database, TBL_FOLDERS, 'fol', $folId);
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $completeFolder
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function addAdditionalToFolderContents(array $completeFolder)
    {
        global $gCurrentUser;

        // If user hasn't adminDocumentsFiles, don't add more data
        if (!$gCurrentUser->adminDocumentsFiles()) {
            return $completeFolder;
        }

        // Check if folder exists
        $folderPath = $this->getFullFolderPath();
        if (!is_dir($folderPath)) {
            return $completeFolder;
        }

        // User has adminDocumentsFiles and folder exists, so lookup the physical directory for items that aren't in the DB
        $dirHandle = @opendir($folderPath);
        if ($dirHandle) {
            while (($entry = readdir($dirHandle)) !== false) {
                if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                    continue;
                }

                $alreadyAdded = false;

                // Check if entry is folder or file
                $entryFolderPath = $folderPath . '/' . $entry;

                if (is_dir($entryFolderPath)) {
                    // Check if folder is already in the regular folders
                    foreach ($completeFolder['folders'] as $folder) {
                        if ($folder['fol_name'] === $entry) {
                            $alreadyAdded = true;
                            break;
                        }
                    }

                    // If isn't already in, add it
                    if (!$alreadyAdded) {
                        $completeFolder['additionalFolders'][] = array('fol_name' => $entry);
                    }
                } elseif (is_file($entryFolderPath)) {
                    // Check if file is already in the regular files
                    foreach ($completeFolder['files'] as $file) {
                        if ($file['fil_name'] === $entry) {
                            $alreadyAdded = true;
                            break;
                        }
                    }

                    // If isn't already in, add it
                    if (!$alreadyAdded) {
                        $completeFolder['additionalFiles'][] = array(
                            'fil_name' => $entry,
                            'fil_size' => filesize($entryFolderPath)
                        );
                    }
                }
            }

            closedir($dirHandle);
        }

        return $completeFolder;
    }

    /**
     * Add a new file or subfolder of the current folder to the database. If a folder will be added all files and
     * subfolders of this folder will be added recursively with this method. The configured rights for viewing and
     * uploading will be adopt to the subfolders.
     * @param string $newFolderFileName Name of the folder or file that should be added to the database.
     */
    public function addFolderOrFileToDatabase(string $newFolderFileName)
    {
        $newFolderFileName = urldecode($newFolderFileName);
        $newObjectPath = $this->getFullFolderPath() . '/' . $newFolderFileName;
        $folderId = (int) $this->getValue('fol_id');

        // check if a file or folder should be created
        if (is_file($newObjectPath)) {
            // add file to database
            $newFile = new TableFile($this->db);
            $newFile->setValue('fil_fol_id', $folderId);
            $newFile->setValue('fil_name', $newFolderFileName);
            $newFile->setValue('fil_locked', $this->getValue('fol_locked'));
            $newFile->setValue('fil_counter', 0);
            $newFile->save();

        } elseif (is_dir($newObjectPath)) {
            // add folder to database
            $newFolder = new TableFolder($this->db);
            $newFolder->setValue('fol_fol_id_parent', $folderId);
            $newFolder->setValue('fol_type', 'DOCUMENTS');
            $newFolder->setValue('fol_name', $newFolderFileName);
            $newFolder->setValue('fol_path', $this->getFolderPath());
            $newFolder->setValue('fol_locked', $this->getValue('fol_locked'));
            $newFolder->setValue('fol_public', $this->getValue('fol_public'));
            $newFolder->save();

            // get roles rights of parent folder
            $rightParentFolderView = new RolesRights($this->db, 'folder_view', $folderId);
            $newFolder->addRolesOnFolder('folder_view', $rightParentFolderView->getRolesIds());
            $rightParentFolderUpload = new RolesRights($this->db, 'folder_upload', $folderId);
            $newFolder->addRolesOnFolder('folder_upload', $rightParentFolderUpload->getRolesIds());

            // now look for all files and folder within that new folder and add them also to the database
            $dirHandle = @opendir($newObjectPath);
            if ($dirHandle) {
                while (($entry = readdir($dirHandle)) !== false) {
                    if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                        continue;
                    }

                    // call recursively
                    $newFolder->addFolderOrFileToDatabase($entry);
                }
                closedir($dirHandle);
            }
        }
    }

    /**
     * Add all roles of the array to the current folder and all of the subfolders. The
     * roles will be assigned to the right that was set through parameter $rolesRightNameIntern.
     * @param string         $rolesRightNameIntern Name of the right where the roles should be added
     * @param array<int,int> $rolesArray
     * @param bool           $recursive            If set to **true** than the rights will be set recursive to all subfolders
     */
    public function addRolesOnFolder($rolesRightNameIntern, array $rolesArray, $recursive = true)
    {
        $this->editRolesOnFolder('add', $rolesRightNameIntern, $rolesArray, $recursive);
    }

    /**
     * Legt einen neuen Ordner im Dateisystem an
     * @param string $folderName
     * @return null|array<string,string>
     */
    public function createFolder($folderName)
    {
        $baseFolder = $this->getFullFolderPath();

        try {
            FileSystemUtils::createDirectoryIfNotExists($baseFolder . '/' . $folderName);
        } catch (\RuntimeException $exception) {
            return array(
                'text' => 'SYS_FOLDER_NOT_CREATED',
                'path' => $baseFolder . '/' . $folderName
            );
        }

        return null;
    }

    /**
     * Deletes the selected record of the table and all references in other tables.
     * Also all files, subfolders and the selected folder will be deleted in the file system.
     * After that the class will be initialize.
     * @param int $folderId
     * @return bool **true** if no error occurred
     */
    public function delete($folderId = 0)
    {
        global $gLogger;

        $folId = (int) $this->getValue('fol_id');
        $folderPath = '';

        if ($folderId === 0) {
            if ($this->getValue('fol_name') === '') {
                return false;
            }

            $folderId = (int) $this->getValue('fol_id');
            $folderPath = $this->getFullFolderPath();
        }

        $this->db->startTransaction();

        $subfoldersStatement = $this->getSubfolderStatement($folderId);

        while ($rowFolId = (int) $subfoldersStatement->fetchColumn()) {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->delete($rowFolId);
        }

        // In der DB die Files der aktuellen folder_id loeschen
        $sqlDeleteFiles = 'DELETE FROM '.TBL_FILES.'
                            WHERE fil_fol_id = ? -- $folderId';
        $this->db->queryPrepared($sqlDeleteFiles, array($folderId));

        // delete all roles assignments that have the right to view this folder
        if ($folderId === $folId) {
            $this->folderViewRolesObject->delete();
            $this->folderUploadRolesObject->delete();
        } else {
            $folderViewRoles = new RolesRights($this->db, 'folder_view', $folderId);
            $folderViewRoles->delete();
            $folderUploadRoles = new RolesRights($this->db, 'folder_upload', $folderId);
            $folderUploadRoles->delete();
        }

        // In der DB den Eintrag des Ordners selber loeschen
        $sqlDeleteFolder = 'DELETE FROM '.TBL_FOLDERS.'
                             WHERE fol_id = ? -- $folderId';
        $this->db->queryPrepared($sqlDeleteFolder, array($folderId));

        // Jetzt noch das Verzeichnis physikalisch von der Platte loeschen
        if ($folderPath !== '') {
            try {
                FileSystemUtils::deleteDirectoryIfExists($folderPath, true);
            } catch (\RuntimeException $exception) {
                $gLogger->error('Could not delete directory!', array('directoryPath' => $folderPath));
                // TODO
            }
        }

        $returnCode = true;

        // Auch wenn das physikalische Löschen fehl schlägt, wird in der DB alles gelöscht...
        if ($folderId === $folId) {
            $returnCode = parent::delete();
        }

        $this->db->endTransaction();

        return $returnCode;
    }

    /**
     * Add all roles of the array to the current folder and all of the subfolders. The
     * roles will be assigned to the right that was set through parameter $rolesRightNameIntern.
     * @param string         $mode                 "mode" could be "add" or "remove"
     * @param string         $rolesRightNameIntern Name of the right where the roles should be added
     * @param array<int,int> $rolesArray
     * @param bool           $recursive            If set to **true** than the rights will be set recursive to all subfolders
     * @param int            $folderId             The folder id of the subfolder if this method is called recursive
     */
    private function editRolesOnFolder($mode, $rolesRightNameIntern, array $rolesArray, $recursive, $folderId = 0)
    {
        if (count($rolesArray) === 0) {
            return;
        }

        if ($folderId === 0) {
            $folderId = (int) $this->getValue('fol_id');
        }

        $this->db->startTransaction();

        if ($recursive) {
            $subfoldersStatement = $this->getSubfolderStatement($folderId);

            while ($folId = (int) $subfoldersStatement->fetchColumn()) {
                // recursive call for every subfolder
                $this->editRolesOnFolder($mode, $rolesRightNameIntern, $rolesArray, $recursive, $folId);
            }
        }

        // add new rights to folder
        $folderRolesRights = new RolesRights($this->db, $rolesRightNameIntern, $folderId);
        if ($mode === 'add') {
            $folderRolesRights->addRoles($rolesArray);
        } else {
            $folderRolesRights->removeRoles($rolesArray);
        }

        $this->db->endTransaction();
    }

    /**
     * Setzt das Lockedflag (0 oder 1) auf einer vorhandenen Ordnerinstanz
     * und allen darin enthaltenen Unterordnern und Dateien rekursiv
     * @param bool $lockedFlag
     * @param int  $folderId
     */
    public function editLockedFlagOnFolder($lockedFlag, $folderId = 0)
    {
        if ($folderId === 0) {
            $folderId = (int) $this->getValue('fol_id');
            $this->setValue('fol_locked', (int) $lockedFlag);
        }

        $this->db->startTransaction();

        $subfoldersStatement = $this->getSubfolderStatement($folderId);

        while ($folId = (int) $subfoldersStatement->fetchColumn()) {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->editLockedFlagOnFolder($lockedFlag, $folId);
        }

        // Jetzt noch das Flag in der DB setzen fuer die aktuelle folder_id...
        $sqlUpdate = 'UPDATE '.TBL_FOLDERS.'
                         SET fol_locked = ? -- $lockedFlag
                       WHERE fol_id = ? -- $folderId';
        $this->db->queryPrepared($sqlUpdate, array((int) $lockedFlag, $folderId));

        // ...und natuerlich auch fuer alle Files die in diesem Ordner sind
        $sqlUpdate = 'UPDATE '.TBL_FILES.'
                         SET fil_locked = ? -- $lockedFlag
                       WHERE fil_fol_id = ? -- $folderId';
        $this->db->queryPrepared($sqlUpdate, array((int) $lockedFlag, $folderId));

        $this->db->endTransaction();
    }

    /**
     * Set the public flag to a folder and all subfolders.
     * @param bool $publicFlag If set to **1** then all users could see this folder.
     * @param int  $folderId   The id of the folder where the public flag should be set.
     */
    public function editPublicFlagOnFolder($publicFlag, $folderId = 0)
    {
        if ($folderId === 0) {
            $folderId = (int) $this->getValue('fol_id');
            $this->setValue('fol_public', (int) $publicFlag);
        }

        $subfoldersStatement = $this->getSubfolderStatement($folderId);

        while ($folId = (int) $subfoldersStatement->fetchColumn()) {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->editPublicFlagOnFolder($publicFlag, $folId);
        }

        // Jetzt noch das Flag in der DB setzen fuer die aktuelle folder_id...
        $sqlUpdate = 'UPDATE '.TBL_FOLDERS.'
                         SET fol_public = ? -- $publicFlag
                       WHERE fol_id = ? -- $folderId';
        $this->db->queryPrepared($sqlUpdate, array((int) $publicFlag, $folderId));
    }

    /**
     * Gets the path of the folder (with folder-name)
     * @return string
     */
    public function getFolderPath()
    {
        return $this->getValue('fol_path') . '/' . $this->getValue('fol_name');
    }

    /**
     * Gets the absolute path of the folder (with folder-name)
     * @return string
     */
    public function getFullFolderPath()
    {
        return ADMIDIO_PATH . $this->getFolderPath();
    }

    /**
     * @return array<int,array<string,mixed>> All files with their properties
     */
    private function getFilesWithProperties()
    {
        global $gCurrentUser;

        $files = array();

        // Get all files of the current folder
        $sqlFiles = 'SELECT *
                       FROM '.TBL_FILES.'
                      INNER JOIN '.TBL_FOLDERS.' ON fol_id = fil_fol_id
                      WHERE fil_fol_id = ? -- $this->getValue(\'fol_id\')
                   ORDER BY fil_name';
        $filesStatement = $this->db->queryPrepared($sqlFiles, array((int) $this->getValue('fol_id')));

        // jetzt noch die Dateien ins Array packen:
        while ($rowFiles = $filesStatement->fetch()) {
            $filePath = $this->getFullFolderPath() . '/' . $rowFiles['fil_name'];
            $fileExists = is_file($filePath);

            $fileSize = 0;
            if ($fileExists) {
                $fileSize = filesize($filePath);
            }

            $addToArray = false;

            // If file exists and file isn't locked or user has adminDocumentsFiles, show it
            if (($fileExists && !$rowFiles['fil_locked']) || $gCurrentUser->adminDocumentsFiles()) {
                $addToArray = true;
            }

            if ($addToArray) {
                $files[] = array(
                    'fil_id'          => $rowFiles['fil_id'],
                    'fil_uuid'        => $rowFiles['fil_uuid'],
                    'fil_name'        => $rowFiles['fil_name'],
                    'fil_description' => $rowFiles['fil_description'],
                    'fil_timestamp'   => $rowFiles['fil_timestamp'],
                    'fil_locked'      => $rowFiles['fil_locked'],
                    'fil_exists'      => $fileExists,
                    'fil_size'        => $fileSize,
                    'fil_counter'     => $rowFiles['fil_counter'],
                    'fol_id'          => $rowFiles['fol_id'],
                    'fol_name'        => $rowFiles['fol_name'],
                    'fol_path'        => $rowFiles['fol_path']
                );
            }
        }

        return $files;
    }

    /**
     * Inhalt des aktuellen Ordners, abhaengig von den Benutzerrechten, als Array zurueckliefern...
     * @return array<string,array<int,array<string,mixed>>>
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
     * Reads the folder recordset from database table **adm_folders** and throws an
     * AdmException if the user has no right to see the folder or the folder id doesn't exists.
     * @param string $folderUuid The UUID of the folder. If the UUID is empty then the root folder will be shown.
     * @throws AdmException
     * @return true Returns **true** if everything is ok otherwise an AdmException is thrown.
     */
    public function getFolderForDownload($folderUuid)
    {
        global $gCurrentUser, $gValidLogin;

        if ($folderUuid !== '') {
            // get folder of the parameter
            $condition = ' fol_uuid   = ? -- $folderUuid
                       AND fol_type = \'DOCUMENTS\' ';
            $queryParams = array($folderUuid);
        } else {
            // get first folder of current organization
            $condition = ' fol_fol_id_parent IS NULL
                       AND fol_org_id = ? -- $GLOBALS[\'gCurrentOrgId\']
                       AND fol_type   = \'DOCUMENTS\' ';
            $queryParams = array($GLOBALS['gCurrentOrgId']);
        }
        $this->readData($condition, $queryParams);

        // Check if a dataset is found
        if ((int) $this->getValue('fol_id') === 0) {
            throw new AdmException('SYS_FOLDER_NOT_FOUND', array($folderUuid));
        }

        // If current user has download-admin-rights => allow
        if ($gCurrentUser->adminDocumentsFiles()) {
            return true;
        }

        // If folder is locked (and no download-admin-rights) => throw exception
        if ($this->getValue('fol_locked')) {
            $this->clear();
            throw new AdmException('SYS_FOLDER_NO_RIGHTS');
        }

        // If folder is public (and file is not locked) => allow
        if ($this->getValue('fol_public')) {
            return true;
        }

        // check if user has a membership in a role that is assigned to the current folder
        if ($this->folderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships())) {
            return true;
        }

        $this->clear();
        if ($gValidLogin) {
            throw new AdmException('SYS_FOLDER_NO_RIGHTS');
        } else {
            throw new AdmException('SYS_FOLDER_NO_FILES_VISITOR');
        }
    }

    /**
     * Create a unique folder name for the root folder of the download module that contains
     * the shortname of the current organization.
     * @param string $type                  The folder type of which the root should be determined.
     *                                      If no type is set than **documents** will be set.
     * @param string $organizationShortname The shortname of the organization for which the folder name should be returned
     *                                      If no shortname is set than shortname of the current organization will be set.
     * @return string Returns the root foldername for the download module.
     */
    public static function getRootFolderName($type = 'documents', $organizationShortname = '')
    {
        global $gCurrentOrganization;

        if ($organizationShortname === '') {
            $organizationShortname = $gCurrentOrganization->getValue('org_shortname');
        }

        $organizationShortname = FileSystemUtils::getSanitizedPathEntry($organizationShortname);

        return StringUtils::strToLower($type) . '_' . strtolower($organizationShortname);
    }

    /**
     * Returns an array with all roles ids that have the right to view the folder.
     * @return array<int,int> Returns an array with all role ids that have the right to view the folder.
     */
    public function getRoleViewArrayOfFolder()
    {
        return $this->folderViewRolesObject->getRolesIds();
    }

    /**
     * Returns an array with all roles ids that have the right to upload files to the folder.
     * @return array<int,int> Returns an array with all role ids that have the right to upload files to the folder.
     */
    public function getRoleUploadArrayOfFolder()
    {
        return $this->folderUploadRolesObject->getRolesIds();
    }

    /**
     * Return \PDOStatement with all subfolders of a parent folder id
     * @param int               $folderId Folder ID
     * @param array<int,string> $columns  The columns that should be in the statement
     * @return false|\PDOStatement SubfolderStatement with fol_id column
     */
    private function getSubfolderStatement($folderId, array $columns = array('fol_id'))
    {
        // select all subfolders of the current folder
        $sql = 'SELECT ' . implode(',', $columns) . '
                  FROM '.TBL_FOLDERS.'
                 WHERE fol_fol_id_parent = ? -- $folderId';

        return $this->db->queryPrepared($sql, array($folderId));
    }

    /**
     * @return array<int,array<string,mixed>> All sub-folders with their properties
     */
    private function getSubfoldersWithProperties()
    {
        global $gCurrentUser, $gValidLogin;

        // Get all subfolder of the current folder
        $sqlFolders = 'SELECT *
                         FROM '.TBL_FOLDERS.'
                        WHERE fol_type          = \'DOCUMENTS\'
                          AND fol_fol_id_parent = ? -- $this->getValue(\'fol_id\')
                          AND fol_org_id        = ? -- $GLOBALS[\'gCurrentOrgId\']
                     ORDER BY fol_name';
        $foldersStatement = $this->db->queryPrepared($sqlFolders, array((int) $this->getValue('fol_id'), $GLOBALS['gCurrentOrgId']));

        $folders = array();

        while ($rowFolders = $foldersStatement->fetch()) {
            $folderExists = is_dir(ADMIDIO_PATH . $rowFolders['fol_path'] . '/' . $rowFolders['fol_name']);

            $addToArray = false;

            // If user has adminDocumentsFiles, show it
            if ($gCurrentUser->adminDocumentsFiles()) {
                $addToArray = true;
            }
            // If user hasn't adminDocumentsFiles, only show if folder exists
            elseif ($folderExists) {
                // If folder is public and not locked, show it
                if ($rowFolders['fol_public'] && !$rowFolders['fol_locked']) {
                    $addToArray = true;
                }
                // If user has a membership in a role that is assigned to the current subfolder, show it
                elseif ($gValidLogin) {
                    $subfolderViewRolesObject = new RolesRights($this->db, 'folder_view', $rowFolders['fol_id']);

                    if ($subfolderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships())) {
                        $addToArray = true;
                    }
                }
            }

            if ($addToArray) {
                $folders[] = array(
                    'fol_id'          => $rowFolders['fol_id'],
                    'fol_uuid'        => $rowFolders['fol_uuid'],
                    'fol_name'        => $rowFolders['fol_name'],
                    'fol_description' => $rowFolders['fol_description'],
                    'fol_path'        => $rowFolders['fol_path'],
                    'fol_timestamp'   => $rowFolders['fol_timestamp'],
                    'fol_public'      => $rowFolders['fol_public'],
                    'fol_exists'      => $folderExists,
                    'fol_locked'      => $rowFolders['fol_locked']
                );
            }
        }

        return $folders;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return mixed Returns the value of the database column.
     *         If the value was manipulated before with **setValue** than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        $value = parent::getValue($columnName, $format);

        if ($columnName === 'fol_name') {
            // Convert HTML-entity back to letters
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        }

        return $value;
    }

    /**
     * Checks if the current user has the right to upload files to the current folder.
     * @return bool Return **true** if the user has the right to upload files
     */
    public function hasUploadRight()
    {
        global $gCurrentUser;

        return $this->folderUploadRolesObject->hasRight($gCurrentUser->getRoleMemberships()) || $gCurrentUser->adminDocumentsFiles();
    }

    /**
     * Checks if the current user has the right to view files of the current folder.
     * @return bool Return **true** if the user has the right to view files
     */
    public function hasViewRight()
    {
        global $gCurrentUser;

        return $this->folderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships()) || $gCurrentUser->adminDocumentsFiles();
    }

    /**
     * Reads a record out of the table in database selected by the conditions of the param **$sqlWhereCondition** out of the table.
     * If the sql will find more than one record the method returns **false**.
     * Per default all columns of the default table will be read and stored in the object.
     * @param string           $sqlWhereCondition Conditions for the table to select one record
     * @param array<int,mixed> $queryParams       The query params for the prepared statement
     * @return bool Returns **true** if one record is found
     * @see TableAccess#readDataById
     * @see TableAccess#readDataByUuid
     * @see TableAccess#readDataByColumns
     */
    protected function readData($sqlWhereCondition, array $queryParams = array())
    {
        if (parent::readData($sqlWhereCondition, $queryParams)) {
            $folId = (int) $this->getValue('fol_id');
            $this->folderViewRolesObject   = new RolesRights($this->db, 'folder_view', $folId);
            $this->folderUploadRolesObject = new RolesRights($this->db, 'folder_upload', $folId);

            return true;
        }

        return false;
    }

    /**
     * Remove all roles of the array from the current folder and all of the subfolders. The
     * roles will be removed from the right that was set through parameter $rolesRightNameIntern.
     * @param string         $rolesRightNameIntern Name of the right where the roles should be removed
     * @param array<int,int> $rolesArray
     * @param bool           $recursive            If set to **true** than the rights will be set recursive to all subfolders
     */
    public function removeRolesOnFolder($rolesRightNameIntern, array $rolesArray, $recursive = true)
    {
        $this->editRolesOnFolder('remove', $rolesRightNameIntern, $rolesArray, $recursive, 0);
    }

    /**
     * Benennt eine Ordnerinstanz um und sorgt dafür das bei allen Unterordnern der Pfad angepasst wird
     * @param string $newName
     * @param string $newPath
     * @param int $folderId
     */
    public function rename($newName, $newPath, $folderId = 0)
    {
        if ($folderId === 0) {
            $folderId = (int) $this->getValue('fol_id');
            $this->setValue('fol_name', $newName);
            $this->save();
        }

        $this->db->startTransaction();

        // Set new path in database for folderId
        $sqlUpdate = 'UPDATE '.TBL_FOLDERS.'
                         SET fol_path = ? -- $newPath
                       WHERE fol_id = ? -- $folderId';
        $this->db->queryPrepared($sqlUpdate, array($newPath, $folderId));

        $subfoldersStatement = $this->getSubfolderStatement($folderId, array('fol_id', 'fol_name'));

        while ($rowSubfolders = $subfoldersStatement->fetch()) {
            // recursive call with every subfolder
            $this->rename($rowSubfolders['fol_name'], $newPath . '/' . $newName, $rowSubfolders['fol_id']);
        }

        $this->db->endTransaction();
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the user, organization and timestamp will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        if ($this->newRecord) {
            $this->setValue('fol_timestamp', DATETIME_NOW);
            $this->setValue('fol_usr_id', $GLOBALS['gCurrentUserId']);
            $this->setValue('fol_org_id', $GLOBALS['gCurrentOrgId']);
        }

        return parent::save($updateFingerPrint);
    }
}
