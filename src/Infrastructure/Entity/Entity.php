<?php

namespace Admidio\Infrastructure\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Users\Entity\User;
use Admidio\Changelog\Entity\LogChanges;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Hooks\Hooks;
use Admidio\Hooks\Service\EntityHookQueue;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Hooks\ValueObject\EntityFieldChange;
use DateTime;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * @brief Controls read and write access to database tables
 *
 * This class should help you to read and write records of database tables.
 * You create an object for a special table, and then you are able to read
 * a special record, manipulate him and write him back. Also, new records can
 * be created with this class. The advantage of this class is that you are
 * independent of SQL. You can use @c getValue, @c setValue, @c readData
 * and @c save to handle the record.
 *
 * **Code example**
 * ```
 * // create an object for table adm_roles of role 4711
 * $roleId = 4177;
 * $role = new Entity($gDb, TBL_ROLES, 'rol', $roleId);
 *
 * // read max. Members and add 1 to the count
 * $maxMembers = $role->getValue('rol_max_members');
 * $maxMembers = $maxMembers + 1;
 * $role->setValue('rol_max_members', $maxMembers);
 * $role->save();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Entity
{
    /**
     * @var array<string,string> Array with subarray that contains additional tables and their connected fields that should be selected when data is read
     */
    protected array $additionalTables = array();
    /**
     * @var string Name of the database table from this object. This must be the table name with the installation-specific prefix e.g. **demo_users**
     */
    protected string $tableName;
    /**
     * @var string The prefix of each column that this table has. E.g., the table adm_users has the column prefix **usr**
     */
    protected string $columnPrefix;
    /**
     * @var string Name of the unique autoincrement index column from the database table
     */
    protected string $keyColumnName = '';
    /**
     * @var Database An object of the class Database for communication with the database
     */
    protected Database $db;

    /**
     * @var bool Flag whether a new data set or existing data set is being edited
     */
    protected bool $newRecord;
    /**
     * @var bool Flag whether a save() of this object has inserted the record into the database.
     *           Unlike newRecord it stays true when the same object is saved again afterwards, so
     *           code that runs after save() can still tell a created record from a changed one.
     */
    protected bool $insertedRecord = false;
    /**
     * @var bool Flag if the data of this record must be inserted or updated
     */
    protected bool $insertRecord;
    /**
     * @var bool The Flag will be set to true if data in array dbColumns was changed
     */
    protected bool $columnsValueChanged;
    /**
     * @var array<string,mixed> Array over all fields of the corresponding table for the selected record
     */
    protected array $dbColumns = array();
    /**
     * @var array<string,array<string,mixed>> Array which stores further information (changed yes/no, field type, previous value)
     */
    protected array $columnsInfos = array();
    /**
     * @var bool If this flag is set, then some right checks will be disabled, so that the object could be saved also
     * if the current user doesn't have the right to do this.
     */
    protected bool $saveChangesWithoutRights;

    /**
     * Flag to enable/disable logging changes to the database.
     * @var bool If this flag is set (default), all changes will be logged to the database if the corresponding preferences item is set.
     * Setting this to false will disable logging in all cases, even with the preference set.
     */
    protected static bool $loggingEnabled = true;

    /**
     * Flag to enable/disable the persistence hooks of the entities.
     * @var bool If this flag is set (default), every entity that has a hook ID dispatches its
     * create, update and delete hooks. The installation and the update switch it off, because the
     * schema and the core are not in a state a plugin could work with while they run.
     */
    protected static bool $hooksEnabled = true;

    /**
     * Constructor that will create an object of a recordset from the specified table.
     * If the id is set, then this recordset will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $tableName The name of the database table. Because of specific prefixes, this should be the defined value e.g. **TBL_USERS**
     * @param string $columnPrefix The prefix of each column from that table. E.g., for table **adm_roles** this is **rol**
     * @param int|string $id The id of the recordset that should be loaded. If id isn't set, then an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, string $tableName, string $columnPrefix, int|string $id = '')
    {
        $this->db =& $database;
        $this->tableName = $tableName;
        $this->columnPrefix = $columnPrefix;

        // if an ID is committed, then read data out of a database
        if ($id > 0) {
            $this->readDataById($id);
        } else {
            $this->clear();
        }
    }

    /**
     * A wakeup add the current database object to this class
     */
    public function __wakeup()
    {
        global $gDb;

        if ($gDb instanceof Database) {
            $this->db = $gDb;
        }
    }

    /**
     * Initializes all class parameters and deletes all read data.
     * Also, the database structure of the associated table will be
     * read and stored in the arrays **dbColumns** and **columnsInfos**
     * @throws Exception
     */
    public function clear(): void
    {
        $this->newRecord = true;
        $this->insertRecord = true;
        $this->insertedRecord = false;
        $this->columnsValueChanged = false;
        $this->saveChangesWithoutRights = false;

        if (count($this->columnsInfos) > 0) {
            // the column infos have already been read and will now only be reinitialized
            foreach ($this->dbColumns as $fieldName => &$fieldValue) {
                $fieldValue = ''; // $this->dbColumns[$fieldName] = '';
                $this->columnsInfos[$fieldName]['changed'] = false;
                $this->columnsInfos[$fieldName]['previousValue'] = null;
            }
            unset($fieldValue);
        } else {
            // read all column information of the tables
            $this->setColumnsInfos();
        }
    }

    /**
     * Adds a table with the connected fields to a member array. This table will be added to the
     * select statement if data is read and the connected record is available in this class.
     * The connected table must have a foreign key in the class table.
     * @param string $table Database table name that should be connected.
     * @param string $columnNameAdditionalTable Name of the column in the connected table that has the foreign key to the class table
     * @param string $columnNameClassTable Name of the column in the class table that has the foreign key to the connected table
     *
     * **Code example**
     * ```
     * // Constructor of adm_events object where the category (calendar) is connected
     * public function __construct($database, $datId = 0)
     * {
     *     $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'dat_cat_id');
     *     parent::__construct($db, TBL_EVENTS, 'dat', $datId);
     * }
     * ```
     */
    public function connectAdditionalTable(string $table, string $columnNameAdditionalTable, string $columnNameClassTable): void
    {
        $this->additionalTables[] = array(
            'table' => $table,
            'columnNameAdditionalTable' => $columnNameAdditionalTable,
            'columnNameClassTable' => $columnNameClassTable
        );
    }

    /**
     * Get the name of the underlying database table. This can be used to construct SQL queries without hardcoding the table.
     * @return string The name of the underlying database table
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Get the column prefix of the underlying table. This can be used to construct column names without hardcoding the prefix.
     * @return string The column prefix used for the underlying database table
     */
    public function getColumnPrefix(): string
    {
        return $this->columnPrefix;
    }

    /**
     * Get the key column of the underlying database table. This can be used to construct SQL queries names without hardcoding the column name.
     * @return string The key column name used for the underlying database table
     */
    public function getKeyColumnName(): string
    {
        return $this->keyColumnName;
    }

    /**
     * Reads the number of all records from this table
     * @return int Number of records of this table
     * @throws Exception
     */
    public function countAllRecords(): int
    {
        $sql = 'SELECT COUNT(*) AS count FROM ' . $this->tableName;
        $countStatement = $this->db->queryPrepared($sql);

        return (int)$countStatement->fetchColumn();
    }

    /**
     * Return a human-readable representation of this record.
     * If a column [prefix]_name exists, it is returned, otherwise the id.
     * This method can be overridden in child classes for custom behavior.
     *
     * @return string The readable representation of the record (can also be a translatable identifier)
     */
    public function readableName(): string
    {
        if (array_key_exists($this->columnPrefix . '_name', $this->dbColumns)) {
            $name = $this->dbColumns[$this->columnPrefix . '_name'] ?? '';
        } elseif (array_key_exists($this->columnPrefix . '_title', $this->dbColumns)) {
            $name = $this->dbColumns[$this->columnPrefix . '_title'] ?? '';
        } elseif (array_key_exists($this->columnPrefix . '_headline', $this->dbColumns)) {
            $name = $this->dbColumns[$this->columnPrefix . '_headline'] ?? '';
        } elseif (array_key_exists($this->columnPrefix . '_text', $this->dbColumns)) {
            $name = $this->dbColumns[$this->columnPrefix . '_text'] ?? '';
        } else {
            $name = $this->dbColumns[$this->keyColumnName] ?? '';
        }

        return $this->filterReadableName($name);
    }

    /**
     * Pass the result of readableName() through entity_readable_name and, for an entity that names
     * itself for the persistence hooks (see getHookId()), through <hookId>_readable_name as well.
     * A subclass that overrides readableName() calls this on its own return value instead of
     * dispatching the filter itself, so that one name reaches every entity regardless of how its
     * readable name is built.
     *
     * @param string $name The readable name before filtering.
     * @return string The readable name after both filters.
     */
    protected function filterReadableName(string $name): string
    {
        $name = Hooks::applyFilters('entity_readable_name', $name, $this);

        $hookId = $this->getHookId();
        if ($hookId !== null) {
            $name = Hooks::applyFilters($hookId . '_readable_name', $name, $this);
        }

        return $name;
    }

    /**
     * Return a human-readable representation of the given database field/column.
     * By default, the column name is returned unmodified. Subclasses can override this method.
     * @param string $field The database column
     * @return string The readable representation of the DB column (can also be a translatable identifier)
     */
    public function getFieldTitle(string $field): string
    {
        return $field;
    }

    /**
     * Enable/disable logging globally, irrespective of the preference setting. Used, e.g., during setup / updates
     * @param mixed $enabled Whether logging should be enabled or not.
     * @return void
     */
    public static function setLoggingEnabled(mixed $enabled): void
    {
        self::$loggingEnabled = $enabled;
    }


    /**
     * Switch the persistence hooks of all entities on or off. The installation and the update
     * switch them off: they write records of every kind while the schema and the core are still
     * changing, and a plugin callback has nothing useful to do with that.
     * @param bool $enabled **false** disables the hooks for the rest of the request.
     * @return void
     */
    public static function setHooksEnabled(bool $enabled): void
    {
        self::$hooksEnabled = $enabled;
    }

    /**
     * The stable public identifier of this entity in the hook API. It is the first half of the
     * entity-specific hook names, so an entity with the hook ID **oidc_client** dispatches
     * **oidc_client_created**, **oidc_client_updated** and **oidc_client_deleted**, next to the
     * generic **entity_created**, **entity_updated** and **entity_deleted**.
     *
     * The identifier is a public API and is deliberately not derived from the PHP class name, so
     * that a class can be renamed without breaking the plugins that listen to it.
     *
     * An entity that returns **null**, which is the default, dispatches nothing at all, the
     * generic hooks included. That is how an entity opts out, and it is also why a new entity is
     * silent until someone decides what its public name should be. Sessions, auto logins, the
     * changelog itself and the OAuth tokens stay silent on purpose: they are written constantly,
     * they hold infrastructure state or secrets, and a changelog entry that reports the writing of
     * a changelog entry would not stop.
     *
     * @return string|null Returns the hook ID of this entity, or **null** if it has no hooks.
     */
    public function getHookId(): ?string
    {
        return null;
    }

    /**
     * The columns whose value must not be handed to a hook callback: secrets such as a password,
     * a key or a token, and values that have no meaning outside the record, such as an image. The
     * change set reports that these columns changed and replaces both values with
     * EntityChangeSet::REDACTED_VALUE, and it leaves them out of its snapshot.
     *
     * This is deliberately not getIgnoredLogColumns(), which lists the columns that are noise in
     * the change history. Noise and secrets are two different problems and an entity usually has
     * different columns for each of them.
     *
     * @return array Returns the list of database columns whose value must not leave the record.
     */
    public function getSensitiveHookColumns(): array
    {
        return array();
    }

    /**
     * Whether this entity dispatches persistence hooks at all and at least one callback is
     * registered for the given stage. Building a change set reads the whole record, so nothing of
     * it is built while nobody listens.
     * @param string $suffix The stage, e.g. **updating** or **updated**.
     * @return bool Returns **true** if the stage has to be dispatched.
     */
    protected function hasHookListener(string $suffix): bool
    {
        $hookId = $this->getHookId();

        if (!self::$hooksEnabled || $hookId === null) {
            return false;
        }

        return Hooks::hasAction('entity_' . $suffix) || Hooks::hasAction($hookId . '_' . $suffix);
    }

    /**
     * Dispatch one stage of the persistence lifecycle, to the generic hook and to the hook of this
     * entity. The order nests the two: before the operation the generic hook runs first, after it
     * and on failure the entity-specific hook runs first, so that a callback of the generic layer
     * encloses the callbacks of the specific one.
     *
     * Every stage also hands the callback this object, so it can read fields the change set does not
     * carry - an unchanged column, a related record - or call a domain method on it, with two
     * exceptions: **deleted** and **delete_failed** receive **null** instead. By the time either can
     * fire, delete() has already cleared the object - immediately for a direct failure, later through
     * the commit/rollback queue for everything else - and a bulk deletion reuses one object for every
     * row it removes, so it would as often be the wrong record as an empty one. The change set's
     * snapshot is what those two describe the record from.
     *
     * @param string $suffix The stage, e.g. **updating** or **updated**.
     * @param EntityChangeSet $changeSet What the operation changes or changed.
     * @param bool $genericFirst **true** before the operation, **false** after it and on failure.
     * @param bool $catchErrors **true** for the failure stages, where the original failure has to
     *                          stay the one that Admidio reports.
     * @return void
     */
    protected function dispatchHook(string $suffix, EntityChangeSet $changeSet, bool $genericFirst, bool $catchErrors = false): void
    {
        $names = array('entity_' . $suffix, $this->getHookId() . '_' . $suffix);

        if (!$genericFirst) {
            $names = array_reverse($names);
        }

        $entity = in_array($suffix, array('deleted', 'delete_failed'), true) ? null : $this;

        foreach ($names as $name) {
            if ($catchErrors) {
                Hooks::doActionCatchErrors($name, $changeSet, $entity);
            } else {
                Hooks::doAction($name, $changeSet, $entity);
            }
        }
    }

    /**
     * Dispatch the hooks of an operation that is now really in the database. The queue calls this
     * at the commit of the outermost transaction, with the operations of one record already put
     * together, so a plugin sees one event for one logical change and never one for a change that
     * was rolled back.
     * @param EntityChangeSet $changeSet What the transaction changed about this record.
     * @return void
     */
    public function dispatchCommittedHook(EntityChangeSet $changeSet): void
    {
        $suffix = match ($changeSet->getOperation()) {
            EntityChangeSet::OPERATION_CREATE => 'created',
            EntityChangeSet::OPERATION_DELETE => 'deleted',
            default => 'updated'
        };

        $this->dispatchHook($suffix, $changeSet, false);
    }

    /**
     * Dispatch the failure hooks of an operation whose transaction never reached the database. The
     * queue calls this on a rollback and at the end of a request that abandoned a transaction, so
     * that a callback which reserved something in the pre-action can release it again.
     * @param EntityChangeSet $changeSet What the operation would have changed.
     * @return void
     */
    public function dispatchFailedHook(EntityChangeSet $changeSet): void
    {
        $suffix = match ($changeSet->getOperation()) {
            EntityChangeSet::OPERATION_CREATE => 'create_failed',
            EntityChangeSet::OPERATION_DELETE => 'delete_failed',
            default => 'update_failed'
        };

        $this->dispatchHook($suffix, $changeSet, false, true);
    }

    /**
     * Whether one of the three stages of a deletion has a listener, so that the record has to be
     * described before it is removed.
     * @return bool Returns **true** if a deletion of this entity has to be reported.
     */
    protected function hasDeletionHookListener(): bool
    {
        return $this->hasHookListener('deleting') || $this->hasHookListener('deleted')
            || $this->hasHookListener('delete_failed');
    }

    /**
     * The change set of the deletion of the record this object currently holds. A deletion reports
     * every column of the own table as a change to **null**, so that hasChanged() and getOldValue()
     * work the same way as for a create and an update, and the snapshot carries the record that the
     * post-action can no longer read anywhere else.
     * @return EntityChangeSet Returns what the deletion removes.
     */
    protected function buildDeletionChangeSet(): EntityChangeSet
    {
        $deletedColumns = array();
        foreach ($this->dbColumns as $column => $value) {
            if (str_starts_with($column, $this->columnPrefix . '_')) {
                // A caller may set a column - ProfileFields::saveUserData() clears a field before
                // deciding whether to delete the now-empty record - and dbColumns already holds that
                // proposed value, not the persisted one. previousValue is what the database still
                // holds whenever the column has already been changed; only a column nobody touched
                // can be read from dbColumns directly.
                $oldValue = empty($this->columnsInfos[$column]['changed'])
                    ? $value
                    : $this->columnsInfos[$column]['previousValue'];
                $deletedColumns[$column] = array('oldValue' => $oldValue, 'newValue' => null);
            }
        }

        return $this->buildChangeSet(
            EntityChangeSet::OPERATION_DELETE,
            (string)Uuid::uuid4(),
            $deletedColumns
        );
    }

    /**
     * Queue the delete hooks of every record that the given condition selects. It is called by
     * deleteDependentRecords() right before the records are removed, so that a record which is
     * deleted together with the record it belongs to is reported like any other deletion. Without
     * it the bulk DELETE would take those records out of the hook API without a trace, exactly as
     * it would take them out of the changelog without logBulkDeletion().
     *
     * The records are read in one query and the values are put into this object one after the
     * other, so a caller must not rely on what the object holds afterwards - it is cleared.
     *
     * @param string $sqlWhereCondition Condition that selects the records, without the leading
     *                                  keyword WHERE. It may only use columns of the own table.
     * @param array $queryParams Values of the prepared parameters of the condition.
     * @param Entity|null $cause The record whose deletion removes these ones. Every change set
     *                           names it, so that a listener can tell a membership that was ended
     *                           from one that disappeared with its role.
     * @return int Returns the number of deletions that were reported.
     * @throws Exception
     */
    public function hookBulkDeletion(string $sqlWhereCondition, array $queryParams = array(), ?Entity $cause = null): int
    {
        if (!$this->hasDeletionHookListener()) {
            return 0;
        }

        $sql = 'SELECT * FROM ' . $this->tableName . '
                 WHERE ' . $sqlWhereCondition;
        $statement = $this->db->queryPrepared($sql, $queryParams);
        $reported = 0;

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $this->clear();
            $this->assignRowToColumns($row);
            $this->newRecord = false;
            $this->insertRecord = false;

            $changeSet = $this->buildDeletionChangeSet();
            if ($cause !== null) {
                list($causeId) = $cause->getHookKey();
                $changeSet = $changeSet->withCause($cause->getHookId(), $causeId);
            }
            $this->dispatchHook('deleting', $changeSet, true);
            EntityHookQueue::add($this, $changeSet, $this->db);
            $reported++;
        }

        $this->clear();

        return $reported;
    }

    /**
     * Build the change set of one operation from the change tracking that the object keeps anyway.
     * @param string $operation One of the EntityChangeSet::OPERATION_... constants.
     * @param string $operationId Identifies the operation across its stages.
     * @param array $changedColumns The changed columns as **column => array('oldValue', 'newValue')**.
     *                              For a deletion this is empty, the snapshot describes the record.
     * @return EntityChangeSet Returns what the operation changes or changed.
     */
    protected function buildChangeSet(string $operation, string $operationId, array $changedColumns): EntityChangeSet
    {
        $sensitiveColumns = $this->getSensitiveHookColumns();
        $technicalColumns = $this->getIgnoredLogColumns();
        $changes = array();

        foreach ($changedColumns as $column => $values) {
            if (in_array($column, $sensitiveColumns, true)) {
                $kind = EntityFieldChange::KIND_REDACTED;
                $oldValue = ($values['oldValue'] === null) ? null : EntityChangeSet::REDACTED_VALUE;
                $newValue = ($values['newValue'] === null) ? null : EntityChangeSet::REDACTED_VALUE;
            } else {
                $kind = in_array($column, $technicalColumns, true)
                    ? EntityFieldChange::KIND_TECHNICAL : EntityFieldChange::KIND_BUSINESS;
                $oldValue = $values['oldValue'];
                $newValue = $values['newValue'];
            }

            $changes[$column] = new EntityFieldChange(
                $column,
                $oldValue,
                $newValue,
                $this->columnsInfos[$column]['type'] ?? '',
                $kind
            );
        }

        // The snapshot is what the database holds. For an update the object already carries the
        // new values, so the changed columns are put back to the value they are changed from.
        $snapshot = array();
        if ($operation !== EntityChangeSet::OPERATION_CREATE) {
            foreach ($this->dbColumns as $column => $value) {
                if (in_array($column, $sensitiveColumns, true)) {
                    continue;
                }
                $snapshot[$column] = array_key_exists($column, $changedColumns)
                    ? $changedColumns[$column]['oldValue'] : $value;
            }
        }

        list($id, $uuid) = $this->getHookKey();

        return new EntityChangeSet(
            (string)$this->getHookId(),
            static::class,
            $this->tableName,
            $this->columnPrefix,
            $this->keyColumnName,
            $id,
            $uuid,
            $operation,
            $operationId,
            $changes,
            $snapshot
        );
    }

    /**
     * The key of the record as the hooks report it. Before an insert the database has not assigned
     * the ID yet, so it is null there and known in the matching post-action.
     * @return array Returns **array($id, $uuid)**, either of them **null** when it does not exist.
     */
    protected function getHookKey(): array
    {
        $uuidColumn = $this->columnPrefix . '_uuid';

        $id = (isset($this->dbColumns[$this->keyColumnName]) && $this->dbColumns[$this->keyColumnName] !== '')
            ? $this->dbColumns[$this->keyColumnName] : null;
        $uuid = (array_key_exists($uuidColumn, $this->dbColumns) && $this->dbColumns[$uuidColumn] !== '')
            ? (string)$this->dbColumns[$uuidColumn] : null;

        return array($id, $uuid);
    }

    /**
     * Let the filters transform a value that is about to be set. It runs before the type handling
     * and the sanitizing of setValue(), so that everything a filter returns still goes through the
     * normal checks of the column and nothing can bypass them.
     *
     * The key column, the UUID and the creator and editor columns are not filtered: they are the
     * bookkeeping of the record and not data anyone should rewrite through a hook.
     *
     * A callback must not call setValue() on the entity it is filtering for, that would recurse.
     *
     * @param string $columnName The database column that is about to be set.
     * @param mixed $newValue The proposed value.
     * @return mixed Returns the value after the filters.
     */
    protected function applyValueFilters(string $columnName, mixed $newValue): mixed
    {
        $hookId = $this->getHookId();

        if (!self::$hooksEnabled || $hookId === null) {
            return $newValue;
        }

        $unfiltered = array(
            $this->keyColumnName,
            $this->columnPrefix . '_uuid',
            $this->columnPrefix . '_usr_id_create',
            $this->columnPrefix . '_timestamp_create',
            $this->columnPrefix . '_usr_id_change',
            $this->columnPrefix . '_timestamp_change'
        );

        if (in_array($columnName, $unfiltered, true)) {
            return $newValue;
        }

        $oldValue = $this->dbColumns[$columnName] ?? null;

        foreach (array('entity_value', $hookId . '_value') as $name) {
            if (Hooks::hasFilter($name)) {
                $newValue = Hooks::applyFilters($name, $newValue, $this, $columnName, $oldValue);
            }
        }

        return $newValue;
    }
    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * Some tables contain columns _usr_id_create, timestamp_create, etc. We do not want
     * to log changes to these columns. Subclasses can also add further fields
     * (e.g., the user's table stores and auto-increments the login count, which
     * we do not want to log)
     *
     * @return array Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return [
            $this->columnPrefix . '_uuid',
            $this->columnPrefix . '_usr_id_create',
            $this->columnPrefix . '_timestamp_create',
            $this->columnPrefix . '_usr_id_change',
            $this->columnPrefix . '_timestamp_change',
            $this->columnPrefix . '_usr_id',
            $this->columnPrefix . '_timestamp'
        ];
    }

    /**
     * Adjust the changelog entry for this db record. By default, record_id, record_name are taken
     * from this record, linked and related are left empty, and the field is the column name of each change.
     *
     * What a class has to provide so that its table produces useful changelog entries:
     *  - a key column, and preferably a <prefix>_uuid column. Without the uuid the changelog can
     *    only show the record name as plain text and cannot link to the record.
     *  - a readableName() that names the record. The default falls back to the name, headline or
     *    text column and finally to the numeric id, which is of little use in a log.
     *  - getIgnoredLogColumns() for the columns that are noise, and adjustLogEntry() for the ones
     *    whose value must not be stored, see User::adjustLogEntry() for the masking of secrets.
     *  - an adjustLogEntry() override for relation and composite-key tables, where the record of
     *    the log entry is not the record itself. Membership and RolesDependencies point their
     *    entries at the user and name the role as the related object.
     * The table also has to be registered in ChangelogService, which documents the methods that
     * have to be kept in sync.
     *
     * @param LogChanges $logEntry The log entry to adjust
     *
     * @return void
     */
    protected function adjustLogEntry(LogChanges $logEntry): void
    {
    }


    /**
     * Logs creation of the DB record
     * @return true Returns **true** if no error occurred
     * @throws Exception
     */
    public function logCreation(): bool
    {
        if (!self::$loggingEnabled) return false;
        $table = str_replace(TABLE_PREFIX . '_', '', $this->tableName);
        // Check whether this table is logged at all before collecting the data for the log entry.
        // readableName() and adjustLogEntry() may read further records from the database, and
        // LogChanges::save() would discard all of that work again.
        if (!ChangelogService::isTableLogged($table)) return false;

        $record_name = $this->readableName();
        if (array_key_exists($this->columnPrefix . '_uuid', $this->dbColumns)) {
            $uuid = (string)$this->getValue($this->columnPrefix . '_uuid');
        } else {
            $uuid = null;
        }

        $logEntry = new LogChanges($this->db);
        $logEntry->setLogCreation($table, $this->dbColumns[$this->keyColumnName] ?? 0, $uuid, $record_name);
        $this->adjustLogEntry($logEntry);
        return $logEntry->save();
    }

    /**
     * Logs deletion of the DB record
     *
     * @return true Returns **true** if no error occurred
     * @throws Exception
     */
    public function logDeletion(): bool
    {
        if (!self::$loggingEnabled) return false;
        $table = str_replace(TABLE_PREFIX . '_', '', $this->tableName);
        // Check whether this table is logged at all before collecting the data for the log entry.
        // readableName() and adjustLogEntry() may read further records from the database, and
        // LogChanges::save() would discard all of that work again.
        if (!ChangelogService::isTableLogged($table)) return false;

        $record_name = $this->readableName();
        if (array_key_exists($this->columnPrefix . '_uuid', $this->dbColumns)) {
            $uuid = (string)$this->getValue($this->columnPrefix . '_uuid');
        } else {
            $uuid = null;
        }


        $logEntry = new LogChanges($this->db);
        $logEntry->setLogDeletion($table, $this->dbColumns[$this->keyColumnName] ?? 0, $uuid, $record_name);
        $this->adjustLogEntry($logEntry);
        return $logEntry->save();
    }


    /**
     * Logs all modifications of the DB record
     * @param array $logChanges Array of all changes, generated by the save method
     * @return true Returns **true** if no error occurred
     * @throws Exception
     */
    public function logModifications(array $logChanges): bool
    {
        if (!self::$loggingEnabled) return false;
        if (count($logChanges) === 0) return false;
        $table = str_replace(TABLE_PREFIX . '_', '', $this->tableName);
        if (!ChangelogService::isTableLogged($table)) return false;

        $retVal = true;
        $id = $this->dbColumns[$this->keyColumnName];
        $record_name = $this->readableName();
        if (array_key_exists($this->columnPrefix . '_uuid', $this->dbColumns)) {
            $uuid = (string)$this->getValue($this->columnPrefix . '_uuid');
        } else {
            $uuid = null;
        }


        $logEntry = new LogChanges($this->db, $table);

        // Log each individual record modification
        foreach ($logChanges as $field => $changes) {
            $fieldName = $this->getFieldTitle($field);
            $logEntry->setLogModification($table, $id, $uuid, $record_name, $field, $fieldName, $changes['oldValue'], $changes['newValue']);
            $this->adjustLogEntry($logEntry);
            $retVal = $retVal && $logEntry->save();
            $logEntry->clear();
            $logEntry->saveChangesWithoutRights();
        }
        return $retVal;
    }


    /**
     * Deletes the selected record of the table and initializes the class
     * @return true Returns **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
    {
        if (array_key_exists($this->keyColumnName, $this->dbColumns) && isset($this->dbColumns[$this->keyColumnName]) && $this->dbColumns[$this->keyColumnName] !== '') {
            // The change set has to be built while the record still exists: clear() below empties
            // the object, so the snapshot it carries is the only thing a post-delete callback can
            // read the deleted record from.
            $changeSet = null;
            if ($this->hasDeletionHookListener()) {
                $changeSet = $this->buildDeletionChangeSet();
                $this->dispatchHook('deleting', $changeSet, true);
            }

            // Log record deletion, then delete. The deletion of the dependent records that a
            // derived delete() removes beforehand is a change of its own and is not part of this
            // change set.
            $previousChangeSet = LogChanges::startChangeSet();
            $this->logDeletion();
            LogChanges::endChangeSet($previousChangeSet);

            $sql = 'DELETE FROM ' . $this->tableName . '
                     WHERE ' . $this->keyColumnName . ' = ? -- $this->dbColumns[$this->keyColumnName]';
            try {
                $this->db->queryPrepared($sql, array($this->dbColumns[$this->keyColumnName]));
            } catch (Throwable $exception) {
                if ($changeSet !== null && !$this->db->isInTransaction()) {
                    $this->dispatchFailedHook($changeSet);
                }
                throw $exception;
            }

            $this->clear();

            if ($changeSet !== null) {
                EntityHookQueue::add($this, $changeSet, $this->db);
            }

            return true;
        }

        $this->clear();
        return true;
    }

    /**
     * Delete all records of a dependent table that belong to this record. The records are removed
     * with one DELETE, but every one of them is described before that: logBulkDeletion() writes its
     * changelog entry and hookBulkDeletion() queues its delete hooks. A plain bulk DELETE would take
     * the records out of the audit trail and out of the hook API without a trace, see the class
     * documentation of ChangelogService.
     *
     * The identifying columns are named explicitly, because not every table has a single key
     * column. adm_role_dependencies for example is identified by its parent and its child.
     *
     * @param Entity $object An empty object of the dependent table. It is reused for every record.
     * @param array $identifyingColumns The columns that identify a single record of that table.
     * @param string $sqlWhereCondition Condition that selects the dependent records, without the
     *                                  leading keyword WHERE.
     * @param array $queryParams Values of the prepared parameters of the condition.
     * @return int Returns the number of deleted records.
     * @throws Exception
     */
    protected function deleteDependentRecords(Entity $object, array $identifyingColumns, string $sqlWhereCondition, array $queryParams = array()): int
    {
        $object->logBulkDeletion($identifyingColumns, $sqlWhereCondition, $queryParams);
        $object->hookBulkDeletion($sqlWhereCondition, $queryParams, $this);

        $sql = 'DELETE FROM ' . $object->tableName . '
                 WHERE ' . $sqlWhereCondition;
        $statement = $this->db->queryPrepared($sql, $queryParams);

        return $statement->rowCount();
    }

    /**
     * Write one changelog entry for every record that the given condition selects. It is called by
     * deleteDependentRecords() right before the records are deleted, so the entries still describe
     * records that exist.
     *
     * The default implementation reads one record after the other and lets logDeletion() build its
     * entry, so that an entity which customizes its log entry keeps exactly the entry it writes for
     * a single deletion. An entity whose dependent records can be many should override this method
     * and collect the same data in as few queries as possible.
     *
     * @param array $identifyingColumns The columns that identify a single record of that table.
     * @param string $sqlWhereCondition Condition that selects the records, without the leading
     *                                  keyword WHERE. It may only use columns of the own table,
     *                                  because deleteDependentRecords() reuses it for the DELETE.
     * @param array $queryParams Values of the prepared parameters of the condition.
     * @return int Returns the number of written log entries.
     * @throws Exception
     */
    public function logBulkDeletion(array $identifyingColumns, string $sqlWhereCondition, array $queryParams = array()): int
    {
        if (!self::$loggingEnabled) return 0;
        $table = str_replace(TABLE_PREFIX . '_', '', $this->tableName);
        if (!ChangelogService::isTableLogged($table)) return 0;

        $sql = 'SELECT ' . implode(', ', $identifyingColumns) . '
                  FROM ' . $this->tableName . '
                 WHERE ' . $sqlWhereCondition;
        $records = $this->db->queryPrepared($sql, $queryParams)->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($records as $record) {
            $this->readDataByColumns($record);
            $this->logDeletion();
        }

        return count($records);
    }

    /**
     * Get the first name and last name of the person who has created this record. In dependence of the preference
     * system_show_create_edit the login name will be shown. If the current user has a valid login and the
     * parameter **$linkToProfile** is set, then an HTML link to the profile is set around the name.
     * @param bool $linkToProfile If set to **true** a link to the profile is set around the name.
     * @return string Returns the first name and last name of the person optional with a link to the profile.
     * @throws Exception
     */
    public function getNameOfCreatingUser(bool $linkToProfile = true): string
    {
        global $gDb, $gProfileFields, $gL10n, $gSettingsManager, $gValidLogin;

        $nameOfCreatingUser = '';

        if ($this->getValue($this->columnPrefix . '_timestamp_create') !== '') {
            if ((int)$this->getValue($this->columnPrefix . '_usr_id_create') > 0) {
                $userCreated = new User($gDb, $gProfileFields, $this->getValue($this->columnPrefix . '_usr_id_create'));

                if ((int)$gSettingsManager->get('system_show_create_edit') === 1) {
                    $nameOfCreatingUser = $userCreated->getValue('FIRST_NAME') . ' ' . $userCreated->getValue('LAST_NAME');
                } else {
                    $nameOfCreatingUser = $userCreated->getValue('usr_login_name');
                }

                // if valid login and a user id is given than create a link to the profile of this user
                if ($linkToProfile && $gValidLogin && $nameOfCreatingUser !== $gL10n->get('SYS_SYSTEM')) {
                    $nameOfCreatingUser = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $userCreated->getValue('usr_uuid'))) .
                        '">' . $nameOfCreatingUser . '</a>';
                }
            } else {
                $nameOfCreatingUser = $gL10n->get('SYS_DELETED_USER');
            }
        }
        return $nameOfCreatingUser;
    }

    /**
     * Get the first name and last name of the person who was the last editor of this record. In dependence of the preference
     * system_show_create_edit the login name will be shown. If the current user has a valid login and the
     * parameter **$linkToProfile** is set, then an HTML link to the profile is set around the name.
     * @param bool $linkToProfile If set to **true** a link to the profile is set around the name.
     * @return string Returns the first name and last name of the person optional with a link to the profile.
     * @throws Exception
     */
    public function getNameOfLastEditingUser(bool $linkToProfile = true): string
    {
        global $gDb, $gProfileFields, $gL10n, $gSettingsManager, $gValidLogin;

        $nameOfLastEditingUser = '';

        if ($this->getValue($this->columnPrefix . '_timestamp_change') !== '') {
            if ((int)$this->getValue($this->columnPrefix . '_usr_id_change') > 0) {
                $userLastEdited = new User($gDb, $gProfileFields, $this->getValue($this->columnPrefix . '_usr_id_change'));

                if ((int)$gSettingsManager->get('system_show_create_edit') === 1) {
                    $nameOfLastEditingUser = $userLastEdited->getValue('FIRST_NAME') . ' ' . $userLastEdited->getValue('LAST_NAME');
                } else {
                    $nameOfLastEditingUser = $userLastEdited->getValue('usr_login_name');
                }

                // if valid login and a user id is given than create a link to the profile of this user
                if ($linkToProfile && $gValidLogin && $nameOfLastEditingUser !== $gL10n->get('SYS_SYSTEM')) {
                    $nameOfLastEditingUser = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $userLastEdited->getValue('usr_uuid'))) .
                        '">' . $nameOfLastEditingUser . '</a>';
                }
            } else {
                $nameOfLastEditingUser = $gL10n->get('SYS_DELETED_USER');
            }
        }
        return $nameOfLastEditingUser;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** then the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format For date or timestamp columns, the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns, the format can be **database** that would return the original database value without any transformations
     * @return mixed Returns the value of the database column.
     *               If the value was manipulated before with **setValue** then the manipulated value is returned.
     * @throws Exception
     * @see Entity#setValue
     */
    public function getValue(string $columnName, string $format = ''): mixed
    {
        global $gSettingsManager;

        $columnValue = '';

        if (array_key_exists($columnName, $this->dbColumns)) {
            // if the key field is empty, return 0
            if ($this->keyColumnName === $columnName && empty($this->dbColumns[$columnName])) {
                $columnValue = 0;
            } else {
                $columnValue = $this->dbColumns[$columnName];
            }
        }

        if (array_key_exists($columnName, $this->columnsInfos) && array_key_exists('type', $this->columnsInfos[$columnName])) {
            switch ($this->columnsInfos[$columnName]['type']) {
                // String
                case 'char': // fallthrough
                case 'varchar': // fallthrough
                case 'text':
                case 'tinytext':
                case 'mediumtext':
                case 'longtext':
                    if ($format !== 'database') {
                        // if text field and format not 'database' then convert all quotes to HTML syntax
                        $columnValue = SecurityUtils::encodeHTML((string)$columnValue);
                    } else {
                        // Postgres returns null for empty text fields, so we convert it to empty string here
                        $columnValue = $columnValue ?? '';
                    }
                    break;

                case 'blob':
                    // For blobs, we return the raw data as is
                    break;

                case 'bytea':
                    // For Postgres, we must encode the stored resource hex value back to binary
                    if (is_resource($columnValue)) {
                        ob_start();
                        fpassthru($columnValue);
                        $columnValue = hex2bin(ob_get_contents());
                        ob_end_clean();
                        $this->dbColumns[$columnName] = $columnValue;
                    }
                    break;

                case 'timestamp': // fallthrough
                case 'date': // fallthrough
                case 'time':
                    if (isset($columnValue) && $columnValue !== '') {
                        if ($format === '' && isset($gSettingsManager)) {
                            if (str_contains($this->columnsInfos[$columnName]['type'], 'timestamp')) {
                                $format = $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time');
                            } elseif (str_contains($this->columnsInfos[$columnName]['type'], 'date')) {
                                $format = $gSettingsManager->getString('system_date');
                            } else {
                                $format = $gSettingsManager->getString('system_time');
                            }
                        }

                        // try to format the date, else output the available data
                        try {
                            $datetime = new DateTime($columnValue);
                            $columnValue = $datetime->format($format);
                        } catch (Throwable) {
                            $columnValue = $this->dbColumns[$columnName];
                        }
                    } else {
                        $columnValue = '';
                    }
                    break;
            }
        }

        return $columnValue;
    }

    /**
     * If a column of the row in this object has changed throw setValue, then this method
     * will return **true** otherwise @false
     * @return bool Returns **true** if at least one value of one column has changed
     *              after the recordset was loaded otherwise **false**
     */
    public function hasColumnsValueChanged(): bool
    {
        return $this->columnsValueChanged;
    }

    /**
     * If the recordset is new and wasn't read from a database or was not stored in a database,
     * then this method will return true otherwise false
     * @return bool Returns **true** if record is not stored in a database
     */
    public function isNewRecord(): bool
    {
        return $this->newRecord;
    }

    /**
     * If a save() of this object has inserted the record into the database, then this method will
     * return true. In contrast to isNewRecord() the answer does not change when the same object is
     * saved again afterwards, so it can be used after save() to tell a created record from a changed
     * one, e.g. to choose between a "created" and a "changed" notification.
     * @return bool Returns **true** if this object has created the record it represents
     */
    public function wasInserted(): bool
    {
        return $this->insertedRecord;
    }

    /**
     * Reads a record out of the table in the database selected by the conditions of the param **$sqlWhereCondition** out of the table.
     * If the SQL finds more than one record, the method returns **false**.
     * Per default, all columns of the default table will be read and stored in the object.
     * @param string $sqlWhereCondition Conditions for the table to select one record
     * @param array<int,mixed> $queryParams The query params for the prepared statement
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readDataById
     * @see Entity#readDataByUuid
     */
    protected function readData(string $sqlWhereCondition, array $queryParams = array()): bool
    {
        $sqlAdditionalTables = '';

        // create SQL to connect additional tables to the select statement
        if (count($this->additionalTables) > 0) {
            foreach ($this->additionalTables as $arrAdditionalTable) {
                $sqlAdditionalTables .= ', ' . $arrAdditionalTable['table'];
                $sqlWhereCondition .= ' AND ' . $arrAdditionalTable['columnNameAdditionalTable'] . ' = ' . $arrAdditionalTable['columnNameClassTable'] . ' ';
            }
        }

        // if the condition starts with AND then remove this
        if (StringUtils::strStartsWith(ltrim($sqlWhereCondition), 'AND', false)) {
            $sqlWhereCondition = substr($sqlWhereCondition, 4);
        }

        if ($sqlWhereCondition !== '') {
            $sql = 'SELECT *
                      FROM ' . $this->tableName . '
                           ' . $sqlAdditionalTables . '
                     WHERE ' . $sqlWhereCondition;
            $readDataStatement = $this->db->queryPrepared($sql, $queryParams); // TODO add more params

            if ($readDataStatement->rowCount() === 1) {
                $row = $readDataStatement->fetch();
                $this->newRecord = false;
                $this->insertRecord = false;
                $this->insertedRecord = false;

                // move data to class column value array
                $this->assignRowToColumns($row);

                return true;
            }

            $this->clear();
        }

        return false;
    }

    /**
     * Move the columns of a database row into the value array of this object and convert them to
     * the PHP type of their column, so that a boolean column is a bool and an id an int.
     * @param array $row One row of the table, as the database returned it.
     * @return void
     */
    protected function assignRowToColumns(array $row): void
    {
        foreach ($row as $key => $value) {
            if ($this->columnsInfos[$key]['type'] === 'boolean'
                || $this->columnsInfos[$key]['type'] === 'tinyint') {
                $this->dbColumns[$key] = (bool)$value;
            } elseif (($this->columnsInfos[$key]['type'] === 'integer'
                    || $this->columnsInfos[$key]['type'] === 'smallint')
                && $value != '') {
                // only convert to int if it's not a null value
                $this->dbColumns[$key] = (int)$value;
            } else {
                $this->dbColumns[$key] = $value;
            }
        }
    }

    /**
     * Reads a record out of the table in the database selected by the unique id column in the table.
     * Per default, all columns of the default table will be read and stored in the object.
     * @param int $id Unique id of id column of the table.
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readData
     * @see Entity#readDataByUuid
     */
    public function readDataById(int $id): bool
    {
        // initialize the object, so that all fields are empty
        $this->clear();

        // add id to SQL condition
        if ($id > 0) {
            // call method to read data out of a database
            return $this->readData(' AND ' . $this->keyColumnName . ' = ? ', array($id));
        }

        return false;
    }

    /**
     * Reads a record out of the table in the database selected by the unique uuid column in the table.
     * The name of the column must have the syntax table_prefix, underscore and uuid. E.g., usr_uuid.
     * Per default, all columns of the default table will be read and stored in the object.
     * Not every Admidio table has a UUID. Please check the database structure before you use this method.
     * @param string $uuid Unique uuid that should be searched.
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readData
     * @see Entity#readDataById
     */
    public function readDataByUuid(string $uuid): bool
    {
        // initialize the object, so that all fields are empty
        $this->clear();

        // add id to SQL condition
        if ($uuid !== '') {
            // call method to read data out of a database
            return $this->readData(' AND ' . $this->columnPrefix . '_uuid = ? ', array($uuid));
        }

        return false;
    }

    /**
     * Reads a record out of the table in database selected by different columns in the table.
     * The columns are committed with an array where every element index is the column name and the value is the column value.
     * If you want a column to be null, then set the value to **NULL**
     * The columns and values must be selected so that they identify only one record.
     * If the SQL finds more than one record, the method returns **false**.
     * Per default, all columns of the default table will be read and stored in the object.
     * @param array<string,mixed> $columnArray An array where every element index is the column name and the value is the column value
     * @return bool Returns **true** if one record is found
     *
     * **Code example**
     * ```
     * // reads data not be mem_id but with combination of role and user id
     * $member = new Entity($gDb, TBL_MEMBERS, 'rol');
     * $member->readDataByColumn(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
     * ```
     * @throws Exception
     * @see Entity#readDataByUuid
     * @see Entity#readData
     * @see Entity#readDataById
     */
    public function readDataByColumns(array $columnArray): bool
    {
        // initialize the object, so that all fields are empty
        $this->clear();

        if (count($columnArray) === 0) {
            return false;
        }

        $sqlWhereCondition = '';
        $sqlParams = array();

        // add every array element as an SQL condition to the condition string
        foreach ($columnArray as $columnName => $columnValue) {
            if ($columnValue === 'NULL') {
                $sqlWhereCondition .= ' AND ' . $columnName . ' IS NULL ';
            } else {
                $sqlWhereCondition .= ' AND ' . $columnName . ' = ? ';
                $sqlParams[] = $columnValue;
            }
        }

        // call method to read data out of a database
        $returnCode = $this->readData($sqlWhereCondition, array_values($sqlParams));

        // if no record was found, then save the array fields in the object
        if (!$returnCode) {
            foreach ($columnArray as $columnName => $columnValue) {
                if (str_starts_with($columnName, $this->columnPrefix . '_')) {
                    $this->setValue($columnName, $columnValue);
                }
            }
        }

        return $returnCode;
    }

    /**
     * Save all changed columns of the recordset in table of a database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor, then these columns with their timestamp will be updated.
     * For a new record if there is a UUID column, a new uuid will be created and stored.
     *
     * The changelog entries of the change are written after the record itself was written, and
     * they are not part of a transaction of their own. A failing log write does not undo the
     * change and does not turn the return value into false: an error of the database ends the
     * request anyway, and the changelog must not be able to block an ordinary save. Callers that
     * need the record and its log entries to be written together have to open a transaction
     * around the whole operation themselves, e.g.
     * ```
     * $gDb->startTransaction();
     * $entity->save();
     * $gDb->endTransaction();
     * ```
     * Transactions are counted, so this is also safe when the caller is already within one.
     *
     * An entity that has a hook ID dispatches its persistence hooks here: **entity_creating** and
     * **&lt;entity&gt;_creating** before the statement, **&lt;entity&gt;_created** and
     * **entity_created** after it, and the matching failure hooks when the statement fails. The
     * pre-hooks run before anything of the object is modified, so a callback that throws to reject
     * the operation leaves an object the caller can still save.
     *
     * The post-hooks do not fire here. EntityHookQueue holds them until the outermost transaction
     * commits, and it reduces the saves of one record within that transaction to the one change
     * that happened as far as anybody outside can tell. Without an open transaction the statement
     * is the commit and they fire immediately.
     *
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset
     *                                if a table has columns like **usr_id_create** or **usr_id_change**
     * @return bool If an update or insert into the database was done, then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        if (!$this->columnsValueChanged && isset($this->dbColumns[$this->keyColumnName]) && $this->dbColumns[$this->keyColumnName] !== '') {
            return false;
        }

        // if a new role then set create the uuid
        if ($this->isNewRecord()
            && array_key_exists($this->columnPrefix . '_uuid', $this->dbColumns)
            && (string)$this->getValue($this->columnPrefix . '_uuid') === '') {
            $this->setValue($this->columnPrefix . '_uuid', (string)Uuid::uuid4());
        }

        // TODO check if "$gCurrentUser instanceof User"
        // see "start_installation.php"
        if ($updateFingerPrint && isset($GLOBALS['gCurrentUserId']) && $GLOBALS['gCurrentUserId'] > 0) {
            // if the table has fields to store the creator and the last change,
            // then fill them here automatically
            if ($this->newRecord && $this->insertRecord && array_key_exists($this->columnPrefix . '_usr_id_create', $this->dbColumns)) {
                $this->setValue($this->columnPrefix . '_timestamp_create', DATETIME_NOW);
                $this->setValue($this->columnPrefix . '_usr_id_create', $GLOBALS['gCurrentUserId']);
            } elseif (array_key_exists($this->columnPrefix . '_usr_id_change', $this->dbColumns)) {
                // Do not update data if the same user has done so within 15 minutes
                if ($GLOBALS['gCurrentUserId'] !== $this->getValue($this->columnPrefix . '_usr_id_create')
                    || time() > (strtotime($this->getValue($this->columnPrefix . '_timestamp_create')) + 900)) {
                    $this->setValue($this->columnPrefix . '_timestamp_change', DATETIME_NOW);
                    $this->setValue($this->columnPrefix . '_usr_id_change', $GLOBALS['gCurrentUserId']);
                }
            }
        }

        $sqlFieldArray = array();
        $sqlSetArray = array();
        $queryParams = array();
        $returnCode = false;

        $logChanges = array();

        // The hooks describe the same operation as the changelog, but they also report the
        // bookkeeping columns and they need the value of the object and not the one that the
        // PostgreSQL boolean conversion below produces. Nothing of this is collected while no
        // callback is registered for any stage of this operation.
        $operation = $this->insertRecord ? EntityChangeSet::OPERATION_CREATE : EntityChangeSet::OPERATION_UPDATE;
        $hookStages = ($operation === EntityChangeSet::OPERATION_CREATE)
            ? array('creating', 'created', 'create_failed')
            : array('updating', 'updated', 'update_failed');
        $collectHookChanges = false;
        foreach ($hookStages as $hookStage) {
            if ($this->hasHookListener($hookStage)) {
                $collectHookChanges = true;
                break;
            }
        }
        // Collect them before the loop below, because that loop clears the changed flags as it
        // builds the statement. A pre-action that vetoes the operation by throwing has to leave
        // the object exactly as it found it, so that the caller can handle the rejection and save
        // again.
        $hookChanges = array();
        if ($collectHookChanges) {
            foreach ($this->dbColumns as $key => $value) {
                if (str_starts_with($key, $this->columnPrefix . '_')
                    && !$this->columnsInfos[$key]['serial'] && $this->columnsInfos[$key]['changed']) {
                    $hookChanges[$key] = array(
                        'oldValue' => ($operation === EntityChangeSet::OPERATION_CREATE)
                            ? null : $this->columnsInfos[$key]['previousValue'],
                        'newValue' => $value
                    );
                }
            }
        }

        $changeSet = null;
        if ($collectHookChanges
            && ($operation === EntityChangeSet::OPERATION_CREATE || count($hookChanges) > 0)) {
            $changeSet = $this->buildChangeSet($operation, (string)Uuid::uuid4(), $hookChanges);
            $this->dispatchHook($hookStages[0], $changeSet, true);
        }

        // An insert must also contain the columns for which the database requires a value but which the
        // caller has not set. It is read before the loop below, because that loop resets the changed flags.
        $requiredColumns = $this->insertRecord ? $this->getRequiredColumnsWithoutValue() : array();

        // Loop over all DB fields and add them to the update
        foreach ($this->dbColumns as $key => $value) {
            // fields of other tables must not appear in insert/update
            if (str_starts_with($key, $this->columnPrefix . '_')) {
                if ($this->columnsInfos[$key]['type'] === 'boolean' && DB_TYPE === Database::PDO_ENGINE_PGSQL) {
                    if ($value || $value === '1') {
                        $value = 'true';
                    } else {
                        $value = 'false';
                    }
                }

                // Auto-increment fields must not appear in insert/update
                if (!$this->columnsInfos[$key]['serial'] && $this->columnsInfos[$key]['changed']) {
                    if ($this->insertRecord) {
                        // Prepare data for an insert
                        $sqlFieldArray[] = $key;
                    } else {
                        // Prepare data for an update
                        $sqlSetArray[] = $key . ' = ?';

                    }
                    $queryParams[] = $value;
                    // Ignore the usr_id_create and timestamp_create (and *_change) columns in the change log...
                    if (!in_array($key, $this->getIgnoredLogColumns())) {
                        $logChanges[$key] = array('oldValue' => $this->columnsInfos[$key]['previousValue'], 'newValue' => $value);
                    }

                    $this->columnsInfos[$key]['changed'] = false;
                }
            }
        }

        // These columns carry no information, so they are deliberately not part of the changelog.
        foreach ($requiredColumns as $key => $value) {
            $sqlFieldArray[] = $key;
            $queryParams[] = $value;
        }

        // A caller may set columnsValueChanged for a change that belongs to a connected object and
        // not to a column of this table, User::save() for its profile fields for example. If no
        // column of this table changed, there is nothing to update, and an UPDATE without a SET
        // clause would be a syntax error.
        if (!$this->insertRecord && count($sqlSetArray) === 0) {
            $this->columnsValueChanged = false;
            return false;
        }

        // Every log entry that is written by this save belongs to the same change: the creation
        // entry and the entries of the initial values of a new record, or all fields that this
        // save has modified. LogChanges stamps them with one UUID, so that they can be shown
        // together in the change history.
        $previousChangeSet = LogChanges::startChangeSet();

        try {
            if ($this->insertRecord) {
                // insert record and remember the new id
                $sql = 'INSERT INTO ' . $this->tableName . '
                               (' . implode(',', $sqlFieldArray) . ')
                        VALUES (' . Database::getQmForValues($sqlFieldArray) . ')';
                if ($this->db->queryPrepared($sql, $queryParams) !== false) {
                    $returnCode = true;
                    if ($this->keyColumnName !== '') {
                        $this->dbColumns[$this->keyColumnName] = $this->db->lastInsertId();
                    }
                    // The result of the log writes is deliberately not evaluated, see the comment
                    // about transactions in the description of this method.
                    $this->logCreation();
                    $this->logModifications($logChanges);
                    // the flags are set after the log was written, because the log methods of some
                    // classes decide by the new-record state what they have to write
                    $this->newRecord = false;
                    $this->insertRecord = false;
                    $this->insertedRecord = true;
                }
            } else {
                $sql = 'UPDATE ' . $this->tableName . '
                           SET ' . implode(', ', $sqlSetArray) . '
                         WHERE ' . $this->keyColumnName . ' = ? -- $this->dbColumns[$this->keyColumnName]';
                $queryParams[] = $this->dbColumns[$this->keyColumnName];
                if ($this->db->queryPrepared($sql, $queryParams) !== false) {
                    $returnCode = true;
                    // Log record modification
                    $this->logModifications($logChanges);
                }
            }
        } catch (Throwable $exception) {
            // cleanup for whoever prepared something in the pre-action, and the original failure
            // stays the one that Admidio reports. Inside a transaction the database runs the
            // failure hooks of everything the transaction did, this one included.
            if ($changeSet !== null && !$this->db->isInTransaction()) {
                $this->dispatchFailedHook($changeSet);
            }
            throw $exception;
        }

        LogChanges::endChangeSet($previousChangeSet);

        $this->columnsValueChanged = false;

        if ($changeSet !== null && $returnCode) {
            // an insert only learns its ID from the database, so the post-action gets the key that
            // the pre-action could not know yet
            list($id, $uuid) = $this->getHookKey();
            EntityHookQueue::add($this, $changeSet->withKey($id, $uuid), $this->db);
        }

        return $returnCode;
    }

    /**
     * Collect the columns of the table for which the database requires a value but which the caller has
     * not set: columns that are NOT NULL and have no default. save() writes only the changed columns, so
     * such a column is missing from the INSERT. MySQL accepts that only because
     * Database::setConnectionOptions() switches the connection to SQL_MODE 'ANSI', which replaces the
     * server default and therefore drops STRICT_TRANS_TABLES. PostgreSQL rejects the row, so without this
     * the same entity code stores a record on one engine and nothing on the other.
     *
     * The value that is written is the empty value of the column type. Date and time columns are
     * deliberately left out: there is no empty date, and a fabricated one would hide the missing value
     * instead of letting the database report it.
     *
     * @return array<string,mixed> Column name and value of every column that must be added to an INSERT
     */
    private function getRequiredColumnsWithoutValue(): array
    {
        $requiredColumns = array();

        foreach ($this->dbColumns as $key => $value) {
            // only columns of the table of this class and only those the caller has not set
            if (!str_starts_with($key, $this->columnPrefix . '_')
                || $this->columnsInfos[$key]['serial']
                || $this->columnsInfos[$key]['changed']) {
                continue;
            }

            // the column accepts NULL, or the database fills it itself
            if ($this->columnsInfos[$key]['null']
                || array_key_exists('default', $this->columnsInfos[$key])) {
                continue;
            }

            switch ($this->columnsInfos[$key]['type']) {
                case 'boolean': // fallthrough
                case 'tinyint':
                    $requiredColumns[$key] = (DB_TYPE === Database::PDO_ENGINE_PGSQL) ? 'false' : 0;
                    break;

                case 'integer': // fallthrough
                case 'smallint': // fallthrough
                case 'bigint': // fallthrough
                case 'decimal': // fallthrough
                case 'numeric': // fallthrough
                case 'double': // fallthrough
                case 'float':
                    $requiredColumns[$key] = 0;
                    break;

                case 'date': // fallthrough
                case 'time': // fallthrough
                case 'datetime': // fallthrough
                case 'timestamp':
                    break;

                default:
                    $requiredColumns[$key] = '';
            }
        }

        return $requiredColumns;
    }

    /**
     * If this method is set, then the current user can save changes to this object if he hasn't the necessary rights.
     * The flag must be used within the class implementation.
     * @return void
     */
    public function saveChangesWithoutRights(): void
    {
        $this->saveChangesWithoutRights = true;
    }

    /**
     * The method requires an array with all fields of one recordset from the table object.
     * These fields will be added to the object as if you read one record with **readDataById**
     * but without a separate SQL. This method is useful if you have several recordset of the
     * table and want to use a table object for each recordset. So you don't have to do a
     * separate SQL read for each record. This is a performant way to fill the object with
     * the necessary data.
     * @param array $fieldArray An array with all fields and their values of the table. If the object has
     *                          more connected tables than you should add the fields of these tables, too.
     *
     * **Code example**
     * ```
     * // read all announcements with their categories
     * $sql = 'SELECT * FROM ' . TBL_ANNOUNCEMENTS . ' INNER JOIN ' . TBL_CATEGORIES . ' ON ann_cat_id = cat_id';
     * $announcementsStatement = $gDb->queryPrepared($sql);
     * $announcement = new Announcements($gDb);
     *
     * While ($row = $announcementsStatement->fetch())
     * {
     *     // add each recordset to an object without a separate sql within the object
     *     $announcement->clear();
     *     $announcement->setArray($row);
     *     ...
     * }
     * ```
     * @throws Exception
     */
    public function setArray(array $fieldArray): void
    {
        foreach ($fieldArray as $field => $value) {
            if (!empty($this->dbColumns[$field])) {
                $this->columnsInfos[$field]['previousValue'] = $this->dbColumns[$field];
            }
            $this->dbColumns[$field] = $value;
            $this->columnsInfos[$field]['changed'] = false;
        }

        if (empty($this->dbColumns[$this->keyColumnName])) {
            $this->setNewRecord();
        } else {
            $this->newRecord = false;
            $this->insertRecord = false;
            $this->insertedRecord = false;
        }
    }

    /**
     * Read all columns with their information like **type** (integer, varchar, boolean),
     * **null** (or not), **key** and **serial**. Also, the changed flag will be set to false.
     * @throws Exception
     */
    protected function setColumnsInfos(): void
    {
        // create an array with base table and all connected tables
        $tables = array($this->tableName);

        foreach ($this->additionalTables as $values) {
            $tables[] = $values['table'];
        }

        foreach ($tables as $table) {
            $tableColumnsProperties = $this->db->getTableColumnsProperties($table);

            foreach ($tableColumnsProperties as $columnName => $property) {
                // some actions should only be done for columns of the main table from this class
                if (str_starts_with($columnName, $this->columnPrefix . '_')) {
                    $this->dbColumns[$columnName] = null;

                    if ($property['serial']) {
                        $this->keyColumnName = $columnName;
                    }
                }
                $this->columnsInfos[$columnName]['changed'] = false;
                $this->columnsInfos[$columnName]['previousValue'] = null;
                if (strpos($property['type'], '(') > 0) {
                    $this->columnsInfos[$columnName]['type'] = substr($property['type'], 0, strpos($property['type'], '('));
                } else {
                    $this->columnsInfos[$columnName]['type'] = $property['type'];
                }
                $this->columnsInfos[$columnName]['null'] = $property['null'];
                $this->columnsInfos[$columnName]['key'] = $property['key'];
                $this->columnsInfos[$columnName]['serial'] = $property['serial'];
                if (isset($property['default'])) {
                    $this->columnsInfos[$columnName]['default'] = $property['default'];
                } elseif ($property['null']) {
                    $this->columnsInfos[$columnName]['default'] = null;
                }
            }
        }
    }

    /**
     * Use this method if you have read a record from the database and want to use this data to create a new record.
     * Method set the flag that it's a new record and initialize the ID and set a new UUID if that column exists.
     * @return void
     * @throws Exception
     */
    public function setNewRecord(): void
    {
        $this->newRecord = true;
        $this->insertRecord = true;
        $this->insertedRecord = false;

        if (array_key_exists($this->columnPrefix . '_id', $this->dbColumns)) {
            $this->setValue($this->columnPrefix . '_id', 0);
        }
        if (array_key_exists($this->columnPrefix . '_uuid', $this->dbColumns)) {
            $this->setValue($this->columnPrefix . '_uuid', (string)Uuid::uuid4());
        }
    }

    /**
     * Set a new value for a column of the database table. The value is only saved in the object.
     * You must call the method **save** to store the new value to the database. If the unique key
     * column is set to 0, then this record will be a new record and all other columns are marked as changed.
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed $newValue The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** then the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @throws Exception If **columnName** doesn't exist. Exception->text contains a string with the reason why the login failed.
     * @see Entity#getValue
     */
    public function setValue(string $columnName, mixed $newValue, bool $checkValue = true): bool
    {
        if (!array_key_exists($columnName, $this->dbColumns)) {
            throw new Exception('Column ' . $columnName . ' does not exists in table ' . $this->tableName . '!');
        }

        $type     = strtolower($this->columnsInfos[$columnName]['type']);
        $nullable = $this->columnsInfos[$columnName]['null'] ?? false;
        $isKey    = $this->columnsInfos[$columnName]['key'] ?? false;

        // normalize string values
        $newValue = is_string($newValue) ? trim($newValue) : $newValue;

        // a filter may transform or reject the proposed value, the checks below then run on its result
        $newValue = $this->applyValueFilters($columnName, $newValue);

        if ($checkValue) {
            if (!isset($newValue) || $newValue === '') {
                if ($nullable) {
                    $newValue = null;
                } else {
                    // fallback to safe defaults when NULL not allowed
                    if (preg_match('/int|tinyint|smallint|mediumint|bigint/', $type)) {
                        if (isset($this->columnsInfos[$columnName]['default'])) {
                            $newValue = $this->columnsInfos[$columnName]['default'];
                        } else {
                            $newValue = 0;
                        }
                        // Key fields should not contain 0
                        if ($isKey && (int)$newValue === 0) {
                            $newValue = '';
                        }
                    } elseif (preg_match('/decimal|numeric|float|double|real/', $type)) {
                        if (isset($this->columnsInfos[$columnName]['default'])) {
                            $newValue = $this->columnsInfos[$columnName]['default'];
                        } else {
                            $newValue = 0.0;
                        }
                    } elseif (preg_match('/date|time|year/', $type)) {
                        if (isset($this->columnsInfos[$columnName]['default'])) {
                            $newValue = $this->columnsInfos[$columnName]['default'];
                        } else {
                            $newValue = '1970-01-01';
                        }
                    } elseif (preg_match('/datetime|timestamp/', $type)) {
                        if (isset($this->columnsInfos[$columnName]['default'])) {
                            $newValue = $this->columnsInfos[$columnName]['default'];
                        } else {
                            $newValue = '1970-01-01 00:00:00';
                        }
                    } elseif (preg_match('/bool|tinyint\(1\)/', $type)) {
                        if (isset($this->columnsInfos[$columnName]['default'])) {
                            $newValue = $this->columnsInfos[$columnName]['default'];
                        } else {
                            $newValue = false;
                        }
                    } else {
                        if (isset($this->columnsInfos[$columnName]['default'])) {
                            $newValue = $this->columnsInfos[$columnName]['default'];
                        } else {
                            $newValue = '';
                        }
                    }
                }
            } else {
                // Convert and sanitize based on type
                switch ($type) {
                    // Numeric
                    case 'integer':
                    case 'smallint':
                    case 'bigint':
                    case 'tinyint':
                        if (!is_numeric($newValue)) {
                            $newValue = $nullable ? null : 0;
                        } else {
                            $newValue = (int)$newValue;
                        }

                        // Key fields should not contain 0
                        if ($isKey && (int)$newValue === 0) {
                            $newValue = $nullable ? null : '';
                        }
                        break;

                    // Decimal / Float
                    case 'decimal':
                    case 'numeric':
                    case 'float':
                    case 'double':
                    case 'real':
                        $newValue = (float)str_replace(',', '.', $newValue);
                        break;

                    // Boolean
                    case 'boolean':
                        $boolVal = filter_var($newValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        $newValue = $boolVal ?? ($nullable ? null : false);
                        break;

                    // Date
                    case 'date':
                        $date = date_create($newValue);
                        $newValue = $date ? $date->format('Y-m-d') : ($nullable ? null : '1970-01-01');
                        break;

                    // Datetime
                    case 'datetime':
                    case 'timestamp':
                        $dateTime = date_create($newValue);
                        $newValue = $dateTime ? $dateTime->format('Y-m-d H:i:s') : ($nullable ? null : '1970-01-01 00:00:00');
                        break;

                    // Strings
                    case 'char':
                    case 'varchar':
                    case 'text':
                    case 'tinytext':
                    case 'mediumtext':
                    case 'longtext':
                        // no HTML tags and no HTML entities should be stored in the database
                        $newValue = StringUtils::strStripTags(html_entity_decode($newValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                        break;

                    // Byte/Blob
                    case 'blob':
                        // For blobs, we accept the raw data as is
                        break;

                    case 'bytea':
                        // Postgres can only store hex values in bytea, so we must decode binary in hex
                        $newValue = bin2hex($newValue);
                        break;

                    default:
                        // fallback sanitize
                        $newValue = is_scalar($newValue) ? htmlspecialchars((string)$newValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $newValue;
                }
            }
        }

        // if the key field was set to 0, then a new record is to be created
        if ($this->keyColumnName === $columnName && (int)$newValue === 0) {
            $this->newRecord = true;
            $this->insertRecord = true;

            // now mark all other columns with values of this object as changed
            foreach ($this->dbColumns as $column => $value) {
                if ((is_array($value) && count($value) > 0) || isset($value)) {
                    $this->columnsInfos[$column]['changed'] = true;
                }
            }
        }

        if ($this->valueChanged($columnName, $newValue)) {
            if ($this->columnsInfos[$columnName]['changed'] && !$this->insertRecord
                && !$this->valuesDiffer($columnName, $this->columnsInfos[$columnName]['previousValue'], $newValue)) {
                // The field is back at the value that the database holds. It is not a change any
                // more, so it must neither be written nor appear in the change history.
                $this->dbColumns[$columnName] = $newValue;
                $this->columnsInfos[$columnName]['changed'] = false;
                $this->columnsInfos[$columnName]['previousValue'] = null;
                $this->columnsValueChanged = $this->hasChangedColumns();
            } else {
                if (!$this->columnsInfos[$columnName]['changed']) {
                    // Remember the value that the database holds. A field that is set more than once
                    // before the save must still report the change from its persisted value and not
                    // from the intermediate one.
                    $this->columnsInfos[$columnName]['previousValue'] = $this->dbColumns[$columnName];
                }
                $this->dbColumns[$columnName] = $newValue;
                $this->columnsValueChanged = true;
                $this->columnsInfos[$columnName]['changed'] = true;
            }
        }

        return true;
    }

    /**
     * Check whether at least one column of the object still differs from the value that the
     * database holds.
     * @return bool Returns **true** if at least one column is marked as changed.
     */
    protected function hasChangedColumns(): bool
    {
        foreach ($this->columnsInfos as $columnInfo) {
            if (!empty($columnInfo['changed'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the given column has changed, considering the DB column type.
     * Since loading from the database converts to the actual data types, but setting
     * uses strings, some datatypes need special-casing. Boolean false are read as null,
     * but set as 0 -> null and 0 must be considered as false. Similarly, loading a
     * (date)time will include seconds, but the setting will not include seconds in the string
     * value.
     *
     * @param string $columnName the database column name to check
     * @param string|null $newValue the new value to set
     * @return bool Whether the $newValue can be considered different from the current value
     */
    protected function valueChanged(string $columnName, ?string $newValue): bool
    {
        return $this->valuesDiffer($columnName, $this->dbColumns[$columnName] ?? null, $newValue);
    }

    /**
     * Check whether a new value differs from a given old value, considering the DB column type.
     * valueChanged() compares against the value that the object currently holds; a change of the
     * record has to be reported against the value that the database holds, which is the comparison
     * this method also allows.
     *
     * @param string $columnName the database column name to check
     * @param mixed $oldValue the value to compare against
     * @param string|null $newValue the new value to set
     * @return bool Whether the $newValue can be considered different from $oldValue
     */
    protected function valuesDiffer(string $columnName, mixed $oldValue, ?string $newValue): bool
    {
        $oldValue = !empty($oldValue) ? $oldValue : null;

        // certain data types need special handling to detect changes
        //   * bool: unset/null and 0 mean false
        //   * date/time: Make sure seconds are handled consistently, no need to convert to string
        //   * all other types can be compared by converting to string and comparing strings
        switch ($this->columnsInfos[$columnName]['type']) {
            case 'boolean': // fallthrough
            case 'tinyint':
                if (empty($newValue)) $newValue = 0;
                return $oldValue != $newValue;
            case 'timestamp': // fallthrough
            case 'date': // fallthrough
            case 'time':
                // if both are empty, no need to go through DateTime
                if (empty($oldValue) && empty($newValue)) {
                    return false;
                } elseif (empty($oldValue) || empty($newValue)) {
                    return true;
                }
                try {
                    // Convert old and new to a DateTime and compare that directly
                    $oldDate = new DateTime($oldValue);
                    $newDate = new DateTime($newValue);
                    return $oldDate != $newDate;
                } catch (\Exception) {
                    // if DateTime-conversion did not work, compare the strings
                    return $oldValue != $newValue;
                }
            default:
                // only mark as "changed" if the value is different (DON'T use binary safe function!)
                if (!isset($oldValue) && !isset($newValue)) {
                    return false;
                } elseif (!isset($oldValue) && isset($newValue)) {
                    return true;
                } else {
                    return strcmp((string)$oldValue, (string)$newValue) !== 0;
                }
        }
    }

}
