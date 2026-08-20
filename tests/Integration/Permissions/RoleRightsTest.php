<?php
/**
 * Role Rights Tests
 *
 * Tests that the right columns of a role reach the members of that role, and only them.
 */

namespace Admidio\Tests\Integration\Permissions;

use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;

class RoleRightsTest extends DatabaseTestCase
{
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test that a new role carries no rights
     *
     * @testdox A new role grants none of the module rights
     */
    public function testNewRoleHasNoRights(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Rights Org', 'rights');
        $role = $fixture->createAndSaveRoleWithRights('Plain', $org['org_id']);
        $user = $fixture->createAndSaveUser('plainuser', 'plain@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        $member = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        $this->assertFalse($member->isAdministrator());
        $this->assertFalse($member->isAdministratorUsers());
        $this->assertFalse($member->isAdministratorRoles());
        $this->assertFalse($member->isAdministratorAnnouncements());
        $this->assertFalse($member->isAdministratorWeblinks());
    }

    /**
     * Test that a right on the role reaches its members
     *
     * @testdox A right granted by a role reaches the members of that role
     */
    public function testRoleRightReachesItsMembers(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Rights Org', 'rights');
        $role = $fixture->createAndSaveRoleWithRights('User Admins', $org['org_id'], ['rol_edit_user' => 1]);
        $user = $fixture->createAndSaveUser('useradmin', 'useradmin@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        $member = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        $this->assertTrue($member->isAdministratorUsers());

        // only the right that was granted, not the neighbouring ones
        $this->assertFalse($member->isAdministratorRoles());
        $this->assertFalse($member->isAdministratorWeblinks());
    }

    /**
     * Test that the right stops at the role boundary
     *
     * @testdox A user outside the role does not get its right
     */
    public function testRightDoesNotReachNonMembers(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Rights Org', 'rights');
        $role = $fixture->createAndSaveRoleWithRights('User Admins', $org['org_id'], ['rol_edit_user' => 1]);

        $member = $fixture->createAndSaveUser('insider', 'in@example.local');
        $outsider = $fixture->createAndSaveUser('outsider', 'out@example.local');
        $fixture->assignUserToRole($member['usr_id'], $role['rol_id']);

        $this->assertTrue(
            $this->loadUserInOrganization($member['usr_id'], $org['org_id'])->isAdministratorUsers()
        );
        $this->assertFalse(
            $this->loadUserInOrganization($outsider['usr_id'], $org['org_id'])->isAdministratorUsers()
        );
    }

    /**
     * Test that rights of several roles are combined
     *
     * @testdox Rights of several roles are combined for a user
     */
    public function testRightsOfSeveralRolesCombine(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Rights Org', 'rights');

        $userRole = $fixture->createAndSaveRoleWithRights('User Admins', $org['org_id'], ['rol_edit_user' => 1]);
        $linkRole = $fixture->createAndSaveRoleWithRights('Link Admins', $org['org_id'], ['rol_weblinks' => 1]);

        $user = $fixture->createAndSaveUser('bothroles', 'both@example.local');
        $fixture->assignUserToRole($user['usr_id'], $userRole['rol_id']);
        $fixture->assignUserToRole($user['usr_id'], $linkRole['rol_id']);

        $member = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        // the union of both roles, and nothing beyond it
        $this->assertTrue($member->isAdministratorUsers());
        $this->assertTrue($member->isAdministratorWeblinks());
        $this->assertFalse($member->isAdministratorAnnouncements());
    }

    /**
     * Test the meaning of the administrator flag.
     * rol_administrator marks the user as administrator of the organization, but the module
     * rights are separate columns: the flag alone does not grant them.
     *
     * @testdox rol_administrator sets the administrator flag but not the module rights
     */
    public function testAdministratorFlagIsSeparateFromModuleRights(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Rights Org', 'rights');
        $role = $fixture->createAndSaveRoleWithRights('Admins', $org['org_id'], ['rol_administrator' => 1]);
        $user = $fixture->createAndSaveUser('theadmin', 'admin@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        $member = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        $this->assertTrue($member->isAdministrator());
        $this->assertFalse($member->isAdministratorUsers());
        $this->assertFalse($member->isAdministratorRoles());
    }

    /**
     * Test that an expired membership grants nothing
     *
     * @testdox An expired membership grants no rights
     */
    public function testExpiredMembershipGrantsNoRights(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Rights Org', 'rights');
        $role = $fixture->createAndSaveRoleWithRights('Link Admins', $org['org_id'], ['rol_weblinks' => 1]);
        $user = $fixture->createAndSaveUser('former', 'former@example.local');

        $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], '2020-01-01', '2020-12-31');

        $member = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);
        $this->assertFalse($member->isAdministratorWeblinks());
    }

    /**
     * Test that a membership that has not started yet grants nothing
     *
     * @testdox A membership that starts in the future grants no rights
     */
    public function testFutureMembershipGrantsNoRights(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Rights Org', 'rights');
        $role = $fixture->createAndSaveRoleWithRights('Link Admins', $org['org_id'], ['rol_weblinks' => 1]);
        $user = $fixture->createAndSaveUser('future', 'future@example.local');

        $begin = date('Y-m-d', strtotime('+1 month'));
        $end = date('Y-m-d', strtotime('+2 months'));
        $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], $begin, $end);

        $member = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);
        $this->assertFalse($member->isAdministratorWeblinks());
    }

    /**
     * Test that an inactive role grants nothing
     *
     * @testdox A role that is no longer valid grants no rights
     */
    public function testInvalidRoleGrantsNoRights(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Rights Org', 'rights');
        $role = $fixture->createAndSaveRoleWithRights('Link Admins', $org['org_id'], ['rol_weblinks' => 1]);
        $user = $fixture->createAndSaveUser('stillmember', 'still@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        // the right applies while the role is valid
        $this->assertTrue(
            $this->loadUserInOrganization($user['usr_id'], $org['org_id'])->isAdministratorWeblinks()
        );

        $fixture->setRoleValidity($role['rol_id'], false);

        // and is gone once the role is deactivated, although the membership still exists
        $this->assertFalse(
            $this->loadUserInOrganization($user['usr_id'], $org['org_id'])->isAdministratorWeblinks()
        );
    }

    /**
     * Test that rights do not cross the organization boundary
     *
     * @testdox A right granted in one organization does not apply in another
     */
    public function testRightsAreScopedToTheOrganization(): void
    {
        $fixture = $this->getFixture();
        $orgA = $fixture->createAndSaveOrganization('Org A', 'rightsa');
        $orgB = $fixture->createAndSaveOrganization('Org B', 'rightsb');

        $role = $fixture->createAndSaveRoleWithRights('User Admins', $orgA['org_id'], ['rol_edit_user' => 1]);
        $user = $fixture->createAndSaveUser('scoped', 'scoped@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        // the very same user and membership, resolved against two organizations
        $this->assertTrue(
            $this->loadUserInOrganization($user['usr_id'], $orgA['org_id'])->isAdministratorUsers()
        );
        $this->assertFalse(
            $this->loadUserInOrganization($user['usr_id'], $orgB['org_id'])->isAdministratorUsers()
        );
    }
}
