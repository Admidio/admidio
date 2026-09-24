<?php

namespace Admidio\Tests\Integration\Weblinks;

use Admidio\Infrastructure\Exception;
use Admidio\Organizations\Entity\Organization;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Weblinks\Service\WeblinksService;

class WeblinksRssAccessTest extends DatabaseTestCase
{
    private function assertRssDenied(
        WeblinksService $service,
        Organization $organization,
        bool $validLogin,
        string $expectedError
    ): void {
        try {
            $service->assertRssFeedAccessible($organization, $validLogin);
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

        // Resolve the target by the same short name accepted by rss/links.php.
        $requestedOrganization = new Organization($this->getDatabase(), $requested['org_shortname']);
        $settings = $requestedOrganization->getSettingsManager();
        $service = new WeblinksService($this->getDatabase());

        $settings->set('enable_rss', '0');
        $settings->set('weblinks_module_enabled', '1');
        $this->assertRssDenied($service, $requestedOrganization, false, 'SYS_RSS_DISABLED');

        $settings->set('enable_rss', '1');
        $settings->set('weblinks_module_enabled', '0');
        $this->assertRssDenied($service, $requestedOrganization, false, 'SYS_MODULE_DISABLED');

        $settings->set('weblinks_module_enabled', '2');
        $this->assertRssDenied($service, $requestedOrganization, false, 'SYS_NO_RIGHTS');
        $service->assertRssFeedAccessible($requestedOrganization, true);

        $settings->set('weblinks_module_enabled', '1');
        $service->assertRssFeedAccessible($requestedOrganization, false);

        $currentOrganization->getSettingsManager()->set('enable_rss', '0');
        $service->assertRssFeedAccessible($requestedOrganization, false);
    }
}
