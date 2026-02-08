<?php
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
require_once(__DIR__ . '/../../version.php');

header('Content-Type: application/json; charset=utf-8');

validateApiKey();

$admidioVersion = null;
if (defined('ADMIDIO_VERSION_TEXT')) {
    $admidioVersion = ADMIDIO_VERSION_TEXT;
} elseif (defined('ADMIDIO_VERSION')) {
    $admidioVersion = ADMIDIO_VERSION;
}

$residentsVersion = defined('RESIDENTS_VERSION') ? RESIDENTS_VERSION : null;

echo json_encode([
    'admidio_version' => $admidioVersion,
    'residents_version' => $residentsVersion,
]);
