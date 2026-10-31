<?php
namespace Admidio\SSO\Service;

use Admidio\Infrastructure\Exception;
use Admidio\SSO\Entity\SAMLClient;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Applies the SAML metadata of a service provider to its SAML client.
 *
 * It takes the same settings from the metadata as the client form does in the browser: the
 * assertion consumer service, the single logout service, the signing certificate and whether
 * the service provider signs its authentication requests. The metadata may describe one entity
 * (EntityDescriptor) or several (EntitiesDescriptor); only the entry with the entity ID of the
 * client is used, so that reloading metadata never turns a client into another service
 * provider. Changing the entity ID stays a deliberate step in the client form.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SAMLMetadataImporter
{
    private const NS_METADATA = 'urn:oasis:names:tc:SAML:2.0:metadata';
    private const NS_SIGNATURE = 'http://www.w3.org/2000/09/xmldsig#';
    private const BINDING_POST = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';
    private const BINDING_REDIRECT = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';

    /**
     * Apply the metadata to the client. The client is not saved.
     * @param SAMLClient $client The client whose settings are updated.
     * @param string $metadataXml The metadata document.
     * @throws Exception SYS_SSO_METADATA_INVALID if the document is not SAML metadata,
     *                   SYS_SSO_METADATA_ENTITY_MISSING if it does not describe the client.
     */
    public function apply(SAMLClient $client, string $metadataXml): void
    {
        $xpath = $this->load($metadataXml);
        $entityId = (string) $client->getValue('smc_client_id');

        $spDescriptor = null;
        foreach ($xpath->query('//md:EntityDescriptor') as $entity) {
            if ($entity instanceof DOMElement && $entity->getAttribute('entityID') === $entityId) {
                $spDescriptor = $xpath->query('md:SPSSODescriptor', $entity)->item(0);
                break;
            }
        }
        if (!$spDescriptor instanceof DOMElement) {
            throw new Exception('SYS_SSO_METADATA_ENTITY_MISSING', array($entityId));
        }

        // Prefer the default ACS and otherwise an HTTP-POST ACS, which is the binding Admidio
        // uses for SAML responses.
        $acsUrl = $this->firstLocation($xpath, $spDescriptor, 'md:AssertionConsumerService[@isDefault="true"]')
            ?? $this->firstLocation($xpath, $spDescriptor, 'md:AssertionConsumerService[@Binding="' . self::BINDING_POST . '"]')
            ?? $this->firstLocation($xpath, $spDescriptor, 'md:AssertionConsumerService');
        if ($acsUrl === null) {
            throw new Exception('SYS_SSO_METADATA_INVALID');
        }
        $client->setValue('smc_acs_url', $acsUrl);

        // Admidio sends SLO messages with HTTP-Redirect, so that endpoint is preferred.
        $sloUrl = $this->firstLocation($xpath, $spDescriptor, 'md:SingleLogoutService[@Binding="' . self::BINDING_REDIRECT . '"]')
            ?? $this->firstLocation($xpath, $spDescriptor, 'md:SingleLogoutService');
        if ($sloUrl !== null) {
            $client->setValue('smc_slo_url', $sloUrl);
        }

        // A KeyDescriptor without a use attribute can be used for signing too.
        $certificate = $xpath->query(
            'md:KeyDescriptor[@use="signing" or not(@use)]/ds:KeyInfo/ds:X509Data/ds:X509Certificate',
            $spDescriptor
        )->item(0);
        if ($certificate !== null && trim($certificate->textContent) !== '') {
            $client->setValue('smc_x509_certificate', self::formatCertificate($certificate->textContent));
        }

        $client->setValue('smc_require_auth_signed', $spDescriptor->getAttribute('AuthnRequestsSigned') === 'true');
    }

    /**
     * Wrap the base64 content of an X509Certificate element into a PEM certificate.
     */
    public static function formatCertificate(string $base64): string
    {
        $base64 = preg_replace('/\s+/', '', $base64);
        return "-----BEGIN CERTIFICATE-----\n" . chunk_split($base64, 64, "\n") . '-----END CERTIFICATE-----';
    }

    /**
     * Parse the document. A document type is refused, so that no entity of it is ever resolved.
     * @throws Exception SYS_SSO_METADATA_INVALID
     */
    private function load(string $metadataXml): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($metadataXml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $document->doctype !== null || $document->documentElement === null
            || $document->documentElement->namespaceURI !== self::NS_METADATA
        ) {
            throw new Exception('SYS_SSO_METADATA_INVALID');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('md', self::NS_METADATA);
        $xpath->registerNamespace('ds', self::NS_SIGNATURE);
        return $xpath;
    }

    /**
     * Location of the first endpoint the query selects below the SP descriptor, if any.
     */
    private function firstLocation(DOMXPath $xpath, DOMElement $spDescriptor, string $query): ?string
    {
        $endpoint = $xpath->query($query, $spDescriptor)->item(0);
        if (!$endpoint instanceof DOMElement) {
            return null;
        }
        $location = trim($endpoint->getAttribute('Location'));
        return $location === '' ? null : $location;
    }
}
