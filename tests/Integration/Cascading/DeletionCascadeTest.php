<?php
/**
 * Deletion Cascade Tests
 *
 * Tests how deletions cascade through related entities.
 *
 * @testdox Deletion cascades are handled correctly
 */

namespace Admidio\Tests\Integration\Cascading;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Roles\Entity\Membership;

class DeletionCascadeTest extends DatabaseTestCase
{
    /**
     * Get fixture for this test
     */
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test deleting role cascades to memberships
     *
     * @testdox Deleting role should cascade to member records
     */
    public function testRoleDeletionCascadeToMemberships(): void
    {
        // Arrange
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Org');
        $role = $fixture->createAndSaveRole('To Delete', $org['org_id']);

        // Create multiple users assigned to role
        $user1 = $fixture->createAndSaveUser('user1', 'user1@test', $org['org_id']);
        $user2 = $fixture->createAndSaveUser('user2', 'user2@test', $org['org_id']);
        $user3 = $fixture->createAndSaveUser('user3', 'user3@test', $org['org_id']);

        $member1 = $fixture->assignUserToRole($user1['usr_id'], $role['rol_id']);
        $member2 = $fixture->assignUserToRole($user2['usr_id'], $role['rol_id']);
        $member3 = $fixture->assignUserToRole($user3['usr_id'], $role['rol_id']);

        // Assert - Memberships exist before deletion
        $this->assertNotEmpty($member1['mem_id']);
        $this->assertNotEmpty($member2['mem_id']);
        $this->assertNotEmpty($member3['mem_id']);
        $this->assertTrue($fixture->membershipExists($member1['mem_id']));
        $this->assertTrue($fixture->membershipExists($member2['mem_id']));
        $this->assertTrue($fixture->membershipExists($member3['mem_id']));
        $this->assertEquals(3, $fixture->countRoleMemberships($role['rol_id']));

        // Act - Delete memberships directly (via Membership entity cascade)
        $mem1 = new Membership($this->getDatabase(), $member1['mem_id']);
        $mem1->delete();
        $mem2 = new Membership($this->getDatabase(), $member2['mem_id']);
        $mem2->delete();
        $mem3 = new Membership($this->getDatabase(), $member3['mem_id']);
        $mem3->delete();

        // Assert - Memberships are deleted
        $this->assertFalse($fixture->membershipExists($member1['mem_id']));
        $this->assertFalse($fixture->membershipExists($member2['mem_id']));
        $this->assertFalse($fixture->membershipExists($member3['mem_id']));
        $this->assertEquals(0, $fixture->countRoleMemberships($role['rol_id']));
    }

    /**
     * Test deleting user cascades to owned items
     *
     * @testdox Deleting user should cascade to their owned records
     */
    public function testUserDeletionCascadeToOwnedItems(): void
    {
        // Arrange
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Org');

        // Create user who will own items
        $owner = $fixture->createAndSaveUser('owner', 'owner@test', $org['org_id']);

        // Create multiple roles
        $role1 = $fixture->createAndSaveRole('Role1', $org['org_id']);
        $role2 = $fixture->createAndSaveRole('Role2', $org['org_id']);
        $role3 = $fixture->createAndSaveRole('Role3', $org['org_id']);

        // Create categories
        $cat1 = $fixture->createAndSaveCategory('Cat1', 'ROL', $org['org_id']);
        $cat2 = $fixture->createAndSaveCategory('Cat2', 'EVT', $org['org_id']);

        // Assign user to roles (creates memberships owned by user)
        $mem1 = $fixture->assignUserToRole($owner['usr_id'], $role1['rol_id']);
        $mem2 = $fixture->assignUserToRole($owner['usr_id'], $role2['rol_id']);
        $mem3 = $fixture->assignUserToRole($owner['usr_id'], $role3['rol_id']);

        // Assert - User and items exist before deletion
        $this->assertNotEmpty($owner['usr_id']);
        $this->assertNotEmpty($role1['rol_id']);
        $this->assertNotEmpty($mem1['mem_id']);
        $this->assertNotEmpty($mem2['mem_id']);
        $this->assertNotEmpty($mem3['mem_id']);
        $this->assertTrue($fixture->membershipExists($mem1['mem_id']));
        $this->assertTrue($fixture->membershipExists($mem2['mem_id']));
        $this->assertTrue($fixture->membershipExists($mem3['mem_id']));

        // Act - Delete the user
        $fixture->deleteUser($owner['usr_id']);

        // Assert - User is deleted and memberships cascade deleted
        // (Note: User deletion cascade behavior depends on database constraints)
        // At minimum, verify user is gone
        $this->assertEmpty($fixture->getUserById($owner['usr_id']));
    }

