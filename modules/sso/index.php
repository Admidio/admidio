<?php

use Laminas\Diactoros\Response\JsonResponse;    

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
        try {
            $oidcService = new OIDCService($gDb, $gCurrentUser);
            $oidcService->setupService();
        } catch (Exception $e) {
            echo json_encode(['error' => 'OIDC service setup failed: ' . $e->getMessage()]);
            exit;
        }

        try {
            $response = null;
            if (strpos($requestUri, '/oidc/authorize') !== false && $method === 'GET') {
                $response = $oidcService->handleAuthorizationRequest();
            } elseif (strpos($requestUri, '/oidc/token') !== false && $method === 'POST') {
                $response = $oidcService->handleTokenRequest();
            } elseif (strpos($requestUri, '/oidc/userinfo') !== false) {
                $response = $oidcService->handleUserInfoRequest();
            } elseif (strpos($requestUri, '/oidc/jwks') !== false && $method === 'GET') {
                $response = $oidcService->handleJWKSRequest();
            } elseif (strpos($requestUri, '/oidc/.well-known/openid-configuration') !== false && $method === 'GET') {
                $response = $oidcService->handleDiscoveryRequest();
            } elseif (strpos($requestUri, '/oidc/introspect') !== false && $method === 'POST') {
                $response = $oidcService->handleIntrospectionRequest();
            } elseif (strpos($requestUri, '/oidc/revoke') !== false && $method === 'POST') {
                $response = $oidcService->handleRevocationRequest();
            } elseif (strpos($requestUri, '/oidc/logout') !== false && $method === 'GET') {
                $response = $oidcService->handleLogoutRequest();
            } else {
                $response = new JsonResponse(['error' => 'Endpoint not found'], 404);
            }
            if (!empty($response)) {
                http_response_code($response->getStatusCode());
                foreach ($response->getHeaders() as $name => $values) {
                    foreach ($values as $value) {
                        header(sprintf('%s: %s', $name, $value), false);
                    }
                }
                $body = (string) $response->getBody();
                echo (string) $response->getBody();
                exit;
            }

        } catch (Exception $e) {
            echo json_encode(['error' => 'OIDC Error in Admidio: ' . $e->getMessage()]);
            exit;
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
