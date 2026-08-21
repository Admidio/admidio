<?php
/**
 * Membership Lifecycle Tests
 *
 * Tests a membership over its whole life: starting it, extending it, promoting the member to a
 * leader and ending it again. A membership is a period, not a flag, so ending one usually means
 * setting mem_end rather than removing the row - except when the membership never covered a full
 * day, then it is deleted instead.
 */

namespace Admidio\Tests\Integration\Workflows;

use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class MembershipLifecycleTest extends DatabaseTestCase
{
    /**
     * The organization created by the installation.
     */
    private const ORG_ID = 1;

    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * The administrator of the installed organization.
     */
    private function administrator(): User
    {
        return new User($this->getDatabase(), $GLOBALS['gProfileFields'], 1);
    }

    /**
     * Run a callback as the administrator of the installed organization.
     */
    private function asAdministrator(callable $callback)
    {
        return $this->withCurrentUser($this->administrator(), self::ORG_ID, true, $callback);
    }

    /**
     * The membership rows of a user in a role.
     */
    private function rows(int $rolId, int $usrId): array
    {
        $sql = 'SELECT mem_id, mem_begin, mem_end, mem_leader, mem_approved FROM ' . TBL_MEMBERS . '
                 WHERE mem_rol_id = ? AND mem_usr_id = ? ORDER BY mem_id';

        return $this->getDatabase()->queryPrepared($sql, [$rolId, $usrId])->fetchAll();
    }

    /**
     * Yesterday, the day a membership ends when it is stopped today.
     */
    private function yesterday(): string
    {
        return date('Y-m-d', strtotime('-1 day'));
    }

    /**
     * Test what a started membership looks like
     *
     * @testdox Starting a membership runs it from today until the maximum date
     */
    public function testStartingAMembershipRunsFromTodayUntilTheMaximumDate(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Lifecycle Role', self::ORG_ID);
        $user = $fixture->createAndSaveUser('lifecycle', 'lc@example.local');

        $started = $this->asAdministrator(function () use ($role, $user) {
            $membership = new Membership($this->getDatabase());

            return $membership->startMembership($role['rol_id'], $user['usr_id']);
        });

        $this->assertTrue($started);

        $rows = $this->rows($role['rol_id'], $user['usr_id']);
        $this->assertCount(1, $rows);
        $this->assertEquals(DATE_NOW, $rows[0]['mem_begin']);
        $this->assertEquals(DATE_MAX, $rows[0]['mem_end']);

        // a plain member is neither a leader nor in need of an approval
        $this->assertFalse((bool) $rows[0]['mem_leader']);
        $this->assertNull($rows[0]['mem_approved']);
    }

    /**
     * Test that a membership is not started twice
     *
     * @testdox Starting a membership that already runs changes nothing
     */
    public function testStartingAMembershipThatAlreadyRunsChangesNothing(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Lifecycle Role', self::ORG_ID);
        $user = $fixture->createAndSaveUser('lifecycle', 'lc@example.local');

        $second = $this->asAdministrator(function () use ($role, $user) {
            $first = new Membership($this->getDatabase());
            $first->startMembership($role['rol_id'], $user['usr_id']);

            $again = new Membership($this->getDatabase());

            return $again->startMembership($role['rol_id'], $user['usr_id']);
        });

        // nothing changed, so the second call reports that it did not save
        $this->assertFalse($second);
        $this->assertCount(1, $this->rows($role['rol_id'], $user['usr_id']));
    }

    /**
     * Test that a membership of the same day is removed
     *
     * @testdox Stopping a membership that started today removes it
     */
    public function testStoppingAMembershipThatStartedTodayRemovesIt(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Lifecycle Role', self::ORG_ID);
        $user = $fixture->createAndSaveUser('lifecycle', 'lc@example.local');

        $stopped = $this->asAdministrator(function () use ($role, $user) {
            $start = new Membership($this->getDatabase());
            $start->startMembership($role['rol_id'], $user['usr_id']);

            $stop = new Membership($this->getDatabase());

            return $stop->stopMembership($role['rol_id'], $user['usr_id']);
        });

        $this->assertTrue($stopped);

        // ending it yesterday would put the end before the beginning, so the row goes instead
        $this->assertCount(0, $this->rows($role['rol_id'], $user['usr_id']));
    }

    /**
     * Test that an older membership is ended rather than removed
     *
     * @testdox Stopping an older membership ends it yesterday and keeps the record
     */
    public function testStoppingAnOlderMembershipEndsItYesterday(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Lifecycle Role', self::ORG_ID);
        $user = $fixture->createAndSaveUser('lifecycle', 'lc@example.local');
        $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], '2020-01-01', DATE_MAX);

        $stopped = $this->asAdministrator(function () use ($role, $user) {
            $stop = new Membership($this->getDatabase());

            return $stop->stopMembership($role['rol_id'], $user['usr_id']);
        });

        $this->assertTrue($stopped);

        $rows = $this->rows($role['rol_id'], $user['usr_id']);
        $this->assertCount(1, $rows);
        $this->assertEquals('2020-01-01', $rows[0]['mem_begin']);
        $this->assertEquals($this->yesterday(), $rows[0]['mem_end']);
    }

    /**
     * Test that stopping a membership also ends the leadership
     *
     * @testdox Stopping the membership of a leader clears the leader flag
     */
    public function testStoppingTheMembershipOfALeaderClearsTheLeaderFlag(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Lifecycle Role', self::ORG_ID);
        $user = $fixture->createAndSaveUser('lifecycle', 'lc@example.local');
        $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], '2020-01-01', DATE_MAX, true);

        $this->assertTrue((bool) $this->rows($role['rol_id'], $user['usr_id'])[0]['mem_leader']);

        $this->asAdministrator(function () use ($role, $user) {
            $stop = new Membership($this->getDatabase());
            $stop->stopMembership($role['rol_id'], $user['usr_id']);
        });

        $rows = $this->rows($role['rol_id'], $user['usr_id']);
        $this->assertEquals($this->yesterday(), $rows[0]['mem_end']);
        $this->assertFalse((bool) $rows[0]['mem_leader']);
    }

    /**
     * Test that a member can be started as a leader
     *
     * @testdox A member can be assigned as a leader of the role
     */
    public function testAMemberCanBeAssignedAsALeader(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Lifecycle Role', self::ORG_ID);
        $user = $fixture->createAndSaveUser('lifecycle', 'lc@example.local');

        $this->asAdministrator(function () use ($role, $user) {
            $roleEntity = new Role($this->getDatabase(), $role['rol_id']);
            $roleEntity->startMembership($user['usr_id'], true);
        });

        $rows = $this->rows($role['rol_id'], $user['usr_id']);
        $this->assertCount(1, $rows);
        $this->assertTrue((bool) $rows[0]['mem_leader']);
        $this->assertEquals(DATE_NOW, $rows[0]['mem_begin']);
    }

    /**
     * Test that an ended membership no longer grants rights
     *
     * @testdox A membership that has ended no longer grants the rights of its role
     */
    public function testAnEndedMembershipNoLongerGrantsTheRightsOfItsRole(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRoleWithRights('Lifecycle Rights', self::ORG_ID, ['rol_announcements' => 1]);
        $user = $fixture->createAndSaveUser('lifecycle', 'lc@example.local');
        $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], '2020-01-01', DATE_MAX);

        $before = $this->loadUserInOrganization($user['usr_id'], self::ORG_ID);
        $this->assertTrue($this->withCurrentUser($before, self::ORG_ID, true, fn () => $before->isAdministratorAnnouncements()));

        $this->asAdministrator(function () use ($role, $user) {
            $stop = new Membership($this->getDatabase());
            $stop->stopMembership($role['rol_id'], $user['usr_id']);
        });

        // the rights follow the period, so a fresh object no longer sees the right
        $after = $this->loadUserInOrganization($user['usr_id'], self::ORG_ID);
        $this->assertFalse($this->withCurrentUser($after, self::ORG_ID, true, fn () => $after->isAdministratorAnnouncements()));
    }

    /**
     * Test that the duration of a membership is derived from its period
     *
     * @testdox The duration of a membership is calculated from its period
     */
    public function testTheDurationOfAMembershipIsCalculatedFromItsPeriod(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Lifecycle Role', self::ORG_ID);
        $user = $fixture->createAndSaveUser('lifecycle', 'lc@example.local');
        $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], '2020-01-01', '2021-03-16');

        $duration = $this->asAdministrator(function () use ($role, $user) {
            $membership = new Membership($this->getDatabase());
            $membership->readDataByColumns(array('mem_rol_id' => $role['rol_id'], 'mem_usr_id' => $user['usr_id']));

            return $membership->calculateDuration('2020-01-01', '2021-03-15');
        });

        $this->assertEquals(1, $duration['years']);
        $this->assertEquals(2, $duration['months']);
        $this->assertEquals(15, $duration['days']);
    }

    /**
     * Test that a membership can be removed outright
     *
     * @testdox Deleting a membership removes it without leaving a period behind
     */
    public function testDeletingAMembershipRemovesIt(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Lifecycle Role', self::ORG_ID);
        $user = $fixture->createAndSaveUser('lifecycle', 'lc@example.local');
        $membership = $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], '2020-01-01', DATE_MAX);

        $this->assertCount(1, $this->rows($role['rol_id'], $user['usr_id']));

        $this->asAdministrator(function () use ($membership) {
            $entity = new Membership($this->getDatabase(), $membership['mem_id']);
            $entity->delete();
        });

        $this->assertCount(0, $this->rows($role['rol_id'], $user['usr_id']));
    }
}
