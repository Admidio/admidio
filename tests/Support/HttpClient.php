<?php

namespace Admidio\Tests\Support;

use CurlHandle;
use RuntimeException;

/** Cookie state belongs to one simulated browser. Redirects stay visible to assertions. */
final class HttpClient
{
    private CurlHandle $handle;

    public function __construct(private readonly string $url)
    {
        $this->handle = curl_init();
    }

    /** @param array<string,mixed> $form Form fields, including nested arrays. */
    public function request(string $path, array $form = [], string $method = 'GET'): HttpResponse
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new RuntimeException('HTTP tests accept only paths within the test installation.');
        }
        $headers = [];
        // curl_reset retains the handle's in-memory cookie engine, including HttpOnly cookies.
        curl_reset($this->handle);
        curl_setopt_array($this->handle, [
            CURLOPT_URL => $this->url . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
            CURLOPT_PROXY => '',
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_USERAGENT => 'Admidio HTTP Tests',
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))][] = trim($value);
                }
                return strlen($line);
            },
        ]);
        if ($method === 'POST') {
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, http_build_query($form));
        } elseif ($method !== 'GET') {
            curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, $method);
        }
        $body = curl_exec($this->handle);
        if ($body === false) {
            throw new RuntimeException('HTTP request failed: ' . curl_error($this->handle));
        }
        return new HttpResponse((int) curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE), $headers, $body);
    }
}
