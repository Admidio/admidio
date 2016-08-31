<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_menu
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableMenu
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
 */
class TableMenu extends TableAccess
{
    protected $elementTable;
    protected $elementColumn;

    /**
     * Constructor that will create an object of a recordset of the table adm_category.
     * If the id is set than the specific category will be loaded.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $cat_id   The recordset of the category with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $men_id = 0)
    {
        parent::__construct($database, TBL_MENU, 'men', $men_id);
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                           For text columns the format can be @b database that would return the original database value without any transformations
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with @b setValue than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        global $gL10n;

        $value = parent::getValue($columnName, $format);

        // if text is a translation-id then translate it
        if($columnName === 'men_translate_name' && $format !== 'database')
        {
            $value = $gL10n->get(admStrToUpper($value));
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
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_MENU.'
                 WHERE men_group = \''. $this->getValue('men_group'). '\'';
        $countMenuStatement = $this->db->query($sql);
        $row = $countMenuStatement->fetch();

        // die Sortierung wird um eine Nummer gesenkt und wird somit in der Liste weiter nach oben geschoben
        if(admStrToUpper($mode) === 'UP')
        {
            if($this->getValue('men_order') > 1)
            {
                $sql = 'UPDATE '.TBL_MENU.' SET men_order = '.$this->getValue('men_order').'
                         WHERE men_group = \''. $this->getValue('men_group'). '\'
                           AND men_order = '.$this->getValue('men_order').' - 1 ';
                $this->db->query($sql);
                $this->setValue('men_order', $this->getValue('men_order')-1);
                $this->save();
            }
        }
        // die Kategorie wird um eine Nummer erhoeht und wird somit in der Liste weiter nach unten geschoben
        elseif(admStrToUpper($mode) === 'DOWN')
        {
            if($this->getValue('men_order') < $row['count'])
            {
                $sql = 'UPDATE '.TBL_MENU.' SET men_order = '.$this->getValue('men_order').'
                         WHERE men_group = \''. $this->getValue('men_group'). '\'
                           AND men_order = '.$this->getValue('men_order').' + 1 ';
                $this->db->query($sql);
                $this->setValue('men_order', $this->getValue('men_order')+1);
                $this->save();
            }
        }
    }

    /**
     * Reads a menu out of the table in database selected by the unique menu id in the table.
     * Per default all columns of adm_menu will be read and stored in the object.
     * @param int $men_id Unique men_id
     * @return bool Returns @b true if one record is found
     */
    public function readDataById($men_id)
    {
        global $g_tbl_praefix;

        $returnValue = parent::readDataById($men_id);

        if($returnValue)
        {
            $this->elementTable = TBL_MENU;
            $this->elementColumn = 'men_id';
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
     * @return bool Returns @b true if one record is found
     */
    public function readDataByColumns(array $columnArray)
    {
        $returnValue = parent::readDataByColumns($columnArray);

        if($returnValue)
        {
            $this->elementTable = TBL_MENU;
            $this->elementColumn = 'men_id';
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
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentSession;
        $fields_changed = $this->columnsValueChanged;
        $this->db->startTransaction();

        if($this->new_record)
        {
            // beim Insert die hoechste Reihenfolgennummer der Kategorie ermitteln
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_MENU.'
                     WHERE men_group = \''. $this->getValue('men_group'). '\'';
            $countMenuStatement = $this->db->query($sql);

            $row = $countMenuStatement->fetch();
            $this->setValue('men_order', $row['count'] + 1);
        }

        $returnValue = parent::save($updateFingerPrint);

        $this->db->endTransaction();

        return $returnValue;
    }
}
