<?php


use Admidio\SSO\Service\OIDCService;
use Admidio\SSO\Service\SAMLService;

/**
 ***********************************************************************************************
 * Event list
 *
 * Plugin that lists the latest events in a slim interface and
 * can thus be ideally used in an overview page.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
try {
    $rootPath = dirname(__DIR__, 2);
    $pluginFolder = basename(__DIR__);

    require_once($rootPath . '/system/common.php');
    $requestUri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];

    $type = '';
    if (strpos($requestUri, '/saml/') !== false) {
        $type = 'saml';
    } elseif (strpos($requestUri, '/oidc/') !== false) {
        $type = 'oidc';
    }

    // Login checks will be done in the individual endpoint handler functions!

    if ($type === 'oidc') {
        $oidcService = new OIDCService($gDb, $gCurrentUser);

        if (strpos($requestUri, '/oidc/authorize') !== false && $method === 'GET') {
            $oidcService->handleAuthorizationRequest();
        } elseif (strpos($requestUri, '/oidc/token') !== false && $method === 'POST') {
            $oidcService->handleTokenRequest();
        } elseif (strpos($requestUri, '/oidc/userinfo') !== false && $method === 'GET') {
            $oidcService->handleUserInfoRequest();
        } elseif (strpos($requestUri, '/oidc/.well-known/jwks.json') !== false && $method === 'GET') {
            $oidcService->handleJWKSRequest();
        } elseif (strpos($requestUri, '/oidc/.well-known/openid-configuration') !== false && $method === 'GET') {
            $oidcService->handleDiscoveryRequest();
        } elseif (strpos($requestUri, '/oidc/introspect') !== false && $method === 'POST') {
            $oidcService->handleIntrospectionRequest();
        } elseif (strpos($requestUri, '/oidc/revoke') !== false && $method === 'POST') {
            $oidcService->handleRevocationRequest();
        } elseif (strpos($requestUri, '/oidc/logout') !== false && $method === 'GET') {
            $oidcService->handleLogoutRequest();
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Endpoint not found']);
        }
     
    } elseif ($type === 'saml') {

        $samlService = new SAMLService($gDb, $gCurrentUser);

        if (strpos($requestUri, '/saml/metadata') !== false) {
            $samlService->handleMetadataRequest();
        } elseif (strpos($requestUri, '/saml/sso') !== false) {
            $samlService->handleSSORequest();
        } elseif (strpos($requestUri, '/saml/slo') !== false) {
            $samlService->handleSLORequest();
//        } elseif (strpos($requestUri, '/saml/attribute-query') !== false) {
//            $samlService->handleAttributeQuery();
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Endpoint not found']);
        }
        
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'URL or authorization protocol not available']);
    }


} catch (Throwable $e) {
    echo $e->getMessage();
}

exit;
