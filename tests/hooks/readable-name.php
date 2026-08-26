<?php
/**
 * Entity::readableName() dispatches entity_readable_name and, for a named entity, the specific
 * <hookId>_readable_name as well. Executed against the real Entity.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/entities.php';

use Admidio\Hooks\Hooks;
use Admidio\Tests\Hooks\FakeDatabase;
use Admidio\Tests\Hooks\TestRoom;
use Admidio\Tests\Hooks\TestSession;

function newDatabase(): FakeDatabase
{
    $db = new FakeDatabase();
    $db->createTable(TABLE_PREFIX . '_rooms', columnDefinition('room'));
    $db->createTable(TABLE_PREFIX . '_sessions', columnDefinition('ses'));
    return $db;
}

// ---------------------------------------------------------------- a named entity gets both filters
Hooks::reset();
$log = array();
Hooks::addFilter('entity_readable_name', function (string $name) use (&$log) {
    $log[] = 'entity:' . $name;
    return $name;
});
Hooks::addFilter('room_readable_name', function (string $name) use (&$log) {
    $log[] = 'room:' . $name;
    return $name . ' (Room)';
});
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->save();
$name = $room->readableName();
check('the generic filter runs before the specific one', $log === array('entity:Blue Room', 'room:Blue Room'), implode(',', $log));
check('the specific filter\'s result is what readableName() returns', $name === 'Blue Room (Room)', $name);

// ---------------------------------------------------------------- an unnamed entity gets only the generic filter
Hooks::reset();
$log = array();
Hooks::addFilter('entity_readable_name', function (string $name) use (&$log) {
    $log[] = 'entity:' . $name;
    return $name;
});
Hooks::addFilter('ses_readable_name', function (string $name) use (&$log) {
    $log[] = 'ses:' . $name;
    return $name;
});
$db = newDatabase();
$session = new TestSession($db);
$session->setValue('ses_name', 'A Session');
$session->save();
$session->readableName();
check('an entity with no hook ID never reaches a specific filter', $log === array('entity:A Session'), implode(',', $log));

// ---------------------------------------------------------------- no listener costs nothing and changes nothing
Hooks::reset();
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Green Room');
$room->save();
check('without a listener the name is unchanged', $room->readableName() === 'Green Room', $room->readableName());

echo "\n";
exit(testSummary());
