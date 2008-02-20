<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_folders
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Folderobjekt zu erstellen.
 * Ein Ordner kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $folder = new File($g_db);
 *
 * Mit der Funktion getFolder($folder_id) kann nun alle Informationen zum Folder
 * aus der Db ausgelesen werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array
 * setValue($field_name, $field_value) - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save()                 - File wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben bwz. angelegt
 * delete()               - Das aktuelle File wird aus der Datenbank geloescht
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/table_access_class.php");

class File extends TableAccess
{
    // Konstruktor
    function Folder(&$db, $folder_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_FOLDERS;
        $this->column_praefix = "fol";

        if($folder_id > 0)
        {
            $this->getFolder($folder_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Folder mit der uebergebenen ID aus der Datenbank auslesen
    function getFolder($folder_id)
    {
        global $g_current_organization;

        $condition = "     fol_id     = $folder_id
                       AND fol_org_id = ". $g_current_organization->getValue("org_id");
        $this->readData($folder_id, $condition);
    }

	// Folder mit der uebergebenen ID aus der Datenbank fuer das Downloadmodul auslesen
    function getFolderForDownload($folder_id)
    {
        global $g_current_organization, $g_current_user;

        $condition = "     fol_id     = $folder_id
        			   AND fol_type   = 'DOWNLOAD'
                       AND fol_org_id = ". $g_current_organization->getValue("org_id");
        $this->readData($folder_id, $condition);

        //Gucken ob ueberhaupt ein Datensatz gefunden wurde...
        if ($this->getValue('fil_id'))
        {
	        //Falls der Ordner gelocked ist und der User keine Downloadadminrechte hat, bekommt er nix zu sehen..
	        if (!$g_current_user->editDownloadRight() && $this->getValue("fol_locked"))
	        {
	        	$this->clear();
	        }
	        else if (!$this->getValue("fol_public"))
	        {
	        	//Wenn der Ordner nicht public ist, muessen die Rechte untersucht werden
	        	$sql_rights = "SELECT count(*)
                         FROM ". TBL_FOLDER_ROLES. ", ". TBL_MEMBERS. "
                        WHERE flr_fol_id		= ". $this->getValue("fol_id"). "
                          AND flr_rol_id 		= mem_rol_id
                          AND mem_usr_id 		= ". $g_current_user->getValue("usr_id"). "
                          AND mem_valid 		= 1";
        		$result_rights = $this->db->query($sql_rights);
        		$row_rights = $g_db->fetch_array($result_rights);
        		$row_count  = $row_rights[0];

        		//Falls der User in keiner Rolle Mitglied ist, die Rechte an dem Ordner besitzt
        		//wird auch kein Ordner geliefert.
        		if ($row_count == 0)
        		{
        			$this->clear();
        		}

	        }
        }
    }

    // Folder mit der uebergebenen ID aus der Datenbank auslesen
    function getFolderContentsForDownload()
	{
        global $g_current_organization, $g_current_user;

        //Erst einmal alle Unterordner auslesen, die in diesem Verzeichnis enthalten sind
        $sql_folders = "SELECT *
                         FROM ". TBL_FOLDERS. "
                        WHERE fol_type 			= 'DOWNLOAD'
                          AND fol_fol_id_parent = ". $this->getValue("fol_id"). "
                          AND fol_org_id 		= ". $g_current_organization->getValue("org_id"). "
        				ORDER BY fol_name";
        $result_folders = $this->db->query($sql_folders);

        //Nun alle Dateien auslesen, die in diesem Verzeichnis enthalten sind
        $sql_files   = "SELECT *
                         FROM ". TBL_FILES. "
                        WHERE fil_fol_id        = ". $this->getValue("fol_id"). "
        				ORDER BY fil_name";
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
			else if ($g_current_user->editDownloadRight())
			{
				//Falls der User editDownloadRechte hat, bekommt er den Ordner natuerlich auch zu sehen
				$addToArray = true;
			}
			else
			{
				//Gucken ob der angemeldete Benutzer Rechte an dem Unterordner hat...
				$sql_rights = "SELECT count(*)
                         FROM ". TBL_FOLDER_ROLES. ", ". TBL_MEMBERS. "
                        WHERE flr_fol_id		= ". $row_folders->fol_id. "
                          AND flr_rol_id 		= mem_rol_id
                          AND mem_usr_id 		= ". $g_current_user->getValue("usr_id"). "
                          AND mem_valid 		= 1";
        		$result_rights = $this->db->query($sql_rights);
        		$row_rights = $g_db->fetch_array($result_rights);
        		$row_count  = $row_rights[0];

        		//Falls der User in mindestens einer Rolle Mitglied ist, die Rechte an dem Ordner besitzt
        		//wird der Ordner natuerlich ins Array gepackt.
        		if ($row_count > 0)
        		{
        			$addToArray;
        		}

			}

			if ($addToArray)
			{
				$completeFolder["folders"][] = array(
								'fol_id'        => $row_folders->fol_id,
								'fol_name'      => $row_folders->fol_name,
								'fol_public'    => $row_folders->fol_public,
								'fol_locked'    => $row_folders->fol_locked
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
			else if ($g_current_user->editDownloadRight())
			{
				//Falls der User editDownloadRechte hat, bekommt er das File natürlich auch zu sehen
				$addToArray = true;
			}

			if ($addToArray)
			{
				$completeFolder["files"][] = array(
								'fil_id'        => $row_files->fil_id,
								'fil_name'      => $row_files->fil_name,
								'fil_locked'    => $row_files->fil_locked,
								'fil_counter'   => $row_files->fil_counter
				);
			}
		}


        // Das Array mit dem Ordnerinhalt zurueckgeben
        return $completeFolder;
    }

    // die Methode wird innerhalb von delete() aufgerufen und entsorgt die Referenzen des Datensatzes...
    function _delete()
    {
		//TODO: Hier muss noch ein "bisschen" Code rein, der alle untergeordneten Datein und Unterordner mit Inhalt und Berechtigungen löscht
    }

    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function _save()
    {
        global $g_current_organization, $g_current_user;

        if($this->new_record)
        {
            $this->setValue("fol_timestamp", date("Y-m-d H:i:s", time()));
            $this->setValue("fol_usr_id", $g_current_user->getValue("usr_id"));
        }

    }
}
?>