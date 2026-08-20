<?php
/**
 * Object Rights Tests
 *
 * Tests the per-object rights of Admidio: RolesRights assigns roles to a single object, such as
 * one folder or one category, so that access can be restricted per record instead of per module.
 */

namespace Admidio\Tests\Integration\Permissions;

use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Session\Entity\Session;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;

class ObjectRightsTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The name of a role right that the installation seeds into adm_roles_rights.
     */
    private const RIGHT_NAME = 'folder_view';

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test that an object right applies only to the roles it was given to
     *
     * @testdox An object right applies only to the roles assigned to that object
     */
    public function testObjectRightAppliesOnlyToAssignedRoles(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Object Org', 'objorg');
        $allowed = $fixture->createAndSaveRoleWithRights('Allowed', $org['org_id']);
        $denied = $fixture->createAndSaveRoleWithRights('Denied', $org['org_id']);

        $objectId = 4711;
        $rights = new RolesRights($this->getDatabase(), self::RIGHT_NAME, $objectId);
        $rights->saveRoles([$allowed['rol_id']]);

        // read the object back, the assignment has to be persisted
        $stored = new RolesRights($this->getDatabase(), self::RIGHT_NAME, $objectId);
        $this->assertEquals([$allowed['rol_id']], $stored->getRolesIds());

        $this->assertTrue($stored->hasRight([$allowed['rol_id']]));
        $this->assertFalse($stored->hasRight([$denied['rol_id']]));
    }

    /**
     * Test that a user without any role has no object right
     *
     * @testdox A user without roles has no object right
     */
    public function testUserWithoutRolesHasNoObjectRight(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Object Org', 'objorg');
        $role = $fixture->createAndSaveRoleWithRights('Allowed', $org['org_id']);

        $rights = new RolesRights($this->getDatabase(), self::RIGHT_NAME, 4711);
        $rights->saveRoles([$role['rol_id']]);

        // an empty list of assigned roles never matches
        $this->assertFalse($rights->hasRight([]));
    }

    /**
     * Test that the rights are kept per object
     *
     * @testdox Object rights are stored separately for each object
     */
    public function testObjectRightsAreStoredPerObject(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Object Org', 'objorg');
        $role = $fixture->createAndSaveRoleWithRights('Allowed', $org['org_id']);

        $rights = new RolesRights($this->getDatabase(), self::RIGHT_NAME, 100);
        $rights->saveRoles([$role['rol_id']]);

        // the very same right name on a different object is untouched
        $otherObject = new RolesRights($this->getDatabase(), self::RIGHT_NAME, 200);
        $this->assertEquals([], $otherObject->getRolesIds());
        $this->assertFalse($otherObject->hasRight([$role['rol_id']]));
    }

    /**
     * Test that the right of a different right name is independent
     *
     * @testdox Object rights of different right names are independent
     */
    public function testRightNamesAreIndependent(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Object Org', 'objorg');
        $role = $fixture->createAndSaveRoleWithRights('Allowed', $org['org_id']);

        $objectId = 4711;
        $view = new RolesRights($this->getDatabase(), 'folder_view', $objectId);
        $view->saveRoles([$role['rol_id']]);

        // the same object may be viewable without being uploadable
        $upload = new RolesRights($this->getDatabase(), 'folder_upload', $objectId);
        $this->assertEquals([], $upload->getRolesIds());
        $this->assertFalse($upload->hasRight([$role['rol_id']]));
    }

    /**
     * Test that saving a new set of roles replaces the old one
     *
     * @testdox Saving a new set of roles replaces the previous assignment
     */
    public function testSavingRolesReplacesThePreviousAssignment(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Object Org', 'objorg');
        $first = $fixture->createAndSaveRoleWithRights('First', $org['org_id']);
        $second = $fixture->createAndSaveRoleWithRights('Second', $org['org_id']);

        $objectId = 4711;
        $rights = new RolesRights($this->getDatabase(), self::RIGHT_NAME, $objectId);
        $rights->saveRoles([$first['rol_id']]);

        $rights = new RolesRights($this->getDatabase(), self::RIGHT_NAME, $objectId);
        $rights->saveRoles([$second['rol_id']]);

        $stored = new RolesRights($this->getDatabase(), self::RIGHT_NAME, $objectId);
        $this->assertEquals([$second['rol_id']], $stored->getRolesIds());
        $this->assertFalse($stored->hasRight([$first['rol_id']]));
        $this->assertTrue($stored->hasRight([$second['rol_id']]));
    }

    /**
     * Test that removing a role revokes the right
     *
     * @testdox Removing a role revokes its object right
     */
    public function testRemovingARoleRevokesTheRight(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Object Org', 'objorg');
        $role = $fixture->createAndSaveRoleWithRights('Allowed', $org['org_id']);

        $objectId = 4711;
        $rights = new RolesRights($this->getDatabase(), self::RIGHT_NAME, $objectId);
        $rights->saveRoles([$role['rol_id']]);
        $this->assertTrue($rights->hasRight([$role['rol_id']]));

        $rights->removeRoles([$role['rol_id']]);

        $stored = new RolesRights($this->getDatabase(), self::RIGHT_NAME, $objectId);
        $this->assertEquals([], $stored->getRolesIds());
        $this->assertFalse($stored->hasRight([$role['rol_id']]));
    }

    /**
     * Test that the names of the assigned roles can be read
     *
     * @testdox The names of the roles of an object right can be read
     */
    public function testRoleNamesOfAnObjectRightCanBeRead(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Object Org', 'objorg');
        $role = $fixture->createAndSaveRoleWithRights('Board', $org['org_id']);

        $rights = new RolesRights($this->getDatabase(), self::RIGHT_NAME, 4711);
        $rights->saveRoles([$role['rol_id']]);

        $stored = new RolesRights($this->getDatabase(), self::RIGHT_NAME, 4711);
        $this->assertEquals(['Board'], $stored->getRolesNames());
    }

    /**
     * Test that a rights change reaches the running sessions.
     * Role::save() asks the current session to flag all sessions for reload, so that a user whose
     * rights changed does not keep working with the permissions cached in their session.
     *
     * @testdox Changing a role flags the running sessions for reload
     */
    public function testChangingARoleFlagsSessionsForReload(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();
        $org = $fixture->createAndSaveOrganization('Reload Org', 'reload');
        $role = $fixture->createAndSaveRoleWithRights('Reload Role', $org['org_id']);

        $session = new Session($db, COOKIE_PREFIX);
        $session->save();
        $sessionId = $session->getValue('ses_session_id');

        $db->queryPrepared(
            'UPDATE ' . TBL_SESSIONS . ' SET ses_reload = false WHERE ses_session_id = ?',
            [$sessionId]
        );

        $readReloadFlag = static function () use ($db, $sessionId) {
            $sql = 'SELECT ses_reload FROM ' . TBL_SESSIONS . ' WHERE ses_session_id = ?';

            return (bool) $db->queryPrepared($sql, [$sessionId])->fetch()['ses_reload'];
        };
        $this->assertFalse($readReloadFlag());

        $previousSession = $GLOBALS['gCurrentSession'] ?? null;
        $GLOBALS['gCurrentSession'] = $session;

        try {
            $entity = new Role($db, $role['rol_id']);
            $entity->saveChangesWithoutRights();
            $entity->setValue('rol_weblinks', 1);
            $entity->save();
        } finally {
            $GLOBALS['gCurrentSession'] = $previousSession;
        }

        $this->assertTrue($readReloadFlag());
    }
}
