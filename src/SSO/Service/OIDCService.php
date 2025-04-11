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

use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\Entities\ClaimSetEntity;

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
use Admidio\SSO\Entity\IdTokenResponse;

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
    private ClaimExtractor $claimExtractor;
    private ClientRepository $clientRepository;

    private string $issuerURL;
    private string $authorizationEndpoint;
    private string $tokenEndpoint;
    private string $userinfoEndpoint;
    private string $discoveryURL;

    public static ?OIDCClient $client = null;

    private bool $isServiceSetup = false;
    
    public function __construct($db, $currentUser) {//, ResourceServer $resourceServer) {
        global $gSettingsManager;

        parent::__construct($db, $currentUser);
        $this->columnPrefix = 'ocl';
        $this->table = TBL_OIDC_CLIENTS;

        // Attention: IssuerURL must be the base URL, where ./well-known/openid-configuration is located!
        $this->issuerURL = $gSettingsManager->get('sso_oidc_issuer_url') ?: ADMIDIO_URL;
        if (empty($this->issuerURL)) {
            $this->issuerURL = ADMIDIO_URL . '/adm_program/modules/sso/index.php/oidc';
            $gSettingsManager->set('sso_oidc_issuer_url', $this->issuerURL);
        }
        $this->authorizationEndpoint = $this->issuerURL  . "/authorize";
        $this->tokenEndpoint = $this->issuerURL . "/token";
        $this->userinfoEndpoint = $this->issuerURL . "/userinfo";
        $this->discoveryURL = $this->issuerURL . "/.well-known/openid-configuration";

    }

    protected function saveCustomClientSettings(array &$formValues, SSOClient $client) {
        if (array_key_exists('ocl_scope', $formValues)) {
            $formValues['ocl_scope'] = implode(' ', array_merge(['openid'], $formValues['ocl_scope']));
        }
        if (array_key_exists('new_ocl_client_secret', $formValues)) {
            // A new client secret -> store the hashed value in the database!
            $client->setValue(
                $client->getColumnPrefix().'_client_secret', 
                password_hash($formValues['new_ocl_client_secret'], PASSWORD_DEFAULT)
            );
        }
    }

    public static function setClient(OIDCClient $client) {
        self::$client = $client;
    }
    public static function getClient(): ?OIDCClient {
        return self::$client;
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
        $refreshTokenRepository = new RefreshTokenRepository(database: $this->db); // instance of RefreshTokenRepositoryInterface

        // Provide the groups as a groups scope and claim
        $claimsExtractor = new ClaimExtractor([
            // new ClaimSetEntity('openid', ['sub']),
            new ClaimSetEntity('groups', ['groups'])
        ]);
        $responseType = new IdTokenResponse($userRepository, $claimsExtractor);

        // Keep references to the relevant objects for later use
        $this->accessTokenRepository = $accessTokenRepository;
        $this->claimExtractor = $claimsExtractor;
        $this->clientRepository = $clientRepository;

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
            $encryptionKey,
            $responseType
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
        global $gProfileFields, $gSettingsManager, $gValidLogin, $gCurrentUserId, $gL10n;

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
            self::$client = $authRequest->getClient();
            
            // Redirect the user to a login endpoint if not logged in yet.
            if (!$gValidLogin) {
                $this->showSSOLoginForm(self::$client);
                // exit;
            }

            // Check whether the current user has access permissions to the SP client:
            if (!self::$client->hasAccessRight()) {
                $message = '<div class="alert alert-danger form-alert" style=""><i class="bi bi-exclamation-circle-fill"></i>' . 
                    $gL10n->get('SYS_SSO_LOGIN_MISSING_PERMISSIONS', array(self::$client->readableName())) . 
                    '</div>';
                $this->showSSOLoginForm(self::$client, $message);
                // Either exit in the showLoginForm or an Exception was triggered => execution won't continue here!
                exit;
            }
            
            // Once the user has logged in set the user on the AuthorizationRequest
            $authRequest->setUser(new UserEntity($this->db, $gProfileFields, self::$client, $gCurrentUserId));
            
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


            $user = $token->getUser();
            if ($user === null) {
                return new JsonResponse(['error' => 'access_denied', 'message' => 'User not found'], 403);
            }

            /**
             * @var OIDCClient $client
             */
            $client = $token->getClient();
            if ($client === null) {
                return new JsonResponse(['error' => 'access_denied', 'message' => 'Client not found'], 403);
            }
            if (!($client instanceof OIDCClient)) {
                return new JsonResponse(['error' => 'access_denied', 'message' => 'Client not found'], 403);
            }
            $scopes = $token->getScopes();
            $scopes = array_map(fn($s) => $s->getIdentifier(), $token->getScopes());
            $requestScopes = $request->getAttribute('oauth_scopes');
            $clientScopes = preg_split('/[,;\s]+/', trim($client->getValue($client->getColumnPrefix() . '_scope')));

            if (!empty($requestScopes)) {
                $scopes = array_intersect($scopes, $requestScopes);
            }
            $scopes = array_intersect($scopes, $clientScopes);

            // The openid scope with the mandatory sub claim is not added by default, and 
            // it cannot be added globally, because then the JWT library will throw an error 
            // due to mandatory claims being redefined. So, as a workaround, add the claim
            //  set here.
            $this->claimExtractor->addClaimSet(new ClaimSetEntity('openid', ['sub']));
            $this->claimExtractor->addClaimSet(new ClaimSetEntity('custom', array_keys($client->getFieldMapping())));
            

            // Extract claims
            $claims = $this->claimExtractor->extract($scopes, $user->getClaims());
            return new JsonResponse($claims);

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

        $config = [
            "issuer" => $issuer,
            "authorization_endpoint" => "{$issuer}/authorize",
            "token_endpoint" => "{$issuer}/token",
            "userinfo_endpoint" => "{$issuer}/userinfo",
            "jwks_uri" => "{$issuer}/jwks",
            "scopes_supported" => ["openid", "profile", "email", "phone", "address", "groups", "custom"],
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

