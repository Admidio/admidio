<?php
/**
 * Regression coverage for SAML clients with several certificates.
 *
 * A service provider that rolls its key over publishes the old and the new signing certificate in
 * its metadata and signs with either. The client keeps all of them, and a message is accepted if
 * one verifies it (SAML-03 of the certification run).
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Service\SAMLCertificates;
use Admidio\SSO\Service\SAMLMetadataImporter;
use Admidio\SSO\Service\SAMLRequestException;
use Admidio\SSO\Service\SAMLService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use LightSaml\Credential\X509Certificate;
use LightSaml\Model\Context\DeserializationContext;
use LightSaml\Model\Context\SerializationContext;
use LightSaml\Model\Protocol\AuthnRequest;
use LightSaml\Model\Protocol\SamlMessage;

class SsoSamlSigningCertificatesTest extends AdministratorTestCase
{
    use SsoClientFixture;

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
     * @return array{0:string,1:string} Certificate and private key PEM of a fresh key pair
     */
    private function createKeyPair(): array
    {
        $key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
        $csr = openssl_csr_new(array('commonName' => 'saml-rollover-test'), $key);
        openssl_x509_export(openssl_csr_sign($csr, null, $key, 1), $certificatePem);
        openssl_pkey_export($key, $privateKeyPem);

        return array(trim($certificatePem), $privateKeyPem);
    }

    private function signed(string $certificatePem, string $privateKeyPem): SamlMessage
    {
        $request = new AuthnRequest();
        $request->setSignature(
            $this->service()->getSignatureWriter($privateKeyPem, (new X509Certificate())->loadPem($certificatePem))
        );
        $document = new \DOMDocument();
        $request->serialize($document, new SerializationContext($document));

        return SamlMessage::fromXML($document->saveXML(), new DeserializationContext());
    }

    private function bodyOf(string $pem): string
    {
        return preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem);
    }

    /**
     * @testdox A request signed with the second certificate of a rollover is accepted, and so is one signed with the first
     */
    public function testEitherCertificateOfARolloverVerifies(): void
    {
        [$oldCert, $oldKey] = $this->createKeyPair();
        [$newCert, $newKey] = $this->createKeyPair();
        $client = $this->createSamlClient($this->suffix());
        $client->setValue('smc_x509_certificate', $oldCert . "\n" . $newCert);

        $this->assertTrue($this->service()->validateSignature($client, $this->signed($newCert, $newKey), true));
        $this->assertTrue($this->service()->validateSignature($client, $this->signed($oldCert, $oldKey), true));
    }

    /**
     * @testdox A request signed with a key that is none of the certificates is rejected
     */
    public function testAnotherKeyIsRejected(): void
    {
        [$oldCert] = $this->createKeyPair();
        [$newCert] = $this->createKeyPair();
        [$strangerCert, $strangerKey] = $this->createKeyPair();
        $client = $this->createSamlClient($this->suffix());
        $client->setValue('smc_x509_certificate', $oldCert . "\n" . $newCert);

        $this->expectException(SAMLRequestException::class);
        $this->service()->validateSignature($client, $this->signed($strangerCert, $strangerKey), true);
    }

    /**
     * @testdox A block that is not a certificate does not hide the others
     */
    public function testAnUnreadableBlockIsSkipped(): void
    {
        [$cert, $key] = $this->createKeyPair();
        $client = $this->createSamlClient($this->suffix());
        $client->setValue(
            'smc_x509_certificate',
            "-----BEGIN CERTIFICATE-----\nAAAA\n-----END CERTIFICATE-----\n" . $cert
        );

        $this->assertTrue($this->service()->validateSignature($client, $this->signed($cert, $key), true));
    }

    /**
     * @testdox Certificates are split, normalized and de-duplicated
     */
    public function testCertificatesAreParsed(): void
    {
        [$first] = $this->createKeyPair();
        [$second] = $this->createKeyPair();
        $unwrapped = '-----BEGIN CERTIFICATE-----' . $this->bodyOf($first) . '-----END CERTIFICATE-----';

        $parsed = SAMLCertificates::parse($first . "\n\n" . $second . "\n" . $unwrapped);

        $this->assertCount(2, $parsed);
        $this->assertSame($this->bodyOf($first), $this->bodyOf($parsed[0]));
        $this->assertSame($this->bodyOf($second), $this->bodyOf($parsed[1]));
        $this->assertSame(array(), SAMLCertificates::parse(''));
        $this->assertFalse(SAMLCertificates::hasInvalidBlock($first . "\n" . $second));
        $this->assertFalse(SAMLCertificates::hasInvalidBlock(''));
        $this->assertTrue(SAMLCertificates::hasInvalidBlock($first . "\n-----BEGIN CERTIFICATE-----\nAAAA\n-----END CERTIFICATE-----"));
        $this->assertTrue(SAMLCertificates::hasInvalidBlock('just some text'));
    }

    /**
     * @testdox The metadata importer keeps every signing certificate and the encryption certificate, and drops one that left the metadata
     */
    public function testTheImporterTakesAllSigningCertificates(): void
    {
        [$old] = $this->createKeyPair();
        [$new] = $this->createKeyPair();
        [$encryption] = $this->createKeyPair();
        $client = $this->createSamlClient($this->suffix());
        $entityId = (string) $client->getValue('smc_client_id');
        $importer = new SAMLMetadataImporter();

        $importer->apply($client, $this->metadata($entityId, array($old, $new), $encryption));
        $this->assertSame(array($this->bodyOf($old), $this->bodyOf($new)), array_map(
            fn ($pem) => $this->bodyOf($pem),
            SAMLCertificates::parse($client->getValue('smc_x509_certificate'))
        ));
        $this->assertSame($this->bodyOf($encryption), $this->bodyOf($client->getValue('smc_x509_encryption_certificate')));

        // after the rollover the old key is gone from the metadata, and so is the encryption key
        $importer->apply($client, $this->metadata($entityId, array($new), null));
        $this->assertSame(
            array($this->bodyOf($new)),
            array_map(fn ($pem) => $this->bodyOf($pem), SAMLCertificates::parse($client->getValue('smc_x509_certificate')))
        );
        $this->assertEmpty($client->getValue('smc_x509_encryption_certificate'));
    }

    private function metadata(string $entityId, array $signing, ?string $encryption): string
    {
        $key = static fn (string $use, string $pem): string => '<md:KeyDescriptor use="' . $use . '"><ds:KeyInfo><ds:X509Data><ds:X509Certificate>'
            . preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem) . '</ds:X509Certificate></ds:X509Data></ds:KeyInfo></md:KeyDescriptor>';

        $descriptors = '';
        foreach ($signing as $pem) {
            $descriptors .= $key('signing', $pem);
        }
        if ($encryption !== null) {
            $descriptors .= $key('encryption', $encryption);
        }

        return '<?xml version="1.0"?><md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" entityID="' . $entityId . '">'
            . '<md:SPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">' . $descriptors
            . '<md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="' . $entityId . '/acs" index="0"/>'
            . '</md:SPSSODescriptor></md:EntityDescriptor>';
    }
}
