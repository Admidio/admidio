<?php
/**
 * The persistence lifecycle of Entity - creation and update - executed against FakeDatabase, an
 * in-memory SQLite connection, so that the real Entity::save() runs and really writes, reads back
 * and updates a row.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use Admidio\Tests\Unit\Hooks\Support\TestRoom;

class EntityLifecycleTest extends EntityHookTestCase
{
    private function newDatabase(): FakeDatabase
    {
        $db = new FakeDatabase();
        $db->createTable(TABLE_PREFIX . '_rooms', FakeDatabase::columnDefinition('room'));
        $db->createTable(TABLE_PREFIX . '_clients', FakeDatabase::columnDefinition('ocl'));
        $db->createTable(TABLE_PREFIX . '_sessions', FakeDatabase::columnDefinition('ses'));
        return $db;
    }

    /** Record every dispatch of the lifecycle, so the order can be asserted. */
    private function recordAllStages(array &$log): void
    {
        foreach (array('entity', 'room', 'oidc_client', 'ses') as $prefix) {
            foreach (array('creating', 'created', 'updating', 'updated', 'deleting', 'deleted',
                           'create_failed', 'update_failed', 'delete_failed') as $stage) {
                $name = $prefix . '_' . $stage;
                Hooks::addAction($name, function (EntityChangeSet $cs) use (&$log, $name) {
                    $log[] = $name;
                });
            }
        }
    }

    /**
     * @testdox a creation dispatches generic-before then specific-before, specific-after then generic-after
     */
    public function testCreationDispatchesGenericThenSpecificThenSpecificThenGeneric(): void
    {
        $log = array();
        $this->recordAllStages($log);
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Blue Room');
        $room->save();

        $this->assertSame(array('entity_creating', 'room_creating', 'room_created', 'entity_created'), $log);
    }

    public function testCreateChangeSet(): void
    {
        $captured = array();
        Hooks::addAction('room_creating', function (EntityChangeSet $cs) use (&$captured) { $captured['pre'] = $cs; });
        Hooks::addAction('room_created', function (EntityChangeSet $cs) use (&$captured) { $captured['post'] = $cs; });
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Blue Room');
        $room->save();

        /** @var EntityChangeSet $pre */
        $pre = $captured['pre'];
        /** @var EntityChangeSet $post */
        $post = $captured['post'];

        $this->assertTrue($pre->isCreate(), 'the create change set says create');
        $this->assertSame(EntityChangeSet::OPERATION_CREATE, $pre->getOperation());
        $this->assertSame('room', $pre->getHookId(), 'it carries the hook ID');
        $this->assertSame(TABLE_PREFIX . '_rooms', $pre->getTableName(), 'and the table');
        $this->assertNull($pre->getOldValue('room_name'), 'the old value of a creation is null');
        $this->assertSame('Blue Room', $pre->getNewValue('room_name'), 'the new value is what was set');
        $this->assertSame(array(), $pre->getSnapshot(), 'the snapshot of a creation is empty');
        $this->assertNull($pre->getId(), 'the pre-action does not know the ID yet');
        $this->assertSame(1, (int)$post->getId(), 'the post-action knows the ID');
        $this->assertSame($pre->getOperationId(), $post->getOperationId(), 'both stages share one operation ID');
        $this->assertNotNull($post->getUuid());
        $this->assertSame(36, strlen($post->getUuid()), 'the UUID is reported');
        $this->assertCount(1, $db->fetchAll(TABLE_PREFIX . '_rooms'), 'the record really was written');
    }

    /**
     * @testdox an update dispatches the update stages and reports the persisted old value
     */
    public function testUpdateDispatchesUpdateStages(): void
    {
        $log = array();
        $this->recordAllStages($log);
        $captured = array();
        Hooks::addAction('room_updated', function (EntityChangeSet $cs) use (&$captured) { $captured['cs'] = $cs; });
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Blue Room');
        $room->save();
        $log = array();
        $room->setValue('room_name', 'Green Room');
        $room->save();

        $this->assertSame(array('entity_updating', 'room_updating', 'room_updated', 'entity_updated'), $log);

        /** @var EntityChangeSet $cs */
        $cs = $captured['cs'];
        $this->assertSame('Blue Room', $cs->getOldValue('room_name'), 'the update reports the persisted old value');
        $this->assertSame('Green Room', $cs->getNewValue('room_name'), 'and the new one');
        $this->assertSame(
            'Blue Room',
            $cs->getSnapshot()['room_name'] ?? null,
            'the snapshot holds the record as the database had it'
        );
    }

    /**
     * @testdox A -> B -> C in one save reports A -> C
     */
    public function testMultipleSetValueBeforeSaveReportsFirstToLast(): void
    {
        $captured = array();
        Hooks::addAction('room_updated', function (EntityChangeSet $cs) use (&$captured) { $captured[] = $cs; });
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'A');
        $room->save();
        $room->setValue('room_name', 'B');
        $room->setValue('room_name', 'C');
        $room->save();

        $this->assertCount(1, $captured);
        $this->assertSame('A', $captured[0]->getOldValue('room_name'));
        $this->assertSame('C', $captured[0]->getNewValue('room_name'));
    }

    /**
     * @testdox A -> B -> A dispatches nothing and saves nothing
     */
    public function testRoundTripValueDispatchesAndSavesNothing(): void
    {
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'A');
        $room->save();

        $captured = array();
        Hooks::addAction('room_updated', function (EntityChangeSet $cs) use (&$captured) { $captured[] = $cs; });
        $room->setValue('room_name', 'D');
        $room->setValue('room_name', 'A');
        $saved = $room->save();

        $this->assertSame(array(), $captured);
        $this->assertFalse($saved);
    }
}
