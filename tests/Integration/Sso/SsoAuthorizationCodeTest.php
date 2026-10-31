<?php
/**
 * Regression coverage for the data an OIDC authorization code carries to the ID token, and for
 * the tokens it produces.
 *
 * The time of the authentication was handed to the timestamp column as a Unix time, which the
 * entity cannot parse and stored as NULL. Every ID token was then issued without auth_time,
 * which OIDC requires when the client sent max_age. The conformance tests oidcc-max-age-1 and
 * oidcc-max-age-10000 failed on it.
 *
 * A code that is presented a second time may have been intercepted, so the tokens of its first
 * exchange have to be revoked (RFC 6749, section 4.1.2). They stayed valid, because nothing
 * linked them to the code (oidcc-codereuse-30seconds).
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Entity\AuthCodeEntity;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Grants\OIDCAuthCodeGrant;
use Admidio\SSO\Repository\AccessTokenRepository;
use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use League\OAuth2\Server\CryptKey;
use ReflectionMethod;

class SsoAuthorizationCodeTest extends AdministratorTestCase
{
    use SsoClientFixture;

    private OIDCAuthCodeGrant $grant;
    private AuthCodeRepository $authCodeRepository;
    private AccessTokenRepository $accessTokenRepository;
    private RefreshTokenRepository $refreshTokenRepository;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authCodeRepository = new AuthCodeRepository($this->getDatabase());
        $this->accessTokenRepository = new AccessTokenRepository($this->getDatabase());
        $this->refreshTokenRepository = new RefreshTokenRepository($this->getDatabase());

        $this->grant = new OIDCAuthCodeGrant($this->authCodeRepository, $this->refreshTokenRepository, new \DateInterval('PT10M'));
        $this->grant->setAccessTokenRepository($this->accessTokenRepository);
        $this->grant->setRefreshTokenTTL(new \DateInterval('P1D'));

        // An access token is a signed JWT, so the grant needs a key, though none is verified here
        openssl_pkey_export(openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA)), $privateKey);
        $this->grant->setPrivateKey(new CryptKey($privateKey, null, false));
    }

    /**
     * @testdox The authorization code keeps the time of the authentication
     */
    public function testTheAuthorizationCodeKeepsTheAuthenticationTime(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());

        $authenticationTime = time() - 3600;
        $this->grant->setAuthenticationTime($authenticationTime);

        $storedCode = $this->authCodeRepository->getToken($this->issueAuthCode($client));
        $this->assertFalse($storedCode->isNewRecord());
        $this->assertSame($authenticationTime, (int) $storedCode->getValue('oac_auth_time', 'U'));
    }

    /**
     * @testdox A reused authorization code revokes the tokens of its first exchange
     */
    public function testAReusedCodeRevokesItsTokens(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());

        $codeId = $this->issueAuthCode($client);
        [$accessTokenId, $refreshTokenId] = $this->exchange($client, $codeId);
        $otherCodeId = $this->issueAuthCode($client);
        [$otherAccessTokenId, $otherRefreshTokenId] = $this->exchange($client, $otherCodeId);

        // The exchange revokes the code, so a second exchange finds it revoked
        $this->authCodeRepository->revokeAuthCode($codeId);
        $this->assertTrue($this->authCodeRepository->isAuthCodeRevoked($codeId));

        $this->assertTrue($this->accessTokenRepository->isAccessTokenRevoked($accessTokenId));
        $this->assertTrue($this->refreshTokenRepository->isRefreshTokenRevoked($refreshTokenId));

        // The tokens of another code are not touched
        $this->assertFalse($this->accessTokenRepository->isAccessTokenRevoked($otherAccessTokenId));
        $this->assertFalse($this->refreshTokenRepository->isRefreshTokenRevoked($otherRefreshTokenId));
    }

    /**
     * @testdox The first exchange of an authorization code leaves its tokens valid
     */
    public function testAnUnusedCodeLeavesItsTokensValid(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());

        $codeId = $this->issueAuthCode($client);
        [$accessTokenId, $refreshTokenId] = $this->exchange($client, $codeId);

        $this->assertFalse($this->authCodeRepository->isAuthCodeRevoked($codeId));
        $this->assertFalse($this->accessTokenRepository->isAccessTokenRevoked($accessTokenId));
        $this->assertFalse($this->refreshTokenRepository->isRefreshTokenRevoked($refreshTokenId));
    }

    /**
     * Issue an authorization code for the current user, the way the authorization endpoint does.
     *
     * @return string Identifier of the code.
     */
    private function issueAuthCode(OIDCClient $client): string
    {
        global $gCurrentUser;

        // The fixture client identifies its users by usr_uuid
        $issueAuthCode = new ReflectionMethod($this->grant, 'issueAuthCode');
        $authCode = $issueAuthCode->invoke($this->grant, new \DateInterval('PT10M'), $client,
            (string) $gCurrentUser->getValue('usr_uuid'), $client->getRedirectUri(), array());

        return $authCode->getIdentifier();
    }

    /**
     * Issue the tokens of a code exchange and link them to the code, the way
     * respondToAccessTokenRequest() does after the code is validated.
     *
     * @return array{0:string,1:string} Identifiers of the access token and the refresh token.
     */
    private function exchange(OIDCClient $client, string $codeId): array
    {
        global $gCurrentUser;

        $issueAccessToken = new ReflectionMethod($this->grant, 'issueAccessToken');
        $accessToken = $issueAccessToken->invoke($this->grant, new \DateInterval('PT1H'), $client,
            (string) $gCurrentUser->getValue('usr_uuid'), array());

        $issueRefreshToken = new ReflectionMethod($this->grant, 'issueRefreshToken');
        $refreshToken = $issueRefreshToken->invoke($this->grant, $accessToken);
        $this->assertNotNull($refreshToken);

        $authCode = $this->authCodeRepository->getToken($codeId);
        $this->assertInstanceOf(AuthCodeEntity::class, $authCode);
        (new ReflectionMethod($this->grant, 'linkIssuedTokens'))->invoke($this->grant, $authCode);

        return array($accessToken->getIdentifier(), $refreshToken->getIdentifier());
    }
}
