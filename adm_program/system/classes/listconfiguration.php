<?php
/******************************************************************************
 * Class manages the list configuration
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
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

/**
 * Class ListConfiguration
 */
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
     * fuegt eine neue Spalte dem Spaltenarray hinzu
     * @param int    $number
     * @param        $field
     * @param string $sort
     * @param string $filter
     * @return bool
     */
    public function addColumn($number, $field, $sort = '', $filter = '')
    {
        // MySQL kann nicht mehr als 61 Tabellen joinen
        // Uebergaben muessen sinnvoll gefuellt sein
        if(count($this->columns) < 57 && $number > 0 && $field !== '')
        {
            // falls Spalte noch nicht existiert, dann Objekt anlegen
            if(!isset($this->columns[$number]))
            {
                $this->columns[$number] = new TableAccess($this->db, TBL_LIST_COLUMNS, 'lsc');
                $this->columns[$number]->setValue('lsc_lsf_id', $this->getValue('lst_id'));
            }

            // Spalteninhalte belegen
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
     * Anzahl der Spalten der Liste zurueckgeben
     * @return int
     */
    public function countColumns()
    {
        return count($this->columns);
    }

    /**
     * entfernt die entsprechende Spalte aus der Konfiguration
     * @param int  $number
     * @param bool $all gibt an, ob alle folgenden Spalten auch geloescht werden sollen
     */
    public function deleteColumn($number, $all = false)
    {
        if($number <= $this->countColumns())
        {
            if($all)
            {
                // alle Spalten ab der Nummer werden entfernt
                for($newColumnNumber = $this->countColumns(); $newColumnNumber >= $number; $newColumnNumber--)
                {
                    $this->columns[$newColumnNumber]->delete();
                    array_pop($this->columns);
                }
            }
            else
            {
                // es wird nur die einzelne Spalte entfernt und alle folgenden Spalten ruecken eins nach vorne
                for($newColumnNumber = $number; $newColumnNumber < $this->countColumns(); $newColumnNumber++)
                {
                    $this->columns[$newColumnNumber]->setValue('lsc_usf_id', $this->columns[$newColumnNumber+1]->getValue('lsc_usf_id'));
                    $this->columns[$newColumnNumber]->setValue('lsc_special_field', $this->columns[$newColumnNumber+1]->getValue('lsc_special_field'));
                    $this->columns[$newColumnNumber]->setValue('lsc_sort',   $this->columns[$newColumnNumber+1]->getValue('lsc_sort'));
                    $this->columns[$newColumnNumber]->setValue('lsc_filter', $this->columns[$newColumnNumber+1]->getValue('lsc_filter'));
                    $this->columns[$newColumnNumber]->save();
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
     * gibt das passende SQL-Statement zu der Liste zurueck
     * @param $roleIds Array ueber alle Rollen-IDs, von denen Mitglieder in der Liste angezeigt werden sollen
     * @param int $memberStatus 0 - Nur aktive Rollenmitglieder
     *                          1 - Nur ehemalige Rollenmitglieder
     *                          2 - Aktive und ehemalige Rollenmitglieder
     * @return string
     * @throws AdmException
     */
    public function getSQL($roleIds, $memberStatus = 0)
    {
        global $gL10n, $gProfileFields, $gCurrentOrganization, $gDbType;
        $sql = '';
        $sqlSelect  = '';
        $sqlJoin    = '';
        $sqlWhere   = '';
        $sqlOrderBy = '';
        $sqlRoleIds = '';
        $sqlMemberStatus = '';

        foreach($this->columns as $number => $listColumn)
        {
            // Spalte anhaengen
            if($sqlSelect !== '')
            {
                $sqlSelect = $sqlSelect . ', ';
            }

            if($listColumn->getValue('lsc_usf_id') > 0)
            {
                // dynamisches Profilfeld
                $tableAlias = 'row'. $listColumn->getValue('lsc_number'). 'id'. $listColumn->getValue('lsc_usf_id');

                // JOIN - Syntax erstellen
                $sqlJoin = $sqlJoin. ' LEFT JOIN '. TBL_USER_DATA .' '.$tableAlias.'
                                           ON '.$tableAlias.'.usd_usr_id = usr_id
                                          AND '.$tableAlias.'.usd_usf_id = '.$listColumn->getValue('lsc_usf_id');

                // hierbei wird die usf_id als Tabellen-Alias benutzt und vorangestellt
                $dbColumnName = $tableAlias.'.usd_value';
            }
            else
            {
                // Spezialfelder z.B. usr_photo, mem_begin ...
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

                // custom profile field
                if($listColumn->getValue('lsc_usf_id') > 0)
                {
                    switch ($userFieldType) {
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
                            $value = array_search(admStrToLower($value), array_map('admStrToLower', $arrListValues));
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
                    switch ($listColumn->getValue('lsc_special_field')) {
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
                    $parser->setNotExistsStatement('SELECT 1 FROM '.TBL_USER_DATA.' '.$tableAlias.'s
                                                     WHERE '.$tableAlias.'s.usd_usr_id = usr_id
                                                       AND '.$tableAlias.'s.usd_usf_id = '.$listColumn->getValue('lsc_usf_id'));
                }

                // now transform condition into SQL
                $condition = $parser->makeSqlStatement($value, $dbColumnName, $type, $gProfileFields->getPropertyById($listColumn->getValue('lsc_usf_id'), 'usf_name'));
                $sqlWhere = $sqlWhere. $condition;
            }
        }

        // Rollen-IDs zusammensetzen
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

        // Status der Mitgliedschaft setzen
        if($memberStatus === 0)
        {
            $sqlMemberStatus = ' AND mem_begin <= \''.DATE_NOW.'\'
                                 AND mem_end   >= \''.DATE_NOW.'\' ';
        }
        elseif($memberStatus === 1)
        {
            $sqlMemberStatus = ' AND mem_end < \''.DATE_NOW.'\' ';
        }

        // SQL-Statement zusammenbasteln
        $sql = 'SELECT mem_leader, usr_id, '.$sqlSelect.'
                  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. ', '. TBL_USERS. '
                       '.$sqlJoin.'
                 WHERE rol_id    IN ('.$sqlRoleIds.')
                   AND rol_cat_id = cat_id
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR cat_org_id IS NULL )
                   AND mem_rol_id = rol_id
                       '.$sqlMemberStatus.'
                   AND mem_usr_id = usr_id
                   AND usr_valid  = 1
                       '.$sqlWhere.'
                 ORDER BY mem_leader DESC ';
        if($sqlOrderBy !== '')
        {
            $sql = $sql. ', '. $sqlOrderBy;
        }

        return $sql;
    }

    /**
     * Daten der zugehoerigen Spalten einlesen und in Objekten speichern
     */
    public function readColumns()
    {
        $sql = 'SELECT * FROM '. TBL_LIST_COLUMNS. '
                 WHERE lsc_lst_id = '. $this->getValue('lst_id'). '
                 ORDER BY lsc_number ASC ';
        $lsc_result = $this->db->query($sql);

        while($lsc_row = $this->db->fetch_array($lsc_result))
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
            $newColumnNumber++;
        }

        // now restore columns with new numbers
        $this->columns = array();
        $this->readColumns();
    }

    /**
     * @param bool $updateFingerPrint
     */
    public function save($updateFingerPrint = true)
    {
        $this->db->startTransaction();

        parent::save($updateFingerPrint);

        // jetzt noch die einzelnen Spalten sichern
        foreach($this->columns as $number => $listColumn)
        {
            if($listColumn->getValue('lsc_lst_id') == 0)
            {
                $listColumn->setValue('lsc_lst_id', $this->getValue('lst_id'));
            }
            $listColumn->save($updateFingerPrint);
        }

        $this->db->endTransaction();
    }
}
