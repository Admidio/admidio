<?php
/**
 * Role Membership Rights Tests
 *
 * Tests membership driven rights: global roles, leader memberships and profile edit rights.
 */

namespace Admidio\Tests\Integration\Permissions;

use Admidio\Roles\Entity\Role;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;

class RoleMembershipRightsTest extends DatabaseTestCase
{
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test that the membership list has to be read before it can be queried.
     * isMemberOfRole() only looks at the cache that checkRolesRight() fills, it does not fill it
     * itself, so on a freshly read user it answers false for a role the user really is in.
     *
     * @testdox isMemberOfRole only answers once the roles have been read
     */
    public function testIsMemberOfRoleNeedsTheRightsToBeReadFirst(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Member Org', 'memorg');
        $role = $fixture->createAndSaveRoleWithRights('Members', $org['org_id']);
        $user = $fixture->createAndSaveUser('cachetest', 'cache@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        $member = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        // the membership exists in the database
        $this->assertEquals(1, $fixture->countRoleMemberships($role['rol_id']));

        // but the object does not know about it yet
        $this->assertFalse($member->isMemberOfRole($role['rol_id']));

        // reading the rights fills the cache, and only then is the answer correct
        $member->checkRolesRight();
        $this->assertTrue($member->isMemberOfRole($role['rol_id']));
    }

    /**
     * Test that a role in a global category works in every organization
     *
     * @testdox A role in a global category grants its right in every organization
     */
    public function testRoleInGlobalCategoryAppliesEverywhere(): void
    {
        $fixture = $this->getFixture();
        $orgA = $fixture->createAndSaveOrganization('Org A', 'globala');
        $orgB = $fixture->createAndSaveOrganization('Org B', 'globalb');

        // a category without an organization is global
        $globalCategory = $fixture->createAndSaveCategory('Global Roles', 'ROL', 0);
        $this->assertNull($fixture->getCategoryById($globalCategory['cat_id'])['cat_org_id']);

        $role = new Role($this->getDatabase());
        $role->saveChangesWithoutRights();
        $role->setValue('rol_name', 'Global Announcers');
        $role->setValue('rol_cat_id', $globalCategory['cat_id']);
        $role->setValue('rol_announcements', 1);
        $role->save();
        $roleId = (int) $role->getValue('rol_id');

        $user = $fixture->createAndSaveUser('globaluser', 'globalu@example.local');
        $fixture->assignUserToRole($user['usr_id'], $roleId);

        // unlike an org-scoped role, this one is effective in both organizations
        $this->assertTrue(
            $this->loadUserInOrganization($user['usr_id'], $orgA['org_id'])->isAdministratorAnnouncements()
        );
        $this->assertTrue(
            $this->loadUserInOrganization($user['usr_id'], $orgB['org_id'])->isAdministratorAnnouncements()
        );
    }

    /**
     * Test that a leader membership is stored and is separate from the module rights
     *
     * @testdox A leader membership does not by itself grant module admin rights
     */
    public function testLeaderMembershipIsSeparateFromModuleRights(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Leader Org', 'leadorg');
        $role = $fixture->createAndSaveRoleWithRights(
            'Led Role',
            $org['org_id'],
            ['rol_leader_rights' => Role::ROLE_LEADER_MEMBERS_ASSIGN]
        );
        $user = $fixture->createAndSaveUser('theleader', 'leader@example.local');

        $membership = $fixture->assignUserToRolePeriod(
            $user['usr_id'],
            $role['rol_id'],
            date('Y-m-d'),
            '9999-12-31',
            true
        );

        // the leader flag is stored
        $sql = 'SELECT mem_leader FROM ' . TBL_MEMBERS . ' WHERE mem_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$membership['mem_id']])->fetch();
        $this->assertTrue((bool) $row['mem_leader']);

        // but leading a role is not the same as holding the role administration right
        $leader = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);
        $this->assertFalse($leader->isAdministratorRoles());
        $this->assertFalse($leader->isAdministrator());
    }

