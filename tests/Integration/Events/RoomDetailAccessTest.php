<?php

namespace Admidio\Tests\Integration\Events;

use Admidio\Events\Entity\Event;
use Admidio\Events\Entity\Room;
use Admidio\Events\Service\EventService;
use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class RoomDetailAccessTest extends DatabaseTestCase
{
    use PermissionContext;

    public static function accessCases(): array
    {
        return array(
            'public event for guest' => [1, false, false, false, 'valid', true],
            'private event for guest' => [1, false, true, false, 'valid', false],
            'private event for member' => [1, true, true, true, 'valid', true],
            'private event for unrelated user' => [1, true, true, false, 'valid', false],
            'login required for guest' => [2, false, false, false, 'valid', false],
            'login required for member' => [2, true, false, true, 'valid', true],
            'disabled module' => [0, true, false, true, 'valid', false],
            'room enumeration without event' => [1, false, false, false, 'missing', false],
            'nonexistent event' => [1, false, false, false, 'unknown', false],
            'substituted room UUID' => [1, false, false, false, 'otherRoom', false],
            'missing room UUID' => [1, false, false, false, 'missingRoom', false],
            'nonexistent room UUID' => [1, false, false, false, 'unknownRoom', false],
            'numeric room ID rejected' => [1, false, false, false, 'numericRoom', false],
            'event of another organization' => [1, false, false, false, 'otherOrg', false]
        );
    }

    /** @dataProvider accessCases */
    public function testRoomDetailsRequireAccessToTheAssociatedEvent(
        int $moduleAccess,
        bool $loggedIn,
        bool $privateCalendar,
        bool $isMember,
        string $request,
        bool $allowed
    ): void {
        $fixture = new AdmidioTestFixture($this->getDatabase());
        $org = $fixture->createAndSaveOrganization('Room Access', 'roomaccess');
        $category = $fixture->createAndSaveCategory('Room Calendar', 'EVT', $org['org_id']);
        $role = $fixture->createAndSaveRoleWithRights('Room Readers', $org['org_id']);
        if ($privateCalendar) {
            $rights = new RolesRights($this->getDatabase(), 'category_view', $category['cat_id']);
            $rights->saveRoles([$role['rol_id']]);
        }

        $adminRole = $fixture->createAndSaveRoleWithRights('Event Admins', $org['org_id'], ['rol_events' => 1]);
        $admin = $fixture->createAndSaveUser('roomadmin', 'roomadmin@example.local');
        $fixture->assignUserToRole($admin['usr_id'], $adminRole['rol_id']);
        $adminUser = $this->loadUserInOrganization($admin['usr_id'], $org['org_id']);

        $room = new Room($this->getDatabase());
        $room->setValue('room_name', 'Internal venue');
        $room->setValue('room_capacity', 20);
        $room->setValue('room_description', 'Confidential room details');
        $room->save();
        $roomId = (int) $room->getValue('room_id');
        $roomUUID = $room->getValue('room_uuid');

        $eventUUID = $this->withCurrentUser($adminUser, $org['org_id'], true, function () use ($category, $roomId) {
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $category['cat_id']);
            $event->setValue('dat_headline', 'Room meeting');
            $event->setValue('dat_begin', '2030-04-01 18:00:00');
            $event->setValue('dat_end', '2030-04-01 20:00:00');
            $event->setValue('dat_room_id', $roomId);
            $event->save();
            return $event->getValue('dat_uuid');
        });

        $visitor = $this->withOrganization($org['org_id'], fn () => new User($this->getDatabase()));
        if ($loggedIn) {
            $reader = $fixture->createAndSaveUser('roomreader', 'roomreader@example.local');
            if ($isMember) {
                $fixture->assignUserToRole($reader['usr_id'], $role['rol_id']);
            }
            $visitor = $this->loadUserInOrganization($reader['usr_id'], $org['org_id']);
        }

        if ($request === 'missing') {
            $eventUUID = '';
        } elseif ($request === 'unknown') {
            $eventUUID = '00000000-0000-4000-8000-000000000000';
        } elseif ($request === 'otherRoom') {
            $otherRoom = new Room($this->getDatabase());
            $otherRoom->setValue('room_name', 'Unrelated venue');
            $otherRoom->setValue('room_capacity', 10);
            $otherRoom->save();
            $roomUUID = $otherRoom->getValue('room_uuid');
        } elseif ($request === 'missingRoom') {
            $roomUUID = '';
        } elseif ($request === 'unknownRoom') {
            $roomUUID = '00000000-0000-4000-8000-000000000000';
        } elseif ($request === 'numericRoom') {
            $roomUUID = (string) $roomId;
        } elseif ($request === 'otherOrg') {
            $org = $fixture->createAndSaveOrganization('Other Room Org', 'otherroom');
            $visitor = $this->withOrganization($org['org_id'], fn () => new User($this->getDatabase()));
        }

        if (!$allowed) {
            $this->expectException(Exception::class);
        }

        $this->withCurrentUser($visitor, $org['org_id'], $loggedIn, function () use ($moduleAccess, $eventUUID, $roomUUID) {
            $GLOBALS['gSettingsManager']->set('events_module_enabled', (string) $moduleAccess);
            $room = (new EventService($this->getDatabase()))->getRoomForEvent($eventUUID, $roomUUID);
            $this->assertSame('Confidential room details', $room->getValue('room_description'));
        });
    }
}
