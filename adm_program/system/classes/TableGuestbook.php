<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_guestbook
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Diese Klasse dient dazu ein Gaestebucheintragsobjekt zu erstellen.
 * Eine Gaestebucheintrag kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * moderate()       - guestbook entry will be published, if moderate mode is set
 */
class TableGuestbook extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_guestbook.
     * If the id is set than the specific guestbook will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $gboId    The recordset of the guestbook with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $gboId = 0)
    {
        parent::__construct($database, TBL_GUESTBOOK, 'gbo', $gboId);
    }

    /**
     * Deletes the selected guestbook entry and all comments.
     * After that the class will be initialize.
     * @return bool **true** if no error occurred
     */
    public function delete()
    {
        $this->db->startTransaction();

        // Delete all available comments to this guestbook entry
        $sql = 'DELETE FROM '.TBL_GUESTBOOK_COMMENTS.'
                      WHERE gbc_gbo_id = ? -- $this->getValue(\'gbo_id\')';
        $this->db->queryPrepared($sql, array((int) $this->getValue('gbo_id')));

        $return = parent::delete();

        $this->db->endTransaction();

        return $return;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return mixed Returns the value of the database column.
     *         If the value was manipulated before with **setValue** than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        if ($columnName === 'gbo_text') {
            if (!isset($this->dbColumns['gbo_text'])) {
                $value = '';
            } elseif ($format === 'database') {
                $value = html_entity_decode(StringUtils::strStripTags($this->dbColumns['gbo_text']));
            } else {
                $value = $this->dbColumns['gbo_text'];
            }

            return $value;
        }

        return parent::getValue($columnName, $format);
    }

    /**
     * guestbook entry will be published, if moderate mode is set
     */
    public function moderate()
    {
        // unlock entry
        $this->setValue('gbo_locked', '0');
        $this->save();
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the organization and ip address will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        if ($this->newRecord) {
            $this->setValue('gbo_org_id', $GLOBALS['gCurrentOrgId']);
            $this->setValue('gbo_ip_address', $_SERVER['REMOTE_ADDR']);
        }

        return parent::save($updateFingerPrint);
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        if ($checkValue) {
            if ($columnName === 'gbo_text') {
                return parent::setValue($columnName, $newValue, false);
            } elseif ($columnName === 'gbo_email' && $newValue !== '') {
                // If Email has a invalid format, it won't be set
                if (!StringUtils::strValidCharacters($newValue, 'email')) {
                    return false;
                }
            } elseif ($columnName === 'gbo_homepage' && $newValue !== '') {
                $newValue = admFuncCheckUrl($newValue);

                if ($newValue === false) {
                    return false;
                }
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
