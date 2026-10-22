<?php
/**
 * The records that an entity removes together with itself. deleteDependentRecords() replaces them
 * with one DELETE, so their delete hooks have to be queued before that statement runs - otherwise a
 * listener never learns that they existed.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\Service\EntityHookQueue;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use Admidio\Tests\Unit\Hooks\Support\TestBooking;
use Admidio\Tests\Unit\Hooks\Support\TestRoomWithBookings;

class EntityCascadeDeleteTest extends EntityHookTestCase
{
    private function bookingColumns(): array
    {
        $columns = FakeDatabase::columnDefinition('bok');
        $columns['bok_room_id'] = array('type' => 'integer', 'null' => true, 'key' => false, 'serial' => false, 'default' => null);
        return $columns;
    }

    private function newDatabase(): FakeDatabase
    {
        $db = new FakeDatabase();
        $db->createTable(TABLE_PREFIX . '_rooms', FakeDatabase::columnDefinition('room'));
        $db->createTable(TABLE_PREFIX . '_bookings', $this->bookingColumns());
        return $db;
    }

    /** A room with two bookings, all committed. */
    private function roomWithBookings(FakeDatabase $db): TestRoomWithBookings
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

    private function watch(array &$events): void
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

    private function stages(array $events): string
    {
        return implode(' ', array_column($events, 'stage'));
    }

    public function testEveryDependentRecordReportsItsOwnDeletionBeforeTheOwner(): void
    {
        $db = $this->newDatabase();
        $room = $this->roomWithBookings($db);
        $events = array();
        $this->watch($events);
        $room->delete();

        $this->assertSame(
            'deleting deleting deleted deleted room_deleted',
            $this->stages($events),
            'the dependent records are dispatched before the record they belong to'
        );
        $this->assertSame(array(), $db->fetchAll(TABLE_PREFIX . '_bookings'), 'and the rows are really gone');

        $deleted = array_values(array_filter($events, function (array $event) {
            return $event['stage'] === 'deleted';
        }));
        $names = array_map(function (array $event) {
            return $event['changeSet']->getOldValue('bok_name');
        }, $deleted);
        $this->assertSame(array('Choir', 'Board'), $names, 'each of them names the record that was removed');

        /** @var EntityChangeSet $first */
        $first = $deleted[0]['changeSet'];
        $this->assertTrue($first->isDelete());
        $this->assertSame('booking', $first->getHookId());
        $this->assertSame(1, $first->getId(), 'the id of the removed record is known');
        $this->assertSame('Choir', $first->getSnapshot()['bok_name'] ?? null, 'the snapshot carries the record');
        $this->assertTrue($first->isCascade());
        $this->assertSame('room', $first->getCauseHookId(), 'and it says what removed it');
        $this->assertSame(1, $first->getCauseId());

        $roomEvent = array_values(array_filter($events, function (array $event) {
            return $event['stage'] === 'room_deleted';
        }))[0]['changeSet'];
        $this->assertFalse($roomEvent->isCascade(), 'the record that was asked for is no cascade');
        $this->assertSame(1, $first->getOldValue('bok_room_id'), 'the owner it belonged to is in the change set');
    }

    public function testDeletionsWaitForTheOutermostCommit(): void
    {
        $db = $this->newDatabase();
        $room = $this->roomWithBookings($db);
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $room->delete();
        $this->assertSame('deleting deleting', $this->stages($events));

        $db->endTransaction();
        $this->assertSame('deleting deleting deleted deleted room_deleted', $this->stages($events));
    }

    public function testARolledBackCascadeReportsTheFailureInstead(): void
    {
        $db = $this->newDatabase();
        $room = $this->roomWithBookings($db);
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $room->delete();
        $db->rollback();

        $this->assertSame('deleting deleting delete_failed delete_failed', $this->stages($events));
        $this->assertCount(2, $db->fetchAll(TABLE_PREFIX . '_bookings'), 'and the rows are still there');
    }

    public function testWithoutAListenerTheDependentRecordsAreNotReadAtAll(): void
    {
        $db = $this->newDatabase();
        $room = $this->roomWithBookings($db);
        Hooks::reset();
        $before = count($db->statements);
        $room->delete();
        $selects = 0;
        foreach (array_slice($db->statements, $before) as $statement) {
            if (str_starts_with(ltrim($statement), 'SELECT * FROM ' . TABLE_PREFIX . '_bookings')) {
                $selects++;
            }
        }
        $this->assertSame(0, $selects);
    }
}
