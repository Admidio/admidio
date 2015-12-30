<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_categories
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Diese Klasse dient dazu einen Kategorieobjekt zu erstellen.
 * Eine Kategorieobjekt kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getNewNameIntern($name, $index) - diese rekursive Methode ermittelt fuer den
 *                       uebergebenen Namen einen eindeutigen Namen dieser bildet sich
 *                       aus dem Namen in Grossbuchstaben und der naechsten freien Nummer
 * getNumberElements() - number of child recordsets
 * moveSequence($mode) - Kategorie wird um eine Position in der Reihenfolge verschoben
 *
 *****************************************************************************/
class TableCategory extends TableAccess
{
    protected $elementTable;
    protected $elementColumn;

    /**
     * Constructor that will create an object of a recordset of the table adm_category.
     * If the id is set than the specific category will be loaded.
     * @param object $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int    $cat_id   The recordset of the category with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $cat_id = 0)
    {
        parent::__construct($database, TBL_CATEGORIES, 'cat', $cat_id);
    }

    /**
     * Deletes the selected record of the table and all references in other tables.
     * After that the class will be initialize. The method throws exceptions if
     * the category couldn't be deleted.
     * @throws AdmException
     * @return @b true if no error occurred
     */
    public function delete()
    {
        global $gCurrentSession;

        // system-category couldn't be deleted
        if($this->getValue('cat_system') == 1)
        {
            throw new AdmException('SYS_DELETE_SYSTEM_CATEGORY');
        }

        // checks if there exists another category of this type. Don't delete the last category of a type!
        $sql = 'SELECT COUNT(*) AS count_categories
                  FROM '.TBL_CATEGORIES.'
                 WHERE (  cat_org_id = '. $gCurrentSession->getValue('ses_org_id'). '
                       OR cat_org_id IS NULL )
                   AND cat_type     = \''. $this->getValue('cat_type'). '\'';
        $countCategoriesStatement = $this->db->query($sql);

        $row = $countCategoriesStatement->fetch();

        if($row['count_categories'] > 1)
        {
            $this->db->startTransaction();

            // Luecke in der Reihenfolge schliessen
            $sql = 'UPDATE '.TBL_CATEGORIES.' SET cat_sequence = cat_sequence - 1
                     WHERE (  cat_org_id = '. $gCurrentSession->getValue('ses_org_id'). '
                           OR cat_org_id IS NULL )
                       AND cat_sequence > '. $this->getValue('cat_sequence'). '
                       AND cat_type     = \''. $this->getValue('cat_type'). '\'';
            $this->db->query($sql);

            // alle zugehoerigen abhaengigen Objekte suchen und mit weiteren Abhaengigkeiten loeschen
            $sql = 'SELECT *
                      FROM '.$this->elementTable.'
                     WHERE '.$this->elementColumn.' = '. $this->getValue('cat_id');
            $recordsetsStatement = $this->db->query($sql);

            if($recordsetsStatement->rowCount() > 0)
            {
                throw new AdmException('CAT_DONT_DELETE_CATEGORY', $this->getValue('cat_name'), $this->getNumberElements());
            }

            $return = parent::delete();

            $this->db->endTransaction();
            return $return;
        }
        else
        {
            // Don't delete the last category of a type!
            throw new AdmException('SYS_DELETE_LAST_CATEGORY');
        }
    }

    /**
     * diese rekursive Methode ermittelt fuer den uebergebenen Namen einen eindeutigen Namen
     * dieser bildet sich aus dem Namen in Grossbuchstaben und der naechsten freien Nummer (index)
     * Beispiel: 'Gruppen' => 'GRUPPEN_2'
     * @param string $name
     * @param int    $index
     * @return string
     */
    private function getNewNameIntern($name, $index)
    {
        $newNameIntern = strtoupper(str_replace(' ', '_', $name));
        if($index > 1)
        {
            $newNameIntern = $newNameIntern.'_'.$index;
        }
        $sql = 'SELECT cat_id
                  FROM '.TBL_CATEGORIES.'
                 WHERE cat_name_intern = \''.$newNameIntern.'\'';
        $categoriesStatement = $this->db->query($sql);

        if($categoriesStatement->rowCount() > 0)
        {
            ++$index;
            $newNameIntern = $this->getNewNameIntern($name, $index);
        }

        return $newNameIntern;
    }

