<?php
/**
 * Regression coverage for the SSO session cookie and the session hooks it uses.
 *
 * A SAML request with the HTTP-POST binding or an OIDC form post arrives as a cross-site post,
 * which does not carry Admidio's SameSite=Lax session cookie. Admidio asked a logged-in user to
 * log in again and replaced the session cookie. The SSO module now keeps a second, SameSite=None
 * cookie for its own path and names the session through the session_id resolver of
 * Session::start(); it is set and removed through the login and logout hooks.
 *
 * The cookie itself and Session::start() need a real HTTP request; they were tested against a
 * running installation. This test covers the decisions that do not.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Hooks\Hooks;
use Admidio\SSO\Service\SSOSessionCookie;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;

class SsoSessionCookieTest extends AdministratorTestCase
{
    private array $previousServer = array();
    private array $previousCookie = array();

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousServer = $_SERVER;
        $this->previousCookie = $_COOKIE;
        SSOSessionCookie::registerHooks();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->previousServer;
        $_COOKIE = $this->previousCookie;
        // only the registrations of this test are removed, other hooks of the run stay
        Hooks::removeResolver('session_id', 'sso_session_cookie');
        foreach (array('login_succeeded', 'auto_login_succeeded', 'logout') as $action) {
            Hooks::removeAction($action, 'sso_session_cookie');
        }
        parent::tearDown();
    }

    /**
     * @testdox The SSO module is registered on the session, login and logout hooks
     */
    public function testTheHooksAreRegistered(): void
    {
        $this->assertTrue(Hooks::hasResolver('session_id'));
        $this->assertTrue(Hooks::hasAction('login_succeeded'));
        $this->assertTrue(Hooks::hasAction('auto_login_succeeded'));
        $this->assertTrue(Hooks::hasAction('logout'));
    }

    /**
     * @testdox A request to the SSO module continues the session of the SSO session cookie
     */
    public function testAnSsoRequestIsResolvedFromTheSsoCookie(): void
    {
        $_SERVER['REQUEST_URI'] = ADMIDIO_URL_PATH . '/modules/sso/index.php/saml/sso?x=1';
        $_COOKIE[SSOSessionCookie::getName()] = 'abcdefghijklmnopqrstuvwxyz012345';

        $this->assertSame('abcdefghijklmnopqrstuvwxyz012345', Hooks::resolve('session_id', null, 'SESSION'));
    }

    /**
     * The cookie is only sent to the SSO module by the browser, but a request may carry it
     * anyway; outside the SSO module it must never name the session.
     *
     * @testdox A request outside the SSO module is not resolved from the SSO session cookie
     */
    public function testOtherRequestsAreNotResolved(): void
    {
        $_SERVER['REQUEST_URI'] = ADMIDIO_URL_PATH . '/modules/profile/profile.php';
        $_COOKIE[SSOSessionCookie::getName()] = 'abcdefghijklmnopqrstuvwxyz012345';

        $this->assertNull(Hooks::resolve('session_id', null, 'SESSION'));
    }

    /**
     * @testdox An SSO request without the SSO session cookie is not resolved
     */
    public function testAnSsoRequestWithoutCookieIsNotResolved(): void
    {
        $_SERVER['REQUEST_URI'] = ADMIDIO_URL_PATH . '/modules/sso/index.php/saml/sso';
        unset($_COOKIE[SSOSessionCookie::getName()]);

        $this->assertNull(Hooks::resolve('session_id', null, 'SESSION'));
    }
}
