<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
/**
 * @class TableAccess
 * @brief Controls read and write access to datbase tables
 *
 * This class should help you to read and write records of database tables.
 * You create an object for a special table and than you are able to read
 * a special record, manipulate him and write him back. Also new records can
 * be created with this class. The advantage of this class is that you are
 * independent from SQL. You can use @c getValue, @c setValue, @c readData
 * and @c save to handle the record.
 * @par Examples
 * @code // create an object for table adm_roles of role 4711
 * $roleId = 4177;
 * $role = new TableAccess($gDb, TBL_ROLES, 'rol', $roleId);
 *
 * // read max. Members and add 1 to the count
 * $maxMembers = $role->getValue('rol_max_members');
 * $maxMembers = $maxMembers + 1;
 * $role->setValue('rol_max_members', $maxMembers);
 * $role->save(); @endcode
 */
class TableAccess
{
    protected $additionalTables;    ///< Array with sub array that contains additional tables and their connected fields that should be selected when data is read
    protected $tableName;           ///< Name of the database table of this object. This must be the table name with the installation specific praefix e.g. @b demo_users
    protected $columnPrefix;       ///< The praefix of each column that this table has. E.g. the table adm_users has the column praefix @b usr
    protected $keyColumnName;       ///< Name of the unique autoincrement index column of the database table
    protected $db;                  ///< An object of the class Database for communication with the database

    protected $new_record;          // Merker, ob ein neuer Datensatz oder vorhandener Datensatz bearbeitet wird
    protected $columnsValueChanged; ///< Flag will be set to true if data in array dbColumns was changed
    public $dbColumns = array();    // Array ueber alle Felder der entsprechenden Tabelle zu dem gewaehlten Datensatz
    public $columnsInfos = array(); // Array, welches weitere Informationen (geaendert ja/nein, Feldtyp) speichert

    /**
     * Constructor that will create an object of a recordset of the specified table.
     * If the id is set than this recordset will be loaded.
     * @param object     $database     Object of the class Database. This should be the default global object @b $gDb.
     * @param string     $tableName    The name of the database table. Because of specific praefixes this should be the define value e.g. @b TBL_USERS
     * @param string     $columnPrefix The prefix of each column of that table. E.g. for table @b adm_roles this is @b rol
     * @param string|int $id           The id of the recordset that should be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $tableName, $columnPrefix, $id = '')
    {
        $this->tableName    = $tableName;
        $this->columnPrefix = $columnPrefix;

        $this->setDatabase($database);

        // if a id is commited, then read data out of database
        if((!is_numeric($id) && $id !== '') || (is_numeric($id) && $id > 0))
        {
            $this->readDataById($id);
        }
        else
        {
            $this->clear();
        }
    }

    /**
     * Called on serialization of this object. The database object could not
     * be serialized and should be ignored.
     * @return array Returns all class variables that should be serialized.
     */
    public function __sleep()
    {
        return array_diff(array_keys(get_object_vars($this)), array('db'));
    }

