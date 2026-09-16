<?php
/**
 * The entity_value and <entity>_value filters, and the pre-action veto.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use Admidio\Tests\Unit\Hooks\Support\TestRoom;
use Admidio\Tests\Unit\Hooks\Support\TestSession;
use RuntimeException;
use Throwable;

class EntityValueFilterTest extends EntityHookTestCase
{
    private function newDatabase(): FakeDatabase
    {
        $db = new FakeDatabase();
        $db->createTable(TABLE_PREFIX . '_rooms', FakeDatabase::columnDefinition('room'));
        $db->createTable(TABLE_PREFIX . '_clients', FakeDatabase::columnDefinition('ocl'));
        $db->createTable(TABLE_PREFIX . '_sessions', FakeDatabase::columnDefinition('ses'));
        return $db;
    }

    public function testEntityValueTransformsTheValue(): void
    {
        Hooks::addFilter('entity_value', function ($value, $entity, $column) {
            return ($column === 'room_name') ? strtoupper($value) : $value;
        });
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'blue room');

        $this->assertSame('BLUE ROOM', $room->getValue('room_name'));

        $room->save();
        $this->assertSame(
            'BLUE ROOM',
            $db->fetchAll(TABLE_PREFIX . '_rooms')[0]['room_name'],
            'the transformed value is what is stored'
        );
    }

    public function testGenericFilterRunsBeforeSpecificOneAndReceivesColumnAndOldValue(): void
    {
        $seen = array();
        Hooks::addFilter('entity_value', function ($value, $entity, $column, $oldValue) use (&$seen) {
            $seen[] = 'generic:' . $column . ':' . var_export($oldValue, true);
            return $value . '|g';
        });
        Hooks::addFilter('room_value', function ($value, $entity, $column) use (&$seen) {
            $seen[] = 'specific:' . $column;
            return $value . '|s';
        });
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'x');

        $this->assertSame('x|g|s', $room->getValue('room_name'), 'the generic filter runs before the entity-specific one');
        $this->assertSame('generic:room_name:NULL', $seen[0], 'the filter receives the column and the old value');
    }

    public function testBookkeepingColumnsAreNotFiltered(): void
    {
        $columns = array();
        Hooks::addFilter('entity_value', function ($value, $entity, $column) use (&$columns) {
            $columns[] = $column;
            return $value;
        });
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'a room');
        $room->save();
        $GLOBALS['gCurrentUserId'] = 42;
        $room->setValue('room_name', 'another room');
        $room->save();
        $GLOBALS['gCurrentUserId'] = 0;

        $this->assertSame(
            array('room_name', 'room_name'),
            $columns,
            'the key, the UUID and the creator and editor columns are not filtered'
        );
    }

    public function testSanitizingOfSetValueRunsOnTheResultOfTheFilter(): void
    {
        Hooks::addFilter('entity_value', function ($value) {
            return '<b>' . $value . '</b><script>alert(1)</script>';
        });
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'plain');

        $this->assertSame('plainalert(1)', $room->getValue('room_name'));
    }

    public function testEntityWithoutHookIdIsNotFiltered(): void
    {
        $called = 0;
        Hooks::addFilter('entity_value', function ($value) use (&$called) { $called++; return $value; });
        $db = $this->newDatabase();
        $session = new TestSession($db);
        $session->setValue('ses_name', 'a session');

        $this->assertSame(0, $called);
    }

    public function testFilterCanRejectAValueByThrowing(): void
    {
        Hooks::addFilter('room_value', function ($value, $entity, $column) {
            if ($column === 'room_name' && !str_starts_with($value, 'Room ')) {
                throw new RuntimeException('SYS_FIELD_INVALID_INPUT');
            }
            return $value;
        });
        $db = $this->newDatabase();
        $room = new TestRoom($db);

        $this->expectException(RuntimeException::class);
        $room->setValue('room_name', 'Lounge');
    }

    public function testPreActionVetoesTheOperationAndLeavesTheObjectSaveable(): void
    {
        Hooks::addAction('room_creating', function (EntityChangeSet $cs) {
            throw new RuntimeException('not allowed');
        });
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Lounge');

        $thrown = false;
        try {
            $room->save();
        } catch (RuntimeException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'a pre-action vetoes the operation by throwing');
        $this->assertCount(0, $db->fetchAll(TABLE_PREFIX . '_rooms'), 'and nothing was written');

        // a rejected save must leave the object saveable, so the caller can react and save again
        Hooks::reset();
        $this->assertTrue($room->hasColumnsValueChanged(), 'a vetoed save leaves the object unchanged');
        $saved = $room->save();
        $this->assertTrue($saved, 'so the very same object can be saved afterwards');
        $this->assertCount(1, $db->fetchAll(TABLE_PREFIX . '_rooms'));
        $this->assertSame(
            'Lounge',
            $db->fetchAll(TABLE_PREFIX . '_rooms')[0]['room_name'],
            'and it kept its value'
        );
    }

    public function testFailingStatementDispatchesFailureStagesSpecificFirst(): void
    {
        $log = array();
        foreach (array('room_create_failed', 'entity_create_failed') as $name) {
            Hooks::addAction($name, function (EntityChangeSet $cs) use (&$log, $name) { $log[] = $name; });
        }
        // a listener exists, so a change set is built
        Hooks::addAction('room_creating', function (EntityChangeSet $cs) {
        });
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Lounge');
        $db->breakNextStatement = true;

        $thrown = null;
        try {
            $room->save();
        } catch (Throwable $e) {
            $thrown = $e;
        }

        $this->assertSame(array('room_create_failed', 'entity_create_failed'), $log);
        $this->assertNotNull($thrown, 'the original failure is what is reported');
        $this->assertStringContainsString('no such table', $thrown->getMessage());
    }
}
