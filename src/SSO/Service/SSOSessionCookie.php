<?php
namespace Admidio\SSO\Service;

use Admidio\Hooks\Hooks;

/**
 * The cookie that carries the session id to the single sign-on endpoints.
 *
 * A service provider on another site sends its requests (SAML HTTP-POST binding, OIDC form
 * posts) as cross-site posts, which do not carry Admidio's SameSite=Lax session cookie. Admidio
 * then did not see the session, asked a logged-in user to log in again and replaced the session
 * cookie with the one of a new session. This second cookie carries the same session id with
 * SameSite=None, but only to the path of the SSO module, so that the rest of Admidio keeps the
 * protection of SameSite=Lax. Identity providers like Keycloak and Shibboleth keep their session
 * cookies the same way.
 *
 * The cookie is managed through hooks: it is set after a login, removed at the logout, and the
 * session resolver continues the session of an SSO request that only carries this cookie.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SSOSessionCookie
{
    /** Suffix of the cookie name after the cookie prefix of the installation */
    public const COOKIE_SUFFIX = '_SSO_SESSION_ID';

    /** Registration ID of the hooks, so that registering them twice replaces them */
    private const HOOK_ID = 'sso_session_cookie';

    /**
     * Register the hooks of the SSO session cookie. This has to happen before the session starts.
     */
    public static function registerHooks(): void
    {
        Hooks::addResolver('session_id', array(self::class, 'resolveSessionId'), Hooks::DEFAULT_PRIORITY, 0, self::HOOK_ID);
        Hooks::addAction('login_succeeded', array(self::class, 'set'), Hooks::DEFAULT_PRIORITY, 0, self::HOOK_ID);
        Hooks::addAction('auto_login_succeeded', array(self::class, 'set'), Hooks::DEFAULT_PRIORITY, 0, self::HOOK_ID);
        Hooks::addAction('logout', array(self::class, 'remove'), Hooks::DEFAULT_PRIORITY, 0, self::HOOK_ID);
    }

    /**
     * Name the session of an SSO request that does not carry the session cookie, from the SSO
     * session cookie. Other requests are not answered.
     */
    public static function resolveSessionId(): ?string
    {
        if (!self::isSsoRequest()) {
            return null;
        }

        $sessionId = $_COOKIE[self::getName()] ?? null;
        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    /**
     * Set the cookie to the id of the current session, unless the request already carries it.
     */
    public static function set(): void
    {
        global $gSettingsManager;

        if (!isset($gSettingsManager)
            || ($gSettingsManager->get('sso_saml_enabled') !== '1' && $gSettingsManager->get('sso_oidc_enabled') !== '1')
        ) {
            return;
        }

        $sessionId = session_id();
        if ($sessionId === false || $sessionId === '' || ($_COOKIE[self::getName()] ?? '') === $sessionId) {
            return;
        }

        self::send($sessionId, 0);
    }

    /**
     * Remove the cookie from the browser.
     */
    public static function remove(): void
    {
        self::send('', 1);
    }

    /**
     * Name of the cookie, derived from the cookie prefix of the installation.
     */
    public static function getName(): string
    {
        return COOKIE_PREFIX . self::COOKIE_SUFFIX;
    }

    /**
     * Check whether the current request is addressed to the single sign-on module.
     */
    public static function isSsoRequest(): bool
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        return is_string($path) && str_starts_with($path, self::getPath());
    }

    /**
     * The cookie is only sent to the SSO module.
     */
    private static function getPath(): string
    {
        return ADMIDIO_URL_PATH . FOLDER_MODULES . '/sso/';
    }

    /**
     * Browsers only accept SameSite=None together with Secure, so the cookie is only sent over
     * HTTPS. A single sign-on installation has to be served over HTTPS anyway.
     */
    private static function send(string $value, int $expires): void
    {
        if (!HTTPS || headers_sent()) {
            return;
        }

        setcookie(self::getName(), $value, array(
            'expires' => $expires,
            'path' => self::getPath(),
            'domain' => DOMAIN === 'localhost' ? '' : DOMAIN,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None'
        ));
    }
}
