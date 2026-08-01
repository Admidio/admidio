<?php
namespace Admidio\SSO\Service;

use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\CryptTrait;

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
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Users\Entity\User;
use Admidio\SSO\Entity\Key;
use Admidio\SSO\Entity\UserEntity;
use Admidio\SSO\Entity\SSOClient;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Entity\IdTokenResponse;
use Admidio\SSO\Entity\OIDCConsent;
use Admidio\UI\Presenter\OIDCConsentPresenter;
use Admidio\SSO\Grants\OIDCAuthCodeGrant;
use Admidio\SSO\Service\KeyService;

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
    use CryptTrait;

    private AuthorizationServer $authServer;
    private ResourceServer $resourceServer;
    private AccessTokenRepository $accessTokenRepository;
    private RefreshTokenRepository $refreshTokenRepository;

    private ClaimExtractor $claimExtractor;
    private ClientRepository $clientRepository;

    private string $issuerURL;
    private string $authorizationEndpoint;
    private string $tokenEndpoint;
    private string $userinfoEndpoint;
    private string $jwksEndpoint;
    private string $logoutEndpoint;
    private string $discoveryURL;

    private OIDCAuthCodeGrant $authCodeGrant;

    public static ?OIDCClient $client = null;

    private bool $isServiceSetup = false;

    public const AUTHENTICATION_CONTEXT_PASSWORD = 'urn:admidio:authentication:password';
    public const AUTHENTICATION_CONTEXT_PASSWORD_TOTP = 'urn:admidio:authentication:password-totp';

    /**
     * Return the default issuer URL derived from the current Admidio URL.
     * @return string
     */
    public static function getDefaultIssuerURL(): string
    {
        return ADMIDIO_URL . FOLDER_MODULES . '/sso/index.php/oidc';
    }

    public function __construct($db, $currentUser) {//, ResourceServer $resourceServer) {
        global $gSettingsManager;

        parent::__construct($db, $currentUser);
        $this->columnPrefix = 'ocl';
        $this->table = TBL_OIDC_CLIENTS;

        // Attention: IssuerURL must be the base URL, where ./well-known/openid-configuration is located!
        $configuredIssuerURL = trim((string)$gSettingsManager->get('sso_oidc_issuer_url'));

        // empty stored issuer URL means "Use the default admidio URL", which will continue working when the installation is moved
        if ($configuredIssuerURL === '') {
            $this->issuerURL = self::getDefaultIssuerURL();
        } else {
            $this->issuerURL = rtrim($configuredIssuerURL, '/');
        }

        $this->authorizationEndpoint = $this->issuerURL  . "/authorize";
        $this->tokenEndpoint = $this->issuerURL . "/token";
        $this->userinfoEndpoint = $this->issuerURL . "/userinfo";
        $this->jwksEndpoint = $this->issuerURL . "/jwks";
        $this->logoutEndpoint = $this->issuerURL . "/logout";
        $this->discoveryURL = $this->issuerURL . "/.well-known/openid-configuration";

    }

    protected function saveCustomClientSettings(array &$formValues, SSOClient $client) {
        if (array_key_exists('ocl_scope', $formValues)) {
            if (!is_array($formValues['ocl_scope'])) {
                throw new Exception('SYS_SSO_CLIENT_SCOPES_INVALID');
            }

            $selectedScopes = array_values(array_unique($formValues['ocl_scope']));
            $invalidScopes = array_diff($selectedScopes, OIDCClient::getOptionalScopes());

            if (!empty($invalidScopes)) {
                throw new Exception(
                    'SYS_SSO_CLIENT_SCOPES_INVALID',
                    array(implode(', ', $invalidScopes))
                );
            }

            $formValues['ocl_scope'] = implode(' ',
                array_merge(array(OIDCClient::SCOPE_OPENID), $selectedScopes)
            );
        }
        $newClientSecret = (string) ($formValues['new_ocl_client_secret'] ?? '');
        // new clients require a secret
        if ($client->isNewRecord() && $newClientSecret === '') {
            throw new \Exception('SYS_SSO_CLIENT_SECRET_REQUIRED');
        }

        if ($newClientSecret !== '') {
            // A new client secret -> store the hashed value in the database!
            $client->setValue(
                $client->getColumnPrefix().'_client_secret',
                password_hash($formValues['new_ocl_client_secret'], PASSWORD_DEFAULT)
            );
        }
        // Do not keep the client secret available in plain text longer than required
        unset($formValues['new_ocl_client_secret']);
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

    public function initializeClientObject(Database $database): ?SSOClient {
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
     * Return the JWKS endpoint
     * @return string
     */
    public function getJWKSEndpoint() {
        return $this->jwksEndpoint;
    }
    /**
     * Return the userinfo endpoint
     * @return string
     */
    public function getLogoutEndpoint() {
        return $this->logoutEndpoint;
    }
    /**
     * Return the discovery URL
     * @return string
     */
    public function getDiscoveryURL() {
        return $this->discoveryURL;
    }


    /**
     * Returns an associative array with labels and links for the static IdP configuration data
     * (metadata/discovery URL, SSO/SLO endpoints, etc.).
     * @return array Associative arry, the keys will be the displayed labels, each entry has the form
     *     ['value' => 'linkHTML', 'id' => 'uniqueIDinForm', 'style' => 'additionalCSSstyles']
     *   where the 'style' key is optional, but 'value' and 'id' are required.
     */
    public function getStaticSettings() : array {
        global $gL10n;
        $discoveryURL = $this->getDiscoveryURL();
        $staticSettings = array(
            $gL10n->get('SYS_SSO_OIDC_DISCOVERY_URL') => ['value' => '<a href="' . $discoveryURL . '">' . $discoveryURL . '</a>', 'id' => 'discovery_URL'],
            $gL10n->get('SYS_SSO_OIDC_AUTH_ENDPOINT') => ['value' => $this->getAuthorizationEndpoint(), 'id' => 'auth_endpoint'],
            $gL10n->get('SYS_SSO_OIDC_TOKEN_ENDPOINT') => ['value' => $this->getTokenEndpoint(),'id' => 'token_endpoint'],
            $gL10n->get('SYS_SSO_OIDC_USERINFO_ENDPOINT') => ['value' => $this->getUserinfoEndpoint(),'id' => 'userinfo_endpoint'],
            $gL10n->get('SYS_SSO_OIDC_JWKS_ENDPOINT') => ['value' => $this->getJWKSEndpoint(),'id' => 'jwks_endpoint'],
            $gL10n->get('SYS_SSO_OIDC_LOGOUT_ENDPOINT') => ['value' => $this->getLogoutEndpoint(),'id' => 'logout_endpoint'],
        );
        return $staticSettings;
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
        global $gSettingsManager, $gLogger;

        // Init our repositories
        $clientRepository = new ClientRepository($this->db);            // instance of ClientRepositoryInterface
        $scopeRepository = new ScopeRepository($this->db);                        // instance of ScopeRepositoryInterface
        $accessTokenRepository = new AccessTokenRepository($this->db);  // instance of AccessTokenRepositoryInterface
        $authCodeRepository = new AuthCodeRepository($this->db);        // instance of AuthCodeRepositoryInterface
        $userRepository = new UserRepository($this->db); // instance of UserRepositoryInterface // TODO_RK: Add user ID field and allowed Roles!
        $refreshTokenRepository = new RefreshTokenRepository(database: $this->db); // instance of RefreshTokenRepositoryInterface

        // Private key for signing
        $keyService = new KeyService($this->db);
        $privateKeyID = (int) $gSettingsManager->get('sso_oidc_signing_key');
        $privateKeyObject = $keyService->getUsableKey($privateKeyID, KeyService::USAGE_OIDC_SIGNING);
        $privateKey = new CryptKey($privateKeyObject->getValue('key_private'));
        $publicKey = new CryptKey($privateKeyObject->getValue('key_public'));

        // Provide the groups as a groups scope and claim
        $claimsExtractor = new ClaimExtractor([
            // new ClaimSetEntity('openid', ['sub']),
            new ClaimSetEntity('groups', ['groups'])
        ]);
        $responseType = new IdTokenResponse(
            $userRepository,
            $claimsExtractor,
            $this->issuerURL,
            $privateKeyObject->getValue('key_uuid')
        );

        // Keep references to the relevant objects for later use
        $this->accessTokenRepository = $accessTokenRepository;
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->claimExtractor = $claimsExtractor;
        $this->clientRepository = $clientRepository;


        // The encryption key is used to store tokens encrypted to the DB.
        $encryptionKey = $gSettingsManager->get('sso_oidc_encryption_key');
        if (empty($encryptionKey)) {
            $encryptionKey = base64_encode(random_bytes(32));
            $gSettingsManager->set('sso_oidc_encryption_key', $encryptionKey);
        }
        $this->setEncryptionKey($encryptionKey);

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
        $grant = new OIDCAuthCodeGrant(
             $authCodeRepository,
             $refreshTokenRepository,
             new \DateInterval('PT10M') // authorization codes will expire after 10 minutes
        );
        $this->authCodeGrant = $grant;

        $grant->setRefreshTokenTTL(new \DateInterval('P1M')); // refresh tokens will expire after 1 month
        // Enable the authentication code grant on the server
        $server->enableGrantType(
            $grant,
            new \DateInterval('PT1H') // access tokens will expire after 1 hour
        );


        /* ***********************************************************************
        * RefreshToken Grant
        */
        $grant = new RefreshTokenGrant($refreshTokenRepository);
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

    /**
     * Read and validate the max_age parameter.
     * @param ServerRequestInterface $request
     * @return int|null
     * @throws OAuthServerException
     */
    private function getMaxAge(ServerRequestInterface $request): int|null
    {
        $queryParams = $request->getQueryParams();

        if (!array_key_exists('max_age', $queryParams)) {
            return null;
        }

        // max_age is a string, which must represent a number, so check this explicitly
        if ((!is_string($queryParams['max_age']) && !is_int($queryParams['max_age']))
            || preg_match('/^\d+$/', (string)$queryParams['max_age']) !== 1
        ) {
            throw OAuthServerException::invalidRequest('max_age');
        }

        return (int)$queryParams['max_age'];
    }

    /**
     * Check whether the current authentication satisfies max_age.
     * @param int $maxAge
     * @return bool
     */
    private function isAuthenticationWithinMaxAge(int $maxAge): bool
    {
        global $gCurrentSession;
        $authenticationTime = (int)$gCurrentSession->getValue('ses_authentication_time', 'U');

        if ($authenticationTime === 0) {
            return false;
        }

        return time() - $authenticationTime <= $maxAge;
    }

    private function getAuthenticationRequestID(ServerRequestInterface $request): string
    {
        return hash('sha256', (string)$request->getUri());
    }

    // Prevent a reauthentication loop, because the original URL still contains max_age=0.
    // So we need to store information about the reauthentication in the session and check
    // that to break the loop
    private function hasCompletedReauthentication(ServerRequestInterface $request): bool
    {
        global $gCurrentSession;

        if (!isset($_SESSION['oidc_reauthentication_request'])
            || !is_array($_SESSION['oidc_reauthentication_request'])
            || !isset($_SESSION['oidc_reauthentication_request']['request_id'])
            || !isset($_SESSION['oidc_reauthentication_request']['requested_at'])
        ) {
            return false;
        }

        if (!hash_equals(
                $_SESSION['oidc_reauthentication_request']['request_id'],
                $this->getAuthenticationRequestID($request))) {
            return false;
        }

        if ((int)$gCurrentSession->getValue('ses_authentication_time', 'U')
            < (int)$_SESSION['oidc_reauthentication_request']['requested_at']) {
            return false;
        }

        unset($_SESSION['oidc_reauthentication_request']);

        return true;
    }

    private function rememberReauthenticationRequest(ServerRequestInterface $request): void
    {
        $_SESSION['oidc_reauthentication_request'] = array(
            'request_id' => $this->getAuthenticationRequestID($request),
            'requested_at' => time()
        );
    }

    public function handleAuthorizationRequest(): ResponseInterface {
        global $gProfileFields, $gSettingsManager, $gValidLogin, $gCurrentUserId, $gL10n, $gLogger, $gCurrentSession;

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
            if (!self::$client->isEnabled()) {
                throw OAuthServerException::invalidClient($request, 'Client "' . self::$client->getIdentifier() . '" is valid, but disabled. Login is not allowed.');
            }
            $maxAge = $this->getMaxAge($request);
            $reauthenticationCompleted = $this->hasCompletedReauthentication($request);
            $authenticationRequired = !$gValidLogin;

            if (!$reauthenticationCompleted && $maxAge !== null && !$this->isAuthenticationWithinMaxAge($maxAge)) {
                $authenticationRequired = true;
            }
            
            // Depending on the prompt parameter, show a login form (if needed) or deny authorization
            $promptValues = $this->getPromptValues($request);
            if (!$reauthenticationCompleted && in_array('login', $promptValues, true)) {
                $authenticationRequired = true;
            }
            if ($authenticationRequired && in_array('none', $promptValues, true)) {
                return $this->createAuthorizationErrorResponse($authRequest, $response, 'login_required');
            }

            // Redirect the user to a login endpoint if not logged in yet or the authentication is too old (given by the max_age param)
            if ($authenticationRequired) {
                $this->rememberReauthenticationRequest($request);
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

            // Redirect the user to an authorization page.
            // This form will ask the user to approve the client and the scopes requested.
            $consentRequired = !$this->hasOIDCConsent($authRequest) || in_array('consent', $promptValues, true);

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $form = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
                $form->validate($_POST);

                if (array_key_exists('adm_button_deny', $_POST)) {
                    $authRequest->setAuthorizationApproved(false);
                    return $this->authServer->completeAuthorizationRequest($authRequest, $response);
                }

                if (array_key_exists('adm_button_approve', $_POST)) {
                    $this->saveOIDCConsent($authRequest);
                    $consentRequired = false;
                }
            }

            if ($consentRequired) {
                // If consent is required, prompt=none must fail, since we cannot show a consent form
                if (in_array('none', $promptValues, true)) {
                    return $this->createAuthorizationErrorResponse($authRequest, $response, 'consent_required');
                }
                $this->showOIDCConsentForm($authRequest);
            }

            $authenticationMethods = preg_split('/\s+/', trim($gCurrentSession->getValue('ses_authentication_methods')));

            $authenticationContext = self::AUTHENTICATION_CONTEXT_PASSWORD;
            if (in_array('otp', $authenticationMethods, true)) {
                $authenticationContext = self::AUTHENTICATION_CONTEXT_PASSWORD_TOTP;
            }

            $this->authCodeGrant->setAuthenticationContext(
                (int)$gCurrentSession->getValue('ses_authentication_time', 'U'),
                $gCurrentSession->getValue('ses_external_session_id'),
                $authenticationMethods,
                $authenticationContext
            );

            // Once the user has approved or denied the client update the status
            // (true = approved, false = denied)
            $authRequest->setAuthorizationApproved(true);

            // Return the HTTP redirect response
            return $this->authServer->completeAuthorizationRequest($authRequest, $response);

        } catch (OAuthServerException $exception) {
            $gLogger->error($exception->getMessage(), array_merge($exception->getPayload(), ['trace' => $exception->getTraceAsString()]));
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
        global $gLogger;
        $request = $this->getRequest();
        $response = new Response();
        try {
            if (!$this->isServiceSetup) {
                $this->setupService();
            }
            // Try to respond to the request
            return $this->authServer->respondToAccessTokenRequest($request, $response);

        } catch (OAuthServerException $exception) {
            $gLogger->error($exception->getMessage(), array_merge($exception->getPayload(), ['trace' => $exception->getTraceAsString()]));
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
        global $gLogger;
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

            $scopes = array_map(fn($s) => $s->getIdentifier(), $token->getScopes());
            $scopes = array_values(array_intersect($scopes, $client->getAllowedScopes()));

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
            $gLogger->error($exception->getMessage(), array_merge($exception->getPayload(), ['trace' => $exception->getTraceAsString()]));
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
        $keyService = new KeyService($this->db);
        $key = $keyService->getUsableKey((int) $gSettingsManager->get('sso_oidc_signing_key'), KeyService::USAGE_OIDC_SIGNING);
        $publicKeyPem = (string) $key->getValue('key_public');
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        $keyDetails = openssl_pkey_get_details($publicKey);

        if ($keyDetails === false|| !isset($keyDetails['rsa']['n'], $keyDetails['rsa']['e'])) {
            throw new \Exception('SYS_SSO_PUBLIC_KEY_INVALID');
        }

        // Extract the modulus and exponent
        $modulus = rtrim(strtr(base64_encode($keyDetails['rsa']['n']), '+/', '-_'), '=');
        $exponent = rtrim(strtr(base64_encode($keyDetails['rsa']['e']), '+/', '-_'), '=');

        // Build the JWKS response
        $jwks = [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'kid' => $key->getValue('key_uuid'),
                'alg' => 'RS256',
                'n'   => $modulus,
                'e'   => $exponent
            ]]
        ];

        // Return as JSON
        return new JsonResponse($jwks);
    }

    public function handleDiscoveryRequest(): JsonResponse
    {
        return new JsonResponse($this->getDiscoveryConfiguration());
    }

    public function getDiscoveryConfiguration(): array
    {
        $issuer = $this->issuerURL;

        return [
            "issuer" => $issuer,
            "authorization_endpoint" => "{$issuer}/authorize",
            "token_endpoint" => "{$issuer}/token",
            "userinfo_endpoint" => "{$issuer}/userinfo",
            "jwks_uri" => "{$issuer}/jwks",
            "introspection_endpoint" => "{$issuer}/introspect",
            "revocation_endpoint" => "{$issuer}/revoke",
            "scopes_supported" => OIDCClient::getSupportedScopes(),
            "response_types_supported" => ["code"],
            "response_modes_supported" => ["query"],
            "grant_types_supported" => ["authorization_code", "refresh_token"],
            "subject_types_supported" => ["public"],
            "id_token_signing_alg_values_supported" => ["RS256"],
            "token_endpoint_auth_methods_supported" => ["client_secret_post", "client_secret_basic"],
            "revocation_endpoint_auth_methods_supported" => ["client_secret_basic", "client_secret_post"],
            // announce standard claims, even though each client in admidio can define their own admidio 
            // profile field -> claim mappeing with arbitrary claims! The custom claims are not included
            // in the discovery announcement!
            "claims_supported" => [
                "sub",
                "iss",
                "aud",
                "exp",
                "iat",
                "auth_time",
                "nonce",
                "acr",
                "amr",
                "sid",
                "uuid",
                "preferred_username",
                "name",
                "family_name",
                "given_name",
                "email",
                "phone_number",
                "address",
                "groups",
                "locale",
                "website",
                "gender",
                "birthdate"
            ],
            "acr_values_supported" => [self::AUTHENTICATION_CONTEXT_PASSWORD, self::AUTHENTICATION_CONTEXT_PASSWORD_TOTP],
            "claims_parameter_supported" => false,
            "request_parameter_supported" => false,
            "request_uri_parameter_supported" => false,
        ];
    }

    /**
     * @return JsonResponse
     * @throws \Exception
     */
    public function handleIntrospectionRequest(): JsonResponse
    {
        if (!$this->isServiceSetup) {
            $this->setupService();
        }

        $request = $this->getRequest();

        // 1. Authenticate the resource server (RFC 7662 Section 2.1)
        // The resource server MUST authenticate using client credentials
        $clientId = $this->authenticateEndpointClient($request);
        if ($clientId === null) {
            return $this->createInvalidClientResponse();
        }

        // 2. Get and validate the token
        $requestBody = $request->getParsedBody();
        if (!is_array($requestBody)) {
            $requestBody = array();
        }

        $tokenValue = $requestBody['token'] ?? '';
        if (empty($tokenValue)) {
            return new JsonResponse(['active' => false]);
        }

        try {
            // Validate the token using the resource server
            $validatedRequest = $this->resourceServer->validateAuthenticatedRequest(
                $request->withHeader('Authorization', 'Bearer ' . $tokenValue)
            );

            $tokenId = $validatedRequest->getAttribute('oauth_access_token_id');

            // Check if token is revoked
            if ($this->accessTokenRepository->isAccessTokenRevoked($tokenId)) {
                return new JsonResponse(['active' => false]);
            }

            $token = $this->accessTokenRepository->getToken($tokenId);

            // Check expiry
            if ($token->getExpiryDateTime() < new \DateTimeImmutable()) {
                return new JsonResponse(['active' => false]);
            }

            return new JsonResponse([
                'active' => true,
                'sub' => $token->getUserIdentifier(),
                'client_id' => $token->getClient()->getIdentifier(),
                'exp' => $token->getExpiryDateTime()->getTimestamp(),
                'scope' => implode(' ', array_map(fn($s) => $s->getIdentifier(), $token->getScopes())),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['active' => false]);
        }
    }

    /**
     * @return JsonResponse
     * @throws \Exception
     */
    public function handleRevocationRequest(): JsonResponse
    {
        if (!$this->isServiceSetup) {
            $this->setupService();
        }

        $request = $this->getRequest();
        $requestBody = $request->getParsedBody();
        if (!is_array($requestBody)) {
            $requestBody = array();
        }

        // Authenticate the client
        $clientId = $this->authenticateEndpointClient($request);
        if ($clientId === null) {
            return $this->createInvalidClientResponse();
        }

        $tokenValue = $requestBody['token'] ?? '';
        $tokenTypeHint = $requestBody['token_type_hint'] ?? null;

        if (!is_string($tokenValue) || $tokenValue === '') {
            return new JsonResponse([], 200);
        }

        if ($tokenTypeHint === 'refresh_token') {
            if (!$this->revokeRefreshToken($tokenValue, $clientId)) {
                $this->revokeAccessToken($request, $tokenValue, $clientId);
            }
        } elseif ($tokenTypeHint === 'access_token') {
            if (!$this->revokeAccessToken($request, $tokenValue, $clientId)) {
                $this->revokeRefreshToken($tokenValue, $clientId);
            }
        } elseif (!$this->revokeAccessToken($request, $tokenValue, $clientId)) {
            $this->revokeRefreshToken($tokenValue, $clientId);
        }

        return new JsonResponse([], 200);
    }

    /**
     * Authenticate a client at an OIDC endpoint.
     *
     * Supports the client_secret_basic and client_secret_post authentication methods.
     *
     * @param ServerRequestInterface $request
     * @return string|null Authenticated client ID or null if authentication failed.
     */
    private function authenticateEndpointClient(ServerRequestInterface $request): ?string
    {
        $clientCredentials = $this->getEndpointClientCredentials($request);

        if ($clientCredentials === null) {
            return null;
        }

        try {
            if (!$this->clientRepository->validateClient(
                $clientCredentials['clientId'], 
                $clientCredentials['clientSecret'], 
                null
            )) {
                return null;
            }
        } catch (\Throwable $exception) {
            return null;
        }

        return $clientCredentials['clientId'];
    }

    /**
     * Extract client credentials from HTTP Basic authentication or the request body.
     *
     * @param ServerRequestInterface $request
     * @return array{clientId: string, clientSecret: string}|null
     */
    private function getEndpointClientCredentials(ServerRequestInterface $request): ?array
    {
        $requestBody = $request->getParsedBody();

        if (!is_array($requestBody)) {
            $requestBody = array();
        }

        $authorizationHeader = trim($request->getHeaderLine('Authorization'));
        $usesBasicAuthentication = strncasecmp($authorizationHeader, 'Basic ', 6) === 0;
        $usesPostAuthentication = array_key_exists('client_id', $requestBody)
            || array_key_exists('client_secret', $requestBody);

        // A client must not use more than one authentication method per request.
        if ($usesBasicAuthentication && $usesPostAuthentication) {
            return null;
        }

        if ($usesBasicAuthentication) {
            $encodedCredentials = trim(substr($authorizationHeader, 6));
            $decodedCredentials = base64_decode($encodedCredentials, true);

            if ($decodedCredentials === false || !str_contains($decodedCredentials, ':')) {
                return null;
            }

            [$clientId, $clientSecret] = explode(':', $decodedCredentials, 2);

            $clientId = urldecode($clientId);
            $clientSecret = urldecode($clientSecret);
        } elseif ($usesPostAuthentication) {
            $clientId = $requestBody['client_id'] ?? null;
            $clientSecret = $requestBody['client_secret'] ?? null;

            if (!is_string($clientId) || !is_string($clientSecret)) {
                return null;
            }
        } else {
            return null;
        }

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        return array(
            'clientId' => $clientId,
            'clientSecret' => $clientSecret
        );
    }

    /**
     * Create the response for failed OIDC endpoint client authentication.
     * @return JsonResponse
     */
    private function createInvalidClientResponse(): JsonResponse
    {
        return new JsonResponse(
            array('error' => 'invalid_client'),
            401,
            array(
                'WWW-Authenticate' => 'Basic realm="Admidio OIDC"',
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache'
            )
        );
    }


   /**
     * Revoke an access token.
     * @param ServerRequestInterface $request
     * @param string $tokenValue
     * @param string $clientId
     * @return bool Returns true if the value was recognized as an access token.
     */
    private function revokeAccessToken(ServerRequestInterface $request, string $tokenValue, string $clientId): bool 
    {
        try {
            $validatedRequest = $this->resourceServer->validateAuthenticatedRequest(
                $request->withHeader('Authorization', 'Bearer ' . $tokenValue)
            );

            $tokenId = $validatedRequest->getAttribute('oauth_access_token_id');
            $tokenClientId = $validatedRequest->getAttribute('oauth_client_id');

            if (!is_string($tokenId) || $tokenId === '' || !is_string($tokenClientId)
                || !hash_equals($clientId, $tokenClientId)
            ) {
                return false;
            }

            $this->accessTokenRepository->revokeAccessToken($tokenId);
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Revoke a refresh token and its associated access token.
     * @param string $tokenValue
     * @param string $clientId
     * @return bool Returns true if the value was recognized as a refresh token.
     */
    private function revokeRefreshToken(string $tokenValue, string $clientId): bool
    {
        try {
            $tokenPayload = json_decode($this->decrypt($tokenValue), true);

            if (
                !is_array($tokenPayload)
                || !isset(
                    $tokenPayload['refresh_token_id'],
                    $tokenPayload['access_token_id'],
                    $tokenPayload['client_id']
                )
                || !is_string($tokenPayload['refresh_token_id'])
                || !is_string($tokenPayload['access_token_id'])
                || !is_string($tokenPayload['client_id'])
                || $tokenPayload['refresh_token_id'] === ''
                || $tokenPayload['access_token_id'] === ''
                || !hash_equals($clientId, $tokenPayload['client_id'])
            ) {
                return false;
            }

            $this->refreshTokenRepository->revokeRefreshToken(
                $tokenPayload['refresh_token_id']
            );
            $this->accessTokenRepository->revokeAccessToken(
                $tokenPayload['access_token_id']
            );

            return true;
        } catch (\Exception $exception) {
            return false;
        }
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

    private function getRequestedScopeNames(AuthorizationRequestInterface $authRequest): array
    {
        return array_map(
            static fn ($scope): string => $scope->getIdentifier(),
            $authRequest->getScopes()
        );
    }

    private function hasOIDCConsent(AuthorizationRequestInterface $authRequest): bool
    {
        global $gCurrentOrgId, $gCurrentUserId;

        $consent = new OIDCConsent($this->db);
        $consent->readDataByUserAndClient(
            $gCurrentOrgId,
            $gCurrentUserId,
            self::$client->getValue('ocl_id')
        );

        if ($consent->isNewRecord()) {
            return false;
        }

        return $consent->coversScopes(
            $this->getRequestedScopeNames($authRequest)
        );
    }

    private function saveOIDCConsent(AuthorizationRequestInterface $authRequest): void
    {
        global $gCurrentOrgId, $gCurrentUserId;

        $consent = new OIDCConsent($this->db);
        $consent->readDataByUserAndClient(
            $gCurrentOrgId,
            $gCurrentUserId,
            self::$client->getValue('ocl_id')
        );

        $consent->setValue('oco_org_id', $gCurrentOrgId);
        $consent->setValue('oco_usr_id', $gCurrentUserId);
        $consent->setValue('oco_ocl_id', self::$client->getValue('ocl_id'));
        $consent->setValue(
            'oco_scopes',
            implode(' ', $this->getRequestedScopeNames($authRequest))
        );
        $consent->save();
    }

    private function showOIDCConsentForm(AuthorizationRequestInterface $authRequest): void
    {
        $presenter = new OIDCConsentPresenter(self::$client->readableName());
        $presenter->createConsentForm(
            self::$client,
            $this->getRequestedScopeNames($authRequest)
        );
        $presenter->show();
        exit;
    }

    private function getPromptValues(ServerRequestInterface $request): array
    {
        $queryParams = $request->getQueryParams();

        if (!array_key_exists('prompt', $queryParams)) {
            return array();
        }

        if (!is_string($queryParams['prompt'])) {
            throw OAuthServerException::invalidRequest('prompt');
        }

        $promptValues = preg_split('/\s+/', trim($queryParams['prompt']));

        if ($promptValues === false || $promptValues === array('')) {
            throw OAuthServerException::invalidRequest('prompt');
        }

        foreach ($promptValues as $promptValue) {
            if (!in_array($promptValue, array('none', 'login', 'consent'), true)) {
                throw OAuthServerException::invalidRequest('prompt');
            }
        }

        if (in_array('none', $promptValues, true) && count($promptValues) > 1) {
            throw OAuthServerException::invalidRequest('prompt');
        }

        return array_values(array_unique($promptValues));
    }
    
    private function createAuthorizationErrorResponse(AuthorizationRequestInterface $authRequest, ResponseInterface $response, string $error): ResponseInterface 
    {
        $redirectURI = $authRequest->getRedirectUri();

        if ($redirectURI === null) {
            return new JsonResponse(array('error' => $error), 400);
        }

        $parameters = array('error' => $error);

        if ($authRequest->getState() !== null) {
            $parameters['state'] = $authRequest->getState();
        }

        $separator = str_contains($redirectURI, '?') ? '&' : '?';

        return $response
            ->withStatus(302)
            ->withHeader('Location', $redirectURI . $separator . http_build_query($parameters));
    }
}

