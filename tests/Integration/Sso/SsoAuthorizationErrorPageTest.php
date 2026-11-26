<?php
/**
 * Regression coverage for the authorization errors that cannot be sent back to the client.
 *
 * Without a verified redirect URI the OP must not redirect, but inform the user (OpenID Connect
 * Core 1.0, section 3.1.2.6). The authorization endpoint answered with a JSON body instead, and
 * league reports an unregistered redirect URI as "Client authentication failed", which tells
 * neither the user nor the administrator what is wrong (oidcc-ensure-registered-redirect-uri).
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Grants\OIDCAuthCodeGrant;
use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Repository\ClientRepository;
use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\SSO\Service\OIDCService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\UI\Component\Message;
use Laminas\Diactoros\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use ReflectionMethod;
use RuntimeException;

class SsoAuthorizationErrorPageTest extends AdministratorTestCase
{
    use SsoClientFixture;

    private mixed $previousMessage = null;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousMessage = $GLOBALS['gMessage'] ?? null;
        OIDCService::$client = null;

        // The page exits the script, so the test double hands its content over instead
        $GLOBALS['gMessage'] = new class extends Message {
            public function show(string $content, string $headline = ''): void
            {
                throw new RuntimeException($content);
            }
        };
    }

    protected function tearDown(): void
    {
        $GLOBALS['gMessage'] = $this->previousMessage;
        OIDCService::$client = null;
        parent::tearDown();
    }

    /**
     * @testdox An unregistered redirect URI is reported as such, not as a failed client authentication
     */
    public function testAnUnregisteredRedirectUriHasItsOwnError(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());

        $exception = $this->authorizationError(array(
            'response_type' => 'code',
            'client_id' => $client->getIdentifier(),
            'redirect_uri' => $client->getRedirectUri() . '/other',
            'scope' => 'openid'
        ));

        $this->assertSame(OIDCAuthCodeGrant::ERROR_REDIRECT_URI_NOT_REGISTERED, $exception->getCode());
        $this->assertFalse($exception->hasRedirect());
    }

    /**
     * @testdox The page for an unregistered redirect URI names the client and the redirect URI
     */
    public function testThePageNamesClientAndRedirectUri(): void
    {
        global $gL10n;

        $client = $this->createRestrictedClient($this->suffix(), array());
        $query = array(
            'response_type' => 'code',
            'client_id' => $client->getIdentifier(),
            'redirect_uri' => $client->getRedirectUri() . '/other?a=1&b=<2>',
            'scope' => 'openid'
        );

        $this->assertSame(
            $gL10n->get('SYS_SSO_OIDC_REDIRECT_URI_NOT_REGISTERED', array(
                $client->readableName(),
                $client->getRedirectUri() . '/other?a=1&amp;b=&lt;2&gt;'
            )),
            $this->errorPage($this->authorizationError($query), $query)
        );
    }

    /**
     * @testdox The page for an unknown client names the client ID
     */
    public function testThePageNamesAnUnknownClient(): void
    {
        global $gL10n;

        $query = array(
            'response_type' => 'code',
            'client_id' => 'unknown-' . $this->suffix(),
            'redirect_uri' => 'https://client.example/callback',
            'scope' => 'openid'
        );

        $this->assertSame(
            $gL10n->get('SYS_SSO_OIDC_CLIENT_UNKNOWN', array($query['client_id'])),
            $this->errorPage($this->authorizationError($query), $query)
        );
    }

    /**
     * @testdox Any other error is shown with its description
     */
    public function testOtherErrorsShowTheirDescription(): void
    {
        global $gL10n;

        $query = array('response_type' => 'code', 'scope' => 'openid');
        $exception = OAuthServerException::invalidRequest('client_id');

        $this->assertSame(
            $gL10n->get('SYS_SSO_OIDC_AUTHORIZATION_FAILED', array($exception->getMessage() . ' ' . $exception->getHint())),
            $this->errorPage($exception, $query)
        );
    }

    /**
     * Validate an authorization request with the grant and return the error it raises.
     *
     * @param array<string,string> $queryParams Query parameters of the authorization request.
     */
    private function authorizationError(array $queryParams): OAuthServerException
    {
        $grant = new OIDCAuthCodeGrant(
            new AuthCodeRepository($this->getDatabase()),
            new RefreshTokenRepository($this->getDatabase()),
            new \DateInterval('PT10M')
        );
        $grant->setClientRepository(new ClientRepository($this->getDatabase()));

        try {
            $grant->validateAuthorizationRequest($this->request($queryParams));
        } catch (OAuthServerException $exception) {
            return $exception;
        }

        $this->fail('The authorization request was accepted.');
    }

    /**
     * Return the content of the page the authorization endpoint shows for an error.
     *
     * @param array<string,string> $queryParams Query parameters of the authorization request.
     */
    private function errorPage(OAuthServerException $exception, array $queryParams): string
    {
        global $gCurrentUser;

        $service = new OIDCService($this->getDatabase(), $gCurrentUser);
        try {
            (new ReflectionMethod($service, 'showAuthorizationError'))->invoke($service, $exception, $this->request($queryParams));
        } catch (RuntimeException $page) {
            return $page->getMessage();
        }

        $this->fail('No error page was shown.');
    }

    private function request(array $queryParams): ServerRequest
    {
        return (new ServerRequest(array(), array(), 'https://op.example/authorize', 'GET'))
            ->withQueryParams($queryParams);
    }
}
