<?php
/**
 * The persistence lifecycle of Entity, executed against SQLite.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/entities.php';

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Hooks\ValueObject\EntityFieldChange;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Tests\Hooks\FakeDatabase;
use Admidio\Tests\Hooks\TestClient;
use Admidio\Tests\Hooks\TestRoom;
use Admidio\Tests\Hooks\TestSession;

function newDatabase(): FakeDatabase
{
    $db = new FakeDatabase();
    $db->createTable(TABLE_PREFIX . '_rooms', columnDefinition('room'));
    $db->createTable(TABLE_PREFIX . '_clients', columnDefinition('ocl'));
    $db->createTable(TABLE_PREFIX . '_sessions', columnDefinition('ses'));
    return $db;
}

/** Record every dispatch of the lifecycle, so the order can be asserted. */
function recordAll(array &$log): void
{
    foreach (array('entity', 'room', 'oidc_client', 'ses') as $prefix) {
        foreach (array('creating', 'created', 'updating', 'updated', 'deleting', 'deleted',
                       'create_failed', 'update_failed', 'delete_failed') as $stage) {
            $name = $prefix . '_' . $stage;
            Hooks::addAction($name, function (EntityChangeSet $cs) use (&$log, $name) {
                $log[] = $name;
            });
        }
    }
}

// ---------------------------------------------------------------- create
Hooks::reset();
$log = array();
recordAll($log);
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->save();
check('a creation dispatches generic-before then specific-before, specific-after then generic-after',
    $log === array('entity_creating', 'room_creating', 'room_created', 'entity_created'),
    implode(' ', $log));

// ---------------------------------------------------------------- create change set
Hooks::reset();
$captured = array();
Hooks::addAction('room_creating', function (EntityChangeSet $cs) use (&$captured) { $captured['pre'] = $cs; });
Hooks::addAction('room_created', function (EntityChangeSet $cs) use (&$captured) { $captured['post'] = $cs; });
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->save();
$pre = $captured['pre'];
$post = $captured['post'];
check('the create change set says create', $pre->isCreate() && $pre->getOperation() === EntityChangeSet::OPERATION_CREATE);
check('it carries the hook ID and the table', $pre->getHookId() === 'room' && $pre->getTableName() === TABLE_PREFIX . '_rooms');
check('the old value of a creation is null', $pre->getOldValue('room_name') === null);
check('the new value is what was set', $pre->getNewValue('room_name') === 'Blue Room', var_export($pre->getNewValue('room_name'), true));
check('the snapshot of a creation is empty', $pre->getSnapshot() === array());
check('the pre-action does not know the ID yet', $pre->getId() === null, var_export($pre->getId(), true));
check('the post-action knows the ID', (int)$post->getId() === 1, var_export($post->getId(), true));
check('both stages share one operation ID', $pre->getOperationId() === $post->getOperationId());
check('the UUID is reported', $post->getUuid() !== null && strlen($post->getUuid()) === 36);
check('the record really was written', count($db->fetchAll(TABLE_PREFIX . '_rooms')) === 1);

// ---------------------------------------------------------------- update
Hooks::reset();
$log = array();
recordAll($log);
$captured = array();
Hooks::addAction('room_updated', function (EntityChangeSet $cs) use (&$captured) { $captured['cs'] = $cs; });
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->save();
$log = array();
$room->setValue('room_name', 'Green Room');
$room->save();
check('an update dispatches the update stages', $log === array('entity_updating', 'room_updating', 'room_updated', 'entity_updated'), implode(' ', $log));
$cs = $captured['cs'];
check('the update reports the persisted old value', $cs->getOldValue('room_name') === 'Blue Room', var_export($cs->getOldValue('room_name'), true));
check('and the new one', $cs->getNewValue('room_name') === 'Green Room');
check('the snapshot holds the record as the database had it', ($cs->getSnapshot()['room_name'] ?? null) === 'Blue Room', var_export($cs->getSnapshot()['room_name'] ?? null, true));

// ---------------------------------------------------------------- A -> B -> C and A -> B -> A
Hooks::reset();
$captured = array();
Hooks::addAction('room_updated', function (EntityChangeSet $cs) use (&$captured) { $captured[] = $cs; });
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'A');
$room->save();
$room->setValue('room_name', 'B');
$room->setValue('room_name', 'C');
$room->save();
check('A -> B -> C reports A -> C', count($captured) === 1
    && $captured[0]->getOldValue('room_name') === 'A' && $captured[0]->getNewValue('room_name') === 'C',
    count($captured) . ' events');

$captured = array();
$room->setValue('room_name', 'D');
$room->setValue('room_name', 'C');
$saved = $room->save();
check('A -> B -> A dispatches nothing and saves nothing', $captured === array() && $saved === false);

echo "\n";
exit(testSummary());
