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

    // Parse the request URI into the script name, the SSO type (saml or oidc) and the standlone endpoint name
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $scriptPath = $_SERVER['SCRIPT_NAME'];

    if (!is_string($requestPath)) {
        $sendResponse(new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Invalid request URI.'], 400));
    }
    if (!is_string($scriptPath) || !str_starts_with($requestPath, $scriptPath)) {
        $sendResponse(new JsonResponse(['error' => 'invalid_request', 'error_description' => 'SSO endpoint not found.'], 404));
    }

    $pathAfterScript = substr($requestPath, strlen($scriptPath));
    // make sure the request URL continues with a / after the script path:
    if ($pathAfterScript !== '' && !str_starts_with($pathAfterScript, '/')) {
        $sendResponse(new JsonResponse(['error' => 'invalid_request', 'error_description' => 'SSO endpoint not found.'], 404));
    }
    $endpointPath = trim($pathAfterScript, '/');

    if ($endpointPath === '') {
        $sendResponse(new JsonResponse(['error' => 'invalid_request', 'error_description' => 'SSO endpoint not found.'], 404));
    }

    $pathParts = explode('/', $endpointPath);
    $type = array_shift($pathParts);
    $endpoint = implode('/', $pathParts);

    if (!in_array($type, ['oidc', 'saml'], true) || $endpoint === '') {
        $sendResponse(new JsonResponse(['error' => 'invalid_request', 'error_description' => 'SSO endpoint not found.'], 404));
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
            $response = match ($endpoint) {
                'authorize'     => $oidcService->handleAuthorizationRequest(),
                'token'         => $oidcService->handleTokenRequest(),
                'userinfo'      => $oidcService->handleUserInfoRequest(),
                'jwks'          => $oidcService->handleJWKSRequest(),
                '.well-known/openid-configuration' => $oidcService->handleDiscoveryRequest(),
                'introspect'    => $oidcService->handleIntrospectionRequest(),
                'revoke'        => $oidcService->handleRevocationRequest(),
                'logout'        => $oidcService->handleLogoutRequest(),
                default         => new JsonResponse(
                        [
                            'error' => 'invalid_request',
                            'error_description' => 'Endpoint not found.'
                        ],
                        404
                    )
            };
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
    
            switch ($endpoint) {
                case 'metadata':
                    $samlService->handleMetadataRequest();
                    break;
                case 'sso':
                    $samlService->handleSSORequest();
                    break;
                case 'slo':
                    $samlService->handleSLORequest();
                    break;
        //        case 'attribute-query':
        //            $samlService->handleAttributeQuery();
        //            break;
                default:
                    $sendResponse(new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Endpoint not found.'], 404));
            }
        } catch (Throwable $e) {
            $logSSOException('An unexpected error occurred at a SAML endpoint.', $e);
            $sendResponse(new JsonResponse([
                    'error' => 'server_error', 'error_description' => 'The SAML request could not be processed.'
                ], 
                500
            ));
            // exit; // $sendResponse WILL EXIT -> no explicit exit needed
        }

    } else {
        $sendResponse(
            new JsonResponse(
                ['error' => 'invalid_request', 'error_description' => 'SSO endpoint not found or authorization protocoll not available.'],
                404
            )
        );
    }


} catch (Throwable $e) {
    handleException($e, true);
}

exit;
