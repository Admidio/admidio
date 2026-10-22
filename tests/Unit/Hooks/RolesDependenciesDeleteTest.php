<?php
/**
 * RolesDependencies::delete() overrides the base Entity::delete() because the table has a composite
 * key instead of a single auto-increment column - and until finding 96 it never dispatched its own
 * hooks at all. Executed against the real class over FakeDatabase, not a stand-in: its constructor
 * takes nothing but a Database.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Roles\Entity\RolesDependencies;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;

class RolesDependenciesDeleteTest extends EntityHookTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!defined('TBL_ROLE_DEPENDENCIES')) {
            define('TBL_ROLE_DEPENDENCIES', TABLE_PREFIX . '_role_dependencies');
        }
    }

    private function roleDependencyColumns(): array
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

    private function newDatabase(): FakeDatabase
    {
        $db = new FakeDatabase();
        $db->createTable(TBL_ROLE_DEPENDENCIES, $this->roleDependencyColumns());
        return $db;
    }

    public function testDeleteDispatchesBothSpecificAndGenericHooksInNestingOrder(): void
    {
        $db = $this->newDatabase();
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

        $this->assertSame(
            array('entity_deleting', 'role_dependency_deleting', 'role_dependency_deleted', 'entity_deleted'),
            $events
        );
        $this->assertSame(array(), $db->fetchAll(TBL_ROLE_DEPENDENCIES), 'the row is really gone');
    }

    public function testChangeSetNamesTheOldRecord(): void
    {
        $db = $this->newDatabase();
        $dependency = new RolesDependencies($db);
        $dependency->setValue('rld_rol_id_parent', 3);
        $dependency->setValue('rld_rol_id_child', 7);
        $dependency->save();

        $captured = null;
        Hooks::addAction('role_dependency_deleted', function (EntityChangeSet $cs) use (&$captured) {
            $captured = $cs;
        });
        $dependency->delete();

        $this->assertTrue($captured->isDelete());
        $this->assertSame('role_dependency', $captured->getHookId());
        $this->assertSame('3', $captured->getSnapshot()['rld_rol_id_parent'] ?? null, 'the parent id is in the snapshot');
        $this->assertSame('7', $captured->getSnapshot()['rld_rol_id_child'] ?? null, 'and the child id');
    }

    public function testWithoutAListenerHookBulkDeletionReadsNothingExtra(): void
    {
        $db = $this->newDatabase();
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
        $this->assertSame(0, $selects);
    }
}
