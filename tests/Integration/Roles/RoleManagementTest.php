<?php
/**
 * Role Management Tests
 *
 * Tests role creation and basic role entity operations without complex permissions.
 */

namespace Admidio\Tests\Integration\Roles;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;

class RoleManagementTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test creating roles using direct SQL (avoiding permission checks)
     *
     * @testdox Roles can be created in the database
     */
    public function testRoleCreation(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $cat = $fixture->createAndSaveCategory('Roles', 'ROL', $org['org_id']);

        // Create role using direct SQL to bypass permission checks
        $sql = 'INSERT INTO ' . TBL_ROLES . ' (rol_name, rol_cat_id, rol_uuid) VALUES (?, ?, UUID())';
        $this->getDatabase()->queryPrepared($sql, ['Test Role', $cat['cat_id']]);
        $roleId = (int) $this->getDatabase()->lastInsertId();

        // Verify role exists
        $this->assertGreaterThan(0, $roleId);

        $roleData = $fixture->getRoleById($roleId);
        $this->assertNotEmpty($roleData);
        $this->assertEquals('Test Role', $roleData['rol_name']);
    }

    /**
     * Test multiple roles can exist
     *
     * @testdox Multiple roles can be created independently
     */
    public function testMultipleRoles(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $cat = $fixture->createAndSaveCategory('Roles', 'ROL', $org['org_id']);

        $sql = 'INSERT INTO ' . TBL_ROLES . ' (rol_name, rol_cat_id, rol_uuid) VALUES (?, ?, UUID())';

        $this->getDatabase()->queryPrepared($sql, ['Role 1', $cat['cat_id']]);
        $role1Id = (int) $this->getDatabase()->lastInsertId();

        $this->getDatabase()->queryPrepared($sql, ['Role 2', $cat['cat_id']]);
        $role2Id = (int) $this->getDatabase()->lastInsertId();

        $this->getDatabase()->queryPrepared($sql, ['Role 3', $cat['cat_id']]);
        $role3Id = (int) $this->getDatabase()->lastInsertId();

        // Verify all exist uniquely
        $this->assertNotEquals($role1Id, $role2Id);
        $this->assertNotEquals($role2Id, $role3Id);

        // Verify correct names
        $this->assertEquals('Role 1', $fixture->getRoleById($role1Id)['rol_name']);
        $this->assertEquals('Role 2', $fixture->getRoleById($role2Id)['rol_name']);
        $this->assertEquals('Role 3', $fixture->getRoleById($role3Id)['rol_name']);
    }

    /**
     * Test roles belong to categories
     *
     * @testdox Each role must belong to a category
     */
    public function testRolesHaveCategories(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $cat = $fixture->createAndSaveCategory('Roles', 'ROL', $org['org_id']);

        $sql = 'INSERT INTO ' . TBL_ROLES . ' (rol_name, rol_cat_id, rol_uuid) VALUES (?, ?, UUID())';
        $this->getDatabase()->queryPrepared($sql, ['Test Role', $cat['cat_id']]);
        $roleId = (int) $this->getDatabase()->lastInsertId();

        // Verify role has correct category
        $roleData = $fixture->getRoleById($roleId);
        $this->assertEquals($cat['cat_id'], $roleData['rol_cat_id']);
    }

    /**
     * Test role membership management
     *
     * @testdox Role membership count can be tracked
     */
    public function testRoleMembershipCounting(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $user1 = $fixture->createAndSaveUser('user1', 'user1@example.local');
        $user2 = $fixture->createAndSaveUser('user2', 'user2@example.local');
        $user3 = $fixture->createAndSaveUser('user3', 'user3@example.local');

        $cat = $fixture->createAndSaveCategory('Roles', 'ROL', $org['org_id']);
        $sql = 'INSERT INTO ' . TBL_ROLES . ' (rol_name, rol_cat_id, rol_uuid) VALUES (?, ?, UUID())';
        $this->getDatabase()->queryPrepared($sql, ['Test Role', $cat['cat_id']]);
        $roleId = (int) $this->getDatabase()->lastInsertId();

        $this->assertGreaterThan(0, $roleId);

        // Initially no members
        $this->assertEquals(0, $fixture->countRoleMemberships($roleId));

        // Add members
        $mem1 = $fixture->assignUserToRole($user1['usr_id'], $roleId);
        $this->assertNotEmpty($mem1['mem_id']);
        $this->assertEquals(1, $fixture->countRoleMemberships($roleId));

        $mem2 = $fixture->assignUserToRole($user2['usr_id'], $roleId);
        $this->assertNotEmpty($mem2['mem_id']);
        $this->assertEquals(2, $fixture->countRoleMemberships($roleId));

        $mem3 = $fixture->assignUserToRole($user3['usr_id'], $roleId);
        $this->assertNotEmpty($mem3['mem_id']);
        $this->assertEquals(3, $fixture->countRoleMemberships($roleId));
    }

    /**
     * Test role names are flexible
     *
     * @testdox Role names support various characters
     */
    public function testRoleNameFormats(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $cat = $fixture->createAndSaveCategory('Roles', 'ROL', $org['org_id']);

        $names = [
            'Simple Role',
            'Role with Numbers 123',
            'Role (Special)',
            'Role & More',
        ];

        $sql = 'INSERT INTO ' . TBL_ROLES . ' (rol_name, rol_cat_id, rol_uuid) VALUES (?, ?, UUID())';

        foreach ($names as $name) {
            $this->getDatabase()->queryPrepared($sql, [$name, $cat['cat_id']]);
            $roleId = (int) $this->getDatabase()->lastInsertId();

            $roleData = $fixture->getRoleById($roleId);
            $this->assertEquals($name, $roleData['rol_name']);
        }
    }
}
