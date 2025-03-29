<?php
namespace Admidio\SSO\Service;

use League\OAuth2\Server\CryptKey;

use Admidio\SSO\Repository\AccessTokenRepository;
use Admidio\SSO\Repository\ClientRepository;
use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\SSO\Repository\ScopeRepository;
use Admidio\SSO\Repository\UserRepository;


// use Laminas\Diactoros\Response;
// use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface; // Needed for PSR-7 compliance
use Psr\Http\Message\ResponseInterface; // Ensures correct return types for responses
use Psr\Http\Server\RequestHandlerInterface; // May be useful for middleware in the future
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Exception\OAuthServerException;

use Admidio\Infrastructure\Database;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\SSO\Entity\Key;
use Admidio\SSO\Entity\SSOClient;
use Admidio\SSO\Entity\OIDCClient;

class OIDCService extends SSOService {
    private AuthorizationServer $authServer;
    private ResourceServer $resourceServer;

    private string $issuerURL;
    private string $authorizationEndpoint;
    private string $tokenEndpoint;
    private string $userinfoEndpoint;
    private string $discoveryURL;
    
    public function __construct($db, $currentUser) {//, ResourceServer $resourceServer) {
        global $gSettingsManager;

        parent::__construct($db, $currentUser);
        $this->columnPrefix = 'ocl';
        $this->table = TBL_OIDC_CLIENTS;

        $this->issuerURL = $gSettingsManager->get('sso_oidc_issuer_url') ?: ADMIDIO_URL;
        $this->authorizationEndpoint = $this->issuerURL  . "/adm_program/modules/sso/index.php/oidc/auth";
        $this->tokenEndpoint = $this->issuerURL . "/adm_program/modules/sso/index.php/oidc/token";
        $this->userinfoEndpoint = $this->issuerURL . "/adm_program/modules/sso/index.php/oidc/userinfo";
        $this->discoveryURL = $this->issuerURL . "/adm_program/modules/sso/index.php/oidc/.well-known/openid-configuration";
    

        $this->setupService();
    }


