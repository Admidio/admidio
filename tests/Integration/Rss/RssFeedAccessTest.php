<?php

namespace Admidio\Tests\Integration\Rss;

use Admidio\Announcements\Service\AnnouncementsService;
use Admidio\Forum\Service\ForumService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\RssFeedAccess;
use Admidio\Organizations\Entity\Organization;
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

        foreach (array('photo_module_enabled', 'events_module_enabled', 'announcements_module_enabled', 'forum_module_enabled') as $moduleSetting) {
            $currentSettings->set($moduleSetting, '1');
            $requestedSettings->set('enable_rss', '0');
            $requestedSettings->set($moduleSetting, '1');
            $this->assertDenied(
                fn () => RssFeedAccess::assertAccessible($requestedOrganization, $moduleSetting, false),
                'SYS_RSS_DISABLED'
            );

            $requestedSettings->set('enable_rss', '1');
            $requestedSettings->set($moduleSetting, '0');
            $this->assertDenied(
                fn () => RssFeedAccess::assertAccessible($requestedOrganization, $moduleSetting, false),
                'SYS_MODULE_DISABLED'
            );

            $requestedSettings->set($moduleSetting, '2');
            $this->assertDenied(
                fn () => RssFeedAccess::assertAccessible($requestedOrganization, $moduleSetting, false),
                'SYS_NO_RIGHTS'
            );
            RssFeedAccess::assertAccessible($requestedOrganization, $moduleSetting, true);
            $requestedSettings->set($moduleSetting, '1');
            RssFeedAccess::assertAccessible($requestedOrganization, $moduleSetting, false);
        }

        $currentSettings->set('enable_rss', '0');
        RssFeedAccess::assertAccessible($requestedOrganization, 'photo_module_enabled', false);
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

        $this->withCurrentUser($currentUser, $current['org_id'], false, function () use ($db, $requested): void {
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