    /**
     * Initializes all class parameters and deletes all read data.
     * Also the database structure of the associated table will be
     * read and stored in the arrays @b dbColumns and @b columnsInfos
     */
    public function clear()
    {
        $this->columnsValueChanged = false;
        $this->new_record = true;

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

    /**
     * Adds a table with the connected fields to a member array. This table will be add to the
     * select statement if data is read and the connected record is avaiable in this class.
     * The connected table must have a foreign key in the class table.
     * @param string $table                     Database table name that should be connected. This can be the define of the table.
     * @param string $columnNameAdditionalTable Name of the column in the connected table that has the foreign key to the class table
     * @param string $columnNameClassTable      Name of the column in the class table that has the foreign key to the connected table
     * @par Examples
     * @code // Constructor of adm_dates object where the category (calendar) is connected
     * public function __construct(&$db, $dat_id = 0)
     * {
     *     $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'dat_cat_id');
     *     parent::__construct($db, TBL_DATES, 'dat', $dat_id);
     * } @endcode
     */
    protected function connectAdditionalTable($table, $columnNameAdditionalTable, $columnNameClassTable)
    {
        $this->additionalTables[] = array(
            'table'                     => $table,
            'columnNameAdditionalTable' => $columnNameAdditionalTable,
            'columnNameClassTable'      => $columnNameClassTable
        );
    }

    /**
     * Reads the number of all records of this table
     * @return int Number of records of this table
     */
    public function countAllRecords()
    {
        $sql = 'SELECT COUNT(*) as count FROM '.$this->tableName;
        $countStatement = $this->db->query($sql);
        $row = $countStatement->fetch();
        return $row['count'];
    }

    /**
     * Deletes the selected record of the table and initializes the class
     * @return true Returns @b true if no error occurred
     */
    public function delete()
    {
        if(strlen($this->dbColumns[$this->keyColumnName]) > 0)
        {
            $sql = 'DELETE FROM '.$this->tableName.'
                     WHERE '.$this->keyColumnName.' = \''. $this->dbColumns[$this->keyColumnName]. '\'';
            $this->db->query($sql);
        }

        $this->clear();
        return true;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                           For text columns the format can be @b database that would return the original database value without any transformations
     * @return mixed Returns the value of the database column.
     *               If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @see TableAccess#setValue
     */
    public function getValue($columnName, $format = '')
    {
        global $gPreferences;
        $columnValue = '';

        if(array_key_exists((string) $columnName, $this->dbColumns))
        {
            // wenn Schluesselfeld leer ist, dann 0 zurueckgeben
            if($columnName === $this->keyColumnName && empty($this->dbColumns[$columnName]))
            {
                $columnValue = 0;
            }
            else
            {
                $columnValue = $this->dbColumns[$columnName];
            }
        }

        // if text field and format not 'database' then convert all quotes to html syntax
        if(array_key_exists((string) $columnName, $this->columnsInfos) && array_key_exists('type', $this->columnsInfos[$columnName]))
        {
            if($format !== 'database'
            && (strpos($this->columnsInfos[$columnName]['type'], 'char') !== false
                || strpos($this->columnsInfos[$columnName]['type'], 'text') !== false))
            {
                return htmlspecialchars($columnValue, ENT_QUOTES);
            }
            // in PostgreSQL we must encode the stored hex value back to binary
            elseif(strpos($this->columnsInfos[$columnName]['type'], 'bytea') !== false)
            {
                $columnValue = substr($columnValue, 2);
                $columnValue = pack('H*', $columnValue);
                return pack('H*', $columnValue);
            }        // Datum in dem uebergebenen Format bzw. Systemformat zurueckgeben
            elseif(strpos($this->columnsInfos[$columnName]['type'], 'timestamp') !== false
                || strpos($this->columnsInfos[$columnName]['type'], 'date') !== false
                || strpos($this->columnsInfos[$columnName]['type'], 'time') !== false)
            {
                if(strlen($columnValue) > 0)
                {
                    if($format === '' && isset($gPreferences))
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
        else
        {
            return $columnValue;
        }
    }

    /**
     * If a column of the row in this object has changed throw setValue then this method
     * will return @b true otherwise @false
     * @return bool Returns @b true if at least one value of one column has changed
     *              after the recordset was loaded otherwise @b false
     */
    public function hasColumnsValueChanged()
    {
        return $this->columnsValueChanged;
    }

    /**
     * If the recordset is new and wasn't read from database or was not stored in database
     * then this method will return true otherwise false
     * @return bool Returns @b true if record is not stored in database
     */
    public function isNewRecord()
    {
        return $this->new_record;
    }

    /**
     * Reads a record out of the table in database selected by the conditions of the param @b $sqlWhereCondition out of the table.
     * If the sql will find more than one record the method returns @b false.
     * Per default all columns of the default table will be read and stored in the object.
     * @param string $sqlWhereCondition Conditions for the table to select one record
     * @return bool Returns @b true if one record is found
     * @see TableAccess#readDataById
     * @see TableAccess#readDataByColumns
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

        if($sqlWhereCondition !== '')
        {
            $sql = 'SELECT *
                      FROM '.$this->tableName.$sqlAdditionalTables.'
                     WHERE '.$sqlWhereCondition;
            $readDataStatement = $this->db->query($sql);

            if($readDataStatement->rowCount() === 1)
            {
                $row = $readDataStatement->fetch();
                $this->new_record = false;

                // Daten in das Klassenarray schieben
                foreach($row as $key => $value)
                {
                    if($value === null)
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

    /**
     * Reads a record out of the table in database selected by the unique id column in the table.
     * Per default all columns of the default table will be read and stored in the object.
     * @param int|string $id Unique id of id column of the table.
     * @return bool Returns @b true if one record is found
     * @see TableAccess#readData
     * @see TableAccess#readDataByColumns
     */
    public function readDataById($id)
    {
        // initialize the object, so that all fields are empty
        $this->clear();

        // add id to sql condition
        if($id !== '' && $id != '0')
        {
            $sqlWhereCondition = ' AND '.$this->keyColumnName.' = \''.$id.'\' ';

            // call method to read data out of database
            return $this->readData($sqlWhereCondition);
        }
        return false;
    }

    /**
     * Reads a record out of the table in database selected by different columns in the table.
     * The columns are commited with an array where every element index is the column name and the value is the column value.
     * The columns and values must be selected so that they identify only one record.
     * If the sql will find more than one record the method returns @b false.
     * Per default all columns of the default table will be read and stored in the object.
     * @param array $columnArray An array where every element index is the column name and the value is the column value
     * @return bool Returns @b true if one record is found
     * @par Examples
     * @code // reads data not be mem_id but with combination of role and user id
     * $member = new TableAccess($gDb, TBL_MEMBERS, 'rol');
     * $member->readDataByColumn(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId)); @endcode
     * @see TableAccess#readData
     * @see TableAccess#readDataById
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
            if(!$returnCode)
            {
                foreach($columnArray as $columnName => $columnValue)
                {
                    $this->setValue($columnName, $columnValue);
                }
            }
        }
        return $returnCode;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset
     *                                if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        if($this->columnsValueChanged || $this->dbColumns[$this->keyColumnName] === '')
        {
            // SQL-Update-Statement fuer User-Tabelle zusammenbasteln
            $item_connection = '';
            $sql_field_list  = '';
            $sql_value_list  = '';

            if($updateFingerPrint)
            {
                // besitzt die Tabelle Felder zum Speichern des Erstellers und der letzten Aenderung,
                // dann diese hier automatisiert fuellen
                if($this->new_record && array_key_exists($this->columnPrefix . '_usr_id_create', $this->dbColumns))
                {
                    global $gCurrentUser;
                    $this->setValue($this->columnPrefix.'_timestamp_create', DATETIME_NOW);
                    $this->setValue($this->columnPrefix.'_usr_id_create', $gCurrentUser->getValue('usr_id'));
                }
                elseif(array_key_exists($this->columnPrefix . '_usr_id_change', $this->dbColumns))
                {
                    global $gCurrentUser;
                    // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
                    if(time() > (strtotime($this->getValue($this->columnPrefix.'_timestamp_create')) + 900)
                    || $gCurrentUser->getValue('usr_id') != $this->getValue($this->columnPrefix.'_usr_id_create'))
                    {
                        $this->setValue($this->columnPrefix.'_timestamp_change', DATETIME_NOW);
                        $this->setValue($this->columnPrefix.'_usr_id_change', $gCurrentUser->getValue('usr_id'));
                    }
                }
            }

            // Schleife ueber alle DB-Felder und diese dem Update hinzufuegen
            foreach($this->dbColumns as $key => $value)
            {
                // Auto-Increment-Felder duerfen nicht im Insert/Update erscheinen
                // Felder anderer Tabellen auch nicht
                if(strpos($key, $this->columnPrefix. '_') === 0
                && $this->columnsInfos[$key]['serial'] == 0
                && $this->columnsInfos[$key]['changed'])
                {
                    if($this->new_record)
                    {
                        if($value !== '')
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
                        if($value === '' || $value === null)
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
                    if($item_connection === '' && $sql_field_list !== '')
                    {
                        $item_connection = ',';
                    }
                    $this->columnsInfos[$key]['changed'] = false;
                }
            }

            if($this->new_record)
            {
                // insert record and mark this object as not new and remember the new id
                $sql = 'INSERT INTO '.$this->tableName.' ('.$sql_field_list.') VALUES ('.$sql_value_list.') ';
                $this->db->query($sql);
                $this->new_record = false;
                if($this->keyColumnName !== '')
                {
                    $this->dbColumns[$this->keyColumnName] = $this->db->lastInsertId();
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

    /**
     * The method requires an array with all fields of one recordset of the table object.
     * These fields will be add to the object as if you read one record with @b readDataById
     * but without a separate SQL. This method is useful if you have several recordsets of the
     * table and want to use an table object for each recordset. So you don't have to do an
     * separate sql read for each record. This is a performant way to fill the object with
     * the necessary data.
     * @param array $fieldArray An array with all fields and their values of the table. If the
     *                           object has more connected tables than you should add the fields of these tables, too.
     * @par Examples
     * @code // read all announcements with their categories
     * $sql = 'SELECT * FROM adm_announcements, adm_categories WHERE ann_cat_id = cat_id';
     * $announcementsStatement = $gDb->query($sql);
     * $announcement = new TableAnnouncements($gDb);
     *
     * while ($row = $announcementsStatement->fetch())
     * {
     *     // add each recordset to an object without a separate sql within the object
     *     $announcement->clear();
     *     $announcement->setArray($row);
     *     ...
     * } @endcode
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

    /**
     * Set the database object for communication with the database of this class.
     * @param object $database An object of the class Database. This should be the global $gDb object.
     */
    public function setDatabase(&$database)
    {
        if(is_object($database))
        {
            $this->db =& $database;
        }
    }

    /**
     * Set a new value for a column of the database table. The value is only saved in the object.
     * You must call the method @b save to store the new value to the database. If the unique key
     * column is set to 0 than this record will be a new record and all other columns are marked as changed.
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.
     * @return bool Returns @b true if the value is stored in the current object and @b false if a check failed
     * @see TableAccess#getValue
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        if(array_key_exists($columnName, $this->dbColumns))
        {
            // Allgemeine Plausibilitaets-Checks anhand des Feldtyps
            if($newValue !== '' && $checkValue)
            {
                // Numerische Felder
                if($this->columnsInfos[$columnName]['type'] === 'integer'
                || $this->columnsInfos[$columnName]['type'] === 'smallint')
                {
                    if(!is_numeric($newValue))
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

                elseif($this->columnsInfos[$columnName]['type'] === 'blob'
                ||     $this->columnsInfos[$columnName]['type'] === 'bytea')
                {
                    // PostgreSQL can only store hex values in bytea, so we must decode binary in hex
                    if($this->columnsInfos[$columnName]['type'] === 'bytea')
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

                // now mark all other columns with values of this object as changed
                foreach($this->dbColumns as $column => $value)
                {
                    if(strlen($value) > 0)
                    {
                        $this->columnsInfos[$column]['changed'] = true;
                    }
                }
            }

            if(array_key_exists($columnName, $this->dbColumns))
            {
                // only mark as "changed" if the value is different (use binary safe function!)
                if(strcmp($newValue, $this->dbColumns[$columnName]) !== 0)
                {
                    $this->dbColumns[$columnName] = $newValue;
                    $this->columnsValueChanged = true;
                    $this->columnsInfos[$columnName]['changed'] = true;
                }

                return true;
            }
        }
        return false;
    }
}
