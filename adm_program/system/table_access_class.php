<?php 
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_organizations
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Objekt einer Organisation zu erstellen. 
 * Eine Organisation kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $orga = new TblOrganization($g_db);
 *
 * Mit der Funktion getOrganization($shortname) kann die gewuenschte Organisation
 * ausgelesen werden.
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * update()         - Die Organisation wird mit den geaenderten Daten in die Datenbank 
 *                    zurueckgeschrieben
 * insert()         - Eine neue Organisation wird in die Datenbank geschrieben
 * clear()          - Die Klassenvariablen werden neu initialisiert
 * getPreferences() - gibt ein Array mit allen organisationsspezifischen Einstellungen
 *                    aus adm_preferences zurueck
 * getReferenceOrganizations($child = true, $parent = true)
 *                  - Gibt ein Array mit allen Kinder- bzw. Elternorganisationen zurueck
 *
 *****************************************************************************/

class TableAccess
{
    var $table_name;
    var $column_praefix;
    var $key_name;
    var $auto_increment;
    var $db;
    
    var $new_record;                // Merker, ob ein neuer Datensatz oder vorhandener Datensatz bearbeitet wird
    var $db_fields_changed;         // Merker ob an den db_fields Daten was geaendert wurde
    var $db_fields = array();       // Array ueber alle Felder der Rollen-Tabelle der entsprechenden Rolle
    
