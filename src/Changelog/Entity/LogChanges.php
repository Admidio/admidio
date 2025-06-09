<?php
namespace Admidio\Changelog\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Changelog\Service\ChangelogService;


/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Handle logging of changes to various different objects (user profile data, user fields,
 * memberships, but also configuration of fields) and manage it in the database table
 * adm_log_changes
 *
 */
class LogChanges extends Entity
{
    
    
    /**
     *  The DB table this record refers to. A lot of functionality depends on the underlying table (e.g. links to the original object, display strings, etc.)
     * @var string
     */
    protected string $objectTableName;


    /**
     * Constructor that will create an object of a recordset of the table adm_log_changes.
     * If the id is set than the specific membership will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $memId    The recordset of the membership with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, string $table = '', $logId = 0)
    {
        // We want to log changes from everyone, even those not allowed to change anything -> detect possible bugs or at least hold people accountable!
        $this->saveChangesWithoutRights();
        $this->objectTableName = $table;
        $this->connectReferencedTable($this->objectTableName);
        parent::__construct($database, TBL_LOG, 'log', $logId);
    }


    /**
     * As the log table is a generic table referencing objects of all DB tables, we cannot use Foreign keys and hardcoded connections to additional tables
     * Instead, we derive the connected additional table and the corresponding keys from the $table argument passed to the constructor (or loaded from the DB)
     */
    private function connectReferencedTable(string $table)
    {
               // Depending on the type of object (identified by DB table), connect different additional object tables to retrieve record data
        switch ($table) {
            case 'announcements':
                $this->connectAdditionalTable(TBL_ANNOUNCEMENTS, 'ann_id', 'log_record_id');
                break;
            case 'categories':
                $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'log_record_id');
                break;
            case 'category_report':
                $this->connectAdditionalTable(TBL_CATEGORY_REPORT, 'crt_id', 'log_record_id');
                break;
            case 'components':
                $this->connectAdditionalTable(TBL_COMPONENTS, 'com_id', 'log_record_id');
                break;
            case 'events':
                $this->connectAdditionalTable(TBL_EVENTS, 'dat_id', 'log_record_id');
                break;
            case 'files':
                $this->connectAdditionalTable(TBL_FILES, 'fil_id', 'log_record_id');
                break;
            case 'folders':
                $this->connectAdditionalTable(TBL_FOLDERS, 'fol_id', 'log_record_id');
                break;
            case 'links':
                $this->connectAdditionalTable(TBL_LINKS, 'lnk_id', 'log_record_id');
                break;
            case 'list_columns':
                $this->connectAdditionalTable(TBL_LIST_COLUMNS, 'lsc_id', 'log_record_id');
                break;
            case 'lists':
                $this->connectAdditionalTable(TBL_LISTS, 'lst_id', 'log_record_id');
                break;
            case 'members':
                $this->connectAdditionalTable(TBL_MEMBERS, 'mem_id', 'log_record_id');
                break;
            case 'menu':
                $this->connectAdditionalTable(TBL_MENU, 'men_id', 'log_record_id');
                break;
            case 'messages':
                $this->connectAdditionalTable(TBL_MESSAGES, 'msg_id', 'log_record_id');
                break;
            case 'messages_attachments':
                $this->connectAdditionalTable(TBL_MESSAGES_ATTACHMENTS, 'msa_id', 'log_record_id');
                break;
            case 'messages_content':
                $this->connectAdditionalTable(TBL_MESSAGES_CONTENT, 'msc_id', 'log_record_id');
                break;
            case 'messages_recipients':
                $this->connectAdditionalTable(TBL_MESSAGES_RECIPIENTS, 'msr_id', 'log_record_id');
                break;
            case 'organizations':
                $this->connectAdditionalTable(TBL_ORGANIZATIONS, 'org_id', 'log_record_id');
                break;
            case 'photos':
                $this->connectAdditionalTable(TBL_PHOTOS, 'pho_id', 'log_record_id');
                break;
            case 'preferences':
                $this->connectAdditionalTable(TBL_PREFERENCES, 'prf_id', 'log_record_id');
                break;
            case 'role_dependencies':
                // TODO: How shall we connect role dependencies? There is no single unique key, but two (parent and child!)
                // $this->connectAdditionalTable(TBL_ROLE_DEPENDENCIES, '', 'log_record_id');
                break;
            case 'roles':
                $this->connectAdditionalTable(TBL_ROLES, 'rol_id', 'log_record_id');
                break;
            case 'roles_rights':
                $this->connectAdditionalTable(TBL_ROLES_RIGHTS, 'ror_id', 'log_record_id');
                break;
            case 'roles_rights_data':
                $this->connectAdditionalTable(TBL_ROLES_RIGHTS_DATA, 'rrd_id', 'log_record_id');
                break;
            case 'inventory_fields':
                $this->connectAdditionalTable(TBL_INVENTORY_FIELDS, 'inf_id', 'log_record_id');
                break;
            case 'inventory_items':
                $this->connectAdditionalTable(TBL_INVENTORY_ITEMS, 'ini_id', 'log_record_id');
                break;
            case 'inventory_item_lend_data':
                $this->connectAdditionalTable(TBL_INVENTORY_ITEM_LEND_DATA, 'inl_id', 'log_record_id');
                break;
            case 'inventory_item_data':
                $this->connectAdditionalTable(TBL_INVENTORY_ITEM_DATA, 'ind_id', 'log_record_id');
                break;
            case 'rooms':
                $this->connectAdditionalTable(TBL_ROOMS, 'room_id', 'log_record_id');
                break;
            case 'texts':
                $this->connectAdditionalTable(TBL_TEXTS, 'txt_id', 'log_record_id');
                break;
            case 'users':
                $this->connectAdditionalTable(TBL_USERS, 'usr_id', 'log_record_id');
                break;
            case 'user_data':
                $this->connectAdditionalTable(TBL_USER_DATA, 'usd_id', 'log_record_id');
                break;
            case 'user_fields':
                $this->connectAdditionalTable(TBL_USER_FIELDS, 'usf_id', 'log_record_id');
                break;
            case 'user_relations':
                $this->connectAdditionalTable(TBL_USER_RELATIONS, 'ure_id', 'log_record_id');
                break;
            case 'user_relation_types':
                $this->connectAdditionalTable(TBL_USER_RELATION_TYPES, 'urt_id', 'log_record_id');
                break;
        }

    }


    /**
     * Logs Creation of the DB record -> Changes to the log table are NOT logged!
     * 
     * @return true Returns **false**, since no logging occurs
     */
    public function logCreation(): bool
    {
        return false;
    }

    /**
     * Logs deletion of the DB record -> Changes to the log table are NOT logged!
     * 
     * @return true Returns **false**, since no logging occurs
     */
    public function logDeletion(): bool
    {
        return false;
    }


    /**
     * Logs all modifications of the DB record -> Changes to the log table are NOT logged!
     * @param array $logChanges Array of all changes, generated by the save method
     * 
     * @return true Returns **false**, since no logging occurs
     */
    public function logModifications(array $logChanges): bool
    {
        return false;
    }


    /**
     * Set up the base fields for the log entry .
     * @param string $table The database table (without the prefix)
     * @param int $id The record ID of the affected record
     * @param string $uuid The UUID of the affected record (if any UUID exists)
     * @param string $objectname Human readable representation of the record (used in the log view)
     * @param string $action The cause of the log (CREATED, DELETED, MODIFY)
     */
    protected function setLogBasevalues(string $table, int $id = 0, ?string $uuid = null, ?string $objectname = null, string $action = 'MODIFY') {
        $this->objectTableName = $table;
        $this->setValue('log_table', $this->objectTableName);
        $this->setValue('log_record_id', $id);
        $this->setValue('log_record_uuid', $uuid);
        $this->setValue('log_record_name', $objectname);
        $this->setValue('log_action', $action);

    }


    /**
     * Set up all fields for the log entry of the object creation.
     * @param string $table The database table (without the prefix)
     * @param int $id The record ID of the inserted record
     * @param string $objectname Human readable representation of the record (used in the log view)
     */
    public function setLogCreation(string $table, int $id = 0, ?string $uuid = null, ?string $objectname = null)
    {
        $this->setLogBasevalues($table, $id, $uuid, $objectname, 'CREATED');
    }


    /**
     * Set up all fields for the log entry of the object deletion.
     * @param string $table The database table (without the prefix)
     * @param int $id The record ID of the inserted record
     * @param string $objectname Human readable representation of the record (used in the log view)
     */
    public function setLogDeletion(string $table, int $id = 0, ?string $uuid = null, ?string $objectname = null) 
    {
        $this->setLogBasevalues($table, $id, $uuid, $objectname, 'DELETED');
    }


    /**
     * Set up all fields for the log entry of the object deletion.
     * @param string $table The database table (without the prefix)
     * @param int $id The record ID of the inserted record
     * @param string $objectname Human readable representation of the record (used in the log view)
     */
    public function setLogModification(string $table, int $id, ?string $uuid = null, ?string $objectname = null, ?string $field = null, ?string $fieldName = null, ?string $oldValue = null, ?string $newValue = null)
    {
        $this->setLogBasevalues($table, $id, $uuid, $objectname, 'MODIFY');

        $this->setValue('log_field', $field);
        $this->setValue('log_field_name', $fieldName);
        $this->setValue('log_value_old', $oldValue);
        $this->setValue('log_value_new', $newValue);
    }

    /**
     * Sets the object ID for links, if the affected DB record does not have its own page and instead needs a different object to link to.
     * Examples are user fields, where we need to link to the user itself, as the user field records has no modification page.
     * 
     * @param int $linkID
     * @return void
     */
    public function setLogLinkID(int|string $linkID) {
        $this->setValue('log_record_linkid', $linkID);
    }

    /**
     * Sets a related object in the database for the current log record.
     * Examples are group memberships, where the membership itself links to the user (via link ID), 
     * but we also want to record the group (and link to it in the changelog table).
     * 
     * @param string $relatedID The ID or UUID of the related object (given as string)
     * @param string $relatedName The name of the related object
     * @return void
     */
    public function setLogRelated(string $relatedID, string $relatedName) {
        $this->setValue('log_related_id', $relatedID);
        $this->setValue('log_related_name', $relatedName);
    }


    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        if (in_array($this->objectTableName, ChangelogService::$noLogTables) ||
            !ChangelogService::isTableLogged($this->objectTableName)) {
            return false;
        }
        global $gCurrentSession, $gChangeNotification, $gCurrentUser;

        $newRecord = $this->newRecord;
        return parent::save($updateFingerPrint);
    }

}
