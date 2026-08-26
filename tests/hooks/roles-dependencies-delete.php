<?php
/**
 * RolesDependencies::delete() overrides the base Entity::delete() because the table has a composite
 * key instead of a single auto-increment column - and until finding 96 it never dispatched its own
 * hooks at all. Executed against the real class over SQLite, not a stand-in: its constructor takes
 * nothing but a Database.
 */
require __DIR__ . '/bootstrap.php';

define('TBL_ROLE_DEPENDENCIES', TABLE_PREFIX . '_role_dependencies');

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Roles\Entity\RolesDependencies;
use Admidio\Tests\Hooks\FakeDatabase;

function roleDependencyColumns(): array
{
    $text = fn() => array('type' => 'text', 'null' => true, 'key' => false, 'serial' => false, 'default' => null);
    return array(
        'rld_rol_id_parent' => $text(),
        'rld_rol_id_child' => $text(),
        'rld_comment' => $text(),
        'rld_usr_id' => $text(),
        'rld_timestamp' => $text(),
    );
}

function newDatabase(): FakeDatabase
{
    $db = new FakeDatabase();
    $db->createTable(TBL_ROLE_DEPENDENCIES, roleDependencyColumns());
    return $db;
}

// ------------------------------------------------------------------------ delete dispatches its hooks
Hooks::reset();
$db = newDatabase();
$dependency = new RolesDependencies($db);
$dependency->setValue('rld_rol_id_parent', 3);
$dependency->setValue('rld_rol_id_child', 7);
$dependency->save();

$events = array();
foreach (array('role_dependency_deleting', 'role_dependency_deleted', 'entity_deleting', 'entity_deleted') as $name) {
    Hooks::addAction($name, function (EntityChangeSet $cs) use (&$events, $name) {
        $events[] = $name;
    });
}
$dependency->delete();

check(
    'both the specific and the generic hooks fire, generic-then-specific before and after the delete',
    $events === array('entity_deleting', 'role_dependency_deleting', 'role_dependency_deleted', 'entity_deleted'),
    implode(' ', $events)
);
check('the row is really gone', $db->fetchAll(TBL_ROLE_DEPENDENCIES) === array());

// ---------------------------------------------------------------- the change set names the old record
Hooks::reset();
$db = newDatabase();
$dependency = new RolesDependencies($db);
$dependency->setValue('rld_rol_id_parent', 3);
$dependency->setValue('rld_rol_id_child', 7);
$dependency->save();

$captured = null;
Hooks::addAction('role_dependency_deleted', function (EntityChangeSet $cs) use (&$captured) {
    $captured = $cs;
});
$dependency->delete();

check('the operation is a deletion', $captured->isDelete() && $captured->getHookId() === 'role_dependency');
check(
    'the parent and child ids are in the snapshot',
    ($captured->getSnapshot()['rld_rol_id_parent'] ?? null) === '3' && ($captured->getSnapshot()['rld_rol_id_child'] ?? null) === '7',
    var_export($captured->getSnapshot(), true)
);

// ------------------------------------------------------- without a listener, nothing extra is read
Hooks::reset();
$db = newDatabase();
$dependency = new RolesDependencies($db);
$dependency->setValue('rld_rol_id_parent', 1);
$dependency->setValue('rld_rol_id_child', 2);
$dependency->save();

$before = count($db->statements);
$dependency->delete();
$selects = 0;
foreach (array_slice($db->statements, $before) as $statement) {
    if (str_starts_with(ltrim($statement), 'SELECT * FROM ' . TBL_ROLE_DEPENDENCIES)) {
        $selects++;
    }
}
check('without a listener hookBulkDeletion() reads nothing', $selects === 0, (string) $selects);

echo "\n";
exit(testSummary());
