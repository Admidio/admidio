<?php
/**
 * Event Participation Workflow Tests
 *
 * Tests an event that people can sign up for. Participation is not a table of its own: the event
 * points at a role through dat_rol_id, and everybody who is a member of that role is a participant.
 * The approval state of the membership records whether the participant said yes, maybe or no.
 *
 * The participation role is built here the way EventService builds it, so that the tests do not
 * depend on the form values the service expects.
 */

namespace Admidio\Tests\Integration\Workflows;

use Admidio\Events\Entity\Event;
use Admidio\Events\ValueObject\Participants;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class EventParticipationWorkflowTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation.
     */
    private const ORG_ID = 1;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * The administrator of the installed organization.
     */
    private function administrator(): User
    {
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $usrId = (int) $this->getDatabase()->queryPrepared($sql, ['admin'])->fetchColumn();

        return new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);
    }

    /**
     * Run a callback as the administrator of the installed organization.
     */
    private function asAdministrator(callable $callback)
    {
        return $this->withCurrentUser($this->administrator(), self::ORG_ID, true, $callback);
    }

    /**
     * The id of a category of the installed organization.
     */
    private function categoryId(string $type): int
    {
        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_type = ? AND cat_org_id = ? ORDER BY cat_id';

        return (int) $this->getDatabase()->queryPrepared($sql, [$type, self::ORG_ID])->fetchColumn();
    }

    /**
     * The category that holds the roles of the events.
     */
    private function eventRoleCategoryId(): int
    {
        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . " WHERE cat_name_intern = 'EVENTS' AND cat_org_id = ?";

        return (int) $this->getDatabase()->queryPrepared($sql, [self::ORG_ID])->fetchColumn();
    }

    /**
     * Create an event that people can sign up for.
     *
     * @return array{dat_id: int, rol_id: int}
     */
    private function createEventWithParticipation(string $headline, string $begin, int $maxMembers = 0): array
    {
        $db = $this->getDatabase();

        $event = new Event($db);
        $event->saveChangesWithoutRights();
        $event->setValue('dat_cat_id', $this->categoryId('EVT'));
        $event->setValue('dat_headline', $headline);
        $event->setValue('dat_begin', $begin . ' 09:00:00');
        $event->setValue('dat_end', $begin . ' 17:00:00');
        $event->setValue('dat_max_members', $maxMembers);
        $event->save();

        $role = new Role($db);
        $role->saveChangesWithoutRights();
        $role->setType(Role::ROLE_EVENT);
        $role->setValue('rol_cat_id', $this->eventRoleCategoryId());
        $role->setValue('rol_name', $headline . ' participants');
        $role->save();

        $event->setValue('dat_rol_id', (int) $role->getValue('rol_id'));
        $event->save();

        return array('dat_id' => (int) $event->getValue('dat_id'), 'rol_id' => (int) $role->getValue('rol_id'));
    }

    /**
     * Sign a user up for the participation role of an event.
     */
    private function signUp(int $rolId, int $usrId, ?bool $leader = null): void
    {
        $role = new Role($this->getDatabase(), $rolId);
        $role->startMembership($usrId, $leader);
    }

    /**
     * Test that participation is expressed through a role
     *
     * @testdox An event that people can sign up for points at a participation role
     */
    public function testAnEventWithParticipationPointsAtARole(): void
    {
        $event = $this->asAdministrator(fn () => $this->createEventWithParticipation('Summer camp', '2030-07-01'));

        $sql = 'SELECT dat_rol_id, dat_headline FROM ' . TBL_EVENTS . ' WHERE dat_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$event['dat_id']])->fetch();
        $this->assertEquals($event['rol_id'], (int) $row['dat_rol_id']);

        // the role lives in the category that is reserved for the events of the organization
        $sql = 'SELECT cat_name_intern FROM ' . TBL_ROLES . ' INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                 WHERE rol_id = ?';
        $this->assertEquals('EVENTS', $this->getDatabase()->queryPrepared($sql, [$event['rol_id']])->fetchColumn());
    }

    /**
     * Test that signing up makes somebody a participant
     *
     * @testdox Signing up for an event makes the user a member of its participation role
     */
    public function testSigningUpMakesTheUserAParticipant(): void
    {
        $fixture = $this->getFixture();
        $participant = $fixture->createAndSaveUser('part1', 'p1@example.local');

        $count = $this->asAdministrator(function () use ($participant) {
            $event = $this->createEventWithParticipation('Summer camp', '2030-07-01');
            $this->signUp($event['rol_id'], $participant['usr_id']);

            $participants = new Participants($this->getDatabase(), $event['rol_id']);

            return array(
                'count' => $participants->getCount(),
                'isMember' => $participants->isMemberOfEvent($participant['usr_id']),
                'rol_id' => $event['rol_id']
            );
        });

        $this->assertEquals(1, $count['count']);
        $this->assertTrue($count['isMember']);

        $sql = 'SELECT mem_begin, mem_end FROM ' . TBL_MEMBERS . ' WHERE mem_rol_id = ? AND mem_usr_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$count['rol_id'], $participant['usr_id']])->fetch();
        $this->assertEquals(DATE_NOW, $row['mem_begin']);
    }

    /**
     * Test that leaders are counted separately
     *
     * @testdox The participant count does not include the leaders of the event
     */
    public function testParticipantCountDoesNotIncludeTheLeaders(): void
    {
        $fixture = $this->getFixture();
        $leader = $fixture->createAndSaveUser('lead1', 'l1@example.local');
        $memberA = $fixture->createAndSaveUser('part1', 'p1@example.local');
        $memberB = $fixture->createAndSaveUser('part2', 'p2@example.local');

        $result = $this->asAdministrator(function () use ($leader, $memberA, $memberB) {
            $event = $this->createEventWithParticipation('Summer camp', '2030-07-01');
            $this->signUp($event['rol_id'], $leader['usr_id'], true);
            $this->signUp($event['rol_id'], $memberA['usr_id']);
            $this->signUp($event['rol_id'], $memberB['usr_id']);

            $participants = new Participants($this->getDatabase(), $event['rol_id']);

            return array(
                'count' => $participants->getCount(),
                'leaders' => $participants->getNumLeaders(),
                'isLeader' => $participants->isLeader($leader['usr_id'])
            );
        });

        $this->assertEquals(2, $result['count']);
        $this->assertEquals(1, $result['leaders']);
        $this->assertTrue($result['isLeader']);
    }

    /**
     * Test that the limit of an event is respected
     *
     * @testdox An event reports when its participant limit is reached
     */
    public function testEventReportsWhenItsParticipantLimitIsReached(): void
    {
        $fixture = $this->getFixture();
        $memberA = $fixture->createAndSaveUser('part1', 'p1@example.local');
        $memberB = $fixture->createAndSaveUser('part2', 'p2@example.local');

        $reached = $this->asAdministrator(function () use ($memberA, $memberB) {
            $event = $this->createEventWithParticipation('Small trip', '2030-07-01', 2);

            $this->signUp($event['rol_id'], $memberA['usr_id']);
            $afterFirst = (new Event($this->getDatabase(), $event['dat_id']))->participantLimitReached();

            $this->signUp($event['rol_id'], $memberB['usr_id']);
            $afterSecond = (new Event($this->getDatabase(), $event['dat_id']))->participantLimitReached();

            return array($afterFirst, $afterSecond);
        });

        $this->assertFalse($reached[0]);
        $this->assertTrue($reached[1]);
    }

    /**
     * Test that an event without a limit never fills up
     *
     * @testdox An event without a limit never reports its limit as reached
     */
    public function testEventWithoutALimitNeverFillsUp(): void
    {
        $fixture = $this->getFixture();
        $memberA = $fixture->createAndSaveUser('part1', 'p1@example.local');
        $memberB = $fixture->createAndSaveUser('part2', 'p2@example.local');

        $reached = $this->asAdministrator(function () use ($memberA, $memberB) {
            $event = $this->createEventWithParticipation('Open day', '2030-07-01', 0);
            $this->signUp($event['rol_id'], $memberA['usr_id']);
            $this->signUp($event['rol_id'], $memberB['usr_id']);

            return (new Event($this->getDatabase(), $event['dat_id']))->participantLimitReached();
        });

        $this->assertFalse($reached);
    }

    /**
     * Test that a participant who declines drops out of the count
     *
     * @testdox A participant who declines is no longer counted
     */
    public function testAParticipantWhoDeclinesIsNoLongerCounted(): void
    {
        $fixture = $this->getFixture();
        $memberA = $fixture->createAndSaveUser('part1', 'p1@example.local');
        $memberB = $fixture->createAndSaveUser('part2', 'p2@example.local');

        $result = $this->asAdministrator(function () use ($memberA, $memberB) {
            $event = $this->createEventWithParticipation('Summer camp', '2030-07-01');
            $this->signUp($event['rol_id'], $memberA['usr_id']);
            $this->signUp($event['rol_id'], $memberB['usr_id']);

            $before = (new Participants($this->getDatabase(), $event['rol_id']))->getCount();

            $membership = new Membership($this->getDatabase());
            $membership->readDataByColumns(array('mem_rol_id' => $event['rol_id'], 'mem_usr_id' => $memberB['usr_id']));
            $membership->setValue('mem_approved', Participants::PARTICIPATION_NO);
            $membership->save();

            $after = new Participants($this->getDatabase(), $event['rol_id']);

            return array(
                'before' => $before,
                'after' => $after->getCount(),
                'stillMember' => $after->isMemberOfEvent($memberB['usr_id'])
            );
        });

        $this->assertEquals(2, $result['before']);
        $this->assertEquals(1, $result['after']);

        // the membership stays so that the refusal is recorded, but the user is not a participant
        $this->assertFalse($result['stillMember']);
    }

    /**
     * Test that the participants can be listed with their answer
     *
     * @testdox The participants of an event are listed with their approval state
     */
    public function testParticipantsAreListedWithTheirApprovalState(): void
    {
        $fixture = $this->getFixture();
        $memberA = $fixture->createAndSaveUser('part1', 'p1@example.local');
        $memberB = $fixture->createAndSaveUser('part2', 'p2@example.local');

        $participants = $this->asAdministrator(function () use ($memberA, $memberB) {
            $event = $this->createEventWithParticipation('Summer camp', '2030-07-01');
            $this->signUp($event['rol_id'], $memberA['usr_id']);
            $this->signUp($event['rol_id'], $memberB['usr_id'], true);

            $membership = new Membership($this->getDatabase());
            $membership->readDataByColumns(array('mem_rol_id' => $event['rol_id'], 'mem_usr_id' => $memberA['usr_id']));
            $membership->setValue('mem_approved', Participants::PARTICIPATION_MAYBE);
            $membership->save();

            return (new Participants($this->getDatabase(), $event['rol_id']))->getParticipantsArray();
        });

        $this->assertArrayHasKey($memberA['usr_id'], $participants);
        $this->assertArrayHasKey($memberB['usr_id'], $participants);
        $this->assertEquals(Participants::PARTICIPATION_MAYBE, $participants[$memberA['usr_id']]['approved']);
        $this->assertFalse($participants[$memberA['usr_id']]['leader']);
        $this->assertTrue($participants[$memberB['usr_id']]['leader']);
    }

    /**
     * Test that the deadline closes the sign up
     *
     * @testdox The deadline of an event decides whether it is still open
     */
    public function testTheDeadlineDecidesWhetherTheEventIsStillOpen(): void
    {
        $open = $this->asAdministrator(function () {
            $event = $this->createEventWithParticipation('Future trip', '2030-07-01');

            return (new Event($this->getDatabase(), $event['dat_id']))->deadlineExceeded();
        });
        $this->assertFalse($open);

        $past = $this->asAdministrator(function () {
            $event = $this->createEventWithParticipation('Past trip', '2020-07-01');

            return (new Event($this->getDatabase(), $event['dat_id']))->deadlineExceeded();
        });
        $this->assertTrue($past);

        // an explicit deadline is used instead of the start of the event
        $withDeadline = $this->asAdministrator(function () {
            $event = $this->createEventWithParticipation('Trip with deadline', '2030-07-01');
            $entity = new Event($this->getDatabase(), $event['dat_id']);
            $entity->saveChangesWithoutRights();
            // a deadline is accepted in the format Y-m-d H:i, seconds are refused
            $entity->setValue('dat_deadline', '2020-06-01 00:00');
            $entity->save();

            return (new Event($this->getDatabase(), $event['dat_id']))->deadlineExceeded();
        });
        $this->assertTrue($withDeadline);
    }

    /**
     * Test that deleting the event cleans up the participation
     *
     * @testdox Deleting an event deletes its participation role and the sign ups
     */
    public function testDeletingAnEventDeletesItsParticipationRole(): void
    {
        $fixture = $this->getFixture();
        $participant = $fixture->createAndSaveUser('part1', 'p1@example.local');

        $rolId = $this->asAdministrator(function () use ($participant) {
            $event = $this->createEventWithParticipation('Summer camp', '2030-07-01');
            $this->signUp($event['rol_id'], $participant['usr_id']);

            $entity = new Event($this->getDatabase(), $event['dat_id']);
            $entity->delete();

            return $event['rol_id'];
        });

        $db = $this->getDatabase();
        $this->assertFalse($db->queryPrepared('SELECT rol_id FROM ' . TBL_ROLES . ' WHERE rol_id = ?', [$rolId])->fetch());
        $this->assertEquals(0, (int) $db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_MEMBERS . ' WHERE mem_rol_id = ?', [$rolId])->fetchColumn());
    }
}
