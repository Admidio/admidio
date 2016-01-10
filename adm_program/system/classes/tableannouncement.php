<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_announcements
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Diese Klasse dient dazu ein Ankuendigungsobjekt zu erstellen.
 * Eine Ankuendigung kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * editRight()       - prueft, ob die Ankuendigung von der aktuellen Orga bearbeitet werden darf
 ***********************************************************************************************
 */

/**
 * Class TableAnnouncement
 */
class TableAnnouncement extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_announcements.
     * If the id is set than the specific announcement will be loaded.
     * @param object $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int    $ann_id   The recordset of the announcement with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $ann_id = 0)
    {
        parent::__construct($database, TBL_ANNOUNCEMENTS, 'ann', $ann_id);
    }

    /**
     * prueft, ob die Ankuendigung von der aktuellen Orga bearbeitet werden darf
     * @return bool
     */
    public function editRight()
    {
        global $gCurrentOrganization;

        // Ankuendigung der eigenen Orga darf bearbeitet werden
        if($this->getValue('ann_org_id') === $gCurrentOrganization->getValue('org_id'))
        {
            return true;
        }
        // Ankuendigung von Kinder-Orgas darf bearbeitet werden, wenn diese als global definiert wurden
        elseif($this->getValue('ann_global') == true
        && $gCurrentOrganization->isChildOrganization($this->getValue('ann_org_id')))
        {
            return true;
        }

        return false;
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
        if($columnName === 'ann_description')
        {
            if(!isset($this->dbColumns['ann_description']))
            {
                $value = '';
            }

            elseif($format === 'database')
            {
                $value = html_entity_decode(strStripTags($this->dbColumns['ann_description']), ENT_QUOTES, 'UTF-8');
            }
            else
            {
                $value = $this->dbColumns['ann_description'];
            }
        }
        else
        {
            $value = parent::getValue($columnName, $format);
        }

        return $value;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * The current organization will be set per default.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization;

        if($this->new_record)
        {
            $this->setValue('ann_org_id', $gCurrentOrganization->getValue('org_id'));
        }

        return parent::save($updateFingerPrint);
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
        if($columnName === 'ann_description')
        {
            return parent::setValue($columnName, $newValue, false);
        }
        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
