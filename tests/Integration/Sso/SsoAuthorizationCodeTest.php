<?php
/**
 * Regression coverage for the data an OIDC authorization code carries to the ID token.
 *
 * The time of the authentication was handed to the timestamp column as a Unix time, which the
 * entity cannot parse and stored as NULL. Every ID token was then issued without auth_time,
 * which OIDC requires when the client sent max_age. The conformance tests oidcc-max-age-1 and
 * oidcc-max-age-10000 failed on it.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Grants\OIDCAuthCodeGrant;
use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use ReflectionMethod;

class SsoAuthorizationCodeTest extends AdministratorTestCase
{
    use SsoClientFixture;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @testdox The authorization code keeps the time of the authentication
     */
    public function testTheAuthorizationCodeKeepsTheAuthenticationTime(): void
    {
        global $gCurrentUser;

        $client = $this->createRestrictedClient($this->suffix(), array());
        $repository = new AuthCodeRepository($this->getDatabase());
        $grant = new OIDCAuthCodeGrant($repository, new RefreshTokenRepository($this->getDatabase()), new \DateInterval('PT10M'));

        $authenticationTime = time() - 3600;
        $grant->setAuthenticationTime($authenticationTime);

        // The fixture client identifies its users by usr_uuid
        $issueAuthCode = new ReflectionMethod($grant, 'issueAuthCode');
        $authCode = $issueAuthCode->invoke($grant, new \DateInterval('PT10M'), $client, (string) $gCurrentUser->getValue('usr_uuid'),
            $client->getRedirectUri(), array());

        $storedCode = $repository->getToken($authCode->getIdentifier());
        $this->assertFalse($storedCode->isNewRecord());
        $this->assertSame($authenticationTime, (int) $storedCode->getValue('oac_auth_time', 'U'));
    }
}
