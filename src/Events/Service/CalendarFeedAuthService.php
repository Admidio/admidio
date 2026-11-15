<?php
namespace Admidio\Events\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\ValueObjects\ProfileFields;

/**
 * HTTP Basic authentication for token based calendar feeds.
 *
 * The login name is the Admidio login name of the user and the password is
 * the feed token. The service never falls back to the normal HTML login.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class CalendarFeedAuthService
{
    private CalendarFeedService $feedService;

    public function __construct(private readonly Database $database)
    {
        $this->feedService = new CalendarFeedService($database);
    }

    /**
     * Authenticate an HTTP request for a calendar feed.
     *
     * On any failure the corresponding HTTP response is sent and null is returned.
     * The caller must not continue in that case.
     *
     * @return array{feed: \Admidio\Events\Entity\CalendarFeed, user: \Admidio\Users\Entity\User, organization: Organization}|null
     * @throws Exception
     */
    public function authorize(string $organizationShortName): ?array
    {
        if (!defined('HTTPS') || !HTTPS) {
            $this->sendError(403, 'SYS_CALENDAR_FEED_HTTPS_REQUIRED');
            return null;
        }

        $credentials = $this->getBasicCredentials();
        if ($credentials === null) {
            $this->sendChallenge();
            return null;
        }

        $organization = $this->resolveOrganization($organizationShortName);
        if ($organization === null) {
            $this->sendChallenge();
            return null;
        }

        $settings = $organization->getSettingsManager();
        if ($settings->getInt('events_module_enabled') === 0) {
            $this->sendError(403, 'SYS_MODULE_DISABLED');
            return null;
        }
        if (!$settings->getBool('events_ical_export_enabled')) {
            $this->sendError(403, 'SYS_ICAL_DISABLED');
            return null;
        }

        $authentication = $this->feedService->authenticate($credentials['password'], $organization);
        if ($authentication === null) {
            $this->sendChallenge();
            return null;
        }

        $loginName = (string)$authentication['user']->getValue('usr_login_name', 'database');
        if ($loginName === '' || strcasecmp($loginName, $credentials['username']) !== 0) {
            $this->sendChallenge();
            return null;
        }

        $authentication['organization'] = $organization;
        $this->feedService->touchLastUsed((int)$authentication['feed']->getValue('cfd_id'));

        return $authentication;
    }

    /**
     * Make the authenticated user the current user for the rest of the request.
     *
     * This is the same context the normal login establishes, so the event
     * repository applies the regular visibility rules of that user.
     *
     * @param array{feed: \Admidio\Events\Entity\CalendarFeed, user: \Admidio\Users\Entity\User, organization: Organization} $authentication
     * @throws Exception
     */
    public function establishContext(array $authentication): void
    {
        $organization = $authentication['organization'];
        $user = $authentication['user'];
        $orgId = (int)$organization->getValue('org_id');
        $settings = $organization->getSettingsManager();

        $GLOBALS['gCurrentOrganization'] = $organization;
        $GLOBALS['gSettingsManager'] = $settings;
        $GLOBALS['gCurrentOrgId'] = $orgId;
        $GLOBALS['gProfileFields'] = new ProfileFields($this->database, $orgId);
        $GLOBALS['gCurrentUser'] = $user;
        $GLOBALS['gCurrentUserId'] = (int)$user->getValue('usr_id');
        $GLOBALS['gCurrentUserUUID'] = $user->getValue('usr_uuid');
        $GLOBALS['gValidLogin'] = true;
        $GLOBALS['gL10n'] = new Language($settings->getString('system_language'), true);
    }

    /**
     * Read the Basic credentials from the different server variants.
     * @return array{username: string, password: string}|null
     */
    private function getBasicCredentials(): ?array
    {
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            return array(
                'username' => (string)$_SERVER['PHP_AUTH_USER'],
                'password' => (string)($_SERVER['PHP_AUTH_PW'] ?? '')
            );
        }

        $authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($authorization === '' || !preg_match('/^Basic\s+([A-Za-z0-9+\/=]+)\s*$/i', $authorization, $matches)) {
            return null;
        }

        $decoded = base64_decode($matches[1], true);
        if ($decoded === false) {
            return null;
        }

        $separator = strpos($decoded, ':');
        if ($separator === false) {
            return null;
        }

        return array(
            'username' => substr($decoded, 0, $separator),
            'password' => substr($decoded, $separator + 1)
        );
    }

    /**
     * Resolve the organization of a feed. Without a short name the current
     * organization of the request is used.
     * @throws Exception
     */
    private function resolveOrganization(string $shortName): ?Organization
    {
        if ($shortName === '' && isset($GLOBALS['gCurrentOrganization']) && $GLOBALS['gCurrentOrganization'] instanceof Organization) {
            $organization = $GLOBALS['gCurrentOrganization'];
        } else {
            $organization = new Organization($this->database, $shortName);
        }

        if ((int)$organization->getValue('org_id') === 0) {
            return null;
        }

        return $organization;
    }

    /**
     * Send a Basic challenge. Calendar clients must never receive the HTML login.
     */
    public function sendChallenge(): void
    {
        global $gL10n;

        http_response_code(401);
        header('WWW-Authenticate: Basic realm="Admidio calendar", charset="UTF-8"');
        header('Content-Type: text/plain; charset=utf-8');
        echo $gL10n->get('SYS_CALENDAR_FEED_INVALID_TOKEN');
    }

    /**
     * Send a plain text error response.
     */
    private function sendError(int $statusCode, string $translationId): void
    {
        global $gL10n;

        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=utf-8');
        echo $gL10n->get($translationId);
    }
}
