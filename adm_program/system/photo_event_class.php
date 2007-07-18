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
 * $photo_event = new PhotoEvent($g_adm_con);
 *
 * Mit der Funktion getPhotoEvent($pho_id) kann die gewuenschte Fotoveranstaltung 
 * ausgelesen werden. 
 *
 * Folgende Funktionen stehen weiter zur Verfuegung:
 *
 * clear()                - Die Klassenvariablen werden neu initialisiert
 * setArray($field_arra)  - uebernimmt alle Werte aus einem Array in das Field-Array
 * setValue($field_name, $field_value) 
 *                         - setzt einen Wert fuer ein bestimmtes Feld
 * getValue($field_name)  - gibt den Wert eines Feldes zurueck
 * save($login_user_id)   - Veranstaltung wird mit den geaenderten Daten in die Datenbank
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

class PhotoEvent
{
    var $db_connection;
    
    var $db_fields_changed;         // Merker ob an den db_fields Daten was geaendert wurde
    var $db_fields = array();       // Array ueber alle Felder der Rollen-Tabelle der entsprechenden Rolle

    // Konstruktor
    function PhotoEvent($connection, $photo_id = 0)
    {
        $this->db_connection = $connection;
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
        $this->clear();
        
        if(is_numeric($photo_id) && $photo_id > 0)
        {
            $sql = "SELECT * 
                      FROM ". TBL_PHOTOS. "
                     WHERE pho_id    = $photo_id ";
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);

            if($row = mysql_fetch_array($result, MYSQL_ASSOC))
            {
                // Daten in das Klassenarray schieben
                foreach($row as $key => $value)
                {
                    if(is_null($value))
                    {
                        $this->db_fields[$key] = "";
                    }
                    else
                    {
                        $this->db_fields[$key] = $value;
                    }
                }
            }        
        }
    }

    // alle Klassenvariablen wieder zuruecksetzen
    function clear()
    {
        $this->db_fields_changed = false;
        
        if(count($this->db_fields) > 0)
        {
            foreach($this->db_fields as $key => $value)
            {
                $this->db_fields[$key] = "";
            }
        }
        else
        {
            // alle Spalten der Tabelle adm_roles ins Array einlesen 
            // und auf null setzen
            $sql = "SHOW COLUMNS FROM ". TBL_PHOTOS;
            $result = mysql_query($sql, $this->db_connection);
            db_error($result,__FILE__,__LINE__);
            
            while ($row = mysql_fetch_array($result))
            {
                $this->db_fields[$row['Field']] = "";
            }
        }
    }
    
    // Funktion uebernimmt alle Werte eines Arrays in das Field-Array
    function setArray($field_array)
    {
        foreach($field_array as $field => $value)
        {
            $this->db_fields[$field] = $value;
        }
    }
    
    // Funktion setzt den Wert eines Feldes neu, 
    // dabei koennen noch noetige Plausibilitaetspruefungen gemacht werden
    function setValue($field_name, $field_value)
    {
        $field_name  = strStripTags($field_name);
        $field_value = strStripTags($field_value);
        
        if(strlen($field_value) > 0)
        {
            // Plausibilitaetspruefungen
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
                    }
                    break;

                case "pho_quantity":
                    if(is_numeric($field_value) == false)
                    {
                        $field_value = "";
                    }
                    break;            
                    
                case "pho_locked":
                    if($field_value != 1)
                    {
                        $field_value = 0;
                    }
                    break;
            }
        }

        if(isset($this->db_fields[$field_name])
        && $field_value != $this->db_fields[$field_name])
        {
            $this->db_fields[$field_name] = $field_value;
            $this->db_fields_changed      = true;
        }
    }

    // Funktion gibt den Wert eines Feldes zurueck
    // hier koennen auch noch bestimmte Formatierungen angewandt werden
    function getValue($field_name)
    {
        return $this->db_fields[$field_name];
    }
    
    // die Funktion speichert die Rollendaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    function save($login_user_id)
    {
        if((is_numeric($login_user_id) || strlen($login_user_id) == 0)
        && (is_numeric($this->db_fields['pho_id']) || strlen($this->db_fields['pho_id']) == 0))
        {
            global $g_current_organization;
            
            if($login_user_id > 0)
            {
                // Default-Felder vorbelegen
                if($this->db_fields['pho_id'] > 0)
                {
                    $this->db_fields['pho_last_change']   = date("Y-m-d H:i:s", time());
                    $this->db_fields['pho_usr_id_change'] = $login_user_id;
                }
                else
                {
                    $this->db_fields['pho_timestamp']     = date("Y-m-d H:i:s", time());
                    $this->db_fields['pho_usr_id']        = $login_user_id;
                    $this->db_fields['pho_org_shortname'] = $g_current_organization->getValue("org_shortname");
                }
            }
            
            if($this->db_fields_changed || strlen($this->db_fields['pho_id']) == 0)
            {
                // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
                $item_connection = "";                
                $sql_field_list  = "";
                $sql_value_list  = "";

                // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
                foreach($this->db_fields as $key => $value)
                {
                    // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                    if($key != "pho_id" && strpos($key, "pho_") === 0) 
                    {
                        if($this->db_fields['pho_id'] == 0)
                        {
                            if(strlen($value) > 0)
                            {
                                // Daten fuer ein Insert aufbereiten
                                $sql_field_list = $sql_field_list. " $item_connection $key ";
                                if(is_numeric($value))
                                {
                                    $sql_value_list = $sql_value_list. " $item_connection $value ";
                                }
                                else
                                {
                                    $value = addSlashes($value);
                                    $sql_value_list = $sql_value_list. " $item_connection '$value' ";
                                }
                            }
                        }
                        else
                        {
                            // Daten fuer ein Update aufbereiten
                            if(strlen($value) == 0 || is_null($value))
                            {
                                $sql_field_list = $sql_field_list. " $item_connection $key = NULL ";
                            }
                            elseif(is_numeric($value))
                            {
                                $sql_field_list = $sql_field_list. " $item_connection $key = $value ";
                            }
                            else
                            {
                                $value = addSlashes($value);
                                $sql_field_list = $sql_field_list. " $item_connection $key = '$value' ";
                            }
                        }
                        if(strlen($item_connection) == 0 && strlen($sql_field_list) > 0)
                        {
                            $item_connection = ",";
                        }
                    }
                }

                if($this->db_fields['pho_id'] > 0)
                {
                    $sql = "UPDATE ". TBL_PHOTOS. " SET $sql_field_list 
                             WHERE pho_id = ". $this->db_fields['pho_id'];
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                }
                else
                {
                    $sql = "INSERT INTO ". TBL_PHOTOS. " ($sql_field_list) VALUES ($sql_value_list) ";
                    error_log($sql);
                    $result = mysql_query($sql, $this->db_connection);
                    db_error($result,__FILE__,__LINE__);
                    $this->db_fields['pho_id'] = mysql_insert_id($this->db_connection);
                }
            }

            $this->db_fields_changed = false;
            return 0;
        }
        return -1;
    }    

    // aktuelle Fotoveranstaltung loeschen
    function delete()
    {
        $return_code = $this->deleteInDatabase($this->db_fields['pho_id']);
        
        if($return_code)
        {
            $this->clear();
        }

        return $return_code;
    }

    // Rekursive Funktion die die uebergebene Veranstaltung und alle
    // Unterveranstaltungen loescht
    function deleteInDatabase($photo_id)
    {
        $return_code = true;
    
        // erst einmal rekursiv zur tiefsten Tochterveranstaltung gehen
        $sql = "SELECT pho_id FROM ". TBL_PHOTOS. "
                 WHERE pho_pho_id_parent = $photo_id ";
        error_log($sql);
        error_log("sql: $photo_id");
        $result1 = mysql_query($sql, $this->db_connection);
        db_error($result1,__FILE__,__LINE__);
        
        while($row = mysql_fetch_array($result1))
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
        
            if($return_code)
            {
                // Veranstaltung jetzt loeschen            
                $sql = "DELETE FROM ". TBL_PHOTOS. "
                         WHERE pho_id = $photo_id ";
                error_log($sql);
                $result = mysql_query($sql, $this->db_connection);
                db_error($result,__FILE__,__LINE__);
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