<?php
/**
 * Regression coverage for the authentication context that a SAML assertion states.
 *
 * The assertion used to claim `unspecified` no matter how the session had been authenticated,
 * although Admidio records the methods and the OIDC side already reports them as `acr`. A service
 * provider that reads the context has to be told the truth. See finding 35.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Session\Entity\Session;
use Admidio\SSO\Service\SAMLService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use LightSaml\SamlConstants;
use ReflectionMethod;

class SsoAuthnContextTest extends AdministratorTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @dataProvider authenticationMethodProvider
     * @testdox A session authenticated with "$methods" states the context $expected
     */
    public function testTheAssertionStatesTheAchievedContext(string $methods, string $expected): void
    {
        $this->assertSame($expected, $this->contextForAuthenticationMethods($methods));
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function authenticationMethodProvider(): array
    {
        return array(
            // The test environment describes an installation served over HTTPS, which is what an
            // installation offering single sign-on has to be, so a password login is protected by
            // the transport.
            'a password login' => array('pwd', SamlConstants::AUTHN_CONTEXT_PASSWORD_PROTECTED_TRANSPORT),
            'a password and a TOTP' => array('pwd otp', SAMLService::AUTHN_CONTEXT_TIME_SYNC_TOKEN),
            'an auto login' => array(Session::AUTHENTICATION_METHOD_AUTO_LOGIN, SAMLService::AUTHN_CONTEXT_PREVIOUS_SESSION),
            'nothing recorded' => array('', SamlConstants::AUTHN_CONTEXT_UNSPECIFIED),
            'only whitespace' => array('   ', SamlConstants::AUTHN_CONTEXT_UNSPECIFIED)
        );
    }

    /**
     * An auto login session that was later re-authenticated with a TOTP is no longer a resumed
     * session, but the auto login marker stays in the record. The stronger method has to win.
     *
     * @testdox A resumed session that is re-authenticated is not reported as a previous session
     */
    public function testTheAutoLoginMarkerWinsOverAPasswordOnly(): void
    {
        // The marker is what the OIDC side reports as urn:admidio:authentication:auto-login, so the
        // SAML side has to agree with it rather than invent its own order.
        $this->assertSame(
            SAMLService::AUTHN_CONTEXT_PREVIOUS_SESSION,
            $this->contextForAuthenticationMethods('pwd ' . Session::AUTHENTICATION_METHOD_AUTO_LOGIN)
        );
    }

    /**
     * The transport decides between the two password classes, and a service provider that asks for
     * PasswordProtectedTransport must not be told so by an installation served over plain HTTP.
     *
     * @testdox The password class follows the transport of the installation
     */
    public function testThePasswordClassFollowsTheTransport(): void
    {
        $this->assertSame(
            HTTPS ? SamlConstants::AUTHN_CONTEXT_PASSWORD_PROTECTED_TRANSPORT : SamlConstants::AUTHN_CONTEXT_PASSWORD,
            $this->contextForAuthenticationMethods('pwd')
        );
    }

    /**
     * The class references have to be the ones of the SAML authentication context specification,
     * because a service provider matches them literally.
     *
     * @testdox The context classes are the standard SAML URNs
     */
    public function testTheContextClassesAreTheStandardUrns(): void
    {
        $this->assertSame(
            'urn:oasis:names:tc:SAML:2.0:ac:classes:TimeSyncToken',
            SAMLService::AUTHN_CONTEXT_TIME_SYNC_TOKEN
        );
        $this->assertSame(
            'urn:oasis:names:tc:SAML:2.0:ac:classes:PreviousSession',
            SAMLService::AUTHN_CONTEXT_PREVIOUS_SESSION
        );
    }

    /**
     * Ask the production service which context it would state for a session that was authenticated
     * with the given methods.
     */
    private function contextForAuthenticationMethods(string $methods): string
    {
        global $gCurrentUser;

        $session = new Session($this->getDatabase(), COOKIE_PREFIX);
        $session->setValue('ses_authentication_methods', $methods);

        $previousSession = $GLOBALS['gCurrentSession'] ?? null;
        $GLOBALS['gCurrentSession'] = $session;

        try {
            $service = new SAMLService($this->getDatabase(), $gCurrentUser);
            $method = new ReflectionMethod($service, 'getAuthenticationContextClassRef');
            $method->setAccessible(true);

            return (string) $method->invoke($service);
        } finally {
            $GLOBALS['gCurrentSession'] = $previousSession;
        }
    }
}