    public function createClientObject($clientUUID): ?SSOClient {
        $client = new OIDCClient($this->db);
        $client->readDataByUuid($clientUUID);
        return $client;
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
     * Save data from the OIDC client edit form into the database.
     * @throws Exception
     */
    public function save($getClientUUID)
    {
        global $gCurrentSession;

        // check form field input and sanitized it from malicious content
        $clientEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $clientEditForm->validate($_POST);
        $client = $this->createClientObject($getClientUUID);

        $this->db->startTransaction();

        // Collect all field mappings and the catch-all checkbox
        // If a OIDC field is left empty, use the admidio name!
        $oidcFields = $formValues['sso_oidc_fields']??[];
        $admFields = $formValues['Admidio_oidc_fields']??[];
        $oidcFields = array_map(function ($a, $b) { return (!empty($a)) ? $a : $b;}, $oidcFields, $admFields);
        $client->setFieldMapping(array_combine($oidcFields, $admFields), $formValues['oidc_fields_all_other']??false);

        // // Collect all role mappings and the catch-all checkbox
        // $oidcRoles = $formValues['OIDC_oidc_roles']??[];
        // $admRoles = $formValues['Admidio_oidc_roles']??[];
        // $oidcRoles = array_map( function($s, $a) { 
        //         if (empty($s)) {
        //             $role = new Role($this->db, $a);
        //             return $role->readableName();
        //         } else { 
        //             return $s; 
        //         }
        //     }, $oidcRoles, $admRoles);
        // $client->setRoleMapping(array_combine($oidcRoles, $admRoles), $formValues['oidc_roles_all_other']??false);

        // write all other form values
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'ocl_')) {
                $client->setValue($key, $value);
            }
        }

        $client->save();

        // save changed roles rights of the menu
        if (isset($_POST['oidc_roles_access'])) {
            $accessRoles = array_map('intval', $_POST['oidc_roles_access']);
        } else {
            $accessRoles = array();
        }

        $accessRolesRights = new RolesRights($this->db, 'sso_oidc_access', $client->getValue('ocl_id'));
        $accessRolesRights->saveRoles($accessRoles);

        $this->db->endTransaction();
    }


    /**
     * Returns a PSR-7 request for the OAuth2 server while ensuring Admidio compatibility
     */
    private function getRequest() {
        // Ensure Admidio’s global request variables are used for internal logic
        $serverRequest = ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
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
        $server = new \League\OAuth2\Server\AuthorizationServer(
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

        $resourceServer = new \League\OAuth2\Server\ResourceServer(
            $accessTokenRepository,
            $publicKey
        );
        new \League\OAuth2\Server\Middleware\ResourceServerMiddleware($resourceServer);
        $this->resourceServer = $resourceServer;
    }

    public function handleAuthorizationRequest() {
        $request = $this->getRequest();
        $response = new Response();
        try {
    
            // Validate the HTTP request and return an AuthorizationRequest object.
            $authRequest = $this->authServer->validateAuthorizationRequest($request);
            
            // The auth request object can be serialized and saved into a user's session.
            // You will probably want to redirect the user at this point to a login endpoint.
            
            // Once the user has logged in set the user on the AuthorizationRequest
            $authRequest->setUser(new UserEntity()); // an instance of UserEntityInterface
            
            // At this point you should redirect the user to an authorization page.
            // This form will ask the user to approve the client and the scopes requested.
            
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
            // Try to respond to the request
            return $this->authServer->respondToAccessTokenRequest($request, $response);

        } catch (\League\OAuth2\Server\Exception\OAuthServerException $exception) {
            // All instances of OAuthServerException can be formatted into a HTTP response
            return $exception->generateHttpResponse($response);
        } catch (\Exception $exception) {
            // Unknown exception
            $body = new Stream(fopen('php://temp', 'r+'));
            $body->write($exception->getMessage());
            return $response->withStatus(500)->withBody($body);
        }
    }

    public function handleUserInfoRequest($accessToken) {
        $token = $this->resourceServer->validateAuthenticatedRequest($accessToken);
        
        // Ensure Admidio's user object is used
        $userId = $token->getAttribute("user_id");
        if ($this->currentUser->getValue('usr_id') !== $userId) {
            return json_encode(["error" => "invalid_user"], JSON_UNESCAPED_SLASHES);
        }

        return json_encode([
            "sub"   => $this->currentUser->getValue("usr_id"),
            "name"  => $this->currentUser->getValue("usr_first_name") . " " . $this->currentUser->getValue("usr_last_name"),
            "email" => $this->currentUser->getValue("usr_email")
        ], JSON_UNESCAPED_SLASHES);
    }

    public function handleJWKSRequest() {
        global $gSettingsManager;

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
        header('Content-Type: application/json');
        echo json_encode($jwks);
        exit;
    }

    public function handleDiscoveryRequest() {
        return json_encode([
            "issuer"                  => "https://example.com", 
            "authorization_endpoint"  => "/authorize", 
            "token_endpoint"          => "/token", 
            "userinfo_endpoint"       => "/userinfo",
            "jwks_uri"                => "/.well-known/jwks.json"
        ], JSON_UNESCAPED_SLASHES);
    }

    public function handleIntrospectionRequest() {
        return json_encode(["active" => true], JSON_UNESCAPED_SLASHES);
    }

    public function handleRevocationRequest() {
        return json_encode(["revoked" => true], JSON_UNESCAPED_SLASHES);
    }

    public function handleLogoutRequest() {
        // Properly destroy session and logout user
        if (isset($_SESSION)) {
            session_unset();
            session_destroy();
        }
        return json_encode(["logout" => true], JSON_UNESCAPED_SLASHES);
    }
}

