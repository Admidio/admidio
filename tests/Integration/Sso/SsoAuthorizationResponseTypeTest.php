<?php
/**
 * Regression coverage for the response_type of an OIDC authorization request.
 *
 * Only the code flow is supported. A request for another response type, or for none at all,
 * used to end in a JSON error page "unsupported_grant_type" of the OP, although OpenID Connect
 * Core 1.0, section 3.1.2.6, requires the error to be sent to the redirect URI of the client
 * once the client and the redirect URI are verified. The OpenID conformance suite stops at that
 * error page.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Grants\OIDCAuthCodeGrant;
use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Repository\ClientRepository;
use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;

class SsoAuthorizationResponseTypeTest extends AdministratorTestCase
{
    use SsoClientFixture;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @testdox An unsupported response type is reported to the redirect URI of the client
     */
    public function testAnUnsupportedResponseTypeIsRedirectedToTheClient(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());

        $response = $this->authorize(array(
            'response_type' => 'code token',
            'client_id' => $client->getIdentifier(),
            'redirect_uri' => $client->getRedirectUri(),
            'scope' => 'openid',
            'state' => 'state-value'
        ));

        $this->assertRedirectedWithError($response, $client, 'unsupported_response_type');
    }

    /**
     * @testdox A missing response type is reported to the redirect URI of the client
     */
    public function testAMissingResponseTypeIsRedirectedToTheClient(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());

        $response = $this->authorize(array(
            'client_id' => $client->getIdentifier(),
            'redirect_uri' => $client->getRedirectUri(),
            'scope' => 'openid',
            'state' => 'state-value'
        ));

        $this->assertRedirectedWithError($response, $client, 'invalid_request');
    }

    /**
     * An unverified redirect URI must never receive a redirect, whatever else is wrong with the
     * request, or the OP becomes an open redirector.
     *
     * @testdox An unsupported response type to an unregistered redirect URI is not redirected
     */
    public function testAnUnregisteredRedirectUriIsNotRedirected(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());

        $response = $this->authorize(array(
            'response_type' => 'code token',
            'client_id' => $client->getIdentifier(),
            'redirect_uri' => 'https://attacker.example/callback',
            'scope' => 'openid',
            'state' => 'state-value'
        ));

        $this->assertFalse($response->hasHeader('Location'));
        $this->assertSame('invalid_client', json_decode((string) $response->getBody(), true)['error'] ?? null);
    }

    /**
     * @testdox An unsupported response type of an unknown client is not redirected
     */
    public function testAnUnknownClientIsNotRedirected(): void
    {
        $response = $this->authorize(array(
            'response_type' => 'code token',
            'client_id' => 'unknown-' . $this->suffix(),
            'redirect_uri' => 'https://client.example/callback',
            'scope' => 'openid'
        ));

        $this->assertFalse($response->hasHeader('Location'));
        $this->assertSame('invalid_client', json_decode((string) $response->getBody(), true)['error'] ?? null);
    }

    /**
     * Validate an authorization request with the grant and return the response the OP sends.
     *
     * @param array<string,string> $queryParams Query parameters of the authorization request.
     * @return ResponseInterface The error response.
     */
    private function authorize(array $queryParams): ResponseInterface
    {
        $grant = new OIDCAuthCodeGrant(
            new AuthCodeRepository($this->getDatabase()),
            new RefreshTokenRepository($this->getDatabase()),
            new \DateInterval('PT10M')
        );
        $grant->setClientRepository(new ClientRepository($this->getDatabase()));

        $request = (new ServerRequest(array(), array(), 'https://op.example/authorize', 'GET'))
            ->withQueryParams($queryParams);

        try {
            $grant->validateAuthorizationRequest($request);
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse(new Response());
        }

        $this->fail('The authorization request was accepted.');
    }

    private function assertRedirectedWithError(ResponseInterface $response, OIDCClient $client, string $error): void
    {
        $this->assertSame(302, $response->getStatusCode());

        $location = $response->getHeaderLine('Location');
        $this->assertStringStartsWith($client->getRedirectUri() . '?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $parameters);
        $this->assertSame($error, $parameters['error'] ?? null);
        $this->assertSame('state-value', $parameters['state'] ?? null);
    }
}