    /**
     * Test that rol_edit_user grants the right to edit other profiles
     *
     * @testdox rol_edit_user grants the right to edit other profiles
     */
    public function testEditUserRightGrantsProfileEditing(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Profile Org', 'proforg');
        $role = $fixture->createAndSaveRoleWithRights('User Admins', $org['org_id'], ['rol_edit_user' => 1]);

        $editor = $fixture->createAndSaveUser('profileeditor', 'pe@example.local');
        $target = $fixture->createAndSaveUser('profiletarget', 'pt@example.local');
        $fixture->assignUserToRole($editor['usr_id'], $role['rol_id']);

        $editorUser = $this->loadUserInOrganization($editor['usr_id'], $org['org_id']);
        $targetUser = $this->loadUserInOrganization($target['usr_id'], $org['org_id']);

        $this->assertTrue($editorUser->hasRightEditProfile($targetUser));
    }

    /**
     * Test that a user without the right cannot edit other profiles
     *
     * @testdox A user without rol_edit_user cannot edit another profile
     */
    public function testPlainUserCannotEditAnotherProfile(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Profile Org', 'proforg');

        $one = $fixture->createAndSaveUser('plainone', 'p1@example.local');
        $two = $fixture->createAndSaveUser('plaintwo', 'p2@example.local');

        $oneUser = $this->loadUserInOrganization($one['usr_id'], $org['org_id']);
        $twoUser = $this->loadUserInOrganization($two['usr_id'], $org['org_id']);

        $this->assertFalse($oneUser->hasRightEditProfile($twoUser));
    }

    /**
     * Test that the profile edit right is bound to the organization
     *
     * @testdox The profile edit right does not apply in another organization
     */
    public function testProfileEditRightIsScopedToTheOrganization(): void
    {
        $fixture = $this->getFixture();
        $orgA = $fixture->createAndSaveOrganization('Org A', 'profa');
        $orgB = $fixture->createAndSaveOrganization('Org B', 'profb');

        $role = $fixture->createAndSaveRoleWithRights('User Admins', $orgA['org_id'], ['rol_edit_user' => 1]);
        $editor = $fixture->createAndSaveUser('scopededitor', 'se@example.local');
        $target = $fixture->createAndSaveUser('scopedtarget', 'st@example.local');
        $fixture->assignUserToRole($editor['usr_id'], $role['rol_id']);

        $inOrgA = $this->loadUserInOrganization($editor['usr_id'], $orgA['org_id']);
        $targetInA = $this->loadUserInOrganization($target['usr_id'], $orgA['org_id']);
        $this->assertTrue($inOrgA->hasRightEditProfile($targetInA));

        $inOrgB = $this->loadUserInOrganization($editor['usr_id'], $orgB['org_id']);
        $targetInB = $this->loadUserInOrganization($target['usr_id'], $orgB['org_id']);
        $this->assertFalse($inOrgB->hasRightEditProfile($targetInB));
    }

    /**
     * Test that a group leader may administrate the members of their own role.
     * hasRightEditProfile() accepts a leader whose rol_leader_rights allow editing, but only for
     * users that are plain members of a role the leader leads.
     *
     * @testdox A group leader may edit the profiles of the members of their role
     */
    public function testLeaderMayEditMembersOfTheirOwnRole(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Leader Org', 'leadedit');
        $role = $fixture->createAndSaveRoleWithRights(
            'Led Role',
            $org['org_id'],
            ['rol_leader_rights' => Role::ROLE_LEADER_MEMBERS_EDIT]
        );

        $leader = $fixture->createAndSaveUser('editleader', 'el@example.local');
        $member = $fixture->createAndSaveUser('editmember', 'em@example.local');

        $fixture->assignUserToRolePeriod($leader['usr_id'], $role['rol_id'], date('Y-m-d'), '9999-12-31', true);
        $fixture->assignUserToRole($member['usr_id'], $role['rol_id']);

        $leaderUser = $this->loadUserInOrganization($leader['usr_id'], $org['org_id']);
        $memberUser = $this->loadUserInOrganization($member['usr_id'], $org['org_id']);

        $this->assertTrue($leaderUser->hasRightEditProfile($memberUser));

        // the leader holds no module wide right, the permission is limited to their own group
        $this->assertFalse($leaderUser->isAdministratorUsers());
    }

