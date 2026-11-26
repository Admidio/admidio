<?php
/**
 * Regression coverage for the answers of the userinfo endpoint to unusable access tokens.
 *
 * Expired, revoked or otherwise unusable tokens were answered with 403 access_denied and a
 * missing or invalid token with 401 access_denied, both without the Bearer challenge that
 * RFC 6750, section 3.1, requires: 401 with WWW-Authenticate: Bearer error="invalid_token",
 * and no error code at all for a request without a token.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Grants\OIDCAuthCodeGrant;
use Admidio\SSO\Repository\AccessTokenRepository;
use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\SSO\Repository\ScopeRepository;
use Admidio\SSO\Service\KeyService;
use Admidio\SSO\Service\OIDCService;
use Admidio\SSO\Entity\Key;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use League\OAuth2\Server\CryptKey;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;

class SsoUserInfoResponseTest extends AdministratorTestCase
{
    use SsoClientFixture;

    private array $previousServer = array();
    private string $previousSigningKey = '';
    private AccessTokenRepository $accessTokenRepository;
    private Key $signingKey;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        global $gSettingsManager;

        parent::setUp();
        $this->previousServer = $_SERVER;
        $this->previousSigningKey = (string) $gSettingsManager->get('sso_oidc_signing_key');

        // The endpoint verifies the token with the signing key of the installation
        $keyService = new KeyService($this->getDatabase());
        $keyId = $keyService->createDefaultKey();
        $gSettingsManager->set('sso_oidc_signing_key', $keyId);
        $this->signingKey = $keyService->getUsableKey($keyId, KeyService::USAGE_OIDC_SIGNING);

        $this->accessTokenRepository = new AccessTokenRepository($this->getDatabase());
    }

    protected function tearDown(): void
    {
        global $gSettingsManager;

        $_SERVER = $this->previousServer;
        $gSettingsManager->set('sso_oidc_signing_key', $this->previousSigningKey);
        parent::tearDown();
    }

    /**
     * @testdox A valid access token gets the claims of its user
     */
    public function testAValidTokenGetsTheClaims(): void
    {
        global $gCurrentUser;

        [$jwt] = $this->issueAccessToken($this->createRestrictedClient($this->suffix(), array()));
        $response = $this->userInfo('Bearer ' . $jwt);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame((string) $gCurrentUser->getValue('usr_uuid'), json_decode((string) $response->getBody(), true)['sub'] ?? null);
    }

    /**
     * @testdox A revoked access token is answered with 401 invalid_token and a Bearer challenge
     */
    public function testARevokedTokenIsInvalid(): void
    {
        [$jwt, $tokenId] = $this->issueAccessToken($this->createRestrictedClient($this->suffix(), array()));
        $this->accessTokenRepository->revokeAccessToken($tokenId);

        $this->assertInvalidToken($this->userInfo('Bearer ' . $jwt));
    }

    /**
     * @testdox A malformed access token is answered with 401 invalid_token and a Bearer challenge
     */
    public function testAMalformedTokenIsInvalid(): void
    {
        $this->assertInvalidToken($this->userInfo('Bearer not-a-token'));
    }

    /**
     * @testdox A request without access token gets the Bearer challenge without an error code
     */
    public function testARequestWithoutTokenGetsTheChallenge(): void
    {
        $response = $this->userInfo('');

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        $this->assertArrayNotHasKey('error', json_decode((string) $response->getBody(), true));
    }

    private function assertInvalidToken(ResponseInterface $response): void
    {
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringStartsWith('Bearer error="invalid_token"', $response->getHeaderLine('WWW-Authenticate'));
        $this->assertSame('invalid_token', json_decode((string) $response->getBody(), true)['error'] ?? null);
    }

    /**
     * Issue an access token of the current user for a client, signed like the token endpoint does.
     *
     * @return array{0:string,1:string} The JWT and the identifier of the token.
     */
    private function issueAccessToken(OIDCClient $client): array
    {
        global $gCurrentUser;

        $grant = new OIDCAuthCodeGrant(new AuthCodeRepository($this->getDatabase()),
            new RefreshTokenRepository($this->getDatabase()), new \DateInterval('PT10M'));
        $grant->setAccessTokenRepository($this->accessTokenRepository);
        $privateKey = new CryptKey((string) $this->signingKey->getValue('key_private'), null, false);
        $grant->setPrivateKey($privateKey);

        $scopes = array((new ScopeRepository($this->getDatabase()))->getScopeEntityByIdentifier('openid'));
        $accessToken = (new ReflectionMethod($grant, 'issueAccessToken'))->invoke($grant, new \DateInterval('PT1H'),
            $client, (string) $gCurrentUser->getValue('usr_uuid'), $scopes);
        $accessToken->setPrivateKey($privateKey);

        return array($accessToken->toString(), $accessToken->getIdentifier());
    }

    /**
     * Call the userinfo endpoint with the given Authorization header, none if empty.
     */
    private function userInfo(string $authorization): ResponseInterface
    {
        global $gCurrentUser;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['QUERY_STRING'] = '';
        unset($_SERVER['HTTP_AUTHORIZATION']);
        if ($authorization !== '') {
            $_SERVER['HTTP_AUTHORIZATION'] = $authorization;
        }

        return (new OIDCService($this->getDatabase(), $gCurrentUser))->handleUserInfoRequest();
    }
}
