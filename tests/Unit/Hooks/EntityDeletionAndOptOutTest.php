<?php
/**
 * Deletion, redaction of sensitive columns, the opt-out of an entity without a hook ID, selective
 * dispatch between two entity types and the global setHooksEnabled(false) switch.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use Admidio\Tests\Unit\Hooks\Support\TestClient;
use Admidio\Tests\Unit\Hooks\Support\TestRoom;
use Admidio\Tests\Unit\Hooks\Support\TestSession;

class EntityDeletionAndOptOutTest extends EntityHookTestCase
{
    protected function tearDown(): void
    {
        Entity::setHooksEnabled(true);
        parent::tearDown();
    }

    private function newDatabase(): FakeDatabase
    {
        $db = new FakeDatabase();
        $db->createTable(TABLE_PREFIX . '_rooms', FakeDatabase::columnDefinition('room'));
        $db->createTable(TABLE_PREFIX . '_clients', FakeDatabase::columnDefinition('ocl'));
        $db->createTable(TABLE_PREFIX . '_sessions', FakeDatabase::columnDefinition('ses'));
        return $db;
    }

    public function testDeleteDispatchesNestingOrderAndReportsTheDeletedRecord(): void
    {
        $log = array();
        $captured = array();
        foreach (array('entity_deleting', 'room_deleting', 'room_deleted', 'entity_deleted') as $name) {
            Hooks::addAction($name, function (EntityChangeSet $cs) use (&$log, &$captured, $name) {
                $log[] = $name;
                $captured[$name] = $cs;
            });
        }
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Blue Room');
        $room->save();
        $uuid = $room->getValue('room_uuid');
        $room->delete();

        $this->assertSame(array('entity_deleting', 'room_deleting', 'room_deleted', 'entity_deleted'), $log);

        /** @var EntityChangeSet $cs */
        $cs = $captured['entity_deleted'];
        $this->assertTrue($cs->isDelete());
        $this->assertSame($uuid, $cs->getUuid(), 'the post-delete change set survives clear()');
        $this->assertSame(
            'Blue Room',
            $cs->getSnapshot()['room_name'] ?? null,
            'it carries the deleted record in its snapshot'
        );
        $this->assertSame('Blue Room', $cs->getOldValue('room_name'));
        $this->assertNull($cs->getNewValue('room_name'), 'and reports the columns as old value to null');
        $this->assertSame('', $room->getValue('room_name'), 'the object itself was cleared');
        $this->assertCount(0, $db->fetchAll(TABLE_PREFIX . '_rooms'), 'the row is gone');
    }

    public function testRedactedColumnIsReportedChangedButWithheld(): void
    {
        $captured = null;
        Hooks::addAction('oidc_client_created', function (EntityChangeSet $cs) use (&$captured) { $captured = $cs; });
        $db = $this->newDatabase();
        $client = new TestClient($db);
        $client->setValue('ocl_name', 'My App');
        $client->setValue('ocl_secret', 'hunter2');
        $client->save();

        $this->assertTrue($captured->hasChanged('ocl_secret'), 'a redacted column is reported as changed');
        $this->assertSame(
            EntityChangeSet::REDACTED_VALUE,
            $captured->getNewValue('ocl_secret'),
            'but its value is withheld'
        );
        $this->assertTrue($captured->getChange('ocl_secret')->isRedacted());
        $this->assertSame('My App', $captured->getNewValue('ocl_name'), 'an ordinary column is untouched');
        $this->assertSame(
            'hunter2',
            $db->fetchAll(TABLE_PREFIX . '_clients')[0]['ocl_secret'],
            'the secret was still written to the database'
        );

        $captured = null;
        Hooks::reset();
        Hooks::addAction('oidc_client_deleted', function (EntityChangeSet $cs) use (&$captured) { $captured = $cs; });
        $client->delete();

        $this->assertFalse(
            array_key_exists('ocl_secret', $captured->getSnapshot()),
            'a redacted column is left out of the snapshot'
        );
    }

    public function testEntityWithoutHookIdDispatchesNothing(): void
    {
        $called = 0;
        foreach (array('entity_created', 'entity_updated', 'entity_deleted', 'ses_created') as $name) {
            Hooks::addAction($name, function () use (&$called) { $called++; });
        }
        $db = $this->newDatabase();
        $session = new TestSession($db);
        $session->setValue('ses_name', 'a session');
        $session->save();
        $session->delete();

        $this->assertSame(0, $called, 'an entity without a hook ID dispatches nothing, the generic hooks included');
        $this->assertGreaterThan(0, count($db->statements), 'it is still written normally');
    }

    public function testListenerOnOneEntityIsNotCalledForAnother(): void
    {
        $roomEvents = 0;
        $clientEvents = 0;
        Hooks::addAction('room_updated', function () use (&$roomEvents) { $roomEvents++; });
        Hooks::addAction('oidc_client_updated', function () use (&$clientEvents) { $clientEvents++; });
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'One');
        $room->save();
        $room->setValue('room_name', 'Two');
        $room->save();

        $this->assertSame(0, $clientEvents, 'a listener on oidc_client_updated is not called for a room update');
        $this->assertSame(1, $roomEvents);
    }

    public function testSetHooksEnabledFalseSilencesTheLifecycle(): void
    {
        $called = 0;
        Hooks::addAction('entity_created', function () use (&$called) { $called++; });
        Entity::setHooksEnabled(false);
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'During the update');
        $room->save();
        Entity::setHooksEnabled(true);

        $this->assertSame(0, $called, 'setHooksEnabled(false) silences the lifecycle');
    }
}
