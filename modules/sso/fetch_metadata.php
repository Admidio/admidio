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

    $url = trim(admFuncVariableIsValid($_POST, 'url', 'string'));

    // The metadata URL is requested by the server, so it must be a public HTTPS target.
    // The returned options pin cURL to the address that was validated here.
    try {
        $requestOptions = SecurityUtils::getOutboundRequestCurlOptions(
            $url,
            $gSettingsManager->getBool('sso_allow_private_network')
        );
    } catch (Exception $e) {
        http_response_code(400);
        exit;
    }

    $metadata = '';
    $responseTooLarge = false;
    $maximumResponseSize = 2 * 1024 * 1024;

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Could not initialize the metadata request.');
    }

    curl_setopt_array($curl, $requestOptions + array(
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
