<?php

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;

use Admidio\SSO\Service\OIDCService;
use Admidio\SSO\Service\SAMLService;

/**
 ***********************************************************************************************
 * SSO identity provider endpoints
 *
 * Handles the OpenID Connect and SAML identity provider endpoints.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
try {
    $rootPath = dirname(__DIR__, 2);

    require_once($rootPath . '/system/common.php');
    $requestUri = $_SERVER['REQUEST_URI'];

    /**
     * Send a PSR-7 response to the client.
     */
    $sendResponse = static function (ResponseInterface $response): never {
        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        echo (string) $response->getBody();
        exit;
    };
    /**
     * Log an unexpected SSO exception without returning its details to the client.
     */
    $logSSOException = static function (string $message, Throwable $exception) use ($gLogger): void {
        $gLogger->error(
            $message,
            [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]
        );
    };

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
            $logSSOException('Unable to initialize the OIDC connect service', $e);
            $sendResponse(new JsonResponse([
                   'error' => 'server_error',
                    'error_description' => 'The OpenID Connect service could not be initialized.'
                ],
                500
            ));
            // exit; // $sendResponse WILL EXIT -> no explicit exit needed
        }

        try {
            if (strpos($requestUri, '/oidc/authorize') !== false) {
                $response = $oidcService->handleAuthorizationRequest();
            } elseif (strpos($requestUri, '/oidc/token') !== false) {
                $response = $oidcService->handleTokenRequest();
            } elseif (strpos($requestUri, '/oidc/userinfo') !== false) {
                $response = $oidcService->handleUserInfoRequest();
            } elseif (strpos($requestUri, '/oidc/jwks') !== false) {
                $response = $oidcService->handleJWKSRequest();
            } elseif (strpos($requestUri, '/oidc/.well-known/openid-configuration') !== false) {
                $response = $oidcService->handleDiscoveryRequest();
            } elseif (strpos($requestUri, '/oidc/introspect') !== false) {
                $response = $oidcService->handleIntrospectionRequest();
            } elseif (strpos($requestUri, '/oidc/revoke') !== false) {
                $response = $oidcService->handleRevocationRequest();
            } elseif (strpos($requestUri, '/oidc/logout') !== false) {
                $response = $oidcService->handleLogoutRequest();
            } else {
                $response = new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Endpoint not found'], 404);
            }
            $sendResponse($response);

        } catch (Throwable $e) {
            $logSSOException('An unexpected error occurred at an OpenID Connect endpoint.', $e);
            $sendResponse(new JsonResponse([
                    'error' => 'server_error', 
                    'error_description' => 'The OpenID Connect request could not be processed.'
                ], 
                500
            ));
            // exit; // $sendResponse WILL EXIT -> no explicit exit needed
        }


    } elseif ($type === 'saml') {
        try {
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
                $sendResponse(new JsonResponse(['error' => 'Endpoint not found.'], 404));
            }
        } catch (Throwable $e) {
            $logSSOException('An unexpected error occurred at a SAML endpoint.', $e);
            $sendResponse(new JsonResponse([
                    'error' => 'The SAML request could not be processed.'
                ], 
                500
            ));
            // exit; // $sendResponse WILL EXIT -> no explicit exit needed
        }

    } else {
        $sendResponse(
            new JsonResponse(
                ['error' => 'SSO endpoint not found or authorization protocoll not available.'],
                404
            )
        );
    }


} catch (Throwable $e) {
    handleException($e, true);
}

exit;
