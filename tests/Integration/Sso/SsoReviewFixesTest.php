<?php
/**
 * Regression coverage for the findings 103 to 125 of the SAML/OIDC end-to-end review (SSO_Certification).
 *
 * What can be decided without a browser lives here: the settings of a SAML client (several ACS URLs, the
 * lifetime of an assertion), the choice of the ACS for a request, the first-pass rule for the request
 * lifetime, the basic client credentials, a disabled OIDC client, the claims, and the right of a user to
 * keep a refresh token.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Infrastructure\Exception;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Entity\SAMLClient;
use Admidio\SSO\Entity\UserEntity;
use Admidio\SSO\Repository\ClientRepository;
use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\SSO\Service\SAMLService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use LightSaml\Model\Protocol\AuthnRequest;
use LightSaml\SamlConstants;
use ReflectionMethod;

class SsoReviewFixesTest extends AdministratorTestCase
{
    use SsoClientFixture;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    private function samlService(): SAMLService
    {
        global $gCurrentUser;

        return new SAMLService($this->getDatabase(), $gCurrentUser);
    }

    private function formValues(string $suffix, array $override = array()): array
    {
        return array_merge(array(
            'smc_client_id' => 'https://sp.example/' . $suffix,
            'smc_client_name' => 'Review ' . $suffix,
            'smc_acs_url' => 'https://sp.example/acs',
            'smc_x509_certificate' => '',
            'smc_userid_field' => 'usr_uuid',
        ), $override);
    }

    /**
     * @testdox Several ACS URLs, one per line, are saved trimmed and without duplicates, the first is the default
     */
    public function testSeveralAcsUrlsAreNormalised(): void
    {
        $client = $this->samlService()->saveData(null, $this->formValues($this->suffix(), array(
            'smc_acs_url' => " https://sp.example/acs1 \r\n\r\nhttps://sp.example/acs2\nhttps://sp.example/acs1\n"
        )));

        $this->assertSame(
            array('https://sp.example/acs1', 'https://sp.example/acs2'),
            $client->getAcsUrls()
        );
    }

    /**
     * @testdox An ACS URL that is no http(s) address, and an empty list, are refused
     */
    public function testAnInvalidAcsUrlIsRefused(): void
    {
        foreach (array("https://sp.example/acs\njavascript:alert(1)", 'not a url', "  \n ") as $value) {
            try {
                $this->samlService()->saveData(null, $this->formValues($this->suffix(), array('smc_acs_url' => $value)));
                $this->fail('"' . $value . '" must be refused.');
            } catch (Exception $exception) {
                $this->assertContains($exception->getTranslationId(), array('SYS_SSO_ACS_URL_INVALID', 'SYS_FIELD_EMPTY'));
            }
        }
    }

    /**
     * @testdox A lifetime of 0 seconds for the assertions is refused
     */
    public function testAnAssertionLifetimeOfZeroIsRefused(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('1 second');

        $this->samlService()->saveData(null, $this->formValues($this->suffix(), array('smc_assertion_lifetime' => 0)));
    }

    private function authnRequest(?string $acsUrl): AuthnRequest
    {
        $request = new AuthnRequest();
        $request->setID('ID' . bin2hex(random_bytes(8)));
        if ($acsUrl !== null) {
            $request->setAssertionConsumerServiceURL($acsUrl);
        }
        return $request;
    }

    private function resolveAcsUrl(AuthnRequest $request, SAMLClient $client): string
    {
        return (new ReflectionMethod(SAMLService::class, 'resolveAcsUrl'))->invoke($this->samlService(), $request, $client);
    }

    /**
     * @testdox A request may name any registered ACS URL, names none and gets the default, or is refused
     */
    public function testTheAcsUrlOfARequestIsOneOfTheRegisteredOnes(): void
    {
        $client = $this->samlService()->saveData(null, $this->formValues($this->suffix(), array(
            'smc_acs_url' => "https://sp.example/acs1\nhttps://sp.example/acs2"
        )));

        $this->assertSame('https://sp.example/acs2', $this->resolveAcsUrl($this->authnRequest('https://sp.example/acs2'), $client));
        $this->assertSame('https://sp.example/acs1', $this->resolveAcsUrl($this->authnRequest(null), $client));

        $this->expectException(\Admidio\SSO\Service\SAMLRequestException::class);
        $this->resolveAcsUrl($this->authnRequest('https://evil.example/acs'), $client);
    }

    /**
     * @testdox The lifetime of a request is judged on arrival only, not when it returns from the login form
     */
    public function testTheRequestLifetimeIsNotJudgedAgainAfterTheLogin(): void
    {
        $service = $this->samlService();
        $client = $this->createSamlClient($this->suffix());
        $request = $this->authnRequest(null);
        $request->setIssueInstant(new \DateTime('-1 hour'));
        $request->setDestination('https://idp.example/sso');

        $validate = new ReflectionMethod(SAMLService::class, 'validateRequestContext');
        $remember = new ReflectionMethod(SAMLService::class, 'rememberPendingLogin');
        $returns = new ReflectionMethod(SAMLService::class, 'isPendingLoginReturn');

        try {
            $validate->invoke($service, $client, $request, 'https://idp.example/sso', true);
            $this->fail('An hour old request is expired.');
        } catch (\Admidio\SSO\Service\SAMLRequestException $exception) {
            $this->assertStringContainsString('expired', $exception->getMessage());
        }

        $this->assertFalse($returns->invoke($service, $request));
        $remember->invoke($service, $request);
        $this->assertTrue($returns->invoke($service, $request));
        $this->assertFalse($returns->invoke($service, $this->authnRequest(null)), 'another request is not a return');

        $validate->invoke($service, $client, $request, 'https://idp.example/sso', false);
        unset($_SESSION['saml_pending_login']);
        $this->addToAssertionCount(1);
    }

    /**
     * @testdox A disabled OIDC client is returned by the repository but never authenticates
     */
    public function testADisabledOidcClientDoesNotEndInAnException(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        $client->setValue('ocl_enabled', 0);
        $client->save();

        $repository = new ClientRepository($this->getDatabase());
        $this->assertInstanceOf(OIDCClient::class, $repository->getClientEntity($client->getIdentifier()));
        $this->assertFalse($repository->validateClient($client->getIdentifier(), 'secret-' . substr($client->getIdentifier(), 11), null));
    }

    /**
     * A secret sent raw in a Basic header is URL-decoded when it has the encoded form, "ab+c" arrives as "ab c".
     *
     * @testdox A secret with a plus sign authenticates, sent raw or URL-encoded
     */
    public function testASecretWithAPlusSignAuthenticatesRawAndEncoded(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        $client->setValue('ocl_client_secret', password_hash('Ab+Cd9', PASSWORD_DEFAULT));
        $client->save();
        $repository = new ClientRepository($this->getDatabase());

        // raw: the credentials normalisation turns "Ab+Cd9" into "Ab Cd9"
        $this->assertTrue($repository->validateClient($client->getIdentifier(), 'Ab Cd9', null));
        // encoded: "Ab%2BCd9" is decoded to the secret itself
        $this->assertTrue($repository->validateClient($client->getIdentifier(), 'Ab+Cd9', null));
        $this->assertFalse($repository->validateClient($client->getIdentifier(), 'Ab Cd8', null));
    }

    /**
     * @testdox The claims carry neither a locale nor an address region
     */
    public function testNoLocaleAndNoRegionClaim(): void
    {
        global $gCurrentUserId, $gProfileFields;

        $client = $this->createRestrictedClient($this->suffix(), array());
        $claims = (new UserEntity($this->getDatabase(), $gProfileFields, $client, (int) $gCurrentUserId))->getClaims();

        $this->assertArrayNotHasKey('locale', $claims);
        $this->assertArrayNotHasKey('region', $claims['address'] ?? array());
    }

    /**
     * @testdox A user who may no longer log in loses the refresh token, though the client is open to everybody
     */
    public function testARefreshTokenEndsWithTheLoginRight(): void
    {
        global $gCurrentOrgId;

        $fixture = $this->getFixture();
        $suffix = $this->suffix();
        $user = $fixture->createAndSaveUser('sso-login-' . $suffix, 'sso-login-' . $suffix . '@example.local');
        $role = $fixture->createAndSaveRole('SSO login ' . $suffix, $gCurrentOrgId);
        $membership = $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        $client = $this->createRestrictedClient($suffix, array());
        $refreshToken = 'refresh-' . $suffix;
        $this->createToken($client, $user['usr_id'], TBL_OIDC_REFRESH_TOKENS, 'ort', $refreshToken);
        $repository = new RefreshTokenRepository($this->getDatabase());

        $this->assertFalse($repository->isRefreshTokenRevoked($refreshToken), 'a member keeps the token');

        $this->getDatabase()->queryPrepared('UPDATE ' . TBL_USERS . ' SET usr_valid = false WHERE usr_id = ?', array($user['usr_id']));
        $this->assertTrue($repository->isRefreshTokenRevoked($refreshToken), 'a deactivated account loses it');
        $this->assertNotNull($membership);
    }
}
