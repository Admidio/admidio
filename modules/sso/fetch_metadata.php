<?php

try {
    require_once(__DIR__ . '/../../system/common.php');
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if (!isset($_GET['url'])) {
        http_response_code(400);
        echo "Missing URL parameter";
        exit;
    }

    $rawUrl = $_GET['url'];

    // Only allow https:// scheme
    if (!preg_match('#^https://#i', $rawUrl)) {
        http_response_code(400);
        echo "Only HTTPS URLs are permitted";
        exit;
    }

    $url = filter_var($rawUrl, FILTER_VALIDATE_URL);
    if (!$url) {
        http_response_code(400);
        echo "Invalid URL";
        exit;
    }

    // Resolve hostname and block internal/private IP ranges
    $host = parse_url($url, PHP_URL_HOST);
    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        http_response_code(400);
        echo "URL resolves to a private or reserved IP address";
        exit;
    }

    // Fetch metadata from external server
    $ch = curl_init($url);
    $resolve = ["$host:443:$ip"];
    curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $metadata = curl_exec($ch);
    curl_close($ch);

    // Allow CORS only for your frontend
    header("Access-Control-Allow-Origin: " . ADMIDIO_URL);
    header("Content-Type: application/xml");

    echo $metadata;
} catch (Throwable $e) {
    handleException($e);
}
