<?php
namespace Admidio\SSO\Service;

use League\OAuth2\Server\CryptKey;

use Admidio\SSO\Repository\AccessTokenRepository;
use Admidio\SSO\Repository\ClientRepository;
use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\SSO\Repository\ScopeRepository;
use Admidio\SSO\Repository\UserRepository;


use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Stream;

use Psr\Http\Message\ServerRequestInterface; // Needed for PSR-7 compliance
use Psr\Http\Message\ResponseInterface; // Ensures correct return types for responses
use Psr\Http\Server\RequestHandlerInterface; // May be useful for middleware in the future
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Exception\OAuthServerException;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Users\Entity\User;
use Admidio\SSO\Entity\Key;
use Admidio\SSO\Entity\UserEntity;
use Admidio\SSO\Entity\SSOClient;
use Admidio\SSO\Entity\OIDCClient;

/** ***************************************************************************
 * Properly handle scopes and claims
 *    OIDC Scopes: https://openid.net/specs/openid-connect-core-1_0.html#ScopeClaims
 *    OIDC Claims: https://openid.net/specs/openid-connect-core-1_0.html#StandardClaims
 * 
 * Relevant Scopes:
 *   - openid
 *   - profile: name, family_name, given_name, middle_name, nickname, preferred_username, profile, picture, website, gender, birthdate, zoneinfo, locale, updated_at
 *   - email: email, email_verified
 *   - address: 
 *   - phone: phone_number, phone_number_verified Claims.
 *   - groups
 *   - roles
 * 
 * Relevant Claims:
 *   - sub, 
 *   - name, given_name, family_name, middle_name, nickname, 
 *   - preferred_username, profile, picture,
 *   - website, email, email_verified, 
 *   - gender, birthdate, 
 *   - zoneinfo, locale, 
 *   - phone_number, phone_number_verified, 
 *   - address [JSON: formatted, street_address, locality, region, postal_code, country]
 *   - updated_at
 */


class OIDCService extends SSOService {
    private AuthorizationServer $authServer;
    private ResourceServer $resourceServer;
    private AccessTokenRepository $accessTokenRepository;

    private string $issuerURL;
    private string $authorizationEndpoint;
    private string $tokenEndpoint;
    private string $userinfoEndpoint;
    private string $discoveryURL;

    private bool $isServiceSetup = false;
    
    public function __construct($db, $currentUser) {//, ResourceServer $resourceServer) {
        global $gSettingsManager;

        parent::__construct($db, $currentUser);
        $this->columnPrefix = 'ocl';
        $this->table = TBL_OIDC_CLIENTS;

        $this->issuerURL = $gSettingsManager->get('sso_oidc_issuer_url') ?: ADMIDIO_URL;
        $this->authorizationEndpoint = $this->issuerURL  . "/adm_program/modules/sso/index.php/oidc/authorize";
        $this->tokenEndpoint = $this->issuerURL . "/adm_program/modules/sso/index.php/oidc/token";
        $this->userinfoEndpoint = $this->issuerURL . "/adm_program/modules/sso/index.php/oidc/userinfo";
        $this->discoveryURL = $this->issuerURL . "/adm_program/modules/sso/index.php/oidc/.well-known/openid-configuration";

    }

    protected function saveCustomClientSettings(array $formValues, SSOClient $client) {
        if (array_key_exists('new_ocl_client_secret', $formValues)) {
            // A new client secret -> store the hashed value in the database!
            $client->setValue(
                $client->getColumnPrefix().'_client_secret', 
                password_hash($formValues['new_ocl_client_secret'], PASSWORD_DEFAULT)
            );
        }
    }


    protected function getRolesRightName(): string {
        return 'sso_oidc_access';
    }

    public function initializeClientObject($database): ?SSOClient {
        return new OIDCClient($database);
    }

