<?php
/**
 ***********************************************************************************************
 * Class manages the list configuration
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
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
 * getSQL($roleIds, $memberStatus = 0)
 *                       - gibt das passende SQL-Statement zu der Liste zurueck
 *
 *****************************************************************************/
class ListConfiguration extends TableLists
{
    protected $columns = array(); // Array ueber alle Listenspaltenobjekte

    /**
     * Constructor that will create an object to handle the configuration of lists.
     * @param object $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int    $lst_id   The id of the recordset that should be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $lst_id = 0)
    {
        parent::__construct($database, $lst_id);

        if($lst_id > 0)
        {
            $this->readColumns();
        }
    }

    /**
     * Add new column to column array
     * @param int    $number
     * @param        $field
     * @param string $sort
     * @param string $filter
     * @return bool
     */
    public function addColumn($number, $field, $sort = '', $filter = '')
    {
        // can join max. 61 tables
        // Passed parameters must be set carefully
        if(count($this->columns) < 57 && $number > 0 && $field !== '')
        {
            // If colum doesn't exist create object
            if(!isset($this->columns[$number]))
            {
                $this->columns[$number] = new TableAccess($this->db, TBL_LIST_COLUMNS, 'lsc');
                $this->columns[$number]->setValue('lsc_lsf_id', $this->getValue('lst_id'));
            }

            // Assign content of column
            $this->columns[$number]->setValue('lsc_number', $number);
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
            $this->columns[$number]->setValue('lsc_sort', $sort);
            $this->columns[$number]->setValue('lsc_filter', $filter);
            return true;
        }
        return false;
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
     * @param bool $all Define all columns to be deleted
     */
    public function deleteColumn($number, $all = false)
    {
        if($number <= $this->countColumns())
        {
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
                for($newColumnNumber = $number; $newColumnNumber < $this->countColumns(); ++$newColumnNumber)
                {
                    $newColumn = $this->columns[$newColumnNumber];
                    $oldColumn = $this->columns[$newColumnNumber+1];
                    $newColumn->setValue('lsc_usf_id',        $oldColumn->getValue('lsc_usf_id'));
                    $newColumn->setValue('lsc_special_field', $oldColumn->getValue('lsc_special_field'));
                    $newColumn->setValue('lsc_sort',          $oldColumn->getValue('lsc_sort'));
                    $newColumn->setValue('lsc_filter',        $oldColumn->getValue('lsc_filter'));
                    $newColumn->save();
                }
                $this->columns[$newColumnNumber]->delete();
                array_pop($this->columns);
            }
        }
    }

    /**
     * Returns the column object with the corresponding number.
     * If that column doesn't exists the method try to repair the
     * column list. If that won't help then @b null will be returned.
     * @param int $number The internal number of the column.
     *                    This will be the position of the column in the list.
     * @return object|null Returns a TableAccess object of the database table @b adm_list_columns.
     */
    public function getColumnObject($number)
    {
        if(isset($this->columns[$number]))
        {
            return $this->columns[$number];
        }
        else
        {
            // column not found, then try to repair list
            $this->repair();
            if(isset($this->columns[$number]))
            {
                return $this->columns[$number];
            }
            else
            {
                return null;
            }
        }
    }

