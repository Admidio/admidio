<?php 
/******************************************************************************
 * Abstrakte Klasse steuert den Zugriff auf die entsprechenden Datenbanktabellen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Von dieser Klasse werden saemtliche Datenbankzugriffsklassen abgeleitet.
 * Es stehen diverse Methoden zur Verfuegung, welche in den abgeleiteten Klassen
 * durch vordefinierte Methoden angepasst werden koennen
 *
 * The following functions are available:
 *
 * readData($id, $sql_where_condition = "", $sql_additional_tables = "")
 *                  - Liest den Datensatz zur uebergebenen ID ($key_value) ein
 * clear()          - Die Klassenvariablen werden neu initialisiert
 * countAllRecords()- Anzahl aller Datensaetze der Tabelle werden zurueckgegeben
 * setArray($field_array)
 *                  - es wird ein Array mit allen noetigen gefuellten Tabellenfeldern
 *                    uebergeben. Key ist Spaltenname und Wert ist der Inhalt.
 *                    Mit dieser Methode kann das Einlesen der Werte umgangen werden.
 * setValue($field_name, $field_value, $check_value = true)
 *                  - setzt einen Wert fuer ein bestimmtes Feld der Tabelle
 * getValue($field_name, format)- gibt den Wert eines Feldes der Tabelle zurueck
 * save()           - die aktuellen Daten werden in die Datenbank zurueckgeschrieben
 *                    Es wird automatisch ein Update oder Insert erstellt
 * delete()         - der aktuelle Datensatz wird aus der Tabelle geloescht
 *
 *****************************************************************************/

class TableAccess
{
    protected $table_name;
    protected $column_praefix;
    protected $key_name;
    public    $db;					// db object must public because of session handling
    
    protected $new_record;          // Merker, ob ein neuer Datensatz oder vorhandener Datensatz bearbeitet wird
    public $columnsValueChanged; // Merker ob an den dbColumns Daten was geaendert wurde
    public $dbColumns = array();    // Array ueber alle Felder der entsprechenden Tabelle zu dem gewaehlten Datensatz
    public $columnsInfos = array(); // Array, welches weitere Informationen (geaendert ja/nein, Feldtyp) speichert
    
    // wird diese Klasse nicht abgeleitet, so muss dem Konstruktor neben der DB-Objekt auch der Tabellenname,
    // das Präfix der Spalten übergeben werden und optional noch die ID, die direkt eingelesen werden soll
    public function __construct(&$db, $tableName, $columnPraefix, $id = '')
    {
        $this->db            =& $db;
        $this->table_name     = $tableName;
        $this->column_praefix = $columnPraefix;

        // wurde eine ID uebergeben, dann Daten aus DB einlesen
        if((is_numeric($id) == false && strlen($id) > 0) 
        || (is_numeric($id) == true  && $id > 0))
        {
            $this->readData($id);
        }
        else
        {
            $this->clear();
        }
    }
    
