<?php
/******************************************************************************
 * Class manages access to database table adm_folders
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Folderobjekt zu erstellen.
 * Ein Ordner kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getFolderForDownload($folder_id)
 *                         - Folder mit der uebergebenen ID aus der Datenbank
 *                           fuer das Downloadmodul auslesen
 * getFolderContentsForDownload()
 *                         - Inhalt des aktuellen Ordners, abhaengig von den
 *                           Benutzerrechten, als Array zurueckliefern
 * ...
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');
require_once(SERVER_PATH. '/adm_program/system/classes/table_roles.php');
require_once(SERVER_PATH. '/adm_program/system/classes/folder.php');

class TableFolder extends TableAccess
{
    protected $folderPath;

    // Konstruktor
    public function __construct(&$db, $folder_id = 0)
    {
        parent::__construct($db, TBL_FOLDERS, 'fol', $folder_id);
        
        $this->folderPath = new Folder();
    }


    // Folder mit der uebergebenen ID aus der Datenbank auslesen
    public function readData($folder_id, $sql_where_condition = '', $sql_additional_tables = '')
    {
        global $gCurrentOrganization;

        if(strlen($sql_where_condition) == 0)
        {
            $sql_where_condition = '    fol_id     = '.$folder_id.'
                                    AND fol_type   = \'DOWNLOAD\'
                                    AND fol_org_id = '. $gCurrentOrganization->getValue('org_id');
        }
        return parent::readData($folder_id, $sql_where_condition, $sql_additional_tables);
    }


    // Folder mit der uebergebenen ID aus der Datenbank fuer das Downloadmodul auslesen
	// Rueckgabe: -2 = keine Rechte; -1 = Ordner existiert nicht; 1 = alles OK
    public function getFolderForDownload($folder_id)
    {
        global $gCurrentOrganization, $gCurrentUser, $gValidLogin;
		
		$returnValue = -1;

        if ($folder_id > 0) 
        {
            $condition = '     fol_id     = '.$folder_id.'
                           AND fol_type   = \'DOWNLOAD\'
                           AND fol_org_id = '. $gCurrentOrganization->getValue('org_id');
            parent::readData($folder_id, $condition);
        }
        else 
        {
            $condition = '     fol_name   = \'download\'
                           AND fol_type   = \'DOWNLOAD\'
                           AND fol_path   = \'/adm_my_files\'
                           AND fol_org_id = '. $gCurrentOrganization->getValue('org_id');
            parent::readData($folder_id, $condition);
        }



        //Gucken ob ueberhaupt ein Datensatz gefunden wurde...
        if ($this->getValue('fol_id'))
        {
            //Falls der Ordner gelocked ist und der User keine Downloadadminrechte hat, bekommt er nix zu sehen..
            if (!$gCurrentUser->editDownloadRight() && $this->getValue('fol_locked'))
            {
                $this->clear();
				$returnValue = -2;
            }
            else if (!$gValidLogin && !$this->getValue('fol_public'))
            {
                //Wenn der Ordner nicht public ist und der Benutzer nicht eingeloggt ist, bekommt er nix zu sehen..
                $this->clear();
				$returnValue = -2;
            }
            else if (!$gCurrentUser->editDownloadRight() && !$this->getValue('fol_public'))
            {
                //Wenn der Ordner nicht public ist und der Benutzer keine DownloadAdminrechte hat, muessen die Rechte untersucht werden
                $sql_rights = 'SELECT count(*)
                         FROM '. TBL_FOLDER_ROLES. ', '. TBL_MEMBERS. '
                        WHERE flr_fol_id = '. $this->getValue('fol_id'). '
                          AND flr_rol_id = mem_rol_id
                          AND mem_usr_id = '. $gCurrentUser->getValue('usr_id'). '
                          AND mem_begin <= \''.DATE_NOW.'\'
                          AND mem_end    > \''.DATE_NOW.'\'';
                $result_rights = $this->db->query($sql_rights);
                $row_rights = $this->db->fetch_array($result_rights);
                $row_count  = $row_rights[0];

                //Falls der User in keiner Rolle Mitglied ist, die Rechte an dem Ordner besitzt
                //wird auch kein Ordner geliefert.
                if ($row_count == 0)
                {
                    $this->clear();
					$returnValue = -2;
                }

				$returnValue = 1;
            }
			else
			{
				$returnValue = 1;
			}
        }
		return $returnValue;
    }


    // Inhalt des aktuellen Ordners, abhaengig von den Benutzerrechten, als Array zurueckliefern...
    public function getFolderContentsForDownload()
    {
        global $gCurrentOrganization, $gCurrentUser, $gValidLogin;

        //RueckgabeArray initialisieren
        $completeFolder = null;

        //Erst einmal alle Unterordner auslesen, die in diesem Verzeichnis enthalten sind
        $sql_folders = 'SELECT *
                         FROM '. TBL_FOLDERS. '
                        WHERE fol_type          = \'DOWNLOAD\'
                          AND fol_fol_id_parent = '. $this->getValue('fol_id'). '
                          AND fol_org_id        = '. $gCurrentOrganization->getValue('org_id'). '
                        ORDER BY fol_name';
        $result_folders = $this->db->query($sql_folders);

        //Nun alle Dateien auslesen, die in diesem Verzeichnis enthalten sind
        $sql_files   = 'SELECT *
                         FROM '. TBL_FILES. '
                        WHERE fil_fol_id = '. $this->getValue('fol_id'). '
                        ORDER BY fil_name';
        $result_files = $this->db->query($sql_files);

        //Nun alle Folders und Files in ein mehrdimensionales Array stopfen
        //angefangen mit den Ordnern:
        while($row_folders = $this->db->fetch_object($result_folders))
        {
            $addToArray = false;

            //Wenn der Ordner public ist und nicht gelocked ist, wird er auf jeden Fall ins Array gepackt
            if (!$row_folders->fol_locked && $row_folders->fol_public)
            {
                $addToArray = true;
            }
            else if ($gCurrentUser->editDownloadRight())
            {
                //Falls der User editDownloadRechte hat, bekommt er den Ordner natuerlich auch zu sehen
                $addToArray = true;
            }
            else if ($gValidLogin)
            {

                //Gucken ob der angemeldete Benutzer Rechte an dem Unterordner hat...
                $sql_rights = 'SELECT count(*)
                                 FROM '. TBL_FOLDER_ROLES. ', '. TBL_MEMBERS. '
                                WHERE flr_fol_id = '. $row_folders->fol_id. '
                                  AND flr_rol_id = mem_rol_id
                                  AND mem_usr_id = '. $gCurrentUser->getValue('usr_id'). '
                                  AND mem_begin <= \''.DATE_NOW.'\'
                                  AND mem_end    > \''.DATE_NOW.'\'';
                $result_rights = $this->db->query($sql_rights);
                $row_rights = $this->db->fetch_array($result_rights);
                $row_count  = $row_rights[0];

                //Falls der User in mindestens einer Rolle Mitglied ist, die Rechte an dem Ordner besitzt
                //wird der Ordner natuerlich ins Array gepackt.
                if ($row_count > 0)
                {
                    $addToArray = true;
                }

            }

            //Jetzt noch pruefen ob der Ordner physikalisch vorhanden ist
            if (file_exists(SERVER_PATH. $row_folders->fol_path. '/'. $row_folders->fol_name)) {
                $folderExists = true;
            }
            else {
                $folderExists = false;

                if ($gCurrentUser->editDownloadRight()) {
                    //falls der Ordner physikalisch nicht existiert wird er nur im Falle von AdminRechten dem Array hinzugefuegt
                    $addToArray = true;
                }
                else {
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

        //jetzt noch die Dateien ins Array packen:
        while($row_files = $this->db->fetch_object($result_files))
        {
            $addToArray = false;

            //Wenn das File nicht gelocked ist, wird es auf jeden Fall in das Array gepackt...
            if (!$row_files->fil_locked)
            {
                $addToArray = true;
            }
            else if ($gCurrentUser->editDownloadRight())
            {
                //Falls der User editDownloadRechte hat, bekommt er das File natürlich auch zu sehen
                $addToArray = true;
            }

            //Jetzt noch pruefen ob das File physikalisch vorhanden ist
            if (file_exists(SERVER_PATH. $this->getValue('fol_path'). '/'. $this->getValue('fol_name'). '/'. $row_files->fil_name)) {
                $fileExists = true;

                //Filegroesse ermitteln
                $fileSize = round(filesize(SERVER_PATH. $this->getValue('fol_path'). '/'. $this->getValue('fol_name'). '/'. $row_files->fil_name)/1024);
            }
            else {
                $fileExists = false;
                $fileSize   = 0;

                if ($gCurrentUser->editDownloadRight()) {
                    //falls das File physikalisch nicht existiert wird es nur im Falle von AdminRechten dem Array hinzugefuegt
                    $addToArray = true;
                }
                else {
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

        //Falls der User Downloadadmin ist, wird jetzt noch im physikalischen Verzeichnis geschaut,
        //ob Sachen drin sind die nicht in der DB sind...
        if ($gCurrentUser->editDownloadRight()) {

            //pruefen ob der Ordner wirklich existiert
            if (file_exists($this->getCompletePathOfFolder()))
            {

                $fileHandle    = opendir($this->getCompletePathOfFolder());
                if($fileHandle) {
                    while($file = readdir($fileHandle)) {
                        if ($file == '.' || $file == '..' || substr($file, 0, 1) == '.') {
                            continue;
                        }
                         else {

                            //Gucken ob Datei oder Ordner
                            if (is_dir($this->getCompletePathOfFolder(). '/'. $file)) {

                                $alreadyAdded = false;

                                //Gucken ob das Verzeichnis bereits bei den regurlären Files dabei ist.
                                if (isset($completeFolder['folders'])) {
                                    for($i=0; $i<count($completeFolder['folders']); $i++) {

                                        $nextFolder = $completeFolder['folders'][$i];

                                        if ($nextFolder['fol_name'] == $file) {

                                            $alreadyAdded = true;
                                        }

                                    }
                                }

                                if (!$alreadyAdded) {

                                    //wenn nicht bereits enthalten wird es nun hinzugefuegt
                                    $completeFolder['additionalFolders'][] = array('fol_name' => $file);
                                }

                            }
                            else if (is_file($this->getCompletePathOfFolder(). '/'. $file)) {

                                $alreadyAdded = false;

                                //Gucken ob die Datei bereits bei den regurlären Files dabei ist.
                                if (isset($completeFolder['files'])) {
                                    for($i=0; $i<count($completeFolder['files']); $i++) {

                                        $nextFile = $completeFolder['files'][$i];

                                        if ($nextFile['fil_name'] == $file) {

                                            $alreadyAdded = true;
                                        }

                                    }
                                }

                                if (!$alreadyAdded) {

                                    //wenn nicht bereits enthalten wird es nun hinzugefuegt
                                    $completeFolder['additionalFiles'][] = array('fil_name' => $file);
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


    //Gibt den kompletten Pfad des Ordners zurueck
    public function getCompletePathOfFolder()
    {
        //Pfad zusammen setzen
        $folderPath   = $this->getValue('fol_path');
        $folderName   = $this->getValue('fol_name');
        $completePath = SERVER_PATH. $folderPath. '/'. $folderName;

        return $completePath;
    }


    //Gibt fuer das Downloadmodul eine HTML-Navigationsleiste fuer die Ordner zurueck
    public function getNavigationForDownload($folderId = 0, $currentNavigation = '')
    {
        global $gCurrentOrganization, $g_root_path;

        $originalCall = false;

        if ($folderId == 0)
        {
            $originalCall = true;
            $folderId = $this->getValue('fol_id');
            $parentId = $this->getValue('fol_fol_id_parent');

            if ($parentId) {

                //wenn der Ordner einen Mutterordner hat muss der Rootordner ermittelt werden
                $sql_rootFolder = 'SELECT * FROM '. TBL_FOLDERS. '
                                        WHERE fol_name = \'download\'
                                       AND fol_type    = \'DOWNLOAD\'
                                       AND fol_path    = \'/adm_my_files\'
                                       AND fol_org_id  = '. $gCurrentOrganization->getValue('org_id');

                $result_rootFolder = $this->db->query($sql_rootFolder);
                $rootFolderRow = $this->db->fetch_object($result_rootFolder);

                $rootFolderId = $rootFolderRow->fol_id;

                $navigationPrefix =    '<a href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php?folder_id='. $rootFolderRow->fol_id. '">
                                         <img src="'.THEME_PATH.'/icons/application_view_list.png" alt="Downloads" /></a>
                                          <a href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php?folder_id='. $rootFolderRow->fol_id. '">Downloads</a>';

                $currentNavigation = $this->getNavigationForDownload($parentId, $currentNavigation);
            }
            else {

                //Wenn es keinen Elternordner gibt, wird auch keine Navigationsleite benoetigt
                return '';
            }
        }
        else
        {
            //Informationen zur uebergebenen OrdnerId aus der DB holen
            $sql_currentFolder = 'SELECT * FROM '. TBL_FOLDERS. '
                                   WHERE fol_id   = '.$folderId;

            $result_currentFolder = $this->db->query($sql_currentFolder);
            $currentFolderRow = $this->db->fetch_object($result_currentFolder);

            if ($currentFolderRow->fol_fol_id_parent) 
			{
                $currentNavigation = ' &gt; <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php?folder_id='.
                                       $currentFolderRow->fol_id. '">'. $currentFolderRow->fol_name. '</a>'. $currentNavigation;


                //naechster Aufruf mit ParentFolder
                return $this->getNavigationForDownload($currentFolderRow->fol_fol_id_parent, $currentNavigation);
            }
            else 
			{
                return $currentNavigation;
            }
        }

        if ($originalCall) 
		{
            $link = '<div class="navigationPath">'.$navigationPrefix.' '.$currentNavigation.' &gt; '. $this->getValue('fol_name'). '</div>';

            return $link;
        }
    }


    //Gibt die aktuellen Rollenbrechtigungen des Ordners als Array zurueck
    public function getRoleArrayOfFolder()
    {
        //RueckgabeArray initialisieren
        $roleArray = null;

        //Erst einmal die aktuellen Rollenberechtigungen fuer den Ordner auslesen
        $sql_rolset = 'SELECT * FROM '. TBL_FOLDER_ROLES. ', '. TBL_ROLES. '
                            WHERE flr_fol_id = '. $this->getValue('fol_id'). '
                              AND flr_rol_id = rol_id';

        $result_roleset = $this->db->query($sql_rolset);

        while($row_roleset = $this->db->fetch_object($result_roleset))
        {
            //Jede Rolle wird nun dem Array hinzugefuegt
            $roleArray[] = array(
                                'rol_id'        => $row_roleset->rol_id,
                                'rol_name'      => $row_roleset->rol_name);
        }

        return $roleArray;
    }


    // Setzt das Publicflag (0 oder 1) auf einer vorhandenen Ordnerinstanz
    // und all seinen Unterordnern rekursiv
    public function editPublicFlagOnFolder($public_flag, $folder_id = 0)
    {
        if ($folder_id == 0)
        {
            $folder_id = $this->getValue('fol_id');
            $this->setValue('fol_public', $public_flag);
        }

        //Alle Unterordner auslesen, die im uebergebenen Verzeichnis enthalten sind
        $sql_subfolders = 'SELECT *
                              FROM '. TBL_FOLDERS. '
                            WHERE fol_fol_id_parent = '.$folder_id;
        $result_subfolders = $this->db->query($sql_subfolders);

        while($row_subfolders = $this->db->fetch_object($result_subfolders))
        {
            //rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->editPublicFlagOnFolder($public_flag, $row_subfolders->fol_id);
        }

        //Jetzt noch das Flag in der DB setzen fuer die aktuelle folder_id...
        $sql_update = 'UPDATE '. TBL_FOLDERS. '
                          SET fol_public = \''.$public_flag.'\'
                        WHERE fol_id = '.$folder_id;
        $this->db->query($sql_update);

    }


    // Setzt Berechtigungen fuer Rollen auf einer vorhandenen Ordnerinstanz
    // und all seinen Unterordnern rekursiv
    public function setRolesOnFolder($rolesArray, $folder_id = 0)
    {
        if ($folder_id == 0)
        {
            $folder_id = $this->getValue('fol_id');
        }

		$this->db->startTransaction();
	
        //Alle Unterordner auslesen, die im uebergebenen Ordner enthalten sind
        $sql_subfolders = 'SELECT *
                              FROM '. TBL_FOLDERS. '
                            WHERE fol_fol_id_parent = '.$folder_id;
        $result_subfolders = $this->db->query($sql_subfolders);

        while($row_subfolders = $this->db->fetch_object($result_subfolders))
        {
            //rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->setRolesOnFolder($rolesArray, $row_subfolders->fol_id);
        }

        //Erst die alten Berechtigungen loeschen fuer die aktuelle OrdnerId
        $sql_delete  = 'DELETE FROM '. TBL_FOLDER_ROLES. '
                            WHERE flr_fol_id = '.$folder_id;
        $this->db->query($sql_delete);

        //Jetzt die neuen Berechtigungen schreiben
        if (count($rolesArray) > 0) {
            for($i=0; $i<count($rolesArray); $i++) {
                $sql_insert = 'INSERT INTO '. TBL_FOLDER_ROLES. ' (flr_fol_id, flr_rol_id)
                                  VALUES ('. $folder_id. ', '. $rolesArray[$i]['rol_id']. ')';
                $this->db->query($sql_insert);
            }
        }

		$this->db->endTransaction();
    }


    // Setzt das Lockedflag (0 oder 1) auf einer vorhandenen Ordnerinstanz
    // und allen darin enthaltenen Unterordnern und Dateien rekursiv
    public function editLockedFlagOnFolder($locked_flag, $folder_id = 0)
    {
        if ($folder_id == 0)
        {
            $folder_id = $this->getValue('fol_id');
            $this->setValue('fol_locked', $locked_flag);
        }
		
		$this->db->startTransaction();

        //Alle Unterordner auslesen, die im uebergebenen Verzeichnis enthalten sind
        $sql_subfolders = 'SELECT *
                              FROM '. TBL_FOLDERS. '
                            WHERE fol_fol_id_parent = '.$folder_id;
        $result_subfolders = $this->db->query($sql_subfolders);

        while($row_subfolders = $this->db->fetch_object($result_subfolders))
        {
            //rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->editLockedFlagOnFolder($locked_flag, $row_subfolders->fol_id);
        }

        //Jetzt noch das Flag in der DB setzen fuer die aktuelle folder_id...
        $sql_update = 'UPDATE '. TBL_FOLDERS. '
                          SET fol_locked = \''.$locked_flag.'\'
                        WHERE fol_id = '.$folder_id;
        $this->db->query($sql_update);

        //...und natuerlich auch fuer alle Files die in diesem Ordner sind
        $sql_update = 'UPDATE '. TBL_FILES. '
                          SET fil_locked = \''.$locked_flag.'\'
                        WHERE fil_fol_id = '.$folder_id;
        $this->db->query($sql_update);
		
		$this->db->endTransaction();
    }

    // Legt einen neuen Ordner im Dateisystem an
    public function createFolder($folderName)
    {
        $error = array('text' => '', 'path' => '');

        $this->folderPath->setFolder($this->getCompletePathOfFolder());
        $b_return = $this->folderPath->createFolder($folderName, true);

        if($this->folderPath->createFolder($folderName, true) == false)
        {
            $error['text'] = 'SYS_FOLDER_NOT_CREATED';
            $error['path'] = $this->getCompletePathOfFolder().'/'.$folderName;
        }

        return $error;
    }

    //benennt eine Ordnerinstanz um
    //und sorgt dafür das bei allen Unterordnern der Pfad angepasst wird
    public function rename($newName, $newPath, $folder_id = 0)
    {
		if ($folder_id == 0)
		{
			$folder_id = $this->getValue('fol_id');
			$this->setValue('fol_name', $newName);
			$this->save();
		}

		$this->db->startTransaction();

        //Den neuen Pfad in der DB setzen fuer die aktuelle folder_id...
        $sql_update = 'UPDATE '. TBL_FOLDERS. '
                          SET fol_path = \''.$newPath.'\'
                        WHERE fol_id = '.$folder_id;
        $this->db->query($sql_update);


        //Alle Unterordner auslesen, die im uebergebenen Verzeichnis enthalten sind
        $sql_subfolders = 'SELECT *
                              FROM '. TBL_FOLDERS. '
                            WHERE fol_fol_id_parent = '.$folder_id;
        $result_subfolders = $this->db->query($sql_subfolders);

        while($row_subfolders = $this->db->fetch_object($result_subfolders))
        {
            //rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->rename($row_subfolders->fol_name, $newPath. '/'. $newName, $row_subfolders->fol_id);
        }

		$this->db->endTransaction();
    }


    // die Methode wird innerhalb von delete() aufgerufen und entsorgt die Referenzen des Datensatzes
    // und loescht die Verzeichnisse auch physikalisch auf der Platte...
    public function delete($folder_id = 0)
    {
		$returnCode = true;
	
        if ($folder_id == 0)
        {
            $folder_id = $this->getValue('fol_id');

            if (!strlen($this->getValue('fol_name')) > 0) {
               return false;
            }
            $folderPath = $this->getCompletePathOfFolder();

        }
		
		$this->db->startTransaction();

        //Alle Unterordner auslesen, die im uebergebenen Verzeichnis enthalten sind
        $sql_subfolders = 'SELECT *
                              FROM '. TBL_FOLDERS. '
                            WHERE fol_fol_id_parent = '.$folder_id;
        $result_subfolders = $this->db->query($sql_subfolders);

        while($row_subfolders = $this->db->fetch_object($result_subfolders))
        {
            //rekursiver Aufruf mit jedem einzelnen Unterordner
            $this->delete($row_subfolders->fol_id);
        }

        //In der DB die Files der aktuellen folder_id loeschen
        $sql_delete_files = 'DELETE from '. TBL_FILES. '
                        WHERE fil_fol_id = '.$folder_id;
        $this->db->query($sql_delete_files);

        //In der DB die verknuepften Berechtigungen zu dieser Folder_ID loeschen...
        $sql_delete_fol_rol = 'DELETE from '. TBL_FOLDER_ROLES. '
                        WHERE flr_fol_id = '.$folder_id;
        $this->db->query($sql_delete_fol_rol);

        //In der DB den Eintrag des Ordners selber loeschen
        $sql_delete_folder = 'DELETE from '. TBL_FOLDERS. '
                        WHERE fol_id = '.$folder_id;
        $this->db->query($sql_delete_folder);


        //Jetzt noch das Verzeichnis physikalisch von der Platte loeschen
        if (isset($folderPath))
        {
            $this->folderPath->setFolder($folderPath);
            $this->folderPath->delete($folderPath);
        }

        //Auch wenn das physikalische Löschen fehl schlägt, wird in der DB alles gelöscht...

        if ($folder_id == $this->getValue('fol_id')) {
            $returnCode = parent::delete();
        }

		$this->db->endTransaction();
		return $returnCode;
    }

    public function getValue($field_name, $format = '')
    {
        $value = parent::getValue($field_name, $format);
        
        if($field_name == 'fol_name')
        {
            // Konvertiert HTML-Auszeichnungen zurück in Buchstaben 
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }

    // Methode, die Defaultdaten fur Insert und Update vorbelegt
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization, $gCurrentUser;

        if($this->new_record)
        {
            $this->setValue('fol_timestamp', DATETIME_NOW);
            $this->setValue('fol_usr_id', $gCurrentUser->getValue('usr_id'));
            $this->setValue('fol_org_id', $gCurrentOrganization->getValue('org_id'));
        }
        parent::save($updateFingerPrint);
    }
}
?>