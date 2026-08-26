<?php
/**
 * Every persistence hook stage hands the callback the live entity alongside the change set, except
 * `deleted` and `delete_failed`, which pass null instead - by the time either can fire the object may
 * already be cleared (delete() clears it before queueing the committed/failure dispatch) or, for a
 * bulk-deleted dependent record, reused for a different row entirely. See the docblock of
 * Entity::dispatchHook().
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/entities.php';

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Tests\Hooks\FakeDatabase;
use Admidio\Tests\Hooks\TestBooking;
use Admidio\Tests\Hooks\TestRoom;
use Admidio\Tests\Hooks\TestRoomWithBookings;

function newDatabase(): FakeDatabase
{
    $db = new FakeDatabase();
    $db->createTable(TABLE_PREFIX . '_rooms', columnDefinition('room'));
    $bookingColumns = columnDefinition('bok');
    $bookingColumns['bok_room_id'] = array('type' => 'integer', 'null' => true, 'key' => false, 'serial' => false, 'default' => null);
    $db->createTable(TABLE_PREFIX . '_bookings', $bookingColumns);
    return $db;
}

// ----------------------------------------------------------- pre-actions get the live entity
Hooks::reset();
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->setValue('room_secret', 'unchanged-secret');
$room->save();

$captured = array();
Hooks::addAction('room_updating', function (EntityChangeSet $cs, ?Entity $entity) use (&$captured) {
    $captured['changeSet'] = $cs;
    $captured['entity'] = $entity;
});
$room->setValue('room_name', 'Green Room');
$room->save();

check('the pre-action receives the live entity, not just the change set', $captured['entity'] === $room);
check(
    'a plugin can read a field the change set does not carry, e.g. to connect the record elsewhere',
    $captured['entity']->getValue('room_secret') === 'unchanged-secret',
    (string) $captured['entity']->getValue('room_secret')
);
check('the change set itself still only carries what changed', !$captured['changeSet']->hasChanged('room_secret'));

// ---------------------------------------------------- creating/created/updated get it too
Hooks::reset();
$db = newDatabase();
$seen = array();
foreach (array('room_creating', 'room_created', 'room_updated') as $name) {
    Hooks::addAction($name, function (EntityChangeSet $cs, ?Entity $entity) use (&$seen, $name) {
        $seen[$name] = $entity;
    });
}
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->save();
$room->setValue('room_name', 'Green Room');
$room->save();

check('room_creating receives the entity', $seen['room_creating'] === $room);
check('room_created receives the entity', $seen['room_created'] === $room);
check('room_updated receives the entity', $seen['room_updated'] === $room);

// --------------------------------------------------------------- deleting gets it, deleted does not
Hooks::reset();
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->save();

$seen = array();
Hooks::addAction('room_deleting', function (EntityChangeSet $cs, ?Entity $entity) use (&$seen) {
    $seen['deleting'] = $entity;
});
Hooks::addAction('room_deleted', function (EntityChangeSet $cs, ?Entity $entity) use (&$seen) {
    $seen['deleted'] = $entity;
});
$room->delete();

check('room_deleting still receives the entity - delete() has not cleared it yet', $seen['deleting'] === $room);
check(
    'room_deleted receives null, not the now-cleared entity',
    array_key_exists('deleted', $seen) && $seen['deleted'] === null
);

// --------------------------------------- a bulk-deleted dependent record also gets null, not the scratch object
Hooks::reset();
$db = newDatabase();
$room = new TestRoomWithBookings($db);
$room->setValue('room_name', 'Blue Room');
$room->save();
foreach (array('Choir', 'Board') as $name) {
    $booking = new TestBooking($db);
    $booking->setValue('bok_name', $name);
    $booking->setValue('bok_room_id', $room->getValue('room_id'));
    $booking->save();
}

$bookingEntities = array();
Hooks::addAction('booking_deleted', function (EntityChangeSet $cs, ?Entity $entity) use (&$bookingEntities) {
    $bookingEntities[] = $entity;
});
$room->delete();

check(
    'both bulk-deleted bookings report null - the scratch object was reused for each row',
    count($bookingEntities) === 2 && $bookingEntities[0] === null && $bookingEntities[1] === null,
    var_export($bookingEntities, true)
);

// ------------------------------------------------------------------ create_failed gets it too
// FakeDatabase::breakNextStatement only mangles an INSERT, so this checks the create path; nothing
// in dispatchHook() distinguishes create_failed from update_failed, both are driven by the same
// $entity assignment.
Hooks::reset();
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');

$seen = null;
Hooks::addAction('room_create_failed', function (EntityChangeSet $cs, ?Entity $entity) use (&$seen) {
    $seen = $entity;
});
$db->breakNextStatement = true;
try {
    $room->save();
    check('the broken statement really failed', false);
} catch (\Throwable $exception) {
    check('room_create_failed receives the entity', $seen === $room);
}

echo "\n";
exit(testSummary());
