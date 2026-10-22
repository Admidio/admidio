<?php
/**
 * The committed hooks: they fire at the outermost commit, they describe what the transaction did as
 * a whole, and they do not fire at all when the transaction is lost.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\Service\EntityHookQueue;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use Admidio\Tests\Unit\Hooks\Support\TestRoom;

class EntityCommittedHooksTest extends EntityHookTestCase
{
    private function newDatabase(): FakeDatabase
    {
        $db = new FakeDatabase();
        $db->createTable(TABLE_PREFIX . '_rooms', FakeDatabase::columnDefinition('room'));
        $db->createTable(TABLE_PREFIX . '_clients', FakeDatabase::columnDefinition('ocl'));
        $db->createTable(TABLE_PREFIX . '_sessions', FakeDatabase::columnDefinition('ses'));
        return $db;
    }

    /** Collect every committed and failed event of a room. */
    private function watch(array &$events): void
    {
        foreach (array('created', 'updated', 'deleted', 'create_failed', 'update_failed', 'delete_failed') as $stage) {
            Hooks::addAction('room_' . $stage, function (EntityChangeSet $cs) use (&$events, $stage) {
                $events[] = array('stage' => $stage, 'changeSet' => $cs);
            });
        }
    }

    private function stages(array $events): string
    {
        return implode(' ', array_column($events, 'stage'));
    }

    public function testNothingFiresBeforeTheCommitButTheOperationIsWaiting(): void
    {
        $db = $this->newDatabase();
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Blue Room');
        $room->save();

        $this->assertSame(array(), $events, 'nothing is dispatched while the transaction is open');
        $this->assertSame(1, EntityHookQueue::countPending(), 'but the operation is waiting');

        $db->endTransaction();
        $this->assertSame('created', $this->stages($events), 'the commit dispatches it');
    }

    public function testNestedTransactionsOnlyDispatchOnTheOutermostEnd(): void
    {
        $db = $this->newDatabase();
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $db->startTransaction();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Blue Room');
        $room->save();
        $db->endTransaction();
        $this->assertSame(array(), $events, 'the inner end does not dispatch');

        $db->endTransaction();
        $this->assertSame('created', $this->stages($events), 'the outermost end does');
    }

    public function testCreateThenUpdateInOneTransactionIsOneCreation(): void
    {
        $db = $this->newDatabase();
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Blue Room');
        $room->save();
        $room->setValue('room_name', 'Green Room');
        $room->save();
        $db->endTransaction();

        $this->assertSame('created', $this->stages($events));
        $this->assertSame('Green Room', $events[0]['changeSet']->getNewValue('room_name'), 'with the final values');
    }

    public function testTwoUpdatesInOneTransactionAreOneUpdate(): void
    {
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'A');
        $room->save();
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $room->setValue('room_name', 'B');
        $room->save();
        $room->setValue('room_name', 'C');
        $room->save();
        $db->endTransaction();

        $this->assertSame('updated', $this->stages($events));
        $this->assertSame('A', $events[0]['changeSet']->getOldValue('room_name'), 'from the value before the transaction');
        $this->assertSame('C', $events[0]['changeSet']->getNewValue('room_name'), 'to the value after it');
    }

    public function testARoundTripInOneTransactionDispatchesNothing(): void
    {
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'A');
        $room->save();
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $room->setValue('room_name', 'B');
        $room->save();
        $room->setValue('room_name', 'A');
        $room->save();
        $db->endTransaction();

        $this->assertSame(array(), $events, 'a value that ends where it started dispatches nothing');
    }

    public function testUpdateThenDeleteInOneTransactionIsOneDeletion(): void
    {
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'A');
        $room->save();
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $room->setValue('room_name', 'B');
        $room->save();
        $room->delete();
        $db->endTransaction();

        $this->assertSame('deleted', $this->stages($events));
        $this->assertSame(
            'A',
            $events[0]['changeSet']->getOldValue('room_name'),
            'measured against the state at the start of the transaction'
        );
    }

    public function testCreateThenDeleteInOneTransactionNeverHappened(): void
    {
        $db = $this->newDatabase();
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Ephemeral');
        $room->save();
        $room->delete();
        $db->endTransaction();

        $this->assertSame(array(), $events, 'a record created and deleted in one transaction never happened');
    }

    public function testTwoObjectsOfOneRowAreOneRecord(): void
    {
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'A');
        $room->save();
        $id = (int)$room->getValue('room_id');
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $first = new TestRoom($db, $id);
        $first->setValue('room_name', 'B');
        $first->save();
        $second = new TestRoom($db, $id);
        $second->setValue('room_name', 'C');
        $second->save();
        $db->endTransaction();

        $this->assertSame('updated', $this->stages($events));
        $this->assertSame('A', $events[0]['changeSet']->getOldValue('room_name'), 'the merge spans both of them');
        $this->assertSame('C', $events[0]['changeSet']->getNewValue('room_name'));
    }

    public function testRollbackDispatchesFailureNotSuccess(): void
    {
        $db = $this->newDatabase();
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Never committed');
        $room->save();
        $db->rollback();

        $this->assertSame('create_failed', $this->stages($events), 'a rollback dispatches the failure and not the success');
        $this->assertSame(0, EntityHookQueue::countPending(), 'and nothing is left waiting');
        $this->assertCount(0, $db->fetchAll(TABLE_PREFIX . '_rooms'), 'and the row really is gone');
    }

    public function testALostTransactionDispatchesTheFailure(): void
    {
        $db = $this->newDatabase();
        $events = array();
        $this->watch($events);
        $db->startTransaction();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Abandoned');
        $room->save();
        $db->runAfterRollbackCallbacks();

        $this->assertSame('create_failed', $this->stages($events));
    }

    public function testWithoutATransactionTheStatementIsTheCommit(): void
    {
        $db = $this->newDatabase();
        $events = array();
        $this->watch($events);
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Immediate');
        $room->save();

        $this->assertSame('created', $this->stages($events));
        $this->assertSame(0, EntityHookQueue::countPending());
    }
}
