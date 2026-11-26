<?php
/**
 * Regression coverage for how the SAML endpoints classify a faulty request.
 *
 * A request that is faulty in itself (wrong destination, expired, bad signature, wrong ACS URL) is
 * the requester's error: it is rejected with a SAMLRequestException, which the endpoints answer
 * with the status Requester instead of the Responder status that stands for a fault of the
 * identity provider (SAML Core 3.2.2.2). Found with the Codice SAML conformance test kit, which is
 * not part of the build.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Service\SAMLRequestException;
use Admidio\SSO\Service\SAMLService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use LightSaml\Credential\X509Certificate;
use LightSaml\Model\Context\DeserializationContext;
use LightSaml\Model\Context\SerializationContext;
use LightSaml\Model\Protocol\AuthnRequest;
use LightSaml\Model\Protocol\SamlMessage;
use LightSaml\SamlConstants;
use ReflectionMethod;

class SsoSamlRequestErrorTest extends AdministratorTestCase
{
    use SsoClientFixture;

    private const SSO_URL = 'https://idp.example/modules/sso/index.php/saml/sso';

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    private function service(): SAMLService
    {
        global $gCurrentUser;

        return new SAMLService($this->getDatabase(), $gCurrentUser);
    }

    /**
     * Call a private method of the production service.
     */
    private function call(string $method, mixed ...$arguments): mixed
    {
        $service = $this->service();

        return (new ReflectionMethod($service, $method))->invoke($service, ...$arguments);
    }

    private function assertRejectedAsRequesterError(callable $action, array $expectedStatus = array(SamlConstants::STATUS_REQUESTER)): void
    {
        try {
            $action();
            $this->fail('The request should have been rejected with a SAMLRequestException.');
        } catch (SAMLRequestException $exception) {
            $this->assertSame($expectedStatus, $exception->getStatus());
        }
    }

    /**
     * @testdox A request error carries the status Requester unless it says otherwise
     */
    public function testARequestErrorIsARequesterError(): void
    {
        $this->assertSame(
            array(SamlConstants::STATUS_REQUESTER),
            (new SAMLRequestException('faulty'))->getStatus()
        );
    }

    private function requestWith(string $destination, ?int $issuedAt): AuthnRequest
    {
        $request = new AuthnRequest();
        $request->setDestination($destination);
        if ($issuedAt !== null) {
            $request->setIssueInstant((new \DateTime())->setTimestamp($issuedAt));
        }

        return $request;
    }

    /**
     * @testdox A request for this endpoint that was just issued is accepted
     */
    public function testAValidRequestContextIsAccepted(): void
    {
        $client = $this->createSamlClient($this->suffix());

        $this->call('validateRequestContext', $client, $this->requestWith(self::SSO_URL, time()), self::SSO_URL);
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider faultyContextProvider
     * @testdox A request with $fault is a requester error
     */
    public function testAFaultyRequestContextIsARequesterError(string $fault, string $destination, ?int $issuedAt): void
    {
        $client = $this->createSamlClient($this->suffix());
        $request = $this->requestWith($destination, $issuedAt);

        $this->assertRejectedAsRequesterError(
            fn () => $this->call('validateRequestContext', $client, $request, self::SSO_URL)
        );
    }

    /**
     * @return array<string,array{0:string,1:string,2:int|null}>
     */
    public static function faultyContextProvider(): array
    {
        return array(
            'a destination of another endpoint' => array('a destination of another endpoint', 'https://incorrect.destination.com', time()),
            'no issue instant' => array('no issue instant', self::SSO_URL, null),
            'an issue instant in the future' => array('an issue instant in the future', self::SSO_URL, time() + 3600),
            'an expired issue instant' => array('an expired issue instant', self::SSO_URL, time() - 3600)
        );
    }

    /**
     * @return array{0:X509Certificate,1:string} Certificate and private key PEM of a fresh key pair
     */
    private function createKeyPair(): array
    {
        $key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
        $csr = openssl_csr_new(array('commonName' => 'saml-request-test'), $key);
        $certificate = openssl_csr_sign($csr, null, $key, 1);
        openssl_x509_export($certificate, $certificatePem);
        openssl_pkey_export($key, $privateKeyPem);

        return array((new X509Certificate())->loadPem($certificatePem), $privateKeyPem);
    }

    /**
     * Sign an AuthnRequest and read it back, the way an incoming message reaches the service.
     */
    private function signedAndReadBack(AuthnRequest $request, X509Certificate $certificate, string $privateKeyPem): SamlMessage
    {
        $request->setSignature($this->service()->getSignatureWriter($privateKeyPem, $certificate));

        $document = new \DOMDocument();
        $request->serialize($document, new SerializationContext($document));

        return SamlMessage::fromXML($document->saveXML(), new DeserializationContext());
    }

    /**
     * @testdox A missing signature that the client requires is a requester error
     */
    public function testAMissingRequiredSignatureIsARequesterError(): void
    {
        [$certificate] = $this->createKeyPair();
        $client = $this->createSamlClient($this->suffix());
        $client->setValue('smc_x509_certificate', $certificate->toPem());

        $this->assertRejectedAsRequesterError(
            fn () => $this->service()->validateSignature($client, new AuthnRequest(), true)
        );
    }

    /**
     * @testdox A signature that does not match the certificate of the client is a requester error
     */
    public function testAWrongSignatureIsARequesterError(): void
    {
        [$clientCertificate] = $this->createKeyPair();
        [$otherCertificate, $otherKey] = $this->createKeyPair();
        $client = $this->createSamlClient($this->suffix());
        $client->setValue('smc_x509_certificate', $clientCertificate->toPem());

        $request = $this->signedAndReadBack(new AuthnRequest(), $otherCertificate, $otherKey);

        $this->assertRejectedAsRequesterError(
            fn () => $this->service()->validateSignature($client, $request, true)
        );
    }

    /**
     * @testdox A signature made with the key of the client is accepted
     */
    public function testARightSignatureIsAccepted(): void
    {
        [$certificate, $privateKey] = $this->createKeyPair();
        $client = $this->createSamlClient($this->suffix());
        $client->setValue('smc_x509_certificate', $certificate->toPem());

        $request = $this->signedAndReadBack(new AuthnRequest(), $certificate, $privateKey);

        $this->assertTrue($this->service()->validateSignature($client, $request, true));
    }
}
