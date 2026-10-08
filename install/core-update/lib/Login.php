<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * The login of the updater.
 *
 * It is needed where the Admidio login is not at hand: for the updater of an older installation
 * that was copied in by hand, to continue an update in another browser, and when the updater
 * cookie is gone. It checks what the Admidio login checks, with plain database reads:
 *
 * - the login name, case-insensitive, of a valid user;
 * - the password, with the hash formats Admidio uses. An MD5 hash from a very old version is
 *   refused with the hint to log in to Admidio once, which converts it;
 * - the code of the authenticator app when the user has set up two-factor authentication
 *   (RFC 6238 with the settings Admidio uses: SHA-1, 6 digits, 30 seconds, one step tolerance);
 * - that Admidio has not locked the account after wrong passwords;
 * - an active membership in a role with administrator rights, in any organization.
 *
 * Every failure says the same thing, and every attempt is written to login.log. The Throttle stops
 * a series of attempts.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Login
{
    public const PASSWORD_VALID = 'valid';
    public const PASSWORD_INVALID = 'invalid';
    public const PASSWORD_OUTDATED = 'outdated';

    /**
     * The values of Admidio's own lock: so many wrong passwords within LOCK_WINDOW seconds lock the
     * account for that long (User::hasMaxInvalidLogins()).
     */
    private const ADMIDIO_MAX_INVALID_LOGINS = 3;
    private const ADMIDIO_LOCK_WINDOW = 900;

    /**
     * @param Db $db
     * @param Throttle $throttle
     * @param string $logFile adm_my_files/core-update/login.log
     */
    public function __construct(
        private readonly Db $db,
        private readonly Throttle $throttle,
        private readonly string $logFile
    ) {
    }

    /**
     * Check a login and return the ID of the administrator.
     * @param string $loginName
     * @param string $password
     * @param string $totpCode
     * @param string $address The IP address of the request, for the log.
     * @param int $now
     * @return int
     * @throws UpdaterException SYS_CORE_UPDATE_LOGIN_BLOCKED, SYS_CORE_UPDATE_LOGIN_OUTDATED_PASSWORD
     *                          or SYS_CORE_UPDATE_LOGIN_FAILED.
     */
    public function authenticate(string $loginName, string $password, string $totpCode, string $address, int $now): int
    {
        if ($this->throttle->getBlockedUntil($now) > 0) {
            $this->log($now, $address, $loginName, 'blocked');
            throw new UpdaterException('SYS_CORE_UPDATE_LOGIN_BLOCKED');
        }

        $user = $loginName === '' ? null : $this->db->fetchRow(
            'SELECT usr_id, usr_password, usr_tfa_secret, usr_number_invalid, usr_date_invalid
               FROM ' . $this->db->table('users') . '
              WHERE LOWER(usr_login_name) = LOWER(?)
                AND usr_valid = true',
            array($loginName)
        );

        $passwordResult = $user === null ? self::PASSWORD_INVALID
            : self::verifyPassword($password, (string)$user['usr_password']);

        if ($passwordResult === self::PASSWORD_OUTDATED) {
            $this->log($now, $address, $loginName, 'outdated password hash');
            throw new UpdaterException('SYS_CORE_UPDATE_LOGIN_OUTDATED_PASSWORD');
        }

        $reason = match (true) {
            $passwordResult !== self::PASSWORD_VALID => 'wrong login name or password',
            !self::verifyTotp((string)($user['usr_tfa_secret'] ?? ''), $totpCode, $now) => 'wrong security code',
            self::isLockedByAdmidio($user, $now) => 'account locked by Admidio',
            !$this->isAdministrator((int)$user['usr_id'], $now) => 'no administrator',
            default => ''
        };

        if ($reason !== '') {
            $this->throttle->recordFailure($now);
            $this->log($now, $address, $loginName, 'failed: ' . $reason);
            throw new UpdaterException('SYS_CORE_UPDATE_LOGIN_FAILED');
        }

        $this->throttle->reset();
        $this->log($now, $address, $loginName, 'success, user ' . $user['usr_id']);

        return (int)$user['usr_id'];
    }

    /**
     * Check a password against a hash the way Admidio's PasswordUtils::verify() does. An MD5 hash
     * is recognised, but reported as outdated instead of valid when it matches.
     * @param string $password
     * @param string $hash
     * @return string One of the PASSWORD_* constants.
     */
    public static function verifyPassword(string $password, string $hash): string
    {
        if (str_starts_with($hash, '$argon2id$') || str_starts_with($hash, '$argon2i$') || str_starts_with($hash, '$2y$')) {
            return password_verify($password, $hash) ? self::PASSWORD_VALID : self::PASSWORD_INVALID;
        }
        if (str_starts_with($hash, '$6$')) {
            return hash_equals($hash, crypt($password, $hash)) ? self::PASSWORD_VALID : self::PASSWORD_INVALID;
        }
        if (preg_match('/^[\dA-Fa-f]{32}$/', $hash) === 1) {
            return hash_equals(strtolower($hash), md5($password)) ? self::PASSWORD_OUTDATED : self::PASSWORD_INVALID;
        }

        return self::PASSWORD_INVALID;
    }

    /**
     * Check the code of an authenticator app. Without a secret, two-factor authentication is not set
     * up and any code - also none - is accepted.
     * @param string $secret The base32 secret of the user.
     * @param string $code
     * @param int $now
     * @return bool
     */
    public static function verifyTotp(string $secret, string $code, int $now): bool
    {
        if ($secret === '') {
            return true;
        }

        $code = preg_replace('/\s+/', '', $code) ?? '';
        $key = self::decodeBase32($secret);
        if ($key === '' || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        // Every step of the window is checked, so the time does not tell which one matched.
        $valid = false;
        $counter = intdiv($now, 30);
        for ($step = -1; $step <= 1; $step++) {
            $valid = hash_equals(self::totpCode($key, $counter + $step, 6), $code) || $valid;
        }

        return $valid;
    }

    /**
     * The one-time password of a counter value (RFC 4226 with the counter of RFC 6238).
     * @param string $key The secret as bytes.
     * @param int $counter
     * @param int $digits
     * @return string
     */
    public static function totpCode(string $key, int $counter, int $digits): string
    {
        $hash = hash_hmac('sha1', pack('J', $counter), $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string)($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Decode base32 (RFC 4648), the form in which authenticator secrets are stored.
     * @param string $value
     * @return string The bytes, or an empty string if the value is not base32.
     */
    public static function decodeBase32(string $value): string
    {
        $value = strtoupper(rtrim(preg_replace('/\s+/', '', $value) ?? '', '='));
        if ($value === '' || preg_match('/^[A-Z2-7]+$/', $value) !== 1) {
            return '';
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($value) as $character) {
            $bits .= str_pad(decbin(strpos($alphabet, $character)), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

        return $bytes;
    }

    /**
     * Whether Admidio has locked the account after too many wrong passwords.
     * @param array<string,mixed> $user
     * @param int $now
     * @return bool
     */
    private static function isLockedByAdmidio(array $user, int $now): bool
    {
        if ((int)($user['usr_number_invalid'] ?? 0) < self::ADMIDIO_MAX_INVALID_LOGINS) {
            return false;
        }
        $lastInvalid = strtotime((string)($user['usr_date_invalid'] ?? ''));

        return $lastInvalid !== false && $lastInvalid > $now - self::ADMIDIO_LOCK_WINDOW;
    }

    /**
     * Whether the user is an active member of a role with administrator rights, in any organization.
     * @param int $userId
     * @param int $now
     * @return bool
     */
    private function isAdministrator(int $userId, int $now): bool
    {
        $today = date('Y-m-d', $now);

        return (int)$this->db->fetchValue(
            'SELECT COUNT(*)
               FROM ' . $this->db->table('members') . '
         INNER JOIN ' . $this->db->table('roles') . '
                 ON rol_id = mem_rol_id
              WHERE mem_usr_id = ?
                AND mem_begin <= ?
                AND mem_end > ?
                AND rol_valid = true
                AND rol_administrator = true',
            array($userId, $today, $today)
        ) > 0;
    }

    /**
     * Write one attempt to the login log.
     * @param int $now
     * @param string $address
     * @param string $loginName
     * @param string $result
     * @return void
     */
    private function log(int $now, string $address, string $loginName, string $result): void
    {
        $line = date('Y-m-d H:i:s', $now) . "\t" . $address . "\t"
            . preg_replace('/[\x00-\x1f\x7f]/', '?', mb_substr($loginName, 0, 100)) . "\t" . $result . PHP_EOL;
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
