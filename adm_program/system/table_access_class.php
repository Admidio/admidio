<?php 
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_organizations
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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

class TableAccess
{
    var $table_name;
    var $column_praefix;
    var $key_name;
    var $db;
    
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
        
        if(strlen($sql_where_condition) == 0 && is_numeric($key_value))
        {
            // es wurde keine Bedingung uebergeben, dann den Satz mit der Key-Id lesen
            $sql_where_condition = " $this->key_name = $key_value ";
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
        
        // Zusaetzliche Daten der abgeleiteten Klasse loeschen
        if(method_exists($this, "_clear"))
        {
            $this->_clear();
        }
                        
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
    
    // Funktion gibt den Wert eines Feldes zurueck
    function getValue($field_name)
    {
        return $this->db_fields[$field_name];
    }    
    
    // die Funktion speichert die Organisationsdaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    function save()
    {
        // nur durchfuehren, wenn das Schluesselfeld vernuenftig belegt ist
        if(is_numeric($this->db_fields[$this->key_name]) 
        || strlen($this->db_fields[$this->key_name]) == 0)
        {
            if($this->db_fields_changed || strlen($this->db_fields[$this->key_name]) == 0)
            {
                // Defaultdaten vorbelegen
                if(method_exists($this, "_save"))
                {
                    $this->_save();
                }
                
                // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
                $item_connection = "";                
                $sql_field_list  = "";
                $sql_value_list  = "";

                // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
                foreach($this->db_fields as $key => $value)
                {
                    // ID und andere Tabellenfelder sollen nicht im Insert erscheinen
                    if($key != $this->key_name && strpos($key, $this->column_praefix. "_") === 0) 
                    {
                        if($this->db_fields[$this->key_name] == 0)
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

                if($this->db_fields[$this->key_name] > 0)
                {
                    $sql = "UPDATE $this->table_name SET $sql_field_list 
                             WHERE $this->key_name = ". $this->db_fields[$this->key_name];
                    $this->db->query($sql);
                }
                else
                {
                    $sql = "INSERT INTO $this->table_name ($sql_field_list) VALUES ($sql_value_list) ";
                    $this->db->query($sql);
                    $this->db_fields[$this->key_name] = $this->db->insert_id();
                }
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
                        WHERE $this->key_name = ". $this->db_fields[$this->key_name];
            $this->db->query($sql);
    
            $this->clear();
        }
        return $ret_code;
    }    
}
?>