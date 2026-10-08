<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * Protection of the updater's forms against requests from other sites.
 *
 * Every request that changes something is a POST. Once an administrator is logged in, it carries
 * the token of the update, which is kept in its state. The login form comes before that, so it
 * uses a token that is both a cookie and a form field: another site can submit the form, but it
 * cannot read the cookie to put the same value into it.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Csrf
{
    /**
     * Whether a request carries the token of the update.
     * @param array<string,mixed> $state
     * @param mixed $token
     * @return bool
     */
    public static function isValid(array $state, mixed $token): bool
    {
        return is_string($token) && $token !== ''
            && isset($state['csrfToken']) && hash_equals((string)$state['csrfToken'], $token);
    }

    /**
     * The name of the cookie that carries the token of the login form.
     * @param Config $config
     * @return string
     */
    public static function getLoginCookieName(Config $config): string
    {
        return $config->getCookiePrefix() . '_CORE_UPDATE_LOGIN';
    }

    /**
     * The token of the login form: the one the browser already has, or a new one, which is sent to
     * it.
     * @param Config $config
     * @param mixed $cookieValue
     * @return string
     */
    public static function getLoginToken(Config $config, mixed $cookieValue): string
    {
        if (is_string($cookieValue) && preg_match('/^[a-f0-9]{64}$/', $cookieValue) === 1) {
            return $cookieValue;
        }

        $token = bin2hex(random_bytes(32));
        setcookie(self::getLoginCookieName($config), $token, array(
            'expires' => 0,
            'path' => $config->getBasePath() . '/install/',
            'secure' => $config->isHttps(),
            'httponly' => true,
            'samesite' => 'Strict'
        ));

        return $token;
    }

    /**
     * Whether the login form was sent with the token of its cookie.
     * @param mixed $cookieValue
     * @param mixed $formValue
     * @return bool
     */
    public static function isValidLogin(mixed $cookieValue, mixed $formValue): bool
    {
        return is_string($cookieValue) && is_string($formValue)
            && preg_match('/^[a-f0-9]{64}$/', $cookieValue) === 1 && hash_equals($cookieValue, $formValue);
    }
}
