<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_texts
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class TableText extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_texts.
     * If the id is set than the specific text will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $name The recordset of the text with this name will be loaded.
     *                           If name isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, string $name = '')
    {
        parent::__construct($database, TBL_TEXTS, 'txt', $name);
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return int|string Returns the value of the database column.
     *                    If the value was manipulated before with **setValue** than the manipulated value is returned.
     */
    public function getValue(string $columnName, string $format = '')
    {
        if ($columnName === 'txt_text') {
            return $this->dbColumns['txt_text'];
        }

        return parent::getValue($columnName, $format);
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the organization will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws AdmException
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        if ($this->newRecord && $this->getValue('txt_org_id') === '') {
            // Insert
            $this->setValue('txt_org_id', $GLOBALS['gCurrentOrgId']);
        }

        return parent::save($updateFingerPrint);
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed $newValue The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @throws AdmException
     */
    public function setValue(string $columnName, $newValue, bool $checkValue = true): bool
    {
        if ($columnName === 'txt_text') {
            // convert <br /> to a normal line feed
            $newValue = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/', chr(13) . chr(10), $newValue);
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
