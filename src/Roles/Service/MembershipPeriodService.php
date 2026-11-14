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
 * request of the day. Two preferences of the organization keep track of it:
 *
 * - **groups_roles_membership_period_date** is the last day that was processed completely. It is
 *   written only after the work is done, so a run that fails or is killed (a timeout, a restarted
 *   worker) leaves it as it was, and the next run starts from there again.
 * - **groups_roles_membership_period_lock** holds the start time of the run that is working right now.
 *   It is taken with an atomic UPDATE, so two requests or a request and the cron job do not do the work
 *   at the same time. A run that ends, also with an error, clears it; a lock that a killed run left
 *   behind counts as abandoned after LOCK_TIMEOUT seconds and is taken over.
 *
 * Doing a day twice does no harm to the tokens: revoking them again changes nothing. A callback of the
 * action that fails is logged and does not stop the others - the day is not repeated for it, because a
 * broken plugin would otherwise make every request do the work again.
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
    public const LOCK_SETTING = 'groups_roles_membership_period_lock';

    /** Seconds after which the lock of a run that did not finish counts as abandoned. */
    public const LOCK_TIMEOUT = 3600;

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

        $lock = $this->lock();
        if ($lock === null) {
            return null;
        }

        try {
            // read after the lock is taken: a run that just finished may have done today already
            $since = $this->lastProcessedDay($today);
            if ($since >= $today) {
                $gSettingsManager->has(self::SETTING, true);
                return null;
            }

            $count = $this->process($since, $today);
            $gSettingsManager->set(self::SETTING, $today);

            return $count;
        } finally {
            $this->unlock($lock);
        }
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

        // the OIDC tokens first: they are the security relevant part. One user that fails must not
        // keep the others' tokens alive, so all are tried before the failure is reported.
        $revocation = new SSOAccessRevocationService($this->db);
        $failure = null;
        foreach (array_keys($ended) as $userId) {
            try {
                $revocation->revokeLostClientAccess($userId);
            } catch (\Throwable $exception) {
                $failure ??= $exception;
            }
        }
        if ($failure !== null) {
            throw $failure;
        }

        if (Hooks::hasAction(self::HOOK)) {
            foreach ($rows as $row) {
                if ($row['mem_begin'] > $since && $row['mem_begin'] <= $until) {
                    Hooks::doActionCatchErrors(self::HOOK, $row, self::CHANGE_STARTED);
                }
                if ($row['mem_end'] > $since && $row['mem_end'] <= $until) {
                    Hooks::doActionCatchErrors(self::HOOK, $row, self::CHANGE_ENDED);
                }
            }
        }

        return count($rows);
    }

    /**
     * The last day that was processed completely, or yesterday if there was none yet.
     * @throws Exception
     */
    private function lastProcessedDay(string $today): string
    {
        $value = (string) $this->readPreference(self::SETTING);

        return $value === '' ? date('Y-m-d', (int) strtotime($today . ' -1 day')) : $value;
    }

    /**
     * Take the lock of the current organization, unless another run holds it. A lock older than
     * LOCK_TIMEOUT is one a killed run left behind and is taken over. Both the insert of a missing
     * preference and the conditional UPDATE are atomic, so of two requests only one gets the lock.
     *
     * @return string|null The value of the lock, which unlock() needs, or **null** if another run
     *                     holds it.
     * @throws Exception
     */
    private function lock(): ?string
    {
        global $gCurrentOrgId;

        $now = time();
        $lock = date('Y-m-d H:i:s', $now) . ' ' . bin2hex(random_bytes(4));
        $current = $this->readPreference(self::LOCK_SETTING);

        if ($current === false) {
            // an installation from before this preference; a concurrent insert fails on the unique index
            $inserted = $this->db->queryPrepared(
                'INSERT INTO ' . TBL_PREFERENCES . ' (prf_org_id, prf_name, prf_value) VALUES (?, ?, ?)',
                array((int) $gCurrentOrgId, self::LOCK_SETTING, $lock),
                false
            );
            return $inserted === false ? null : $lock;
        }

        $current = (string) $current;
        if ($current !== '' && (int) strtotime(substr($current, 0, 19)) > $now - self::LOCK_TIMEOUT) {
            return null;
        }

        return $this->replacePreference(self::LOCK_SETTING, $current, $lock) ? $lock : null;
    }

    /**
     * Release the lock, unless another run took it over in the meantime.
     * @throws Exception
     */
    private function unlock(string $lock): void
    {
        $this->replacePreference(self::LOCK_SETTING, $lock, '');
    }

    /**
     * The value of a preference of the current organization, read from the database and not from the
     * settings of the session, or **false** if there is none.
     * @throws Exception
     */
    private function readPreference(string $name): string|false
    {
        global $gCurrentOrgId;

        $value = $this->db->queryPrepared(
            'SELECT prf_value FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id = ? AND prf_name = ?',
            array((int) $gCurrentOrgId, $name)
        )->fetchColumn();

        return $value === false ? false : (string) $value;
    }

    /**
     * Change a preference of the current organization from $expected to $value, but only if it still
     * has the value $expected.
     * @return bool Whether the preference was changed.
     * @throws Exception
     */
    private function replacePreference(string $name, string $expected, string $value): bool
    {
        global $gCurrentOrgId;

        return $this->db->queryPrepared(
            'UPDATE ' . TBL_PREFERENCES . ' SET prf_value = ?
              WHERE prf_org_id = ? AND prf_name = ? AND prf_value = ?',
            array($value, (int) $gCurrentOrgId, $name, $expected)
        )->rowCount() === 1;
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
