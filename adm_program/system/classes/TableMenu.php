<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_menu
 *
 * @copyright The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
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
     * @param int $menId The recordset of the category with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $menId = 0)
    {
        parent::__construct($database, TBL_MENU, 'men', $menId);
    }

    /**
     * Deletes the selected menu entries.
     * After that the class will be initialized.
     * @return bool **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
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
     * @param string $name The name from which the unique name should be created
     * @param int $index The index of the name. Should be startet with 1
     * @return string Returns the unique name with capital letters and number
     * @throws Exception
     */
    private function getNewNameIntern(string $name, int $index): string
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
     * @param string $format For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @throws Exception
     */
    public function getValue(string $columnName, string $format = '')
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
     * @throws AdmException
     * @throws Exception
     */
    public function moveSequence(string $mode): bool
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
        // the category is increased by one number and is therefore moved further down the list
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
     * @param int $id Unique men_id
     * @return bool Returns **true** if one record is found
     * @throws Exception
     */
    public function readDataById(int $id): bool
    {
        $returnValue = parent::readDataById($id);

        if ($returnValue) {
            $this->elementTable = TBL_MENU;
            $this->elementColumn = 'men_id';
        }

        return $returnValue;
    }

    /**
     * Reads a category out of the table in database selected by different columns in the table.
     * The columns are committed with an array where every element index is the column name and the value is the column value.
     * The columns and values must be selected so that they identify only one record.
     * If the sql will find more than one record the method returns **false**.
     * Per default all columns of adm_categories will be read and stored in the object.
     * @param array $columnArray An array where every element index is the column name and the value is the column value
     * @return bool Returns **true** if one record is found
     * @throws AdmException
     */
    public function readDataByColumns(array $columnArray): bool
    {
        $returnValue = parent::readDataByColumns($columnArray);

        if ($returnValue) {
            $this->elementTable = TBL_MENU;
            $this->elementColumn = 'men_id';
        }

        return $returnValue;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * If a new record is inserted than the next free sequence will be determined.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws AdmException
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        global $gCurrentSession;

        $this->db->startTransaction();

        if ($this->newRecord) {
            // if new field than generate new name intern, otherwise no change will be made
            $this->setValue('men_name_intern', $this->getNewNameIntern($this->getValue('men_name', 'database'), 1));

            // Determine the highest sequence number of the category when inserting
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

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     *@throws AdmException
     */
    public function setValue(string $columnName, $newValue, bool $checkValue = true): bool
    {
        if ($newValue !== parent::getValue($columnName) && $checkValue) {
            if ($columnName === 'men_icon' && $newValue !== '') {
                // check if font awesome syntax is used
                if (!preg_match('/fa-[a-zA-z0-9]/', $newValue)) {
                    throw new AdmException('SYS_INVALID_FONT_AWESOME');
                }
            }

            return parent::setValue($columnName, $newValue, $checkValue);
        }
        return false;
    }
}
