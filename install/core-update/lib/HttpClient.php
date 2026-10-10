<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

/**
 * Downloads for the updater: HTTPS only, with the certificate checked on every hop.
 *
 * Redirects are followed here and not by cURL, so that every hop is checked again: at most
 * MAX_REDIRECTS, each one HTTPS. There is no list of allowed hosts. A signed file is authentic
 * whichever mirror delivers it, and the updater never takes an unsigned URL from downloaded data.
 *
 * Every download has a size limit, counted on the bytes that actually arrive, and a connection is
 * given up when it stalls. A large file is fetched in parts with HTTP range requests, so that no
 * single request runs longer than the server allows (getRange()).
 *
 * The one exception to HTTPS is a local test server: with $gDebug, URLs below the configured
 * $gCoreUpdateServerUrl may use plain HTTP.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class HttpClient
{
    public const MAX_REDIRECTS = 5;

    /** Seconds to establish a connection. */
    private const CONNECT_TIMEOUT = 20;
    /** A transfer slower than LOW_SPEED_LIMIT bytes per second for LOW_SPEED_TIME seconds is given up. */
    private const LOW_SPEED_LIMIT = 1024;
    private const LOW_SPEED_TIME = 60;

    /**
     * The network access: fn(string $url, int $maxBytes, resource|null $file, array $headers):
     * array{status: int, location: string, body: string}. With a file, the body is written to it
     * instead of returned; $headers are additional request headers.
     * @var callable
     */
    private $transport;

    /**
     * @param string $plainHttpBase A URL prefix below which plain HTTP is allowed; only set in debug
     *                              mode, for a local test server.
     * @param callable|null $transport Replaces the network access in tests.
     */
    public function __construct(private readonly string $plainHttpBase = '', ?callable $transport = null)
    {
        $this->transport = $transport ?? self::curlTransport(...);
    }

    /**
     * Fetch a small file, for example a descriptor or a signature.
     * @param string $url
     * @param int $maxBytes
     * @return string
     * @throws UpdaterException
     */
    public function get(string $url, int $maxBytes): string
    {
        return $this->request($url, $maxBytes, null)['body'];
    }

    /**
     * Fetch a part of a file with a range request.
     *
     * A server that supports ranges answers with status 206 and exactly that part. One that does
     * not answers with 200 and the whole file, which is then limited to $maxTotal bytes; the caller
     * decides what to do with it.
     * @param string $url
     * @param int $offset The first byte.
     * @param int $length The number of bytes.
     * @param int $maxTotal The size limit of the whole file, for a server that ignores the range.
     * @return array{status: int, body: string}
     * @throws UpdaterException
     */
    public function getRange(string $url, int $offset, int $length, int $maxTotal): array
    {
        $response = $this->request($url, max($length, $maxTotal), null,
            array('Range: bytes=' . $offset . '-' . ($offset + $length - 1)));

        if ($response['status'] === 206 && strlen($response['body']) > $length) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', 'The server sent more than the requested range.', array(''));
        }

        return $response;
    }

    /**
     * Download a file.
     * @param string $url
     * @param string $targetFile
     * @param int $maxBytes
     * @return void
     * @throws UpdaterException
     */
    public function download(string $url, string $targetFile, int $maxBytes): void
    {
        $handle = @fopen($targetFile, 'wb');
        if ($handle === false) {
            throw new UpdaterException('SYS_CORE_UPDATE_FILES_NOT_WRITABLE');
        }

        try {
            $this->request($url, $maxBytes, $handle);
        } catch (UpdaterException $exception) {
            fclose($handle);
            @unlink($targetFile);
            throw $exception;
        }
        fclose($handle);
    }

    /**
     * Refuse a URL the updater must not request.
     * @param string $url
     * @return void
     * @throws UpdaterException SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID
     */
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);
        $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
        $plainHttpAllowed = $scheme === 'http' && $this->plainHttpBase !== '' && str_starts_with($url, $this->plainHttpBase);

        if (!is_array($parts) || ($scheme !== 'https' && !$plainHttpAllowed) || ($parts['host'] ?? '') === ''
            || isset($parts['user']) || isset($parts['pass'])) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_URL_INVALID', 'Refused URL: ' . $url);
        }
    }

    /**
     * The absolute URL of a redirect target.
     * @param string $base The URL that answered with the redirect.
     * @param string $location The Location header.
     * @return string
     */
    public static function resolve(string $base, string $location): string
    {
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($location, '//')) {
            return $parts['scheme'] . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $path = $parts['path'] ?? '/';
        $directory = substr($path, 0, (int)strrpos($path, '/') + 1);

        return $origin . ($directory === '' ? '/' : $directory) . $location;
    }

    /**
     * Request a URL and follow its redirects.
     * @param string $url
     * @param int $maxBytes
     * @param resource|null $file
     * @param array<int,string> $headers Additional request headers, sent on every hop.
     * @return array{status: int, body: string} The body is empty when it was written to the file.
     */
    private function request(string $url, int $maxBytes, $file, array $headers = array()): array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $this->assertAllowed($url);

            if ($file !== null) {
                ftruncate($file, 0);
                rewind($file);
            }
            $response = ($this->transport)($url, $maxBytes, $file, $headers);
            $status = (int)$response['status'];

            if ($status >= 300 && $status < 400 && $response['location'] !== '') {
                $url = self::resolve($url, $response['location']);
                continue;
            }
            if ($status < 200 || $status >= 300) {
                throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', 'HTTP status ' . $status . ' for ' . $url, array('HTTP ' . $status));
            }

            return array('status' => $status, 'body' => (string)$response['body']);
        }

        throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', 'More than ' . self::MAX_REDIRECTS . ' redirects.', array(''));
    }

    /**
     * One request with cURL, without following redirects.
     * @param string $url
     * @param int $maxBytes
     * @param resource|null $file
     * @param array<int,string> $headers
     * @return array{status: int, location: string, body: string}
     */
    private static function curlTransport(string $url, int $maxBytes, $file, array $headers = array()): array
    {
        if (!extension_loaded('curl')) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_REQUIRED_EXTENSIONS', 'ext-curl is not loaded.');
        }

        $body = '';
        $received = 0;
        $location = '';
        $tooLarge = false;

        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_LOW_SPEED_LIMIT => self::LOW_SPEED_LIMIT,
            CURLOPT_LOW_SPEED_TIME => self::LOW_SPEED_TIME,
            CURLOPT_USERAGENT => 'Admidio core updater',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$location): int {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, strlen('Location:')));
                }

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $data) use (&$body, &$received, &$tooLarge, $maxBytes, $file): int {
                $received += strlen($data);
                if ($received > $maxBytes) {
                    $tooLarge = true;

                    return 0; // ends the transfer
                }
                if ($file !== null) {
                    return (int)fwrite($file, $data);
                }
                $body .= $data;

                return strlen($data);
            }
        ));

        $success = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($tooLarge) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', 'The download is larger than ' . $maxBytes . ' bytes: ' . $url, array(''));
        }
        if ($success !== true) {
            throw UpdaterException::withDetail('SYS_CORE_UPDATE_DOWNLOAD_FAILED', $error . ': ' . $url, array($error));
        }

        return array('status' => $status, 'location' => $location, 'body' => $body);
    }
}
