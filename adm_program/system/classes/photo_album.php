<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_photos
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Fotoveranstaltungsobjekt zu erstellen.
 * Eine Fotoveranstaltung kann ueber diese Klasse in der Datenbank verwaltet werden.
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * deleteInDatabase($photo_id) - Rekursive Funktion die die uebergebene Veranstaltung
 *                               und alle Unterveranstaltungen loescht
 * deleteInFilesystem($folder) - Rekursive Funktion die alles innerhalb des uebergebenen
 *                               Ordners mit Unterordnern und allen Dateien loescht
 * createFolder()      - erzeugt den entsprechenden Ordner unter adm_my_files/photos
 *
 *****************************************************************************/

$absolute_path = substr(__FILE__, 0, strpos(__FILE__, "adm_program")-1);
require_once("$absolute_path/adm_program/system/classes/table_access.php");

class PhotoAlbum extends TableAccess
{
    // Konstruktor
    function PhotoAlbum(&$db, $photo_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_PHOTOS;
        $this->column_praefix = "pho";
        
        if(is_numeric($photo_id))
        {
            $this->readData($photo_id);
        }
        else
        {
            $this->clear();
        }
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function save()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->new_record)
        {
            $this->setValue("pho_timestamp_create", date("Y-m-d H:i:s", time()));
            $this->setValue("pho_usr_id_create", $g_current_user->getValue("usr_id"));
            $this->setValue("pho_org_shortname", $g_current_organization->getValue("org_shortname"));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(strtotime($this->getValue("pho_timestamp_change")) > (strtotime($this->getValue("pho_timestamp_create")) + 900)
            || $this->getValue("pho_usr_id_change") != $this->getValue("pho_usr_id_create") )
            {
                $this->setValue("pho_timestamp_change", date("Y-m-d H:i:s", time()));
                $this->setValue("pho_usr_id_change", $g_current_user->getValue("usr_id"));
            }
        }
        parent::save();
    }
    
    // interne Funktion, die die Fotoveranstaltung in Datenbank und File-System loeschen
    // die Funktion wird innerhalb von delete() aufgerufen
    function delete()
    {
        if($this->deleteInDatabase($this->db_fields['pho_id']))
        {
            return parent::delete();
        }
        return false;
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