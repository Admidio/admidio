<?php

namespace Admidio\Tests\Integration\Weblinks;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\RssFeedAccess;
use Admidio\Organizations\Entity\Organization;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;

class WeblinksRssAccessTest extends DatabaseTestCase
{
    private function assertRssDenied(
        Organization $organization,
        bool $validLogin,
        string $expectedError
    ): void {
        try {
            RssFeedAccess::assertAccessible($organization, 'weblinks_module_enabled', $validLogin);
            $this->fail('The RSS feed should be denied.');
        } catch (Exception $exception) {
            $this->assertSame($expectedError, $exception->getTranslationId());
        }
    }

    public function testRssAccessUsesTheRequestedOrganizationsSettings(): void
    {
        $fixture = new AdmidioTestFixture($this->getDatabase());
        $current = $fixture->createAndSaveOrganization('Current Links', 'rsscurrent');
        $requested = $fixture->createAndSaveOrganization('Requested Links', 'rssrequest');
        $currentOrganization = new Organization($this->getDatabase(), $current['org_id']);
        $currentOrganization->getSettingsManager()->set('enable_rss', '1');
        $currentOrganization->getSettingsManager()->set('weblinks_module_enabled', '1');

        // Resolve the target by the same short name accepted by rss/weblinks.php.
        $requestedOrganization = new Organization($this->getDatabase(), $requested['org_shortname']);
        $settings = $requestedOrganization->getSettingsManager();
        $settings->set('enable_rss', '0');
        $settings->set('weblinks_module_enabled', '1');
        $this->assertRssDenied($requestedOrganization, false, 'SYS_RSS_DISABLED');

        $settings->set('enable_rss', '1');
        $settings->set('weblinks_module_enabled', '0');
        $this->assertRssDenied($requestedOrganization, false, 'SYS_MODULE_DISABLED');

        $settings->set('weblinks_module_enabled', '2');
        $this->assertRssDenied($requestedOrganization, false, 'SYS_NO_RIGHTS');
        RssFeedAccess::assertAccessible($requestedOrganization, 'weblinks_module_enabled', true);

        $settings->set('weblinks_module_enabled', '1');
        RssFeedAccess::assertAccessible($requestedOrganization, 'weblinks_module_enabled', false);

        $currentOrganization->getSettingsManager()->set('enable_rss', '0');
        RssFeedAccess::assertAccessible($requestedOrganization, 'weblinks_module_enabled', false);
    }
}
