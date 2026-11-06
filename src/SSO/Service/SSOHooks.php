<?php
namespace Admidio\SSO\Service;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Psr\Http\Message\ResponseInterface;

/**
 * The listeners through which the single sign-on module takes part in core events, so that the
 * core itself does not call into the module:
 *
 * - the SSO session cookie follows the session (see SSOSessionCookie),
 * - a logout notifies the SAML and OIDC clients of the session and ends with their logout chain,
 * - a membership that ends, also by the deletion of its role, revokes the tokens of the clients
 *   the user may no longer use.
 *
 * The registrations carry fixed IDs, so registering them again replaces them.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SSOHooks
{
    /** Registration ID of the listeners */
    private const HOOK_ID = 'sso';

    /** @var string External id of the session whose logout is in progress, empty if there is none */
    private static string $logoutSessionId = '';

    /** @var array<string,int> Users of memberships being deleted that have to be checked afterwards, by membership id */
    private static array $usersOfDeletedMemberships = array();

    /**
     * Register all listeners of the SSO module. This has to happen before the session starts.
     */
    public static function register(): void
    {
        SSOSessionCookie::registerHooks();

        Hooks::addAction('logout', array(self::class, 'onLogout'), Hooks::DEFAULT_PRIORITY, 0, self::HOOK_ID);
        Hooks::addResolver('logout_response', array(self::class, 'resolveLogoutResponse'), Hooks::DEFAULT_PRIORITY, 1, self::HOOK_ID);

        Hooks::addAction('membership_updated', array(self::class, 'onMembershipUpdated'), Hooks::DEFAULT_PRIORITY, 1, self::HOOK_ID);
        Hooks::addAction('membership_deleting', array(self::class, 'onMembershipDeleting'), Hooks::DEFAULT_PRIORITY, 1, self::HOOK_ID);
        Hooks::addAction('membership_deleted', array(self::class, 'onMembershipDeleted'), Hooks::DEFAULT_PRIORITY, 1, self::HOOK_ID);
    }

    /**
     * Remember the external id of the session that is being logged out, while it is still known.
     */
    public static function onLogout(): void
    {
        global $gCurrentSession, $gSettingsManager;

        self::$logoutSessionId = '';

        if (!isset($gCurrentSession, $gSettingsManager)
            || ($gSettingsManager->get('sso_saml_enabled') !== '1' && $gSettingsManager->get('sso_oidc_enabled') !== '1')
        ) {
            return;
        }

        self::$logoutSessionId = $gCurrentSession->getExternalSessionId();
    }

    /**
     * Finish the logout with the logout chain of the single sign-on clients of the session. The
     * OIDC back-channel clients are contacted right away, the OIDC front-channel clients are loaded
     * in one page of iframes, and the SAML service providers are then visited one after another
     * through the browser. The chain ends at the given address.
     * @param string $homepage Address the visitor is sent to after the logout.
     */
    public static function resolveLogoutResponse(string $homepage): ?ResponseInterface
    {
        global $gDb, $gCurrentUser;

        if (self::$logoutSessionId === '') {
            return null;
        }

        $externalSessionId = self::$logoutSessionId;
        self::$logoutSessionId = '';

        return (new SAMLService($gDb, $gCurrentUser))->startSessionLogout($externalSessionId, $homepage);
    }

    /**
     * A membership that was changed may have ended or been shortened, which can cost the user the
     * access to a single sign-on client. A new membership only adds rights, so it is not handled.
     * The check runs after the commit, against the state the change left behind.
     */
    public static function onMembershipUpdated(EntityChangeSet $changeSet): void
    {
        global $gDb;

        $snapshot = $changeSet->getSnapshot();
        $roleId = (int) ($snapshot['mem_rol_id'] ?? 0);
        $userId = (int) ($snapshot['mem_usr_id'] ?? 0);
        if ($roleId === 0 || $userId === 0) {
            return;
        }

        $revocationService = new SSOAccessRevocationService($gDb);
        if ($revocationService->isRoleUsedForClientAccess($roleId)) {
            $revocationService->revokeLostClientAccess($userId);
        }
    }

    /**
     * Remember the user of a membership that is being deleted, if its role restricts the access to
     * a client. This has to be decided before the deletion: when a role is deleted, its access
     * rights are removed together with its memberships.
     */
    public static function onMembershipDeleting(EntityChangeSet $changeSet): void
    {
        global $gDb;

        $snapshot = $changeSet->getSnapshot();
        $roleId = (int) ($snapshot['mem_rol_id'] ?? 0);
        $userId = (int) ($snapshot['mem_usr_id'] ?? 0);
        if ($roleId === 0 || $userId === 0) {
            return;
        }

        if ((new SSOAccessRevocationService($gDb))->isRoleUsedForClientAccess($roleId)) {
            self::$usersOfDeletedMemberships[(string) $changeSet->getId()] = $userId;
        }
    }

    /**
     * Check the user of a deleted membership against the state the deletion left behind: a client
     * that is still restricted to roles the user is not in loses its tokens, a client whose last
     * access role was deleted is open to everybody and keeps them.
     */
    public static function onMembershipDeleted(EntityChangeSet $changeSet): void
    {
        global $gDb;

        $key = (string) $changeSet->getId();
        if (!isset(self::$usersOfDeletedMemberships[$key])) {
            return;
        }

        $userId = self::$usersOfDeletedMemberships[$key];
        unset(self::$usersOfDeletedMemberships[$key]);

        (new SSOAccessRevocationService($gDb))->revokeLostClientAccess($userId);
    }
}
