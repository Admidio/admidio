<?php
/**
 ***********************************************************************************************
 * Class manages the list configuration
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class creates a list configuration object. With this object it's possible
 * to manage the configuration in the database. You can easily create new lists,
 * add new columns or remove columns.
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * readColumns()         - Daten der zugehoerigen Spalten einlesen und in Objekten speichern
 * addColumn($number, $field, $sort = "", $condition = "")
 *                       - fuegt eine neue Spalte dem Spaltenarray hinzu
 * deleteColumn($number, $all = false)
 *                       - entfernt die entsprechende Spalte aus der Konfiguration
 * countColumns()        - Anzahl der Spalten der Liste zurueckgeben
 * getSQL($roleIds, $showFormerMembers = false)
 *                       - gibt das passende SQL-Statement zu der Liste zurueck
 */
class ListConfiguration extends TableLists
{
    /**
     * @var array<int,TableAccess> Array with all Listenspaltenobjekte
     */
    protected $columns = array();

    /**
     * Constructor that will create an object to handle the configuration of lists.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $lstId    The id of the recordset that should be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $lstId = 0)
    {
        parent::__construct($database, $lstId);

        if($lstId > 0)
        {
            $this->readColumns();
        }
    }

    /**
     * Add new column to column array
     * @param int        $number
     * @param int|string $field
     * @param string     $sort
     * @param string     $filter
     * @return bool
     */
    public function addColumn($number, $field, $sort = '', $filter = '')
    {
        // can join max. 61 tables
        // Passed parameters must be set carefully
        if($number === 0 || $field === '' || count($this->columns) >= 57)
        {
            return false;
        }

        // If column doesn't exist create object
        if(!array_key_exists($number, $this->columns))
        {
            $this->columns[$number] = new TableAccess($this->db, TBL_LIST_COLUMNS, 'lsc');
            $this->columns[$number]->setValue('lsc_lsf_id', $this->getValue('lst_id'));
        }

        // Assign content of column
        if(is_numeric($field))
        {
            $this->columns[$number]->setValue('lsc_usf_id', $field);
            $this->columns[$number]->setValue('lsc_special_field', '');
        }
        else
        {
            $this->columns[$number]->setValue('lsc_usf_id', '');
            $this->columns[$number]->setValue('lsc_special_field', $field);
        }

        $this->columns[$number]->setValue('lsc_number', $number);
        $this->columns[$number]->setValue('lsc_sort', $sort);
        $this->columns[$number]->setValue('lsc_filter', $filter);

        return true;
    }

    public function clear()
    {
        $this->columns = array();

        parent::clear();
    }

    /**
     * Return count of columns
     * @return int
     */
    public function countColumns()
    {
        return count($this->columns);
    }

    /**
     * Delete pointed columns out of configuration
     * @param int  $number
     * @param bool $all    Define all columns to be deleted
     * @return bool
     */
    public function deleteColumn($number, $all = false)
    {
        if($number > $this->countColumns())
        {
            return false;
        }

        if($all)
        {
            // Delete all columns starting with number
            for($newColumnNumber = $this->countColumns(); $newColumnNumber >= $number; --$newColumnNumber)
            {
                $this->columns[$newColumnNumber]->delete();
                array_pop($this->columns);
            }
        }
        else
        {
            // only 1 columns is deleted and following are going 1 step up
            for($newColumnNumber = $number, $max = $this->countColumns(); $newColumnNumber < $max; ++$newColumnNumber)
            {
                $newColumn = $this->columns[$newColumnNumber];
                $oldColumn = $this->columns[$newColumnNumber + 1];
                $newColumn->setValue('lsc_usf_id',        $oldColumn->getValue('lsc_usf_id'));
                $newColumn->setValue('lsc_special_field', $oldColumn->getValue('lsc_special_field'));
                $newColumn->setValue('lsc_sort',          $oldColumn->getValue('lsc_sort'));
                $newColumn->setValue('lsc_filter',        $oldColumn->getValue('lsc_filter'));
                $newColumn->save();
            }
            $this->columns[$newColumnNumber]->delete();
            array_pop($this->columns);
        }

        return true;
    }

    /**
     * Returns the column object with the corresponding number.
     * If that column doesn't exists the method try to repair the
     * column list. If that won't help then **null** will be returned.
     * @param int $number The internal number of the column.
     *                    This will be the position of the column in the list.
     * @return TableAccess|null Returns a TableAccess object of the database table **adm_list_columns**.
     */
    public function getColumnObject($number)
    {
        if(array_key_exists($number, $this->columns))
        {
            return $this->columns[$number];
        }

        // column not found, then try to repair list
        $this->repair();
        if(array_key_exists($number, $this->columns))
        {
            return $this->columns[$number];
        }

        return null;
    }

