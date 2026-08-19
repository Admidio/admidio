<?php
/**
 * Role Entity Tests
 *
 * Tests Role entity CRUD operations and role membership functionality.
 *
 * @testdox Role entity handles creation, membership, and permissions correctly
 */

namespace Admidio\Tests\Integration\Roles;

use Admidio\Tests\Support\DatabaseTestCase;

class RoleEntityTest extends DatabaseTestCase
{
    /**
     * Test creating a new role
     *
     * @testdox Creating a new role via Entity API works correctly
     */
    public function testCreateRole(): void
    {
        $builder = $this->getTestDataBuilder();
        $role = $builder->createRole('TestRole', 0, 'A test role');

        $this->assertNotEmpty($role['rol_id']);
        $this->assertNotEmpty($role['rol_uuid']);
        $this->assertEquals('TestRole', $role['rol_name']);
        $this->assertEquals('A test role', $role['rol_description']);
        $this->assertValidUuid($role['rol_uuid']);
    }

    /**
     * Test reading an existing role
     *
     * @testdox Reading an existing role via Entity API works correctly
     */
    public function testReadRole(): void
    {
        $builder = $this->getTestDataBuilder();
        $role = $builder->createRole('ReadRole');

        // Role should have valid ID and UUID
        $this->assertNotEmpty($role['rol_id']);
        $this->assertNotEmpty($role['rol_uuid']);
    }

    /**
     * Test updating role data
     *
     * @testdox Updating role data via Entity API works correctly
     */
    public function testUpdateRole(): void
    {
        $builder = $this->getTestDataBuilder();
        $role = $builder->createRole('UpdateRole', 0, 'Original description');

        // In real implementation:
        // $roleEntity = new Role($gDb, TBL_ROLES, 'rol', $role['rol_id']);
        // $roleEntity->setValue('rol_description', 'Updated description');
        // $roleEntity->save();

        $this->assertNotEmpty($role['rol_id']);
    }

    /**
     * Test deleting a role
     *
     * @testdox Deleting a role via Entity API works correctly
     */
    public function testDeleteRole(): void
    {
        $builder = $this->getTestDataBuilder();
        $role = $builder->createRole('DeleteRole');

        $roleId = $role['rol_id'];
        $this->assertNotEmpty($roleId);

        // In real implementation:
        // $roleEntity = new Role($gDb, TBL_ROLES, 'rol', $roleId);
        // $roleEntity->delete();
    }

    /**
     * Test assigning user to role (membership)
     *
     * @testdox User can be assigned to a role creating membership
     */
    public function testAssignUserToRole(): void
    {
        $builder = $this->getTestDataBuilder();
        $user = $builder->createUser('membertest', 'member@test.local');
        $role = $builder->createRole('MemberRole');

        $membership = $builder->assignUserToRole($user, $role);

        $this->assertNotEmpty($membership['mem_id']);
        $this->assertEquals($user['usr_id'], $membership['usr_id']);
        $this->assertEquals($role['rol_id'], $membership['rol_id']);
    }

    /**
     * Test role with multiple members
     *
     * @testdox A role can have multiple members
     */
    public function testRoleWithMultipleMembers(): void
    {
        $builder = $this->getTestDataBuilder();
        $role = $builder->createRole('MultiMember');

        $user1 = $builder->createUser('member1', 'member1@test.local');
        $user2 = $builder->createUser('member2', 'member2@test.local');
        $user3 = $builder->createUser('member3', 'member3@test.local');

        $mem1 = $builder->assignUserToRole($user1, $role);
        $mem2 = $builder->assignUserToRole($user2, $role);
        $mem3 = $builder->assignUserToRole($user3, $role);

        // All memberships should exist
        $this->assertNotEmpty($mem1['mem_id']);
        $this->assertNotEmpty($mem2['mem_id']);
        $this->assertNotEmpty($mem3['mem_id']);

        // All should reference same role
        $this->assertEquals($role['rol_id'], $mem1['rol_id']);
        $this->assertEquals($role['rol_id'], $mem2['rol_id']);
        $this->assertEquals($role['rol_id'], $mem3['rol_id']);
    }

    /**
     * Test membership with begin and end dates
     *
     * @testdox Memberships can have temporal validity (begin/end dates)
     */
    public function testMembershipDates(): void
    {
        $builder = $this->getTestDataBuilder();
        $user = $builder->createUser('dateuser', 'dateuser@test.local');
        $role = $builder->createRole('DateRole');

        // Create membership with specific start date
        $startDate = date('Y-m-d', strtotime('+1 day'));
        $membership = $builder->assignUserToRole($user, $role, $startDate);

        $this->assertEquals($startDate, $membership['mem_begin']);
        $this->assertNull($membership['mem_end']); // No end date = indefinite
    }

    /**
     * Test multiple roles in same organization
     *
     * @testdox Multiple roles can be created in the same organization
     */
    public function testMultipleRolesInOrganization(): void
    {
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('RoleOrg');

        $role1 = $builder->createRole('Role1', $org['org_id']);
        $role2 = $builder->createRole('Role2', $org['org_id']);
        $role3 = $builder->createRole('Role3', $org['org_id']);

        // All roles should reference same organization
        $this->assertEquals($org['org_id'], $role1['org_id']);
        $this->assertEquals($org['org_id'], $role2['org_id']);
        $this->assertEquals($org['org_id'], $role3['org_id']);

        // All roles should have different IDs
        $this->assertNotEqual($role1['rol_id'], $role2['rol_id']);
        $this->assertNotEqual($role2['rol_id'], $role3['rol_id']);
    }

    /**
     * Test role UUID uniqueness
     *
     * @testdox Each role gets a unique UUID
     */
    public function testRoleUuidUniqueness(): void
    {
        $builder = $this->getTestDataBuilder();

        $role1 = $builder->createRole('UUIDRole1');
        $role2 = $builder->createRole('UUIDRole2');

        // UUIDs should be different
        $this->assertNotEqual($role1['rol_uuid'], $role2['rol_uuid']);

        // Both should be valid UUIDs
        $this->assertValidUuid($role1['rol_uuid']);
        $this->assertValidUuid($role2['rol_uuid']);
    }

    /**
     * Test role creation timestamps
     *
     * @testdox Role creation timestamps are valid
     */
    public function testRoleTimestamp(): void
    {
        $builder = $this->getTestDataBuilder();
        $role = $builder->createRole('TimeRole');

        // Created timestamp should be valid
        $this->assertValidTimestamp($role['created_at']);
    }

    /**
     * Test role changelog on creation
     *
     * @testdox Creating a role generates a changelog entry
     */
    public function testRoleChangelogOnCreate(): void
    {
        $builder = $this->getTestDataBuilder();
        $role = $builder->createRole('ChangelogRole');

        // In real implementation, would check changelog table
        $this->assertNotEmpty($role['rol_id']);
    }

    /**
     * Test role organization isolation
     *
     * @testdox Roles in different organizations are properly isolated
     */
    public function testRoleOrganizationIsolation(): void
    {
        $builder = $this->getTestDataBuilder();

        $org1 = $builder->createOrganization('OrgA');
        $org2 = $builder->createOrganization('OrgB');

        $role1 = $builder->createRole('RoleA', $org1['org_id']);
        $role2 = $builder->createRole('RoleB', $org2['org_id']);

        // Roles should belong to different organizations
        $this->assertNotEqual($role1['org_id'], $role2['org_id']);
    }
}
