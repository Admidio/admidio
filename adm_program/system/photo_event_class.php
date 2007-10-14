<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_photos
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
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
 * createFolder()         - erzeugt den entsprechenden Ordner unter adm_my_files/photos
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
        $this->auto_increment = true;
        
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
    function _setValue($field_name, $field_value)
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
    function _save()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->new_record)
        {
            $this->setValue("pho_timestamp", date("Y-m-d H:i:s", time()));
            $this->setValue("pho_usr_id", $g_current_user->getValue("usr_id"));
            $this->setValue("pho_org_shortname", $g_current_organization->getValue("org_shortname"));
        }
        else
        {
            $this->setValue("pho_last_change", date("Y-m-d H:i:s", time()));
            $this->setValue("pho_usr_id_change", $g_current_user->getValue("usr_id"));
        }
    }
    
    // interne Funktion, die die Fotoveranstaltung in Datenbank und File-System loeschen
    // die Funktion wird innerhalb von delete() aufgerufen
    function _delete()
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
                if(@rmdir($folder) == false)
                {
                    return false;
                }
            }

            if($return_code)
            {
                // Veranstaltung jetzt in DB loeschen            
                $sql = "DELETE FROM ". TBL_PHOTOS. "
                         WHERE pho_id = $photo_id ";
                $this->db->query($sql);
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
    
    // Legt den Ordner fuer die Veranstaltung im Dateisystem an
    function createFolder()
    {
        $error = array("code" => "0", "text" => "");
    
        if(is_writeable(SERVER_PATH. "/adm_my_files"))
        {
            if(file_exists(SERVER_PATH. "/adm_my_files/photos") == false)
            {
                // Ordner fuer die Fotos existiert noch nicht -> erst anlegen
                $b_return = @mkdir(SERVER_PATH. "/adm_my_files/photos", 0777);
                if($b_return)
                {
                    $b_return = @chmod(SERVER_PATH. "/adm_my_files/photos", 0777);
                }
                if($b_return == false)
                {
                    $error['code'] = "-2";
                    $error['text'] = "adm_my_files/photos";
                }
            }
            
            // nun den Ordner fuer die Veranstaltung anlegen
            $folder_name = $this->db_fields['pho_begin']. "_". $this->db_fields['pho_id'];
            
            $b_return = @mkdir(SERVER_PATH. "/adm_my_files/photos/$folder_name", 0777);
            if($b_return)
            {
                $b_return = @chmod(SERVER_PATH. "/adm_my_files/photos/$folder_name", 0777);
            }
            if($b_return == false)
            {
                $error['code'] = "-3";
                $error['text'] = "adm_my_files/photos/$folder_name";
            }
        }
        else
        {
            $error['code'] = "-1";
            $error['text'] = "adm_my_files";
        }
        return $error;
    }
}
?>