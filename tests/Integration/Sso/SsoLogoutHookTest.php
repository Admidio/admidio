<?php
/**
 * Regression coverage for the logout of the single sign-on clients through hooks.
 *
 * system/logout.php used to create the SAML service itself to start the logout chain of the SSO
 * clients. The SSO module now remembers the session in the logout action and answers the
 * logout_response resolver with the chain, so the core logout no longer knows the module.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Hooks\Hooks;
use Admidio\SSO\Service\SSOHooks;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Psr\Http\Message\ResponseInterface;

class SsoLogoutHookTest extends AdministratorTestCase
{
    private const HOMEPAGE = 'https://admidio.test/modules/overview.php';

    private mixed $previousSession = null;
    private array $previousSettings = array();

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        global $gSettingsManager;

        parent::setUp();
        $this->previousSession = $GLOBALS['gCurrentSession'] ?? null;
        foreach (array('sso_saml_enabled', 'sso_oidc_enabled') as $setting) {
            $this->previousSettings[$setting] = (string) $gSettingsManager->get($setting);
        }

        // The session that is logged out, known to its clients by its external id
        $GLOBALS['gCurrentSession'] = new class {
            public function getExternalSessionId(): string
            {
                return 'external-session-of-the-logout';
            }
        };

        SSOHooks::register();
    }

    protected function tearDown(): void
    {
        global $gSettingsManager;

        foreach ($this->previousSettings as $setting => $value) {
            $gSettingsManager->set($setting, $value);
        }
        $GLOBALS['gCurrentSession'] = $this->previousSession;

        // only the registrations of the SSO module are removed, other hooks of the run stay
        foreach (array('membership_updated', 'membership_deleting', 'membership_deleted', 'logout') as $action) {
            Hooks::removeAction($action, 'sso');
        }
        Hooks::removeResolver('logout_response', 'sso');
        foreach (array('login_succeeded', 'auto_login_succeeded', 'logout') as $action) {
            Hooks::removeAction($action, 'sso_session_cookie');
        }
        Hooks::removeResolver('session_id', 'sso_session_cookie');
        parent::tearDown();
    }

    /**
     * @testdox Without single sign-on the logout is not taken over
     */
    public function testWithoutSingleSignOnTheLogoutIsNotTakenOver(): void
    {
        global $gSettingsManager;

        $gSettingsManager->set('sso_saml_enabled', '0');
        $gSettingsManager->set('sso_oidc_enabled', '0');

        Hooks::doAction('logout', $GLOBALS['gCurrentUser']);

        $this->assertNull(Hooks::resolve('logout_response', null, self::HOMEPAGE));
    }

    /**
     * Without any client in the session the chain has nothing to visit and ends right away at the
     * logout homepage.
     *
     * @testdox With single sign-on the logout ends with the logout chain of the clients
     */
    public function testWithSingleSignOnTheLogoutEndsWithTheChain(): void
    {
        global $gSettingsManager;

        $gSettingsManager->set('sso_saml_enabled', '1');

        Hooks::doAction('logout', $GLOBALS['gCurrentUser']);
        $response = Hooks::resolve('logout_response', null, self::HOMEPAGE);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(self::HOMEPAGE, $response->getHeaderLine('Location'));

        // The chain belongs to this one logout
        $this->assertNull(Hooks::resolve('logout_response', null, self::HOMEPAGE));
    }
}
