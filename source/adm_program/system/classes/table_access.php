<?php 
/******************************************************************************
 * Abstrakte Klasse steuert den Zugriff auf die entsprechenden Datenbanktabellen
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Von dieser Klasse werden saemtliche Datenbankzugriffsklassen abgeleitet.
 * Es stehen diverse Methoden zur Verfuegung, welche in den abgeleiteten Klassen
 * durch vordefinierte Methoden angepasst werden koennen
 *
 * Folgende Funktionen stehen zur Verfuegung:
 *
 * readData($id, $sql_where_condition = "", $sql_additional_tables = "")
 *                  - Liest den Datensatz zur uebergebenen ID ($key_value) ein
 * clear()          - Die Klassenvariablen werden neu initialisiert
 * countAllRecords()- Anzahl aller Datensaetze der Tabelle werden zurueckgegeben
 * setArray($field_array)
 *                  - es wird ein Array mit allen noetigen gefuellten Tabellenfeldern
 *                    uebergeben. Key ist Spaltenname und Wert ist der Inhalt.
 *                    Mit dieser Methode kann das Einlesen der Werte umgangen werden.
 * setValue($field_name, $field_value) 
 *                  - setzt einen Wert fuer ein bestimmtes Feld der Tabelle
 * getValue($field_name)- gibt den Wert eines Feldes der Tabelle zurueck
 * save()           - die aktuellen Daten werden in die Datenbank zurueckgeschrieben
 *                    Es wird automatisch ein Update oder Insert erstellt
 * delete()         - der aktuelle Datensatz wird aus der Tabelle geloescht
 *
 *****************************************************************************/

class TableAccess
{
    var $table_name;
    var $column_praefix;
    var $key_name;
    var $db;
    
    var $new_record;             // Merker, ob ein neuer Datensatz oder vorhandener Datensatz bearbeitet wird
    var $columnsValueChanged;    // Merker ob an den dbColumns Daten was geaendert wurde
    var $dbColumns = array();    // Array ueber alle Felder der entsprechenden Tabelle zu dem gewaehlten Datensatz
    var $columnsInfos = array(); // Array, welches weitere Informationen (geaendert ja/nein, Feldtyp) speichert

    // liest den Datensatz von $id ein
    // id : Schluesselwert von dem der Datensatz gelesen werden soll
    // sql_where_condition : optional eine individuelle WHERE-Bedinugung fuer das SQL-Statement
    // sql_additioinal_tables : mit Komma getrennte Auflistung weiterer Tabelle, die mit
    //                          eingelesen werden soll, dabei muessen die Verknuepfungen
    //                          in sql_where_condition stehen
    function readData($id, $sql_where_condition = '', $sql_additional_tables = '')
    {
        // erst einmal alle Felder in das Array schreiben, falls kein Satz gefunden wird
        $this->clear();

        // es wurde keine Bedingung uebergeben, dann den Satz mit der Key-Id lesen, 
        // falls diese sinnvoll gefuellt ist
        if(strlen($sql_where_condition) == 0 && strlen($id) > 0 && $id != '0')
        {
            $sql_where_condition = ' '.$this->key_name.' = "'.$id.'" ';
        }
        if(strlen($sql_additional_tables) > 0)
        {
            $sql_additional_tables = ', '. $sql_additional_tables;
        }
        
        if(strlen($sql_where_condition) > 0)
        {
            $sql = 'SELECT * FROM '.$this->table_name.' '.$sql_additional_tables.'
                     WHERE '.$sql_where_condition.' ';
            $result = $this->db->query($sql);
    
            if($row = $this->db->fetch_array($result, MYSQL_ASSOC))
            {
                $this->new_record = false;
                
                // Daten in das Klassenarray schieben
                foreach($row as $key => $value)
                {
                    if(is_null($value))
                    {
                        $this->dbColumns[$key] = '';
                    }
                    else
                    {
                        $this->dbColumns[$key] = $value;
                    }
                }
            }
        }       
    }
    
