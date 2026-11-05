<?php

namespace Admidio\SSO\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;

/**
 * Slows down guessing of client secrets at the OIDC endpoints that authenticate a client.
 *
 * Failed authentications are counted per client and address, and per address alone. The count of a
 * key grows with every failure. The first 9 failures are free, then the key is locked for a growing
 * time: 1, 2, 4 and 8 minutes for the 10th to 13th failure, 15 minutes from the 14th (SCHEDULE). The count starts again after a
 * day without failure, and a successful authentication resets the count of the client and address.
 *
 * A client ID is public, so a count per client alone would let anybody lock a client out. The count per
 * client AND address only affects the address that fails. The count per address alone catches an
 * address that tries many clients, with a higher threshold (it starts to lock with the 30th failure) so
 * that many users behind one proxy are not locked by a few mistyped secrets.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SSOAuthThrottleService
{
    private const KIND_CLIENT = 'client_ip';
    private const KIND_IP = 'ip';

    /** Seconds without a failure after which the count starts again */
    private const RESET_AFTER = 86400;

    /** Failures of an address alone that are free before the first lock */
    private const IP_ALLOWANCE = 20;

    /** Lock in seconds by the number of failures; counts above the last entry take its lock */
    private const SCHEDULE = array(10 => 60, 11 => 120, 12 => 240, 13 => 480, 14 => 900);

    public function __construct(private Database $database)
    {
    }

    /**
     * The lock that follows a number of failures.
     * @param int $failures Number of failures of a key since the count started
     * @return int Seconds the key is locked, 0 for none
     */
    public static function lockSeconds(int $failures): int
    {
        $seconds = 0;
        foreach (self::SCHEDULE as $threshold => $lock) {
            if ($failures >= $threshold) {
                $seconds = $lock;
            }
        }
        return $seconds;
    }

    /**
     * Seconds a client has to wait from this address, 0 if it may try.
     * @throws Exception
     */
    public function getRetryAfter(string $clientId, string $address): int
    {
        $statement = $this->database->queryPrepared(
            'SELECT MAX(saf_locked_until) FROM ' . TBL_SSO_AUTH_FAILURES . '
              WHERE (saf_kind = ? AND saf_key = ?) OR (saf_kind = ? AND saf_key = ?)',
            array(self::KIND_CLIENT, $this->key($clientId, $address), self::KIND_IP, $this->key('', $address))
        );

        return max(0, (int) $statement->fetchColumn() - time());
    }

    /**
     * Count a failed authentication.
     * @throws Exception
     */
    public function recordFailure(string $clientId, string $address): void
    {
        $now = time();
        $this->database->queryPrepared(
            'DELETE FROM ' . TBL_SSO_AUTH_FAILURES . ' WHERE saf_last_failure < ?',
            array($now - self::RESET_AFTER)
        );
        $this->count(self::KIND_CLIENT, $this->key($clientId, $address), 0, $now);
        $this->count(self::KIND_IP, $this->key('', $address), self::IP_ALLOWANCE, $now);
    }

    /**
     * A successful authentication ends the lock of the client at this address.
     * @throws Exception
     */
    public function recordSuccess(string $clientId, string $address): void
    {
        $this->database->queryPrepared(
            'DELETE FROM ' . TBL_SSO_AUTH_FAILURES . ' WHERE saf_kind = ? AND saf_key = ?',
            array(self::KIND_CLIENT, $this->key($clientId, $address))
        );
    }

    /**
     * @throws Exception
     */
    private function count(string $kind, string $key, int $allowance, int $now): void
    {
        $row = $this->database->queryPrepared(
            'SELECT saf_id, saf_count FROM ' . TBL_SSO_AUTH_FAILURES . ' WHERE saf_kind = ? AND saf_key = ?',
            array($kind, $key)
        )->fetch();

        $failures = ($row === false ? 0 : (int) $row['saf_count']) + 1;
        $lockedUntil = ($seconds = self::lockSeconds($failures - $allowance)) > 0 ? $now + $seconds : 0;

        if ($row === false) {
            $this->database->queryPrepared(
                'INSERT INTO ' . TBL_SSO_AUTH_FAILURES . ' (saf_kind, saf_key, saf_count, saf_last_failure, saf_locked_until)
                 VALUES (?, ?, ?, ?, ?)',
                array($kind, $key, $failures, $now, $lockedUntil)
            );
        } else {
            $this->database->queryPrepared(
                'UPDATE ' . TBL_SSO_AUTH_FAILURES . ' SET saf_count = ?, saf_last_failure = ?, saf_locked_until = ?
                  WHERE saf_id = ?',
                array($failures, $now, $lockedUntil, (int) $row['saf_id'])
            );
        }
    }

    /** The IDs and addresses are hashed: they have any length and the table does not need to hold them */
    private function key(string $clientId, string $address): string
    {
        return hash('sha256', $clientId . "\n" . $address);
    }
}
