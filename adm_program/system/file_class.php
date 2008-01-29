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
            $this->getWeblink($file_id);
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
        $this->readData($lnk_id, $condition, $tables);
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