    // alle Klassenvariablen wieder zuruecksetzen
   function clear()
   {
        $this->columnsValueChanged = false;
        $this->new_record   = true;
        $this->record_count = -1;

        if(count($this->columnsInfos) > 0)
        {
            // die Spalteninfos wurden bereits eingelesen
            // und werden nun nur noch neu initialisiert
            foreach($this->dbColumns as $field_name => $field_value)
            {
                $this->dbColumns[$field_name] = '';
                $this->columnsInfos[$field_name]['changed'] = false;
            }
        }
        else
        {
            // alle Spalten der Tabelle ins Array einlesen und auf leer setzen
            $sql = 'SHOW COLUMNS FROM '.$this->table_name;
            $this->db->query($sql);

            while ($row = $this->db->fetch_array())
            {
                $this->dbColumns[$row['Field']] = '';
                $this->columnsInfos[$row['Field']]['changed'] = false;
                $this->columnsInfos[$row['Field']]['type']    = $row['Type'];
                $this->columnsInfos[$row['Field']]['key']     = $row['Key'];
                $this->columnsInfos[$row['Field']]['extra']   = $row['Extra'];
                
                if($row['Key'] == 'PRI')
                {
                    $this->key_name = $row['Field'];
                }
            }
        }
    }
    
    // Methode gibt die Anzahl aller Datensaetze dieser Tabelle zurueck
    function countAllRecords()
    {
        $sql = 'SELECT COUNT(1) as count FROM '.$this->table_name;
        $this->db->query($sql);
        $row = $this->db->fetch_array();
        return $row['count'];
    }
    
    // es wird ein Array mit allen noetigen gefuellten Tabellenfeldern
    // uebergeben. Key ist Spaltenname und Wert ist der Inhalt.
    // Mit dieser Methode kann das Einlesen der Werte umgangen werden.
    function setArray($field_array)
    {
        foreach($field_array as $field => $value)
        {
            $this->dbColumns[$field] = $value;
            $this->columnsInfos[$field]['changed'] = false;
        }
        $this->new_record = false;
    }

    // Methode setzt den Wert eines Feldes neu, 
    // dabei koennen noch noetige Plausibilitaetspruefungen gemacht werden
    function setValue($field_name, $field_value)
    {
        $return_code = false;

        if(array_key_exists($field_name, $this->dbColumns))
        {
            // Allgemeine Plausibilitaets-Checks anhand des Feldtyps
            if(strlen($field_value) > 0)
            {
                // Numerische Felder
                if(strpos($this->columnsInfos[$field_name]['type'], 'int') !== false)
                {
                    if(is_numeric($field_value) == false)
                    {
                        $field_value = '';
                    }

                    // Schluesselfelder
                    if((  $this->columnsInfos[$field_name]['key'] == 'PRI'
                       || $this->columnsInfos[$field_name]['key'] == 'MUL')
                    && $field_value == 0)
                    {
                        if($field_name != 'mem_from_rol_id') $field_value = '';
                    }
                }
                
                // Strings
                elseif(strpos($this->columnsInfos[$field_name]['type'], 'char') !== false
                ||     strpos($this->columnsInfos[$field_name]['type'], 'text') !== false)
                {
                    $field_value = strStripTags($field_value);
                }

                // Daten
                elseif(strpos($this->columnsInfos[$field_name]['type'], 'blob') !== false)
                {
                    $field_value = addslashes($field_value);
                }
            }
    
            if(array_key_exists($field_name, $this->dbColumns)
            && $field_value != $this->dbColumns[$field_name])
            {
                $this->dbColumns[$field_name] = $field_value;
                $this->columnsValueChanged      = true;
                $this->columnsInfos[$field_name]['changed'] = true;
                $return_code = true;
            }
        }
        return $return_code;
    }    
    
