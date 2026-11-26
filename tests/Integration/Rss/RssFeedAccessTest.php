<?php

namespace Admidio\Tests\Integration\Rss;

use Admidio\Announcements\Service\AnnouncementsService;
use Admidio\Forum\Service\ForumService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\RssFeedAccess;
use Admidio\Organizations\Entity\Organization;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;

class RssFeedAccessTest extends DatabaseTestCase
{
    use PermissionContext;

    private function assertDenied(callable $action, string $translationId): void
    {
        try {
            $action();
            $this->fail('The RSS feed should be denied.');
        } catch (Exception $exception) {
            $this->assertSame($translationId, $exception->getTranslationId());
        }
    }

    public function testRequestedOrganizationControlsAllOtherRssFeeds(): void
    {
        $db = $this->getDatabase();
        $fixture = new AdmidioTestFixture($db);
        $current = $fixture->createAndSaveOrganization('RSS Current', 'rsscur');
        $requested = $fixture->createAndSaveOrganization('RSS Requested', 'rssreq');
        $currentOrganization = new Organization($db, $current['org_id']);
        $requestedOrganization = RssFeedAccess::resolveOrganization($db, $currentOrganization, $requested['org_shortname']);
        $this->assertSame($requested['org_id'], (int)$requestedOrganization->getValue('org_id'));
        $this->assertSame($currentOrganization, RssFeedAccess::resolveOrganization($db, $currentOrganization, ''));
        $this->assertDenied(
            fn () => RssFeedAccess::resolveOrganization($db, $currentOrganization, 'no-such-org'),
            'SYS_INVALID_PAGE_VIEW'
        );

        $currentSettings = $currentOrganization->getSettingsManager();
        $requestedSettings = $requestedOrganization->getSettingsManager();
        $currentSettings->set('enable_rss', '1');

        foreach (array('weblinks_module_enabled', 'photo_module_enabled', 'events_module_enabled', 'announcements_module_enabled', 'forum_module_enabled') as $moduleSetting) {
            $currentSettings->set($moduleSetting, '1');
            $requestedSettings->set('enable_rss', '0');
            $requestedSettings->set($moduleSetting, '1');
            $this->assertDenied(
                fn () => RssFeedAccess::assertAccessible($requestedOrganization, $moduleSetting),
                'SYS_RSS_DISABLED'
            );

            $requestedSettings->set('enable_rss', '1');
            $requestedSettings->set($moduleSetting, '0');
            $this->assertDenied(
                fn () => RssFeedAccess::assertAccessible($requestedOrganization, $moduleSetting),
                'SYS_MODULE_DISABLED'
            );

            $requestedSettings->set($moduleSetting, '2');
            $this->assertDenied(
                fn () => RssFeedAccess::assertAccessible($requestedOrganization, $moduleSetting),
                'SYS_NO_RIGHTS'
            );
            $requestedSettings->set($moduleSetting, '1');
            RssFeedAccess::assertAccessible($requestedOrganization, $moduleSetting);
        }

        $currentSettings->set('enable_rss', '0');
        RssFeedAccess::assertAccessible($requestedOrganization, 'photo_module_enabled');
    }

    public function testGuestSelectionExcludesRoleRestrictedCategories(): void
    {
        $db = $this->getDatabase();
        $fixture = new AdmidioTestFixture($db);
        $org = $fixture->createAndSaveOrganization('RSS Categories', 'rsscat');
        $role = $fixture->createAndSaveRole('RSS Readers', $org['org_id']);
        $user = $fixture->createAndSaveUser('rss-reader', 'rss-reader@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
        $reader = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);
        $organization = new Organization($db, $org['org_id']);

        foreach (array('FOT', 'ANN', 'EVT', 'LNK') as $type) {
            $public = $fixture->createAndSaveCategory('Public', $type, $org['org_id']);
            $private = $fixture->createAndSaveCategory('Private', $type, $org['org_id']);
            $rights = new RolesRights($db, 'category_view', $private['cat_id']);
            $rights->saveRoles(array($role['rol_id']));
            $this->assertContains($private['cat_id'], $reader->getAllVisibleCategories($type));

            $this->withCurrentUser($reader, $org['org_id'], true, function () use ($db, $organization, $type, $public, $private): void {
                $visible = RssFeedAccess::asGuest(
                    $db,
                    $organization,
                    fn () => $GLOBALS['gCurrentUser']->getAllVisibleCategories($type)
                );
                $this->assertContains($public['cat_id'], $visible);
                $this->assertNotContains($private['cat_id'], $visible);
            });
        }
    }

