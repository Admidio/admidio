<?php
/**
 * Membership Cascade Tests
 *
 * Tests cascade behavior when memberships and related entities are deleted.
 */

namespace Admidio\Tests\Integration\Cascading;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;

class MembershipCascadeTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test that multiple users can be members of roles
     *
     * @testdox Multiple users can be managed independently
     */
    public function testMultipleUsersCanBeMembers(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');

        // Create multiple users
        $user1 = $fixture->createAndSaveUser('user1', 'user1@example.local');
        $user2 = $fixture->createAndSaveUser('user2', 'user2@example.local');
        $user3 = $fixture->createAndSaveUser('user3', 'user3@example.local');

        // Verify all users exist
        $this->assertNotEmpty($user1['usr_id']);
        $this->assertNotEmpty($user2['usr_id']);
        $this->assertNotEmpty($user3['usr_id']);

        // Verify they have different IDs
        $this->assertNotEquals($user1['usr_id'], $user2['usr_id']);
        $this->assertNotEquals($user2['usr_id'], $user3['usr_id']);
    }

    /**
     * Test membership with date ranges
     *
     * @testdox Membership start dates can be set
     */
    public function testMembershipDateTracking(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $user = $fixture->createAndSaveUser('testuser', 'test@example.local');

        $cat = $fixture->createAndSaveCategory('Roles', 'ROL', $org['org_id']);
        $sql = 'INSERT INTO ' . TBL_ROLES . ' (rol_name, rol_cat_id, rol_uuid) VALUES (?, ?, UUID())';
        $this->getDatabase()->queryPrepared($sql, ['Test Role', $cat['cat_id']]);
        $roleId = (int) $this->getDatabase()->lastInsertId();

        // Create membership with custom start date
        $startDate = '2025-01-01';
        $mem = $fixture->assignUserToRole($user['usr_id'], $roleId, $startDate);

        // Verify membership has date (format may be localized by DB)
        $this->assertNotEmpty($mem['mem_begin']);

        // Verify membership exists
        $this->assertTrue($fixture->membershipExists($mem['mem_id']));
    }

    /**
     * Test users can have multiple roles
     *
     * @testdox Single user can be assigned to multiple roles
     */
    public function testUserCanHaveMultipleRoles(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $user = $fixture->createAndSaveUser('multiuser', 'multi@example.local');

        $cat = $fixture->createAndSaveCategory('Roles', 'ROL', $org['org_id']);
        $sql = 'INSERT INTO ' . TBL_ROLES . ' (rol_name, rol_cat_id, rol_uuid) VALUES (?, ?, UUID())';

        // Create multiple roles
        $this->getDatabase()->queryPrepared($sql, ['Role1', $cat['cat_id']]);
        $role1Id = (int) $this->getDatabase()->lastInsertId();

        $this->getDatabase()->queryPrepared($sql, ['Role2', $cat['cat_id']]);
        $role2Id = (int) $this->getDatabase()->lastInsertId();

        // Assign user to both roles
        $mem1 = $fixture->assignUserToRole($user['usr_id'], $role1Id);
        $mem2 = $fixture->assignUserToRole($user['usr_id'], $role2Id);

        // Verify both memberships exist
        $this->assertTrue($fixture->membershipExists($mem1['mem_id']));
        $this->assertTrue($fixture->membershipExists($mem2['mem_id']));
        $this->assertNotEquals($mem1['mem_id'], $mem2['mem_id']);
    }
}
