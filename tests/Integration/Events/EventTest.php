<?php
/**
 * Event Tests
 *
 * Tests creating, reading and deleting events, and the category rights that guard them.
 */

namespace Admidio\Tests\Integration\Events;

use Admidio\Events\Entity\Event;
use Admidio\Events\Repository\EventRecurrenceRepository;
use Admidio\Events\Service\EventService;
use Admidio\Events\ValueObject\EventRecurrenceRule;
use Admidio\Infrastructure\Exception;
use Admidio\Hooks\Hooks;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class EventTest extends DatabaseTestCase
{
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Create an event in the given category as the given user.
     */
    private function createEvent(User $author, int $orgId, int $catId, string $headline, string $begin, string $end): int
    {
        return $this->withCurrentUser($author, $orgId, true, function () use ($catId, $headline, $begin, $end) {
            $event = new Event($this->getDatabase());
            $event->setValue('dat_cat_id', $catId);
            $event->setValue('dat_headline', $headline);
            $event->setValue('dat_begin', $begin);
            $event->setValue('dat_end', $end);
            $event->save();

            return (int) $event->getValue('dat_id');
        });
    }

    /**
     * Build an event administrator of the given organization.
     *
     * @return array{0: User, 1: array} The user object and the fixture record
     */
    private function makeEventAdmin(AdmidioTestFixture $fixture, int $orgId, string $login): array
    {
        $role = $fixture->createAndSaveRoleWithRights('Event Admins', $orgId, ['rol_events' => 1]);
        $user = $fixture->createAndSaveUser($login, $login . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        return [$this->loadUserInOrganization($user['usr_id'], $orgId), $user];
    }

    /**
     * Test that an event administrator can create an event
     *
     * @testdox An event administrator can create an event in a category of their organization
     */
    public function testEventAdministratorCanCreateEvent(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Event Org', 'evtorg');
        $category = $fixture->createAndSaveCategory('Calendar', 'EVT', $org['org_id']);
        [$adminUser] = $this->makeEventAdmin($fixture, $org['org_id'], 'evtadmin');

        $eventId = $this->createEvent(
            $adminUser,
            $org['org_id'],
            $category['cat_id'],
            'Annual Meeting',
            '2030-05-01 18:00:00',
            '2030-05-01 20:00:00'
        );

        $this->assertGreaterThan(0, $eventId);
    }

    /**
     * Test that creating an event without the right is refused
     *
     * @testdox Creating an event without the events right is refused
     */
    public function testCreatingEventWithoutRightIsRefused(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Event Org', 'evtorg');
        $category = $fixture->createAndSaveCategory('Calendar', 'EVT', $org['org_id']);

        $stranger = $fixture->createAndSaveUser('evtstranger', 'es@example.local');
        $strangerUser = $this->loadUserInOrganization($stranger['usr_id'], $org['org_id']);

        $this->expectException(Exception::class);

        $this->createEvent(
            $strangerUser,
            $org['org_id'],
            $category['cat_id'],
            'Forbidden Meeting',
            '2030-05-01 18:00:00',
            '2030-05-01 20:00:00'
        );
    }

    /**
     * Test that the event data is stored as given
     *
     * @testdox An event is stored with its category, headline and period
     */
    public function testEventIsStoredWithItsData(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Event Org', 'evtorg');
        $category = $fixture->createAndSaveCategory('Calendar', 'EVT', $org['org_id']);
        [$adminUser, $admin] = $this->makeEventAdmin($fixture, $org['org_id'], 'evtadmin');

        $eventId = $this->createEvent(
            $adminUser,
            $org['org_id'],
            $category['cat_id'],
            'Summer Camp',
            '2030-07-01 09:00:00',
            '2030-07-05 17:00:00'
        );

        $sql = 'SELECT dat_cat_id, dat_headline, dat_begin, dat_end, dat_usr_id_create
                  FROM ' . TBL_EVENTS . ' WHERE dat_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$eventId])->fetch();

        $this->assertEquals($category['cat_id'], (int) $row['dat_cat_id']);
        $this->assertEquals('Summer Camp', $row['dat_headline']);
        $this->assertEquals('2030-07-01 09:00:00', $row['dat_begin']);
        $this->assertEquals('2030-07-05 17:00:00', $row['dat_end']);
        $this->assertEquals($admin['usr_id'], (int) $row['dat_usr_id_create']);
    }

    /**
     * Test that an event can be read back through its entity
     *
     * @testdox An event can be read back through the Event entity
     */
    public function testEventCanBeReadBack(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Event Org', 'evtorg');
        $category = $fixture->createAndSaveCategory('Calendar', 'EVT', $org['org_id']);
        [$adminUser] = $this->makeEventAdmin($fixture, $org['org_id'], 'evtadmin');

        $eventId = $this->createEvent(
            $adminUser,
            $org['org_id'],
            $category['cat_id'],
            'Board Meeting',
            '2030-03-01 19:00:00',
            '2030-03-01 21:00:00'
        );

        $event = $this->withOrganization($org['org_id'], fn () => new Event($this->getDatabase(), $eventId));
        $this->assertEquals('Board Meeting', $event->getValue('dat_headline'));
        $this->assertEquals($category['cat_id'], (int) $event->getValue('dat_cat_id'));

        // the joined category is available once the record was read
        $this->assertEquals($org['org_id'], (int) $event->getValue('cat_org_id'));
    }

    /**
     * Test the deadline of an event that lies in the future
     *
     * @testdox The deadline of a future event is not exceeded
     */
    public function testDeadlineOfFutureEventIsNotExceeded(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Event Org', 'evtorg');
        $category = $fixture->createAndSaveCategory('Calendar', 'EVT', $org['org_id']);
        [$adminUser] = $this->makeEventAdmin($fixture, $org['org_id'], 'evtadmin');

        $futureId = $this->createEvent(
            $adminUser,
            $org['org_id'],
            $category['cat_id'],
            'Future Event',
            '2030-01-01 10:00:00',
            '2030-01-01 12:00:00'
        );
        $pastId = $this->createEvent(
            $adminUser,
            $org['org_id'],
            $category['cat_id'],
            'Past Event',
            '2020-01-01 10:00:00',
            '2020-01-01 12:00:00'
        );

        $future = $this->withOrganization($org['org_id'], fn () => new Event($this->getDatabase(), $futureId));
        $past = $this->withOrganization($org['org_id'], fn () => new Event($this->getDatabase(), $pastId));

        // without an explicit deadline the begin of the event counts
        $this->assertFalse($future->deadlineExceeded());
        $this->assertTrue($past->deadlineExceeded());
    }

    /**
     * Test that an event is deleted
     *
     * @testdox An event can be deleted
     */
    public function testEventCanBeDeleted(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();
        $org = $fixture->createAndSaveOrganization('Event Org', 'evtorg');
        $category = $fixture->createAndSaveCategory('Calendar', 'EVT', $org['org_id']);
        [$adminUser] = $this->makeEventAdmin($fixture, $org['org_id'], 'evtadmin');

        $eventId = $this->createEvent(
            $adminUser,
            $org['org_id'],
            $category['cat_id'],
            'Cancelled Event',
            '2030-09-01 10:00:00',
            '2030-09-01 12:00:00'
        );

        $countSql = 'SELECT COUNT(*) AS count FROM ' . TBL_EVENTS . ' WHERE dat_id = ?';
        $this->assertEquals(1, (int) $db->queryPrepared($countSql, [$eventId])->fetch()['count']);

        $this->withCurrentUser($adminUser, $org['org_id'], true, function () use ($db, $eventId) {
            $event = new Event($db, $eventId);
            $event->delete();
        });

        $this->assertEquals(0, (int) $db->queryPrepared($countSql, [$eventId])->fetch()['count']);
    }

    public static function recurrenceDeletionCases(): array
    {
        return [['guard'], ['this'], ['series'], ['permanent'], ['rollback']];
    }

    /** @dataProvider recurrenceDeletionCases */
    public function testRecurrenceDeletion(string $action): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();
        $initialCount = (int) $db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_EVENTS)->fetchColumn();
        $org = $fixture->createAndSaveOrganization('Series Org', 'seriesorg');
        $fixture->seedDefaultPreferences($org['org_id']);
        $category = $fixture->createAndSaveCategory('Calendar', 'EVT', $org['org_id']);
        [$admin] = $this->makeEventAdmin($fixture, $org['org_id'], 'seriesadmin');
        $this->withCurrentUser($admin, $org['org_id'], true, function () use ($admin, $org, $category, $db, $action, $initialCount): void {
            $ids = [];
            foreach (['2020-01-01', '2030-01-01', '2030-01-02'] as $day) {
                $ids[] = $this->createEvent($admin, $org['org_id'], $category['cat_id'], 'Series', $day . ' 10:00:00', $day . ' 11:00:00');
            }
            $rule = (new EventRecurrenceRepository($db))->save($ids[0], new EventRecurrenceRule('daily'));
            $ruleId = (int) $rule->getValue('evr_id');
            foreach ($ids as $index => $id) {
                $event = new Event($db, $id);
                $event->setValue('dat_evr_id', $ruleId);
                $event->setValue('dat_recurrence_status', ['master', 'modified', 'cancelled'][$index]);
                $event->save();
            }
            $master = new Event($db, $ids[0]);
            if ($action === 'rollback') {
                $deleted = 0;
                Hooks::addAction('entity_deleting', function ($changeSet, $entity) use (&$deleted): void {
                    if ($entity instanceof Event && ++$deleted === 2) {
                        throw new \RuntimeException('Injected event deletion failure');
                    }
                }, Hooks::DEFAULT_PRIORITY, 2, 'series_rollback_test');
                try {
                    (new EventService($db))->deleteSeriesData((string) $master->getValue('dat_uuid'));
                    $this->fail('The injected failure must abort the deletion.');
                } catch (\RuntimeException $exception) {
                    $this->assertSame('Injected event deletion failure', $exception->getMessage());
                    $this->assertSame(2, $deleted);
                    $this->assertFalse($db->isInTransaction());
                    // The fixture itself belongs to the enclosing test transaction, which is
                    // rolled back too. Nothing from either occurrence may have been committed.
                    $this->assertSame($initialCount, (int) $db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_EVENTS)->fetchColumn());
                    $this->assertNull((new EventRecurrenceRepository($db))->readById($ruleId));
                } finally {
                    Hooks::removeAction('entity_deleting', 'series_rollback_test');
                }
                return;
            }
            if ($action === 'guard') {
                try {
                    $master->delete();
                    $this->fail('Direct master deletion must be refused.');
                } catch (Exception $exception) {
                    $this->assertSame('SYS_RECURRENCE_DELETE_SERIES_REQUIRED', $exception->getTranslationId());
                }
            } elseif ($action === 'permanent') {
                // Start from a non-master occurrence and include history and cancelled exceptions.
                $occurrence = new Event($db, $ids[1]);
                (new EventService($db))->deleteSeriesData((string) $occurrence->getValue('dat_uuid'));
            } else {
                $occurrence = new Event($db, $ids[1]);
                (new EventService($db))->deleteData((string) $occurrence->getValue('dat_uuid'), $action);
                $this->assertSame('cancelled', (new Event($db, $ids[1]))->getValue('dat_recurrence_status'));
                $this->assertSame('master', (new Event($db, $ids[0]))->getValue('dat_recurrence_status'));
            }
            $remaining = (int) $db->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_EVENTS . ' WHERE dat_id IN (?, ?, ?)', $ids
            )->fetchColumn();
            $this->assertSame($action === 'permanent' ? 0 : 3, $remaining);
            $this->assertSame($action === 'permanent', (new EventRecurrenceRepository($db))->readById($ruleId) === null);
        });
    }

    /**
     * Test that events of one organization stay there
     *
     * @testdox Events are listed only for the organization of their category
     */
    public function testEventsAreScopedToTheirOrganization(): void
    {
        $fixture = $this->getFixture();
        $orgA = $fixture->createAndSaveOrganization('Org A', 'evtlka');
        $orgB = $fixture->createAndSaveOrganization('Org B', 'evtlkb');

        $catA = $fixture->createAndSaveCategory('Calendar A', 'EVT', $orgA['org_id']);
        $catB = $fixture->createAndSaveCategory('Calendar B', 'EVT', $orgB['org_id']);

        [$adminA] = $this->makeEventAdmin($fixture, $orgA['org_id'], 'evtadmina');
        [$adminB] = $this->makeEventAdmin($fixture, $orgB['org_id'], 'evtadminb');

        $eventA = $this->createEvent($adminA, $orgA['org_id'], $catA['cat_id'], 'A Event', '2030-01-01 10:00:00', '2030-01-01 11:00:00');
        $eventB = $this->createEvent($adminB, $orgB['org_id'], $catB['cat_id'], 'B Event', '2030-01-01 10:00:00', '2030-01-01 11:00:00');

        // each administrator reaches only the calendar of their own organization
        $this->assertContains($catA['cat_id'], $adminA->getAllEditableCategories('EVT'));
        $this->assertNotContains($catB['cat_id'], $adminA->getAllEditableCategories('EVT'));

        // and the events follow their category
        $sql = 'SELECT dat_id FROM ' . TBL_EVENTS . '
                 INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = dat_cat_id
                 WHERE cat_org_id = ?';
        $idsA = array_map('intval', array_column($this->getDatabase()->queryPrepared($sql, [$orgA['org_id']])->fetchAll(), 'dat_id'));

        $this->assertContains($eventA, $idsA);
        $this->assertNotContains($eventB, $idsA);
    }

    /**
     * Test that a category view right makes the calendar readable but not writable
     *
     * @testdox A category_view right on a calendar does not allow creating events
     */
    public function testCategoryViewRightDoesNotAllowCreatingEvents(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Event Org', 'evtorg');
        $category = $fixture->createAndSaveCategory('Calendar', 'EVT', $org['org_id']);

        $role = $fixture->createAndSaveRoleWithRights('Readers', $org['org_id']);
        $reader = $fixture->createAndSaveUser('evtreader', 'er@example.local');
        $fixture->assignUserToRole($reader['usr_id'], $role['rol_id']);

        $rights = new RolesRights($this->getDatabase(), 'category_view', $category['cat_id']);
        $rights->saveRoles([$role['rol_id']]);

        $readerUser = $this->loadUserInOrganization($reader['usr_id'], $org['org_id']);

        $this->assertContains($category['cat_id'], $readerUser->getAllVisibleCategories('EVT'));
        $this->assertNotContains($category['cat_id'], $readerUser->getAllEditableCategories('EVT'));

        $this->expectException(Exception::class);
        $this->createEvent($readerUser, $org['org_id'], $category['cat_id'], 'Nope', '2030-01-01 10:00:00', '2030-01-01 11:00:00');
    }

    /**
     * Test that getDateTimePeriod formats single-day events with identical start and end time without duplicate time
     *
     * @testdox getDateTimePeriod only displays start time when start and end time are identical on the same day
     */
    public function testDateTimePeriodWithEqualStartAndEndTime(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Event Org', 'evtorg');
        // the period is formatted with the date and time format of the organization preferences
        $fixture->seedDefaultPreferences($org['org_id']);
        $category = $fixture->createAndSaveCategory('Calendar', 'EVT', $org['org_id']);
        [$adminUser] = $this->makeEventAdmin($fixture, $org['org_id'], 'evtadmin');

        $this->withCurrentUser($adminUser, $org['org_id'], true, function () use ($adminUser, $org, $category) {
            $eventId = $this->createEvent(
                $adminUser,
                $org['org_id'],
                $category['cat_id'],
                'Single Time Event',
                '2030-05-01 18:00:00',
                '2030-05-01 18:00:00'
            );

            $event = new Event($this->getDatabase(), $eventId);
            $period = $event->getDateTimePeriod(true, 'none');
            $this->assertSame('01.05.2030 18:00', $period);

            $eventIdDiffTime = $this->createEvent(
                $adminUser,
                $org['org_id'],
                $category['cat_id'],
                'Range Event',
                '2030-05-01 18:00:00',
                '2030-05-01 20:00:00'
            );

            $eventDiff = new Event($this->getDatabase(), $eventIdDiffTime);
            $periodDiff = $eventDiff->getDateTimePeriod(true, 'none');
            $this->assertSame('01.05.2030 18:00 - 20:00', $periodDiff);
        });
    }
}