    /**
     * Test that the delegated right stops at the boundary of the led role
     *
     * @testdox A group leader may not edit the profile of someone outside their role
     */
    public function testLeaderMayNotEditMembersOfAnotherRole(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Leader Org', 'leadedit');
        $ledRole = $fixture->createAndSaveRoleWithRights(
            'Led Role',
            $org['org_id'],
            ['rol_leader_rights' => Role::ROLE_LEADER_MEMBERS_EDIT]
        );
        $otherRole = $fixture->createAndSaveRoleWithRights('Other Role', $org['org_id']);

        $leader = $fixture->createAndSaveUser('boundleader', 'bl@example.local');
        $outsider = $fixture->createAndSaveUser('boundoutsider', 'bo@example.local');

        $fixture->assignUserToRolePeriod($leader['usr_id'], $ledRole['rol_id'], date('Y-m-d'), '9999-12-31', true);
        $fixture->assignUserToRole($outsider['usr_id'], $otherRole['rol_id']);

        $leaderUser = $this->loadUserInOrganization($leader['usr_id'], $org['org_id']);
        $outsiderUser = $this->loadUserInOrganization($outsider['usr_id'], $org['org_id']);

        $this->assertFalse($leaderUser->hasRightEditProfile($outsiderUser));
    }

    /**
     * Test that a leader without edit rights may not edit their members
     *
     * @testdox A leader whose role grants no edit rights may not edit its members
     */
    public function testLeaderWithoutEditRightsMayNotEditMembers(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Leader Org', 'leadedit');

        // assigning members is allowed, editing them is not
        $role = $fixture->createAndSaveRoleWithRights(
            'Led Role',
            $org['org_id'],
            ['rol_leader_rights' => Role::ROLE_LEADER_MEMBERS_ASSIGN]
        );

        $leader = $fixture->createAndSaveUser('assignleader', 'al@example.local');
        $member = $fixture->createAndSaveUser('assignmember', 'am@example.local');

        $fixture->assignUserToRolePeriod($leader['usr_id'], $role['rol_id'], date('Y-m-d'), '9999-12-31', true);
        $fixture->assignUserToRole($member['usr_id'], $role['rol_id']);

        $leaderUser = $this->loadUserInOrganization($leader['usr_id'], $org['org_id']);
        $memberUser = $this->loadUserInOrganization($member['usr_id'], $org['org_id']);

        // isLeaderOfRole reads the same cache as isMemberOfRole and does not fill it either
        $leaderUser->checkRolesRight();

        $this->assertTrue($leaderUser->isLeaderOfRole($role['rol_id']));
        $this->assertFalse($leaderUser->hasRightEditProfile($memberUser));
    }

    /**
     * Test that the leader flag is reported for the led role only
     *
     * @testdox isLeaderOfRole reports the role the user leads
     */
    public function testIsLeaderOfRoleReportsTheLedRole(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Leader Org', 'leadflag');
        $ledRole = $fixture->createAndSaveRoleWithRights('Led Role', $org['org_id']);
        $plainRole = $fixture->createAndSaveRoleWithRights('Plain Role', $org['org_id']);

        $user = $fixture->createAndSaveUser('flagleader', 'fl@example.local');
        $fixture->assignUserToRolePeriod($user['usr_id'], $ledRole['rol_id'], date('Y-m-d'), '9999-12-31', true);
        $fixture->assignUserToRole($user['usr_id'], $plainRole['rol_id']);

        $leaderUser = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);
        $leaderUser->checkRolesRight();

        $this->assertTrue($leaderUser->isLeaderOfRole($ledRole['rol_id']));
        $this->assertFalse($leaderUser->isLeaderOfRole($plainRole['rol_id']));
    }

    /**
     * Test that removing the membership removes the right
     *
     * @testdox Ending a membership removes the rights it granted
     */
    public function testEndingMembershipRemovesTheRight(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Revoke Org', 'revoke');
        $role = $fixture->createAndSaveRoleWithRights('Link Admins', $org['org_id'], ['rol_weblinks' => 1]);
        $user = $fixture->createAndSaveUser('revokeuser', 'rev@example.local');

        $membership = $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
        $this->assertTrue(
            $this->loadUserInOrganization($user['usr_id'], $org['org_id'])->isAdministratorWeblinks()
        );

        // delete the membership through its entity, so the change stays in the audit trail
        $this->assertTrue($fixture->deleteMembership($membership['mem_id']));

        $this->assertFalse($fixture->membershipExists($membership['mem_id']));
        $this->assertFalse(
            $this->loadUserInOrganization($user['usr_id'], $org['org_id'])->isAdministratorWeblinks()
        );
    }
}
