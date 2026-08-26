<?php
/**
 * Deletion, redaction, the opt-out, selective dispatch and the value filters.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/entities.php';

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Hooks\ValueObject\EntityFieldChange;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Tests\Hooks\FakeDatabase;
use Admidio\Tests\Hooks\TestClient;
use Admidio\Tests\Hooks\TestRoom;
use Admidio\Tests\Hooks\TestSession;

function newDatabase(): FakeDatabase
{
    $db = new FakeDatabase();
    $db->createTable(TABLE_PREFIX . '_rooms', columnDefinition('room'));
    $db->createTable(TABLE_PREFIX . '_clients', columnDefinition('ocl'));
    $db->createTable(TABLE_PREFIX . '_sessions', columnDefinition('ses'));
    return $db;
}

// ---------------------------------------------------------------- delete
Hooks::reset();
$log = array();
$captured = array();
foreach (array('entity_deleting', 'room_deleting', 'room_deleted', 'entity_deleted') as $name) {
    Hooks::addAction($name, function (EntityChangeSet $cs) use (&$log, &$captured, $name) {
        $log[] = $name;
        $captured[$name] = $cs;
    });
}
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'Blue Room');
$room->save();
$uuid = $room->getValue('room_uuid');
$room->delete();
check('a deletion dispatches the delete stages in the nesting order',
    $log === array('entity_deleting', 'room_deleting', 'room_deleted', 'entity_deleted'), implode(' ', $log));
$cs = $captured['entity_deleted'];
check('the post-delete change set survives clear()', $cs->isDelete() && $cs->getUuid() === $uuid);
check('it carries the deleted record in its snapshot', ($cs->getSnapshot()['room_name'] ?? null) === 'Blue Room',
    var_export($cs->getSnapshot()['room_name'] ?? null, true));
check('and reports the columns as old value to null', $cs->getOldValue('room_name') === 'Blue Room' && $cs->getNewValue('room_name') === null);
check('the object itself was cleared', $room->getValue('room_name') === '');
check('the row is gone', count($db->fetchAll(TABLE_PREFIX . '_rooms')) === 0);

// ---------------------------------------------------------------- redaction
Hooks::reset();
$captured = null;
Hooks::addAction('oidc_client_created', function (EntityChangeSet $cs) use (&$captured) { $captured = $cs; });
$db = newDatabase();
$client = new TestClient($db);
$client->setValue('ocl_name', 'My App');
$client->setValue('ocl_secret', 'hunter2');
$client->save();
check('a redacted column is reported as changed', $captured->hasChanged('ocl_secret'));
check('but its value is withheld', $captured->getNewValue('ocl_secret') === EntityChangeSet::REDACTED_VALUE,
    var_export($captured->getNewValue('ocl_secret'), true));
check('and it is marked redacted', $captured->getChange('ocl_secret')->isRedacted());
check('an ordinary column is untouched', $captured->getNewValue('ocl_name') === 'My App');
check('the secret was still written to the database', $db->fetchAll(TABLE_PREFIX . '_clients')[0]['ocl_secret'] === 'hunter2');

$captured = null;
Hooks::reset();
Hooks::addAction('oidc_client_deleted', function (EntityChangeSet $cs) use (&$captured) { $captured = $cs; });
$client->delete();
check('a redacted column is left out of the snapshot', !array_key_exists('ocl_secret', $captured->getSnapshot()));

// ---------------------------------------------------------------- the opt-out
Hooks::reset();
$called = 0;
foreach (array('entity_created', 'entity_updated', 'entity_deleted', 'ses_created') as $name) {
    Hooks::addAction($name, function () use (&$called) { $called++; });
}
$db = newDatabase();
$session = new TestSession($db);
$session->setValue('ses_name', 'a session');
$session->save();
$session->delete();
check('an entity without a hook ID dispatches nothing, the generic hooks included', $called === 0, $called . ' calls');
check('it is still written normally', count($db->statements) > 0);

// ---------------------------------------------------------------- selectivity
Hooks::reset();
$roomEvents = 0;
$clientEvents = 0;
Hooks::addAction('room_updated', function () use (&$roomEvents) { $roomEvents++; });
Hooks::addAction('oidc_client_updated', function () use (&$clientEvents) { $clientEvents++; });
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'One');
$room->save();
$room->setValue('room_name', 'Two');
$room->save();
check('a listener on oidc_client_updated is not called for a room update', $clientEvents === 0 && $roomEvents === 1,
    "room=$roomEvents client=$clientEvents");

// ---------------------------------------------------------------- global suppression
Hooks::reset();
$called = 0;
Hooks::addAction('entity_created', function () use (&$called) { $called++; });
Entity::setHooksEnabled(false);
$db = newDatabase();
$room = new TestRoom($db);
$room->setValue('room_name', 'During the update');
$room->save();
Entity::setHooksEnabled(true);
check('setHooksEnabled(false) silences the lifecycle', $called === 0, $called . ' calls');

echo "\n";
exit(testSummary());
