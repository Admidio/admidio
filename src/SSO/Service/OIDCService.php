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
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface; // Needed for PSR-7 compliance
use Psr\Http\Message\ResponseInterface; // Ensures correct return types for responses
use Psr\Http\Server\RequestHandlerInterface; // May be useful for middleware in the future

use Admidio\Infrastructure\Database;
use Admidio\Users\Entity\User;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\SSO\Entity\Key;

class OIDCService {
    private \League\OAuth2\Server\AuthorizationServer $authServer;
    // private ResourceServer $resourceServer;
    private Database $db;
    private User $currentUser;

    private string $issuerURL;
    private string $authorizationEndpoint;
    private string $tokenEndpoint;
    private string $userinfoEndpoint;
    private string $discoveryURL;
    
    public function __construct($db, $currentUser) {//, ResourceServer $resourceServer) {
        global $gSettingsManager;
        $this->db = $db;
        $this->currentUser = $currentUser;
        $this->issuerURL = $gSettingsManager->get('sso_oidc_issuer_url') ?: ADMIDIO_URL;
        $this->authorizationEndpoint = $this->issuerURL  . "/adm_program/modules/sso/index.php/oidc/auth";
        $this->tokenEndpoint = $this->issuerURL . "/adm_program/modules/sso/index.php/oidc/token";
        $this->userinfoEndpoint = $this->issuerURL . "/adm_program/modules/sso/index.php/oidc/userinfo";
        $this->discoveryURL = $this->issuerURL . "/adm_program/modules/sso/index.php/oidc/.well-known/openid-configuration";
    

        // $this->resourceServer = $resourceServer;
        // $this->setupService();
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
        return $serverRequest;
    }


    public function setupService() {
        global $gSettingsManager;

        // Init our repositories
        $clientRepository = new ClientRepository($this->db);            // instance of ClientRepositoryInterface
        $scopeRepository = new ScopeRepository($this->db);                        // instance of ScopeRepositoryInterface
        $accessTokenRepository = new AccessTokenRepository($this->db);  // instance of AccessTokenRepositoryInterface
        $authCodeRepository = new AuthCodeRepository($this->db);        // instance of AuthCodeRepositoryInterface
        $refreshTokenRepository = new RefreshTokenRepository($this->db); // instance of RefreshTokenRepositoryInterface

        // Private key and Certificate for signatures
        $signatureKeyID = $gSettingsManager->get('sso_oidc_signing_key');
        $signatureKey = new Key($this->db, $signatureKeyID);
        $privateKey = $signatureKey->getValue('key_private');
        // $idpCertPem = $signatureKey->getValue('key_certificate');
        
        $signatureKeyID = $gSettingsManager->get('sso_oidc_signing_key');
        $signatureKey = new Key($this->db, $signatureKeyID);


        // $privateKey = new CryptKey($idpPrivateKeyPem, ''); // if private key has a pass phrase
        // TODO: What is the encryption key?
        $encryptionKey = 'lxZFUEsBCJ2Yb14IF2ygAHI5N4+ZAUXXaSeeJm6+twsUmIen'; // generate using base64_encode(random_bytes(32))

        // Setup the authorization server
        $server = new \League\OAuth2\Server\AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            $privateKey,
            $encryptionKey
        );

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


        $grant = new \League\OAuth2\Server\Grant\RefreshTokenGrant($refreshTokenRepository);
        $grant->setRefreshTokenTTL(new \DateInterval('P1M')); // new refresh tokens will expire after 1 month
        
        // Enable the refresh token grant on the server
        $server->enableGrantType(
            $grant,
            new \DateInterval('PT1H') // new access tokens will expire after an hour
        );


        $this->authServer = $server;
    }

    public function handleAuthorizationRequest() {
        $request = $this->getRequest();
        return $this->authServer->respondToAuthorizationRequest($request, new Response());
    }

    public function handleTokenRequest() {
        $request = $this->getRequest();
        return $this->authServer->respondToAccessTokenRequest($request, new Response());
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