    /**
     * prepare SQL to list configuration
     * @param array  $roleIds Array with all roles, which members are shown
     * @param int    $memberStatus 0 - Only active members of a role
     *                             1 - Only former members
     *                             2 - Active and former members of a role
     * @param string $startDate
     * @param string $endDate
     * @return string
     */
    public function getSQL($roleIds, $memberStatus = 0, $startDate = null, $endDate = null)
    {
        global $gL10n, $gProfileFields, $gCurrentOrganization, $gDbType;

        $sqlSelect  = '';
        $sqlJoin    = '';
        $sqlWhere   = '';
        $sqlOrderBy = '';
        $sqlRoleIds = '';
        $sqlMemberStatus = '';

        foreach($this->columns as $number => $listColumn)
        {
            // add column
            if($sqlSelect !== '')
            {
                $sqlSelect = $sqlSelect . ', ';
            }

            if($listColumn->getValue('lsc_usf_id') > 0)
            {
                // dynamic profile field
                $tableAlias = 'row'. $listColumn->getValue('lsc_number'). 'id'. $listColumn->getValue('lsc_usf_id');

                // define JOIN - Syntax
                $sqlJoin = $sqlJoin.' LEFT JOIN '.TBL_USER_DATA.' '.$tableAlias.'
                                             ON '.$tableAlias.'.usd_usr_id = usr_id
                                            AND '.$tableAlias.'.usd_usf_id = '.$listColumn->getValue('lsc_usf_id');

                // usf_id is prefix for the table
                $dbColumnName = $tableAlias.'.usd_value';
            }
            else
            {
                // Special fields like usr_photo, mem_begin ...
                $dbColumnName = $listColumn->getValue('lsc_special_field');
            }

            $sqlSelect = $sqlSelect. $dbColumnName;

            $userFieldType = $gProfileFields->getPropertyById($listColumn->getValue('lsc_usf_id'), 'usf_type');

            // create a valid sort
            if(strlen($listColumn->getValue('lsc_sort')) > 0)
            {
                if($sqlOrderBy !== '')
                {
                    $sqlOrderBy = $sqlOrderBy. ', ';
                }

                if($userFieldType === 'NUMBER' || $userFieldType === 'DECIMAL')
                {
                    // if a field has numeric values then there must be a cast because database
                    // column is varchar. A varchar sort of 1,10,2 will be with cast 1,2,10
                    if($gDbType === 'postgresql')
                    {
                        $columnType = 'numeric';
                    }
                    else
                    {
                        // mysql
                        $columnType = 'unsigned';
                    }
                    $sqlOrderBy = $sqlOrderBy. ' CAST('.$dbColumnName. ' AS '.$columnType.') '. $listColumn->getValue('lsc_sort');
                }
                else
                {
                    $sqlOrderBy = $sqlOrderBy. $dbColumnName. ' '. $listColumn->getValue('lsc_sort');
                }
            }

            // Handle the conditions for the columns
            if(strlen($listColumn->getValue('lsc_filter')) > 0)
            {
                $value = $listColumn->getValue('lsc_filter');
                $type = '';

                // custom profile field
                if($listColumn->getValue('lsc_usf_id') > 0)
                {
                    switch ($userFieldType)
                    {
                        case 'CHECKBOX':
                            $type = 'checkbox';

                            // 'yes' or 'no' will be replaced with 1 or 0, so that you can compare it with the database value
                            $arrCheckboxValues = array($gL10n->get('SYS_YES'), $gL10n->get('SYS_NO'), 'true', 'false');
                            $arrCheckboxKeys   = array(1, 0, 1, 0);
                            $value = str_replace(array_map('admStrToLower', $arrCheckboxValues), $arrCheckboxKeys, admStrToLower($value));
                            break;

                        case 'DROPDOWN':
                        case 'RADIO_BUTTON':
                            $type = 'int';

                            // replace all field values with their internal numbers
                            $arrListValues = $gProfileFields->getPropertyById($listColumn->getValue('lsc_usf_id'), 'usf_value_list', 'text');
                            $value = array_search(admStrToLower($value), array_map('admStrToLower', $arrListValues), true);
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
                if($listColumn->getValue('lsc_usf_id') > 0)
                {
                    $parser->setNotExistsStatement('SELECT 1
                                                      FROM '.TBL_USER_DATA.' '.$tableAlias.'s
                                                     WHERE '.$tableAlias.'s.usd_usr_id = usr_id
                                                       AND '.$tableAlias.'s.usd_usf_id = '.$listColumn->getValue('lsc_usf_id'));
                }

                // now transform condition into SQL
                $condition = $parser->makeSqlStatement($value, $dbColumnName, $type, $gProfileFields->getPropertyById($listColumn->getValue('lsc_usf_id'), 'usf_name'));
                $sqlWhere = $sqlWhere. $condition;
            }
        }

        // Create role-IDs
        foreach($roleIds as $key => $value)
        {
            if(is_numeric($key))
            {
                if($sqlRoleIds !== '')
                {
                    $sqlRoleIds = $sqlRoleIds. ', ';
                }
                $sqlRoleIds = $sqlRoleIds. $value;
            }
        }

        // Set state of membership
        if ($memberStatus === 0)
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
                $sqlMemberStatus .= 'AND mem_end >= \''.DATE_NOW.'\'';
            }
            else
            {
                $sqlMemberStatus .= 'AND mem_end >= \''.$startDate.' 00:00:00\'';
            }
        }
        elseif ($memberStatus === 1)
        {
            $sqlMemberStatus = 'AND mem_end < \''.DATE_NOW.'\'';
        }

        // Set SQL-Statement
        $sql = 'SELECT mem_leader, usr_id, '.$sqlSelect.'
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
            INNER JOIN '.TBL_USERS.'
                    ON usr_id = mem_usr_id
                       '.$sqlJoin.'
                 WHERE usr_valid = 1
                   AND rol_id IN ('.$sqlRoleIds.')
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR cat_org_id IS NULL )
                       '.$sqlMemberStatus.'
                       '.$sqlWhere.'
              ORDER BY mem_leader DESC';
        if($sqlOrderBy !== '')
        {
            $sql = $sql. ', '. $sqlOrderBy;
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
                 WHERE lsc_lst_id = '.$this->getValue('lst_id').'
              ORDER BY lsc_number ASC';
        $lscStatement = $this->db->query($sql);

        while($lsc_row = $lscStatement->fetch())
        {
            $this->columns[$lsc_row['lsc_number']] = new TableAccess($this->db, TBL_LIST_COLUMNS, 'lsc');
            $this->columns[$lsc_row['lsc_number']]->setArray($lsc_row);
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
            if($number != $newColumnNumber)
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
        foreach($this->columns as $number => $listColumn)
        {
            if($listColumn->getValue('lsc_lst_id') == 0)
            {
                $listColumn->setValue('lsc_lst_id', $this->getValue('lst_id'));
            }
            $listColumn->save($updateFingerPrint);
        }

        $this->db->endTransaction();

        return $returnValue;
    }
}
