<?php
/**
 * The records that an entity removes together with itself. deleteDependentRecords() replaces them
 * with one DELETE, so their delete hooks have to be queued before that statement runs - otherwise a
 * listener never learns that they existed.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/entities.php';

use Admidio\Hooks\Hooks;
use Admidio\Hooks\Service\EntityHookQueue;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Tests\Hooks\FakeDatabase;
use Admidio\Tests\Hooks\TestBooking;
use Admidio\Tests\Hooks\TestRoomWithBookings;

function bookingColumns(): array
{
    $columns = columnDefinition('bok');
    $columns['bok_room_id'] = array('type' => 'integer', 'null' => true, 'key' => false, 'serial' => false, 'default' => null);
    return $columns;
}

function newDatabase(): FakeDatabase
{
    Hooks::reset();
    EntityHookQueue::reset();
    $db = new FakeDatabase();
    $db->createTable(TABLE_PREFIX . '_rooms', columnDefinition('room'));
    $db->createTable(TABLE_PREFIX . '_bookings', bookingColumns());
    return $db;
}

/** A room with two bookings, all committed. */
function roomWithBookings(FakeDatabase $db): TestRoomWithBookings
{
    $room = new TestRoomWithBookings($db);
    $room->setValue('room_name', 'Blue Room');
    $room->save();

    foreach (array('Choir', 'Board') as $name) {
        $booking = new TestBooking($db);
        $booking->setValue('bok_name', $name);
        $booking->setValue('bok_room_id', $room->getValue('room_id'));
        $booking->save();
    }

    return $room;
}

function watch(array &$events): void
{
    foreach (array('deleting', 'deleted', 'delete_failed') as $stage) {
        Hooks::addAction('booking_' . $stage, function (EntityChangeSet $cs) use (&$events, $stage) {
            $events[] = array('stage' => $stage, 'changeSet' => $cs);
        });
    }
    Hooks::addAction('room_deleted', function (EntityChangeSet $cs) use (&$events) {
        $events[] = array('stage' => 'room_deleted', 'changeSet' => $cs);
    });
}

function stages(array $events): string
{
    return implode(' ', array_column($events, 'stage'));
}

// ------------------------------------------------ every dependent record reports its own deletion
$db = newDatabase();
$room = roomWithBookings($db);
$events = array();
watch($events);
$room->delete();

check(
    'the dependent records are dispatched before the record they belong to',
    stages($events) === 'deleting deleting deleted deleted room_deleted',
    stages($events)
);
check('and the rows are really gone', $db->fetchAll(TABLE_PREFIX . '_bookings') === array());

$deleted = array_values(array_filter($events, function (array $event) {
    return $event['stage'] === 'deleted';
}));
$names = array_map(function (array $event) {
    return $event['changeSet']->getOldValue('bok_name');
}, $deleted);
check('each of them names the record that was removed', $names === array('Choir', 'Board'), implode(',', $names));

$first = $deleted[0]['changeSet'];
check('the operation is a deletion', $first->isDelete() && $first->getHookId() === 'booking');
check('the id of the removed record is known', $first->getId() === 1, var_export($first->getId(), true));
check('the snapshot carries the record', ($first->getSnapshot()['bok_name'] ?? null) === 'Choir');
check(
    'and it says what removed it',
    $first->isCascade() && $first->getCauseHookId() === 'room' && $first->getCauseId() === 1,
    var_export(array($first->getCauseHookId(), $first->getCauseId()), true)
);

$roomEvent = array_values(array_filter($events, function (array $event) {
    return $event['stage'] === 'room_deleted';
}))[0]['changeSet'];
check('the record that was asked for is no cascade', !$roomEvent->isCascade());
check(
    'the owner it belonged to is in the change set',
    $first->getOldValue('bok_room_id') === 1,
    var_export($first->getOldValue('bok_room_id'), true)
);

// ------------------------------------------------------------ nothing is dispatched before commit
$db = newDatabase();
$room = roomWithBookings($db);
$events = array();
watch($events);
$db->startTransaction();
$room->delete();
check('the deletions wait for the outermost commit', stages($events) === 'deleting deleting', stages($events));
$db->endTransaction();
check('and are dispatched by it', stages($events) === 'deleting deleting deleted deleted room_deleted', stages($events));

// ----------------------------------------------------------- a lost transaction reports a failure
$db = newDatabase();
$room = roomWithBookings($db);
$events = array();
watch($events);
$db->startTransaction();
$room->delete();
$db->rollback();
check(
    'a rolled back cascade reports the failure instead',
    stages($events) === 'deleting deleting delete_failed delete_failed',
    stages($events)
);
check('and the rows are still there', count($db->fetchAll(TABLE_PREFIX . '_bookings')) === 2);

// ---------------------------------------------------------- nothing is read while nobody listens
$db = newDatabase();
$room = roomWithBookings($db);
Hooks::reset();
$before = count($db->statements);
$room->delete();
$selects = 0;
foreach (array_slice($db->statements, $before) as $statement) {
    if (str_starts_with(ltrim($statement), 'SELECT * FROM ' . TABLE_PREFIX . '_bookings')) {
        $selects++;
    }
}
check('without a listener the dependent records are not read at all', $selects === 0, (string)$selects);

exit(testSummary());
