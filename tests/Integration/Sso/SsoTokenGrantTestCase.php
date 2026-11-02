<?php
/**
 * Shared setup for the tests of the OIDC grants: a code grant and a refresh token grant on the real
 * repositories, and helpers to issue codes, exchange them and refresh the tokens the way the token
 * endpoint does.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Entity\AuthCodeEntity;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Grants\OIDCAuthCodeGrant;
use Admidio\SSO\Grants\OIDCRefreshTokenGrant;
use Admidio\SSO\Repository\AccessTokenRepository;
use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Repository\ClientRepository;
use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\SSO\Repository\ScopeRepository;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Laminas\Diactoros\ServerRequest;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use ReflectionMethod;

abstract class SsoTokenGrantTestCase extends AdministratorTestCase
{
    use SsoClientFixture;

    protected string $privateKeyPem = '';

    protected const ENCRYPTION_KEY = 'a-key-for-the-token-rotation-test';

    protected OIDCAuthCodeGrant $codeGrant;
    protected OIDCRefreshTokenGrant $refreshGrant;
    protected AuthCodeRepository $authCodeRepository;
    protected AccessTokenRepository $accessTokenRepository;
    protected RefreshTokenRepository $refreshTokenRepository;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $db = $this->getDatabase();
        $this->authCodeRepository = new AuthCodeRepository($db);
        $this->accessTokenRepository = new AccessTokenRepository($db);
        $this->refreshTokenRepository = new RefreshTokenRepository($db);

        // An access token is a signed JWT, so the grants need a key, though none is verified here
        openssl_pkey_export(openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA)), $privateKey);
        $this->privateKeyPem = $privateKey;

        $this->codeGrant = new OIDCAuthCodeGrant($this->authCodeRepository, $this->refreshTokenRepository, new \DateInterval('PT10M'));
        $this->refreshGrant = new OIDCRefreshTokenGrant($this->refreshTokenRepository);
        foreach (array($this->codeGrant, $this->refreshGrant) as $grant) {
            $grant->setClientRepository(new ClientRepository($db));
            $grant->setScopeRepository(new ScopeRepository($db));
            $grant->setAccessTokenRepository($this->accessTokenRepository);
            $grant->setRefreshTokenTTL(new \DateInterval('P1D'));
            $grant->setPrivateKey(new CryptKey($privateKey, null, false));
            $grant->setEncryptionKey(self::ENCRYPTION_KEY);
        }
    }

    protected function issueAuthCode(OIDCClient $client): string
    {
        global $gCurrentUser;

        $authCode = (new ReflectionMethod($this->codeGrant, 'issueAuthCode'))->invoke($this->codeGrant,
            new \DateInterval('PT10M'), $client, (string) $gCurrentUser->getValue('usr_uuid'), $client->getRedirectUri(), array());

        return $authCode->getIdentifier();
    }

    /**
     * Issue a code and the tokens of its exchange, linked to it, the way the grant does.
     *
     * @return array{0:string,1:string,2:string} Identifiers of the code, access token and refresh token.
     */
    protected function exchangeCode(OIDCClient $client): array
    {
        global $gCurrentUser;

        $codeId = $this->issueAuthCode($client);

        $accessToken = (new ReflectionMethod($this->codeGrant, 'issueAccessToken'))->invoke($this->codeGrant,
            new \DateInterval('PT1H'), $client, (string) $gCurrentUser->getValue('usr_uuid'), array());
        $refreshToken = (new ReflectionMethod($this->codeGrant, 'issueRefreshToken'))->invoke($this->codeGrant, $accessToken);

        $authCode = $this->authCodeRepository->getToken($codeId);
        $this->assertInstanceOf(AuthCodeEntity::class, $authCode);
        (new ReflectionMethod($this->codeGrant, 'linkIssuedTokens'))->invoke($this->codeGrant, $authCode);

        return array($codeId, $accessToken->getIdentifier(), $refreshToken->getIdentifier());
    }

    /**
     * Exchange a refresh token at the refresh token grant, with the client authenticated as at the
     * token endpoint.
     *
     * @return BearerTokenResponse The response type the grant filled.
     */
    protected function refresh(OIDCClient $client, string $refreshTokenId, ?BearerTokenResponse $responseType = null, array $scopes = array()): BearerTokenResponse
    {
        global $gCurrentUser;

        $payload = json_encode(array(
            'client_id' => $client->getIdentifier(),
            'refresh_token_id' => $refreshTokenId,
            'access_token_id' => 'unused',
            'scopes' => $scopes,
            'user_id' => (string) $gCurrentUser->getValue('usr_uuid'),
            'expire_time' => time() + 3600
        ));

        $request = (new ServerRequest(array(), array(), 'https://op.example/token', 'POST'))->withParsedBody(array(
            'grant_type' => 'refresh_token',
            'client_id' => $client->getIdentifier(),
            'client_secret' => $this->secretOf($client),
            'refresh_token' => (new ReflectionMethod($this->refreshGrant, 'encrypt'))->invoke($this->refreshGrant, $payload)
        ));

        return $this->refreshGrant->respondToAccessTokenRequest($request, $responseType ?? $this->newResponseType(), new \DateInterval('PT1H'));
    }

    /**
     * Present an authorization code at the authorization code grant.
     */
    protected function presentCode(OIDCClient $client, string $codeId, int $expireTime): void
    {
        global $gCurrentUser;

        $payload = json_encode(array(
            'client_id' => $client->getIdentifier(),
            'redirect_uri' => $client->getRedirectUri(),
            'auth_code_id' => $codeId,
            'scopes' => array(),
            'user_id' => (string) $gCurrentUser->getValue('usr_uuid'),
            'expire_time' => $expireTime
        ));

        $request = (new ServerRequest(array(), array(), 'https://op.example/token', 'POST'))->withParsedBody(array(
            'grant_type' => 'authorization_code',
            'client_id' => $client->getIdentifier(),
            'client_secret' => $this->secretOf($client),
            'redirect_uri' => $client->getRedirectUri(),
            'code' => (new ReflectionMethod($this->codeGrant, 'encrypt'))->invoke($this->codeGrant, $payload)
        ));

        $this->codeGrant->respondToAccessTokenRequest($request, $this->newResponseType(), new \DateInterval('PT1H'));
    }

    /**
     * A response type that tells which tokens it was given.
     */
    protected function newResponseType(): BearerTokenResponse
    {
        $responseType = new class extends BearerTokenResponse {
            /** @return array{0:string,1:string} */
            public function tokenIdentifiers(): array
            {
                return array($this->accessToken->getIdentifier(), $this->refreshToken->getIdentifier());
            }
        };

        return $responseType;
    }

    protected function secretOf(OIDCClient $client): string
    {
        // The fixture creates the secret from the unique suffix of the client ID
        return 'secret-' . substr($client->getIdentifier(), strlen('regression-'));
    }

    protected function codeRowId(string $codeId): int
    {
        return (int) $this->authCodeRepository->getToken($codeId)->getValue('oac_id');
    }

    protected function linkOf(string $table, string $prefix, string $tokenId): ?int
    {
        $link = $this->getDatabase()->queryPrepared(
            'SELECT ' . $prefix . '_oac_id FROM ' . $table . ' WHERE ' . $prefix . '_token = ?',
            array(hash('sha256', $tokenId))
        )->fetchColumn();

        return $link === null || $link === false ? null : (int) $link;
    }

    protected function expireCode(string $codeId): void
    {
        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_OIDC_AUTH_CODES . ' SET oac_expires_at = ? WHERE oac_token = ?',
            array(date('Y-m-d H:i:s', time() - 600), hash('sha256', $codeId))
        );
    }
}