    /**
     * Read number of child recordsets of this category.
     * @return Returns the number of child elements of this category
     */
    public function getNumberElements()
    {
        $sql = 'SELECT COUNT(*)
                  FROM '.$this->elementTable.'
                 WHERE '.$this->elementColumn.' = '. $this->getValue('cat_id');
        $elementsStatement = $this->db->query($sql);
        $row = $elementsStatement->fetch();

        return $row[0];
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                           For text columns the format can be @b database that would return the original database value without any transformations
     * @return Returns the value of the database column.
     *         If the value was manipulated before with @b setValue than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        global $gL10n;

        if($columnName === 'cat_name_intern')
        {
            // internal name should be read with no conversion
            $value = parent::getValue($columnName, 'database');
        }
        else
        {
            $value = parent::getValue($columnName, $format);
        }

        if($columnName === 'cat_name' && $format !== 'database')
        {
            // if text is a translation-id then translate it
            if(strpos($value, '_') === 3)
            {
                $value = $gL10n->get(admStrToUpper($value));
            }
        }

        return $value;
    }

    /**
     * Change the internal sequence of this category. It can be moved one place up or down
     * @param string $mode This could be @b UP or @b DOWN.
     */
    public function moveSequence($mode)
    {
        global $gCurrentOrganization;

        // count all categories that are organization independent because these categories should not
        // be mixed with the organization categories. Hidden categories are sidelined.
        $sql = 'SELECT COUNT(*) as count
                  FROM '.TBL_CATEGORIES.'
                 WHERE cat_type = \''. $this->getValue('cat_type'). '\'
                   AND cat_name_intern NOT LIKE \'CONFIRMATION_OF_PARTICIPATION\'
                   AND cat_org_id IS NULL ';
        $countCategoriesStatement = $this->db->query($sql);
        $row = $countCategoriesStatement->fetch();

        // die Kategorie wird um eine Nummer gesenkt und wird somit in der Liste weiter nach oben geschoben
        if(admStrToUpper($mode) === 'UP')
        {
            if($this->getValue('cat_org_id') == 0 || $this->getValue('cat_sequence') > $row['count']+1)
            {
                $sql = 'UPDATE '.TBL_CATEGORIES.' SET cat_sequence = '.$this->getValue('cat_sequence').'
                         WHERE cat_type = \''. $this->getValue('cat_type'). '\'
                           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                                          OR cat_org_id IS NULL )
                           AND cat_sequence = '.$this->getValue('cat_sequence').' - 1 ';
                $this->db->query($sql);
                $this->setValue('cat_sequence', $this->getValue('cat_sequence')-1);
                $this->save();
            }
        }
        // die Kategorie wird um eine Nummer erhoeht und wird somit in der Liste weiter nach unten geschoben
        elseif(admStrToUpper($mode) === 'DOWN')
        {
            if($this->getValue('cat_org_id') > 0 || $this->getValue('cat_sequence') < $row['count'])
            {
                $sql = 'UPDATE '.TBL_CATEGORIES.' SET cat_sequence = '.$this->getValue('cat_sequence').'
                         WHERE cat_type = \''. $this->getValue('cat_type'). '\'
                           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                                          OR cat_org_id IS NULL )
                           AND cat_sequence = '.$this->getValue('cat_sequence').' + 1 ';
                $this->db->query($sql);
                $this->setValue('cat_sequence', $this->getValue('cat_sequence')+1);
                $this->save();
            }
        }
    }

    /**
     * Reads a category out of the table in database selected by the unique category id in the table.
     * Per default all columns of adm_categories will be read and stored in the object.
     * @param int $cat_id Unique cat_id
     * @return bool Returns @b true if one record is found
     */
    public function readDataById($cat_id)
    {
        global $g_tbl_praefix;

        $returnValue = parent::readDataById($cat_id);

        if($returnValue)
        {
            switch ($this->getValue('cat_type'))
            {
                case 'ROL':
                    $this->elementTable = TBL_ROLES;
                    $this->elementColumn = 'rol_cat_id';
                    break;
                case 'LNK':
                    $this->elementTable = TBL_LINKS;
                    $this->elementColumn = 'lnk_cat_id';
                    break;
                case 'USF':
                    $this->elementTable = TBL_USER_FIELDS;
                    $this->elementColumn = 'usf_cat_id';
                    break;
                case 'DAT':
                    $this->elementTable = TBL_DATES;
                    $this->elementColumn = 'dat_cat_id';
                    break;
                case 'AWA':
                    $this->elementTable  = $g_tbl_praefix.'_user_awards';
                    $this->elementColumn = 'awa_cat_id';
                    break;
            }
        }

        return $returnValue;
    }

    /**
     * Reads a category out of the table in database selected by different columns in the table.
     * The columns are commited with an array where every element index is the column name and the value is the column value.
     * The columns and values must be selected so that they identify only one record.
     * If the sql will find more than one record the method returns @b false.
     * Per default all columns of adm_categories will be read and stored in the object.
     * @param array $columnArray An array where every element index is the column name and the value is the column value
     * @return Returns @b true if one record is found
     */
    public function readDataByColumns($columnArray)
    {
        $returnValue = parent::readDataByColumns($columnArray);

        if($returnValue)
        {
            switch ($this->getValue('cat_type'))
            {
                case 'ROL':
                    $this->elementTable = TBL_ROLES;
                    $this->elementColumn = 'rol_cat_id';
                    break;
                case 'LNK':
                    $this->elementTable = TBL_LINKS;
                    $this->elementColumn = 'lnk_cat_id';
                    break;
                case 'USF':
                    $this->elementTable = TBL_USER_FIELDS;
                    $this->elementColumn = 'usf_cat_id';
                    break;
                case 'DAT':
                    $this->elementTable = TBL_DATES;
                    $this->elementColumn = 'dat_cat_id';
                    break;
            }
        }

        return $returnValue;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * If a new record is inserted than the next free sequence will be determined.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization, $gCurrentSession;
        $fields_changed = $this->columnsValueChanged;
        $this->db->startTransaction();

        if($this->new_record)
        {
            if($this->getValue('cat_org_id') > 0)
            {
                $org_condition = ' AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                                       OR cat_org_id IS NULL ) ';
            }
            else
            {
                $org_condition = ' AND cat_org_id IS NULL ';
            }
            // beim Insert die hoechste Reihenfolgennummer der Kategorie ermitteln
            $sql = 'SELECT COUNT(*) as count
                      FROM '.TBL_CATEGORIES.'
                     WHERE cat_type = \''. $this->getValue('cat_type'). '\'
                           '.$org_condition;
            $countCategoriesStatement = $this->db->query($sql);

            $row = $countCategoriesStatement->fetch();

            $this->setValue('cat_sequence', $row['count'] + 1);

            if($this->getValue('cat_org_id') == 0)
            {
                // eine Orga-uebergreifende Kategorie ist immer am Anfang, also Kategorien anderer Orgas nach hinten schieben
                $sql = 'UPDATE '.TBL_CATEGORIES.' SET cat_sequence = cat_sequence + 1
                         WHERE cat_type = \''. $this->getValue('cat_type'). '\'
                           AND cat_org_id IS NOT NULL ';
                $this->db->query($sql);
            }
        }

        // if new category than generate new name intern, otherwise no change will be made
        if($this->new_record)
        {
            $this->setValue('cat_name_intern', $this->getNewNameIntern($this->getValue('cat_name'), 1));
        }

        parent::save($updateFingerPrint);

        // Nach dem Speichern noch pruefen, ob Userobjekte neu eingelesen werden muessen,
        if($fields_changed && $this->getValue('cat_type') === 'USF' && is_object($gCurrentSession))
        {
            // all active users must renew their user data because the user field structure has been changed
            $gCurrentSession->renewUserObject();
        }

        $this->db->endTransaction();
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method @b save to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.
     * @return bool Returns @b true if the value is stored in the current object and @b false if a check failed
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        global $gCurrentOrganization;

        // Systemkategorien duerfen nicht umbenannt werden
        if($columnName === 'cat_name' && $this->getValue('cat_system') == 1)
        {
            return false;
        }
        elseif($columnName === 'cat_default' && $newValue == '1')
        {
            // es darf immer nur eine Default-Kategorie je Bereich geben
            $sql = 'UPDATE '.TBL_CATEGORIES.' SET cat_default = 0
                     WHERE cat_type = \''. $this->getValue('cat_type'). '\'
                       AND (  cat_org_id IS NOT NULL
                           OR cat_org_id = '.$gCurrentOrganization->getValue('org_id').')';
            $this->db->query($sql);
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
