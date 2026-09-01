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
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/entities.php';

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Tests\Hooks\FakeDatabase;
use Admidio\Tests\Hooks\TestRoom;

function newDatabase(): FakeDatabase
{
    $db = new FakeDatabase();
    $db->createTable(TABLE_PREFIX . '_rooms', columnDefinition('room'));
    return $db;
}

// ------------------------------------------- clearing the value before delete() still reports it
Hooks::reset();
$db = newDatabase();
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

check(
    'the deletion reports the value the database held, not the value it was just cleared to',
    $captured->getOldValue('room_secret') === '1010',
    var_export($captured->getOldValue('room_secret'), true)
);
check('the new value is still null, as any deletion reports', $captured->getNewValue('room_secret') === null);
check(
    'the snapshot also carries the true persisted value',
    ($captured->getSnapshot()['room_secret'] ?? null) === '1010',
    var_export($captured->getSnapshot()['room_secret'] ?? null, true)
);

// ------------------------------------------------- an untouched column is still read from dbColumns
Hooks::reset();
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->setValue('room_secret', '1010');
$room->save();

$captured = null;
Hooks::addAction('room_deleted', function (EntityChangeSet $cs) use (&$captured) {
    $captured = $cs;
});
$room->delete(); // nothing was set() beforehand this time

check(
    'a column nobody touched before delete() is still reported correctly',
    $captured->getOldValue('room_name') === 'Blue Room',
    var_export($captured->getOldValue('room_name'), true)
);

echo "\n";
exit(testSummary());
