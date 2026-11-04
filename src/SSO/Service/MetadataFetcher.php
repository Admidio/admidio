<?php
namespace Admidio\SSO\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use RuntimeException;

/**
 * Fetches the metadata of a single sign-on partner from its metadata URL.
 *
 * The request is sent by the server, so every URL it reaches - the configured one and every
 * redirect target - has to pass the outbound request policy of SecurityUtils, which also pins
 * the connection to the validated address. HTTP redirects are therefore followed here, one hop
 * after the other, instead of by cURL: the SAML metadata interoperability profile requires them
 * to be honoured (Kantara IIP-MD02.b), but a redirect must not lead past the policy, e.g. to an
 * address inside the network of the installation.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class MetadataFetcher
{
    /** Number of redirects that are followed before the request fails */
    public const MAX_REDIRECTS = 5;

    /** Largest metadata document that is accepted, in bytes */
    public const MAX_RESPONSE_SIZE = 2 * 1024 * 1024;

    /** Status codes of a redirect to another URL that keeps the request a GET */
    private const REDIRECT_STATUS_CODES = array(301, 302, 303, 307, 308);

    /** Response headers of the last request, in lower case; for the caller of fetchConditional() */
    protected array $responseHeaders = array();

    /**
     * @param bool $allowPrivateNetwork Allow addresses inside the network of the installation and
     *                                  plain HTTP (setting sso_allow_private_network).
     */
    public function __construct(private bool $allowPrivateNetwork = false)
    {
    }

    /**
     * Fetch the metadata document of the given URL, following redirects.
     * @param string $url The metadata URL.
     * @return string The metadata document.
     * @throws Exception SYS_OUTBOUND_URL_NOT_ALLOWED if the URL or a redirect target is not allowed.
     * @throws RuntimeException if the metadata could not be fetched.
     */
    public function fetch(string $url): string
    {
        return (string) $this->fetchConditional($url)['body'];
    }

    /**
     * Fetch the metadata document of the given URL, following redirects, unless the server says it did
     * not change since the version the caller already has.
     * @param string $url The metadata URL.
     * @param string|null $etag The ETag of the version the caller has.
     * @param string|null $lastModified The Last-Modified date of the version the caller has.
     * @return array{body:string|null,etag:string|null,lastModified:string|null} The body is null if the
     *         server answered 304 Not Modified.
     * @throws Exception SYS_OUTBOUND_URL_NOT_ALLOWED if the URL or a redirect target is not allowed.
     * @throws RuntimeException if the metadata could not be fetched.
     */
    public function fetchConditional(string $url, ?string $etag = null, ?string $lastModified = null): array
    {
        $headers = array();
        if ($etag !== null && $etag !== '') {
            $headers[] = 'If-None-Match: ' . $etag;
        }
        if ($lastModified !== null && $lastModified !== '') {
            $headers[] = 'If-Modified-Since: ' . $lastModified;
        }

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $requestOptions = SecurityUtils::getOutboundRequestCurlOptions($url, $this->allowPrivateNetwork);
            if ($headers !== array()) {
                $requestOptions[CURLOPT_HTTPHEADER] = array_merge($requestOptions[CURLOPT_HTTPHEADER] ?? array(), $headers);
            }
            $this->responseHeaders = array();
            [$statusCode, $body, $location] = $this->request($url, $requestOptions);

            if (in_array($statusCode, self::REDIRECT_STATUS_CODES, true) && $location !== '') {
                $url = self::resolveLocation($url, $location);
                continue;
            }

            if ($statusCode === 304 && $headers !== array()) {
                return array('body' => null, 'etag' => $etag, 'lastModified' => $lastModified);
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                return array(
                    'body' => $body,
                    'etag' => $this->responseHeaders['etag'] ?? null,
                    'lastModified' => $this->responseHeaders['last-modified'] ?? null
                );
            }

            throw new RuntimeException('The metadata URL answered with HTTP status ' . $statusCode . '.');
        }

        throw new RuntimeException('The metadata URL redirected more than ' . self::MAX_REDIRECTS . ' times.');
    }

    /**
     * Resolve the Location header of a redirect against the URL that sent it (RFC 3986, section
     * 5.2, for the forms a redirect uses). A fragment is dropped, it never reaches the server.
     * @param string $baseUrl The URL that answered with the redirect.
     * @param string $location The value of its Location header.
     * @return string The absolute URL of the redirect target.
     */
    public static function resolveLocation(string $baseUrl, string $location): string
    {
        $location = trim(explode('#', $location, 2)[0]);

        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        $authority = $scheme . '://' . ($base['host'] ?? '') . (isset($base['port']) ? ':' . $base['port'] : '');
        if (str_starts_with($location, '/')) {
            return $authority . $location;
        }

        $basePath = $base['path'] ?? '/';
        if (str_starts_with($location, '?')) {
            return $authority . $basePath . $location;
        }

        $directory = substr($basePath, 0, (int) strrpos($basePath, '/') + 1);
        return $authority . ($directory === '' ? '/' : $directory) . $location;
    }

    /**
     * Send one GET request with the cURL options of the outbound request policy.
     * @param string $url The URL to request.
     * @param array<int,mixed> $requestOptions The cURL options from SecurityUtils.
     * @return array{0:int,1:string,2:string} Status code, body and Location header.
     * @throws RuntimeException if the request fails or the response is too large.
     */
    protected function request(string $url, array $requestOptions): array
    {
        $body = '';
        $location = '';
        $responseTooLarge = false;

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Could not initialize the metadata request.');
        }

        curl_setopt_array($curl, $requestOptions + array(
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HEADERFUNCTION => function ($curlHandle, string $header) use (&$location): int {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, 9));
                }
                if (preg_match('/^(ETag|Last-Modified):\s*(.*?)\s*$/i', $header, $matches)) {
                    $this->responseHeaders[strtolower($matches[1])] = $matches[2];
                }
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use (&$body, &$responseTooLarge): int {
                if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_SIZE) {
                    $responseTooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            }
        ));

        $result = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($responseTooLarge) {
            throw new RuntimeException('The metadata is larger than ' . self::MAX_RESPONSE_SIZE . ' bytes.');
        }
        if ($result === false) {
            throw new RuntimeException('The metadata request failed: ' . curl_error($curl));
        }

        return array($statusCode, $body, $location);
    }
}
