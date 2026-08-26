<?php
/**
 * The entity_value and <entity>_value filters, and the pre-action veto.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/entities.php';

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
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

// ---------------------------------------------------------------- transform
Hooks::reset();
Hooks::addFilter('entity_value', function ($value, $entity, $column) {
    return ($column === 'room_name') ? strtoupper($value) : $value;
});
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'blue room');
check('entity_value transforms the value', $room->getValue('room_name') === 'BLUE ROOM', $room->getValue('room_name'));
$room->save();
check('and the transformed value is what is stored', $db->fetchAll(TABLE_PREFIX . '_rooms')[0]['room_name'] === 'BLUE ROOM');

// ---------------------------------------------------------------- order and arguments
Hooks::reset();
$seen = array();
Hooks::addFilter('entity_value', function ($value, $entity, $column, $oldValue) use (&$seen) {
    $seen[] = 'generic:' . $column . ':' . var_export($oldValue, true);
    return $value . '|g';
});
Hooks::addFilter('room_value', function ($value, $entity, $column, $oldValue) use (&$seen) {
    $seen[] = 'specific:' . $column;
    return $value . '|s';
});
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'x');
check('the generic filter runs before the entity-specific one', $room->getValue('room_name') === 'x|g|s', $room->getValue('room_name'));
check('the filter receives the column and the old value', $seen[0] === "generic:room_name:NULL", $seen[0]);

// ---------------------------------------------------------------- the bookkeeping is not filtered
Hooks::reset();
$columns = array();
Hooks::addFilter('entity_value', function ($value, $entity, $column) use (&$columns) {
    $columns[] = $column;
    return $value;
});
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'a room');
$room->save();
$GLOBALS['gCurrentUserId'] = 42;
$room->setValue('room_name', 'another room');
$room->save();
$GLOBALS['gCurrentUserId'] = 0;
check('the key, the UUID and the creator and editor columns are not filtered',
    $columns === array('room_name', 'room_name'), implode(',', $columns));

// ---------------------------------------------------------------- the core checks still run
Hooks::reset();
Hooks::addFilter('entity_value', function ($value) {
    return '<b>' . $value . '</b><script>alert(1)</script>';
});
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'plain');
check('the sanitizing of setValue runs on the result of the filter',
    $room->getValue('room_name') === 'plainalert(1)', var_export($room->getValue('room_name'), true));

// ---------------------------------------------------------------- an entity without a hook ID
Hooks::reset();
$called = 0;
Hooks::addFilter('entity_value', function ($value) use (&$called) { $called++; return $value; });
$db = newDatabase();
$session = new TestSession($db);
$session->setValue('ses_name', 'a session');
check('an entity without a hook ID is not filtered', $called === 0, $called . ' calls');

// ---------------------------------------------------------------- rejecting a value
Hooks::reset();
Hooks::addFilter('room_value', function ($value, $entity, $column) {
    if ($column === 'room_name' && !str_starts_with($value, 'Room ')) {
        throw new RuntimeException('SYS_FIELD_INVALID_INPUT');
    }
    return $value;
});
$db = newDatabase();
$room = new TestRoom($db);
$thrown = false;
try {
    $room->setValue('room_name', 'Lounge');
} catch (RuntimeException $e) {
    $thrown = true;
}
check('a filter rejects a value by throwing', $thrown);

// ---------------------------------------------------------------- vetoing an operation
Hooks::reset();
Hooks::addAction('room_creating', function (EntityChangeSet $cs) {
    throw new RuntimeException('not allowed');
});
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Lounge');
$thrown = false;
try {
    $room->save();
} catch (RuntimeException $e) {
    $thrown = true;
}
check('a pre-action vetoes the operation by throwing', $thrown);
check('and nothing was written', count($db->fetchAll(TABLE_PREFIX . '_rooms')) === 0);

// a rejected save must leave the object saveable, so the caller can react and save again
Hooks::reset();
check('a vetoed save leaves the object unchanged', $room->hasColumnsValueChanged());
$saved = $room->save();
check('so the very same object can be saved afterwards', $saved === true
    && count($db->fetchAll(TABLE_PREFIX . '_rooms')) === 1);
check('and it kept its value', $db->fetchAll(TABLE_PREFIX . '_rooms')[0]['room_name'] === 'Lounge',
    var_export($db->fetchAll(TABLE_PREFIX . '_rooms')[0]['room_name'] ?? null, true));

// ---------------------------------------------------------------- failure actions
Hooks::reset();
$log = array();
foreach (array('room_create_failed', 'entity_create_failed') as $name) {
    Hooks::addAction($name, function (EntityChangeSet $cs) use (&$log, $name) { $log[] = $name; });
}
Hooks::addAction('room_creating', function (EntityChangeSet $cs) { /* listener exists, so a change set is built */ });
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Lounge');
$db->breakNextStatement = true;
$thrown = null;
try {
    $room->save();
} catch (Throwable $e) {
    $thrown = $e;
}
check('a failing statement dispatches the failure stages, specific first',
    $log === array('room_create_failed', 'entity_create_failed'), implode(' ', $log));
check('and the original failure is what is reported', $thrown !== null && str_contains($thrown->getMessage(), 'no such table'),
    $thrown === null ? 'nothing thrown' : $thrown->getMessage());

echo "\n";
exit(testSummary());
