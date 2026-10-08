<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * RSA-SHA256 signatures over the exact bytes of a file.
 *
 * A signature is stored next to the signed file as **&lt;file&gt;.sig**, base64 encoded. The bytes
 * are signed as they are, without any canonicalisation, so what is verified is exactly what is
 * read.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Signature
{
    /**
     * Whether a signature over the data was made with the private key of a public key.
     * @param string $data
     * @param string $signature The content of the .sig file.
     * @param string $publicKey PEM.
     * @return bool
     */
    public static function verify(string $data, string $signature, string $publicKey): bool
    {
        self::requireOpenSsl();

        $bytes = base64_decode(trim($signature), true);
        $key = @openssl_pkey_get_public($publicKey);
        if ($bytes === false || $bytes === '' || $key === false) {
            return false;
        }

        return openssl_verify($data, $bytes, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Sign data, as the release builder does. Returns the content of the .sig file.
     * @param string $data
     * @param \OpenSSLAsymmetricKey|string $privateKey A key or its PEM.
     * @return string
     */
    public static function sign(string $data, \OpenSSLAsymmetricKey|string $privateKey): string
    {
        self::requireOpenSsl();

        if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new UpdaterException('The data could not be signed: ' . (string)openssl_error_string());
        }

        return base64_encode($signature) . "\n";
    }

    /**
     * @return void
     */
    private static function requireOpenSsl(): void
    {
        if (!extension_loaded('openssl')) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUIRED_EXTENSIONS', 'ext-openssl is not loaded.');
        }
    }
}
