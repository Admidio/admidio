<?php
/**
 * The committed hooks: they fire at the outermost commit, they describe what the transaction did as
 * a whole, and they do not fire at all when the transaction is lost.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/entities.php';

use Admidio\Hooks\Hooks;
use Admidio\Hooks\Service\EntityHookQueue;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Tests\Hooks\FakeDatabase;
use Admidio\Tests\Hooks\TestRoom;

function newDatabase(): FakeDatabase
{
    Hooks::reset();
    EntityHookQueue::reset();
    $db = new FakeDatabase();
    $db->createTable(TABLE_PREFIX . '_rooms', columnDefinition('room'));
    $db->createTable(TABLE_PREFIX . '_clients', columnDefinition('ocl'));
    $db->createTable(TABLE_PREFIX . '_sessions', columnDefinition('ses'));
    return $db;
}

/** Collect every committed and failed event of a room. */
function watch(array &$events): void
{
    foreach (array('created', 'updated', 'deleted', 'create_failed', 'update_failed', 'delete_failed') as $stage) {
        Hooks::addAction('room_' . $stage, function (EntityChangeSet $cs) use (&$events, $stage) {
            $events[] = array('stage' => $stage, 'changeSet' => $cs);
        });
    }
}

function stages(array $events): string
{
    return implode(' ', array_column($events, 'stage'));
}

// ---------------------------------------------------------------- nothing fires before the commit
$db = newDatabase();
$events = array();
watch($events);
$db->startTransaction();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->save();
check('nothing is dispatched while the transaction is open', $events === array(), stages($events));
check('but the operation is waiting', EntityHookQueue::countPending() === 1);
$db->endTransaction();
check('the commit dispatches it', stages($events) === 'created', stages($events));

// ---------------------------------------------------------------- nested transactions
$db = newDatabase();
$events = array();
watch($events);
$db->startTransaction();
$db->startTransaction();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->save();
$db->endTransaction();
check('the inner end does not dispatch', $events === array(), stages($events));
$db->endTransaction();
check('the outermost end does', stages($events) === 'created', stages($events));

// ---------------------------------------------------------------- two saves become one event
$db = newDatabase();
$events = array();
watch($events);
$db->startTransaction();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->save();
$room->setValue('room_name', 'Green Room');
$room->save();
$db->endTransaction();
check('a create and an update in one transaction are one creation', stages($events) === 'created', stages($events));
check('with the final values', $events[0]['changeSet']->getNewValue('room_name') === 'Green Room',
    var_export($events[0]['changeSet']->getNewValue('room_name'), true));

// ---------------------------------------------------------------- two updates become one
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'A');
$room->save();
$events = array();
watch($events);
$db->startTransaction();
$room->setValue('room_name', 'B');
$room->save();
$room->setValue('room_name', 'C');
$room->save();
$db->endTransaction();
check('two updates in one transaction are one update', stages($events) === 'updated', stages($events));
check('from the value before the transaction to the value after it',
    $events[0]['changeSet']->getOldValue('room_name') === 'A'
    && $events[0]['changeSet']->getNewValue('room_name') === 'C',
    var_export($events[0]['changeSet']->getOldValue('room_name'), true) . ' -> '
    . var_export($events[0]['changeSet']->getNewValue('room_name'), true));

// ---------------------------------------------------------------- a round trip is no change
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'A');
$room->save();
$events = array();
watch($events);
$db->startTransaction();
$room->setValue('room_name', 'B');
$room->save();
$room->setValue('room_name', 'A');
$room->save();
$db->endTransaction();
check('a value that ends where it started dispatches nothing', $events === array(), stages($events));

// ---------------------------------------------------------------- update then delete
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'A');
$room->save();
$events = array();
watch($events);
$db->startTransaction();
$room->setValue('room_name', 'B');
$room->save();
$room->delete();
$db->endTransaction();
check('an update and a deletion are one deletion', stages($events) === 'deleted', stages($events));
check('measured against the state at the start of the transaction',
    $events[0]['changeSet']->getOldValue('room_name') === 'A',
    var_export($events[0]['changeSet']->getOldValue('room_name'), true));

// ---------------------------------------------------------------- create then delete
$db = newDatabase();
$events = array();
watch($events);
$db->startTransaction();
$room = new TestRoom($db);
$room->setValue('room_name', 'Ephemeral');
$room->save();
$room->delete();
$db->endTransaction();
check('a record created and deleted in one transaction never happened', $events === array(), stages($events));

// ---------------------------------------------------------------- two objects, one row
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'A');
$room->save();
$id = (int)$room->getValue('room_id');
$events = array();
watch($events);
$db->startTransaction();
$first = new TestRoom($db, $id);
$first->setValue('room_name', 'B');
$first->save();
$second = new TestRoom($db, $id);
$second->setValue('room_name', 'C');
$second->save();
$db->endTransaction();
check('two objects of one row are one record', stages($events) === 'updated', stages($events));
check('and the merge spans both of them', $events[0]['changeSet']->getOldValue('room_name') === 'A'
    && $events[0]['changeSet']->getNewValue('room_name') === 'C');

// ---------------------------------------------------------------- rollback
$db = newDatabase();
$events = array();
watch($events);
$db->startTransaction();
$room = new TestRoom($db);
$room->setValue('room_name', 'Never committed');
$room->save();
$db->rollback();
check('a rollback dispatches the failure and not the success', stages($events) === 'create_failed', stages($events));
check('and nothing is left waiting', EntityHookQueue::countPending() === 0);
check('and the row really is gone', count($db->fetchAll(TABLE_PREFIX . '_rooms')) === 0);

// ---------------------------------------------------------------- a lost transaction
$db = newDatabase();
$events = array();
watch($events);
$db->startTransaction();
$room = new TestRoom($db);
$room->setValue('room_name', 'Abandoned');
$room->save();
$db->runAfterRollbackCallbacks();
check('an abandoned transaction dispatches the failure', stages($events) === 'create_failed', stages($events));

// ---------------------------------------------------------------- without a transaction
$db = newDatabase();
$events = array();
watch($events);
$room = new TestRoom($db);
$room->setValue('room_name', 'Immediate');
$room->save();
check('without a transaction the statement is the commit', stages($events) === 'created', stages($events));
check('and nothing is left waiting', EntityHookQueue::countPending() === 0);

echo "\n";
exit(testSummary());
