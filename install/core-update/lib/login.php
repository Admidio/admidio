<?php
/**
 * The updater's own login. It is the fallback while maintenance mode is on and Admidio cannot be
 * used: to continue an update from another device, or after the updater cookie was lost. Before
 * that, the updater uses the Admidio login.
 *
 * It checks what the Admidio login checks, with plain database reads: a valid user, the password in
 * the hash formats Admidio uses (an MD5 hash of a very old version is refused with the hint to log
 * in to Admidio once), the code of the authenticator app when two-factor authentication is set up
 * (RFC 6238: SHA-1, 6 digits, 30 seconds, one step tolerance), that Admidio has not locked the
 * account, and an active membership in a role with administrator rights in any organization.
 *
 * Every failure says the same thing; every attempt is written to login.log; after
 * MAX_LOGIN_FAILURES failures within LOGIN_WINDOW seconds no login is accepted for LOGIN_BLOCK
 * seconds, counted for the whole installation.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use PDO;

const MAX_LOGIN_FAILURES = 5;
const LOGIN_WINDOW = 900;
const LOGIN_BLOCK = 900;

/**
 * Check a login and return the ID of the administrator.
 * @param string $directory adm_my_files/core-update, which holds login-throttle.json and login.log.
 * @throws UpdaterException SYS_CORE_UPDATE_LOGIN_BLOCKED, SYS_CORE_UPDATE_LOGIN_OUTDATED_PASSWORD or
 *                          SYS_CORE_UPDATE_LOGIN_FAILED.
 */
