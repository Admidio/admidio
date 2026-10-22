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
     * Test that several users can be members of the same role
     *
     * @testdox Several users can be members of the same role
     */
    public function testMultipleUsersCanBeMembers(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $role = $fixture->createAndSaveRole('Test Role', $org['org_id']);

        $user1 = $fixture->createAndSaveUser('user1', 'user1@example.local');
        $user2 = $fixture->createAndSaveUser('user2', 'user2@example.local');
        $user3 = $fixture->createAndSaveUser('user3', 'user3@example.local');

        $fixture->assignUserToRole($user1['usr_id'], $role['rol_id']);
        $fixture->assignUserToRole($user2['usr_id'], $role['rol_id']);
        $fixture->assignUserToRole($user3['usr_id'], $role['rol_id']);

        $this->assertEquals(3, $fixture->countRoleMemberships($role['rol_id']));

        // every one of the three users is recorded, none of them twice
        $memberIds = array_map(
            'intval',
            array_column($fixture->getRoleMemberships($role['rol_id']), 'mem_usr_id')
        );
        sort($memberIds);
        $expected = [$user1['usr_id'], $user2['usr_id'], $user3['usr_id']];
        sort($expected);

        $this->assertEquals($expected, $memberIds);
    }

    /**
     * Test membership with date ranges
     *
     * @testdox Membership start dates are stored as given
     */
    public function testMembershipDateTracking(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $user = $fixture->createAndSaveUser('testuser', 'test@example.local');
        $role = $fixture->createAndSaveRole('Test Role', $org['org_id']);

        // Create membership with custom start date
        $startDate = '2025-01-01';
        $mem = $fixture->assignUserToRole($user['usr_id'], $role['rol_id'], $startDate);

        // the stored begin date has to be the one that was passed in
        $sql = 'SELECT mem_begin FROM ' . TBL_MEMBERS . ' WHERE mem_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$mem['mem_id']]);
        $row = $result->fetch();

        $this->assertEquals($startDate, substr($row['mem_begin'], 0, 10));
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

        $role1 = $fixture->createAndSaveRole('Role1', $org['org_id']);
        $role2 = $fixture->createAndSaveRole('Role2', $org['org_id']);

        // Assign user to both roles
        $mem1 = $fixture->assignUserToRole($user['usr_id'], $role1['rol_id']);
        $mem2 = $fixture->assignUserToRole($user['usr_id'], $role2['rol_id']);

        // Verify both memberships exist
        $this->assertTrue($fixture->membershipExists($mem1['mem_id']));
        $this->assertTrue($fixture->membershipExists($mem2['mem_id']));
        $this->assertNotEquals($mem1['mem_id'], $mem2['mem_id']);

        // each role counts exactly this one member
        $this->assertEquals(1, $fixture->countRoleMemberships($role1['rol_id']));
        $this->assertEquals(1, $fixture->countRoleMemberships($role2['rol_id']));
    }

    /**
     * Test that deleting a role removes its memberships
     *
     * @testdox Deleting a role deletes its memberships
     */
    public function testDeletingRoleCascadesToMemberships(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $user = $fixture->createAndSaveUser('cascadeuser', 'cascade@example.local');

        $role = $fixture->createAndSaveRole('Doomed Role', $org['org_id']);
        $keptRole = $fixture->createAndSaveRole('Kept Role', $org['org_id']);

        $doomedMem = $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
        $keptMem = $fixture->assignUserToRole($user['usr_id'], $keptRole['rol_id']);

        $this->assertTrue($fixture->membershipExists($doomedMem['mem_id']));

        $this->assertTrue($fixture->deleteRole($role['rol_id']));

        // the role and its membership are gone, the unrelated one survives
        $this->assertFalse($fixture->roleExists($role['rol_id']));
        $this->assertFalse($fixture->membershipExists($doomedMem['mem_id']));
        $this->assertTrue($fixture->membershipExists($keptMem['mem_id']));

        // and the user itself is untouched
        $this->assertNotEmpty($fixture->getUserById($user['usr_id']));
    }

    /**
     * Test that deleting a user removes their memberships
     *
     * @testdox Deleting a user deletes their memberships
     */
    public function testDeletingUserCascadesToMemberships(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $role = $fixture->createAndSaveRole('Test Role', $org['org_id']);

        $leaving = $fixture->createAndSaveUser('leaving', 'leaving@example.local');
        $staying = $fixture->createAndSaveUser('staying', 'staying@example.local');

        $leavingMem = $fixture->assignUserToRole($leaving['usr_id'], $role['rol_id']);
        $stayingMem = $fixture->assignUserToRole($staying['usr_id'], $role['rol_id']);

        $this->assertEquals(2, $fixture->countRoleMemberships($role['rol_id']));

        $this->assertTrue($fixture->deleteUser($leaving['usr_id']));

        // only the leaving user's membership disappears, the role keeps the other one
        $this->assertFalse($fixture->membershipExists($leavingMem['mem_id']));
        $this->assertTrue($fixture->membershipExists($stayingMem['mem_id']));
        $this->assertEquals(1, $fixture->countRoleMemberships($role['rol_id']));
        $this->assertTrue($fixture->roleExists($role['rol_id']));
    }
}
