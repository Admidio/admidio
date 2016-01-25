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

/******************************************************************************
 * Diese Klasse dient dazu ein Folderobjekt zu erstellen.
 * Ein Ordner kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getFolderForDownload($folderId)
 *                         - Folder mit der uebergebenen ID aus der Datenbank
 *                           fuer das Downloadmodul auslesen
 * getFolderContentsForDownload()
 *                         - Inhalt des aktuellen Ordners, abhaengig von den
 *                           Benutzerrechten, als Array zurueckliefern
 * ...
 *
 *****************************************************************************/
class TableFolder extends TableAccess
{
    protected $folderPath;

    /**
     * Constructor that will create an object of a recordset of the table adm_folders.
     * If the id is set than the specific folder will be loaded.
     * @param object $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int    $folderId The recordset of the folder with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $folderId = 0)
    {
        parent::__construct($database, TBL_FOLDERS, 'fol', $folderId);

        $this->folderPath = new Folder();
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

        // In der DB die verknuepften Berechtigungen zu dieser Folder_ID loeschen...
        $sql_delete_fol_rol = 'DELETE FROM '.TBL_FOLDER_ROLES.'
                                WHERE flr_fol_id = '.$folderId;
        $this->db->query($sql_delete_fol_rol);

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
     * @param bool $public_flag If set to @b 1 then all users could see this folder.
     * @param int  $folderId    The id of the folder where the public flag should be set.
     */
    public function editPublicFlagOnFolder($public_flag, $folderId = 0)
    {
        if ($folderId === 0)
        {
            $folderId = $this->getValue('fol_id');
            $this->setValue('fol_public', $public_flag);
        }

        // Alle Unterordner auslesen, die im uebergebenen Verzeichnis enthalten sind
        $sql_subfolders = 'SELECT *
                             FROM '.TBL_FOLDERS.'
                            WHERE fol_fol_id_parent = '.$folderId;
        $subfoldersStatement = $this->db->query($sql_subfolders);

        while($row_subfolders = $subfoldersStatement->fetchObject())
        {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->editPublicFlagOnFolder($public_flag, $row_subfolders->fol_id);
        }

        // Jetzt noch das Flag in der DB setzen fuer die aktuelle folder_id...
        $sql_update = 'UPDATE '.TBL_FOLDERS.'
                          SET fol_public = \''.$public_flag.'\'
                        WHERE fol_id = '.$folderId;
        $this->db->query($sql_update);

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
            $condition = '     fol_id     = '.$folderId.'
                           AND fol_type   = \'DOWNLOAD\'
                           AND fol_org_id = '. $gCurrentOrganization->getValue('org_id');
            parent::readData($condition);
        }
        else
        {
            $condition = '     fol_name   = \'download\'
                           AND fol_type   = \'DOWNLOAD\'
                           AND fol_path   = \'/adm_my_files\'
                           AND fol_org_id = '. $gCurrentOrganization->getValue('org_id');
            parent::readData($condition);
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
                // Wenn der Ordner nicht public ist und der Benutzer keine DownloadAdminrechte hat, muessen die Rechte untersucht werden
                $sql_rights = 'SELECT COUNT(*) as count
                                 FROM '.TBL_FOLDER_ROLES.'
                           INNER JOIN '.TBL_MEMBERS.'
                                   ON mem_rol_id = flr_rol_id
                                WHERE flr_fol_id = '.$this->getValue('fol_id').'
                                  AND mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
                                  AND mem_begin <= \''.DATE_NOW.'\'
                                  AND mem_end    > \''.DATE_NOW.'\'';
                $rightsStatement = $this->db->query($sql_rights);
                $rowRights = $rightsStatement->fetch();

                // Falls der User in keiner Rolle Mitglied ist, die Rechte an dem Ordner besitzt
                // wird auch kein Ordner geliefert.
                if ($rowRights['count'] === '0')
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
     */
    public function getFolderContentsForDownload()
    {
        global $gCurrentOrganization, $gCurrentUser, $gValidLogin;

        // RueckgabeArray initialisieren
        $completeFolder = null;

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

                // Gucken ob der angemeldete Benutzer Rechte an dem Unterordner hat...
                $sql_rights = 'SELECT COUNT(*) as count
                                 FROM '.TBL_FOLDER_ROLES.'
                           INNER JOIN '.TBL_MEMBERS.'
                                   ON mem_rol_id = flr_rol_id
                                WHERE flr_fol_id = '.$row_folders->fol_id.'
                                  AND mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
                                  AND mem_begin <= \''.DATE_NOW.'\'
                                  AND mem_end    > \''.DATE_NOW.'\'';
                $rightsStatement = $this->db->query($sql_rights);
                $rowRights = $rightsStatement->fetch();

                // Falls der User in mindestens einer Rolle Mitglied ist, die Rechte an dem Ordner besitzt
                // wird der Ordner natuerlich ins Array gepackt.
                if ($rowRights['count'] !== '0')
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
            // pruefen ob der Ordner wirklich existiert
            if (file_exists($this->getCompletePathOfFolder()))
            {

                $fileHandle = opendir($this->getCompletePathOfFolder());
                if($fileHandle)
                {
                    while($file = readdir($fileHandle))
                    {
                        if ($file === '.' || $file === '..' || substr($file, 0, 1) === '.')
                        {
                            continue;
                        }
                        else
                        {
                            // Gucken ob Datei oder Ordner
                            $fileFolderPath = $this->getCompletePathOfFolder(). '/'. $file;

                            if(is_dir($fileFolderPath))
                            {
                                $alreadyAdded = false;

                                // Gucken ob das Verzeichnis bereits bei den regurlären Files dabei ist.
                                if (isset($completeFolder['folders']))
                                {
                                    for($i=0; $i<count($completeFolder['folders']); ++$i)
                                    {
                                        $nextFolder = $completeFolder['folders'][$i];

                                        if ($nextFolder['fol_name'] == $file)
                                        {
                                            $alreadyAdded = true;
                                        }
                                    }
                                }

                                // wenn nicht bereits enthalten wird es nun hinzugefuegt
                                if (!$alreadyAdded)
                                {
                                    $completeFolder['additionalFolders'][] = array('fol_name' => $file);
                                }
                            }
                            elseif (is_file($fileFolderPath))
                            {
                                $alreadyAdded = false;

                                // Gucken ob die Datei bereits bei den regurlären Files dabei ist.
                                if (isset($completeFolder['files']))
                                {
                                    for($i=0; $i<count($completeFolder['files']); ++$i)
                                    {
                                        $nextFile = $completeFolder['files'][$i];

                                        if ($nextFile['fil_name'] == $file)
                                        {
                                            $alreadyAdded = true;
                                        }
                                    }
                                }

                                // wenn nicht bereits enthalten wird es nun hinzugefuegt
                                if (!$alreadyAdded)
                                {
                                    // compute filesize
                                    $fileSize = round(filesize($fileFolderPath)/1024);

                                    $completeFolder['additionalFiles'][] = array('fil_name' => $file, 'fil_size' => $fileSize);
                                }
                            }
                        }
                    }

                   closedir($fileHandle);
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
        $completePath = SERVER_PATH. $folderPath. '/'. $folderName;

        return $completePath;
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
                                    WHERE fol_name   = \'download\'
                                      AND fol_type   = \'DOWNLOAD\'
                                      AND fol_path   = \'/adm_my_files\'
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
    }

    /**
     * Creates an array with all roles ids that have the right to view the folder.
     * If you need also the name of the folder then set the parameter to true.
     * @param bool $readRolesName In addition to the id also read the name of the role and return them.
     * @return array Returns an array with all roles ids that have the right to view the folder.
     */
    public function getRoleArrayOfFolder($readRolesName = false)
    {
        // RueckgabeArray initialisieren
        $roleArray = array();

        // Erst einmal die aktuellen Rollenberechtigungen fuer den Ordner auslesen
        $sql_rolset = 'SELECT *
                         FROM '.TBL_FOLDER_ROLES.'
                   INNER JOIN '.TBL_ROLES.'
                           ON rol_id = flr_rol_id
                        WHERE flr_fol_id = '.$this->getValue('fol_id');
        $rolesetStatement = $this->db->query($sql_rolset);

        while($rowRoleset = $rolesetStatement->fetchObject())
        {
            if($readRolesName)
            {
                // Jede Rolle wird nun dem Array hinzugefuegt
                $roleArray[] = array(
                                    'rol_id'   => $rowRoleset->rol_id,
                                    'rol_name' => $rowRoleset->rol_name);
            }
            else
            {
                $roleArray[] = $rowRoleset->rol_id;
            }
        }

        return $roleArray;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                           For text columns the format can be @b database that would return the original database value without any transformations
     * @return Returns the value of the database column.
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

    /**
     * Setzt Berechtigungen fuer Rollen auf einer vorhandenen Ordnerinstanz
     * und all seinen Unterordnern rekursiv
     * @param array $rolesArray
     * @param int   $folderId
     */
    public function setRolesOnFolder($rolesArray, $folderId = 0)
    {
        if ($folderId === 0)
        {
            $folderId = $this->getValue('fol_id');
        }

        $this->db->startTransaction();

        // Alle Unterordner auslesen, die im uebergebenen Ordner enthalten sind
        $sql_subfolders = 'SELECT *
                             FROM '.TBL_FOLDERS.'
                            WHERE fol_fol_id_parent = '.$folderId;
        $subfoldersStatement = $this->db->query($sql_subfolders);

        while($row_subfolders = $subfoldersStatement->fetchObject())
        {
            // rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->setRolesOnFolder($rolesArray, $row_subfolders->fol_id);
        }

        // Erst die alten Berechtigungen loeschen fuer die aktuelle OrdnerId
        $sql_delete = 'DELETE FROM '.TBL_FOLDER_ROLES.'
                             WHERE flr_fol_id = '.$folderId;
        $this->db->query($sql_delete);

        // Jetzt die neuen Berechtigungen schreiben
        if (count($rolesArray) > 0)
        {
            foreach($rolesArray as $rolesId)
            {
                $sql_insert = 'INSERT INTO '.TBL_FOLDER_ROLES.' (flr_fol_id, flr_rol_id)
                                    VALUES ('. $folderId. ', '. $rolesId. ')';
                $this->db->query($sql_insert);
            }
        }

        $this->db->endTransaction();
    }
}
