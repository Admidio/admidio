<?php
namespace Admidio\Events\Service;

use Admidio\Events\Entity\CalendarFeed;
use Admidio\Events\Repository\CalendarFeedRepository;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Organizations\Entity\Organization;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Users\Entity\User;
use DateTimeInterface;

/**
 * Business logic for token based calendar feeds.
 *
 * The token is generated once, returned to the caller and stored only as a
 * SHA-256 hash. A feed always belongs to exactly one user and one organization.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class CalendarFeedService
{
    private const TOKEN_PREFIX = 'admcf_';
    private const TOKEN_BYTES = 32;

    private CalendarFeedRepository $repository;

    public function __construct(private readonly Database $database)
    {
        $this->repository = new CalendarFeedRepository($database);
    }

    /**
     * Create a new feed for a user.
     *
     * The plain token is part of the return value and must be shown to the user
     * immediately. It cannot be read again later because only its hash is stored.
     *
     * @return array{feed: CalendarFeed, token: string, url: string}
     * @throws Exception
     */
    public function createFeed(
        int $orgId,
        int $userId,
        string $label,
        ?DateTimeInterface $expires = null,
        string $categoryUuid = ''
    ): array {
        $label = trim(StringUtils::strStripTags($label));
        if ($label === '') {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_CALENDAR_FEED_NAME'));
        }

        $organization = new Organization($this->database, $orgId);
        if ((int)$organization->getValue('org_id') === 0) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $user = $this->loadUser($orgId, $userId);
        if (!$user->getValue('usr_valid')
            || (string)$user->getValue('usr_login_name', 'database') === ''
            || !$user->isMemberOfOrganization()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        if ($categoryUuid !== '' && !in_array($categoryUuid, $user->getAllVisibleCategories('EVT', 'uuid'), true)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $token = $this->generateToken();
        $expiresValue = $expires === null ? null : $expires->format('Y-m-d H:i:s');

        $feed = $this->repository->create(
            $orgId,
            $userId,
            self::hashToken($token),
            $label,
            $categoryUuid,
            $expiresValue
        );

        return array(
            'feed' => $feed,
            'token' => $token,
            'url' => $this->buildFeedUrl($organization, $categoryUuid)
        );
    }

    /**
     * List all feeds of a user without exposing the token hash.
     * @return array<int,array<string,mixed>>
     * @throws Exception
     */
    public function listFeeds(int $orgId, int $userId): array
    {
        return $this->repository->listByUser($orgId, $userId);
    }

    /**
     * Revoke one of the user's feeds.
     * @throws Exception
     */
    public function revokeFeed(int $feedId, int $userId, int $orgId): void
    {
        if ($feedId <= 0) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        if (!$this->repository->revoke($feedId, $orgId, $userId)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
    }

    /**
     * Resolve a token to its feed and user.
     *
     * The token is only valid for the organization it was created for, while the
     * user is active, is a member of the organization and the feed has not expired.
     *
     * @return array{feed: CalendarFeed, user: User}|null
     * @throws Exception
     */
    public function authenticate(string $token, Organization $organization): ?array
    {
        $orgId = (int)$organization->getValue('org_id');
        if ($token === '' || $orgId <= 0) {
            return null;
        }

        $feed = $this->repository->readByTokenHash(self::hashToken($token), $orgId);
        if ($feed === null || !$feed->isActive()) {
            return null;
        }

        $user = $this->loadUser($orgId, (int)$feed->getValue('cfd_usr_id'));
        if (!$user->getValue('usr_valid')
            || (string)$user->getValue('usr_login_name', 'database') === ''
            || !$user->isMemberOfOrganization()) {
            return null;
        }

        return array('feed' => $feed, 'user' => $user);
    }

    /**
     * Remember the last successful usage of a feed.
     * @throws Exception
     */
    public function touchLastUsed(int $feedId): void
    {
        $this->repository->touchLastUsed($feedId);
    }

    /**
     * Build the complete feed URL for a user-facing token display.
     * @throws Exception
     */
    public function buildFeedUrl(Organization $organization, string $categoryUuid = ''): string
    {
        $params = array(
            'mode' => 'feed',
            'org_shortname' => (string)$organization->getValue('org_shortname')
        );
        if ($categoryUuid !== '') {
            $params['cat_uuid'] = $categoryUuid;
        }

        return SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events.php', $params);
    }

    /**
     * Create a URL safe random token.
     */
    private function generateToken(): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');

        return self::TOKEN_PREFIX . $token;
    }

    /**
     * Hash a token for storage and lookup.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Load a user with the rights of the given organization.
     * @throws Exception
     */
    private function loadUser(int $orgId, int $userId): User
    {
        $profileFields = new ProfileFields($this->database, $orgId);

        $previousOrgId = $GLOBALS['gCurrentOrgId'] ?? 0;
        $GLOBALS['gCurrentOrgId'] = $orgId;
        try {
            $user = new User($this->database, $profileFields, $userId);
        } finally {
            $GLOBALS['gCurrentOrgId'] = $previousOrgId;
        }
        $user->setOrganization($orgId);

        return $user;
    }
}
