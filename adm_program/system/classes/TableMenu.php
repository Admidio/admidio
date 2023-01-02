<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_menu
 *
 * @copyright 2004-2023 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
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
    public const MOVE_UP   = 'UP';
    public const MOVE_DOWN = 'DOWN';

    protected $elementTable;
    protected $elementColumn;

    /**
     * Constructor that will create an object of a recordset of the table adm_category.
     * If the id is set than the specific category will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int       $menId    The recordset of the category with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $menId = 0)
    {
        parent::__construct($database, TBL_MENU, 'men', $menId);
    }

    /**
     * Deletes the selected menu entries.
     * After that the class will be initialize.
     * @return bool **true** if no error occurred
     */
    public function delete()
    {
        global $gCurrentSession;

        if ($gCurrentSession instanceof Session) {
            // now set menu object in session invalid because the menu has changed
            $gCurrentSession->reloadAllSessions();
        }

        return parent::delete();
    }

    /**
     * This recursive method creates from the parameter name a unique name that only have
     * capital letters followed by the next free number (index)
     * Example: 'Membership' => 'MEMBERSHIP_2'
     * @param string $name  The name from which the unique name should be created
     * @param int    $index The index of the name. Should be startet with 1
     * @return string Returns the unique name with capital letters and number
     */
    private function getNewNameIntern($name, $index)
    {
        $newNameIntern = strtoupper(str_replace(' ', '_', $name));

        if ($index > 1) {
            $newNameIntern = $newNameIntern . '_' . $index;
        }

        $sql = 'SELECT men_id
                  FROM '.TBL_MENU.'
                 WHERE men_name_intern = ? -- $newNameIntern';
        $userFieldsStatement = $this->db->queryPrepared($sql, array($newNameIntern));

        if ($userFieldsStatement->rowCount() > 0) {
            ++$index;
            $newNameIntern = $this->getNewNameIntern($name, $index);
        }

        return $newNameIntern;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with **setValue** than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        global $gL10n;

        $value = parent::getValue($columnName, $format);

        // if text is a translation-id then translate it
        if ($columnName === 'men_name' && $format !== 'database' && Language::isTranslationStringId($value)) {
            $value = $gL10n->get($value);
        }

        return $value;
    }

    /**
     * Change the internal sequence of this category. It can be moved one place up or down
     * @param string $mode This could be **UP** or **DOWN**.
     * @return bool Return true if the sequence of the menu could be changed, otherwise false.
     */
    public function moveSequence($mode)
    {
        $menOrder = (int) $this->getValue('men_order');
        $menIdParent = (int) $this->getValue('men_men_id_parent');
        $returnCode = false;

        // die Sortierung wird um eine Nummer gesenkt und wird somit in der Liste weiter nach oben geschoben
        if ($mode === self::MOVE_UP) {
            if ($menOrder > 1) {
                $sql = 'UPDATE '.TBL_MENU.'
                           SET men_order = ? -- $menOrder
                         WHERE men_men_id_parent = ? -- $menIdParent
                           AND men_order = ? -- $menOrder - 1';
                $this->db->queryPrepared($sql, array($menOrder, $menIdParent, $menOrder - 1));
                $this->setValue('men_order', $menOrder - 1);
                $returnCode = $this->save();
            }
        }
        // die Kategorie wird um eine Nummer erhoeht und wird somit in der Liste weiter nach unten geschoben
        elseif ($mode === self::MOVE_DOWN) {
            // count all categories that are organization independent because these categories should not
            // be mixed with the organization categories. Hidden categories are sidelined.
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_MENU.'
                     WHERE men_men_id_parent = ? -- $menIdParent';
            $countMenuStatement = $this->db->queryPrepared($sql, array($menIdParent));
            $rowCount = $countMenuStatement->fetchColumn();

            if ($menOrder < $rowCount) {
                $sql = 'UPDATE '.TBL_MENU.'
                           SET men_order = ? -- $menOrder
                         WHERE men_men_id_parent = ? -- $menIdParent
                           AND men_order = ? -- $menOrder + 1';
                $this->db->queryPrepared($sql, array($menOrder, $menIdParent, $menOrder + 1));
                $this->setValue('men_order', $menOrder + 1);
                $returnCode = $this->save();
            }
        }
        return $returnCode;
    }

    /**
     * Reads a menu out of the table in database selected by the unique menu id in the table.
     * Per default all columns of adm_menu will be read and stored in the object.
     * @param int $menId Unique men_id
     * @return bool Returns **true** if one record is found
     */
    public function readDataById($menId)
    {
        $returnValue = parent::readDataById($menId);

        if ($returnValue) {
            $this->elementTable = TBL_MENU;
            $this->elementColumn = 'men_id';
        }

        return $returnValue;
    }

    /**
     * Reads a category out of the table in database selected by different columns in the table.
     * The columns are commited with an array where every element index is the column name and the value is the column value.
     * The columns and values must be selected so that they identify only one record.
     * If the sql will find more than one record the method returns **false**.
     * Per default all columns of adm_categories will be read and stored in the object.
     * @param array $columnArray An array where every element index is the column name and the value is the column value
     * @return bool Returns **true** if one record is found
     */
    public function readDataByColumns(array $columnArray)
    {
        $returnValue = parent::readDataByColumns($columnArray);

        if ($returnValue) {
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
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentSession;

        $this->db->startTransaction();

        if ($this->newRecord) {
            // if new field than generate new name intern, otherwise no change will be made
            $this->setValue('men_name_intern', $this->getNewNameIntern($this->getValue('men_name', 'database'), 1));

            // beim Insert die hoechste Reihenfolgennummer der Kategorie ermitteln
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_MENU.'
                     WHERE men_men_id_parent = ? -- $this->getValue(\'men_men_id_parent\')';
            $countMenuStatement = $this->db->queryPrepared($sql, array((int) $this->getValue('men_men_id_parent')));

            $rowCount = $countMenuStatement->fetchColumn();
            $this->setValue('men_order', $rowCount + 1);
        }

        $returnValue = parent::save($updateFingerPrint);

        if ($gCurrentSession instanceof Session) {
            // now set menu object in session invalid because the menu has changed
            $gCurrentSession->reloadAllSessions();
        }

        $this->db->endTransaction();

        return $returnValue;
    }
}