    /**
     * Return the issuer URL (Base URL of the Admidio installation)
     * @return string
     */
    public function getIssuerURL() {
        return $this->issuerURL;
    }
    /**
     * Return the authorization endpoint
     * @return string
     */
    public function getAuthorizationEndpoint() {
        return $this->authorizationEndpoint;
    }
    /**
     * Return the token endpoint
     * @return string
     */
    public function getTokenEndpoint() {
        return $this->tokenEndpoint;
    }
    /**
     * Return the userinfo endpoint
     * @return string
     */
    public function getUserinfoEndpoint() {
        return $this->userinfoEndpoint;
    }
    /**
     * Return the discovery URL
     * @return string
     */
    public function getDiscoveryURL() {
        return $this->discoveryURL;
    }

    /**
     * Generate user info based on the requested scopes and the user object.\
     * 
     *  Relevant Scopes and their claims:
     *   - openid: sub
     *   - profile: name, family_name, given_name, middle_name, nickname, preferred_username, profile, picture, website, gender, birthdate, zoneinfo, locale, updated_at
     *   - email: email, email_verified
     *   - address: address [JSON array: formatted, street_address, locality, region, postal_code, country]
     *   - phone: phone_number, phone_number_verified Claims.
     *   - [groups]
     *   - [roles]
     * 
     * @param SSOClient $client The client requesting the user info
     * @param User $user The user object containing the user data
     * @param array $scopes The requested scopes
     * @return array The user info as an associative array  
     */
    public function generateUserInfo(SSOClient $client, User $user, array $scopes) : array {
        $userInfo = [];

        if (in_array('openid', $scopes)) {
            $userIDfield = $client->getValue($client->getColumnPrefix() . '_userid_field');
            $userInfo['sub'] = $user->getValue($userIDfield);
            $userInfo['uuid'] = $user->getValue('usr_uuid');
        }
        if (in_array('profile', $scopes)) {
            $userInfo['name'] = $user->readableName();
            $userInfo['family_name'] = $user->getValue('LAST_NAME');
            $userInfo['given_name'] = $user->getValue('FIRST_NAME');
            $userInfo['preferred_username'] = $user->getValue('usr_login_name');
            // $userInfo['profile'] = $user->getValue('');
            // $userInfo['picture'] = $user->getValue('');
            $userInfo['website'] = $user->getValue('WEBSITE');
            $userInfo['gender'] = $user->getValue('GENDER');
            $userInfo['birthdate'] = $user->getValue('BIRTHDAY');
        }

        if (in_array('address', $scopes)) {
            $userInfo['address'] = [
                'formatted' => $user->getValue('ADDRESS'),
                'street_address' => $user->getValue('STREET'),
                'locality' => $user->getValue('CITY'),
                'region' => $user->getValue('BUNDESLAND'),
                'postal_code' => $user->getValue('POSTCODE'),
                'country' => $user->getValue('COUNTRY')
            ];
            $userInfo['address'] = array_filter($userInfo['address'], function ($value) {
                return $value !== '' && $value !== null;
            });
        }
        if (in_array('phone', $scopes)) {
            $userInfo['phone_number'] = $user->getValue('PHONE');
            $userInfo['phone_number'] = $user->getValue('MOBILE');
        }

        if (in_array('email', $scopes)) {
            $userInfo['email'] = $user->getValue("EMAIL");
        }

        // TODO_RK: Add explicitly selected fields from the client config!
        // TODO_RK: Add groups and roles from the client config!

        $userInfo = array_filter($userInfo, function ($value) {
            return $value !== '' && $value !== null && $value !== [];
        });

        return $userInfo;
    }


    /**
     * Returns a PSR-7 request for the OAuth2 server while ensuring Admidio compatibility
     */
    private function getRequest() {
        // Ensure Admidio’s global request variables are used for internal logic
        $serverRequest = ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);

        // Fix known issues with certain clients>
        // 1. Dokuwiki sends OAuth as the authorization header => Replace OAuth with Bearer
        if (str_contains($serverRequest->getHeaderLine('user-agent'), 'DokuWiki')) {
            if ($serverRequest->hasHeader('authorization')) {
                $serverRequest = $serverRequest->withHeader('authorization', str_replace('OAuth ', 'Bearer ', $serverRequest->getHeaderLine('Authorization')));
            }
        }

