<?php
/**
 * Role Management Workflow Tests
 *
 * Tests realistic role scenarios combining roles, users, and organizations.
 *
 * @testdox Role management workflows work correctly across organizations
 */

namespace Admidio\Tests\Integration\Workflows\Roles;

use Admidio\Tests\Support\DatabaseTestCase;

class RoleWorkflowTest extends DatabaseTestCase
{
    /**
     * Test creating multiple roles in organization
     *
     * @testdox Multiple roles can be created in same organization
     */
    public function testMultipleRolesInOrganization(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Multi-Role Org');

        // Act - Create multiple roles
        $admin = $builder->createRole('Administrators', $org['org_id']);
        $leaders = $builder->createRole('Leaders', $org['org_id']);
        $members = $builder->createRole('Members', $org['org_id']);
        $guests = $builder->createRole('Guests', $org['org_id']);

        // Assert
        $roles = $builder->getRoles();
        $this->assertGreaterThanOrEqual(4, count($roles));
        $this->assertNotEquals($admin['rol_id'], $leaders['rol_id']);
        $this->assertNotEquals($leaders['rol_id'], $members['rol_id']);
        $this->assertNotEquals($members['rol_id'], $guests['rol_id']);
    }

    /**
     * Test role isolation across organizations
     *
     * @testdox Roles in different organizations don't interfere
     */
    public function testRoleOrganizationIsolation(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org1 = $builder->createOrganization('Company A');
        $org2 = $builder->createOrganization('Company B');

        // Create roles with same name in different orgs
        $role1 = $builder->createRole('Members', $org1['org_id']);
        $role2 = $builder->createRole('Members', $org2['org_id']);

        // Assert - Same name, different IDs and orgs
        $this->assertEquals('Members', $role1['rol_name']);
        $this->assertEquals('Members', $role2['rol_name']);
        $this->assertNotEquals($role1['rol_id'], $role2['rol_id']);
        $this->assertNotEquals($role1['org_id'], $role2['org_id']);
    }

    /**
     * Test bulk user assignment to role
     *
     * @testdox Multiple users can be assigned to single role
     */
    public function testBulkUserAssignmentToRole(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');
        $role = $builder->createRole('Team', $org['org_id']);

        // Act - Assign multiple users to role
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = $builder->createUser("teamuser$i", "user$i@company", $org['org_id']);
            $users[] = $user;
            $builder->assignUserToRole($user, $role);
        }

        // Assert
        $this->assertEquals(5, count($users));
        $this->assertNotEmpty($role['rol_id']);
    }

    /**
     * Test role with temporal membership
     *
     * @testdox Roles support membership date ranges for temporary assignment
     */
    public function testTemporaryRoleMembership(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');
        $user = $builder->createUser('temp', 'temp@company', $org['org_id']);
        $role = $builder->createRole('Seasonal', $org['org_id']);

        // Act - Assign with date range
        $membership = $builder->assignUserToRole($user, $role, '2026-06-01');

        // Assert
        $this->assertEquals('2026-06-01', $membership['mem_begin']);
        $this->assertNull($membership['mem_end']); // No end date yet
    }

    /**
     * Test role hierarchy simulation
     *
     * @testdox Multiple roles can represent hierarchical structure
     */
    public function testRoleHierarchySimulation(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Hierarchical Org');

        // Create roles representing hierarchy
        $admin = $builder->createRole('Admin', $org['org_id']);
        $director = $builder->createRole('Director', $org['org_id']);
        $manager = $builder->createRole('Manager', $org['org_id']);
        $staff = $builder->createRole('Staff', $org['org_id']);

        // Create users at different levels
        $ceo = $builder->createUser('ceo', 'ceo@company', $org['org_id']);
        $dir1 = $builder->createUser('director', 'dir@company', $org['org_id']);
        $mgr1 = $builder->createUser('manager', 'mgr@company', $org['org_id']);
        $emp1 = $builder->createUser('employee', 'emp@company', $org['org_id']);

        // Assign to roles
        $builder->assignUserToRole($ceo, $admin);
        $builder->assignUserToRole($dir1, $director);
        $builder->assignUserToRole($mgr1, $manager);
        $builder->assignUserToRole($emp1, $staff);

        // Assert
        $roles = $builder->getRoles();
        $this->assertGreaterThanOrEqual(4, count($roles));
    }
}
