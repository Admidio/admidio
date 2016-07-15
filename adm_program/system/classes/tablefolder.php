<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_folders
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
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
     * @param int       $folderId The recordset of the folder with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $folderId = 0)
    {
        parent::__construct($database, TBL_FOLDERS, 'fol', $folderId);

        $this->folderPath = new Folder();
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
        if (count($rolesArray) === 0)
        {
            return;
        }

        if ($folderId === 0)
        {
            $folderId = $this->getValue('fol_id');
        }

        $this->db->startTransaction();

        // read all subfolders of the current folder
        $sql_subfolders = 'SELECT *
                             FROM '.TBL_FOLDERS.'
                            WHERE fol_fol_id_parent = '.$folderId;
        $subfoldersStatement = $this->db->query($sql_subfolders);

        while($row_subfolders = $subfoldersStatement->fetchObject())
        {
            // recursive call for every subfolder
            $this->addRolesOnFolder($rolesRightNameIntern, $rolesArray, $row_subfolders->fol_id);
        }

        // add new rights to folder
        $folderRolesRights = new RolesRights($this->db, $rolesRightNameIntern, $folderId);
        $folderRolesRights->addRoles($rolesArray);

        $this->db->endTransaction();
    }

    /**
     * Legt einen neuen Ordner im Dateisystem an
     * @param string $folderName
     * @return array
     */
    public function createFolder($folderName)
    {
        $error = array('text' => '', 'path' => '');

        $this->folderPath->setFolder($this->getCompletePathOfFolder());
        $this->folderPath->createFolder($folderName, true);

        if(!$this->folderPath->createFolder($folderName, true))
        {
            $error['text'] = 'SYS_FOLDER_NOT_CREATED';
            $error['path'] = $this->getCompletePathOfFolder().'/'.$folderName;
        }

        return $error;
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
        $returnCode = true;

        if ($folderId === 0)
        {
            $folderId = $this->getValue('fol_id');

            if (!strlen($this->getValue('fol_name')) > 0)
            {
                return false;
            }
            $folderPath = $this->getCompletePathOfFolder();

        }

        $this->db->startTransaction();

        // Alle Unterordner auslesen, die im uebergebenen Verzeichnis enthalten sind
        $sql_subfolders = 'SELECT *
                             FROM '.TBL_FOLDERS.'
                            WHERE fol_fol_id_parent = '.$folderId;
        $subfoldersStatement = $this->db->query($sql_subfolders);

        while($row_subfolders = $subfoldersStatement->fetchObject())
        {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->delete($row_subfolders->fol_id);
        }

        // In der DB die Files der aktuellen folder_id loeschen
        $sql_delete_files = 'DELETE FROM '.TBL_FILES.'
                              WHERE fil_fol_id = '.$folderId;
        $this->db->query($sql_delete_files);

        // delete all roles assignments that have the right to view this folder
        if($folderId == $this->getValue('fol_id'))
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
        $sql_delete_folder = 'DELETE FROM '.TBL_FOLDERS.'
                               WHERE fol_id = '.$folderId;
        $this->db->query($sql_delete_folder);

        // Jetzt noch das Verzeichnis physikalisch von der Platte loeschen
        if (isset($folderPath))
        {
            $this->folderPath->setFolder($folderPath);
            $this->folderPath->delete($folderPath);
        }

        // Auch wenn das physikalische Löschen fehl schlägt, wird in der DB alles gelöscht...

        if ($folderId == $this->getValue('fol_id'))
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
            $folderId = $this->getValue('fol_id');
            $this->setValue('fol_locked', $lockedFlag);
        }

        $this->db->startTransaction();

        // Alle Unterordner auslesen, die im uebergebenen Verzeichnis enthalten sind
        $sql_subfolders = 'SELECT *
                             FROM '.TBL_FOLDERS.'
                            WHERE fol_fol_id_parent = '.$folderId;
        $subfoldersStatement = $this->db->query($sql_subfolders);

        while($row_subfolders = $subfoldersStatement->fetchObject())
        {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->editLockedFlagOnFolder($lockedFlag, $row_subfolders->fol_id);
        }

        // Jetzt noch das Flag in der DB setzen fuer die aktuelle folder_id...
        $sql_update = 'UPDATE '.TBL_FOLDERS.'
                          SET fol_locked = \''.$lockedFlag.'\'
                        WHERE fol_id = '.$folderId;
        $this->db->query($sql_update);

        //...und natuerlich auch fuer alle Files die in diesem Ordner sind
        $sql_update = 'UPDATE '.TBL_FILES.'
                          SET fil_locked = \''.$lockedFlag.'\'
                        WHERE fil_fol_id = '.$folderId;
        $this->db->query($sql_update);

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
            $folderId = $this->getValue('fol_id');
            $this->setValue('fol_public', (int) $publicFlag);
        }

        // Alle Unterordner auslesen, die im uebergebenen Verzeichnis enthalten sind
        $sqlSubfolders = 'SELECT *
                             FROM '.TBL_FOLDERS.'
                            WHERE fol_fol_id_parent = '.$folderId;
        $subfoldersStatement = $this->db->query($sqlSubfolders);

        while($rowSubfolders = $subfoldersStatement->fetchObject())
        {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->editPublicFlagOnFolder($publicFlag, $rowSubfolders->fol_id);
        }

        // Jetzt noch das Flag in der DB setzen fuer die aktuelle folder_id...
        $sqlUpdate = 'UPDATE '.TBL_FOLDERS.'
                          SET fol_public = \''.(int) $publicFlag.'\'
                        WHERE fol_id = '.$folderId;
        $this->db->query($sqlUpdate);

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
            ' '       => '_',
            '.'       => '_',
            ','       => '_',
            '\''      => '_',
            '"'       => '_',
            '´'       => '_',
            '`'       => '_'
        );
        return 'download_'.strtolower(str_replace(array_keys($replaceArray), array_values($replaceArray), $gCurrentOrganization->getValue('org_shortname')));
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
        global $gCurrentOrganization, $gCurrentUser, $gValidLogin;

        if ($folderId > 0)
        {
            // get folder of the parameter
            $condition = ' fol_id     = '.$folderId.'
                       AND fol_type   = \'DOWNLOAD\' ';
            $this->readData($condition);
        }
        else
        {
            // get first folder of current organization
            $condition = ' fol_fol_id_parent IS NULL
                       AND fol_type   = \'DOWNLOAD\'
                       AND fol_org_id = '. $gCurrentOrganization->getValue('org_id');
            $this->readData($condition);
        }

        // Gucken ob ueberhaupt ein Datensatz gefunden wurde...
        if ($this->getValue('fol_id'))
        {
            // Falls der Ordner gelocked ist und der User keine Downloadadminrechte hat, bekommt er nix zu sehen..
            if (!$gCurrentUser->editDownloadRight() && $this->getValue('fol_locked'))
            {
                $this->clear();
                throw new AdmException('DOW_FOLDER_NO_RIGHTS');
            }
            elseif (!$gValidLogin && !$this->getValue('fol_public'))
            {
                // Wenn der Ordner nicht public ist und der Benutzer nicht eingeloggt ist, bekommt er nix zu sehen..
                $this->clear();
                throw new AdmException('DOW_FOLDER_NO_RIGHTS');
            }
            elseif (!$gCurrentUser->editDownloadRight() && !$this->getValue('fol_public'))
            {
                // check if user has a membership in a role that is assigned to the current folder
                if(!$this->folderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships()))
                {
                    $this->clear();
                    throw new AdmException('DOW_FOLDER_NO_RIGHTS');
                }

                return true;
            }
            else
            {
                return true;
            }
        }
        throw new AdmException('DOW_FOLDER_NOT_FOUND', $folderId);
    }

    /**
     * Inhalt des aktuellen Ordners, abhaengig von den Benutzerrechten, als Array zurueckliefern...
     * @return array
     */
    public function getFolderContentsForDownload()
    {
        global $gCurrentOrganization, $gCurrentUser, $gValidLogin;

        // RueckgabeArray initialisieren
        $completeFolder = array();

        // Erst einmal alle Unterordner auslesen, die in diesem Verzeichnis enthalten sind
        $sql_folders = 'SELECT *
                         FROM '.TBL_FOLDERS.'
                        WHERE fol_type          = \'DOWNLOAD\'
                          AND fol_fol_id_parent = '. $this->getValue('fol_id'). '
                          AND fol_org_id        = '. $gCurrentOrganization->getValue('org_id'). '
                     ORDER BY fol_name';
        $foldersStatement = $this->db->query($sql_folders);

        // Nun alle Folders und Files in ein mehrdimensionales Array stopfen
        // angefangen mit den Ordnern:
        while($row_folders = $foldersStatement->fetchObject())
        {
            $addToArray = false;

            // Wenn der Ordner public ist und nicht gelocked ist, wird er auf jeden Fall ins Array gepackt
            if (!$row_folders->fol_locked && $row_folders->fol_public)
            {
                $addToArray = true;
            }
            elseif ($gCurrentUser->editDownloadRight())
            {
                // Falls der User editDownloadRechte hat, bekommt er den Ordner natuerlich auch zu sehen
                $addToArray = true;
            }
            elseif ($gValidLogin)
            {
                // check if user has a membership in a role that is assigned to the current folder
                if($this->folderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships()))
                {
                    $addToArray = true;
                }
            }

            // Jetzt noch pruefen ob der Ordner physikalisch vorhanden ist
            if (file_exists(SERVER_PATH. $row_folders->fol_path. '/'. $row_folders->fol_name))
            {
                $folderExists = true;
            }
            else
            {
                $folderExists = false;

                if ($gCurrentUser->editDownloadRight())
                {
                    // falls der Ordner physikalisch nicht existiert wird er nur im Falle von AdminRechten dem Array hinzugefuegt
                    $addToArray = true;
                }
                else
                {
                    $addToArray = false;
                }
            }

            if ($addToArray)
            {
                $completeFolder['folders'][] = array(
                    'fol_id'          => $row_folders->fol_id,
                    'fol_name'        => $row_folders->fol_name,
                    'fol_description' => $row_folders->fol_description,
                    'fol_path'        => $row_folders->fol_path,
                    'fol_timestamp'   => $row_folders->fol_timestamp,
                    'fol_public'      => $row_folders->fol_public,
                    'fol_exists'      => $folderExists,
                    'fol_locked'      => $row_folders->fol_locked
                );
            }
        }

        // Nun alle Dateien auslesen, die in diesem Verzeichnis enthalten sind
        $sql_files = 'SELECT *
                        FROM '.TBL_FILES.'
                       WHERE fil_fol_id = '. $this->getValue('fol_id'). '
                    ORDER BY fil_name';
        $filesStatement = $this->db->query($sql_files);

        // jetzt noch die Dateien ins Array packen:
        while($row_files = $filesStatement->fetchObject())
        {
            $addToArray = false;

            // Wenn das File nicht gelocked ist, wird es auf jeden Fall in das Array gepackt...
            if (!$row_files->fil_locked)
            {
                $addToArray = true;
            }
            elseif ($gCurrentUser->editDownloadRight())
            {
                // Falls der User editDownloadRechte hat, bekommt er das File natürlich auch zu sehen
                $addToArray = true;
            }

            // Jetzt noch pruefen ob das File physikalisch vorhanden ist
            if (file_exists(SERVER_PATH. $this->getValue('fol_path'). '/'. $this->getValue('fol_name'). '/'. $row_files->fil_name))
            {
                $fileExists = true;

                // compute filesize
                $fileSize = round(filesize(SERVER_PATH. $this->getValue('fol_path'). '/'. $this->getValue('fol_name'). '/'. $row_files->fil_name)/1024);
            }
            else
            {
                $fileExists = false;
                $fileSize   = 0;

                if ($gCurrentUser->editDownloadRight())
                {
                    // falls das File physikalisch nicht existiert wird es nur im Falle von AdminRechten dem Array hinzugefuegt
                    $addToArray = true;
                }
                else
                {
                    $addToArray = false;
                }
            }

            if ($addToArray)
            {
                $completeFolder['files'][] = array(
                    'fil_id'          => $row_files->fil_id,
                    'fil_name'        => $row_files->fil_name,
                    'fil_description' => $row_files->fil_description,
                    'fil_timestamp'   => $row_files->fil_timestamp,
                    'fil_locked'      => $row_files->fil_locked,
                    'fil_exists'      => $fileExists,
                    'fil_size'        => $fileSize,
                    'fil_counter'     => $row_files->fil_counter
                );
            }
        }

        // Falls der User Downloadadmin ist, wird jetzt noch im physikalischen Verzeichnis geschaut,
        // ob Sachen drin sind die nicht in der DB sind...
        if ($gCurrentUser->editDownloadRight())
        {
            $folderPath = $this->getCompletePathOfFolder();
            // pruefen ob der Ordner wirklich existiert
            if (is_dir($folderPath))
            {
                $dirHandle = @opendir($folderPath);
                if($dirHandle)
                {
                    while (($entry = readdir($dirHandle)) !== false)
                    {
                        if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0)
                        {
                            continue;
                        }

                        // Gucken ob Datei oder Ordner
                        $entryFolderPath = $folderPath.'/'.$entry;

                        if(is_dir($entryFolderPath))
                        {
                            $alreadyAdded = false;

                            // Gucken ob das Verzeichnis bereits bei den regurlären Files dabei ist.
                            if (isset($completeFolder['folders']))
                            {
                                foreach ($completeFolder['folders'] as $folder)
                                {
                                    if ($folder['fol_name'] === $entry)
                                    {
                                        $alreadyAdded = true;
                                        break;
                                    }
                                }
                            }

                            // wenn nicht bereits enthalten wird es nun hinzugefuegt
                            if (!$alreadyAdded)
                            {
                                $completeFolder['additionalFolders'][] = array('fol_name' => $entry);
                            }
                        }
                        elseif (is_file($entryFolderPath))
                        {
                            $alreadyAdded = false;

                            // Gucken ob die Datei bereits bei den regurlären Files dabei ist.
                            if (isset($completeFolder['files']))
                            {
                                foreach ($completeFolder['files'] as $file)
                                {
                                    if ($file['fil_name'] === $entry)
                                    {
                                        $alreadyAdded = true;
                                        break;
                                    }
                                }
                            }

                            // wenn nicht bereits enthalten wird es nun hinzugefuegt
                            if (!$alreadyAdded)
                            {
                                // compute filesize
                                $fileSize = round(filesize($entryFolderPath)/1024);

                                $completeFolder['additionalFiles'][] = array('fil_name' => $entry, 'fil_size' => $fileSize);
                            }
                        }
                    }

                    closedir($dirHandle);
                }
            }
        }

        // Das Array mit dem Ordnerinhalt zurueckgeben
        return $completeFolder;
    }

    /**
     * Gibt den kompletten Pfad des Ordners zurueck
     * @return string
     */
    public function getCompletePathOfFolder()
    {
        // Pfad zusammen setzen
        $folderPath   = $this->getValue('fol_path');
        $folderName   = $this->getValue('fol_name');

        return SERVER_PATH. $folderPath. '/'. $folderName;
    }

    /**
     * Gibt fuer das Downloadmodul eine HTML-Navigationsleiste fuer die Ordner zurueck
     * @param int $folderId
     * @param string $currentNavigation
     * @return string
     */
    public function getNavigationForDownload($folderId = 0, $currentNavigation = '')
    {
        global $gCurrentOrganization, $g_root_path, $gL10n;

        $originalCall = false;

        if ($folderId === 0)
        {
            $originalCall = true;
            $parentId = $this->getValue('fol_fol_id_parent');

            if ($parentId)
            {

                // wenn der Ordner einen Mutterordner hat muss der Rootordner ermittelt werden
                $sql_rootFolder = 'SELECT *
                                     FROM '.TBL_FOLDERS.'
                                    WHERE fol_type   = \'DOWNLOAD\'
                                      AND fol_fol_id_parent IS NULL
                                      AND fol_org_id = '. $gCurrentOrganization->getValue('org_id');
                $rootFolderStatement = $this->db->query($sql_rootFolder);
                $rootFolderRow = $rootFolderStatement->fetchObject();

                $navigationPrefix = '
                <li><a class="btn" href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php?folder_id='. $rootFolderRow->fol_id. '"><img
                    src="'.THEME_PATH.'/icons/application_view_list.png" alt="Downloads" />'.$gL10n->get('DOW_DOWNLOADS').'</a></li>';

                $currentNavigation = $this->getNavigationForDownload($parentId, $currentNavigation);
            }
            else
            {
                // Wenn es keinen Elternordner gibt, wird auch keine Navigationsleite benoetigt
                return '';
            }
        }
        else
        {
            // Informationen zur uebergebenen OrdnerId aus der DB holen
            $sql_currentFolder = 'SELECT *
                                    FROM '.TBL_FOLDERS.'
                                   WHERE fol_id = '.$folderId;
            $currentFolderStatement = $this->db->query($sql_currentFolder);
            $currentFolderRow = $currentFolderStatement->fetchObject();

            if ($currentFolderRow->fol_fol_id_parent)
            {
                $currentNavigation = '<li><a href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php?folder_id='.
                                       $currentFolderRow->fol_id. '">'. $currentFolderRow->fol_name. '</a></li>'. $currentNavigation;

                // naechster Aufruf mit ParentFolder
                return $this->getNavigationForDownload($currentFolderRow->fol_fol_id_parent, $currentNavigation);
            }
            else
            {
                return $currentNavigation;
            }
        }

        if ($originalCall)
        {
            $link = '
            <ol class="breadcrumb">'.
                $navigationPrefix.
                $currentNavigation.
                '<li>'. $this->getValue('fol_name'). '</li>
            </ol>';

            return $link;
        }
        return '';
    }

    /**
     * Returns an array with all roles ids that have the right to view the folder.
     * @return array Returns an array with all roles ids that have the right to view the folder.
     */
    public function getRoleViewArrayOfFolder()
    {
        return $this->folderViewRolesObject->getRolesIds();
    }

    /**
     * Returns an array with all roles ids that have the right to upload files to the folder.
     * @return array Returns an array with all roles ids that have the right to upload files to the folder.
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

        if($columnName === 'fol_name')
        {
            // Konvertiert HTML-Auszeichnungen zurück in Buchstaben
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        }

        return $value;
    }

    /**
     * Checks if the current user has the right to upload files to the current folder.
     * @return Return @b true if the user has the right to upload files
     */
    public function hasUploadRight()
    {
        global $gCurrentUser;

        if($this->folderUploadRolesObject->hasRight($gCurrentUser->getRoleMemberships()) || $gCurrentUser->editDownloadRight())
        {
            return true;
        }
        return false;
    }

    /**
     * Checks if the current user has the right to view files of the current folder.
     * @return Return @b true if the user has the right to view files
     */
    public function hasViewRight()
    {
        global $gCurrentUser;

        if($this->folderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships()) || $gCurrentUser->editDownloadRight())
        {
            return true;
        }
        return false;
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
        if(parent::readData($sqlWhereCondition))
        {
            $this->folderViewRolesObject = new RolesRights($this->db, 'folder_view', $this->getValue('fol_id'));
            $this->folderUploadRolesObject = new RolesRights($this->db, 'folder_upload', $this->getValue('fol_id'));
            return true;
        }

        return false;
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
        if (count($rolesArray) === 0)
        {
            return;
        }

        if ($folderId === 0)
        {
            $folderId = $this->getValue('fol_id');
        }

        $this->db->startTransaction();

        // read all subfolders of the current folder
        $sql_subfolders = 'SELECT *
                             FROM '.TBL_FOLDERS.'
                            WHERE fol_fol_id_parent = '.$folderId;
        $subfoldersStatement = $this->db->query($sql_subfolders);

        while($row_subfolders = $subfoldersStatement->fetchObject())
        {
            // recursive call for every subfolder
            $this->removeRolesOnFolder($rolesRightNameIntern, $rolesArray, $row_subfolders->fol_id);
        }

        // add new rights to folder
        $folderRolesRights = new RolesRights($this->db, $rolesRightNameIntern, $folderId);
        $folderRolesRights->removeRoles($rolesArray);

        $this->db->endTransaction();
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
            $folderId = $this->getValue('fol_id');
            $this->setValue('fol_name', $newName);
            $this->save();
        }

        $this->db->startTransaction();

        // Den neuen Pfad in der DB setzen fuer die aktuelle folder_id...
        $sql_update = 'UPDATE '.TBL_FOLDERS.'
                          SET fol_path = \''.$newPath.'\'
                        WHERE fol_id = '.$folderId;
        $this->db->query($sql_update);

        // Alle Unterordner auslesen, die im uebergebenen Verzeichnis enthalten sind
        $sql_subfolders = 'SELECT *
                             FROM '.TBL_FOLDERS.'
                            WHERE fol_fol_id_parent = '.$folderId;
        $subfoldersStatement = $this->db->query($sql_subfolders);

        while($row_subfolders = $subfoldersStatement->fetchObject())
        {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->rename($row_subfolders->fol_name, $newPath. '/'. $newName, $row_subfolders->fol_id);
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

        if($this->new_record)
        {
            $this->setValue('fol_timestamp', DATETIME_NOW);
            $this->setValue('fol_usr_id', $gCurrentUser->getValue('usr_id'));
            $this->setValue('fol_org_id', $gCurrentOrganization->getValue('org_id'));
        }
        return parent::save($updateFingerPrint);
    }
}