    public function testGuestSelectionPreservesSessionReferencesEvenOnFailure(): void
    {
        $db = $this->getDatabase();
        $fixture = new AdmidioTestFixture($db);
        $current = $fixture->createAndSaveOrganization('RSS Current', 'rsscur');
        $requested = $fixture->createAndSaveOrganization('RSS Requested', 'rssreq');
        $user = $fixture->createAndSaveUser('rss-reader', 'rss-reader@example.local');
        $currentUser = $this->loadUserInOrganization($user['usr_id'], $current['org_id']);
        $organization = new Organization($db, $requested['org_id']);

        $this->withCurrentUser($currentUser, $current['org_id'], true, function () use ($db, $organization, $currentUser, $user, $current, $requested): void {
            $sessionUser =& $GLOBALS['gCurrentUser'];
            foreach (array(false, true) as $throw) {
                try {
                    $result = RssFeedAccess::asGuest($db, $organization, function () use (&$sessionUser, $currentUser, $requested, $throw): string {
                        $this->assertSame($currentUser, $sessionUser);
                        $this->assertSame(0, (int)$GLOBALS['gCurrentUser']->getValue('usr_id'));
                        $this->assertSame($requested['org_id'], $GLOBALS['gCurrentUser']->getOrganization());
                        $this->assertSame(0, $GLOBALS['gCurrentUserId']);
                        $this->assertFalse($GLOBALS['gValidLogin']);
                        if ($throw) {
                            throw new \RuntimeException('guest selection failed');
                        }
                        return 'public content';
                    });
                    $this->assertFalse($throw);
                    $this->assertSame('public content', $result);
                } catch (\RuntimeException $exception) {
                    $this->assertTrue($throw);
                    $this->assertSame('guest selection failed', $exception->getMessage());
                }
                $this->assertSame($currentUser, $GLOBALS['gCurrentUser']);
                $this->assertSame($current['org_id'], $currentUser->getOrganization());
                $this->assertSame($user['usr_id'], $GLOBALS['gCurrentUserId']);
                $this->assertTrue($GLOBALS['gValidLogin']);
                $GLOBALS['gCurrentUser'] = clone $currentUser;
                $this->assertSame($GLOBALS['gCurrentUser'], $sessionUser);
                $GLOBALS['gCurrentUser'] = $currentUser;
            }
        });
    }

    public function testForumAndAnnouncementsServicesCheckRequestedOrganization(): void
    {
        $db = $this->getDatabase();
        $fixture = new AdmidioTestFixture($db);
        $current = $fixture->createAndSaveOrganization('RSS Current', 'rsscur');
        $requested = $fixture->createAndSaveOrganization('RSS Requested', 'rssreq');
        $user = $fixture->createAndSaveUser('rss-reader', 'rss-reader@example.local');
        $currentUser = $this->loadUserInOrganization($user['usr_id'], $current['org_id']);
        $requestedOrganization = new Organization($db, $requested['org_id']);
        $settings = $requestedOrganization->getSettingsManager();
        $settings->set('enable_rss', '1');
        $settings->set('forum_module_enabled', '0');
        $settings->set('announcements_module_enabled', '2');

        $this->withCurrentUser($currentUser, $current['org_id'], true, function () use ($db, $requested): void {
            $this->assertDenied(
                fn () => (new ForumService($db))->getRssFeedContent($requested['org_shortname']),
                'SYS_MODULE_DISABLED'
            );
            $this->assertDenied(
                fn () => (new AnnouncementsService($db))->getRssFeedContent($requested['org_shortname']),
                'SYS_NO_RIGHTS'
            );
        });

        $currentOrganization = new Organization($db, $current['org_id']);
        $currentSettings = $currentOrganization->getSettingsManager();
        $currentSettings->set('enable_rss', '0');
        $currentSettings->set('forum_module_enabled', '0');
        $currentSettings->set('announcements_module_enabled', '0');
        $settings->set('forum_module_enabled', '1');
        $settings->set('announcements_module_enabled', '1');

        $this->withCurrentUser($currentUser, $current['org_id'], false, function () use ($db, $requested, $current): void {
            $forumFeed = (new ForumService($db))->getRssFeedContent($requested['org_shortname']);
            $announcementsFeed = (new AnnouncementsService($db))->getRssFeedContent($requested['org_shortname']);

            $this->assertStringContainsString('RSS Requested', $forumFeed);
            $this->assertStringContainsString('RSS Requested', $announcementsFeed);
            $this->assertSame($current['org_id'], $GLOBALS['gCurrentUser']->getOrganization());
        });
    }
}
