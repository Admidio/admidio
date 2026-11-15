<?php
namespace Admidio\Events\Repository;

use Admidio\Events\Entity\CalendarFeed;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;

/**
 * Repository for token based calendar feeds.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class CalendarFeedRepository
{
    private const LAST_USED_UPDATE_INTERVAL = 3600;

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Create and save a new calendar feed.
     * @throws Exception
     */
    public function create(
        int $orgId,
        int $userId,
        string $tokenHash,
        string $name,
        string $categoryUuid = '',
        ?string $expires = null
    ): CalendarFeed {
        $feed = new CalendarFeed($this->database);
        $feed->setValue('cfd_org_id', $orgId);
        $feed->setValue('cfd_usr_id', $userId);
        $feed->setValue('cfd_token_hash', $tokenHash);
        $feed->setValue('cfd_name', $name);
        $feed->setValue('cfd_cat_uuid', $categoryUuid);
        $feed->setValue('cfd_timestamp_expires', $expires);
        $feed->setValue('cfd_active', true);
        $feed->save();

        return $feed;
    }

    /**
     * Load a feed by its technical id.
     * @throws Exception
     */
    public function readById(int $feedId): ?CalendarFeed
    {
        if ($feedId <= 0) {
            return null;
        }

        $feed = new CalendarFeed($this->database);
        if (!$feed->readDataById($feedId)) {
            return null;
        }

        return $feed;
    }

    /**
     * Load an active feed by its token hash within one organization.
     * @throws Exception
     */
    public function readByTokenHash(string $tokenHash, int $orgId): ?CalendarFeed
    {
        if ($tokenHash === '' || $orgId <= 0) {
            return null;
        }

        $feed = new CalendarFeed($this->database);
        if (!$feed->readDataByColumns(array('cfd_token_hash' => $tokenHash, 'cfd_org_id' => $orgId))) {
            return null;
        }

        return $feed;
    }

    /**
     * List all feeds of a user in an organization. The token hash is never returned.
     * @return array<int,array<string,mixed>>
     * @throws Exception
     */
    public function listByUser(int $orgId, int $userId): array
    {
        $sql = 'SELECT cfd.cfd_id, cfd.cfd_name, cfd.cfd_cat_uuid, cfd.cfd_timestamp_create,
                       cfd.cfd_timestamp_last_used, cfd.cfd_timestamp_expires, cfd.cfd_active,
                       cat.cat_name
                  FROM ' . TBL_CALENDAR_FEEDS . ' AS cfd
             LEFT JOIN ' . TBL_CATEGORIES . ' AS cat
                    ON cat.cat_uuid = cfd.cfd_cat_uuid
                 WHERE cfd.cfd_org_id = ?
                   AND cfd.cfd_usr_id = ?
              ORDER BY cfd.cfd_timestamp_create DESC, cfd.cfd_id DESC';

        return $this->database->queryPrepared($sql, array($orgId, $userId))->fetchAll();
    }

    /**
     * Revoke a feed that belongs to the given user and organization.
     * @return bool Returns true if an active feed was revoked.
     * @throws Exception
     */
    public function revoke(int $feedId, int $orgId, int $userId): bool
    {
        $sql = 'UPDATE ' . TBL_CALENDAR_FEEDS . '
                   SET cfd_active = ?
                 WHERE cfd_id = ?
                   AND cfd_org_id = ?
                   AND cfd_usr_id = ?
                   AND cfd_active = ?';

        return $this->database->queryPrepared($sql, array(false, $feedId, $orgId, $userId, true))->rowCount() > 0;
    }

    /**
     * Store the last usage of a feed, but at most once per hour.
     * @throws Exception
     */
    public function touchLastUsed(int $feedId): void
    {
        $sql = 'SELECT cfd_timestamp_last_used FROM ' . TBL_CALENDAR_FEEDS . ' WHERE cfd_id = ?';
        $lastUsed = $this->database->queryPrepared($sql, array($feedId))->fetchColumn();

        if ($lastUsed !== false && $lastUsed !== null && $lastUsed !== '') {
            $lastUsedTimestamp = strtotime((string)$lastUsed);
            if ($lastUsedTimestamp !== false && $lastUsedTimestamp > time() - self::LAST_USED_UPDATE_INTERVAL) {
                return;
            }
        }

        $sql = 'UPDATE ' . TBL_CALENDAR_FEEDS . ' SET cfd_timestamp_last_used = ? WHERE cfd_id = ?';
        $this->database->queryPrepared($sql, array(date('Y-m-d H:i:s'), $feedId));
    }


}
