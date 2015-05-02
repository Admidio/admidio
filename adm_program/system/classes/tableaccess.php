<?php 
/*****************************************************************************/
/** @class TableAccess
 *  @brief Controls read and write access to datbase tables
 *
 *  This class should help you to read and write records of database tables.
 *  You create an object for a special table and than you are able to read
 *  a special record, manipulate him and write him back. Also new records can
 *  be created with this class. The advantage of this class is that you are
 *  independent from SQL. You can use @c getValue, @c setValue, @c readData 
 *  and @c save to handle the record.
 *  @par Examples
 *  @code // create an object for table adm_roles of role 4711
 *  $roleId = 4177;
 *  $role = new TableAccess($gDb, TBL_ROLES, 'rol', $roleId);
 *
 *  // read max. Members and add 1 to the count
 *  $maxMembers = $role->getValue('rol_max_members');
 *  $maxMembers = $maxMembers + 1;
 *  $role->setValue('rol_max_members', $maxMembers);
 *  $role->save(); @endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2015 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class TableAccess
{
	private   $additionalTables;	///< Array with sub array that contains additional tables and their connected fields that should be selected when data is read
    protected $tableName;			///< Name of the database table of this object. This must be the table name with the installation specific praefix e.g. @b demo_users
    protected $columnPraefix;		///< The praefix of each column that this table has. E.g. the table adm_users has the column praefix @b usr
    protected $keyColumnName;		///< Name of the unique autoincrement index column of the database table
    public    $db;					///< Database object to handle the communication with the database. This must be public because of session handling.
    
    protected $new_record;          // Merker, ob ein neuer Datensatz oder vorhandener Datensatz bearbeitet wird
    protected $columnsValueChanged; ///< Flag will be set to true if data in array dbColumns was changed
    public $dbColumns = array();    // Array ueber alle Felder der entsprechenden Tabelle zu dem gewaehlten Datensatz
    public $columnsInfos = array(); // Array, welches weitere Informationen (geaendert ja/nein, Feldtyp) speichert
    
	/** Constuctor that will create an object of a recordset of the specified table. 
	 *  If the id is set than this recordset will be loaded.
	 *  @param $db 				Object of the class database. This should be the default object @b $gDb.
	 *  @param $tableName 		The name of the database table. Because of specific praefixes this should be the define value e.g. @b TBL_USERS
	 *  @param $columnPraefix 	The praefix of each column of that table. E.g. for table @b adm_roles this is @b rol
	 *  @param $id 				The id of the recordset that should be loaded. If id isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $tableName, $columnPraefix, $id = '')
    {
        $this->db            =& $db;
        $this->tableName     = $tableName;
        $this->columnPraefix = $columnPraefix;

        // if a id is commited, then read data out of database
        if((is_numeric($id) == false && strlen($id) > 0) 
        || (is_numeric($id) == true  && $id > 0))
        {
            $this->readDataById($id);
        }
        else
        {
            $this->clear();
        }
    }
    
    /** Initializes all class parameters and deletes all read data.
	 *  Also the database structure of the assiciated table will be
	 *  read and stored in the arrays @b dbColumns and @b columnsInfos
	 */
    public function clear()
    {
		$columnProperties   = array();
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
			$columnProperties = $this->db->showColumns($this->tableName);
			
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
                    $this->keyColumnName = $key;
                }
			}
        }
    }

	/** Adds a table with the connected fields to a member array. This table will be add to the 
	 *  select statement if data is read and the connected record is avaiable in this class.
	 *  The connected table must have a foreign key in the class table.
	 *  @param $table 						Database table name that should be connected. This can be the define of the table.
	 *  @param $columnNameAdditionalTable 	Name of the column in the connected table that has the foreign key to the class table
	 *  @param $columnNameClassTable 		Name of the column in the class table that has the foreign key to the connected table
	 *  @par Examples
	 *  @code  // Constructor of adm_dates object where the category (calendar) is connected
     *  public function __construct(&$db, $dat_id = 0)
     *  {
     *      $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'dat_cat_id');
     *      parent::__construct($db, TBL_DATES, 'dat', $dat_id);
     *  } @endcode
	 */
	protected function connectAdditionalTable($table, $columnNameAdditionalTable, $columnNameClassTable)
	{
		$this->additionalTables[] = array('table' => $table, 
										  'columnNameAdditionalTable' => $columnNameAdditionalTable, 
										  'columnNameClassTable' => $columnNameClassTable);
	}
    
	/** Reads the number of all records of this table
	 *  @return Number of records of this table
	 */
    public function countAllRecords()
    {
        $sql = 'SELECT COUNT(1) as count FROM '.$this->tableName;
        $this->db->query($sql);
        $row = $this->db->fetch_array();
        return $row['count'];
    }

	/** Deletes the selected record of the table and initializes the class
	 *  @return @b true if no error occured
	 */
    public function delete()
    {
		if(strlen($this->dbColumns[$this->keyColumnName]) > 0)
		{
			$sql    = 'DELETE FROM '.$this->tableName.' 
						WHERE '.$this->keyColumnName.' = \''. $this->dbColumns[$this->keyColumnName]. '\'';
			$this->db->query($sql);
		}

        $this->clear();
        return true;
    }

    /** Get the value of a column of the database table.
     *  If the value was manipulated before with @b setValue than the manipulated value is returned.
     *  @param $columnName	The name of the database column whose value should be read
     *  @param $format 		For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                 		For text columns the format can be @b database that would return the original database value without any transformations
     *  @return Returns the value of the database column.
     *          If the value was manipulated before with @b setValue than the manipulated value is returned.
     */ 
    public function getValue($columnName, $format = '')
    {
        global $gPreferences;
        $columnValue = '';
        
        if(isset($this->dbColumns[$columnName]))
        {
            // wenn Schluesselfeld leer ist, dann 0 zurueckgeben
            if($columnName == $this->keyColumnName && empty($this->dbColumns[$columnName]))
            {
                $columnValue = 0;
            }
            else
            {
                $columnValue = $this->dbColumns[$columnName];
            }
        }

        // if text field and format not 'database' then convert all quotes to html syntax
        if(isset($this->columnsInfos[$columnName]['type'])
		&& $format != 'database'
        && (strpos($this->columnsInfos[$columnName]['type'], 'char') !== false
           || strpos($this->columnsInfos[$columnName]['type'], 'text') !== false))
        {
            return htmlspecialchars($columnValue, ENT_QUOTES);
        }
        // in PostgreSQL we must encode the stored hex value back to binary
        elseif(isset($this->columnsInfos[$columnName]['type'])
        && strpos($this->columnsInfos[$columnName]['type'], 'bytea') !== false)
        {
            $columnValue = substr($columnValue, 2);
			$columnValue = pack('H*', $columnValue);
			return pack('H*', $columnValue);
        }        // Datum in dem uebergebenen Format bzw. Systemformat zurueckgeben
        elseif(isset($this->columnsInfos[$columnName]['type'])
        &&  (strpos($this->columnsInfos[$columnName]['type'], 'timestamp') !== false
            || strpos($this->columnsInfos[$columnName]['type'], 'date') !== false
            || strpos($this->columnsInfos[$columnName]['type'], 'time') !== false))
        {
            if(strlen($columnValue) > 0)
            {
                if(strlen($format) == 0 && isset($gPreferences))
                {
                    if(strpos($this->columnsInfos[$columnName]['type'], 'timestamp') !== false)
                    {
                        $format = $gPreferences['system_date'].' '.$gPreferences['system_time'];
                    }
                    elseif(strpos($this->columnsInfos[$columnName]['type'], 'date') !== false)
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
                    $datetime = new DateTime($columnValue);
                    $columnValue = $datetime->format($format);
                }
                catch(Exception $e)
                {
                    $columnValue = $this->dbColumns[$columnName];
                }
            }
            return $columnValue;
        }
        else
        {
            return $columnValue;
        }
    }
    
    /** If a column of the row in this object has changed throw setValue then this method
     *  will return @b true otherwise @false
     *  @return Returns @b true if at least one value of one column has changed 
     *          after the recordset was loaded otherwise @b false
     */
    public function hasColumnsValueChanged()
    {
        return $this->columnsValueChanged;
    }
	
	/** If the recordset is new and wasn't read from database or was not stored in database
	 *  then this method will return true otherwise false
	 *  @return Returns @b true if record is not stored in database
	 */
	public function isNewRecord()
	{
		return $this->new_record;
	}

	/** Reads a record out of the table in database selected by the conditions of the param @b $sqlWhereCondition out of the table.
	 *  If the sql will find more than one record the method returns @b false.
	 *  Per default all columns of the default table will be read and stored in the object.
	 *  @param $sqlWhereCondition Conditions for the table to select one record
	 *  @return Returns @b true if one record is found
	 */
    protected function readData($sqlWhereCondition)
    {
		$sqlAdditionalTables = '';

		// create sql to connect additional tables to the select statement
		if(count($this->additionalTables) > 0)
		{
			foreach($this->additionalTables as $key => $arrAdditionalTable)
			{
				$sqlAdditionalTables .= ', '.$arrAdditionalTable['table'];
				$sqlWhereCondition   .= ' AND '.$arrAdditionalTable['columnNameAdditionalTable'].' = '.$arrAdditionalTable['columnNameClassTable'].' ';
			}
		}

		// if condition starts with AND then remove this 
		if(strpos(strtoupper($sqlWhereCondition), 'AND') < 2)
		{
			$sqlWhereCondition = substr($sqlWhereCondition, 4);
		}
        
        if(strlen($sqlWhereCondition) > 0)
        {
            $sql = 'SELECT * FROM '.$this->tableName.$sqlAdditionalTables.'
                     WHERE '.$sqlWhereCondition;
            $result = $this->db->query($sql);
    
			if($this->db->num_rows($result) == 1)			
			{
				$row = $this->db->fetch_array($result, 'ASSOC');
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
	
	/** Reads a record out of the table in database selected by the unique id column in the table.
	 *  Per default all columns of the default table will be read and stored in the object.
	 *  @param $id Unique id of id column of the table.
	 *  @return Returns @b true if one record is found
	 */
	public function readDataById($id)
	{
		// initialize the object, so that all fields are empty
        $this->clear();

		// add id to sql condition
        if(strlen($id) > 0 && $id != '0')
        {
            $sqlWhereCondition = ' AND '.$this->keyColumnName.' = \''.$id.'\' ';
		
			// call method to read data out of database
			return $this->readData($sqlWhereCondition);
        }
		return false;
	}
	
	/** Reads a record out of the table in database selected by different columns in the table.
	 *  The columns are commited with an array where every element index is the column name and the value is the column value.
	 *  The columns and values must be selected so that they identify only one record. 
	 *  If the sql will find more than one record the method returns @b false.
	 *  Per default all columns of the default table will be read and stored in the object.
	 *  @param $columnArray An array where every element index is the column name and the value is the column value
	 *  @return Returns @b true if one record is found
	 *  @par Examples
	 *  @code  // reads data not be mem_id but with combination of role and user id
	 *  $member = new TableAccess($gDb, TBL_MEMBERS, 'rol');
	 *  $member->readDataByColumn(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId)); @endcode
	 */
	public function readDataByColumns($columnArray)
	{
		$returnCode = false;
		$sqlWhereCondition = '';

		// initialize the object, so that all fields are empty
        $this->clear();

        if(count($columnArray) > 0)
        {
			// add every array element as a sql condition to the condition string
			foreach($columnArray as $columnName => $columnValue)
			{
				$sqlWhereCondition .= ' AND '.$columnName.' = \''.$columnValue.'\' ';
			}
		
			// call method to read data out of database
			$returnCode = $this->readData($sqlWhereCondition);
			
			// save the array fields in the object
			if($returnCode == false)
			{
				foreach($columnArray as $columnName => $columnValue)
				{
					$this->setValue($columnName, $columnValue);
				}				
			}
        }
		return $returnCode;
	}
    
	/** Save all changed columns of the recordset in table of database. Therefore the class remembers if it's 
	 *  a new record or if only an update is neccessary. The update statement will only update
	 *  the changed columns. If the table has columns for creator or editor than these column
	 *  with their timestamp will be updated.
	 *  @param $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     *  @return If an update or insert into the database was done then return true, otherwise false.
	 */
    public function save($updateFingerPrint = true)
    {
        if($this->columnsValueChanged || strlen($this->dbColumns[$this->keyColumnName]) == 0)
        {
            // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
            $item_connection = '';                
            $sql_field_list  = '';
            $sql_value_list  = '';

            if($updateFingerPrint)
            {
                // besitzt die Tabelle Felder zum Speichern des Erstellers und der letzten Aenderung, 
                // dann diese hier automatisiert fuellen
                if($this->new_record && isset($this->dbColumns[$this->columnPraefix.'_usr_id_create']))
                {
                    global $gCurrentUser;
                    $this->setValue($this->columnPraefix.'_timestamp_create', DATETIME_NOW);
                    $this->setValue($this->columnPraefix.'_usr_id_create', $gCurrentUser->getValue('usr_id'));
                }
                elseif(isset($this->dbColumns[$this->columnPraefix.'_usr_id_change']))
                {
                    global $gCurrentUser;
                    // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
                    if(time() > (strtotime($this->getValue($this->columnPraefix.'_timestamp_create')) + 900)
                    || $gCurrentUser->getValue('usr_id') != $this->getValue($this->columnPraefix.'_usr_id_create'))
                    {
                        $this->setValue($this->columnPraefix.'_timestamp_change', DATETIME_NOW);
                        $this->setValue($this->columnPraefix.'_usr_id_change', $gCurrentUser->getValue('usr_id'));
                    }
                }
            }

            // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen                
            foreach($this->dbColumns as $key => $value)
            {
                // Auto-Increment-Felder duerfen nicht im Insert/Update erscheinen
                // Felder anderer Tabellen auch nicht
                if(strpos($key, $this->columnPraefix. '_') === 0
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
                $sql = 'INSERT INTO '.$this->tableName.' ('.$sql_field_list.') VALUES ('.$sql_value_list.') ';
                $this->db->query($sql);
                $this->new_record = false;
				if(strlen($this->keyColumnName) > 0)
				{
					$this->dbColumns[$this->keyColumnName] = $this->db->insert_id();
				}
            }
            else
            {
                $sql = 'UPDATE '.$this->tableName.' SET '.$sql_field_list.' 
                         WHERE '.$this->keyColumnName.' = \''. $this->dbColumns[$this->keyColumnName]. '\'';
                $this->db->query($sql);
            }
            
            $this->columnsValueChanged = false;
            return true;
        }

        return false;
    }

	/** The method requires an array with all fields of one recordset of the table object.
	 *  These fields will be add to the object as if you read one record with @b readDataById
	 *  but without a separate SQL. This method is useful if you have several recordsets of the
	 *  table and want to use an table object for each recordset. So you don't have to do an
	 *  separate sql read for each record. This is a performant way to fill the object with
	 *  the neccessary data.
	 *  @param $fieldArray An array with all fields and their values of the table. If the 
	 *   	   object has more connected tables than you should add the fields of these tables, too.
	 *  @par Examples
	 *  @code   // read all announcements with their categories
	 *  $sql = 'SELECT * FROM adm_announcements, adm_categories WHERE ann_cat_id = cat_id';
	 *  $result = $gDb->query($sql);
	 *  $announcement = new TableAnnouncements($gDb);
	 *  
	 *  while ($row = $gDb->fetch_array(result))
     *  {
	 *      // add each recordset to an object without a separate sql within the object
	 *      $announcement->clear();
     *      $announcement->setArray($row);
	 *      ...
	 *  } @endcode
	 */
    public function setArray($fieldArray)
    {
        foreach($fieldArray as $field => $value)
        {
            $this->dbColumns[$field] = $value;
            $this->columnsInfos[$field]['changed'] = false;
        }
        $this->new_record = false;
    }

    /** Set a new value for a column of the database table.
     *  The value is only saved in the object. You must call the method @b save to store the new value to the database
     *  @param $columnName	The name of the database column whose value should get a new value
     *  @param $newValue 	The new value that should be stored in the database field
     *  @param $checkValue 	The value will be checked if it's valid. If set to @b false than the value will not be checked.  
     *  @return Returns @b true if the value is stored in the current object and @b false if a check failed
     */ 
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        $return_code = false;

        if(array_key_exists($columnName, $this->dbColumns))
        {
            // Allgemeine Plausibilitaets-Checks anhand des Feldtyps
            if(strlen($newValue) > 0 && $checkValue == true)
            {
                // Numerische Felder
                if($this->columnsInfos[$columnName]['type'] == 'integer'
				|| $this->columnsInfos[$columnName]['type'] == 'smallint')
                {
                    if(is_numeric($newValue) == false)
                    {
                        $newValue = '';
                    }

                    // Schluesselfelder duerfen keine 0 enthalten
                    if(($this->columnsInfos[$columnName]['key'] == 1 || $this->columnsInfos[$columnName]['null'] == 1)
					&& $newValue == 0)
                    {
                        $newValue = '';
                    }
                }
                
                // Strings
                elseif(strpos($this->columnsInfos[$columnName]['type'], 'char') !== false
                ||     strpos($this->columnsInfos[$columnName]['type'], 'text') !== false)
                {
                    $newValue = strStripTags($newValue);
                }

                elseif($this->columnsInfos[$columnName]['type'] == 'blob'
                ||     $this->columnsInfos[$columnName]['type'] == 'bytea')
                {
	                // PostgreSQL can only store hex values in bytea, so we must decode binary in hex
                	if($this->columnsInfos[$columnName]['type'] == 'bytea')
                	{
	                    $newValue = bin2hex($newValue);
                	}
    	            // we must add slashes to binary data of blob fields so that the default stripslashes don't remove necessary slashes
                    $newValue = addslashes($newValue);
                }
            }

            // wurde das Schluesselfeld auf 0 gesetzt, dann soll ein neuer Datensatz angelegt werden
            if($columnName == $this->keyColumnName && $newValue == 0)
            {
                $this->new_record = true;
            }

            if(array_key_exists($columnName, $this->dbColumns))
            {
                $return_code = true;

                // only mark as "changed" if the value is different (use binary safe function!)
                if(strcmp($newValue, $this->dbColumns[$columnName]) != 0)
                {
                    $this->dbColumns[$columnName] = $newValue;
                    $this->columnsValueChanged    = true;
                    $this->columnsInfos[$columnName]['changed'] = true;
                }
            }
        }
        return $return_code;
    } 
}
?>