<?php
/**
 * Regression coverage for the ID token that a refresh returns.
 *
 * An ID token from a refresh has to state the authentication of the original one (OpenID Connect
 * Core, section 12.2): auth_time, sid, acr and amr were only set while a code was exchanged, so a
 * refreshed token had none of them, and the sid that ties a session to a logout was missing. The
 * values come from the authorization code the refresh token stems from. See OIDC-16.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Session\Entity\Session;
use Admidio\SSO\Entity\IdTokenResponse;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Repository\UserRepository;
use Admidio\SSO\Service\OIDCService;
use Laminas\Diactoros\Response;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\ResponseTypes\AbstractResponseType;
use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\Entities\ClaimSetEntity;
use ReflectionProperty;

class SsoRefreshIdTokenTest extends SsoTokenGrantTestCase
{
    private const AUTH_TIME = 1700000000;
    private const ACR = 'urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport';

    private ?string $previousHost = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The ID token library reads the host from the request
        $this->previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'op.example';
    }

    protected function tearDown(): void
    {
        if ($this->previousHost === null) {
            unset($_SERVER['HTTP_HOST']);
        } else {
            $_SERVER['HTTP_HOST'] = $this->previousHost;
        }

        parent::tearDown();
    }

    private function newIdTokenResponse(): IdTokenResponse
    {
        $responseType = new IdTokenResponse(
            new UserRepository($this->getDatabase()),
            new ClaimExtractor(array(new ClaimSetEntity('groups', array('groups')))),
            'https://op.example/oidc',
            $this->getDatabase(),
            3600,
            'test-key'
        );
        $responseType->setPrivateKey(new CryptKey($this->privateKeyPem, null, false));
        $responseType->setEncryptionKey(self::ENCRYPTION_KEY);

        return $responseType;
    }

    /**
     * Refresh the tokens and return the claims of the ID token that comes with them.
     *
     * @return array<string,mixed>
     */
    private function refreshedClaims(OIDCClient $client, string $refreshTokenId, ?IdTokenResponse &$responseType = null): array
    {
        $responseType = $this->newIdTokenResponse();
        $this->refresh($client, $refreshTokenId, $responseType, array('openid'));

        return $this->claimsOf($responseType);
    }

    /**
     * @return array<string,mixed>
     */
    private function claimsOf(IdTokenResponse $responseType): array
    {
        OIDCService::setClient($this->currentClient);
        $body = json_decode((string) $responseType->generateHttpResponse(new Response())->getBody(), true);
        $this->assertArrayHasKey('id_token', $body, 'The refresh must return an ID token for the openid scope.');

        return json_decode(base64_decode(strtr(explode('.', $body['id_token'])[1], '-_', '+/')), true);
    }

    private ?OIDCClient $currentClient = null;

    private function authenticatedExchange(OIDCClient $client, string $externalSessionId, ?string $nonce = 'the-nonce'): array
    {
        $this->currentClient = $client;
        $this->codeGrant->setAuthenticationTime(self::AUTH_TIME);
        $this->codeGrant->setExternalSessionId($externalSessionId);
        $this->codeGrant->setAuthenticationMethods(array('pwd', 'otp'));
        $this->codeGrant->setAuthenticationContext(self::ACR);
        (new ReflectionProperty($this->codeGrant, 'nonce'))->setValue($this->codeGrant, $nonce);

        return $this->exchangeCode($client);
    }

    /**
     * @testdox A refreshed ID token states the authentication of the original one
     */
    public function testARefreshedIdTokenStatesTheOriginalAuthentication(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        [, , $refreshTokenId] = $this->authenticatedExchange($client, 'session-' . $this->suffix());
        $sid = (string) $this->getDatabase()->queryPrepared('SELECT oac_external_session_id FROM ' . TBL_OIDC_AUTH_CODES . ' ORDER BY oac_id DESC LIMIT 1')->fetchColumn();

        $claims = $this->refreshedClaims($client, $refreshTokenId);

        $this->assertSame(self::AUTH_TIME, $claims['auth_time']);
        $this->assertSame($sid, $claims['sid']);
        $this->assertSame(array('pwd', 'otp'), $claims['amr']);
        $this->assertSame(self::ACR, $claims['acr']);
    }

    /**
     * @testdox The token response states the scope, and the ID token the hash of the access token
     */
    public function testTheResponseHasAScopeAndTheIdTokenAnAtHash(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        [, , $refreshTokenId] = $this->authenticatedExchange($client, 'session-' . $this->suffix());

        $responseType = $this->newIdTokenResponse();
        $this->refresh($client, $refreshTokenId, $responseType, array('openid'));
        OIDCService::setClient($this->currentClient);
        $body = json_decode((string) $responseType->generateHttpResponse(new Response())->getBody(), true);

        $this->assertSame('openid', $body['scope']);

        $claims = json_decode(base64_decode(strtr(explode('.', $body['id_token'])[1], '-_', '+/')), true);
        $expected = rtrim(strtr(base64_encode(substr(hash('sha256', $body['access_token'], true), 0, 16)), '+/', '-_'), '=');
        $this->assertSame($expected, $claims['at_hash']);
    }

    /**
     * @testdox A refreshed ID token has no nonce
     */
    public function testARefreshedIdTokenHasNoNonce(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        [, , $refreshTokenId] = $this->authenticatedExchange($client, 'session-' . $this->suffix(), 'nonce-of-the-authorization-request');

        $this->assertArrayNotHasKey('nonce', $this->refreshedClaims($client, $refreshTokenId));
    }

    /**
     * @testdox The authentication survives a second refresh
     */
    public function testTheAuthenticationSurvivesASecondRefresh(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        [, , $refreshTokenId] = $this->authenticatedExchange($client, 'session-' . $this->suffix());

        $first = $this->newIdTokenResponse();
        $this->refresh($client, $refreshTokenId, $first, array('openid'));
        $secondRefreshTokenId = (new ReflectionProperty(AbstractResponseType::class, 'refreshToken'))
            ->getValue($first)->getIdentifier();

        $claims = $this->refreshedClaims($client, $secondRefreshTokenId);
        $this->assertSame(self::AUTH_TIME, $claims['auth_time']);
        $this->assertArrayHasKey('sid', $claims);
    }

    /**
     * @testdox A refresh token without an authorization code still gets an ID token, without the authentication
     */
    public function testARefreshTokenWithoutACodeGetsNoAuthenticationClaims(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        [, , $refreshTokenId] = $this->authenticatedExchange($client, 'session-' . $this->suffix());
        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_OIDC_REFRESH_TOKENS . ' SET ort_oac_id = NULL WHERE ort_token = ?',
            array(hash('sha256', $refreshTokenId))
        );

        $claims = $this->refreshedClaims($client, $refreshTokenId);

        $this->assertArrayHasKey('sub', $claims);
        foreach (array('auth_time', 'sid', 'amr', 'acr') as $claim) {
            $this->assertArrayNotHasKey($claim, $claims);
        }
    }

    private function loggedInSession(): Session
    {
        global $gCurrentUser;

        $session = new Session($this->getDatabase(), COOKIE_PREFIX);
        $session->setAuthentication((int) $gCurrentUser->getValue('usr_id'), array('pwd'));
        $session->save();

        return $session;
    }

    private function participantCount(OIDCClient $client, string $externalSessionId): int
    {
        return (int) $this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_OIDC_SESSION_PARTICIPANTS . ' WHERE osp_external_session_id = ? AND osp_client_id = ?',
            array($externalSessionId, (int) $client->getValue('ocl_id'))
        )->fetchColumn();
    }

    /**
     * @testdox A refresh keeps the logout participant of a session that is still logged in
     */
    public function testARefreshPersistsTheParticipantOfALiveSession(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        $session = $this->loggedInSession();
        $externalSessionId = $session->getExternalSessionId();
        [, , $refreshTokenId] = $this->authenticatedExchange($client, $externalSessionId);
        $this->assertSame(0, $this->participantCount($client, $externalSessionId));

        $this->refreshedClaims($client, $refreshTokenId);

        $this->assertSame(1, $this->participantCount($client, $externalSessionId));
    }

    /**
     * @testdox A refresh does not bring back the participant of a session that was logged out
     */
    public function testARefreshDoesNotResurrectALoggedOutSession(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        $session = $this->loggedInSession();
        $externalSessionId = $session->getExternalSessionId();
        [, , $refreshTokenId] = $this->authenticatedExchange($client, $externalSessionId);

        $session->setValue('ses_usr_id', '');
        $session->save();

        $claims = $this->refreshedClaims($client, $refreshTokenId);

        $this->assertSame($externalSessionId, $claims['sid']);
        $this->assertSame(0, $this->participantCount($client, $externalSessionId));
    }
}
