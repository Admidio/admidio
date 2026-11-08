<?php

namespace Admidio\Infrastructure;

use Admidio\Organizations\Entity\Organization;
use Admidio\Users\Entity\User;

/**
 * Resolve and check access to an organization's RSS feed.
 */
final class RssFeedAccess
{
    /**
     * Resolve the organization whose RSS feed was requested.
     * An empty short name selects the current organization. A non-empty short name must
     * identify an existing organization.
     *
     * @param Database $database Database connection used to load the requested organization.
     * @param Organization $currentOrganization Organization to use when no short name is supplied.
     * @param string $shortName Short name of the requested organization, or an empty string for the current one.
     * @return Organization The organization whose feed should be generated.
     * @throws Exception If the requested organization does not exist or cannot be loaded.
     */
    public static function resolveOrganization(Database $database, Organization $currentOrganization, string $shortName): Organization
    {
        $organization = $shortName === '' ? $currentOrganization : new Organization($database, $shortName);
        if ((int)$organization->getValue('org_id') === 0) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        return $organization;
    }

    /**
     * Ensure that RSS is enabled and the requested module is publicly accessible.
     * The settings belong to the requested organization. Only module access mode 1
     * allows a feed; an authenticated session does not grant additional RSS access.
     *
     * @param Organization $organization Organization whose RSS and module settings should be checked.
     * @param string $moduleSetting Name of the module access setting, for example 'forum_module_enabled'.
     * @return void
     * @throws Exception If RSS is disabled, the module is disabled, or the module is not public.
     */
    public static function assertAccessible(Organization $organization, string $moduleSetting): void
    {
        $settings = $organization->getSettingsManager();
        if (!$settings->getBool('enable_rss')) {
            throw new Exception('SYS_RSS_DISABLED');
        }

        $moduleAccess = $settings->getInt($moduleSetting);
        if ($moduleAccess === 0) {
            throw new Exception('SYS_MODULE_DISABLED');
        }
        if ($moduleAccess !== 1) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    /**
     * Select feed content with guest permissions, independently of the caller's session.
     * Temporarily replace the current user, user ID and login flag with a guest context
     * for the requested organization. Restore the original values and references after
     * the callback completes, including when it throws an exception. This method does
     * not check whether RSS or the module is enabled; call assertAccessible() first.
     *
     * @param Database $database Database connection used to create the guest user.
     * @param Organization $organization Organization against which guest permissions should be resolved.
     * @param callable $readContent Callback invoked without arguments to read the feed content with guest permissions.
     * @return mixed The callback's return value, passed through unchanged.
     * @throws \Throwable If guest initialization or the callback fails; callback exceptions are propagated after restoring the context.
     */
    public static function asGuest(Database $database, Organization $organization, callable $readContent): mixed
    {
        $guest = new User($database);
        $guest->setOrganization((int)$organization->getValue('org_id'));

        // Preserve references: gCurrentUser can reference the user stored in the session.
        $previousGlobals = array();
        foreach (array('gCurrentUser', 'gCurrentUserId', 'gValidLogin') as $name) {
            $previousGlobals[$name] =& $GLOBALS[$name];
            unset($GLOBALS[$name]);
        }

        $GLOBALS['gCurrentUser'] = $guest;
        $GLOBALS['gCurrentUserId'] = 0;
        $GLOBALS['gValidLogin'] = false;
        try {
            return $readContent();
        } finally {
            foreach ($previousGlobals as $name => &$value) {
                unset($GLOBALS[$name]);
                $GLOBALS[$name] =& $value;
            }
            unset($value);
        }
    }
}
