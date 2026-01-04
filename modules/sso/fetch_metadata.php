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

    $url = filter_var($_GET['url'], FILTER_VALIDATE_URL);
    if (!$url) {
        http_response_code(400);
        echo "Invalid URL";
        exit;
    }

    // Fetch metadata from external server
    $metadata = file_get_contents($url);
    if ($metadata === false) {
        http_response_code(500);
        echo "Failed to fetch metadata";
        exit;
    }

    // Allow CORS only for your frontend
    header("Access-Control-Allow-Origin: " . ADMIDIO_URL);
    header("Content-Type: application/xml");

    echo $metadata;
} catch (Throwable $e) {
    handleException($e);
}
