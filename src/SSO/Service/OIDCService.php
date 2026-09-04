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
use Admidio\Session\Entity\Session;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Admidio\UI\Presenter\OIDCConsentPresenter;
use Admidio\UI\Presenter\OIDCLogoutPresenter;
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

    private const DEFAULT_AUTH_CODE_LIFETIME = 600;
    private const DEFAULT_ACCESS_TOKEN_LIFETIME = 3600;
    private const DEFAULT_REFRESH_TOKEN_LIFETIME = 2592000;

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
    public const AUTHENTICATION_CONTEXT_AUTO_LOGIN = 'urn:admidio:authentication:auto-login';

    /**
     * The parameters of an authorization request that a client may send in a form POST.
     * Everything else that arrives in the body belongs to the Admidio consent form.
     */
    private const AUTHORIZATION_REQUEST_PARAMETERS = array(
        'response_type', 'client_id', 'redirect_uri', 'scope', 'state', 'nonce',
        'response_mode', 'prompt', 'max_age', 'display', 'ui_locales', 'acr_values',
        'id_token_hint', 'login_hint', 'code_challenge', 'code_challenge_method'
    );

    /**
     * Return the default issuer URL derived from the current Admidio URL.
     * @return string
     */
    public static function getDefaultIssuerURL(): string
    {
        return ADMIDIO_URL . FOLDER_MODULES . '/sso/index.php/oidc';
    }

    /**
     * Validate a configured issuer URL against the OpenID Connect issuer identifier rules.
     *
     * The issuer identifier must use HTTPS and must not carry a query or a fragment
     * component (OpenID Connect Discovery 1.0, section 2). Like the registered logout
     * URIs, HTTP is tolerated for loopback hosts so that a local installation remains
     * usable for development.
     *
     * @param string $issuerURL Configured issuer URL. An empty value is not validated,
     *                          it means that the Admidio URL is used.
     * @return void
     * @throws Exception SYS_SSO_OIDC_ISSUER_URL_INVALID
     */
    public static function assertValidIssuerURL(string $issuerURL): void
    {
        if ($issuerURL === '') {
            return;
        }

        $parts = parse_url($issuerURL);
        if (filter_var($issuerURL, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new Exception('SYS_SSO_OIDC_ISSUER_URL_INVALID');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $isLoopback = in_array($host, array('localhost', '127.0.0.1', '::1'), true);

        if ($scheme !== 'https' && !($scheme === 'http' && $isLoopback)) {
            throw new Exception('SYS_SSO_OIDC_ISSUER_URL_INVALID');
        }
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
            // A stored issuer that does not satisfy the OIDC issuer rules would be
            // published in the discovery document and in every iss claim, so fail here
            // instead of serving a consistently wrong issuer identifier.
            self::assertValidIssuerURL($configuredIssuerURL);
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
        $this->normalizeLogoutUriFormValues($formValues);

        if (array_key_exists('ocl_userid_field', $formValues)) {
            $subjectField = (string) $formValues['ocl_userid_field'];

            // A client that was configured with a mutable identifier may keep it, so that
            // saving the form does not reassign the subject behind the relying party's
            // back, but no client may be moved to one.
            if (!in_array($subjectField, OIDCClient::getSupportedSubjectFields(), true)
                && $subjectField !== (string) $client->getValue('ocl_userid_field')
            ) {
                throw new Exception('SYS_SSO_USERID_FIELD_INVALID', array($subjectField));
            }
        }

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

    /**
     * Validate and normalize registered OIDC logout URIs.
     *
     * @throws Exception
     */
    private function normalizeLogoutUriFormValues(array &$formValues): void
    {
        if (array_key_exists('ocl_post_logout_redirect_uris', $formValues)) {
            $value = str_replace(array("\r\n", "\r"), "\n", trim((string) $formValues['ocl_post_logout_redirect_uris']));
            $uris = $value === '' ? array() : explode("\n", $value);

            foreach ($uris as &$uri) {
                $uri = trim($uri);
                $this->assertValidRegisteredLogoutUri($uri);
            }
            unset($uri);

            $formValues['ocl_post_logout_redirect_uris'] = implode("\n", array_values(array_unique($uris)));
        }

        foreach (array('ocl_frontchannel_logout_uri', 'ocl_backchannel_logout_uri') as $field) {
            if (!array_key_exists($field, $formValues)) {
                continue;
            }

            $uri = trim((string) $formValues[$field]);
            if ($uri !== '') {
                $this->assertValidRegisteredLogoutUri($uri);
            }

            $formValues[$field] = $uri;
        }
    }

    /**
     * @throws Exception
     */
    private function assertValidRegisteredLogoutUri(string $uri): void
    {
        $parts = parse_url($uri);
        if (filter_var($uri, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new Exception('SYS_SSO_OIDC_LOGOUT_URI_INVALID');
        }
 
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $isLoopback = in_array($host, array('localhost', '127.0.0.1', '::1'), true);

        if ($scheme !== 'https' && !($scheme === 'http' && $isLoopback)) {
            throw new Exception('SYS_SSO_OIDC_LOGOUT_URI_INVALID');
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


    /**
     * Return the authorization request with the parameters of a form POST normalized into
     * the query parameters of the request.
     *
     * OpenID Connect Core, section 3.1.2.1, requires the authorization endpoint to accept
     * GET and POST. The OAuth library and the Admidio helpers read the authorization
     * parameters from the query string, so a client that posts them is answered by moving
     * them there. The Admidio consent form posts to the same endpoint but keeps the
     * authorization parameters in the query string of its action URL; its own fields are
     * not authorization parameters and are left untouched.
     *
     * @return ServerRequestInterface
     * @throws OAuthServerException A parameter arrives twice with two different values.
     */
    private function getAuthorizationRequest(): ServerRequestInterface
    {
        $request = $this->getRequest();

        if (strtoupper($request->getMethod()) !== 'POST') {
            return $request;
        }

        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $request;
        }

        $queryParams = $request->getQueryParams();

        foreach (self::AUTHORIZATION_REQUEST_PARAMETERS as $parameter) {
            if (!array_key_exists($parameter, $body)) {
                continue;
            }

            if (!is_string($body[$parameter])) {
                throw OAuthServerException::invalidRequest($parameter);
            }

            // A parameter must not be sent twice with different values (RFC 6749,
            // section 3.1). Resolving such a conflict silently is what makes request
            // smuggling between the two parameter sources possible.
            if (array_key_exists($parameter, $queryParams) && $queryParams[$parameter] !== $body[$parameter]) {
                throw OAuthServerException::invalidRequest($parameter);
            }

            $queryParams[$parameter] = $body[$parameter];
        }

        return $request->withQueryParams($queryParams);
    }

    public function setupService() {
        global $gSettingsManager, $gLogger, $gCurrentSession;

        $authCodeTTL = new \DateInterval(
            'PT' . $this->getTokenLifetime('sso_oidc_auth_code_lifetime', self::DEFAULT_AUTH_CODE_LIFETIME) . 'S'
        );
        $accessTokenTTL = new \DateInterval(
            'PT' . $this->getTokenLifetime('sso_oidc_access_token_lifetime', self::DEFAULT_ACCESS_TOKEN_LIFETIME) . 'S'
        );
        $refreshTokenLifetime = $this->getTokenLifetime('sso_oidc_refresh_token_lifetime', self::DEFAULT_REFRESH_TOKEN_LIFETIME);
        $refreshTokenTTL = new \DateInterval('PT' . $refreshTokenLifetime . 'S');

        // Init our repositories
        $clientRepository = new ClientRepository($this->db);            // instance of ClientRepositoryInterface
        $scopeRepository = new ScopeRepository($this->db);              // instance of ScopeRepositoryInterface
        $accessTokenRepository = new AccessTokenRepository($this->db);  // instance of AccessTokenRepositoryInterface
        $authCodeRepository = new AuthCodeRepository($this->db);        // instance of AuthCodeRepositoryInterface
        $userRepository = new UserRepository($this->db);                // instance of UserRepositoryInterface // TODO_RK: Add user ID field and allowed Roles!
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
            $this->db,
            $refreshTokenLifetime,
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
        $grant = new OIDCAuthCodeGrant($authCodeRepository, $refreshTokenRepository, $authCodeTTL);
        $grant->setRefreshTokenTTL($refreshTokenTTL); // refresh tokens will expire after 1 month
        $server->enableGrantType($grant, $accessTokenTTL);

        $this->authCodeGrant = $grant;


        /* ***********************************************************************
        * RefreshToken Grant
        */
        $grant = new RefreshTokenGrant($refreshTokenRepository);
        $grant->setRefreshTokenTTL($refreshTokenTTL); // new refresh tokens will expire after 1 month
        $server->enableGrantType($grant, $accessTokenTTL);



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
     * Return a configured OIDC token lifetime in seconds.
     *
     * @param string $preferenceName
     * @param int $defaultLifetime
     * @return int
     */
    private function getTokenLifetime(string $preferenceName, int $defaultLifetime): int
    {
        global $gSettingsManager;
        $lifetime = (int)$gSettingsManager->get($preferenceName);
        return ($lifetime < 1) ? $defaultLifetime : $lifetime;
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


    /**
     * Validate RFC 7636 PKCE parameters for the current client.
     *
     * PKCE is mandatory when configured for the client. When a challenge is
     * supplied by another client, only S256 is accepted and the OAuth server
     * performs the corresponding code_verifier validation at the token endpoint.
     *
     * @throws OAuthServerException
     */
    private function validatePKCEAuthorizationRequest(ServerRequestInterface $request, OIDCClient $client): void 
    {
        $queryParams = $request->getQueryParams();
        $codeChallenge = $queryParams['code_challenge'] ?? null;
        $codeChallengeMethod = $queryParams['code_challenge_method'] ?? null;
        $hasChallenge = is_string($codeChallenge) && $codeChallenge !== '';

        if (!$hasChallenge) {
            if ($client->requiresPKCE()) {
                throw OAuthServerException::invalidRequest('code_challenge', 'This client requires PKCE for authorization-code requests.');
            }

            if ($codeChallengeMethod !== null && $codeChallengeMethod !== '') {
                throw OAuthServerException::invalidRequest('code_challenge', 'code_challenge_method cannot be used without code_challenge.');
            }
            return;
        }

        if (!is_string($codeChallengeMethod) || $codeChallengeMethod !== 'S256') {
            throw OAuthServerException::invalidRequest('code_challenge_method', 'Only the S256 PKCE code challenge method is supported.');
        }

        if (preg_match('/^[A-Za-z0-9\-_]{43}$/', $codeChallenge) !== 1) {
            throw OAuthServerException::invalidRequest('code_challenge', 'The S256 code challenge must be a 43-character base64url value.');
        }
    }

    public function handleAuthorizationRequest(): ResponseInterface {
        global $gProfileFields, $gSettingsManager, $gValidLogin, $gCurrentUserId, $gL10n, $gLogger, $gCurrentSession;

        if ($gSettingsManager->get('sso_oidc_enabled') !== '1') {
            throw new \Exception("SSO OIDC is not enabled");
        }

        $request = $this->getAuthorizationRequest();
        $response = new Response();
        try {
            if (!$this->isServiceSetup) {
                $this->setupService();
            }

            // Validate the HTTP request and return an AuthorizationRequest object.
            // League OAuth2 Server persists a supplied challenge in the
            // authorization code and validates code_verifier at the token endpoint.
            $authRequest = $this->authServer->validateAuthorizationRequest($request);
            self::$client = $authRequest->getClient();

            if (!self::$client instanceof OIDCClient) {
                throw OAuthServerException::invalidClient($request);
            }

            // Perform Admidio-specific PKCE policy validation here.
            // Verifying the PKCE code is done by league/oauth2-server.
            // AuthCodeGrant persists code_challenge and
            // code_challenge_method in the encrypted authorization code.
            // At the token endpoint, AuthCodeGrant validates code_verifier
            // against that persisted challenge before issuing tokens.
            $this->validatePKCEAuthorizationRequest($request, self::$client);
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
            // An Admidio session holds exactly one account, so account selection is the
            // login form, where the user can continue with the current account or sign
            // in with a different one.
            if (!$reauthenticationCompleted
                && (in_array('login', $promptValues, true) || in_array('select_account', $promptValues, true))
            ) {
                $authenticationRequired = true;
            }
            if ($authenticationRequired && in_array('none', $promptValues, true)) {
                return $this->createAuthorizationErrorResponse($authRequest, $response, 'login_required');
            }

            // The user explicitly cancelled the login/authorization process.
            $cancelAuthorization = admFuncVariableIsValid($_GET, 'sso_cancel', 'bool', array('defaultValue' => false));
            if ($cancelAuthorization) {
                unset($_SESSION['login_forward_url'], $_SESSION['login_forward_url_post']);
                return $this->createAuthorizationErrorResponse($authRequest, $response, 'access_denied', $gL10n->get('SYS_SSO_LOGIN_CANCELLED'));
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
            // If the client is trusted, no consent is required, except for prompt=consent.
            $consentRequired = in_array('consent', $promptValues, true)
                || (!self::$client->isTrusted() && !$this->hasOIDCConsent($authRequest));

            // Only a post of the Admidio consent form carries a decision. A client that
            // posts its authorization request has no Admidio form behind it.
            if (array_key_exists('adm_button_approve', $_POST) || array_key_exists('adm_button_deny', $_POST)) {
                $form = $gCurrentSession->getFormObject((string) ($_POST['adm_csrf_token'] ?? ''));
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

            $authenticationMethods = preg_split('/\s+/', trim((string) $gCurrentSession->getValue('ses_authentication_methods')));
            $authenticationMethods = array_values(array_filter($authenticationMethods, static function ($method) {
                return $method !== '';
            }));

            if (in_array(Session::AUTHENTICATION_METHOD_AUTO_LOGIN, $authenticationMethods, true)) {
                $authenticationContext = self::AUTHENTICATION_CONTEXT_AUTO_LOGIN;
            } elseif (in_array('otp', $authenticationMethods, true)) {
                $authenticationContext = self::AUTHENTICATION_CONTEXT_PASSWORD_TOTP;
            } else {
                $authenticationContext = self::AUTHENTICATION_CONTEXT_PASSWORD;
            }

            /*
            * amr may only name authentication methods of the IANA registry (RFC 8176), so
            * the internal marker of an auto login session is not reported as one.
            */
            $authenticationMethods = array_values(array_diff(
                $authenticationMethods,
                array(Session::AUTHENTICATION_METHOD_AUTO_LOGIN)
            ));

            $externalSessionId = $gCurrentSession->getExternalSessionId();
            if ($externalSessionId !== '') {
                $this->authCodeGrant->setExternalSessionId($externalSessionId);
            } else {
                $gLogger->warning('OIDC authorization continues without sid because the authenticated session has no external session identifier.');
            }

            $this->authCodeGrant->setAuthenticationTime((int) $gCurrentSession->getValue('ses_authentication_time', 'U'));
            $this->authCodeGrant->setAuthenticationMethods($authenticationMethods);
            $this->authCodeGrant->setAuthenticationContext($authenticationContext);

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
            throw $exception;
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
            throw $exception;
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
            if ($this->accessTokenRepository->isAccessTokenRevoked($tokenId)) {
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
            throw $exception;
        }
    }

    public function handleJWKSRequest() {
        global $gSettingsManager;

        // Private key and Certificate for signatures
        $keyService = new KeyService($this->db);
        $signingKeyId = (int) $gSettingsManager->get('sso_oidc_signing_key');
        $signingKey = $keyService->getUsableKey($signingKeyId, KeyService::USAGE_OIDC_SIGNING);
        $signingJwk = $this->createJsonWebKey($signingKey);

        if ($signingJwk === null) {
            throw new \Exception('SYS_SSO_PUBLIC_KEY_INVALID');
        }

        // The key that is currently used for signatures comes first, followed by every
        // other key of the organization that is still usable for OIDC signatures. A key
        // that was replaced in the preferences therefore stays published until it is
        // deactivated, so ID tokens and logout tokens signed with it can still be
        // validated by the relying parties during the rotation.
        $jwks = array('keys' => array($signingJwk));

        foreach ($keyService->getKeysData(true, KeyService::USAGE_OIDC_SIGNING) as $keyData) {
            $keyId = (int) $keyData['key_id'];

            if ($keyId === $signingKeyId) {
                continue;
            }

            $jwk = $this->createJsonWebKey(new Key($this->db, $keyId));
            if ($jwk !== null) {
                $jwks['keys'][] = $jwk;
            }
        }

        // Return as JSON
        return new JsonResponse($jwks);
    }

    /**
     * Convert the public part of a cryptographic key to a JSON Web Key.
     *
     * @param Key $key Cryptographic key of the current organization.
     * @return array|null The JWK, or null if the stored public key is not a usable RSA key.
     */
    private function createJsonWebKey(Key $key): ?array
    {
        $publicKey = openssl_pkey_get_public((string) $key->getValue('key_public'));

        if ($publicKey === false) {
            return null;
        }

        $keyDetails = openssl_pkey_get_details($publicKey);

        if ($keyDetails === false || !isset($keyDetails['rsa']['n'], $keyDetails['rsa']['e'])) {
            return null;
        }

        return array(
            'kty' => 'RSA',
            'use' => 'sig',
            'kid' => $key->getValue('key_uuid'),
            'alg' => 'RS256',
            // Extract the modulus and exponent
            'n'   => rtrim(strtr(base64_encode($keyDetails['rsa']['n']), '+/', '-_'), '='),
            'e'   => rtrim(strtr(base64_encode($keyDetails['rsa']['e']), '+/', '-_'), '=')
        );
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
            "end_session_endpoint" => "{$issuer}/logout",
            "frontchannel_logout_supported" => true,
            "frontchannel_logout_session_supported" => true,
            "backchannel_logout_supported" => true,
            "backchannel_logout_session_supported" => true,
            "scopes_supported" => OIDCClient::getSupportedScopes(),
            "response_types_supported" => ["code"],
            "response_modes_supported" => ["query"],
            "grant_types_supported" => ["authorization_code", "refresh_token"],
            "code_challenge_methods_supported" => ["S256"],
            "subject_types_supported" => ["public"],
            "id_token_signing_alg_values_supported" => ["RS256"],
            "token_endpoint_auth_methods_supported" => ["client_secret_post", "client_secret_basic"],
            "introspection_endpoint_auth_methods_supported" => ["client_secret_basic", "client_secret_post"],
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
            // "acr_values_supported" => [self::AUTHENTICATION_CONTEXT_PASSWORD, self::AUTHENTICATION_CONTEXT_PASSWORD_TOTP],
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
            $tokenClient = $token->getClient();

            if (!$tokenClient instanceof OIDCClient
                || !hash_equals($clientId, $tokenClient->getIdentifier())
            ) {
                return new JsonResponse(['active' => false]);
            }

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

    /**
     * Handle OpenID Connect RP-Initiated Logout.
     *
     * Requests without id_token_hint are confirmed by the user before the OP
     * session is terminated. Redirect URIs are accepted only after exact
     * registration for an identified client.
     */
    public function handleLogoutRequest(): ResponseInterface
    {
        global $gLogger, $gCurrentSession, $gCurrentUser, $gMenu, $gValidLogin, $gCurrentOrgId;

        $request = $this->getRequest();
        $participantService = new OIDCSessionParticipantService($this->db);
        $participantService->removeExpiredParticipants();

        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : array();

        if (array_key_exists('adm_button_cancel', $body)
            || array_key_exists('adm_button_logout', $body)
        ) {
            if (!isset($_POST['adm_csrf_token']) || !is_string($_POST['adm_csrf_token'])) {
                return $this->createOIDCErrorResponse('invalid_request', 'Invalid logout confirmation.', 400);
            }

            $form = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $form->validate($_POST);
        }

        if (array_key_exists('adm_button_cancel', $body)) {
            unset($_SESSION['oidc_logout_confirmation']);

            return $this->createOIDCErrorResponse(
                'access_denied',
                'The logout request was cancelled by the user.',
                400
            );
        }

        if (array_key_exists('adm_button_logout', $body)) {
            $context = $_SESSION['oidc_logout_confirmation'] ?? null;
            unset($_SESSION['oidc_logout_confirmation']);

            if (!is_array($context)
                || !isset($context['requested_at'], $context['external_session_id'])
                || (int) $context['requested_at'] < time() - 300
            ) {
                return $this->createOIDCErrorResponse(
                    'invalid_request',
                    'The logout confirmation has expired.',
                    400
                );
            }

            return $this->completeOIDCLogout(
                (string) $context['external_session_id'],
                isset($context['post_logout_redirect_uri'])
                    ? (string) $context['post_logout_redirect_uri']
                    : null,
                isset($context['state']) ? (string) $context['state'] : null
            );
        }

        $params = array_merge($request->getQueryParams(), $body);
        $idTokenHint = $params['id_token_hint'] ?? null;
        $clientIdentifier = $params['client_id'] ?? null;
        $postLogoutRedirectUri = $params['post_logout_redirect_uri'] ?? null;
        $state = $params['state'] ?? null;

        foreach (array(
            'id_token_hint' => $idTokenHint,
            'client_id' => $clientIdentifier,
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
            'state' => $state
        ) as $parameter => $value) {
            if ($value !== null && !is_string($value)) {
                return $this->createOIDCErrorResponse(
                    'invalid_request',
                    'Invalid ' . $parameter . '.',
                    400
                );
            }
        }

        $hintClaims = null;
        if (is_string($idTokenHint) && $idTokenHint !== '') {
            $requestedClientIdentifier = is_string($clientIdentifier) && $clientIdentifier !== ''
                ? $clientIdentifier
                : null;

            try {
                $hintClaims = $this->validateLogoutIdTokenHint($idTokenHint, $requestedClientIdentifier);
            } catch (\Throwable $exception) {
                $gLogger->warning('OIDC logout rejected an ID token hint.', array('exception' => $exception));
                return $this->createOIDCErrorResponse(
                    'invalid_request',
                    'Invalid id_token_hint.',
                    400
                );
            }

            $clientIdentifier = $hintClaims['client_id'];
        }

        $client = null;
        if (is_string($clientIdentifier) && $clientIdentifier !== '') {
            $client = $this->createClientObject(null, $clientIdentifier);
            if ($client->isNewRecord() || !$client->isEnabled()) {
                return $this->createOIDCErrorResponse(
                    'invalid_request',
                    'Unknown or disabled client.',
                    400
                );
            }
        }

        if ($postLogoutRedirectUri !== null
            && ($client === null
                || !$client->isPostLogoutRedirectUriAllowed($postLogoutRedirectUri))
        ) {
            return $this->createOIDCErrorResponse(
                'invalid_request',
                'Unregistered post_logout_redirect_uri.',
                400
            );
        }

        $externalSessionId = (string) $gCurrentSession->getValue(
            'ses_external_session_id',
            'database'
        );

        if ($hintClaims !== null && $hintClaims['sid'] !== null) {
            $hintSessionId = $hintClaims['sid'];
            $matchesCurrentSession = $externalSessionId === ''
                || hash_equals($externalSessionId, $hintSessionId);

            if ($matchesCurrentSession) {
                try {
                    $participantService->assertParticipant($gCurrentOrgId, $hintSessionId, (int) $client->getValue('ocl_id'), $hintClaims['sub']);
                    return $this->completeOIDCLogout($hintSessionId, $postLogoutRedirectUri, $state);
                } catch (\Throwable $exception) {
                    // The hint is valid but cannot be tied to an active tracked
                    // participant. Continue with explicit user confirmation.
                }
            }
        }

        if ($externalSessionId === '') {
            return $this->createOIDCErrorResponse(
                'invalid_request',
                'The current session cannot be used for OpenID Connect logout.',
                400
            );
        }
        $_SESSION['oidc_logout_confirmation'] = array(
            'external_session_id' => $externalSessionId,
            'client_id' => $client?->getIdentifier() ?? '',
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
            'state' => $state,
            'requested_at' => time()
        );

        $presenter = new OIDCLogoutPresenter(
            $client instanceof OIDCClient ? $client->readableName() : ''
        );
        $presenter->createConfirmationForm();
        $presenter->show();
        exit;
    }

    private function completeOIDCLogout(
        string $externalSessionId,
        ?string $postLogoutRedirectUri,
        ?string $state
    ): ResponseInterface {
        global $gCurrentSession, $gCurrentUser, $gMenu, $gValidLogin;

        $gValidLogin = false;
        $gCurrentSession->logout();
        $gCurrentUser->clear();
        $gMenu->initialize();

        $redirectLocation = null;
        if ($postLogoutRedirectUri !== null) {
            $redirectLocation = $postLogoutRedirectUri;
            if ($state !== null && $state !== '') {
                $redirectLocation .= (str_contains($redirectLocation, '?') ? '&' : '?')
                    . http_build_query(array('state' => $state));
            }
        }

        /*
        * Hand the logout to the SAML service, which notifies the OIDC back-channel clients,
        * loads the OIDC front-channel clients and then walks the SAML service providers
        * through the browser. A relying party that starts the logout this way therefore
        * ends the sessions of both protocols.
        */
        if ($externalSessionId !== '') {
            return (new SAMLService($this->db, $gCurrentUser))
                ->startSessionLogout($externalSessionId, $redirectLocation);
        }

        if ($redirectLocation !== null) {
            return (new Response())
                ->withStatus(302)
                ->withHeader('Location', $redirectLocation)
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Pragma', 'no-cache');
        }

        return new JsonResponse(
            array('logout' => true), 200,
            array('Cache-Control' => 'no-store', 'Pragma' => 'no-cache')
        );
    }

    private function createOIDCErrorResponse(
        string $error,
        string $description,
        int $statusCode = 400
    ): JsonResponse {
        return new JsonResponse(
            array(
                'error' => $error,
                'error_description' => $description
            ),
            $statusCode,
            array(
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache'
            )
        );
    }

    /**
     * Validate the signature and logout-relevant claims of an ID token hint.
     *
     * Expiration is intentionally not enforced: a token that represented the
     * still-active OP session remains usable as a logout hint.
     *
     * The sid claim is optional. If it is absent or unusable, the caller must
     * require explicit user confirmation before terminating the OP session.
     *
     * @return array{client_id:string,sub:string,sid:?string}
     */
    private function validateLogoutIdTokenHint(string $idTokenHint, ?string $requestedClientIdentifier = null): array
    {
        global $gSettingsManager;

        $keyService = new KeyService($this->db);
        $key = $keyService->getUsableKey(
            (int) $gSettingsManager->get('sso_oidc_signing_key'),
            KeyService::USAGE_OIDC_SIGNING
        );

        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText((string) $key->getValue('key_private')),
            InMemory::plainText((string) $key->getValue('key_public'))
        );

        $token = $configuration->parser()->parse($idTokenHint);
        if (!$token instanceof Plain
            || !$configuration->validator()->validate(
                $token,
                new SignedWith($configuration->signer(), $configuration->verificationKey()),
                new IssuedBy($this->issuerURL)
            )
        ) {
            throw new Exception('Invalid ID token hint.');
        }

        $audience = $token->claims()->get('aud', array());
        if (is_string($audience)) {
            $audience = array($audience);
        }
        if (!is_array($audience)) {
            throw new Exception('The ID token hint has an invalid audience.');
        }

        $audience = array_values(array_filter($audience, static fn ($value): bool => is_string($value) && $value !== ''));

        if ($requestedClientIdentifier !== null) {
            if (!in_array($requestedClientIdentifier, $audience, true)) {
                throw new Exception('client_id does not match id_token_hint.');
            }
            $clientIdentifier = $requestedClientIdentifier;
        } elseif (count($audience) === 1) {
            $clientIdentifier = $audience[0];
        } else {
            throw new Exception('The ID token hint audience does not identify one client.');
        }

        $subject = $token->claims()->get('sub', null);
        if (!is_string($subject) || $subject === '') {
            throw new Exception('The ID token hint has no usable sub claim.');
        }
        $sessionId = $token->claims()->get('sid', null);
        if (!is_string($sessionId) || $sessionId === '') {
            $sessionId = null;
        }

        return array(
            'client_id' => $clientIdentifier,
            'sub' => $subject,
            'sid' => $sessionId
        );
    }

    private function getRequestedScopeNames(AuthorizationRequestInterface $authRequest): array
    {
        return array_map(
            static fn ($scope): string => $scope->getIdentifier(),
            $authRequest->getScopes()
        );
    }

    /**
     * Fingerprint of what a client actually receives once the user has approved it.
     *
     * A consent stores the approved scopes, but what a scope releases is decided by the
     * claim and role mapping of the client. An administrator could otherwise map further
     * profile fields into a scope the user has already approved. Storing a fingerprint of
     * that mapping with the consent makes such a change visible, so the user is asked
     * again instead of releasing more than they agreed to. The fingerprint describes the
     * permission, it does not duplicate any profile value.
     *
     * @param OIDCClient $client Client whose release policy should be described.
     * @return string SHA-256 fingerprint of the effective claim release policy.
     * @throws \JsonException
     */
    private function getReleasePolicyHash(OIDCClient $client): string
    {
        $fieldMapping = $client->getFieldMapping();
        ksort($fieldMapping);

        $roleMapping = $client->getRoleMapping();
        ksort($roleMapping);

        return hash('sha256', json_encode(
            array(
                'fields' => $fieldMapping,
                'fields_catchall' => $client->getFieldMappingCatchall(),
                'roles' => $roleMapping,
                'roles_catchall' => $client->getRoleMappingCatchall()
            ),
            JSON_THROW_ON_ERROR
        ));
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

        if (!$consent->matchesReleasePolicy($this->getReleasePolicyHash(self::$client))) {
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
        $consent->setValue('oco_policy_hash', $this->getReleasePolicyHash(self::$client));
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
            if (!in_array($promptValue, array('none', 'login', 'consent', 'select_account'), true)) {
                throw OAuthServerException::invalidRequest('prompt');
            }
        }

        if (in_array('none', $promptValues, true) && count($promptValues) > 1) {
            throw OAuthServerException::invalidRequest('prompt');
        }

        return array_values(array_unique($promptValues));
    }
    
    private function createAuthorizationErrorResponse(AuthorizationRequestInterface $authRequest, ResponseInterface $response, string $error,
    string $errorDescription = ''): ResponseInterface 
    {
        $redirectURI = $authRequest->getRedirectUri();

        if ($redirectURI === null) {
            return new JsonResponse(array('error' => $error), 400);
        }

        $parameters = array('error' => $error);
        if ($errorDescription !== '') {
            $parameters['error_description'] = $errorDescription;
        }
        if ($authRequest->getState() !== null) {
            $parameters['state'] = $authRequest->getState();
        }

        $separator = str_contains($redirectURI, '?') ? '&' : '?';

        return $response
            ->withStatus(302)
            ->withHeader('Location', $redirectURI . $separator . http_build_query($parameters));
    }
}

