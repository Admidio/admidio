<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * The updater cookie: who may continue an update.
 *
 * An administrator gets the cookie either from the Admidio settings, which start the update, or
 * from the login of the updater. Its value is **&lt;update ID&gt;.&lt;secret&gt;**, and only the SHA-256 of
 * the secret is stored, in the state of that update. Every browser has a credential of its own, so
 * continuing in a second browser does not end the first one.
 *
 * A credential expires LIFETIME seconds after the last request that used it, and all of them are
 * revoked when the update is over. The cookie is HttpOnly, SameSite=Lax, Secure under HTTPS and
 * limited to the install/ directory, where the updater and install/update.php live.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Credentials
{
    /**
     * A credential expires this many seconds after its last use.
     */
    public const LIFETIME = 86400;

    /**
     * The name of the updater cookie of an installation.
     * @param string $cookiePrefix The cookie prefix of the installation (COOKIE_PREFIX).
     * @return string
     */
    public static function getCookieName(string $cookiePrefix): string
    {
        return $cookiePrefix . '_CORE_UPDATE';
    }

    /**
     * Add a credential to the state of an update.
     * @param array<string,mixed> $state
     * @param int $now
     * @return string The value of the cookie.
     */
    public static function issue(array &$state, int $now): string
    {
        $secret = bin2hex(random_bytes(32));
        $credentials = self::withoutExpired((array)($state['credentials'] ?? array()), $now);
        $credentials[] = array('hash' => hash('sha256', $secret), 'createdAt' => $now, 'expiresAt' => $now + self::LIFETIME);
        $state['credentials'] = $credentials;

        return $state['updateId'] . '.' . $secret;
    }

    /**
     * The update ID and the secret of a cookie value, or **null** if it has not that form.
     * @param mixed $value
     * @return array{0: string, 1: string}|null
     */
    public static function parse(mixed $value): ?array
    {
        if (!is_string($value) || preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/', $value, $matches) !== 1) {
            return null;
        }

        return array($matches[1], $matches[2]);
    }

    /**
     * Whether a cookie value is a valid credential of this update. When it is, its expiry moves on
     * to LIFETIME seconds from now and expired credentials are dropped.
     * @param array<string,mixed> $state
     * @param mixed $value
     * @param int $now
     * @return bool
     */
    public static function refresh(array &$state, mixed $value, int $now): bool
    {
        $parsed = self::parse($value);
        if ($parsed === null || $parsed[0] !== ($state['updateId'] ?? '')) {
            return false;
        }

        $hash = hash('sha256', $parsed[1]);
        $credentials = self::withoutExpired((array)($state['credentials'] ?? array()), $now);
        $found = false;
        foreach ($credentials as &$credential) {
            if (hash_equals((string)$credential['hash'], $hash)) {
                $credential['expiresAt'] = $now + self::LIFETIME;
                $found = true;
            }
        }
        unset($credential);

        $state['credentials'] = $credentials;

        return $found;
    }

    /**
     * Whether a cookie value is a valid credential of this update, without changing anything.
     * @param array<string,mixed> $state
     * @param mixed $value
     * @param int $now
     * @return bool
     */
    public static function isValid(array $state, mixed $value, int $now): bool
    {
        return self::refresh($state, $value, $now);
    }

    /**
     * Revoke every credential, because the update is over.
     * @param array<string,mixed> $state
     * @return void
     */
    public static function revokeAll(array &$state): void
    {
        $state['credentials'] = array();
    }

    /**
     * Send the cookie to the browser.
     * @param Config $config
     * @param string $value
     * @return void
     */
    public static function sendCookie(Config $config, string $value): void
    {
        // The command line has no browser to send a cookie to.
        if (headers_sent()) {
            return;
        }
        setcookie(self::getCookieName($config->getCookiePrefix()), $value, self::cookieOptions($config, time() + self::LIFETIME));
    }

    /**
     * Remove the cookie from the browser.
     * @param Config $config
     * @return void
     */
    public static function clearCookie(Config $config): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::getCookieName($config->getCookiePrefix()), '', self::cookieOptions($config, time() - 3600));
    }

    /**
     * @param Config $config
     * @param int $expires
     * @return array<string,mixed>
     */
    private static function cookieOptions(Config $config, int $expires): array
    {
        return array(
            'expires' => $expires,
            'path' => $config->getBasePath() . '/install/',
            'secure' => $config->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax'
        );
    }

    /**
     * @param array<int,mixed> $credentials
     * @param int $now
     * @return array<int,array{hash: string, createdAt: int, expiresAt: int}>
     */
    private static function withoutExpired(array $credentials, int $now): array
    {
        return array_values(array_filter($credentials, static fn(mixed $credential): bool =>
            is_array($credential) && isset($credential['hash']) && (int)($credential['expiresAt'] ?? 0) >= $now));
    }
}
