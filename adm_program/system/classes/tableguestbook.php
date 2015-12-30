<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_guestbook
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Diese Klasse dient dazu ein Gaestebucheintragsobjekt zu erstellen.
 * Eine Gaestebucheintrag kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * moderate()       - guestbook entry will be published, if moderate mode is set
 ***********************************************************************************************
 */
class TableGuestbook extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_guestbook.
     * If the id is set than the specific guestbook will be loaded.
     * @param object $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int    $gbo_id   The recordset of the guestbook with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $gbo_id = 0)
    {
        parent::__construct($database, TBL_GUESTBOOK, 'gbo', $gbo_id);
    }

    /**
     * Deletes the selected guestbook entry and all comments.
     * After that the class will be initialize.
     * @return @b true if no error occurred
     */
    public function delete()
    {
        $this->db->startTransaction();

        // erst einmal alle vorhanden Kommentare zu diesem Gaestebucheintrag loeschen...
        $sql = 'DELETE FROM '.TBL_GUESTBOOK_COMMENTS.' WHERE gbc_gbo_id = '. $this->getValue('gbo_id');
        $result = $this->db->query($sql);

        $return = parent::delete();

        $this->db->endTransaction();
        return $return;
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
        if($columnName === 'gbo_text')
        {
            if(!isset($this->dbColumns['gbo_text']))
            {
                $value = '';
            }
            elseif($format === 'database')
            {
                $value = html_entity_decode(strStripTags($this->dbColumns['gbo_text']));
            }
            else
            {
                $value = $this->dbColumns['gbo_text'];
            }
        }
        else
        {
            $value = parent::getValue($columnName, $format);
        }

        return $value;
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
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization;

        if($this->new_record)
        {
            $this->setValue('gbo_org_id', $gCurrentOrganization->getValue('org_id'));
            $this->setValue('gbo_ip_address', $_SERVER['REMOTE_ADDR']);
        }

        parent::save($updateFingerPrint);
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
        if($newValue !== '')
        {
            if($columnName === 'gbo_email')
            {
                $newValue = admStrToLower($newValue);
                if (!strValidCharacters($newValue, 'email'))
                {
                    // falls die Email ein ungueltiges Format aufweist wird sie nicht gesetzt
                    return false;
                }
            }
            elseif($columnName === 'gbo_homepage')
            {
                // Homepage darf nur gueltige Zeichen enthalten
                if (!strValidCharacters($newValue, 'url'))
                {
                    return false;
                }
                // Homepage noch mit http vorbelegen
                if(strpos(admStrToLower($newValue), 'http://')  === false
                && strpos(admStrToLower($newValue), 'https://') === false)
                {
                    $newValue = 'http://'. $newValue;
                }
            }
        }

        if($columnName === 'gbo_text')
        {
            return parent::setValue($columnName, $newValue, false);
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
