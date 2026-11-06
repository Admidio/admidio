<?php
/**
 * Regression coverage for the introspection of a refresh token (RFC 7662).
 *
 * A refresh token was answered "inactive" whatever its state, and the answer for an access token
 * lacked iss, iat, aud, token_type and username.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\SSO\Service\OIDCService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use ReflectionMethod;
use ReflectionProperty;

class SsoRefreshTokenIntrospectionTest extends AdministratorTestCase
{
    use SsoClientFixture;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    private function service(): OIDCService
    {
        global $gCurrentUser;

        $service = new OIDCService($this->getDatabase(), $gCurrentUser);
        (new ReflectionProperty($service, 'refreshTokenRepository'))->setValue($service, new RefreshTokenRepository($this->getDatabase()));
        $service->setEncryptionKey('a-key-for-the-introspection-test');

        return $service;
    }

    /**
     * @return string The refresh token as the client holds it
     */
    private function clientToken(OIDCService $service, string $plainId, string $clientId): string
    {
        $payload = json_encode(array('client_id' => $clientId, 'refresh_token_id' => $plainId, 'access_token_id' => 'unused'));

        return (new ReflectionMethod($service, 'encrypt'))->invoke($service, $payload);
    }

    private function introspect(OIDCService $service, string $token, string $clientId): ?array
    {
        $response = (new ReflectionMethod($service, 'introspectRefreshToken'))->invoke($service, $token, $clientId);

        return $response === null ? null : json_decode((string) $response->getBody(), true);
    }

    /**
     * @testdox An active refresh token is described with issuer, audience, subject, times and scope
     */
    public function testAnActiveRefreshTokenIsDescribed(): void
    {
        $fixture = $this->getFixture();
        $suffix = $this->suffix();
        $user = $fixture->createAndSaveUser('sso-intro-' . $suffix, 'x@example.local');
        $role = $fixture->createAndSaveRole('SSO intro ' . $suffix, $GLOBALS['gCurrentOrgId']);
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
        $client = $this->createRestrictedClient($suffix, array());
        $plain = 'refresh-' . $suffix;
        $this->createToken($client, $user['usr_id'], TBL_OIDC_REFRESH_TOKENS, 'ort', $plain);

        $service = $this->service();
        $answer = $this->introspect($service, $this->clientToken($service, $plain, $client->getIdentifier()), $client->getIdentifier());

        $this->assertTrue($answer['active']);
        $this->assertSame($client->getIdentifier(), $answer['client_id']);
        $this->assertSame($client->getIdentifier(), $answer['aud']);
        $this->assertSame($service->getIssuerURL(), $answer['iss']);
        $this->assertSame('sso-intro-' . $suffix, $answer['username']);
        $this->assertSame('openid profile', $answer['scope']);
        $this->assertArrayHasKey('exp', $answer);
        $this->assertArrayHasKey('iat', $answer);
        $this->assertArrayNotHasKey('token_type', $answer);
    }

    /**
     * @testdox A refresh token of another client, a revoked one and one of a deactivated user are inactive
     */
    public function testOtherRefreshTokensAreInactive(): void
    {
        $fixture = $this->getFixture();
        $suffix = $this->suffix();
        $user = $fixture->createAndSaveUser('sso-intro2-' . $suffix, 'x@example.local');
        $role = $fixture->createAndSaveRole('SSO intro2 ' . $suffix, $GLOBALS['gCurrentOrgId']);
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
        $client = $this->createRestrictedClient($suffix, array());
        $service = $this->service();

        $plain = 'refresh-a-' . $suffix;
        $this->createToken($client, $user['usr_id'], TBL_OIDC_REFRESH_TOKENS, 'ort', $plain);
        $token = $this->clientToken($service, $plain, $client->getIdentifier());
        $this->assertSame(array('active' => false), $this->introspect($service, $token, 'another-client'));

        $this->getDatabase()->queryPrepared('UPDATE ' . TBL_USERS . ' SET usr_valid = false WHERE usr_id = ?', array($user['usr_id']));
        $this->assertSame(array('active' => false), $this->introspect($service, $token, $client->getIdentifier()));

        $revoked = 'refresh-b-' . $suffix;
        $this->createToken($client, $user['usr_id'], TBL_OIDC_REFRESH_TOKENS, 'ort', $revoked);
        (new RefreshTokenRepository($this->getDatabase()))->revokeRefreshToken($revoked);
        $this->assertSame(array('active' => false), $this->introspect($service, $this->clientToken($service, $revoked, $client->getIdentifier()), $client->getIdentifier()));
    }

    /**
     * @testdox A value that is no refresh token is left to the access token check
     */
    public function testAnAccessTokenIsNoRefreshToken(): void
    {
        $service = $this->service();

        $this->assertNull($this->introspect($service, 'eyJhbGciOiJSUzI1NiJ9.e30.signature', 'client'));
        $this->assertNull($this->introspect($service, 'garbage', 'client'));
    }

    /**
     * @testdox The description of an access token has the type, issuer, audience and issue time too
     */
    public function testAnAccessTokenIsDescribedFully(): void
    {
        $suffix = $this->suffix();
        $client = $this->createRestrictedClient($suffix, array());
        $plain = 'access-' . $suffix;
        $this->createToken($client, (int) $GLOBALS['gCurrentUserId'], TBL_OIDC_ACCESS_TOKENS, 'oat', $plain);
        $token = (new \Admidio\SSO\Repository\AccessTokenRepository($this->getDatabase()))->getToken($plain);

        $service = $this->service();
        $answer = (new ReflectionMethod($service, 'describeActiveToken'))->invoke($service, $token, $client, 'Bearer');

        $this->assertSame('Bearer', $answer['token_type']);
        $this->assertSame($service->getIssuerURL(), $answer['iss']);
        $this->assertSame($client->getIdentifier(), $answer['aud']);
        $this->assertArrayHasKey('iat', $answer);
        $this->assertArrayHasKey('username', $answer);
    }
}
