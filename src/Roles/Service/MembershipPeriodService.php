<?php
namespace Admidio\Roles\Service;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\SSO\Service\SSOAccessRevocationService;

/**
 * Memberships begin and end on whole days. A membership whose start or end date is reached changes
 * its state at midnight, without anybody saving it, so nothing that reacts to the saving of a
 * membership learns about it. This service catches up with those changes once per day:
 *
 * - it revokes the OIDC tokens of clients that a user may no longer use because a membership ended
 *   (the same as SSOAccessRevocationService does when a membership is saved), and
 * - it dispatches the action **membership_period_changed** for every membership that started or
 *   ended, so that plugins can react to it.
 *
 * It runs as the maintenance job **memberships** and, for installations without cron, on the first
 * request of the day. The day is claimed in the database before the work starts, so two requests or a
 * request and the cron job do not do it twice.
 *
 * The callbacks of the action receive the membership as array (mem_id, mem_uuid, mem_usr_id,
 * mem_rol_id, mem_begin, mem_end, mem_leader) and **started** or **ended**:
 * ```
 * Hooks::addAction('membership_period_changed', function (array $membership, string $change) {
 *     ...
 * });
 * ```
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class MembershipPeriodService
{
    public const HOOK = 'membership_period_changed';
    public const SETTING = 'groups_roles_membership_period_date';

    public const CHANGE_STARTED = 'started';
    public const CHANGE_ENDED = 'ended';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Process the memberships whose start or end date was reached since the last run, at most once
     * per day.
     *
     * @param string|null $today The day to catch up to, by default today.
     * @return int|null The number of memberships, or **null** if today was done already.
     * @throws Exception
     */
    public function runIfDue(?string $today = null): ?int
    {
        global $gSettingsManager;

        $today ??= DATE_NOW;

        // the settings of a session may be cached; only a value that is today already is conclusive
        $cached = $gSettingsManager->has(self::SETTING) ? $gSettingsManager->getString(self::SETTING) : '';
        if ($cached !== '' && $cached >= $today) {
            return null;
        }

        $since = $this->claim($today);
        if ($since === null) {
            return null;
        }
        return $this->process($since, $today);
    }

    /**
     * Report the memberships of the current organization whose start date or end date lies after
     * $since and up to $until. The end date is the first day without membership, so a membership
     * with mem_end = $until ended today.
     *
     * @return int The number of memberships.
     * @throws Exception
     */
    public function process(string $since, string $until): int
    {
        global $gCurrentOrgId;

        $rows = $this->db->getArrayFromSql(
            'SELECT mem_id, mem_uuid, mem_usr_id, mem_rol_id, mem_begin, mem_end, mem_leader
               FROM ' . TBL_MEMBERS . '
              INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
              INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
              WHERE (cat_org_id = ? OR cat_org_id IS NULL)
                AND ((mem_begin > ? AND mem_begin <= ?) OR (mem_end > ? AND mem_end <= ?))
              ORDER BY mem_id',
            array((int) $gCurrentOrgId, $since, $until, $since, $until)
        );

        $ended = array();
        foreach ($rows as $row) {
            if ($row['mem_end'] > $since && $row['mem_end'] <= $until) {
                $ended[(int) $row['mem_usr_id']] = true;
            }
        }

        // the OIDC tokens first: they are the security relevant part
        $revocation = new SSOAccessRevocationService($this->db);
        foreach (array_keys($ended) as $userId) {
            $revocation->revokeLostClientAccess($userId);
        }

        if (Hooks::hasAction(self::HOOK)) {
            foreach ($rows as $row) {
                if ($row['mem_begin'] > $since && $row['mem_begin'] <= $until) {
                    Hooks::doAction(self::HOOK, $row, self::CHANGE_STARTED);
                }
                if ($row['mem_end'] > $since && $row['mem_end'] <= $until) {
                    Hooks::doAction(self::HOOK, $row, self::CHANGE_ENDED);
                }
            }
        }

        return count($rows);
    }

    /**
     * Claim the day: write it into the preference unless it is there already. The conditional UPDATE
     * is atomic, so of two requests that arrive at the same time only one gets the day.
     *
     * @return string|null The last day that was processed (yesterday for the very first run), or
     *                     **null** if another request or run claimed today already.
     * @throws Exception
     */
    private function claim(string $today): ?string
    {
        global $gSettingsManager, $gCurrentOrgId;

        $yesterday = date('Y-m-d', (int) strtotime($today . ' -1 day'));
        $current = $this->db->queryPrepared(
            'SELECT prf_value FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id = ? AND prf_name = ?',
            array((int) $gCurrentOrgId, self::SETTING)
        )->fetchColumn();

        if ($current === false) {
            // an installation from before this preference: create it, and start with yesterday
            $gSettingsManager->set(self::SETTING, $today);
            return $yesterday;
        }

        $current = (string) $current;
        if ($current !== '' && $current >= $today) {
            return null;
        }
        $claimed = $this->db->queryPrepared(
            'UPDATE ' . TBL_PREFERENCES . ' SET prf_value = ?
              WHERE prf_org_id = ? AND prf_name = ? AND prf_value = ?',
            array($today, (int) $gCurrentOrgId, self::SETTING, $current)
        )->rowCount() === 1;

        if (!$claimed) {
            return null;
        }

        // keep the settings of this request, which may come from the session, in line
        $gSettingsManager->set(self::SETTING, $today);
        return $current === '' ? $yesterday : $current;
    }

    /**
     * Run once per day for the current organization, and never let a failure reach the request
     * that triggered it.
     */
    public static function runIfDueSilently(Database $db): void
    {
        global $gLogger;

        try {
            (new self($db))->runIfDue();
        } catch (\Throwable $exception) {
            $gLogger->warning('The daily check of membership periods failed.', array('message' => $exception->getMessage()));
        }
    }
}
