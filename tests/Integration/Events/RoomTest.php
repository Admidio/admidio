<?php
/**
 * Room Tests
 *
 * Tests the rooms an event can be held in. Unlike the other records of the events module a room is
 * not guarded by a category and does not belong to an organization: the table has no organization
 * column, so every organization of an installation sees the same rooms. Only the description is
 * stripped of markup on the way out, everything else is stored as it was set.
 */

namespace Admidio\Tests\Integration\Events;

use Admidio\Events\Entity\Event;
use Admidio\Events\Entity\Room;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;

class RoomTest extends DatabaseTestCase
{
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Create a room with the given data.
     *
     * @param array<string,mixed> $values Column values on top of the name
     */
    private function createRoom(string $name, array $values = array()): int
    {
        $room = new Room($this->getDatabase());
        $room->setValue('room_name', $name);
        $room->setValue('room_capacity', 10);

        foreach ($values as $column => $value) {
            $room->setValue($column, $value);
        }

        $room->save();

        return (int) $room->getValue('room_id');
    }

    /**
     * Test that a room is stored with the data it was given
     *
     * @testdox A room is stored with its name, capacity and overhang
     */
    public function testRoomIsStoredWithItsData(): void
    {
        $roomId = $this->createRoom('Assembly hall', array(
            'room_capacity' => 120,
            'room_overhang' => 30
        ));

        $sql = 'SELECT room_uuid, room_name, room_capacity, room_overhang
                  FROM ' . TBL_ROOMS . ' WHERE room_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$roomId])->fetch();

        $this->assertSame('Assembly hall', $row['room_name']);
        $this->assertSame(120, (int) $row['room_capacity']);
        $this->assertSame(30, (int) $row['room_overhang']);

        // every record of the installation is addressable by its UUID
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            (string) $row['room_uuid']
        );
    }

    /**
     * Test that a room can be read back through its entity
     *
     * @testdox A room can be read back through the Room entity
     */
    public function testRoomCanBeReadBack(): void
    {
        $roomId = $this->createRoom('Reading room', array('room_capacity' => 8));

        $room = new Room($this->getDatabase(), $roomId);

        $this->assertSame('Reading room', $room->getValue('room_name'));
        $this->assertSame(8, (int) $room->getValue('room_capacity'));

        // and by the UUID the rest of the application passes around
        $byUuid = new Room($this->getDatabase());
        $this->assertTrue($byUuid->readDataByUuid((string) $room->getValue('room_uuid')));
        $this->assertSame($roomId, (int) $byUuid->getValue('room_id'));
    }

    /**
     * Test the two ways the description is read
     *
     * @testdox The description of a room is stored with its markup and read back without it
     */
    public function testDescriptionIsStrippedOnlyForTheDatabaseFormat(): void
    {
        $roomId = $this->createRoom('Described room', array(
            'room_description' => '<p>Second floor, <strong>left</strong></p>'
        ));

        $room = new Room($this->getDatabase(), $roomId);

        // the stored value keeps the markup the editor produced
        $this->assertSame('<p>Second floor, <strong>left</strong></p>', $room->getValue('room_description'));

        // the database format is what a plain-text consumer asks for
        $this->assertSame('Second floor, left', $room->getValue('room_description', 'database'));
    }

    /**
     * Test that a room without a description answers with an empty string
     *
     * @testdox A room without a description answers with an empty string rather than null
     */
    public function testRoomWithoutDescriptionAnswersWithAnEmptyString(): void
    {
        $roomId = $this->createRoom('Bare room');

        $room = new Room($this->getDatabase(), $roomId);

        $this->assertSame('', $room->getValue('room_description'));
        $this->assertSame('', $room->getValue('room_description', 'database'));
    }

    /**
     * Test that the rooms of an installation are not scoped to an organization
     *
     * @testdox A room is visible in every organization of the installation
     */
    public function testRoomsAreNotScopedToAnOrganization(): void
    {
        $fixture = $this->getFixture();
        $orgA = $fixture->createAndSaveOrganization('Room Org A', 'rooma');
        $orgB = $fixture->createAndSaveOrganization('Room Org B', 'roomb');

        $roomId = $this->withOrganization($orgA['org_id'], fn () => $this->createRoom('Shared room'));

        // the table has no organization column, so the room reads the same in the other organization
        $room = $this->withOrganization($orgB['org_id'], fn () => new Room($this->getDatabase(), $roomId));
        $this->assertSame('Shared room', $room->getValue('room_name'));
    }

    /**
     * Test that an event points at the room it is held in
     *
     * @testdox An event refers to its room through dat_room_id
     */
    public function testEventRefersToItsRoom(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Room Event Org', 'roomevt');
        $category = $fixture->createAndSaveCategory('Calendar', 'EVT', $org['org_id']);

        $role = $fixture->createAndSaveRoleWithRights('Event Admins', $org['org_id'], ['rol_events' => 1]);
        $admin = $fixture->createAndSaveUser('roomevtadmin', 'rea@example.local');
        $fixture->assignUserToRole($admin['usr_id'], $role['rol_id']);
        $adminUser = $this->loadUserInOrganization($admin['usr_id'], $org['org_id']);

        $roomId = $this->createRoom('Booked room', array('room_capacity' => 40));

        $eventId = $this->withCurrentUser($adminUser, $org['org_id'], true, function () use ($category, $roomId) {
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Meeting in a room');
            $event->setValue('dat_begin', '2030-04-01 18:00:00');
            $event->setValue('dat_end', '2030-04-01 20:00:00');
            $event->setValue('dat_room_id', $roomId);
            $event->save();

            return (int) $event->getValue('dat_id');
        });

        $event = $this->withOrganization($org['org_id'], fn () => new Event($this->getDatabase(), $eventId));
        $this->assertSame($roomId, (int) $event->getValue('dat_room_id'));

        // the room is what the events module counts before it lets an administrator delete one
        $sql = 'SELECT COUNT(*) FROM ' . TBL_EVENTS . ' WHERE dat_room_id = ?';
        $this->assertSame(1, (int) $this->getDatabase()->queryPrepared($sql, [$roomId])->fetchColumn());
    }

    /**
     * Test that a room is deleted
     *
     * @testdox A room can be deleted
     */
    public function testRoomCanBeDeleted(): void
    {
        $roomId = $this->createRoom('Temporary room');

        $room = new Room($this->getDatabase(), $roomId);
        $this->assertTrue($room->delete());

        $sql = 'SELECT 1 FROM ' . TBL_ROOMS . ' WHERE room_id = ?';
        $this->assertFalse($this->getDatabase()->queryPrepared($sql, [$roomId])->fetch());
    }
}