    // Methode gibt den Wert eines Feldes ($field_name) zurueck
    // $field_value dient als Uebergabe aus ueberschreibenden Methoden heraus
    function getValue($field_name, $field_value = '')
    {
        if(strlen($field_value) == 0
        && isset($this->dbColumns[$field_name]))
        {
            $field_value = $this->dbColumns[$field_name];
        }

        // bei Textfeldern muessen Anfuehrungszeichen noch escaped werden
        if(isset($this->columnsInfos[$field_name]['type'])
        && (  strpos($this->columnsInfos[$field_name]['type'], 'char') !== false
           || strpos($this->columnsInfos[$field_name]['type'], 'text') !== false))
        {
            return htmlspecialchars($field_value, ENT_QUOTES);
        }
        else
        {
            return $field_value;
        }
    }    
    
    // die Methode speichert die Organisationsdaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    function save()
    {
        if($this->columnsValueChanged || strlen($this->dbColumns[$this->key_name]) == 0)
        {
            // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
            $item_connection = '';                
            $sql_field_list  = '';
            $sql_value_list  = '';

            // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
            foreach($this->dbColumns as $key => $value)
            {
                // Auto-Increment-Felder duerfen nicht im Insert/Update erscheinen
                // Felder anderer Tabellen auch nicht
                if(strpos($key, $this->column_praefix. '_') === 0
                && $this->columnsInfos[$key]['extra'] != 'auto_increment') 
                {
                    if($this->columnsInfos[$key]['changed'] == true)
                    {
                        if($this->new_record)
                        {
                            if(strlen($value) > 0)
                            {
                                // Daten fuer ein Insert aufbereiten
                                $sql_field_list = $sql_field_list. ' '.$item_connection.' '.$key.' ';
                                // unterscheiden zwischen Numerisch und Text
                                if(strpos($this->columnsInfos[$key]['type'], 'int') !== false)
                                {
                                    $sql_value_list = $sql_value_list. ' '.$item_connection.' '.$value.' ';
                                }
                                else
                                {
                                    // Slashs (falls vorhanden) erst einmal entfernen und dann neu Zuordnen, 
                                    // damit sie auf jeden Fall da sind
                                    $value = stripslashes($value);
                                    $value = addslashes($value);
                                    $sql_value_list = $sql_value_list. ' '.$item_connection.' "'.$value.'" ';
                                }
                            }
                        }
                        else
                        {
                            // Daten fuer ein Update aufbereiten
                            if(strlen($value) == 0 || is_null($value))
                            {
                                $sql_field_list = $sql_field_list. ' '.$item_connection.' '.$key.' = NULL ';
                            }
                            elseif(strpos($this->columnsInfos[$key]['type'], 'int') !== false)
                            {
                                // numerisch
                                $sql_field_list = $sql_field_list. ' '.$item_connection.' '.$key.' = '.$value.' ';
                            }
                            else
                            {
                                // Slashs (falls vorhanden) erst einmal entfernen und dann neu Zuordnen, 
                                // damit sie auf jeden Fall da sind
                                $value = stripslashes($value);
                                $value = addslashes($value);
                                $sql_field_list = $sql_field_list. ' '.$item_connection.' '.$key.' = "'.$value.'" ';
                            }
                        }
                        if(strlen($item_connection) == 0 && strlen($sql_field_list) > 0)
                        {
                            $item_connection = ',';
                        }
                        $this->columnsInfos[$key]['changed'] = false;
                    }
                }
            }

            if($this->new_record)
            {
                $sql = 'INSERT INTO '.$this->table_name.' ('.$sql_field_list.') VALUES ('.$sql_value_list.') ';
                $this->db->query($sql);
                $this->dbColumns[$this->key_name] = $this->db->insert_id();
                $this->new_record = false;
            }
            else
            {
                $sql = 'UPDATE '.$this->table_name.' SET '.$sql_field_list.' 
                         WHERE '.$this->key_name.' = "'. $this->dbColumns[$this->key_name]. '"';
                $this->db->query($sql);
            }
        }

        $this->columnsValueChanged = false;
        return 0;
    }
    
    // aktuelle Datensatz loeschen und ggf. noch die Referenzen
    function delete()
    {
        $sql    = 'DELETE FROM '.$this->table_name.' 
                    WHERE '.$this->key_name.' = "'. $this->dbColumns[$this->key_name]. '"';
        $this->db->query($sql);

        $this->clear();
        return true;
    }    
}
?>