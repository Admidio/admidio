<?php
/**
 * Token based calendar feed tests.
 *
 * These tests exercise the production service against the real database:
 * token creation, hashing, authentication, expiry, revocation and the
 * organization and user checks.
 */

namespace Admidio\Tests\Integration\Events;

use Admidio\Events\Service\CalendarFeedService;
use Admidio\Organizations\Entity\Organization;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use DateTime;

class CalendarFeedServiceTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    private function getDefaultOrgId(): int
    {
        return (int)$this->getDatabase()
            ->queryPrepared('SELECT MIN(org_id) FROM ' . TBL_ORGANIZATIONS)
            ->fetchColumn();
    }

    /**
     * Create a valid member of the organization.
     * @return array User data from the fixture
     */
    private function createMember(AdmidioTestFixture $fixture, int $orgId, string $login): array
    {
        $role = $fixture->createAndSaveRole('Calendar feed role ' . $login, $orgId);
        $user = $fixture->createAndSaveUser($login, $login . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        return $user;
    }

    /**
     * @testdox A created token authenticates its feed and is stored only as a hash
     */
    public function testCreateAndAuthenticate(): void
    {
        $fixture = $this->getFixture();
        $orgId = $this->getDefaultOrgId();
        $organization = new Organization($this->getDatabase(), $orgId);
        $user = $this->createMember($fixture, $orgId, 'feeduser1');
        $service = new CalendarFeedService($this->getDatabase());

        $created = $service->createFeed($orgId, $user['usr_id'], 'Thunderbird');
        $this->assertStringStartsWith('admcf_', $created['token']);
        $this->assertStringContainsString('mode=feed', $created['url']);
        $this->assertStringContainsString('org_shortname=' . $organization->getValue('org_shortname'), $created['url']);

        $storedHash = $this->getDatabase()
            ->queryPrepared('SELECT cfd_token_hash FROM ' . TBL_CALENDAR_FEEDS . ' WHERE cfd_id = ?', array($created['feed']->getValue('cfd_id')))
            ->fetchColumn();
        $this->assertSame(CalendarFeedService::hashToken($created['token']), $storedHash);
        $this->assertNotSame($created['token'], $storedHash);

        $authentication = $service->authenticate($created['token'], $organization);
        $this->assertNotNull($authentication);
        $this->assertSame((int)$user['usr_id'], (int)$authentication['user']->getValue('usr_id'));

        $this->assertNull($service->authenticate($created['token'] . 'x', $organization));
        $this->assertNull($service->authenticate('', $organization));
    }

    /**
     * @testdox Multiple tokens of one user stay independent and can be revoked one by one
     */
    public function testMultipleTokensAndRevoke(): void
    {
        $fixture = $this->getFixture();
        $orgId = $this->getDefaultOrgId();
        $organization = new Organization($this->getDatabase(), $orgId);
        $user = $this->createMember($fixture, $orgId, 'feeduser2');
        $service = new CalendarFeedService($this->getDatabase());

        $first = $service->createFeed($orgId, $user['usr_id'], 'Thunderbird');
        $second = $service->createFeed($orgId, $user['usr_id'], 'ICSx5');

        $this->assertNotSame($first['token'], $second['token']);
        $this->assertCount(2, $service->listFeeds($orgId, $user['usr_id']));

        $listed = $service->listFeeds($orgId, $user['usr_id']);
        $this->assertArrayNotHasKey('cfd_token_hash', $listed[0]);

        $service->revokeFeed((int)$first['feed']->getValue('cfd_id'), (int)$user['usr_id'], $orgId);

        $this->assertNull($service->authenticate($first['token'], $organization));
        $this->assertNotNull($service->authenticate($second['token'], $organization));
    }

    /**
     * @testdox A feed can be bound to one visible calendar
     */
    public function testFeedCanBeBoundToCalendar(): void
    {
        $fixture = $this->getFixture();
        $orgId = $this->getDefaultOrgId();
        $user = $this->createMember($fixture, $orgId, 'feeduser6');
        $category = $fixture->createAndSaveCategory('Feed calendar', 'EVT', $orgId);
        $service = new CalendarFeedService($this->getDatabase());

        $created = $service->createFeed($orgId, $user['usr_id'], 'Only this calendar', null, $category['cat_uuid']);

        $this->assertSame($category['cat_uuid'], $created['feed']->getValue('cfd_cat_uuid', 'database'));
        $this->assertStringContainsString('cat_uuid=' . $category['cat_uuid'], $created['url']);
    }

    /**
     * @testdox A feed cannot be bound to a calendar the user cannot see
     */
    public function testFeedCannotBeBoundToInvisibleCalendar(): void
    {
        $fixture = $this->getFixture();
        $orgId = $this->getDefaultOrgId();
        $user = $this->createMember($fixture, $orgId, 'feeduser7');
        $service = new CalendarFeedService($this->getDatabase());

        $this->expectException(\Admidio\Infrastructure\Exception::class);
        $service->createFeed($orgId, $user['usr_id'], 'Unknown calendar', null, '00000000-0000-0000-0000-000000000000');
    }

    /**
     * @testdox An expired or deactivated feed no longer authenticates
     */
    public function testExpiryAndInactiveUser(): void
    {
        $fixture = $this->getFixture();
        $orgId = $this->getDefaultOrgId();
        $organization = new Organization($this->getDatabase(), $orgId);
        $user = $this->createMember($fixture, $orgId, 'feeduser3');
        $service = new CalendarFeedService($this->getDatabase());

        $expired = $service->createFeed($orgId, $user['usr_id'], 'Expired', new DateTime('-1 hour'));
        $this->assertNull($service->authenticate($expired['token'], $organization));

        $active = $service->createFeed($orgId, $user['usr_id'], 'Active');
        $this->assertNotNull($service->authenticate($active['token'], $organization));

        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_USERS . ' SET usr_valid = ? WHERE usr_id = ?',
            array(false, $user['usr_id'])
        );
        $this->assertNull($service->authenticate($active['token'], $organization));
    }

    /**
     * @testdox A token is only valid for the organization it was created for
     */
    public function testTokenIsBoundToOrganization(): void
    {
        $fixture = $this->getFixture();
        $orgId = $this->getDefaultOrgId();
        $otherOrg = $fixture->createAndSaveOrganization('Calendar Feed Org', 'cfdorg');
        $organization = new Organization($this->getDatabase(), $orgId);
        $otherOrganization = new Organization($this->getDatabase(), $otherOrg['org_id']);
        $user = $this->createMember($fixture, $orgId, 'feeduser4');
        $service = new CalendarFeedService($this->getDatabase());

        $created = $service->createFeed($orgId, $user['usr_id'], 'Only this org');

        $this->assertNotNull($service->authenticate($created['token'], $organization));
        $this->assertNull($service->authenticate($created['token'], $otherOrganization));
    }

    /**
     * @testdox A successful authentication records the last usage
     */
    public function testLastUsedIsRecorded(): void
    {
        $fixture = $this->getFixture();
        $orgId = $this->getDefaultOrgId();
        $organization = new Organization($this->getDatabase(), $orgId);
        $user = $this->createMember($fixture, $orgId, 'feeduser5');
        $service = new CalendarFeedService($this->getDatabase());

        $created = $service->createFeed($orgId, $user['usr_id'], 'Used now');
        $this->assertNotNull($service->authenticate($created['token'], $organization));
        $service->touchLastUsed((int)$created['feed']->getValue('cfd_id'));

        $lastUsed = $this->getDatabase()
            ->queryPrepared('SELECT cfd_timestamp_last_used FROM ' . TBL_CALENDAR_FEEDS . ' WHERE cfd_id = ?', array($created['feed']->getValue('cfd_id')))
            ->fetchColumn();

        $this->assertNotEmpty($lastUsed);
    }
}
