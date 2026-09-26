<?php

namespace Admidio\Infrastructure;

use Admidio\Organizations\Entity\Organization;

/**
 * Resolve and check access to an organization's RSS feed.
 */
final class RssFeedAccess
{
    public static function resolveOrganization(Database $database, Organization $currentOrganization, string $shortName): Organization
    {
        $organization = $shortName === '' ? $currentOrganization : new Organization($database, $shortName);
        if ((int)$organization->getValue('org_id') === 0) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        return $organization;
    }

    public static function assertAccessible(Organization $organization, string $moduleSetting, bool $validLogin): void
    {
        $settings = $organization->getSettingsManager();
        if (!$settings->getBool('enable_rss')) {
            throw new Exception('SYS_RSS_DISABLED');
        }

        $moduleAccess = $settings->getInt($moduleSetting);
        if ($moduleAccess === 0) {
            throw new Exception('SYS_MODULE_DISABLED');
        }
        if ($moduleAccess === 2 && !$validLogin) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }
}
