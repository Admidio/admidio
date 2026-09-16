<?php
/**
 * Every persistence hook stage hands the callback the live entity alongside the change set, except
 * `deleted` and `delete_failed`, which pass null instead - by the time either can fire the object may
 * already be cleared (delete() clears it before queueing the committed/failure dispatch) or, for a
 * bulk-deleted dependent record, reused for a different row entirely. See the docblock of
 * Entity::dispatchHook().
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use Admidio\Tests\Unit\Hooks\Support\TestBooking;
use Admidio\Tests\Unit\Hooks\Support\TestRoom;
use Admidio\Tests\Unit\Hooks\Support\TestRoomWithBookings;

class EntityHookObjectTest extends EntityHookTestCase
{
    private function newDatabase(): FakeDatabase
    {
        $db = new FakeDatabase();
        $db->createTable(TABLE_PREFIX . '_rooms', FakeDatabase::columnDefinition('room'));
        $bookingColumns = FakeDatabase::columnDefinition('bok');
        $bookingColumns['bok_room_id'] = array('type' => 'integer', 'null' => true, 'key' => false, 'serial' => false, 'default' => null);
        $db->createTable(TABLE_PREFIX . '_bookings', $bookingColumns);
        return $db;
    }

    public function testPreActionsReceiveTheLiveEntityNotJustTheChangeSet(): void
    {
        $db = $this->newDatabase();
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

        $this->assertSame($room, $captured['entity']);
        $this->assertSame(
            'unchanged-secret',
            $captured['entity']->getValue('room_secret'),
            'a plugin can read a field the change set does not carry, e.g. to connect the record elsewhere'
        );
        $this->assertFalse($captured['changeSet']->hasChanged('room_secret'), 'the change set itself still only carries what changed');
    }

    public function testCreatingCreatedAndUpdatedReceiveTheEntity(): void
    {
        $db = $this->newDatabase();
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

        $this->assertSame($room, $seen['room_creating']);
        $this->assertSame($room, $seen['room_created']);
        $this->assertSame($room, $seen['room_updated']);
    }

    public function testDeletingReceivesTheEntityDeletedDoesNot(): void
    {
        $db = $this->newDatabase();
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

        $this->assertSame($room, $seen['deleting'], 'room_deleting still receives the entity - delete() has not cleared it yet');
        $this->assertArrayHasKey('deleted', $seen);
        $this->assertNull($seen['deleted'], 'room_deleted receives null, not the now-cleared entity');
    }

    public function testBulkDeletedDependentRecordAlsoGetsNullNotTheScratchObject(): void
    {
        $db = $this->newDatabase();
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

        $this->assertCount(2, $bookingEntities);
        $this->assertNull($bookingEntities[0], 'both bulk-deleted bookings report null - the scratch object was reused for each row');
        $this->assertNull($bookingEntities[1]);
    }

    /**
     * FakeDatabase::breakNextStatement only mangles an INSERT, so this checks the create path;
     * nothing in dispatchHook() distinguishes create_failed from update_failed, both are driven by
     * the same $entity assignment.
     */
    public function testCreateFailedReceivesTheEntity(): void
    {
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Blue Room');

        $seen = null;
        Hooks::addAction('room_create_failed', function (EntityChangeSet $cs, ?Entity $entity) use (&$seen) {
            $seen = $entity;
        });
        $db->breakNextStatement = true;

        try {
            $room->save();
            $this->fail('the broken statement should have thrown');
        } catch (\Throwable $exception) {
            $this->assertSame($room, $seen);
        }
    }
}
