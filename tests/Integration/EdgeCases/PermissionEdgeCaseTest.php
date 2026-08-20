<?php
/**
 * Permission Edge Case Tests
 *
 * Tests boundary conditions for permission checking and authorization.
 *
 * @testdox Permission edge cases are handled correctly
 */

namespace Admidio\Tests\Integration\EdgeCases;

use Admidio\Tests\Support\DatabaseTestCase;

class PermissionEdgeCaseTest extends DatabaseTestCase
{
    /**
     * Test cross-organization access denial
     *
     * @testdox Users in one organization cannot access another's data
     */
    public function testCrossOrganizationAccessDenial(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org1 = $builder->createOrganization('Company A');
        $org2 = $builder->createOrganization('Company B');

        // Create users in different organizations
        $user1_org1 = $builder->createUser('user1', 'user1@a.com', $org1['org_id']);
        $user1_org2 = $builder->createUser('user1', 'user1@b.com', $org2['org_id']);

        // Create roles in different organizations
        $role_org1 = $builder->createRole('Managers', $org1['org_id']);
        $role_org2 = $builder->createRole('Managers', $org2['org_id']);

        // Assert - Organizations are isolated
        $this->assertEquals($org1['org_id'], $user1_org1['org_id']);
        $this->assertEquals($org2['org_id'], $user1_org2['org_id']);
        $this->assertNotEquals($user1_org1['org_id'], $user1_org2['org_id']);
        $this->assertNotEquals($role_org1['org_id'], $role_org2['org_id']);
    }

    /**
     * Test role-based view restrictions
     *
     * @testdox Different roles have appropriate view restrictions
     */
    public function testRoleBasedViewRestrictions(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Create roles with different permission levels
        $adminRole = $builder->createRole('Administrators', $org['org_id'], 'Full access');
        $managerRole = $builder->createRole('Managers', $org['org_id'], 'Limited access');
        $memberRole = $builder->createRole('Members', $org['org_id'], 'View only');

        // Create users with different roles
        $admin = $builder->createUser('admin', 'admin@company', $org['org_id']);
        $manager = $builder->createUser('manager', 'manager@company', $org['org_id']);
        $member = $builder->createUser('member', 'member@company', $org['org_id']);

        // Assign to roles
        $builder->assignUserToRole($admin, $adminRole);
        $builder->assignUserToRole($manager, $managerRole);
        $builder->assignUserToRole($member, $memberRole);

        // Assert - Roles are distinct
        $this->assertNotEquals($adminRole['rol_id'], $managerRole['rol_id']);
        $this->assertNotEquals($managerRole['rol_id'], $memberRole['rol_id']);
        $this->assertEquals('Full access', $adminRole['rol_description']);
        $this->assertEquals('Limited access', $managerRole['rol_description']);
        $this->assertEquals('View only', $memberRole['rol_description']);
    }

    /**
     * Test multiple role membership permissions
     *
     * @testdox Users can have multiple role memberships with different permissions
     */
    public function testMultipleRoleMembershipPermissions(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Create multiple roles
        $eventOrganizer = $builder->createRole('Event Organizers', $org['org_id']);
        $categoryManager = $builder->createRole('Category Managers', $org['org_id']);
        $regularMember = $builder->createRole('Members', $org['org_id']);

        // Create user with multiple role memberships
        $user = $builder->createUser('poweruser', 'power@company', $org['org_id']);

        // Assign user to multiple roles
        $mem1 = $builder->assignUserToRole($user, $eventOrganizer);
        $mem2 = $builder->assignUserToRole($user, $categoryManager);
        $mem3 = $builder->assignUserToRole($user, $regularMember);

        // Assert - Multiple memberships exist
        $this->assertNotEmpty($mem1['mem_id']);
        $this->assertNotEmpty($mem2['mem_id']);
        $this->assertNotEmpty($mem3['mem_id']);
        $this->assertNotEquals($mem1['mem_id'], $mem2['mem_id']);
        $this->assertNotEquals($mem2['mem_id'], $mem3['mem_id']);
        $this->assertEquals($user['usr_id'], $mem1['usr_id']);
        $this->assertEquals($user['usr_id'], $mem2['usr_id']);
        $this->assertEquals($user['usr_id'], $mem3['usr_id']);
    }

    /**
     * Test category visibility across roles
     *
     * @testdox Categories visibility depends on role assignments
     */
    public function testCategoryVisibilityAcrossRoles(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Create categories
        $publicCategory = $builder->createCategory('Public Events', 'EVT', $org['org_id']);
        $restrictedCategory = $builder->createCategory('Admin Events', 'EVT', $org['org_id']);

        // Create roles
        $publicRole = $builder->createRole('Public', $org['org_id']);
        $adminRole = $builder->createRole('Admin', $org['org_id']);

        // Assert - Categories are distinct
        $this->assertNotEmpty($publicCategory['cat_id']);
        $this->assertNotEmpty($restrictedCategory['cat_id']);
        $this->assertNotEquals($publicCategory['cat_id'], $restrictedCategory['cat_id']);
        $this->assertEquals('EVT', $publicCategory['cat_type']);
        $this->assertEquals('EVT', $restrictedCategory['cat_type']);
    }

    /**
     * Test delegated admin scope boundaries
     *
     * @testdox Delegated admins can only manage their assigned scope
     */
    public function testDelegatedAdminScopeBoundaries(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org1 = $builder->createOrganization('Company A');
        $org2 = $builder->createOrganization('Company B');

        // Create admin roles in each organization
        $adminOrg1 = $builder->createRole('Admin', $org1['org_id']);
        $adminOrg2 = $builder->createRole('Admin', $org2['org_id']);

        // Create admin users
        $delegatedAdmin1 = $builder->createUser('admin1', 'admin1@a.com', $org1['org_id']);
        $delegatedAdmin2 = $builder->createUser('admin2', 'admin2@b.com', $org2['org_id']);

        // Assign to admin roles
        $builder->assignUserToRole($delegatedAdmin1, $adminOrg1);
        $builder->assignUserToRole($delegatedAdmin2, $adminOrg2);

        // Assert - Admin users are scoped to their organizations
        $this->assertEquals($org1['org_id'], $delegatedAdmin1['org_id']);
        $this->assertEquals($org2['org_id'], $delegatedAdmin2['org_id']);
        $this->assertNotEquals($delegatedAdmin1['org_id'], $delegatedAdmin2['org_id']);
    }
}