function authenticateAdministrator(PDO $pdo, string $tablePrefix, string $directory, string $loginName, string $password,
    string $totpCode, string $address, int $now): int
{
    $throttleFile = $directory . '/login-throttle.json';
    $log = static function (string $result) use ($directory, $now, $address, $loginName): void {
        $line = date('Y-m-d H:i:s', $now) . "\t" . $address . "\t"
            . preg_replace('/[\x00-\x1f\x7f]/', '?', mb_substr($loginName, 0, 100)) . "\t" . $result . PHP_EOL;
        @file_put_contents($directory . '/login.log', $line, FILE_APPEND | LOCK_EX);
    };

    if (loginBlockedUntil($throttleFile, $now) > 0) {
        $log('blocked');
        throw new UpdaterException('SYS_CORE_UPDATE_LOGIN_BLOCKED');
    }

    $user = $loginName === '' ? null : dbRow($pdo,
        'SELECT usr_id, usr_password, usr_tfa_secret, usr_number_invalid, usr_date_invalid FROM ' . $tablePrefix . '_users
          WHERE LOWER(usr_login_name) = LOWER(?) AND usr_valid = true', array($loginName));
    $password = $user === null ? 'invalid' : verifyPassword($password, (string)$user['usr_password']);

    if ($password === 'outdated') {
        $log('outdated password hash');
        throw new UpdaterException('SYS_CORE_UPDATE_LOGIN_OUTDATED_PASSWORD');
    }

    $reason = match (true) {
        $password !== 'valid' => 'wrong login name or password',
        !verifyTotp((string)($user['usr_tfa_secret'] ?? ''), $totpCode, $now) => 'wrong security code',
        // Admidio's own lock: 3 wrong passwords within 15 minutes (User::hasMaxInvalidLogins())
        (int)($user['usr_number_invalid'] ?? 0) >= 3 && (int)strtotime((string)$user['usr_date_invalid']) > $now - 900 => 'account locked by Admidio',
        !isAdministrator($pdo, $tablePrefix, (int)$user['usr_id'], $now) => 'no administrator',
        default => ''
    };

    if ($reason !== '') {
        recordLoginFailure($throttleFile, $now);
        $log('failed: ' . $reason);
        throw new UpdaterException('SYS_CORE_UPDATE_LOGIN_FAILED');
    }

    changeThrottle($throttleFile, static fn(array $data): array => array('blockedUntil' => (int)($data['blockedUntil'] ?? 0)));
    $log('success, user ' . $user['usr_id']);

    return (int)$user['usr_id'];
}

/**
 * Check a password the way Admidio's PasswordUtils::verify() does. A matching MD5 hash is reported
 * as "outdated" instead of "valid".
 * @return string "valid", "invalid" or "outdated"
 */
function verifyPassword(string $password, string $hash): string
{
    if (str_starts_with($hash, '$argon2id$') || str_starts_with($hash, '$argon2i$') || str_starts_with($hash, '$2y$')) {
        return password_verify($password, $hash) ? 'valid' : 'invalid';
    }
    if (str_starts_with($hash, '$6$')) {
        return hash_equals($hash, crypt($password, $hash)) ? 'valid' : 'invalid';
    }
    if (preg_match('/^[\dA-Fa-f]{32}$/', $hash) === 1) {
        return hash_equals(strtolower($hash), md5($password)) ? 'outdated' : 'invalid';
    }

    return 'invalid';
}

/**
 * Check the code of an authenticator app. Without a secret, two-factor authentication is not set up
 * and any code is accepted.
 */
function verifyTotp(string $secret, string $code, int $now): bool
{
    if ($secret === '') {
        return true;
    }

    $code = preg_replace('/\s+/', '', $code) ?? '';
    $key = decodeBase32($secret);
    if ($key === '' || preg_match('/^\d{6}$/', $code) !== 1) {
        return false;
    }

    // Every step is checked, so the time does not tell which one matched.
    $valid = false;
    for ($step = -1; $step <= 1; $step++) {
        $valid = hash_equals(totpCode($key, intdiv($now, 30) + $step, 6), $code) || $valid;
    }

    return $valid;
}

/** The one-time password of a counter value (RFC 4226 with the counter of RFC 6238). */
function totpCode(string $key, int $counter, int $digits): string
{
    $hash = hash_hmac('sha1', pack('J', $counter), $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $value = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16)
        | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

    return str_pad((string)($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

/** Decode base32 (RFC 4648); an empty string if the value is not base32. */
function decodeBase32(string $value): string
{
    $value = strtoupper(rtrim(preg_replace('/\s+/', '', $value) ?? '', '='));
    if ($value === '' || preg_match('/^[A-Z2-7]+$/', $value) !== 1) {
        return '';
    }

    $bits = '';
    foreach (str_split($value) as $character) {
        $bits .= str_pad(decbin(strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $character)), 5, '0', STR_PAD_LEFT);
    }

    $bytes = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $bytes .= chr(bindec($byte));
        }
    }

    return $bytes;
}

/** Whether the user is an active member of a role with administrator rights, in any organization. */
function isAdministrator(PDO $pdo, string $tablePrefix, int $userId, int $now): bool
{
    $today = date('Y-m-d', $now);
    $row = dbRow($pdo, 'SELECT COUNT(*) AS administrator FROM ' . $tablePrefix . '_members
        INNER JOIN ' . $tablePrefix . '_roles ON rol_id = mem_rol_id
        WHERE mem_usr_id = ? AND mem_begin <= ? AND mem_end > ? AND rol_valid = true AND rol_administrator = true',
        array($userId, $today, $today));

    return (int)($row['administrator'] ?? 0) > 0;
}

/** The time until which logins are blocked, or 0. */
function loginBlockedUntil(string $throttleFile, int $now): int
{
    $data = is_file($throttleFile) ? json_decode((string)file_get_contents($throttleFile), true) : null;
    $blockedUntil = is_array($data) ? (int)($data['blockedUntil'] ?? 0) : 0;

    return $blockedUntil > $now ? $blockedUntil : 0;
}

/** Count a failed login, and block the login once there were too many. */
function recordLoginFailure(string $throttleFile, int $now): void
{
    changeThrottle($throttleFile, static function (array $data) use ($now): array {
        $failures = array_values(array_filter((array)($data['failures'] ?? array()),
            static fn(mixed $time): bool => is_int($time) && $time > $now - LOGIN_WINDOW));
        $failures[] = $now;
        $data['failures'] = $failures;
        if (count($failures) >= MAX_LOGIN_FAILURES) {
            $data['blockedUntil'] = $now + LOGIN_BLOCK;
            $data['failures'] = array();
        }

        return $data;
    });
}

/**
 * Change the login count under an exclusive lock, so that parallel attempts are all counted.
 * @param callable(array<string,mixed>): array<string,mixed> $change
 */
function changeThrottle(string $throttleFile, callable $change): void
{
    ensureDirectory(dirname($throttleFile));
    $handle = @fopen($throttleFile, 'c+');
    if ($handle === false) {
        throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
    }

    try {
        flock($handle, LOCK_EX);
        $data = json_decode((string)stream_get_contents($handle), true);
        $data = $change(is_array($data) ? $data : array());
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR));
        fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * The login form.
 * @param array{language: string, texts: array<string,string>} $texts
 * @param string $error An error to show above the form, already translated.
 */
function loginForm(array $texts, string $csrfToken, string $error = ''): string
{
    return ($error !== '' ? '<div class="message error">' . h($error) . '</div>' : '')
        . '<p>' . h(text($texts, 'SYS_CORE_UPDATE_LOGIN_DESC')) . '</p>'
        . '<form method="post" action="index.php" autocomplete="off">'
        . '<input type="hidden" name="action" value="login">'
        . '<input type="hidden" name="csrf_token" value="' . h($csrfToken) . '">'
        . '<label>' . h(text($texts, 'SYS_CORE_UPDATE_LOGIN_NAME'))
        . '<input type="text" name="login_name" required maxlength="254" autocomplete="username"></label>'
        . '<label>' . h(text($texts, 'SYS_CORE_UPDATE_PASSWORD'))
        . '<input type="password" name="password" required autocomplete="current-password"></label>'
        . '<label>' . h(text($texts, 'SYS_CORE_UPDATE_SECURITY_CODE'))
        . '<input type="text" name="totp_code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"></label>'
        . '<button class="button" type="submit">' . h(text($texts, 'SYS_CORE_UPDATE_LOGIN')) . '</button>'
        . '</form>';
}
