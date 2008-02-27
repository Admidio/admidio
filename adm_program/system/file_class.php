<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_files
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Fileobjekt zu erstellen.
 * Eine Datei kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $file = new File($g_db);
 *
 * Mit der Funktion getFile($file_id) kann nun alle Informationen zum File
 * aus der Db ausgelsen werden.
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
    function File(&$db, $file_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_FILES;
        $this->column_praefix = "fil";

        if($file_id > 0)
        {
            $this->getFile($file_id);
        }
        else
        {
            $this->clear();
        }
    }


    // File mit der uebergebenen ID aus der Datenbank auslesen
    function getFile($file_id)
    {
        global $g_current_organization;

        $tables    = TBL_FOLDERS;
        $condition = "     fil_id     = $file_id
        		       AND fil_fol_id = fol_id
                       AND fol_org_id = ". $g_current_organization->getValue("org_id");
        $this->readData($file_id, $condition, $tables);
    }


	// File mit der uebergebenen ID aus der Datenbank auslesen fuer das Downloadmodul
	// Hier wird auch direkt ueberprueft ob die Datei oder der Ordner gesperrt ist.
    function getFileForDownload($file_id)
    {
        global $g_current_organization, $g_current_user;

        $tables    = TBL_FOLDERS;
        $condition = "     fil_id     = $file_id
        		       AND fil_fol_id = fol_id
        		       AND fil_locked = 0
        		       AND fol_locked = 0
        		       AND fol_type   = 'DOWNLOAD'
                       AND fol_org_id = ". $g_current_organization->getValue("org_id");
        $this->readData($file_id, $condition, $tables);

        //Pruefen ob der aktuelle Benutzer Rechte an der Datei hat
        //Gucken ob ueberhaupt ein Datensatz gefunden wurde...
        if ($this->getValue('fil_id'))
        {
	        if (!$this->getValue("fol_public"))
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


    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function _save()
    {
        global $g_current_organization, $g_current_user;

        if($this->new_record)
        {
            $this->setValue("fil_timestamp", date("Y-m-d H:i:s", time()));
            $this->setValue("fil_usr_id", $g_current_user->getValue("usr_id"));
        }

    }
}
?>