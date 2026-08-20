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
     * Test creating roles through the Role entity
     *
     * @testdox Roles can be created through the Role entity
     */
    public function testRoleCreation(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');

        $role = $fixture->createAndSaveRole('Test Role', $org['org_id'], 'A description');

        $this->assertGreaterThan(0, $role['rol_id']);

        // read the row back rather than trusting the returned array
        $roleData = $fixture->getRoleById($role['rol_id']);
        $this->assertNotEmpty($roleData);
        $this->assertEquals('Test Role', $roleData['rol_name']);
        $this->assertEquals('A description', $roleData['rol_description']);
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',
            $roleData['rol_uuid']
        );
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

        $role1 = $fixture->createAndSaveRole('Role 1', $org['org_id']);
        $role2 = $fixture->createAndSaveRole('Role 2', $org['org_id']);
        $role3 = $fixture->createAndSaveRole('Role 3', $org['org_id']);

        $ids = [$role1['rol_id'], $role2['rol_id'], $role3['rol_id']];
        $this->assertCount(3, array_unique($ids));

        // Verify correct names
        $this->assertEquals('Role 1', $fixture->getRoleById($role1['rol_id'])['rol_name']);
        $this->assertEquals('Role 2', $fixture->getRoleById($role2['rol_id'])['rol_name']);
        $this->assertEquals('Role 3', $fixture->getRoleById($role3['rol_id'])['rol_name']);
    }

    /**
     * Test roles are stored under the category they were created in
     *
     * @testdox A role is stored under the category it was created in
     */
    public function testRolesHaveCategories(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $cat = $fixture->createAndSaveCategory('Roles', 'ROL', $org['org_id']);

        $role = $fixture->createAndSaveRoleInCategory('Test Role', $cat['cat_id']);

        $roleData = $fixture->getRoleById($role['rol_id']);
        $this->assertEquals($cat['cat_id'], (int) $roleData['rol_cat_id']);

        // the category is a real row of type ROL, not a dangling id
        $catData = $fixture->getCategoryById((int) $roleData['rol_cat_id']);
        $this->assertNotEmpty($catData);
        $this->assertEquals('ROL', $catData['cat_type']);
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

        $role = $fixture->createAndSaveRole('Test Role', $org['org_id']);
        $roleId = $role['rol_id'];

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

        $names = [
            'Simple Role',
            'Role with Numbers 123',
            'Role (Special)',
            'Role / Slash',
        ];

        foreach ($names as $name) {
            $role = $fixture->createAndSaveRole($name, $org['org_id']);

            // the name has to survive the round trip through the database unchanged
            $this->assertEquals($name, $fixture->getRoleById($role['rol_id'])['rol_name']);
        }
    }
}
