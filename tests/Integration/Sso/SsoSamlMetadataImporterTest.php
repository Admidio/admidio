<?php
/**
 * Regression coverage for applying SAML metadata to a SAML client on the server.
 *
 * The metadata of a service provider was only read by the client form in the browser, so a
 * client could not be refreshed from the list, and no automatic refresh could build on it. The
 * importer applies the same settings as the form, but only from the entry with the entity ID of
 * the client, so that reloading never turns a client into another service provider.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Infrastructure\Exception;
use Admidio\SSO\Entity\SAMLClient;
use Admidio\SSO\Service\SAMLMetadataImporter;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;

class SsoSamlMetadataImporterTest extends AdministratorTestCase
{
    use SsoClientFixture;

    private const CERTIFICATE = 'MIIBszCCAVmgAwIBAgIUTESTCERTIFICATEFORADMIDIOTESTSONLYxMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAw';

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @testdox The endpoints, the signing certificate and the request signing are taken from the metadata
     */
    public function testTheSettingsAreTakenFromTheMetadata(): void
    {
        $client = $this->createSamlClient($this->suffix());
        $entityId = (string) $client->getValue('smc_client_id');

        (new SAMLMetadataImporter())->apply($client, $this->metadata($entityId));

        // The default ACS wins over the first one, HTTP-Redirect wins for SLO
        $this->assertSame($entityId . '/acs/default', $client->getValue('smc_acs_url'));
        $this->assertSame($entityId . '/slo/redirect', $client->getValue('smc_slo_url'));
        $this->assertSame(SAMLMetadataImporter::formatCertificate(self::CERTIFICATE), $client->getValue('smc_x509_certificate'));
        $this->assertTrue((bool) $client->getValue('smc_require_auth_signed'));
        $this->assertSame($entityId, $client->getValue('smc_client_id'));
    }

    /**
     * @testdox Only the entry with the entity ID of the client is used from an aggregate
     */
    public function testTheEntryOfTheClientIsUsedFromAnAggregate(): void
    {
        $client = $this->createSamlClient($this->suffix());
        $entityId = (string) $client->getValue('smc_client_id');

        $aggregate = '<md:EntitiesDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata">'
            . $this->entityDescriptor('https://other.example/sp')
            . $this->entityDescriptor($entityId)
            . '</md:EntitiesDescriptor>';
        (new SAMLMetadataImporter())->apply($client, $aggregate);

        $this->assertSame($entityId . '/acs/default', $client->getValue('smc_acs_url'));
    }

    /**
     * @testdox Metadata of another service provider is refused
     */
    public function testMetadataOfAnotherServiceProviderIsRefused(): void
    {
        $client = $this->createSamlClient($this->suffix());

        $this->assertRefused($client, $this->metadata('https://other.example/sp'), 'SYS_SSO_METADATA_ENTITY_MISSING');
        $this->assertSame('', (string) $client->getValue('smc_slo_url'), 'nothing was changed');
    }

    /**
     * @testdox A document with a document type is refused
     */
    public function testADocumentTypeIsRefused(): void
    {
        $client = $this->createSamlClient($this->suffix());
        $xml = '<?xml version="1.0"?><!DOCTYPE md:EntityDescriptor [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
            . $this->entityDescriptor((string) $client->getValue('smc_client_id'));

        $this->assertRefused($client, $xml, 'SYS_SSO_METADATA_INVALID');
    }

    /**
     * @testdox A document that is not SAML metadata is refused
     */
    public function testOtherDocumentsAreRefused(): void
    {
        $client = $this->createSamlClient($this->suffix());

        $this->assertRefused($client, '<html><body>Not found</body></html>', 'SYS_SSO_METADATA_INVALID');
        $this->assertRefused($client, 'no xml at all', 'SYS_SSO_METADATA_INVALID');
    }

    private function assertRefused(SAMLClient $client, string $xml, string $translationId): void
    {
        try {
            (new SAMLMetadataImporter())->apply($client, $xml);
            $this->fail('The metadata was accepted.');
        } catch (Exception $exception) {
            $this->assertSame($translationId, $exception->getTranslationId());
        }
    }

    private function metadata(string $entityId): string
    {
        return '<?xml version="1.0"?>' . $this->entityDescriptor($entityId);
    }

    private function entityDescriptor(string $entityId): string
    {
        return '<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" entityID="' . $entityId . '">'
            . '<md:SPSSODescriptor AuthnRequestsSigned="true" protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">'
            . '<md:KeyDescriptor use="encryption"><ds:KeyInfo><ds:X509Data><ds:X509Certificate>ENCRYPTIONONLY</ds:X509Certificate></ds:X509Data></ds:KeyInfo></md:KeyDescriptor>'
            . '<md:KeyDescriptor use="signing"><ds:KeyInfo><ds:X509Data><ds:X509Certificate>' . self::CERTIFICATE . '</ds:X509Certificate></ds:X509Data></ds:KeyInfo></md:KeyDescriptor>'
            . '<md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="' . $entityId . '/slo/post"/>'
            . '<md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="' . $entityId . '/slo/redirect"/>'
            . '<md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="' . $entityId . '/acs/first" index="0"/>'
            . '<md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="' . $entityId . '/acs/default" index="1" isDefault="true"/>'
            . '</md:SPSSODescriptor></md:EntityDescriptor>';
    }
}