    /**
     * prepare SQL to list configuration
     * @param array<int,int> $roleIds           Array with all roles, which members are shown
     * @param bool           $showFormerMembers false - Only active members of a role
     *                                          true  - Only former members
     * @param string         $startDate
     * @param string         $endDate
     * @param array<int,int> $relationtypeIds
     * @return string
     */
    public function getSQL(array $roleIds, $showFormerMembers = false, $startDate = null, $endDate = null, array $relationtypeIds = array())
    {
        global $gL10n, $gProfileFields, $gCurrentOrganization;

        $sqlColumnNames = array();
        $sqlOrderBys    = array();
        $sqlJoin  = '';
        $sqlWhere = '';

        foreach($this->columns as $listColumn)
        {
            $lscUsfId = (int) $listColumn->getValue('lsc_usf_id');

            $tableAlias = '';
            if($lscUsfId > 0)
            {
                // dynamic profile field
                $tableAlias = 'row'. $listColumn->getValue('lsc_number'). 'id'. $lscUsfId;

                // define JOIN - Syntax
                $sqlJoin .= ' LEFT JOIN '.TBL_USER_DATA.' '.$tableAlias.'
                                     ON '.$tableAlias.'.usd_usr_id = usr_id
                                    AND '.$tableAlias.'.usd_usf_id = '.$lscUsfId;

                // usf_id is prefix for the table
                $dbColumnName = $tableAlias.'.usd_value';
            }
            else
            {
                // Special fields like usr_photo, mem_begin ...
                $dbColumnName = $listColumn->getValue('lsc_special_field');
            }

            $sqlColumnNames[] = $dbColumnName;

            $userFieldType = $gProfileFields->getPropertyById($lscUsfId, 'usf_type');

            // create a valid sort
            $lscSort = $listColumn->getValue('lsc_sort');
            if($lscSort != '')
            {
                if($userFieldType === 'NUMBER' || $userFieldType === 'DECIMAL')
                {
                    // if a field has numeric values then there must be a cast because database
                    // column is varchar. A varchar sort of 1,10,2 will be with cast 1,2,10
                    if(DB_ENGINE === Database::PDO_ENGINE_PGSQL)
                    {
                        $columnType = 'numeric';
                    }
                    else
                    {
                        // mysql
                        $columnType = 'unsigned';
                    }
                    $sqlOrderBys[] = ' CAST('.$dbColumnName.' AS '.$columnType.') '.$lscSort;
                }
                else
                {
                    $sqlOrderBys[] = $dbColumnName.' '.$lscSort;
                }
            }

            // Handle the conditions for the columns
            if($listColumn->getValue('lsc_filter') != '')
            {
                $value = $listColumn->getValue('lsc_filter');
                $type = '';

                // custom profile field
                if($lscUsfId > 0)
                {
                    switch ($userFieldType)
                    {
                        case 'CHECKBOX':
                            $type = 'checkbox';

                            // 'yes' or 'no' will be replaced with 1 or 0, so that you can compare it with the database value
                            $arrCheckboxValues = array($gL10n->get('SYS_YES'), $gL10n->get('SYS_NO'), 'true', 'false');
                            $arrCheckboxKeys   = array(1, 0, 1, 0);
                            $value = str_replace(array_map('StringUtils::strToLower', $arrCheckboxValues), $arrCheckboxKeys, StringUtils::strToLower($value));
                            break;

                        case 'DROPDOWN':
                        case 'RADIO_BUTTON':
                            $type = 'int';

                            // replace all field values with their internal numbers
                            $arrListValues = $gProfileFields->getPropertyById($lscUsfId, 'usf_value_list', 'text');
                            $value = array_search(StringUtils::strToLower($value), array_map('StringUtils::strToLower', $arrListValues), true);
                            break;

                        case 'NUMBER':
                        case 'DECIMAL':
                            $type = 'int';
                            break;

                        case 'DATE':
                            $type = 'date';
                            break;

                        default:
                            $type = 'string';
                    }
                }
                else
                {
                    switch ($listColumn->getValue('lsc_special_field'))
                    {
                        case 'mem_begin':
                        case 'mem_end':
                            $type = 'date';
                            break;

                        case 'usr_login_name':
                            $type = 'string';
                            break;

                        case 'usr_photo':
                            $type = '';
                            break;
                    }
                }

                $parser = new ConditionParser();

                // if profile field then add not exists condition
                if($lscUsfId > 0)
                {
                    $parser->setNotExistsStatement('SELECT 1
                                                      FROM '.TBL_USER_DATA.' '.$tableAlias.'s
                                                     WHERE '.$tableAlias.'s.usd_usr_id = usr_id
                                                       AND '.$tableAlias.'s.usd_usf_id = '.$lscUsfId);
                }

                // now transform condition into SQL
                $sqlWhere .= $parser->makeSqlStatement($value, $dbColumnName, $type, $gProfileFields->getPropertyById($lscUsfId, 'usf_name')); // TODO Exception handling
            }
        }

        $sqlColumnNames = implode(', ', $sqlColumnNames);
        $sqlOrderBys    = implode(', ', $sqlOrderBys);
        $sqlRoleIds     = implode(', ', $roleIds);

        // Set state of membership
        if ($showFormerMembers)
        {
            $sqlMemberStatus = 'AND mem_end < \''.DATE_NOW.'\'';
        }
        else
        {
            if ($startDate === null)
            {
                $sqlMemberStatus = 'AND mem_begin <= \''.DATE_NOW.'\'';
            }
            else
            {
                $sqlMemberStatus = 'AND mem_begin <= \''.$endDate.' 23:59:59\'';
            }

            if ($endDate === null)
            {
                $sqlMemberStatus .= ' AND mem_end >= \''.DATE_NOW.'\'';
            }
            else
            {
                $sqlMemberStatus .= ' AND mem_end >= \''.$startDate.' 00:00:00\'';
            }
        }

        $sqlUserJoin = 'INNER JOIN '.TBL_USERS.'
                                ON usr_id = mem_usr_id';
        $sqlRelationtypeWhere = '';
        if (count($relationtypeIds) > 0)
        {
            $sqlUserJoin = 'INNER JOIN '.TBL_USER_RELATIONS.'
                                    ON ure_usr_id1 = mem_usr_id
                            INNER JOIN '.TBL_USERS.'
                                    ON usr_id = ure_usr_id2';
            $sqlRelationtypeWhere = 'AND ure_urt_id IN ('.implode(', ', $relationtypeIds).')';
        }

        // Set SQL-Statement
        $sql = 'SELECT DISTINCT mem_leader, usr_id, '.$sqlColumnNames.'
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                       '.$sqlUserJoin.'
                       '.$sqlJoin.'
                 WHERE usr_valid = 1
                   AND rol_id IN ('.$sqlRoleIds.')
                       '.$sqlRelationtypeWhere.'
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR cat_org_id IS NULL )
                       '.$sqlMemberStatus.'
                       '.$sqlWhere.'
              ORDER BY mem_leader DESC';
        if($sqlOrderBys !== '')
        {
            $sql .= ', '.$sqlOrderBys;
        }

        return $sql;
    }