        return $serverRequest;
    }


    public function setupService() {
        global $gSettingsManager;

        // Init our repositories
        $clientRepository = new ClientRepository($this->db);            // instance of ClientRepositoryInterface
        $scopeRepository = new ScopeRepository($this->db);                        // instance of ScopeRepositoryInterface
        $accessTokenRepository = new AccessTokenRepository($this->db);  // instance of AccessTokenRepositoryInterface
        $authCodeRepository = new AuthCodeRepository($this->db);        // instance of AuthCodeRepositoryInterface
        $userRepository = new UserRepository($this->db); // instance of UserRepositoryInterface // TODO_RK: Add user ID field and allowed Roles!
        $refreshTokenRepository = new RefreshTokenRepository($this->db); // instance of RefreshTokenRepositoryInterface

        // Keep references to the relevant objects for later use
        $this->accessTokenRepository = $accessTokenRepository;

        // Private key for signing
        $privateKeyID = $gSettingsManager->get('sso_oidc_signing_key');
        $privateKeyObject = new Key($this->db, $privateKeyID);
        $privateKey = new CryptKey($privateKeyObject->getValue('key_private'));
        $publicKey = new CryptKey($privateKeyObject->getValue('key_public'));

        // The encryption key is used to store tokens encrypted to the DB.
        $encryptionKey = $gSettingsManager->get('sso_oidc_encryption_key');
        if (empty($encryptionKey)) {
            $encryptionKey = base64_encode(random_bytes(32));
            $gSettingsManager->set('sso_oidc_encryption_key', $encryptionKey);
        }

        // Setup the authorization server
        $server = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            $privateKey,
            $encryptionKey
        );


        /* ***********************************************************************
         * Auth Code Grant
         */
        $grant = new \League\OAuth2\Server\Grant\AuthCodeGrant(
             $authCodeRepository,
             $refreshTokenRepository,
             new \DateInterval('PT10M') // authorization codes will expire after 10 minutes
        );
     
        $grant->setRefreshTokenTTL(new \DateInterval('P1M')); // refresh tokens will expire after 1 month
        // Enable the authentication code grant on the server
        $server->enableGrantType(
            $grant,
            new \DateInterval('PT1H') // access tokens will expire after 1 hour
        );


        /* ***********************************************************************
         * Client Credentials Grant
         */
        $server->enableGrantType(
            new \League\OAuth2\Server\Grant\ClientCredentialsGrant(),
            new \DateInterval('PT1H') // access tokens will expire after 1 hour
        );
        

        /* ***********************************************************************
        * Resource owner Password Grant
        */
        $grant = new \League\OAuth2\Server\Grant\PasswordGrant(
            $userRepository,
            $refreshTokenRepository
        );
        $grant->setRefreshTokenTTL(new \DateInterval('P1M')); // refresh tokens will expire after 1 month
        $server->enableGrantType(
            $grant,
            new \DateInterval('PT1H') // access tokens will expire after 1 hour
        );        
        

        /* ***********************************************************************
        * Implicit Grant
        */
        // Enable the implicit grant on the server
        $server->enableGrantType(
            new \League\OAuth2\Server\Grant\ImplicitGrant(new \DateInterval('PT1H')),
            new \DateInterval('PT1H') // access tokens will expire after 1 hour
        );
        

        /* ***********************************************************************
        * RefreshToken Grant
        */
        $grant = new \League\OAuth2\Server\Grant\RefreshTokenGrant($refreshTokenRepository);
        $grant->setRefreshTokenTTL(new \DateInterval('P1M')); // new refresh tokens will expire after 1 month
        
        // Enable the refresh token grant on the server
        $server->enableGrantType(
            $grant,
            new \DateInterval('PT1H') // new access tokens will expire after an hour
        );



        /* ***********************************************************************
        * Various other setup things
        */

        // TODO_RK: Handle failed authentications, e.g. after n number of attemps, block the client, etc.
        $server->getEmitter()->addListener(
            'client.authentication.failed',
            function (\League\OAuth2\Server\RequestEvent $event) {
                // TODO_RK
            }
        );
        $server->getEmitter()->addListener(
            'user.authentication.failed',
            function (\League\OAuth2\Server\RequestEvent $event) {
                // TODO_RK
            }
        );


        $this->authServer = $server;


        // Set up resource server and add middleware to check access token validity:

        $resourceServer = new ResourceServer(
            $accessTokenRepository,
            $publicKey
        );
        new \League\OAuth2\Server\Middleware\ResourceServerMiddleware($resourceServer);
        $this->resourceServer = $resourceServer;

        $this->isServiceSetup = true;
    }

    public function handleAuthorizationRequest(): ResponseInterface {
        global $gProfileFields, $gSettingsManager, $gValidLogin, $gCurrentUserId;

        if ($gSettingsManager->get('sso_oidc_enabled') !== '1') {
            throw new \Exception("SSO OIDC is not enabled");
        }

        $request = $this->getRequest();
        $response = new Response();
        try {
            if (!$this->isServiceSetup) {
                $this->setupService();
            }
    
            // Validate the HTTP request and return an AuthorizationRequest object.
            $authRequest = $this->authServer->validateAuthorizationRequest($request);
            $client = $authRequest->getClient();
            
            // Redirect the user to a login endpoint if not logged in yet.
            if (!$gValidLogin) {
                $this->showSSOLoginForm($client);
                // exit;
            }
            
            // Once the user has logged in set the user on the AuthorizationRequest
            $authRequest->setUser(new UserEntity($this->db, $gProfileFields, $client->getUserIdField(), $gCurrentUserId));
            
            // At this point you should redirect the user to an authorization page.
            // This form will ask the user to approve the client and the scopes requested.
            // TODO_RK: Implement the authorization page and redirect to it.
            // For now we will just approve the request automatically.
            
            // Once the user has approved or denied the client update the status
            // (true = approved, false = denied)
            $authRequest->setAuthorizationApproved(true);
            
            // Return the HTTP redirect response
            return $this->authServer->completeAuthorizationRequest($authRequest, $response);
            
        } catch (OAuthServerException $exception) {
        
            // All instances of OAuthServerException can be formatted into a HTTP response
            return $exception->generateHttpResponse($response);
            
        } catch (\Exception $exception) {
        
            // Unknown exception
            $body = new Stream(fopen('php://temp', 'r+'));
            $body->write($exception->getMessage());
            return $response->withStatus(500)->withBody($body);
            
        }
    }

    public function handleTokenRequest() {
        $request = $this->getRequest();
        $response = new Response();
        try {
            if (!$this->isServiceSetup) {
                $this->setupService();
            }
            // Try to respond to the request
            return $this->authServer->respondToAccessTokenRequest($request, $response);

        } catch (OAuthServerException $exception) {
            // All instances of OAuthServerException can be formatted into a HTTP response
            return $exception->generateHttpResponse($response);
        } catch (\Exception $exception) {
            // Unknown exception
            $body = new Stream(fopen('php://temp', 'r+'));
            $body->write($exception->getMessage());
            return $response->withStatus(500)->withBody($body);
        }
    }

    public function handleUserInfoRequest() {
        $request = $this->getRequest();
        $response = new Response();
        try {
            if (!$this->isServiceSetup) {
                $this->setupService();
            }
            // Validate the request (throws exception if token is invalid)
            $request = $this->resourceServer->validateAuthenticatedRequest($request);

            // Get the user ID (sub claim) from the token
            $userId = $request->getAttribute('oauth_user_id');
            $tokenId = $request->getAttribute('oauth_access_token_id');
            $tokenUserId = $this->accessTokenRepository->getUserIdByAccessToken($tokenId);
            $token = $this->accessTokenRepository->getToken($tokenId);

            if ($tokenUserId !== $userId) {
                return new JsonResponse(['error' => 'access_denied', 'message' => 'Token does not match the authenticated user'], 403);
            }
            if ($token->getExpiryDateTime() < new \DateTimeImmutable()) {
                return new JsonResponse(['error' => 'access_denied', 'message' => 'Token expired'], 403);
            }
            if ($this->accessTokenRepository->isTokenRevoked($tokenId)) {
                return new JsonResponse(['error' => 'access_denied', 'message' => 'Token was revoked'], 403);
            }

            $tokenScopes = $token->getScopes();
            $tokenScopes = array_map(function($scope) {
                return $scope->getIdentifier();
            }, $tokenScopes);
            $scopes = $request->getAttribute('oauth_scopes');

            // Only accept scopes that are authorized for the token, even if the request contains more scopes
            $scopes = array_intersect($scopes, $tokenScopes);
            if (count($scopes) === 0) {
                return new JsonResponse(['error' => 'access_denied', 'message' => 'No valid scopes'], 403);
            } 

            $userinfo = $this->generateUserInfo($token->getClient(), $token->getUser(), $scopes);

            return new JsonResponse($userinfo);
        } catch (OAuthServerException $exception) {
            // All instances of OAuthServerException can be formatted into a HTTP response
            return $exception->generateHttpResponse($response);
        } catch (\Exception $exception) {
            // Unknown exception
            $body = new Stream(fopen('php://temp', 'r+'));
            $body->write($exception->getMessage());
            return $response->withStatus(500)->withBody($body);
        }
    }

    public function handleJWKSRequest() {
        global $gSettingsManager;

        if (!$this->isServiceSetup) {
            $this->setupService();
        }
    
        // Private key and Certificate for signatures
        $signatureKeyID = $gSettingsManager->get('sso_oidc_signing_key');
        $signatureKey = new Key($this->db, $signatureKeyID);
        
        $idpPublicKeyPem = $signatureKey->getValue('key_public');
        $keyDetails = openssl_pkey_get_details(openssl_pkey_get_public($idpPublicKeyPem));

        if (!$keyDetails || $keyDetails['type'] != OPENSSL_KEYTYPE_RSA) {
            http_response_code(500);
            echo json_encode(["error" => "Invalid public key"]);
            exit;
        }

        // Extract the modulus and exponent
        $modulus = rtrim(strtr(base64_encode($keyDetails['rsa']['n']), '+/', '-_'), '=');
        $exponent = rtrim(strtr(base64_encode($keyDetails['rsa']['e']), '+/', '-_'), '=');

        // Build the JWKS response
        $jwks = [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig', // Mark as a signing key
                'kid' => 'key-2025', // You can rotate this
                'alg' => 'RS256',
                'n'   => $modulus,
                'e'   => $exponent
            ]]
        ];

        // Return as JSON
        return new JsonResponse($jwks);
    }

    public function handleDiscoveryRequest() {
        $issuer = $this->issuerURL;
        $baseURL = "{$issuer}/adm_program/modules/sso/index.php/oidc";

        $config = [
            "issuer" => $issuer,
            "authorization_endpoint" => "{$baseURL}/authorize",
            "token_endpoint" => "{$baseURL}/token",
            "userinfo_endpoint" => "{$baseURL}/userinfo",
            "jwks_uri" => "{$baseURL}/jwks",
            "scopes_supported" => ["openid", "profile", "email"],
            "response_types_supported" => ["code"],
            "grant_types_supported" => ["authorization_code", "refresh_token"], // TODO_RK: Everything is prepared for all different types of grants!
            "subject_types_supported" => ["public"],
            "id_token_signing_alg_values_supported" => ["RS256"],
            "token_endpoint_auth_methods_supported" => ["client_secret_post", "client_secret_basic"],
        ];
        return new JsonResponse($config);
    }

    public function handleIntrospectionRequest() {
        // TODO_RK
        if (!$this->isServiceSetup) {
            $this->setupService();
        }
        return new JsonResponse(["active" => true]);
    }

    public function handleRevocationRequest() {
        // TODO_RK
        if (!$this->isServiceSetup) {
            $this->setupService();
        }

        return new JsonResponse(["revoked" => true]);
    }

    public function handleLogoutRequest() {
        // Properly destroy session and logout user
        if (isset($_SESSION)) {
            session_unset();
            session_destroy();
        }
        // TODO_RK: Shall we remove the tokens from the database?
        return new JsonResponse(["logout" => true]);
    }
}