    // liest den Datensatz von $key_value ein
    // key_value : Schluesselwert von dem der Datensatz gelesen werden soll
    // sql_where_condition : optional eine individuelle WHERE-Bedinugung fuer das SQL-Statement
    // sql_additioinal_tables : mit Komma getrennte Auflistung weiterer Tabelle, die mit
    //                          eingelesen werden soll, dabei muessen die Verknuepfungen
    //                          in sql_where_condition stehen
    function readData($key_value, $sql_where_condition = "", $sql_additional_tables = "")
    {
        // erst einmal alle Felder in das Array schreiben, falls kein Satz gefunden wird
        $this->clear();

        // es wurde keine Bedingung uebergeben, dann den Satz mit der Key-Id lesen, 
        // falls diese sinnvoll gefuellt ist
        if(strlen($sql_where_condition) == 0 && strlen($key_value) > 0 && $key_value != "0")
        {
            $sql_where_condition = " $this->key_name = '$key_value' ";
        }
        if(strlen($sql_additional_tables) > 0)
        {
            $sql_additional_tables = ", $sql_additional_tables";
        }
        
        if(strlen($sql_where_condition) > 0)
        {
            $sql = "SELECT * FROM $this->table_name $sql_additional_tables
                     WHERE $sql_where_condition ";
            $result = $this->db->query($sql);
    
            if($row = $this->db->fetch_array($result, MYSQL_ASSOC))
            {
                $this->new_record = false;
                
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
        $this->new_record        = true;
                        
        if(count($this->db_fields) > 0)
        {
            foreach($this->db_fields as $key => $value)
            {
                $this->db_fields[$key] = "";
            }
        }
        else
        {
            // alle Spalten der Tabelle ins Array einlesen 
            // und auf leer setzen
            $sql = "SHOW COLUMNS FROM $this->table_name ";
            $this->db->query($sql);

            while ($row = $this->db->fetch_array())
            {
                $this->db_fields[$row['Field']] = "";
            }
        }
        
        // Zusaetzliche Daten der abgeleiteten Klasse loeschen
        if(method_exists($this, "_clear"))
        {
            $this->_clear();
        }
    }    
    
    // Methode uebernimmt alle Werte eines Arrays in das Field-Array
    function setArray($field_array)
    {
        foreach($field_array as $field => $value)
        {
            $this->db_fields[$field] = $value;
        }
    }

    // Methode setzt den Wert eines Feldes neu, 
    // dabei koennen noch noetige Plausibilitaetspruefungen gemacht werden
    function setValue($field_name, $field_value)
    {
        $field_name  = strStripTags($field_name);
        $field_value = strStripTags($field_value);
        
        // Plausibilitaets-Check des Wertes vornehmen
        if(method_exists($this, "_setValue"))
        {
            $this->_setValue($field_name, &$field_value);
        }

        if(isset($this->db_fields[$field_name])
        && $field_value != $this->db_fields[$field_name])
        {
            $this->db_fields[$field_name] = $field_value;
            $this->db_fields_changed      = true;
        }
    }    
    
    // Methode gibt den Wert eines Feldes zurueck
    function getValue($field_name)
    {
        if(method_exists($this, "_getValue"))
        {
            $value = $this->_getValue($field_name);
        }
        
        if(isset($this->db_fields[$field_name]))
        {
            $value = $this->db_fields[$field_name];
        }
        
        return $value;
    }    
    
    // die Methode speichert die Organisationsdaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    function save()
    {
        // nur durchfuehren, wenn das Schluesselfeld vernuenftig belegt ist
        if((  $this->auto_increment == true
           && (  is_numeric($this->db_fields[$this->key_name]) 
              || strlen($this->db_fields[$this->key_name]) == 0))
        || $this->auto_increment == false)
        {
            // Defaultdaten vorbelegen
            if(method_exists($this, "_save"))
            {
                $this->_save();
            }

            if($this->db_fields_changed || strlen($this->db_fields[$this->key_name]) == 0)
            {
                // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
                $item_connection = "";                
                $sql_field_list  = "";
                $sql_value_list  = "";

                // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
                foreach($this->db_fields as $key => $value)
                {
                    // bei Auto-Increment-ID darf diese nicht im Insert erscheinen
                    // Felder anderer Tabellen auch nicht
                    if((  (  $this->auto_increment == true 
                          && $key != $this->key_name )
                       || $this->auto_increment == false )
                    && strpos($key, $this->column_praefix. "_") === 0) 
                    {
                        $value = utf8_decode_db($value);
                        if($this->new_record)
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
                                    // Slashs (falls vorhanden) erst einmal entfernen und dann neu Zuordnen, 
                                    // damit sie auf jeden Fall da sind
                                    $value = stripslashes($value);
                                    $value = addslashes($value);
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
                                // Slashs (falls vorhanden) erst einmal entfernen und dann neu Zuordnen, 
                                // damit sie auf jeden Fall da sind
                                $value = stripslashes($value);
                                $value = addslashes($value);
                                $sql_field_list = $sql_field_list. " $item_connection $key = '$value' ";
                            }
                        }
                        if(strlen($item_connection) == 0 && strlen($sql_field_list) > 0)
                        {
                            $item_connection = ",";
                        }
                    }
                }

                if($this->new_record)
                {
                    $sql = "INSERT INTO $this->table_name ($sql_field_list) VALUES ($sql_value_list) ";
                    $this->db->query($sql);
                    $this->db_fields[$this->key_name] = $this->db->insert_id();
                    $this->new_record = false;
                }
                else
                {
                    $sql = "UPDATE $this->table_name SET $sql_field_list 
                             WHERE $this->key_name = '". $this->db_fields[$this->key_name]. "'";
                    $this->db->query($sql);
                }
            }

            // Nach dem Speichern eine Funktion aufrufen um evtl. abhaenige Daten noch anzupassen
            if(method_exists($this, "_afterSave"))
            {
                $this->_afterSave();
            }

            $this->db_fields_changed = false;
            return 0;
        }
        return -1;
    }
    
    // aktuelle Datensatz loeschen und ggf. noch die Referenzen
    function delete()
    {
        $ret_code = true;
        
        // erst einmal die Referenzen verarbeiten
        if(method_exists($this, "_delete"))
        {
            $ret_code = $this->_delete();
        }
                        
        if($ret_code == true)
        {
            $sql    = "DELETE FROM $this->table_name 
                        WHERE $this->key_name = '". $this->db_fields[$this->key_name]. "'";
            $this->db->query($sql);
    
            $this->clear();
        }
        return $ret_code;
    }    
}
?>