<?php

use Admidio\Infrastructure\Utils\SecurityUtils;

try {
    require_once(__DIR__ . '/../../system/common.php');

    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    // metadata loading through this script must also have correct CSRF token, otherwise arbitrary l
    $csrfToken = admFuncVariableIsValid($_POST, 'adm_csrf_token', 'string');
    SecurityUtils::validateCsrfToken($csrfToken);

    $rawUrl = trim(admFuncVariableIsValid($_POST, 'url', 'string'));
    $url = filter_var($rawUrl, FILTER_VALIDATE_URL);

    if ($url === false || strcasecmp((string) parse_url($url, PHP_URL_SCHEME), 'https') !== 0) {
        http_response_code(400);
        exit;
    }

    // Do not allow basic auth on the metadata URL or fragments
    if (parse_url($url, PHP_URL_USER) !== null
        || parse_url($url, PHP_URL_PASS) !== null
        || parse_url($url, PHP_URL_FRAGMENT) !== null
    ) {
        http_response_code(400);
        exit;
    }

    $host = parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT) ?? 443;

    if (!is_string($host) || $host === '' || $port < 1 || $port > 65535) {
        http_response_code(400);
        exit;
    }

    // Pin cURL to one of the addresses that was validated here. Force IPv4 so
    // cURL cannot fall back to an unvalidated AAAA record after this check.
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        $addresses = array($host);
    } else {
        $addresses = gethostbynamel($host);
    }

    if ($addresses === false || count($addresses) === 0) {
        http_response_code(400);
        exit;
    }

    // Disallow private IPs, among others
    foreach ($addresses as $address) {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            http_response_code(400);
            exit;
        }
    }

    $metadata = '';
    $responseTooLarge = false;
    $maximumResponseSize = 2 * 1024 * 1024;

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Could not initialize the metadata request.');
    }

    curl_setopt_array($curl, array(
        CURLOPT_RESOLVE => array($host . ':' . $port . ':' . $addresses[0]),
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use (
            &$metadata,
            &$responseTooLarge,
            $maximumResponseSize
        ): int {
            if (strlen($metadata) + strlen($chunk) > $maximumResponseSize) {
                $responseTooLarge = true;
                return 0;
            }

            $metadata .= $chunk;
            return strlen($chunk);
        }
    ));

    $result = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($result === false || $responseTooLarge || $statusCode < 200 || $statusCode >= 300) {
        http_response_code(502);
        exit;
    }

    header('Content-Type: application/xml; charset=UTF-8');
    header('Cache-Control: no-store');
    echo $metadata;
} catch (Throwable $e) {
    handleException($e, true);
}
