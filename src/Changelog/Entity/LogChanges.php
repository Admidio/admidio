<?php

namespace Admidio\Changelog\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Changelog\Service\ChangelogService;
use Ramsey\Uuid\Uuid;

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
     * The DB table this record refers to. A lot of functionality depends on the underlying table (e.g., links to the
     * original object, display strings, etc.)
     * @var string
     */
    protected string $objectTableName;

    /**
     * Origin of the changes that are logged in this request, stored in log_comment. A normal web
     * request leaves this empty; the command-line utility sets the executed command, so an
     * administrator can tell a headless change apart from one made in the browser.
     * @var string
     */
    private static string $originComment = '';

    /**
     * Mark all following changelog records as originating from the given context.
     * @param string $comment For example "CLI: group:adduser".
     */
    public static function setOriginComment(string $comment): void
    {
        self::$originComment = $comment;
    }

    /**
     * UUID of the change that is currently being logged. All log entries that are written while
     * a change set is open belong together, e.g. the creation entry and the entries of the initial
     * values of a new record, or all fields that one save has modified.
     *
     * The value is null if no change set is open and an empty string if a change set is open but
     * its UUID has not been needed yet. The UUID is only generated once an entry is really written,
     * so that a save of an unlogged table does not create one.
     * @var string|null
     */
    protected static ?string $changeUuid = null;


    /**
     * Open a change set. All log entries that are written until the change set is closed again
     * share one UUID in log_change_uuid, so that the change history can show them together.
     *
     * Change sets nest like the transactions of the Database class: the outermost one wins. If a
     * change set is already open, this call joins it instead of starting a new one, and the
     * matching endChangeSet() then leaves it open. That is what makes one save of a record that
     * saves further records of its own, such as a user with its profile fields, one single change.
     *
     * Every call has to be paired with an endChangeSet() call that is passed the returned value.
     *
     * @return string|null Returns the change set that was open before, to be passed to endChangeSet()
     */
    public static function startChangeSet(): ?string
    {
        $previousChangeSet = self::$changeUuid;
        if ($previousChangeSet === null) {
            self::$changeUuid = '';
        }
        return $previousChangeSet;
    }

    /**
     * Close the change set that the matching startChangeSet() call has opened. If that call only
     * joined a change set that was already open, the change set stays open.
     *
     * @param string|null $previousChangeSet The return value of the matching startChangeSet() call
     * @return void
     */
    public static function endChangeSet(?string $previousChangeSet): void
    {
        if ($previousChangeSet === null) {
            self::$changeUuid = null;
        }
    }

    /**
     * UUID of the change set that is currently open, generating it if this is the first entry
     * that is written within it.
     *
     * @return string|null Returns the UUID or null if no change set is open
     */
    protected static function getChangeUuid(): ?string
    {
        if (self::$changeUuid === '') {
            self::$changeUuid = (string)Uuid::uuid4();
        }
        return self::$changeUuid;
    }


    /**
     * Constructor that will create an object of a recordset of the table adm_log_changes.
     * If the id is set, then the specific log entry will be loaded.
     *
     * A log entry is a snapshot: it stores the name of the record and of the related object as
     * they were at the time of the change, because the record it refers to may be changed or
     * deleted afterwards. It is therefore deliberately not joined to the table it refers to.
     *
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $table
     * @param int $logId
     * @throws Exception
     */
    public function __construct(Database $database, string $table = '', $logId = 0)
    {
        // We want to log changes from everyone, even those not allowed to change anything -> detect possible bugs or at least hold people accountable!
        $this->saveChangesWithoutRights();
        $this->objectTableName = $table;
        parent::__construct($database, TBL_LOG_CHANGES, 'log', $logId);
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
     * Set up the base fields for the log entry.
     * @param string $table The database table (without the prefix)
     * @param int $id The record ID of the affected record
     * @param string|null $uuid The UUID of the affected record (if any UUID exists)
     * @param string|null $objectName Human-readable representation of the record (used in the log view)
     * @param string $action The cause of the log (CREATED, DELETED, MODIFY)
     * @throws Exception
     */
    protected function setLogBaseValues(string $table, int $id = 0, ?string $uuid = null, ?string $objectName = null, string $action = 'MODIFY'): void
    {
        $this->objectTableName = $table;
        $this->setValue('log_table', $this->objectTableName);
        $this->setValue('log_record_id', $id);
        $this->setValue('log_record_uuid', $uuid);
        $this->setValue('log_record_name', $objectName);
        $this->setValue('log_action', $action);

    }


    /**
     * Set up all fields for the log entry of the object creation.
     * @param string $table The database table (without the prefix)
     * @param int $id The record ID of the inserted record
     * @param string|null $uuid
     * @param string|null $objectName Human-readable representation of the record (used in the log view)
     * @throws Exception
     */
    public function setLogCreation(string $table, int $id = 0, ?string $uuid = null, ?string $objectName = null): void
    {
        $this->setLogBaseValues($table, $id, $uuid, $objectName, 'CREATED');
    }


    /**
     * Set up all fields for the log entry of the object deletion.
     * @param string $table The database table (without the prefix)
     * @param int $id The record ID of the inserted record
     * @param string|null $uuid
     * @param string|null $objectName Human-readable representation of the record (used in the log view)
     * @throws Exception
     */
    public function setLogDeletion(string $table, int $id = 0, ?string $uuid = null, ?string $objectName = null): void
    {
        $this->setLogBaseValues($table, $id, $uuid, $objectName, 'DELETED');
    }


    /**
     * Set up all fields for the log entry of the object deletion.
     * @param string $table The database table (without the prefix)
     * @param int $id The record ID of the inserted record
     * @param string|null $uuid
     * @param string|null $objectName Human-readable representation of the record (used in the log view)
     * @param string|null $field
     * @param string|null $fieldName
     * @param string|null $oldValue
     * @param string|null $newValue
     * @throws Exception
     */
    public function setLogModification(string $table, int $id, ?string $uuid = null, ?string $objectName = null, ?string $field = null, ?string $fieldName = null, ?string $oldValue = null, ?string $newValue = null): void
    {
        $this->setLogBaseValues($table, $id, $uuid, $objectName);

        $this->setValue('log_field', $field);
        $this->setValue('log_field_name', $fieldName);
        $this->setValue('log_value_old', $oldValue);
        $this->setValue('log_value_new', $newValue);
    }

    /**
     * Sets the object ID for links if the affected DB record does not have its own page and instead needs a different object to link to.
     * Examples are user fields, where we need to link to the user itself, as the user field records have no modification page.
     *
     * @param int|string $linkID
     * @return void
     * @throws Exception
     */
    public function setLogLinkID(int|string $linkID): void
    {
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
     * @throws Exception
     */
    public function setLogRelated(string $relatedID, string $relatedName): void
    {
        $this->setValue('log_related_id', $relatedID);
        $this->setValue('log_related_name', $relatedName);
    }


    /**
     * Save all changed columns of the recordset in table of a database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor, then these columns
     * with their timestamp will be updated.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has
     *                                columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done, then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        global $gCurrentOrgId;

        if (in_array($this->objectTableName, ChangelogService::$noLogTables) ||
            !ChangelogService::isTableLogged($this->objectTableName)) {
            return false;
        }

        if (self::$originComment !== '' && (string)$this->getValue('log_comment') === '') {
            $this->setValue('log_comment', self::$originComment);
        }

        // adm_log_changes is a global table shared by all organizations. Remember the organization
        // the change was made in, so that the change history can be restricted to it later on.
        if (isset($gCurrentOrgId) && $gCurrentOrgId > 0 && (int)$this->getValue('log_org_id') === 0) {
            $this->setValue('log_org_id', $gCurrentOrgId);
        }

        // Remember which entries were written by the same change, so that all fields that one save
        // has modified can be shown together. The column is missing if the database of the
        // installation has not been updated yet, in which case the change is logged without it.
        if (array_key_exists('log_change_uuid', $this->dbColumns)
            && (string)$this->getValue('log_change_uuid') === '') {
            $changeUuid = self::getChangeUuid();
            if ($changeUuid !== null) {
                $this->setValue('log_change_uuid', $changeUuid);
            }
        }

        return parent::save($updateFingerPrint);
    }

}
