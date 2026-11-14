<?php
/**
 * Memberships whose start or end date is reached
 *
 * A membership that starts or ends by its date changes state at midnight without being saved. The
 * daily job revokes the OIDC tokens such an end takes away and reports both changes to plugins.
 */

namespace Admidio\Tests\Integration\Roles;

use Admidio\Hooks\Hooks;
use Admidio\Roles\Service\MembershipPeriodService;
use Admidio\Tests\Integration\Sso\SsoClientFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;

class MembershipPeriodServiceTest extends AdministratorTestCase
{
    use SsoClientFixture;

    /** @var array<int,array{0:int,1:string}> */
    private array $reported = array();

    protected function setUp(): void
    {
        parent::setUp();
        $this->reported = array();
        Hooks::addAction(MembershipPeriodService::HOOK, function (array $membership, string $change): void {
            $this->reported[] = array((int) $membership['mem_id'], $change);
        }, 10, 2, 'membership-period-test');
    }

    protected function tearDown(): void
    {
        Hooks::removeAction(MembershipPeriodService::HOOK, 'membership-period-test');
        parent::tearDown();
    }

    private static function day(int $offset): string
    {
        return date('Y-m-d', strtotime(DATE_NOW . ' ' . sprintf('%+d', $offset) . ' day'));
    }

    /**
     * @testdox Started and ended memberships are reported, and the OIDC tokens of an ended one are revoked
     */
    public function testStartedAndEndedMembershipsAreReported(): void
    {
        global $gCurrentOrgId;

        $fixture = new AdmidioTestFixture($this->getDatabase());
        $suffix = $this->suffix();
        $role = $fixture->createAndSaveRole('Period ' . $suffix, (int) $gCurrentOrgId);
        $starting = $fixture->createAndSaveUser('period-start-' . $suffix, 'start-' . $suffix . '@example.local');
        $ending = $fixture->createAndSaveUser('period-end-' . $suffix, 'end-' . $suffix . '@example.local');
        $unchanged = $fixture->createAndSaveUser('period-same-' . $suffix, 'same-' . $suffix . '@example.local');

        $started = $fixture->assignUserToRolePeriod($starting['usr_id'], $role['rol_id'], DATE_NOW, DATE_MAX);
        $ended = $fixture->assignUserToRolePeriod($ending['usr_id'], $role['rol_id'], '2020-01-01', DATE_NOW);
        $fixture->assignUserToRolePeriod($unchanged['usr_id'], $role['rol_id'], '2020-01-01', DATE_MAX);

        $client = $this->createRestrictedClient($suffix, array($role['rol_id']));
        $token = $this->createToken($client, $ending['usr_id'], TBL_OIDC_REFRESH_TOKENS, 'ort');

        $count = (new MembershipPeriodService($this->getDatabase()))->process(self::day(-1), DATE_NOW);

        // the test installation may have memberships of its own that started today
        $ours = array_values(array_filter(
            $this->reported,
            static fn (array $report): bool => in_array($report[0], array($started['mem_id'], $ended['mem_id']), true)
        ));
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertEqualsCanonicalizing(
            array(array($started['mem_id'], MembershipPeriodService::CHANGE_STARTED), array($ended['mem_id'], MembershipPeriodService::CHANGE_ENDED)),
            $ours
        );
        $this->assertTrue((bool) $this->getDatabase()->queryPrepared(
            'SELECT ort_revoked FROM ' . TBL_OIDC_REFRESH_TOKENS . ' WHERE ort_token = ?',
            array($token)
        )->fetchColumn());
    }

