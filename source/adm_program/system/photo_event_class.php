<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_photos
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Diese Klasse dient dazu ein Fotoveranstaltungsobjekt zu erstellen.
 * Eine Fotoveranstaltung kann ueber diese Klasse in der Datenbank verwaltet werden.
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $photo_event = new PhotoEvent($g_db);
 *
 * Mit der Funktion getPhotoEvent($pho_id) kann die gewuenschte Fotoveranstaltung 
 * ausgelesen werden. 
 *
 * Folgende Funktionen stehen weiter zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array
 * setValue($field_name, $field_value) 
 *                        - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save()                 - Veranstaltung wird mit den geaenderten Daten in die Datenbank
 *                          zurueckgeschrieben bwz. angelegt
 * delete()               - Die gewaehlte Veranstaltung wird aus der Datenbank geloescht
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

$absolute_path = substr(__FILE__, 0, strpos(__FILE__, "adm_program")-1);
require_once("$absolute_path/adm_program/system/table_access_class.php");

class PhotoEvent extends TableAccess
{
    // Konstruktor
    function PhotoEvent(&$db, $photo_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_PHOTOS;
        $this->column_praefix = "pho";
        $this->key_name       = "pho_id";
        
        if(is_numeric($photo_id))
        {
            $this->getPhotoEvent($photo_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Fotoevent mit der uebergebenen Foto-ID aus der Datenbank auslesen
    function getPhotoEvent($photo_id)
    {
        $this->readData($photo_id);
    }
    
    // interne Funktion, die bei setValue den uebergebenen Wert prueft
    // und ungueltige Werte auf leer setzt
    // die Funktion wird innerhalb von setValue() aufgerufen
    function checkValue($field_name, $field_value)
    {
        switch($field_name)
        {
            case "pho_id":
            case "pho_usr_id":
            case "pho_pho_id_parent":
            case "pho_usr_id_change":
                if(is_numeric($field_value) == false
                || $field_value == 0)
                {
                    $field_value = "";
                    return false;
                }
                break;

            case "pho_quantity":
                if(is_numeric($field_value) == false)
                {
                    $field_value = "";
                    return false;
                }
                break;            

            case "pho_locked":
                if($field_value != 1)
                {
                    $field_value = 0;
                    return false;
                }
                break; 
        }       
        return true;
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function initializeFields()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->db_fields[$this->key_name] > 0)
        {
            $this->db_fields['pho_last_change']   = date("Y-m-d H:i:s", time());
            $this->db_fields['pho_usr_id_change'] = $g_current_user->getValue("usr_id");
        }
        else
        {
            $this->db_fields['pho_timestamp']     = date("Y-m-d H:i:s", time());
            $this->db_fields['pho_usr_id']        = $g_current_user->getValue("usr_id");
            $this->db_fields['pho_org_shortname'] = $g_current_organization->getValue("org_shortname");
        }
    }
    
    // interne Funktion, die die Fotoveranstaltung in Datenbank und File-System loeschen
    // die Funktion wird innerhalb von delete() aufgerufen
    function deleteReferences()
    {
        return $this->deleteInDatabase($this->db_fields['pho_id']);     
    }    

    // Rekursive Funktion die die uebergebene Veranstaltung und alle
    // Unterveranstaltungen loescht
    function deleteInDatabase($photo_id)
    {
        $return_code = true;
    
        // erst einmal rekursiv zur tiefsten Tochterveranstaltung gehen
        $sql     = "SELECT pho_id FROM ". TBL_PHOTOS. "
                     WHERE pho_pho_id_parent = $photo_id ";
        $result1 = $this->db->query($sql);
        
        while($row = $this->db->fetch_array($result1))
        {
            if($return_code)
            {
                error_log("row: ". $row[0]);
                $return_code = $this->deleteInDatabase($row['pho_id']);
            }
        }

        // nun DB-Eintrag und Ordner loeschen
        if($return_code)
        {
            //Ordnerpfad zusammensetzen
            $folder = SERVER_PATH. "/adm_my_files/photos/".$this->db_fields['pho_begin']."_$photo_id";
            
            // aktuellen Ordner incl. Unterordner und Dateien loeschen, falls er existiert
            if(file_exists($folder))
            {
                // nun erst rekursiv den Ordner im Dateisystem loeschen
                $return_code = $this->deleteInFilesystem($folder);
    
                // nun noch den uebergebenen Ordner loeschen
                @chmod($folder, 0777);
                error_log($folder);
                if(@rmdir($folder) == false)
                {
                    return false;
                }
            }
        }
        
        return $return_code;
    }
    
    // Rekursive Funktion die alles innerhalb des uebergebenen
    // Ordners mit Unterordnern und allen Dateien loescht
    function deleteInFilesystem($folder)
    {
        $dh  = @opendir($folder);
        if($dh)
        {
            while (false !== ($filename = readdir($dh)))
            {
                if($filename != "." && $filename != "..")
                {
                    $act_folder_entry = "$folder/$filename";
                    
                    if(is_dir($act_folder_entry))
                    {
                        // nun den entsprechenden Ordner loeschen
                        $this->deleteInFilesystem($act_folder_entry);
                        @chmod($act_folder_entry, 0777);
                        if(@rmdir($act_folder_entry) == false)
                        {
                            return false;
                        }
                    }
                    else
                    {
                        // die Datei loeschen
                        if(file_exists($act_folder_entry))
                        {
                            @chmod($act_folder_entry, 0777);
                            if(@unlink($act_folder_entry) == false)
                            {
                                return false;
                            }
                        }
                    }
                }
            }
            closedir($dh);
        }
                    
        return true;
    }
}
?>