<?php
namespace Admidio\Hooks\Service;

use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Hooks\ValueObject\EntityFieldChange;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;

/**
 * Holds the committed persistence hooks of the entities until the change they describe is really in
 * the database, and reduces the saves of one record within one transaction to the one change that
 * happened as far as anybody outside can tell.
 *
 * Two problems are solved here that a hook dispatched straight from Entity::save() has:
 *
 * 1. **A post-action must mean committed.** Admidio counts nested transactions and only commits at
 *    the outermost end, so a callback that runs inside Entity::save() can describe a change that is
 *    rolled back afterwards. The queue hands the dispatch to Database::registerAfterCommit(), which
 *    runs it at the commit and drops it when the transaction is lost.
 * 2. **One logical operation must be one event.** modules/events/events_function.php saves an event,
 *    creates its participation role and saves the event again; User::save() saves a registration
 *    twice. A plugin that sends a webhook wants one event, not two, and it wants the change from
 *    what the database held before the transaction to what it holds after it.
 *
 * The rules for putting two operations of one record together:
 *
 * | first  | second | result                                                   |
 * |--------|--------|----------------------------------------------------------|
 * | create | update | one create with the final values                          |
 * | create | delete | nothing, the record never existed outside the transaction |
 * | update | update | one update, oldest old value, newest new value            |
 * | update | delete | one delete, against the state at the start                |
 *
 * A column that ends where it started is dropped, and an update that has nothing left is dropped
 * with it. A create keeps all its columns, because the record itself is the news.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EntityHookQueue
{
    /**
     * @var array<string,array> The operations that are waiting, keyed by record, in the order in
     *                          which the records were first written.
     */
    protected static array $pending = array();

    /**
     * @var bool Whether the flush is already registered with the database of the current transaction.
     */
    protected static bool $flushRegistered = false;

    /**
     * Queue the committed hooks of one operation. If no transaction is open, the statement was the
     * commit and the hooks are dispatched right away.
     *
     * @param Entity $entity The entity that was written, used to tell two records apart when the
     *                       table has no key column of its own.
     * @param EntityChangeSet $changeSet What the operation changed.
     * @param Database $database The database the operation ran on.
     * @return void
     */
    public static function add(Entity $entity, EntityChangeSet $changeSet, Database $database): void
    {
        $key = self::keyOf($entity, $changeSet);

        if (array_key_exists($key, self::$pending)) {
            $merged = self::merge(self::$pending[$key]['changeSet'], $changeSet);

            if ($merged === null) {
                // created and deleted again within the transaction: nothing happened
                unset(self::$pending[$key]);
                return;
            }

            self::$pending[$key]['changeSet'] = $merged;
        } else {
            self::$pending[$key] = array('entity' => $entity, 'changeSet' => $changeSet);
        }

        if (!self::$flushRegistered) {
            self::$flushRegistered = true;
            $database->registerAfterCommit(function () {
                self::flush();
            });
            $database->registerAfterRollback(function () {
                self::discard();
            });
        }
    }

    /**
     * Dispatch everything that was waiting. Called by the database at the outermost commit.
     * @return void
     */
    public static function flush(): void
    {
        $pending = self::$pending;
        self::reset();

        foreach ($pending as $operation) {
            $operation['entity']->dispatchCommittedHook($operation['changeSet']);
        }
    }

    /**
     * Drop everything that was waiting and let the failure hooks run instead. Called by the database
     * when the transaction is rolled back, and at the end of a request that abandoned one.
     * @return void
     */
    public static function discard(): void
    {
        $pending = self::$pending;
        self::reset();

        foreach ($pending as $operation) {
            $operation['entity']->dispatchFailedHook($operation['changeSet']);
        }
    }

    /**
     * Forget everything without dispatching. For tests and for a CLI process that starts over.
     * @return void
     */
    public static function reset(): void
    {
        self::$pending = array();
        self::$flushRegistered = false;
    }

    /**
     * @return int Returns the number of operations that are waiting for a commit.
     */
    public static function countPending(): int
    {
        return count(self::$pending);
    }

    /**
     * What makes two operations belong to the same record. Normally the table and the key, so that
     * two objects of one row are recognized as one record; a table without a key column falls back
     * to the identity of the object.
     * @param Entity $entity The entity that was written.
     * @param EntityChangeSet $changeSet What the operation changed.
     * @return string Returns the key of the record.
     */
    protected static function keyOf(Entity $entity, EntityChangeSet $changeSet): string
    {
        if ($changeSet->getId() !== null) {
            return $changeSet->getTableName() . '#' . $changeSet->getId();
        }

        return $changeSet->getTableName() . '@' . spl_object_id($entity);
    }

    /**
     * Put two operations of one record together, so that the outside world sees the one change that
     * the transaction made. See the table in the class description for the rules.
     * @param EntityChangeSet $first The operation that was queued before.
     * @param EntityChangeSet $second The operation that just happened.
     * @return EntityChangeSet|null Returns the one operation, or **null** if nothing is left of it.
     */
    protected static function merge(EntityChangeSet $first, EntityChangeSet $second): ?EntityChangeSet
    {
        if ($first->isCreate() && $second->isDelete()) {
            return null;
        }

        // a record that was created in this transaction stays a creation, however often it is
        // written afterwards
        $operation = $first->isCreate() ? EntityChangeSet::OPERATION_CREATE : $second->getOperation();

        $changes = $first->getChanges();
        foreach ($second->getChanges() as $column => $change) {
            if (array_key_exists($column, $changes)) {
                // the value the database held is the one of the first operation, the value it holds
                // now is the one of the last
                $changes[$column] = new EntityFieldChange(
                    $column,
                    $changes[$column]->oldValue,
                    $change->newValue,
                    $change->type,
                    $change->kind
                );
            } else {
                $changes[$column] = $change;
            }
        }

        if ($operation === EntityChangeSet::OPERATION_UPDATE) {
            // a column that ends where it started did not change
            $changes = array_filter($changes, function (EntityFieldChange $change) {
                return $change->isRedacted() || $change->oldValue != $change->newValue;
            });

            if (count($changes) === 0) {
                return null;
            }
        }

        return new EntityChangeSet(
            $second->getHookId(),
            $second->getEntityClass(),
            $second->getTableName(),
            $second->getColumnPrefix(),
            $second->getKeyColumnName(),
            $second->getId() ?? $first->getId(),
            $second->getUuid() ?? $first->getUuid(),
            $operation,
            $first->getOperationId(),
            $changes,
            // the state at the start of the transaction, which is what the change is measured from
            $first->getSnapshot(),
            $second->getCauseHookId() ?? $first->getCauseHookId(),
            $second->getCauseId() ?? $first->getCauseId()
        );
    }
}
