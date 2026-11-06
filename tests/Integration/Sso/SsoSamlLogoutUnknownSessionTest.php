<?php
/**
 * Regression coverage for a SAML LogoutRequest whose session Admidio does not know (any more).
 *
 * A participant ends with the Admidio session (20 minutes by default), while a service provider such
 * as WordPress keeps its own session for days and asks for the logout only then. Admidio answered
 * with the status Responder, so the service provider reported "logout_not_success" and its user
 * could not log out. The session the service provider wants ended no longer exists, so the answer
 * is Success.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Service\KeyService;
use Admidio\SSO\Service\SAMLService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use LightSaml\Credential\X509Certificate;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\Assertion\NameID;
use LightSaml\Model\Context\DeserializationContext;
use LightSaml\Model\Context\SerializationContext;
use LightSaml\Model\Protocol\LogoutRequest;
use LightSaml\Model\Protocol\SamlMessage;
use ReflectionMethod;
use ReflectionProperty;

class SsoSamlLogoutUnknownSessionTest extends AdministratorTestCase
{
    use SsoClientFixture;

    private string $previousSigningKey = '0';

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        global $gSettingsManager;

        parent::setUp();

        // The answer is signed with the SAML signing key of the organization
        $this->previousSigningKey = (string) $gSettingsManager->get('sso_saml_signing_key');
        $keyId = (new KeyService($this->getDatabase()))->createDefaultKey();
        $gSettingsManager->set('sso_saml_signing_key', (string) $keyId);
    }

    protected function tearDown(): void
    {
        global $gSettingsManager;

        $gSettingsManager->set('sso_saml_signing_key', $this->previousSigningKey);

        parent::tearDown();
    }

    /**
     * @testdox A logout request for an unknown session is answered with Success and the RelayState
     */
    public function testAnUnknownSessionIsAnsweredAsLoggedOut(): void
    {
        global $gCurrentUser;

        $key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
        $csr = openssl_csr_new(array('commonName' => 'sp-logout-test'), $key);
        openssl_x509_export(openssl_csr_sign($csr, null, $key, 1), $certificatePem);
        openssl_pkey_export($key, $privateKeyPem);

        $client = $this->createSamlClient($this->suffix());
        $client->setValue('smc_x509_certificate', $certificatePem);
        $client->setValue('smc_slo_url', 'https://sp.example/slo');
        $client->save();

        $service = new SAMLService($this->getDatabase(), $gCurrentUser);
        $sloUrl = (string) (new ReflectionProperty($service, 'sloUrl'))->getValue($service);

        $request = new LogoutRequest();
        $request->setID('ID' . bin2hex(random_bytes(8)));
        $request->setIssueInstant(new \DateTime());
        $request->setDestination($sloUrl);
        $request->setIssuer(new Issuer($client->getIdentifier()));
        $request->setNameID(new NameID('somebody', 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent'));
        $request->setSessionIndex('a-session-that-expired');
        $request->setSignature($service->getSignatureWriter($privateKeyPem, (new X509Certificate())->loadPem($certificatePem)));

        $document = new \DOMDocument();
        $request->serialize($document, new SerializationContext($document));
        $received = SamlMessage::fromXML($document->saveXML(), new DeserializationContext());
        // The RelayState travels beside the message, not inside it
        $received->setRelayState('relay-state-of-the-sp');

        $response = (new ReflectionMethod($service, 'handleIncomingLogoutRequest'))->invoke($service, $received);

        $this->assertSame(302, $response->getStatusCode());
        parse_str((string) parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('relay-state-of-the-sp', $query['RelayState']);
        $logoutResponse = gzinflate(base64_decode($query['SAMLResponse']));
        $this->assertStringContainsString('urn:oasis:names:tc:SAML:2.0:status:Success', $logoutResponse);
        $this->assertStringNotContainsString('status:Responder', $logoutResponse);
        $this->assertStringContainsString('InResponseTo="' . $request->getID() . '"', $logoutResponse);
        $this->assertStringStartsWith('https://sp.example/slo?', $response->getHeaderLine('Location'));
    }
}
