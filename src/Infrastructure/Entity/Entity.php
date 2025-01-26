<?php
namespace Admidio\Infrastructure\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Users\Entity\User;
use Ramsey\Uuid\Uuid;

/**
 * @brief Controls read and write access to database tables
 *
 * This class should help you to read and write records of database tables.
 * You create an object for a special table, and then you are able to read
 * a special record, manipulate him and write him back. Also, new records can
 * be created with this class. The advantage of this class is that you are
 * independent of SQL. You can use @c getValue, @c setValue, @c readData
 * and @c save to handle the record.
 *
 * **Code example**
 * ```
 * // create an object for table adm_roles of role 4711
 * $roleId = 4177;
 * $role = new Entity($gDb, TBL_ROLES, 'rol', $roleId);
 *
 * // read max. Members and add 1 to the count
 * $maxMembers = $role->getValue('rol_max_members');
 * $maxMembers = $maxMembers + 1;
 * $role->setValue('rol_max_members', $maxMembers);
 * $role->save();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Entity
{
    /**
     * @var array<string,string> Array with sub array that contains additional tables and their connected fields that should be selected when data is read
     */
    protected array $additionalTables = array();
    /**
     * @var string Name of the database table of this object. This must be the table name with the installation specific prefix e.g. **demo_users**
     */
    protected string $tableName;
    /**
     * @var string The prefix of each column that this table has. E.g. the table adm_users has the column prefix **usr**
     */
    protected string $columnPrefix;
    /**
     * @var string Name of the unique autoincrement index column of the database table
     */
    protected string $keyColumnName;
    /**
     * @var Database An object of the class Database for communication with the database
     */
    protected Database $db;

    /**
     * @var bool Flag whether a new data set or existing data set is being edited
     */
    protected bool $newRecord;
    /**
     * @var bool Flag if the data of this record must be inserted or updated
     */
    protected bool $insertRecord;
    /**
     * @var bool Flag will be set to true if data in array dbColumns was changed
     */
    protected bool $columnsValueChanged;
    /**
     * @var array<string,mixed> Array over all fields of the corresponding table for the selected record
     */
    protected array $dbColumns = array();
    /**
     * @var array<string,array<string,mixed>> Array which stores further information (changed yes/no, field type)
     */
    protected array $columnsInfos = array();
    /**
     * @var bool If this flag is set then some right checks will be disabled, so that the object could be saved also
     * if the current user doesn't have the right to do this.
     */
    protected bool $saveChangesWithoutRights;

    /**
     * Constructor that will create an object of a recordset of the specified table.
     * If the id is set than this recordset will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $tableName The name of the database table. Because of specific prefixes this should be the defined value e.g. **TBL_USERS**
     * @param string $columnPrefix The prefix of each column of that table. E.g. for table **adm_roles** this is **rol**
     * @param string|int $id The id of the recordset that should be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, string $tableName, string $columnPrefix, $id = '')
    {
        $this->db          =& $database;
        $this->tableName    = $tableName;
        $this->columnPrefix = $columnPrefix;

        // if an ID is committed, then read data out of database
        if ($id > 0) {
            $this->readDataById($id);
        } else {
            $this->clear();
        }
    }

    /**
     * A wakeup add the current database object to this class
     */
    public function __wakeup()
    {
        global $gDb;

        if ($gDb instanceof Database) {
            $this->db = $gDb;
        }
    }

    /**
     * Initializes all class parameters and deletes all read data.
     * Also, the database structure of the associated table will be
     * read and stored in the arrays **dbColumns** and **columnsInfos**
     * @throws Exception
     */
    public function clear()
    {
        $this->newRecord = true;
        $this->insertRecord = true;
        $this->columnsValueChanged = false;
        $this->saveChangesWithoutRights = false;

        if (count($this->columnsInfos) > 0) {
            // the column infos have already been read and will now only be reinitialized
            foreach ($this->dbColumns as $fieldName => &$fieldValue) {
                $fieldValue = ''; // $this->dbColumns[$fieldName] = '';
                $this->columnsInfos[$fieldName]['changed'] = false;
            }
            unset($fieldValue);
        } else {
            // read all columns information of the tables
            $this->setColumnsInfos();
        }
    }

    /**
     * Adds a table with the connected fields to a member array. This table will be added to the
     * select statement if data is read and the connected record is available in this class.
     * The connected table must have a foreign key in the class table.
     * @param string $table                     Database table name that should be connected.
     * @param string $columnNameAdditionalTable Name of the column in the connected table that has the foreign key to the class table
     * @param string $columnNameClassTable      Name of the column in the class table that has the foreign key to the connected table
     *
     * **Code example**
     * ```
     * // Constructor of adm_events object where the category (calendar) is connected
     * public function __construct($database, $datId = 0)
     * {
     *     $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'dat_cat_id');
     *     parent::__construct($db, TBL_EVENTS, 'dat', $datId);
     * }
     * ```
     */
    public function connectAdditionalTable(string $table, string $columnNameAdditionalTable, string $columnNameClassTable)
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
     * @throws Exception
     */
    public function countAllRecords(): int
    {
        $sql = 'SELECT COUNT(*) AS count FROM '.$this->tableName;
        $countStatement = $this->db->queryPrepared($sql);

        return (int) $countStatement->fetchColumn();
    }

    /**
     * Deletes the selected record of the table and initializes the class
     * @return true Returns **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
    {
        if (array_key_exists($this->keyColumnName, $this->dbColumns) && $this->dbColumns[$this->keyColumnName] !== '') {
            $sql = 'DELETE FROM '.$this->tableName.'
                     WHERE '.$this->keyColumnName.' = ? -- $this->dbColumns[$this->keyColumnName]';
            $this->db->queryPrepared($sql, array($this->dbColumns[$this->keyColumnName]));
        }

        $this->clear();
        return true;
    }

    /**
     * Get the first name and last name of the person who has created this record. In dependence of the preference
     * system_show_create_edit the login name will be shown. If the current user has a valid login and the
     * parameter **$linkToProfile** is set than a html link to the profile is set around the name.
     * @param bool $linkToProfile If set to **true** a link to the profile is set around the name.
     * @return string Returns the first name and last name of the person optional with a link to the profile.
     * @throws Exception
     */
    public function getNameOfCreatingUser(bool $linkToProfile = true): string
    {
        global $gDb, $gProfileFields, $gL10n, $gSettingsManager, $gValidLogin;

        $nameOfCreatingUser = '';

        if ($this->getValue($this->columnPrefix . '_timestamp_create') !== '') {
            if ((int)$this->getValue($this->columnPrefix . '_usr_id_create') > 0) {
                $userCreated = new User($gDb, $gProfileFields, $this->getValue($this->columnPrefix . '_usr_id_create'));

                if ((int) $gSettingsManager->get('system_show_create_edit') === 1) {
                    $nameOfCreatingUser = $userCreated->getValue('FIRST_NAME') . ' ' . $userCreated->getValue('LAST_NAME');
                } else {
                    $nameOfCreatingUser = $userCreated->getValue('usr_login_name');
                }

                // if valid login and a user id is given than create a link to the profile of this user
                if ($linkToProfile && $gValidLogin && $nameOfCreatingUser !== $gL10n->get('SYS_SYSTEM')) {
                    $nameOfCreatingUser = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $userCreated->getValue('usr_uuid'))) .
                        '">' . $nameOfCreatingUser . '</a>';
                }
            } else {
                $nameOfCreatingUser = $gL10n->get('SYS_DELETED_USER');
            }
        }
        return $nameOfCreatingUser;
    }

    /**
     * Get the first name and last name of the person who was the last editor of this record. In dependence of the preference
     * system_show_create_edit the login name will be shown. If the current user has a valid login and the
     * parameter **$linkToProfile** is set than a html link to the profile is set around the name.
     * @param bool $linkToProfile If set to **true** a link to the profile is set around the name.
     * @return string Returns the first name and last name of the person optional with a link to the profile.
     * @throws Exception
     */
    public function getNameOfLastEditingUser(bool $linkToProfile = true): string
    {
        global $gDb, $gProfileFields, $gL10n, $gSettingsManager, $gValidLogin;

        $nameOfLastEditingUser = '';

        if ($this->getValue($this->columnPrefix . '_timestamp_change') !== '') {
            if ((int)$this->getValue($this->columnPrefix . '_usr_id_change') > 0) {
                $userLastEdited = new User($gDb, $gProfileFields, $this->getValue($this->columnPrefix . '_usr_id_change'));

                if ((int) $gSettingsManager->get('system_show_create_edit') === 1) {
                    $nameOfLastEditingUser = $userLastEdited->getValue('FIRST_NAME') . ' ' . $userLastEdited->getValue('LAST_NAME');
                } else {
                    $nameOfLastEditingUser = $userLastEdited->getValue('usr_login_name');
                }

                // if valid login and a user id is given than create a link to the profile of this user
                if ($linkToProfile && $gValidLogin && $nameOfLastEditingUser !== $gL10n->get('SYS_SYSTEM')) {
                    $nameOfLastEditingUser = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $userLastEdited->getValue('usr_uuid'))) .
                        '">' . $nameOfLastEditingUser . '</a>';
                }
            } else {
                $nameOfLastEditingUser = $gL10n->get('SYS_DELETED_USER');
            }
        }
        return $nameOfLastEditingUser;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return mixed Returns the value of the database column.
     *               If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @throws Exception
     * @see Entity#setValue
     */
    public function getValue(string $columnName, string $format = '')
    {
        global $gSettingsManager;

        $columnValue = '';

        if (array_key_exists($columnName, $this->dbColumns)) {
            // if key field is empty, return 0
            if ($this->keyColumnName === $columnName && empty($this->dbColumns[$columnName])) {
                $columnValue = 0;
            } else {
                $columnValue = $this->dbColumns[$columnName];
            }
        }

        if (array_key_exists($columnName, $this->columnsInfos) && array_key_exists('type', $this->columnsInfos[$columnName])) {
            switch ($this->columnsInfos[$columnName]['type']) {
                // String
                case 'char': // fallthrough
                case 'varchar': // fallthrough
                case 'text':
                    if ($format !== 'database') {
                        // if text field and format not 'database' then convert all quotes to html syntax
                        $columnValue = SecurityUtils::encodeHTML((string) $columnValue);
                    }
                    break;

                case 'bytea':
                    // For Postgres, we must encode the stored resource hex value back to binary
                    if (is_resource($columnValue)) {
                        ob_start();
                        fpassthru($columnValue);
                        $columnValue = hex2bin(ob_get_contents());
                        ob_end_clean();
                        $this->dbColumns[$columnName] = $columnValue;
                    }
                    break;

                case 'timestamp': // fallthrough
                case 'date': // fallthrough
                case 'time':
                    if ($columnValue !== '' && $columnValue !== null) {
                        if ($format === '' && isset($gSettingsManager)) {
                            if (str_contains($this->columnsInfos[$columnName]['type'], 'timestamp')) {
                                $format = $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time');
                            } elseif (str_contains($this->columnsInfos[$columnName]['type'], 'date')) {
                                $format = $gSettingsManager->getString('system_date');
                            } else {
                                $format = $gSettingsManager->getString('system_time');
                            }
                        }

                        // try to format the date, else output the available data
                        try {
                            $datetime = new \DateTime($columnValue);
                            $columnValue = $datetime->format($format);
                        } catch (Exception $e) {
                            $columnValue = $this->dbColumns[$columnName];
                        }
                    } else {
                        $columnValue = '';
                    }
                    break;
            }
        }

        return $columnValue;
    }

    /**
     * If a column of the row in this object has changed throw setValue then this method
     * will return **true** otherwise @false
     * @return bool Returns **true** if at least one value of one column has changed
     *              after the recordset was loaded otherwise **false**
     */
    public function hasColumnsValueChanged(): bool
    {
        return $this->columnsValueChanged;
    }

    /**
     * If the recordset is new and wasn't read from database or was not stored in database
     * then this method will return true otherwise false
     * @return bool Returns **true** if record is not stored in database
     */
    public function isNewRecord(): bool
    {
        return $this->newRecord;
    }

    /**
     * Reads a record out of the table in database selected by the conditions of the param **$sqlWhereCondition** out of the table.
     * If the sql find more than one record the method returns **false**.
     * Per default all columns of the default table will be read and stored in the object.
     * @param string $sqlWhereCondition Conditions for the table to select one record
     * @param array<int,mixed> $queryParams The query params for the prepared statement
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readDataById
     * @see Entity#readDataByUuid
     */
    protected function readData(string $sqlWhereCondition, array $queryParams = array()): bool
    {
        $sqlAdditionalTables = '';

        // create sql to connect additional tables to the select statement
        if (count($this->additionalTables) > 0) {
            foreach ($this->additionalTables as $arrAdditionalTable) {
                $sqlAdditionalTables .= ', '.$arrAdditionalTable['table'];
                $sqlWhereCondition   .= ' AND '.$arrAdditionalTable['columnNameAdditionalTable'].' = '.$arrAdditionalTable['columnNameClassTable'].' ';
            }
        }

        // if condition starts with AND then remove this
        if (StringUtils::strStartsWith(ltrim($sqlWhereCondition), 'AND', false)) {
            $sqlWhereCondition = substr($sqlWhereCondition, 4);
        }

        if ($sqlWhereCondition !== '') {
            $sql = 'SELECT *
                      FROM '.$this->tableName.'
                           '.$sqlAdditionalTables.'
                     WHERE '.$sqlWhereCondition;
            $readDataStatement = $this->db->queryPrepared($sql, $queryParams); // TODO add more params

            if ($readDataStatement->rowCount() === 1) {
                $row = $readDataStatement->fetch();
                $this->newRecord = false;
                $this->insertRecord = false;

                // move data to class column value array
                foreach ($row as $key => $value) {
                    if ($this->columnsInfos[$key]['type'] === 'boolean'
                            || $this->columnsInfos[$key]['type'] === 'tinyint') {
                        $this->dbColumns[$key] = (bool) $value;
                    } elseif (($this->columnsInfos[$key]['type'] === 'integer'
                            || $this->columnsInfos[$key]['type'] === 'smallint')
                    && $value != '') {
                        // only convert to int if it's not a null value
                        $this->dbColumns[$key] = (int) $value;
                    } else {
                        $this->dbColumns[$key] = $value;
                    }
                }

                return true;
            }

            $this->clear();
        }

        return false;
    }

    /**
     * Reads a record out of the table in database selected by the unique id column in the table.
     * Per default all columns of the default table will be read and stored in the object.
     * @param int $id Unique id of id column of the table.
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readData
     * @see Entity#readDataByUuid
     */
    public function readDataById(int $id): bool
    {
        // initialize the object, so that all fields are empty
        $this->clear();

        // add id to sql condition
        if ($id > 0) {
            // call method to read data out of database
            return $this->readData(' AND ' . $this->keyColumnName . ' = ? ', array($id));
        }

        return false;
    }

    /**
     * Reads a record out of the table in database selected by the unique uuid column in the table.
     * The name of the column must have the syntax table_prefix, underscore and uuid. E.g. usr_uuid.
     * Per default all columns of the default table will be read and stored in the object.
     * Not every Admidio table has a UUID. Please check the database structure before you use this method.
     * @param string $uuid Unique uuid that should be searched.
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readData
     * @see Entity#readDataById
     */
    public function readDataByUuid(string $uuid): bool
    {
        // initialize the object, so that all fields are empty
        $this->clear();

        // add id to sql condition
        if ($uuid !== '') {
            // call method to read data out of database
            return $this->readData(' AND ' . $this->columnPrefix . '_uuid = ? ', array($uuid));
        }

        return false;
    }

    /**
     * Reads a record out of the table in database selected by different columns in the table.
     * The columns are committed with an array where every element index is the column name and the value is the column value.
     * If you want a column to be null than set the value to **NULL**
     * The columns and values must be selected so that they identify only one record.
     * If the sql find more than one record the method returns **false**.
     * Per default all columns of the default table will be read and stored in the object.
     * @param array<string,mixed> $columnArray An array where every element index is the column name and the value is the column value
     * @return bool Returns **true** if one record is found
     *
     * **Code example**
     * ```
     * // reads data not be mem_id but with combination of role and user id
     * $member = new Entity($gDb, TBL_MEMBERS, 'rol');
     * $member->readDataByColumn(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
     * ```
     * @throws Exception
     * @see Entity#readDataByUuid
     * @see Entity#readData
     * @see Entity#readDataById
     */
    public function readDataByColumns(array $columnArray): bool
    {
        // initialize the object, so that all fields are empty
        $this->clear();

        if (count($columnArray) === 0) {
            return false;
        }

        $sqlWhereCondition = '';
        $sqlParams = array();

        // add every array element as a sql condition to the condition string
        foreach ($columnArray as $columnName => $columnValue) {
            if ($columnValue === 'NULL') {
                $sqlWhereCondition .= ' AND ' . $columnName . ' IS NULL ';
            } else {
                $sqlWhereCondition .= ' AND ' . $columnName . ' = ? ';
                $sqlParams[] = $columnValue;
            }
        }

        // call method to read data out of database
        $returnCode = $this->readData($sqlWhereCondition, array_values($sqlParams));

        // if no record was found then save the array fields in the object
        if (!$returnCode) {
            foreach ($columnArray as $columnName => $columnValue) {
                if(str_starts_with($columnName, $this->columnPrefix . '_')) {
                    $this->setValue($columnName, $columnValue);
                }
            }
        }

        return $returnCode;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * For a new record if there is an uuid column a new uuid will be created and stored.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset
     *                                if table has columns like **usr_id_create** or **usr_id_change**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        if (!$this->columnsValueChanged && $this->dbColumns[$this->keyColumnName] !== '') {
            return false;
        }

        // if new role then set create the uuid
        if ($this->isNewRecord()
        && array_key_exists($this->columnPrefix . '_uuid', $this->dbColumns)
        && (string) $this->getValue($this->columnPrefix . '_uuid') === '') {
            $this->setValue($this->columnPrefix . '_uuid', (string) Uuid::uuid4());
        }

        // TODO check if "$gCurrentUser instanceof User"
        // see "start_installation.php"
        if ($updateFingerPrint && isset($GLOBALS['gCurrentUserId']) && $GLOBALS['gCurrentUserId'] > 0) {
            // if the table has fields to store the creator and the last change,
            // then fill them here automatically
            if ($this->newRecord && $this->insertRecord && array_key_exists($this->columnPrefix . '_usr_id_create', $this->dbColumns)) {
                $this->setValue($this->columnPrefix . '_timestamp_create', DATETIME_NOW);
                $this->setValue($this->columnPrefix . '_usr_id_create', $GLOBALS['gCurrentUserId']);
            } elseif (array_key_exists($this->columnPrefix . '_usr_id_change', $this->dbColumns)) {
                // Do not update data if the same user has done so within 15 minutes
                if ($GLOBALS['gCurrentUserId'] !== $this->getValue($this->columnPrefix . '_usr_id_create')
                || time() > (strtotime($this->getValue($this->columnPrefix . '_timestamp_create')) + 900)) {
                    $this->setValue($this->columnPrefix . '_timestamp_change', DATETIME_NOW);
                    $this->setValue($this->columnPrefix . '_usr_id_change', $GLOBALS['gCurrentUserId']);
                }
            }
        }

        $sqlFieldArray = array();
        $sqlSetArray = array();
        $queryParams = array();
        $returnCode = false;

        // Loop over all DB fields and add them to the update
        foreach ($this->dbColumns as $key => $value) {
            // fields of other tables must not appear in insert/update
            if (str_starts_with($key, $this->columnPrefix . '_')) {
                if ($this->columnsInfos[$key]['type'] === 'boolean' && DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
                    if ($value || $value === '1') {
                        $value = 'true';
                    } else {
                        $value = 'false';
                    }
                }

                // Auto-increment fields must not appear in insert/update
                if (!$this->columnsInfos[$key]['serial'] && $this->columnsInfos[$key]['changed']) {
                    if ($this->insertRecord) {
                        // Prepare data for an insert
                        if ($value !== '') {
                            $sqlFieldArray[] = $key;
                            $queryParams[] = $value;
                        }
                    } else {
                        // Prepare data for an update
                        $sqlSetArray[] = $key . ' = ?';

                        if ($value === '' || $value === null) {
                            $queryParams[] = null;
                        } else {
                            $queryParams[] = $value;
                        }
                    }

                    $this->columnsInfos[$key]['changed'] = false;
                }
            }
        }

        if ($this->insertRecord) {
            // insert record and remember the new id
            $sql = 'INSERT INTO '.$this->tableName.'
                           ('.implode(',', $sqlFieldArray).')
                    VALUES ('.Database::getQmForValues($sqlFieldArray).')';
            if ($this->db->queryPrepared($sql, $queryParams) !== false) {
                $returnCode = true;
                $this->insertRecord = false;
                if ($this->keyColumnName !== '') {
                    $this->dbColumns[$this->keyColumnName] = $this->db->lastInsertId();
                }
            }
        } else {
            $sql = 'UPDATE '.$this->tableName.'
                       SET '.implode(', ', $sqlSetArray).'
                     WHERE '.$this->keyColumnName.' = ? -- $this->dbColumns[$this->keyColumnName]';
            $queryParams[] = $this->dbColumns[$this->keyColumnName];
            if ($this->db->queryPrepared($sql, $queryParams) !== false) {
                $returnCode = true;
            }
        }

        $this->columnsValueChanged = false;

        return $returnCode;
    }

    /**
     * If this method is set then the current user can save changes to this object if he hasn't the necessary rights.
     * The flag must be used within the class implementation.
     * @return void
     */
    public function saveChangesWithoutRights()
    {
        $this->saveChangesWithoutRights = true;
    }

    /**
     * The method requires an array with all fields of one recordset of the table object.
     * These fields will be added to the object as if you read one record with **readDataById**
     * but without a separate SQL. This method is useful if you have several recordset of the
     * table and want to use a table object for each recordset. So you don't have to do a
     * separate sql read for each record. This is a performant way to fill the object with
     * the necessary data.
     * @param array $fieldArray An array with all fields and their values of the table. If the object has
     *                          more connected tables than you should add the fields of these tables, too.
     *
     * **Code example**
     * ```
     * // read all announcements with their categories
     * $sql = 'SELECT * FROM ' . TBL_ANNOUNCEMENTS . ' INNER JOIN ' . TBL_CATEGORIES . ' ON ann_cat_id = cat_id';
     * $announcementsStatement = $gDb->queryPrepared($sql);
     * $announcement = new Announcements($gDb);
     *
     * while ($row = $announcementsStatement->fetch())
     * {
     *     // add each recordset to an object without a separate sql within the object
     *     $announcement->clear();
     *     $announcement->setArray($row);
     *     ...
     * }
     * ```
     * @throws Exception
     */
    public function setArray(array $fieldArray)
    {
        foreach ($fieldArray as $field => $value) {
            $this->dbColumns[$field] = $value;
            $this->columnsInfos[$field]['changed'] = false;
        }

        if (empty($this->dbColumns[$this->keyColumnName])) {
            $this->setNewRecord();
        } else {
            $this->newRecord = false;
            $this->insertRecord = false;
        }
    }

    /**
     * Read all columns with their information like **type** (integer, varchar, boolean),
     * **null** (or not), **key** and **serial**. Also, the changed flag will be set to false.
     * @throws Exception
     */
    protected function setColumnsInfos()
    {
        // create array with base table and all connected tables
        $tables = array($this->tableName);

        foreach ($this->additionalTables as $values) {
            $tables[] = $values['table'];
        }

        foreach ($tables as $table) {
            $tableColumnsProperties = $this->db->getTableColumnsProperties($table);

            foreach ($tableColumnsProperties as $columnName => $property) {
                // some actions should only be done for columns of the main table from this class
                if (str_starts_with($columnName, $this->columnPrefix . '_')) {
                    $this->dbColumns[$columnName] = '';

                    if ($property['serial']) {
                        $this->keyColumnName = $columnName;
                    }
                }
                $this->columnsInfos[$columnName]['changed'] = false;
                if(strpos($property['type'], '(') > 0) {
                    $this->columnsInfos[$columnName]['type'] = substr($property['type'], 0, strpos($property['type'], '('));
                } else {
                    $this->columnsInfos[$columnName]['type'] = $property['type'];
                }
                $this->columnsInfos[$columnName]['null'] = $property['null'];
                $this->columnsInfos[$columnName]['key'] = $property['key'];
                $this->columnsInfos[$columnName]['serial'] = $property['serial'];
            }
        }
    }

    /**
     * Use this method if you have read a record from the database and want to use this data to create a new record.
     * Method set the flag that it's a new record and initialize the ID and set a new UUID if that column exists.
     * @return void
     * @throws Exception
     */
    public function setNewRecord()
    {
        $this->newRecord = true;
        $this->insertRecord = true;

        if (array_key_exists($this->columnPrefix . '_id', $this->dbColumns)) {
            $this->setValue($this->columnPrefix . '_id', 0);
        }
        if (array_key_exists($this->columnPrefix . '_uuid', $this->dbColumns)) {
            $this->setValue($this->columnPrefix . '_uuid', (string) Uuid::uuid4());
        }
    }

    /**
     * Set a new value for a column of the database table. The value is only saved in the object.
     * You must call the method **save** to store the new value to the database. If the unique key
     * column is set to 0 than this record will be a new record and all other columns are marked as changed.
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @throws Exception If **columnName** doesn't exist. exception->text contains a string with the reason why the login failed.
     * @see Entity#getValue
     */
    public function setValue(string $columnName, $newValue, bool $checkValue = true): bool
    {
        if (!array_key_exists($columnName, $this->dbColumns)) {
            throw new Exception('Column ' . $columnName . ' does not exists in table ' . $this->tableName . '!');
        }

        // General plausibility checks based on the field type
        if ($checkValue && $newValue !== '') {
            switch ($this->columnsInfos[$columnName]['type']) {
                // Numeric
                case 'integer': // fallthrough
                case 'smallint':
                    if (!is_numeric($newValue)) {
                        $newValue = '';
                    }

                    // Key fields should not contain 0
                    if ((int) $newValue === 0 &&
                        ($this->columnsInfos[$columnName]['key'] || $this->columnsInfos[$columnName]['null'])) {
                        $newValue = '';
                    }
                    break;

                // String
                case 'char': // fallthrough
                case 'varchar': // fallthrough
                case 'text':
                    $newValue = StringUtils::strStripTags($newValue);
                    break;

                // Byte/Blob
                case 'bytea':
                    // Postgres can only store hex values in bytea, so we must decode binary in hex
                    $newValue = bin2hex($newValue);
                    break;
            }
        }

        // if the key field was set to 0, then a new record is to be created
        if ($this->keyColumnName === $columnName && (int) $newValue === 0) {
            $this->newRecord = true;
            $this->insertRecord = true;

            // now mark all other columns with values of this object as changed
            foreach ($this->dbColumns as $column => $value) {
                if ((is_array($value) && count($value)>0) || (strlen((string) $value) > 0)) {
                    $this->columnsInfos[$column]['changed'] = true;
                }
            }
        }

        // only mark as "changed" if the value is different (DON'T use binary safe function!)
        if (strcmp((string) $this->dbColumns[$columnName], (string) $newValue) !== 0) {
            $this->dbColumns[$columnName] = $newValue;
            $this->columnsValueChanged = true;
            $this->columnsInfos[$columnName]['changed'] = true;
        }

        return true;
    }
}
