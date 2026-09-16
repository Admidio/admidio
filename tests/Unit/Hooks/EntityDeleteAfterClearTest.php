<?php
/**
 * ProfileFields::saveUserData() clears a profile field by calling setValue('usd_value', '') and then,
 * seeing the record is now empty, deletes it instead of saving it - the same shape as any "delete the
 * dependent row once its value is empty" pattern. setValue() on a nullable column already turns '' into
 * null and overwrites dbColumns with it, so by the time delete() builds the deletion change set, the
 * true persisted value survives only in columnsInfos[...]['previousValue'], not in dbColumns.
 * Entity::buildDeletionChangeSet() has to read it from there, or the deletion is reported as
 * null -> null and disappears from anything that compares old and new (ChangeNotification's
 * recordChange() among them - this is what made a cleared profile field vanish from the change mail).
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use Admidio\Tests\Unit\Hooks\Support\TestRoom;

class EntityDeleteAfterClearTest extends EntityHookTestCase
{
    private function newDatabase(): FakeDatabase
    {
        $db = new FakeDatabase();
        $db->createTable(TABLE_PREFIX . '_rooms', FakeDatabase::columnDefinition('room'));
        return $db;
    }

    public function testClearingTheValueBeforeDeleteStillReportsIt(): void
    {
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Blue Room');
        $room->setValue('room_secret', '1010');
        $room->save();

        $captured = null;
        Hooks::addAction('room_deleted', function (EntityChangeSet $cs) use (&$captured) {
            $captured = $cs;
        });

        // exactly what ProfileFields::saveUserData() does: clear the value, then delete the now-empty record
        $room->setValue('room_secret', '');
        $room->delete();

        $this->assertSame(
            '1010',
            $captured->getOldValue('room_secret'),
            'the deletion reports the value the database held, not the value it was just cleared to'
        );
        $this->assertNull($captured->getNewValue('room_secret'), 'the new value is still null, as any deletion reports');
        $this->assertSame(
            '1010',
            $captured->getSnapshot()['room_secret'] ?? null,
            'the snapshot also carries the true persisted value'
        );
    }

    public function testAnUntouchedColumnIsStillReadFromDbColumns(): void
    {
        $db = $this->newDatabase();
        $room = new TestRoom($db);
        $room->setValue('room_name', 'Blue Room');
        $room->setValue('room_secret', '1010');
        $room->save();

        $captured = null;
        Hooks::addAction('room_deleted', function (EntityChangeSet $cs) use (&$captured) {
            $captured = $cs;
        });
        // nothing was set() beforehand this time
        $room->delete();

        $this->assertSame('Blue Room', $captured->getOldValue('room_name'));
    }
}