    // alle Klassenvariablen wieder zuruecksetzen
    public function clear()
    {
		$columnProperties = array();
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
			$columnProperties = $this->db->showColumns($this->table_name);
			
			foreach($columnProperties as $key => $value)
			{
                $this->dbColumns[$key] = '';
                $this->columnsInfos[$key]['changed'] = false;
                $this->columnsInfos[$key]['type']    = $value['type'];
                $this->columnsInfos[$key]['null']    = $value['null'];
                $this->columnsInfos[$key]['key']     = $value['key'];
                $this->columnsInfos[$key]['serial']  = $value['serial'];
                
                if($value['serial'] == 1)
                {
                    $this->key_name = $key;
                }				
			}
        }
    }
    
    // Methode gibt die Anzahl aller Datensaetze dieser Tabelle zurueck
    public function countAllRecords()
    {
        $sql = 'SELECT COUNT(1) as count FROM '.$this->table_name;
        $this->db->query($sql);
        $row = $this->db->fetch_array();
        return $row['count'];
    }

    // aktuelle Datensatz loeschen und ggf. noch die Referenzen
    public function delete()
    {
		if(strlen($this->dbColumns[$this->key_name]) > 0)
		{
			$sql    = 'DELETE FROM '.$this->table_name.' 
						WHERE '.$this->key_name.' = \''. $this->dbColumns[$this->key_name]. '\'';
			$this->db->query($sql);
		}

        $this->clear();
        return true;
    }

    // method gets the value of the field ($field_name)
    // $format - Datetime 'd.m.Y' = '02.04.2011'
	//         - Text     'plain' the db field value without any transformation
    public function getValue($field_name, $format = '')
    {
        global $gPreferences;
        $field_value = '';
        
        if(isset($this->dbColumns[$field_name]))
        {
            // wenn Schluesselfeld leer ist, dann 0 zurueckgeben
            if($field_name == $this->key_name && empty($this->dbColumns[$field_name]))
            {
                $field_value = 0;
            }
            else
            {
                $field_value = $this->dbColumns[$field_name];
            }
        }

        // if text field and format not 'plain' then convert all quotes to html syntax
        if(isset($this->columnsInfos[$field_name]['type'])
		&& $format != 'plain'
        && (  strpos($this->columnsInfos[$field_name]['type'], 'char') !== false
           || strpos($this->columnsInfos[$field_name]['type'], 'text') !== false))
        {
            return htmlspecialchars($field_value, ENT_QUOTES);
        }
        // in PostgreSQL we must encode the stored hex value back to binary
        elseif(isset($this->columnsInfos[$field_name]['type'])
        && strpos($this->columnsInfos[$field_name]['type'], 'bytea') !== false)
        {
            $field_value = substr($field_value, 2);
			$field_value = pack('H*', $field_value);
			return pack('H*', $field_value);
        }        // Datum in dem uebergebenen Format bzw. Systemformat zurueckgeben
        elseif(isset($this->columnsInfos[$field_name]['type'])
        &&  (  strpos($this->columnsInfos[$field_name]['type'], 'timestamp') !== false
            || strpos($this->columnsInfos[$field_name]['type'], 'date') !== false
            || strpos($this->columnsInfos[$field_name]['type'], 'time') !== false))
        {
            if(strlen($field_value) > 0)
            {
                if(strlen($format) == 0 && isset($gPreferences))
                {
                    if(strpos($this->columnsInfos[$field_name]['type'], 'timestamp') !== false)
                    {
                        $format = $gPreferences['system_date'].' '.$gPreferences['system_time'];
                    }
                    elseif(strpos($this->columnsInfos[$field_name]['type'], 'date') !== false)
                    {
                        $format = $gPreferences['system_date'];
                    }
                    else
                    {
                        $format = $gPreferences['system_time'];
                    }
                }

                // probieren das Datum zu formatieren, ansonsten Ausgabe der vorhandenen Daten
                try
                {
                    $datetime = new DateTime($field_value);
                    $field_value = $datetime->format($format);
                }
                catch(Exception $e)
                {
                    $field_value = $this->dbColumns[$field_name];
                }
            }
            return $field_value;
        }
        else
        {
            return $field_value;
        }
    }

    // liest den Datensatz von $id ein
    // die Methode gibt true zurueck, wenn ein DS gefunden wurde, andernfalls false
    // id : Schluesselwert von dem der Datensatz gelesen werden soll
    // sql_where_condition : optional eine individuelle WHERE-Bedinugung fuer das SQL-Statement
    // sql_additioinal_tables : mit Komma getrennte Auflistung weiterer Tabelle, die mit
    //                          eingelesen werden soll, dabei muessen die Verknuepfungen
    //                          in sql_where_condition stehen
    public function readData($id, $sql_where_condition = '', $sql_additional_tables = '')
    {
        // erst einmal alle Felder in das Array schreiben, falls kein Satz gefunden wird
        $this->clear();

        // es wurde keine Bedingung uebergeben, dann den Satz mit der Key-Id lesen, 
        // falls diese sinnvoll gefuellt ist
        if(strlen($sql_where_condition) == 0 && strlen($id) > 0 && $id != '0')
        {
            $sql_where_condition = ' '.$this->key_name.' = \''.$id.'\' ';
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
                return true;
            }
            else
            {
                $this->clear();
            }
        }
        return false;
    }
    
    // die Methode speichert die Organisationsdaten in der Datenbank,
    // je nach Bedarf wird ein Insert oder Update gemacht
    // updateFingerPrint : steuert, ob in den Tabellen der Ersteller und Aenderer aktualisiert wird
    public function save($updateFingerPrint = true)
    {
        if($this->columnsValueChanged || strlen($this->dbColumns[$this->key_name]) == 0)
        {
            // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
            $item_connection = '';                
            $sql_field_list  = '';
            $sql_value_list  = '';

            if($updateFingerPrint)
            {
                // besitzt die Tabelle Felder zum Speichern des Erstellers und der letzten Aenderung, 
                // dann diese hier automatisiert fuellen
                if($this->new_record && isset($this->dbColumns[$this->column_praefix.'_usr_id_create']))
                {
                    global $gCurrentUser;
                    $this->setValue($this->column_praefix.'_timestamp_create', DATETIME_NOW);
                    $this->setValue($this->column_praefix.'_usr_id_create', $gCurrentUser->getValue('usr_id'));
                }
                elseif(isset($this->dbColumns[$this->column_praefix.'_usr_id_change']))
                {
                    global $gCurrentUser;
                    // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
                    if(time() > (strtotime($this->getValue($this->column_praefix.'_timestamp_create')) + 900)
                    || $gCurrentUser->getValue('usr_id') != $this->getValue($this->column_praefix.'_usr_id_create') )
                    {
                        $this->setValue($this->column_praefix.'_timestamp_change', DATETIME_NOW);
                        $this->setValue($this->column_praefix.'_usr_id_change', $gCurrentUser->getValue('usr_id'));
                    }
                }
            }

            // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
            foreach($this->dbColumns as $key => $value)
            {
                // Auto-Increment-Felder duerfen nicht im Insert/Update erscheinen
                // Felder anderer Tabellen auch nicht
                if(strpos($key, $this->column_praefix. '_') === 0
                && $this->columnsInfos[$key]['serial'] == 0) 
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
                                if(strpos($this->columnsInfos[$key]['type'], 'integer')  !== false
								|| strpos($this->columnsInfos[$key]['type'], 'smallint') !== false)
                                {
                                    $sql_value_list = $sql_value_list. ' '.$item_connection.' '.$value.' ';
                                }
                                else
                                {
                                    // Slashs (falls vorhanden) erst einmal entfernen und dann neu Zuordnen, 
                                    // damit sie auf jeden Fall da sind
                                    $value = stripslashes($value);
                                    $value = addslashes($value);
                                    $sql_value_list = $sql_value_list. ' '.$item_connection.' \''.$value.'\' ';
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
                            elseif(strpos($this->columnsInfos[$key]['type'], 'integer')  !== false
							    || strpos($this->columnsInfos[$key]['type'], 'smallint') !== false)
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
                                $sql_field_list = $sql_field_list. ' '.$item_connection.' '.$key.' = \''.$value.'\' ';
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
				// insert record and mark this object as not new and remember the new id
                $sql = 'INSERT INTO '.$this->table_name.' ('.$sql_field_list.') VALUES ('.$sql_value_list.') ';
                $this->db->query($sql);
                $this->new_record = false;
				if(strlen($this->key_name) > 0)
				{
					$this->dbColumns[$this->key_name] = $this->db->insert_id();
				}
            }
            else
            {
                $sql = 'UPDATE '.$this->table_name.' SET '.$sql_field_list.' 
                         WHERE '.$this->key_name.' = \''. $this->dbColumns[$this->key_name]. '\'';
                $this->db->query($sql);
            }
        }

        $this->columnsValueChanged = false;
        return 0;
    }

    // es wird ein Array mit allen noetigen gefuellten Tabellenfeldern
    // uebergeben. Key ist Spaltenname und Wert ist der Inhalt.
    // Mit dieser Methode kann das Einlesen der Werte umgangen werden.
    public function setArray($field_array)
    {
        foreach($field_array as $field => $value)
        {
            $this->dbColumns[$field] = $value;
            $this->columnsInfos[$field]['changed'] = false;
        }
        $this->new_record = false;
    }

    // Methode setzt den Wert eines Feldes neu, 
    // dabei koennen optional noch noetige Plausibilitaetspruefungen gemacht werden
    public function setValue($field_name, $field_value, $check_value = true)
    {
        $return_code = false;

        if(array_key_exists($field_name, $this->dbColumns))
        {
            // Allgemeine Plausibilitaets-Checks anhand des Feldtyps
            if(strlen($field_value) > 0 && $check_value == true)
            {
                // Numerische Felder
                if($this->columnsInfos[$field_name]['type'] == 'integer'
				|| $this->columnsInfos[$field_name]['type'] == 'smallint')
                {
                    if(is_numeric($field_value) == false)
                    {
                        $field_value = '';
                    }

                    // Schluesselfelder duerfen keine 0 enthalten
                    if(($this->columnsInfos[$field_name]['key'] == 1 || $this->columnsInfos[$field_name]['null'] == 1)
					&& $field_value == 0)
                    {
                        $field_value = '';
                    }
                }
                
                // Strings
                elseif(strpos($this->columnsInfos[$field_name]['type'], 'char') !== false
                ||     strpos($this->columnsInfos[$field_name]['type'], 'text') !== false)
                {
                    $field_value = strStripTags($field_value);
                }

                elseif($this->columnsInfos[$field_name]['type'] == 'blob'
                ||     $this->columnsInfos[$field_name]['type'] == 'bytea' )
                {
	                // PostgreSQL can only store hex values in bytea, so we must decode binary in hex
                	if($this->columnsInfos[$field_name]['type'] == 'bytea')
                	{
	                    $field_value = bin2hex($field_value);
                	}
    	            // we must add slashes to binary data of blob fields so that the default stripslashes don't remove necessary slashes
                    $field_value = addslashes($field_value);
                }
            }

            // wurde das Schluesselfeld auf 0 gesetzt, dann soll ein neuer Datensatz angelegt werden
            if($field_name == $this->key_name && $field_value == 0)
            {
                $this->new_record = true;
            }

            if(array_key_exists($field_name, $this->dbColumns))
            {
                $return_code = true;

                // nur wenn der Wert sich geaendert hat, dann auch als geaendert markieren                
                if($field_value != $this->dbColumns[$field_name])
                {
                    $this->dbColumns[$field_name] = $field_value;
                    $this->columnsValueChanged    = true;
                    $this->columnsInfos[$field_name]['changed'] = true;
                }
            }
        }
        return $return_code;
    } 
}
?>