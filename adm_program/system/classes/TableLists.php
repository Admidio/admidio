<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_lists
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

class TableLists extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_lists.
     * If the id is set than the specific list will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $lstId The recordset of the list with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $lstId = 0)
    {
        parent::__construct($database, TBL_LISTS, 'lst', $lstId);
    }

    /**
     * Deletes the selected list with all associated fields.
     * After that the class will be initialize.
     * @return bool **true** if no error occurred
     * @throws Exception
     * @throws AdmException SYS_ERROR_DELETE_DEFAULT_LIST
     */
    public function delete(): bool
    {
        global $gSettingsManager, $gL10n;

        $lstId = (int) $this->getValue('lst_id');

        // if this list is the default configuration of a module than it couldn't be deleted
        if ($lstId === $gSettingsManager->getInt('groups_roles_default_configuration')) {
            throw new AdmException('SYS_ERROR_DELETE_DEFAULT_LIST', array($this->getValue('lst_name'), $gL10n->get('SYS_GROUPS_ROLES')));
        }
        if ($lstId === $gSettingsManager->getInt('events_list_configuration')) {
            throw new AdmException('SYS_ERROR_DELETE_DEFAULT_LIST', array($this->getValue('lst_name'), $gL10n->get('SYS_EVENTS')));
        }
        if ($lstId === $gSettingsManager->getInt('contacts_list_configuration')) {
            throw new AdmException('SYS_ERROR_DELETE_DEFAULT_LIST', array($this->getValue('lst_name'), $gL10n->get('SYS_CONTACTS')));
        }

        $this->db->startTransaction();

        // Delete all columns of the list
        $sql = 'DELETE FROM '.TBL_LIST_COLUMNS.'
                      WHERE lsc_lst_id = ? -- $lstId';
        $this->db->queryPrepared($sql, array($lstId));

        $return = parent::delete();

        $this->db->endTransaction();

        return $return;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * Per default the organization, user and timestamp will be set.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws AdmException
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        $this->setValue('lst_timestamp', DATETIME_NOW);
        $this->setValue('lst_usr_id', $GLOBALS['gCurrentUserId']);

        if ($this->newRecord && empty($this->getValue('lst_org_id'))) {
            $this->setValue('lst_org_id', $GLOBALS['gCurrentOrgId']);
        }

        // if "lst_global" isn't set explicit to "1", set it to "0"
        if ((int) $this->getValue('lst_global') !== 1) {
            $this->setValue('lst_global', 0);
        }

        return parent::save($updateFingerPrint);
    }
}
