<?php
namespace Admidio\SSO\Service;

/**
 * The X.509 certificates of a SAML client.
 *
 * A service provider can publish several signing certificates at the same time, e.g. during a
 * key rollover, so the certificate column of a SAML client holds one or more PEM certificates
 * one after the other. The importer, the client form and the signature validation all read them
 * through this class, so they agree on what the certificates of a client are.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SAMLCertificates
{
    private const PEM_PATTERN = '/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s';

    /**
     * Split a text into its PEM certificates. A block that is not a certificate is left out, and
     * so is a certificate that occurs twice.
     * @param string $text One or more PEM certificates.
     * @return array<int,string> The certificates in the order of the text, in normalized PEM form.
     */
    public static function parse(string $text): array
    {
        $certificates = array();
        if (preg_match_all(self::PEM_PATTERN, $text, $matches) === false) {
            return $certificates;
        }

        foreach ($matches[1] as $body) {
            $pem = SAMLMetadataImporter::formatCertificate($body);
            if (!self::isReadable($pem)) {
                continue;
            }
            $certificates[self::fingerprint($pem)] = $pem;
        }
        return array_values($certificates);
    }

    /**
     * Whether the text contains a block that is not a certificate, e.g. a truncated paste.
     * @param string $text One or more PEM certificates.
     */
    public static function hasInvalidBlock(string $text): bool
    {
        $blocks = preg_match_all(self::PEM_PATTERN, $text, $matches);
        $begins = substr_count($text, '-----BEGIN CERTIFICATE-----');
        if ($blocks !== $begins || ($blocks === 0 && trim($text) !== '')) {
            return true;
        }

        foreach ($matches[1] as $body) {
            if (!self::isReadable(SAMLMetadataImporter::formatCertificate($body))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether OpenSSL can read the PEM text as a certificate. A broken one makes OpenSSL raise a
     * warning, which Admidio's error handler turns into an exception.
     */
    private static function isReadable(string $pem): bool
    {
        try {
            return openssl_x509_read($pem) !== false;
        } catch (\Throwable) {
            return false;
        } finally {
            while (openssl_error_string() !== false) {
                // empty the OpenSSL error queue
            }
        }
    }

    /**
     * Join certificates into the text that is stored for a client.
     * @param array<int,string> $certificates PEM certificates.
     */
    public static function join(array $certificates): string
    {
        return implode("\n", $certificates);
    }

    /**
     * SHA-256 fingerprint of a certificate.
     */
    public static function fingerprint(string $pem): string
    {
        return openssl_x509_fingerprint($pem, 'sha256') ?: hash('sha256', $pem);
    }
}
