<?php
namespace Admidio\SSO\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Organizations\Entity\Organization;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Infrastructure\Language;
use Admidio\Users\Entity\User;

/**
 * The organization a single sign-on endpoint belongs to.
 *
 * Every organization is an identity provider of its own, with its own issuer, entity ID, keys and
 * clients. The default organization (the one of config.php) keeps the endpoint URLs of an
 * installation that has only one, `.../sso/index.php/oidc/...`. Every other organization has the
 * segment `o/<short name>` in front of the protocol: `.../sso/index.php/o/<short name>/oidc/...`.
 * The short name never changes after an organization was created, so the URLs stay valid.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SSOOrganization
{
    /** First path segment of the endpoints of an organization other than the default one */
    public const PATH_PREFIX = 'o';

    /**
     * The short name of the default organization, the one of config.php. If config.php names none, it
     * is the organization with the lowest ID, like the session handling picks it.
     * @throws Exception
     */
    public static function getDefaultShortname(): string
    {
        global $gDb, $g_organization;

        return (string) Organization::createDefaultOrganizationObject($gDb, (string) ($g_organization ?? ''))
            ->getValue('org_shortname');
    }

    /**
     * Whether the organization is the default organization.
     * @param string|null $shortname Short name of the organization, by default the current one.
     * @throws Exception
     */
    public static function isDefault(?string $shortname = null): bool
    {
        global $gCurrentOrganization;

        $shortname ??= (string) $gCurrentOrganization->getValue('org_shortname');
        return strcasecmp($shortname, self::getDefaultShortname()) === 0;
    }

    /**
     * Path of the organization in front of the protocol, with a leading slash; empty for the default
     * organization.
     * @param string|null $shortname Short name of the organization, by default the current one.
     * @throws Exception
     */
    public static function getPathSegment(?string $shortname = null): string
    {
        global $gCurrentOrganization;

        $shortname ??= (string) $gCurrentOrganization->getValue('org_shortname');
        return self::isDefault($shortname) ? '' : '/' . self::PATH_PREFIX . '/' . rawurlencode($shortname);
    }

    /**
     * URL below which the endpoints `oidc/...` and `saml/...` of the organization are served.
     * @param string|null $shortname Short name of the organization, by default the current one.
     * @throws Exception
     */
    public static function getBaseUrl(?string $shortname = null): string
    {
        return ADMIDIO_URL . FOLDER_MODULES . '/sso/index.php' . self::getPathSegment($shortname);
    }

    /**
     * The entity ID of the SAML identity provider of the organization. The default is the Admidio URL
     * for the default organization, and below it the segment of the organization for every other one,
     * because two organizations must not present themselves with the same entity ID. A stored value
     * that is the Admidio URL itself is the default of an organization that was created before this
     * was the case, and is replaced the same way.
     * @param string $stored The setting sso_saml_entity_id of the organization.
     * @param string|null $shortname Short name of the organization, by default the current one.
     * @throws Exception
     */
    public static function getEffectiveEntityId(string $stored, ?string $shortname = null): string
    {
        $stored = trim($stored);
        if ($stored !== '' && $stored !== ADMIDIO_URL) {
            return $stored;
        }
        return ADMIDIO_URL . self::getPathSegment($shortname);
    }

    /**
     * The issuer of the OpenID Connect identity provider of the organization: the configured issuer
     * URL, or the base URL of its endpoints if none is configured.
     * @param string $stored The setting sso_oidc_issuer_url of the organization.
     * @param string|null $shortname Short name of the organization, by default the current one.
     * @throws Exception
     */
    public static function getEffectiveIssuerUrl(string $stored, ?string $shortname = null): string
    {
        $stored = rtrim(trim($stored), '/');
        return $stored !== '' ? $stored : self::getBaseUrl($shortname) . '/oidc';
    }

    /**
     * Make sure that no other organization that has the protocol enabled presents itself with the same
     * issuer or entity ID, because an identity provider is told apart by them. Tokens of one
     * organization would otherwise be accepted as tokens of the other.
     * @param string $issuer The issuer the current organization is going to use (see getEffectiveIssuerUrl()),
     *                       empty if OpenID Connect is not enabled.
     * @param string $entityId The entity ID the current organization is going to use (see
     *                         getEffectiveEntityId()), empty if SAML is not enabled.
     * @throws Exception SYS_SSO_IDENTIFIER_IN_USE
     */
    public static function assertIdentifiersAreUnique(string $issuer, string $entityId): void
    {
        global $gDb, $gCurrentOrgId;

        $statement = $gDb->queryPrepared(
            'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id <> ?',
            array($gCurrentOrgId)
        );
        while ($organization = $statement->fetch()) {
            $settings = new SettingsManager($gDb, (int) $organization['org_id']);
            $shortname = (string) $organization['org_shortname'];

            if ($issuer !== '' && $settings->getBool('sso_oidc_enabled')
                && $issuer === self::getEffectiveIssuerUrl((string) $settings->get('sso_oidc_issuer_url'), $shortname)
            ) {
                throw new Exception('SYS_SSO_IDENTIFIER_IN_USE', array($issuer));
            }
            if ($entityId !== '' && $settings->getBool('sso_saml_enabled')
                && $entityId === self::getEffectiveEntityId((string) $settings->get('sso_saml_entity_id'), $shortname)
            ) {
                throw new Exception('SYS_SSO_IDENTIFIER_IN_USE', array($entityId));
            }
        }
    }

    /**
     * The organization addressed by the path of a request to the SSO module.
     * @param string $path Path after `sso/index.php`, e.g. `o/CLUB/oidc/token` or `saml/metadata`.
     * @return array{0:string,1:string}|null The short name of the organization (empty for the default
     *         organization) and the rest of the path, or null if the path names an organization that does
     *         not exist or the default organization with its segment, which has no second URL.
     * @throws Exception
     */
    public static function parsePath(string $path): ?array
    {
        global $gDb;

        $parts = explode('/', trim($path, '/'));
        if ($parts[0] !== self::PATH_PREFIX) {
            return array('', implode('/', $parts));
        }

        array_shift($parts);
        $shortname = rawurldecode((string) array_shift($parts));
        $organization = new Organization($gDb);
        if ($shortname === '' || !$organization->readDataByShortname($shortname) || self::isDefault($shortname)) {
            return null;
        }
        return array((string) $organization->getValue('org_shortname'), implode('/', $parts));
    }

    /**
     * Run the rest of the request in the given organization.
     *
     * A session belongs to one organization, so a user who is logged in to another organization is
     * a visitor here, and the login form that follows creates the session of this one. The session
     * itself is not touched: its objects are replaced in this request only, so a request for the
     * endpoint of another organization neither logs the user out nor changes the organization of
     * their session.
     *
     * @param string $shortname Short name of the organization; empty for the default organization.
     * @throws Exception
     */
    public static function enter(string $shortname): void
    {
        global $gDb, $gCurrentOrganization;

        if ($shortname === '') {
            $shortname = self::getDefaultShortname();
        }
        if (strcasecmp((string) $gCurrentOrganization->getValue('org_shortname'), $shortname) === 0) {
            return;
        }

        // The globals refer to the objects of the session. Detach them, so that new objects take
        // their place for this request and the session keeps the ones of its own organization.
        unset(
            $GLOBALS['gCurrentOrganization'],
            $GLOBALS['gSettingsManager'],
            $GLOBALS['gProfileFields'],
            $GLOBALS['gCurrentUser'],
            $GLOBALS['gL10n']
        );

        $organization = new Organization($gDb, $shortname);
        $orgId = (int) $organization->getValue('org_id');

        $GLOBALS['gCurrentOrganization'] = $organization;
        $GLOBALS['gCurrentOrgId'] = $orgId;
        $GLOBALS['gSettingsManager'] = new SettingsManager($gDb, $orgId);
        $GLOBALS['gProfileFields'] = new ProfileFields($gDb, $orgId);
        $GLOBALS['gCurrentUser'] = new User($gDb, $GLOBALS['gProfileFields']);
        $GLOBALS['gCurrentUserId'] = 0;
        $GLOBALS['gCurrentUserUUID'] = '';
        $GLOBALS['gValidLogin'] = false;
        $GLOBALS['gL10n'] = new Language($GLOBALS['gSettingsManager']->getString('system_language'), true);
    }
}
