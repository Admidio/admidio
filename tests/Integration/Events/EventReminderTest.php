<?php
/**
 * Event reminder tests
 *
 * Reminder state is stored per event and user. The tests cover duplicate suppression,
 * skipping confirmed or declined participants, the due window, invited event-role members
 * and filtering disabled or cancelled events.
 */

namespace Admidio\Tests\Integration\Events;

use Admidio\Events\Entity\Event;
use Admidio\Events\Repository\EventReminderRepository;
use Admidio\Events\Service\EventReminderService;
use Admidio\Events\ValueObject\Participants;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;
use Ramsey\Uuid\Uuid;

class EventReminderTest extends DatabaseTestCase
{
    use PermissionContext;

    private const ORG_ID = 1;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    private function administrator(): User
    {
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $usrId = (int) $this->getDatabase()->queryPrepared($sql, ['admin'])->fetchColumn();

        return new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);
    }

    private function asAdministrator(callable $callback)
    {
        return $this->withCurrentUser($this->administrator(), self::ORG_ID, true, $callback);
    }

    private function categoryId(string $type): int
    {
        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_type = ? AND cat_org_id = ? ORDER BY cat_id';

        return (int) $this->getDatabase()->queryPrepared($sql, [$type, self::ORG_ID])->fetchColumn();
    }

    private function eventRoleCategoryId(): int
    {
        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . " WHERE cat_name_intern = 'EVENTS' AND cat_org_id = ?";

        return (int) $this->getDatabase()->queryPrepared($sql, [self::ORG_ID])->fetchColumn();
    }

    /**
     * @return array{dat_id: int, rol_id: int}
     */
    private function createEventWithReminder(string $headline, string $begin, int $reminderDays = 3): array
    {
        return $this->asAdministrator(function () use ($headline, $begin, $reminderDays) {
            $db = $this->getDatabase();

            $event = new Event($db);
            $event->saveChangesWithoutRights();
            $event->setValue('dat_cat_id', $this->categoryId('EVT'));
            $event->setValue('dat_headline', $headline);
            $event->setValue('dat_begin', $begin . ' 09:00:00');
            $event->setValue('dat_end', $begin . ' 17:00:00');
            $event->setValue('dat_reminder_days', $reminderDays);
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
        });
    }

    /**
     * @return array{usr_id: int, usr_uuid: string, usr_login_name: string, org_id: int}
     */
    private function createUserWithEmail(AdmidioTestFixture $fixture, string $login, string $email): array
    {
        $user = $fixture->createAndSaveUser($login, $email);
        $emailFieldId = (int) $this->getDatabase()->queryPrepared(
            'SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_name_intern = ?',
            ['EMAIL']
        )->fetchColumn();

        $this->getDatabase()->queryPrepared(
            'INSERT INTO ' . TBL_USER_DATA . ' (usd_usr_id, usd_usf_id, usd_value) VALUES (?, ?, ?)',
            [$user['usr_id'], $emailFieldId, $email]
        );

        return $user;
    }

    private function setParticipationMembership(int $roleId, int $userId, int $approvalState): void
    {
        $membership = new Membership($this->getDatabase());
        $membership->startMembership($roleId, $userId, null, $approvalState);
    }

    private function runService(): ?string
    {
        return $this->asAdministrator(function (): ?string {
            return (new EventReminderService($this->getDatabase()))->runIfDue();
        });
    }

    /**
     * @testdox A due reminder is sent once per user and persisted
     */
    public function testReminderIsSentOncePerUser(): void
    {
        $fixture = $this->getFixture();
        $eligibleRole = $fixture->createAndSaveRole('Reminder eligible', self::ORG_ID);
        $alice = $this->createUserWithEmail($fixture, 'reminderAlice', 'alice@example.local');
        $fixture->assignUserToRole($alice['usr_id'], $eligibleRole['rol_id']);

        $event = $this->createEventWithReminder('Reminder event', date('Y-m-d', strtotime(DATE_NOW . ' +3 days')));
        $this->asAdministrator(function () use ($event, $eligibleRole) {
            (new RolesRights($this->getDatabase(), 'event_participation', $event['dat_id']))
                ->addRoles([$eligibleRole['rol_id']]);
        });

        $previousDisableEmailSending = $GLOBALS['gDisableEmailSending'] ?? null;
        $GLOBALS['gDisableEmailSending'] = true;

        try {
            $this->assertSame('Sent 1 reminder(s) for 1 event(s).', $this->runService());

            $sql = 'SELECT COUNT(*) FROM ' . TBL_EVENT_REMINDER_SENT . ' WHERE ers_dat_id = ? AND ers_usr_id = ?';
            $this->assertSame(1, (int) $this->getDatabase()->queryPrepared($sql, [$event['dat_id'], $alice['usr_id']])->fetchColumn());

            $this->assertSame('Sent 0 reminder(s) for 1 event(s).', $this->runService());
            $this->assertSame(1, (int) $this->getDatabase()->queryPrepared($sql, [$event['dat_id'], $alice['usr_id']])->fetchColumn());
        } finally {
            if ($previousDisableEmailSending === null) {
                unset($GLOBALS['gDisableEmailSending']);
            } else {
                $GLOBALS['gDisableEmailSending'] = $previousDisableEmailSending;
            }
        }
    }

    /**
     * @testdox A dry run counts due reminders without sending or persisting state
     */
    public function testDryRunDoesNotSendOrPersistState(): void
    {
        $fixture = $this->getFixture();
        $eligibleRole = $fixture->createAndSaveRole('Reminder dry run', self::ORG_ID);
        $alice = $this->createUserWithEmail($fixture, 'dryRunAlice', 'dry-run-alice@example.local');
        $fixture->assignUserToRole($alice['usr_id'], $eligibleRole['rol_id']);

        $event = $this->createEventWithReminder('Dry run event', date('Y-m-d', strtotime(DATE_NOW . ' +3 days')));
        $this->asAdministrator(function () use ($event, $eligibleRole) {
            (new RolesRights($this->getDatabase(), 'event_participation', $event['dat_id']))
                ->addRoles([$eligibleRole['rol_id']]);
        });

        $result = $this->asAdministrator(function (): ?string {
            return (new EventReminderService($this->getDatabase()))->runIfDue(true);
        });

        $this->assertSame('Would send 1 reminder(s) for 1 event(s).', $result);
        $sql = 'SELECT COUNT(*) FROM ' . TBL_EVENT_REMINDER_SENT . ' WHERE ers_dat_id = ?';
        $this->assertSame(0, (int) $this->getDatabase()->queryPrepared($sql, [$event['dat_id']])->fetchColumn());
    }

    /**
     * @testdox Confirmed and declined participants do not receive a reminder
     */
    public function testConfirmedAndDeclinedParticipantsAreSkipped(): void
    {
        $fixture = $this->getFixture();
        $eligibleRole = $fixture->createAndSaveRole('Reminder statuses', self::ORG_ID);
        $alice = $this->createUserWithEmail($fixture, 'statusAlice', 'status-alice@example.local');
        $bob = $this->createUserWithEmail($fixture, 'statusBob', 'status-bob@example.local');
        $carol = $this->createUserWithEmail($fixture, 'statusCarol', 'status-carol@example.local');

        foreach ([$alice, $bob, $carol] as $user) {
            $fixture->assignUserToRole($user['usr_id'], $eligibleRole['rol_id']);
        }

        $event = $this->createEventWithReminder('Reminder statuses event', date('Y-m-d', strtotime(DATE_NOW . ' +3 days')));
        $this->asAdministrator(function () use ($event, $eligibleRole, $bob, $carol) {
            (new RolesRights($this->getDatabase(), 'event_participation', $event['dat_id']))
                ->addRoles([$eligibleRole['rol_id']]);
            $this->setParticipationMembership($event['rol_id'], $bob['usr_id'], Participants::STATE_ATTENDING);
            $this->setParticipationMembership($event['rol_id'], $carol['usr_id'], Participants::STATE_REFUSED);
        });

        $previousDisableEmailSending = $GLOBALS['gDisableEmailSending'] ?? null;
        $GLOBALS['gDisableEmailSending'] = true;

        try {
            $this->assertSame('Sent 1 reminder(s) for 1 event(s).', $this->runService());

            $sql = 'SELECT ers_usr_id FROM ' . TBL_EVENT_REMINDER_SENT . ' WHERE ers_dat_id = ?';
            $remindedUserIds = array_map(
                'intval',
                $this->getDatabase()->queryPrepared($sql, [$event['dat_id']])->fetchAll(\PDO::FETCH_COLUMN)
            );

            $this->assertSame([$alice['usr_id']], $remindedUserIds);
        } finally {
            if ($previousDisableEmailSending === null) {
                unset($GLOBALS['gDisableEmailSending']);
            } else {
                $GLOBALS['gDisableEmailSending'] = $previousDisableEmailSending;
            }
        }
    }

    /**
     * @testdox An invited member of the event role is reminded even without an authorized role
     */
    public function testInvitedEventRoleMemberIsReminded(): void
    {
        $fixture = $this->getFixture();
        $alice = $this->createUserWithEmail($fixture, 'invitedAlice', 'invited-alice@example.local');

        $event = $this->createEventWithReminder('Invited reminder event', date('Y-m-d', strtotime(DATE_NOW . ' +3 days')));
        $this->asAdministrator(function () use ($event, $alice) {
            $this->setParticipationMembership($event['rol_id'], $alice['usr_id'], Participants::STATE_INVITED);
        });

        $previousDisableEmailSending = $GLOBALS['gDisableEmailSending'] ?? null;
        $GLOBALS['gDisableEmailSending'] = true;

        try {
            $this->assertSame('Sent 1 reminder(s) for 1 event(s).', $this->runService());

            $sql = 'SELECT COUNT(*) FROM ' . TBL_EVENT_REMINDER_SENT . ' WHERE ers_dat_id = ? AND ers_usr_id = ?';
            $this->assertSame(1, (int) $this->getDatabase()->queryPrepared($sql, [$event['dat_id'], $alice['usr_id']])->fetchColumn());
        } finally {
            if ($previousDisableEmailSending === null) {
                unset($GLOBALS['gDisableEmailSending']);
            } else {
                $GLOBALS['gDisableEmailSending'] = $previousDisableEmailSending;
            }
        }
    }

    /**
     * @testdox The due window catches up missed days and ignores events outside the window
     */
    public function testDueWindowCatchesUpMissedDays(): void
    {
        $insideWindow = $this->createEventWithReminder('Inside window', date('Y-m-d', strtotime(DATE_NOW . ' +1 day')));
        $outsideWindow = $this->createEventWithReminder('Outside window', date('Y-m-d', strtotime(DATE_NOW . ' +5 days')));

        $dueEventIds = (new EventReminderRepository($this->getDatabase()))->getDueEventIds();
        $this->assertContains($insideWindow['dat_id'], $dueEventIds);
        $this->assertNotContains($outsideWindow['dat_id'], $dueEventIds);
    }

    /**
     * @testdox Due events are limited to the current organization
     */
    public function testDueEventsAreLimitedToCurrentOrganization(): void
    {
        $fixture = $this->getFixture();
        $otherOrg = $fixture->createAndSaveOrganization('Other organization', 'OTHER');
        $otherCategory = $fixture->createAndSaveCategory('Other calendar', 'EVT', (int) $otherOrg['org_id']);

        $begin = date('Y-m-d', strtotime(DATE_NOW . ' +3 days')) . ' 09:00:00';
        $end = date('Y-m-d', strtotime(DATE_NOW . ' +3 days')) . ' 17:00:00';
        $eventUuid = (string) Uuid::uuid4();
        $this->getDatabase()->queryPrepared(
            'INSERT INTO ' . TBL_EVENTS . ' (dat_cat_id, dat_uuid, dat_headline, dat_begin, dat_end, dat_reminder_days)
             VALUES (?, ?, ?, ?, ?, ?)',
            [(int) $otherCategory['cat_id'], $eventUuid, 'Other organization reminder', $begin, $end, 3]
        );
        $eventId = (int) $this->getDatabase()->queryPrepared(
            'SELECT dat_id FROM ' . TBL_EVENTS . ' WHERE dat_uuid = ?',
            [$eventUuid]
        )->fetchColumn();

        $dueEventIds = $this->asAdministrator(function (): array {
            return (new EventReminderRepository($this->getDatabase()))->getDueEventIds();
        });
        $this->assertNotContains($eventId, $dueEventIds);
    }

    /**
     * @testdox Disabled and cancelled events are not returned as due
     */
    public function testDisabledAndCancelledEventsAreNotDue(): void
    {
        $disabledEvent = $this->createEventWithReminder('Disabled reminder', date('Y-m-d', strtotime(DATE_NOW . ' +3 days')), 0);
        $cancelledEvent = $this->createEventWithReminder('Cancelled reminder', date('Y-m-d', strtotime(DATE_NOW . ' +3 days')));
        $dueEvent = $this->createEventWithReminder('Due reminder', date('Y-m-d', strtotime(DATE_NOW . ' +3 days')));

        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_EVENTS . ' SET dat_recurrence_status = ? WHERE dat_id = ?',
            ['cancelled', $cancelledEvent['dat_id']]
        );

        $dueEventIds = (new EventReminderRepository($this->getDatabase()))->getDueEventIds();
        $this->assertNotContains($disabledEvent['dat_id'], $dueEventIds);
        $this->assertNotContains($cancelledEvent['dat_id'], $dueEventIds);
        $this->assertContains($dueEvent['dat_id'], $dueEventIds);
    }
}
