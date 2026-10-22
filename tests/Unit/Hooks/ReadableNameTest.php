<?php
/**
 * Entity::readableName() dispatches entity_readable_name and, for a named entity, the specific
 * <hookId>_readable_name as well. Executed against the real Entity.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use Admidio\Tests\Unit\Hooks\Support\TestRoom;
use Admidio\Tests\Unit\Hooks\Support\TestSession;

class ReadableNameTest extends EntityHookTestCase
{
    private function newDatabase(): FakeDatabase
    {
        $db = new FakeDatabase();
        $db->createTable(TABLE_PREFIX . '_rooms', FakeDatabase::columnDefinition('room'));
        $db->createTable(TABLE_PREFIX . '_sessions', FakeDatabase::columnDefinition('ses'));
        return $db;
    }

    public function testANamedEntityGetsBothFilters(): void
    {
        $log = array();
        Hooks::addFilter('entity_readable_name', function (string $name) use (&$log) {
            $log[] = 'entity:' . $name;
            return $name;
        });
        Hooks::addFilter('room_readable_name', function (string $name) use (&$log) {
            $log[] = 'room:' . $name;
            return $name . ' (Room)';
        });
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Blue Room');
        $room->save();
        $name = $room->readableName();

        $this->assertSame(array('entity:Blue Room', 'room:Blue Room'), $log, 'the generic filter runs before the specific one');
        $this->assertSame('Blue Room (Room)', $name, 'the specific filter\'s result is what readableName() returns');
    }

    public function testAnUnnamedEntityGetsOnlyTheGenericFilter(): void
    {
        $log = array();
        Hooks::addFilter('entity_readable_name', function (string $name) use (&$log) {
            $log[] = 'entity:' . $name;
            return $name;
        });
        Hooks::addFilter('ses_readable_name', function (string $name) use (&$log) {
            $log[] = 'ses:' . $name;
            return $name;
        });
        $db = $this->newDatabase();
        $session = new TestSession($db);
        $session->setValue('ses_name', 'A Session');
        $session->save();
        $session->readableName();

        $this->assertSame(array('entity:A Session'), $log, 'an entity with no hook ID never reaches a specific filter');
    }

    public function testWithoutAListenerTheNameIsUnchanged(): void
    {
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Green Room');
        $room->save();

        $this->assertSame('Green Room', $room->readableName());
    }
}