    /**
     * Read data of responsible columns and store in object
     */
    public function readColumns()
    {
        $sql = 'SELECT *
                  FROM '.TBL_LIST_COLUMNS.'
                 WHERE lsc_lst_id = ? -- $this->getValue(\'lst_id\')
              ORDER BY lsc_number ASC';
        $lscStatement = $this->db->queryPrepared($sql, array($this->getValue('lst_id')));

        while($lscRow = $lscStatement->fetch())
        {
            $lscNumber = (int) $lscRow['lsc_number'];
            $this->columns[$lscNumber] = new TableAccess($this->db, TBL_LIST_COLUMNS, 'lsc');
            $this->columns[$lscNumber]->setArray($lscRow);
        }
    }

    /**
     * The method will clear all column data of this object and restore all
     * columns from the database. Then the column number will be renewed for all columns.
     * This is in some cases a necessary fix if a column number was lost.
     */
    public function repair()
    {
        // restore columns from database
        $this->columns = array();
        $this->readColumns();
        $newColumnNumber = 1;

        // check for every column if the number is expected otherwise set new number
        foreach($this->columns as $number => $listColumn)
        {
            if($number !== $newColumnNumber)
            {
                $this->columns[$number]->setValue('lsc_number', $newColumnNumber);
                $this->columns[$number]->save();
            }
            ++$newColumnNumber;
        }

        // now restore columns with new numbers
        $this->columns = array();
        $this->readColumns();
    }

    /**
     * @param bool $updateFingerPrint
     * @return bool
     */
    public function save($updateFingerPrint = true)
    {
        $this->db->startTransaction();

        $returnValue = parent::save($updateFingerPrint);

        // save columns
        foreach($this->columns as $listColumn)
        {
            if((int) $listColumn->getValue('lsc_lst_id') === 0)
            {
                $listColumn->setValue('lsc_lst_id', $this->getValue('lst_id'));
            }
            $listColumn->save($updateFingerPrint);
        }

        $this->db->endTransaction();

        return $returnValue;
    }
}