    /**
     * @testdox Days that were missed are caught up
     */
    public function testMissedDaysAreCaughtUp(): void
    {
        global $gCurrentOrgId;

        $fixture = new AdmidioTestFixture($this->getDatabase());
        $suffix = $this->suffix();
        $role = $fixture->createAndSaveRole('Missed ' . $suffix, (int) $gCurrentOrgId);
        $user = $fixture->createAndSaveUser('period-missed-' . $suffix, 'missed-' . $suffix . '@example.local');
        $membership = $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], self::day(-2), DATE_MAX);

        (new MembershipPeriodService($this->getDatabase()))->process(self::day(-3), DATE_NOW);

        $this->assertContains(array($membership['mem_id'], MembershipPeriodService::CHANGE_STARTED), $this->reported);
    }

    /**
     * @testdox A day is processed only once, whoever comes first
     */
    public function testADayIsProcessedOnce(): void
    {
        $service = new MembershipPeriodService($this->getDatabase());

        $this->assertNotNull($service->runIfDue(self::day(1)));
        $this->assertNull($service->runIfDue(self::day(1)), 'The same day must not run twice.');
        $this->assertNotNull($service->runIfDue(self::day(2)), 'The next day runs again.');
    }

    /**
     * @testdox A day whose processing failed stays undone and runs again
     */
    public function testAFailedDayRunsAgain(): void
    {
        $failing = new class ($this->getDatabase()) extends MembershipPeriodService {
            public function process(string $since, string $until): int
            {
                throw new \RuntimeException('The revocation failed.');
            }
        };

        try {
            $failing->runIfDue(self::day(3));
            $this->fail('The failing run was expected to throw.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('The revocation failed.', $exception->getMessage());
        }

        $this->assertNotNull(
            (new MembershipPeriodService($this->getDatabase()))->runIfDue(self::day(3)),
            'The day of the failed run must not count as done.'
        );
    }

    /**
     * @testdox A run that was killed blocks others only until its lock is abandoned, and is then resumed
     */
    public function testAnInterruptedRunIsResumed(): void
    {
        $recording = new class ($this->getDatabase()) extends MembershipPeriodService {
            /** @var array<int,array{0:string,1:string}> */
            public array $runs = array();

            public function process(string $since, string $until): int
            {
                $this->runs[] = array($since, $until);
                return 0;
            }
        };

        $this->assertNotNull($recording->runIfDue(self::day(4)));

        // a run for the next day was killed after it took the lock: the lock stays, the day stays undone
        $this->setLock(date('Y-m-d H:i:s') . ' killed');
        $this->assertNull($recording->runIfDue(self::day(5)), 'A run must not start while another holds the lock.');

        $this->setLock(date('Y-m-d H:i:s', time() - MembershipPeriodService::LOCK_TIMEOUT - 60) . ' killed');
        $this->assertNotNull($recording->runIfDue(self::day(5)), 'An abandoned lock must be taken over.');

        $this->assertSame(array(self::day(4), self::day(5)), end($recording->runs), 'The resumed run starts after the last completed day.');
        $this->assertSame(self::day(5), $this->preference(MembershipPeriodService::SETTING));
        $this->assertSame('', $this->preference(MembershipPeriodService::LOCK_SETTING));
    }

    private function setLock(string $value): void
    {
        global $gCurrentOrgId;

        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_PREFERENCES . ' SET prf_value = ? WHERE prf_org_id = ? AND prf_name = ?',
            array($value, (int) $gCurrentOrgId, MembershipPeriodService::LOCK_SETTING)
        );
    }

    private function preference(string $name): string
    {
        global $gCurrentOrgId;

        return (string) $this->getDatabase()->queryPrepared(
            'SELECT prf_value FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id = ? AND prf_name = ?',
            array((int) $gCurrentOrgId, $name)
        )->fetchColumn();
    }

    /**
     * @testdox A failing callback does not keep the others from learning about the change
     */
    public function testAFailingCallbackDoesNotStopTheOthers(): void
    {
        global $gCurrentOrgId;

        Hooks::addAction(MembershipPeriodService::HOOK, static function (): void {
            throw new \RuntimeException('A broken plugin.');
        }, 5, null, 'membership-period-broken');

        try {
            $fixture = new AdmidioTestFixture($this->getDatabase());
            $suffix = $this->suffix();
            $role = $fixture->createAndSaveRole('Broken ' . $suffix, (int) $gCurrentOrgId);
            $user = $fixture->createAndSaveUser('period-broken-' . $suffix, 'broken-' . $suffix . '@example.local');
            $membership = $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], DATE_NOW, DATE_MAX);

            (new MembershipPeriodService($this->getDatabase()))->process(self::day(-1), DATE_NOW);

            $this->assertContains(array($membership['mem_id'], MembershipPeriodService::CHANGE_STARTED), $this->reported);
        } finally {
            Hooks::removeAction(MembershipPeriodService::HOOK, 'membership-period-broken');
        }
    }
}
