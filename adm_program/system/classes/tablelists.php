<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_lists
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableLists
 * Diese Klasse dient dazu ein Listenobjekt zu erstellen.
 * Eine Liste kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * setDefault()       - Aktuelle Liste wird zur Default-Liste der Organisation
 */
class TableLists extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_lists.
     * If the id is set than the specific list will be loaded.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $lstId    The recordset of the list with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $lstId = 0)
    {
        parent::__construct($database, TBL_LISTS, 'lst', $lstId);
    }

    /**
     * Deletes the selected list with all associated fields.
     * After that the class will be initialize.
     * @throws AdmException LST_ERROR_DELETE_DEFAULT_LIST
     * @return bool @b true if no error occurred
     */
    public function delete()
    {
        global $gPreferences;

        $lstId = (int) $this->getValue('lst_id');

        // if this list is the default configuration than it couldn't be deleted
        if ($lstId === (int) $gPreferences['lists_default_configuration'])
        {
            throw new AdmException('LST_ERROR_DELETE_DEFAULT_LIST', $this->getValue('lst_name'));
        }

        $this->db->startTransaction();

        // Delete all columns of the list
        $sql = 'DELETE FROM '.TBL_LIST_COLUMNS.' WHERE lsc_lst_id = '.$lstId;
        $this->db->query($sql);

        $return = parent::delete();

        $this->db->endTransaction();

        return $return;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * Per default the organization, user and timestamp will be set.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization, $gCurrentUser;

        $orgId = $this->getValue('lst_org_id');

        $this->setValue('lst_timestamp', DATETIME_NOW);
        $this->setValue('lst_usr_id', $gCurrentUser->getValue('usr_id'));

        if ($this->new_record && empty($orgId))
        {
            $this->setValue('lst_org_id', $gCurrentOrganization->getValue('org_id'));
        }

        // if "lst_global" isn't set explicit to "1", set it to "0"
        if ((int) $this->getValue('lst_global') !== 1)
        {
            $this->setValue('lst_global', 0);
        }

        return parent::save($updateFingerPrint);
    }
}
