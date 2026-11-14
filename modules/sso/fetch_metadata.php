<?php

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\SSO\Service\MetadataFetcher;

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

    $url = trim(admFuncVariableIsValid($_POST, 'url', 'url'));

    // The metadata URL and every redirect target are requested by the server, so they must pass
    // the outbound request policy: a public HTTPS target unless private networks are allowed.
    try {
        $metadata = (new MetadataFetcher($gSettingsManager->getBool('sso_allow_private_network')))->fetch($url);
    } catch (Exception $e) {
        http_response_code(400);
        exit;
    } catch (RuntimeException $e) {
        $gLogger->warning('Could not load SAML metadata.', array('url' => $url, 'reason' => $e->getMessage()));
        http_response_code(502);
        exit;
    }

    header('Content-Type: application/xml; charset=UTF-8');
    header('Cache-Control: no-store');
    echo $metadata;
} catch (Throwable $e) {
    handleException($e, true);
}
