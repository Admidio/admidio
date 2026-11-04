<?php
namespace Admidio\SSO\Service;

use Admidio\Infrastructure\Exception;
use Admidio\SSO\Entity\SAMLClient;
use DOMDocument;
use DOMElement;
use DOMXPath;
use LightSaml\Credential\KeyHelper;
use LightSaml\Credential\X509Certificate;
use LightSaml\Model\Context\DeserializationContext;
use LightSaml\Model\XmlDSig\SignatureXmlReader;

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
     * @param string $trustedCertificates One or more PEM certificates that the metadata has to be signed
     *                                    with (one of them); empty if the metadata is not required to be signed.
     * @return array{validUntil:int|null,cacheDuration:int|null} How long the metadata is valid: the
     *         earliest validUntil (timestamp) and the shortest cacheDuration (seconds) of the entry and
     *         the aggregates around it, null if it states none.
     * @throws Exception SYS_SSO_METADATA_INVALID if the document is not SAML metadata,
     *                   SYS_SSO_METADATA_ENTITY_MISSING if it does not describe the client,
     *                   SYS_SSO_METADATA_SIGNATURE_MISSING or SYS_SSO_METADATA_SIGNATURE_INVALID if a signature
     *                   is required and the entry has none that verifies.
     */
    public function apply(SAMLClient $client, string $metadataXml, string $trustedCertificates = ''): array
    {
        $xpath = $this->load($metadataXml);
        $entityId = (string) $client->getValue('smc_client_id');

        $entityDescriptor = null;
        $spDescriptor = null;
        foreach ($xpath->query('//md:EntityDescriptor') as $entity) {
            if ($entity instanceof DOMElement && $entity->getAttribute('entityID') === $entityId) {
                $entityDescriptor = $entity;
                $spDescriptor = $xpath->query('md:SPSSODescriptor', $entity)->item(0);
                break;
            }
        }
        if (!$spDescriptor instanceof DOMElement) {
            throw new Exception('SYS_SSO_METADATA_ENTITY_MISSING', array($entityId));
        }

        if (trim($trustedCertificates) !== '') {
            $this->assertSigned($xpath, $entityDescriptor, $trustedCertificates);
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

        // All signing certificates are kept, a service provider publishes several during a key
        // rollover. A KeyDescriptor without a use attribute can be used for signing and for
        // encryption. The metadata is authoritative: a certificate that is no longer in it is
        // removed from the client.
        $signing = $this->certificates($xpath, $spDescriptor, 'md:KeyDescriptor[@use="signing" or not(@use)]');
        if ($signing !== array()) {
            $client->setValue('smc_x509_certificate', SAMLCertificates::join($signing));
        }

        // A separate encryption key is used for encrypted assertions. Without one the client
        // falls back to its first signing certificate.
        $encryption = $this->certificates($xpath, $spDescriptor, 'md:KeyDescriptor[@use="encryption"]');
        $client->setValue('smc_x509_encryption_certificate', $encryption === array() ? null : $encryption[0]);

        $client->setValue('smc_require_auth_signed', $spDescriptor->getAttribute('AuthnRequestsSigned') === 'true');

        return $this->validity($entityDescriptor);
    }

    /**
     * Seconds of an xs:duration like P1D or PT36H. A year counts 365 and a month 30 days, which is
     * accurate enough for a cache duration.
     * @return int|null Null if the text is no duration.
     */
    public static function parseDuration(string $duration): ?int
    {
        if (!preg_match('/^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)(?:\.\d+)?S)?)?$/', trim($duration), $m)
            || $duration === 'P' || str_ends_with($duration, 'T')
        ) {
            return null;
        }
        return ((int) ($m[1] ?? 0)) * 31536000 + ((int) ($m[2] ?? 0)) * 2592000 + ((int) ($m[3] ?? 0)) * 86400
            + ((int) ($m[4] ?? 0)) * 3600 + ((int) ($m[5] ?? 0)) * 60 + (int) ($m[6] ?? 0);
    }

    /**
     * validUntil and cacheDuration of an entry: the strictest value on the entry and the aggregates
     * around it, because each of them limits everything inside.
     * @return array{validUntil:int|null,cacheDuration:int|null}
     */
    private function validity(DOMElement $entity): array
    {
        $validUntil = null;
        $cacheDuration = null;
        for ($node = $entity; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node->hasAttribute('validUntil')) {
                $time = strtotime($node->getAttribute('validUntil'));
                if ($time !== false && ($validUntil === null || $time < $validUntil)) {
                    $validUntil = $time;
                }
            }
            if ($node->hasAttribute('cacheDuration')) {
                $seconds = self::parseDuration($node->getAttribute('cacheDuration'));
                if ($seconds !== null && ($cacheDuration === null || $seconds < $cacheDuration)) {
                    $cacheDuration = $seconds;
                }
            }
        }
        return array('validUntil' => $validUntil, 'cacheDuration' => $cacheDuration);
    }

    /**
     * Make sure that the entry is covered by a signature of one of the trusted certificates. The signature
     * is the one of the entry or of the nearest aggregate around it; LightSAML checks that its reference
     * points to the element it sits in, that the ID is unique in the document and the digest of the
     * element, which keeps another element of the document from being passed off as the signed one.
     * @throws Exception SYS_SSO_METADATA_SIGNATURE_MISSING, SYS_SSO_METADATA_SIGNATURE_INVALID
     */
    private function assertSigned(DOMXPath $xpath, DOMElement $entity, string $trustedCertificates): void
    {
        $signature = null;
        for ($node = $entity; $node instanceof DOMElement && $signature === null; $node = $node->parentNode) {
            $signature = $xpath->query('ds:Signature', $node)->item(0);
        }
        if (!$signature instanceof DOMElement) {
            throw new Exception('SYS_SSO_METADATA_SIGNATURE_MISSING');
        }

        $certificates = SAMLCertificates::parse($trustedCertificates);
        try {
            $reader = new SignatureXmlReader();
            $reader->deserialize($signature, new DeserializationContext($xpath->document));

            foreach ($certificates as $pem) {
                try {
                    if ($reader->validate(KeyHelper::createPublicKey((new X509Certificate())->loadPem($pem)))) {
                        return;
                    }
                } catch (\Throwable) {
                    // not signed with this certificate, the next one may be the right one
                }
            }
        } catch (\Throwable) {
            // an unreadable signature is an invalid one
        }
        throw new Exception('SYS_SSO_METADATA_SIGNATURE_INVALID');
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
     * The certificates of the key descriptors the query selects below the SP descriptor.
     * @return array<int,string> PEM certificates, without duplicates and without unreadable ones.
     */
    private function certificates(DOMXPath $xpath, DOMElement $spDescriptor, string $descriptorQuery): array
    {
        $pem = '';
        foreach ($xpath->query($descriptorQuery . '/ds:KeyInfo/ds:X509Data/ds:X509Certificate', $spDescriptor) as $certificate) {
            if (trim($certificate->textContent) !== '') {
                $pem .= self::formatCertificate($certificate->textContent) . "\n";
            }
        }
        return SAMLCertificates::parse($pem);
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
