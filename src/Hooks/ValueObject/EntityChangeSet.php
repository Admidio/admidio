<?php
namespace Admidio\Hooks\ValueObject;

/**
 * What one create, update or delete of an Entity did, as it is handed to a persistence hook.
 *
 * Admidio tracks the changed columns and the value the database holds for them anyway, so a hook
 * callback does not have to read the record before the save to learn what changed. The change set
 * is immutable: it describes the operation, and nothing a callback does to it can influence that
 * operation. To transform a value use the **entity_value** filter, to reject an operation throw
 * from the pre-action.
 *
 * The same shape is handed to the generic **entity_...** hooks and to the entity-specific
 * hooks, so a callback can move from one to the other without being rewritten.
 *
 * For a creation the old values are **null** and the snapshot is empty, for a deletion the new
 * values are **null** and the snapshot is what the record held. That snapshot is the reason this
 * class exists at all: Entity::delete() clears the object, so a callback that runs afterwards can
 * only learn what was deleted from here.
 *
 * **Code example**
 * ```
 * Hooks::addAction('event_updated', function (EntityChangeSet $changeSet) {
 *     if ($changeSet->hasChanged('dat_begin')) {
 *         myCalendarSync($changeSet->getUuid(), $changeSet->getNewValue('dat_begin'));
 *     }
 * });
 * ```
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class EntityChangeSet
{
    public const OPERATION_CREATE = 'create';
    public const OPERATION_UPDATE = 'update';
    public const OPERATION_DELETE = 'delete';

    /**
     * The value that a redacted column reports instead of its own.
     */
    public const REDACTED_VALUE = '********';

    /**
     * @param string $hookId The stable public identifier of the entity, e.g. **oidc_client**.
     * @param string $entityClass The PHP class of the entity. It may be renamed, so recognize the
     *                            entity by its hook ID and not by this.
     * @param string $tableName The database table, with the table prefix of the installation.
     * @param string $columnPrefix The column prefix of that table, e.g. **usr**.
     * @param string $keyColumnName The name of the key column, e.g. **usr_id**.
     * @param int|string|null $id The value of the key column, **null** while a creation is still
     *                            being written.
     * @param string|null $uuid The UUID of the record if its table has one.
     * @param string $operation One of the OPERATION_... constants.
     * @param string $operationId Identifies this operation, so that a callback of a pre-action can
     *                            recognize the matching post-action or failure action.
     * @param array<string,EntityFieldChange> $changes The changed columns, keyed by column name.
     * @param array<string,mixed> $snapshot The values the database held before the operation,
     *                                      empty for a creation, redacted columns withheld.
     * @param string|null $causeHookId The hook ID of the record whose deletion removed this one,
     *                                 **null** when the operation was asked for on its own.
     * @param int|string|null $causeId The key of that record.
     */
    public function __construct(
        private readonly string $hookId,
        private readonly string $entityClass,
        private readonly string $tableName,
        private readonly string $columnPrefix,
        private readonly string $keyColumnName,
        private readonly int|string|null $id,
        private readonly ?string $uuid,
        private readonly string $operation,
        private readonly string $operationId,
        private readonly array $changes,
        private readonly array $snapshot,
        private readonly ?string $causeHookId = null,
        private readonly int|string|null $causeId = null
    ) {
    }

    /**
     * The stable public identifier of the entity, the same string the hook name is built from.
     * @return string Returns the hook ID, e.g. **oidc_client**.
     */
    public function getHookId(): string
    {
        return $this->hookId;
    }

    /**
     * @return string Returns the PHP class of the entity.
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @return string Returns the database table, with the table prefix of the installation.
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return string Returns the column prefix of the table, e.g. **usr**.
     */
    public function getColumnPrefix(): string
    {
        return $this->columnPrefix;
    }

    /**
     * @return string Returns the name of the key column, e.g. **usr_id**.
     */
    public function getKeyColumnName(): string
    {
        return $this->keyColumnName;
    }

    /**
     * The value of the key column. A pre-create action does not have it yet, because the database
     * assigns it; the matching post-create action does.
     * @return int|string|null Returns the ID of the record or **null**.
     */
    public function getId(): int|string|null
    {
        return $this->id;
    }

    /**
     * @return string|null Returns the UUID of the record, or **null** if the table has none.
     */
    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    /**
     * @return string Returns one of the OPERATION_... constants.
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * @return bool Returns **true** for a creation.
     */
    public function isCreate(): bool
    {
        return $this->operation === self::OPERATION_CREATE;
    }

    /**
     * @return bool Returns **true** for an update.
     */
    public function isUpdate(): bool
    {
        return $this->operation === self::OPERATION_UPDATE;
    }

    /**
     * @return bool Returns **true** for a deletion.
     */
    public function isDelete(): bool
    {
        return $this->operation === self::OPERATION_DELETE;
    }

    /**
     * Identifies this operation. A callback that reserved something in the pre-action recognizes
     * the matching post-action or failure action by this value.
     * @return string Returns the ID of the operation.
     */
    public function getOperationId(): string
    {
        return $this->operationId;
    }

    /**
     * All changed columns, the bookkeeping of the record included.
     * @return array<string,EntityFieldChange> Returns the changes, keyed by column name.
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * The changed columns that are ordinary data of the record. A consumer that reacts to real
     * changes wants these and not the creator, editor or counter columns.
     * @return array<string,EntityFieldChange> Returns the changes, keyed by column name.
     */
    public function getBusinessChanges(): array
    {
        return array_filter($this->changes, function (EntityFieldChange $change) {
            return $change->kind !== EntityFieldChange::KIND_TECHNICAL;
        });
    }

    /**
     * @param string $column Name of the database column.
     * @return bool Returns **true** if the operation changed that column.
     */
    public function hasChanged(string $column): bool
    {
        return array_key_exists($column, $this->changes);
    }

    /**
     * @param string $column Name of the database column.
     * @return EntityFieldChange|null Returns the change of that column, or **null**.
     */
    public function getChange(string $column): ?EntityFieldChange
    {
        return $this->changes[$column] ?? null;
    }

    /**
     * The value the database held for a column before the operation.
     * @param string $column Name of the database column.
     * @return mixed Returns the old value, or **null** if the column did not change.
     */
    public function getOldValue(string $column): mixed
    {
        return isset($this->changes[$column]) ? $this->changes[$column]->oldValue : null;
    }

    /**
     * The value that was written to a column.
     * @param string $column Name of the database column.
     * @return mixed Returns the new value, or **null** if the column did not change.
     */
    public function getNewValue(string $column): mixed
    {
        return isset($this->changes[$column]) ? $this->changes[$column]->newValue : null;
    }

    /**
     * The values the database held before the operation. It is empty for a creation and it is the
     * only place a post-delete action can read the deleted record from, because Entity::delete()
     * clears the object. Redacted columns are not part of it.
     * @return array<string,mixed> Returns the values, keyed by column name.
     */
    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    /**
     * The record whose deletion removed this one. A membership does not only disappear when it is
     * ended, it also disappears when the role or the member is deleted, and a consumer usually has
     * to treat the two differently: the first is news about that membership, the second is a detail
     * of the news about the role or the person.
     *
     * @return string|null Returns the hook ID of the record that caused this operation, or **null**
     *                     if the operation was asked for on its own.
     */
    public function getCauseHookId(): ?string
    {
        return $this->causeHookId;
    }

    /**
     * @return int|string|null Returns the key of the record that caused this operation.
     * @see EntityChangeSet#getCauseHookId
     */
    public function getCauseId(): int|string|null
    {
        return $this->causeId;
    }

    /**
     * @return bool Returns **true** if this operation is a consequence of another one.
     * @see EntityChangeSet#getCauseHookId
     */
    public function isCascade(): bool
    {
        return $this->causeHookId !== null;
    }

    /**
     * A copy of this change set that knows the key of the record. Entity::save() uses it after an
     * insert, because the database assigns the ID only when the record is written.
     * @param int|string|null $id The value of the key column.
     * @param string|null $uuid The UUID of the record.
     * @return self Returns a new change set, this one stays unchanged.
     */
    public function withKey(int|string|null $id, ?string $uuid): self
    {
        return new self(
            $this->hookId,
            $this->entityClass,
            $this->tableName,
            $this->columnPrefix,
            $this->keyColumnName,
            $id,
            $uuid,
            $this->operation,
            $this->operationId,
            $this->changes,
            $this->snapshot,
            $this->causeHookId,
            $this->causeId
        );
    }

    /**
     * A copy of this change set that names the record whose deletion removed this one.
     * Entity::hookBulkDeletion() uses it for the records that go together with their owner.
     * @param string|null $causeHookId The hook ID of the record that caused this operation.
     * @param int|string|null $causeId The key of that record.
     * @return self Returns a new change set, this one stays unchanged.
     */
    public function withCause(?string $causeHookId, int|string|null $causeId): self
    {
        return new self(
            $this->hookId,
            $this->entityClass,
            $this->tableName,
            $this->columnPrefix,
            $this->keyColumnName,
            $this->id,
            $this->uuid,
            $this->operation,
            $this->operationId,
            $this->changes,
            $this->snapshot,
            $causeHookId,
            $causeId
        );
    }
}