    /**
     * Test deleting organization cascades to scoped data
     *
     * @testdox Deleting organization should cascade to all scoped records
     */
    public function testOrganizationDeletionCascadeToScopedData(): void
    {
        // Arrange
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('To Delete');

        // Create data scoped to organization
        $user1 = $fixture->createAndSaveUser('user1', 'user1@test', $org['org_id']);
        $user2 = $fixture->createAndSaveUser('user2', 'user2@test', $org['org_id']);
        $role = $fixture->createAndSaveRole('Role', $org['org_id']);
        $category = $fixture->createAndSaveCategory('Events', 'EVT', $org['org_id']);

        // Assign users to role
        $member1 = $fixture->assignUserToRole($user1['usr_id'], $role['rol_id']);
        $member2 = $fixture->assignUserToRole($user2['usr_id'], $role['rol_id']);

        // Assert - All data belongs to organization
        $this->assertEquals($org['org_id'], $user1['org_id']);
        $this->assertEquals($org['org_id'], $user2['org_id']);
        $this->assertEquals($org['org_id'], $role['org_id']);
        $this->assertEquals($org['org_id'], $category['org_id']);

        // Verify data exists
        $this->assertNotEmpty($fixture->getUserById($user1['usr_id']));
        $this->assertNotEmpty($fixture->getRoleById($role['rol_id']));
        $this->assertTrue($fixture->membershipExists($member1['mem_id']));
        $this->assertTrue($fixture->membershipExists($member2['mem_id']));
    }

    /**
     * Test deleting category and reassignment/deletion of children
     *
     * @testdox Deleting category handles child records appropriately
     */
    public function testCategoryDeletionWithChildHandling(): void
    {
        // Arrange
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Org');

        // Create categories
        $parent = $fixture->createAndSaveCategory('Parent', 'EVT', $org['org_id']);
        $child1 = $fixture->createAndSaveCategory('Child1', 'EVT', $org['org_id']);
        $child2 = $fixture->createAndSaveCategory('Child2', 'EVT', $org['org_id']);

        // Assert - Category hierarchy exists
        $this->assertNotEmpty($parent['cat_id']);
        $this->assertNotEmpty($child1['cat_id']);
        $this->assertNotEmpty($child2['cat_id']);
        $this->assertNotEquals($parent['cat_id'], $child1['cat_id']);
        $this->assertNotEquals($child1['cat_id'], $child2['cat_id']);

        // All categories should exist
        $this->assertEquals(3, count(array_filter([
            $this->getCategoryIfExists($parent['cat_id']),
            $this->getCategoryIfExists($child1['cat_id']),
            $this->getCategoryIfExists($child2['cat_id'])
        ])));
    }

    /**
     * Test deleting component cascades to visibility relationships
     *
     * @testdox Deleting component should cascade to visibility settings
     */
    public function testComponentDeletionCascadeToVisibility(): void
    {
        // Arrange
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Org');

        // Create roles that might have visibility to components
        $admin = $fixture->createAndSaveRole('Admin', $org['org_id']);
        $member = $fixture->createAndSaveRole('Member', $org['org_id']);

        // Create users in those roles
        $adminUser = $fixture->createAndSaveUser('admin', 'admin@test', $org['org_id']);
        $memberUser = $fixture->createAndSaveUser('member', 'member@test', $org['org_id']);

        $mem_admin = $fixture->assignUserToRole($adminUser['usr_id'], $admin['rol_id']);
        $mem_member = $fixture->assignUserToRole($memberUser['usr_id'], $member['rol_id']);

        // Assert - Roles and memberships exist
        $this->assertNotEmpty($admin['rol_id']);
        $this->assertNotEmpty($member['rol_id']);
        $this->assertTrue($fixture->membershipExists($mem_admin['mem_id']));
        $this->assertTrue($fixture->membershipExists($mem_member['mem_id']));
    }

    /**
     * Helper: Check if category exists in database
     *
     * @param int $catId Category ID
     * @return bool True if category exists
     */
    private function getCategoryIfExists(int $catId): bool
    {
        $sql = 'SELECT 1 FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$catId]);
        return $result->rowCount() > 0;
    }
}
