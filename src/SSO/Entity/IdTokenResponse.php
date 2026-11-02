<?php
namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use Admidio\Infrastructure\Database;
use Admidio\SSO\Service\OIDCSessionParticipantService;
use OpenIDConnectServer\Entities\ClaimSetEntity;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use OpenIDConnectServer\ClaimExtractor;

/** 
 * Custom implementation of the IdTokenResponse class. 
 * Purpose: 
 * (1) The claims sent to the client are specific to the client,
 *     but the default implementation does not pass the client object (or 
 *     even the client id, which would allow retrieving the config from the
 *     database) to the ID Token generating code.
 *     In addition to the implementation here, we also need to store the
 *     client earlier, when access to it is still available, and attach it to 
 *     the access Token
 * (2) The issuer URL is defined as the full URL, but the OIDC server library
 *     assumes that only to be the host ('https://' . $_SERVER['HTTP_HOST'),
 *     which is wrong in the Admidio case (https://[HTTP_HOST]/modules/sso/index.php/oidc/)
 * (3) Store the nonce that will be added to the 
 */
// Since the 'custom' scope's claims depend on the client-specific 
class IdTokenResponse extends \OpenIDConnectServer\IdTokenResponse
{
    protected ?string $nonce = null;
    private string $issuerURL;
    protected ?int $authenticationTime = null;
    protected string $externalSessionId = '';
    protected array $authenticationMethods = array();
    protected ?string $authenticationContext = null;
    private Database $database;
    private int $participantLifetime;
    /** @var bool Persist the participant only while the session of the sid is still logged in (refresh) */
    private bool $requireActiveSession = false;

    public function __construct(
        IdentityProviderInterface $identityProvider,
        ClaimExtractor $claimExtractor,
        string $issuerURL,
        Database $database,
        int $participantLifetime,
        ?string $keyIdentifier = null
    ) {
        parent::__construct($identityProvider, $claimExtractor, $keyIdentifier);
        $this->issuerURL = $issuerURL;
        $this->database = $database;
        $this->participantLifetime = $participantLifetime;
    }

    /**
     * @param AccessTokenEntityInterface $accessToken
     * @return array
     */
    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        /** @var OIDCClient */
        $client = $accessToken->getClient();
        // Add the custom scope (client-dependent! to the claims builder, if it does not exist yet)
        if (!$this->claimExtractor->hasClaimSet('custom')) {
            $this->claimExtractor->addClaimSet(
                new ClaimSetEntity('custom', array_keys($client->getFieldMapping()))
            );
        }
        $extraParams = parent::getExtraParams($accessToken);
        $this->persistSessionParticipant($accessToken);

        return $extraParams;
    }

    /**
     * Persist the client participation represented by the issued ID token.
     */
    private function persistSessionParticipant(AccessTokenEntityInterface $accessToken): void
    {
        global $gCurrentOrgId, $gLogger;
        if ($this->externalSessionId === '') {
            $gLogger->warning('OIDC session participant was not persisted because no external session identifier is available.');
            return;
        }

        // A refreshed token must not bring the participant of a session back that was logged out
        if ($this->requireActiveSession && !$this->isSessionActive()) {
            return;
        }

        $client = $accessToken->getClient();
        if (!$client instanceof OIDCClient) {
            throw new \RuntimeException('Cannot persist an OIDC participant for an invalid client.');
        }

        if (!$accessToken instanceof TokenEntity) {
            throw new \RuntimeException('Cannot persist an OIDC participant for an unsupported access token entity.');
        }

        $user = $accessToken->getUser();
        $userId = (int) $user->getValue('usr_id');
        if ($userId <= 0) {
            throw new \RuntimeException('Cannot persist an OIDC participant without a valid Admidio user.');
        }

        $participantExpiresAt = (new \DateTimeImmutable())->modify(
            '+' . $this->participantLifetime . ' seconds'
        );
        if ($accessToken->getExpiryDateTime() > $participantExpiresAt) {
            $participantExpiresAt = $accessToken->getExpiryDateTime();
        }

        $participantService = new OIDCSessionParticipantService($this->database);
        $participantService->persistParticipant(
            $gCurrentOrgId,
            $userId,
            (int) $client->getValue('ocl_id'),
            $this->externalSessionId,
            (string) $accessToken->getUserIdentifier(),
            $participantExpiresAt
        );
    }

    // The issuer in the JWT token MUST be the same as the issuer in the discovery document
    // (https://openid.net/specs/openid-connect-discovery-1_0.html#IssuerDiscovery)
    // The issuer is the URL of the OpenID Provider (OP) that issued the ID token.
    // The OIDC library sets the issuer to the server name only ('https://' . $_SERVER['HTTP_HOST'),
    // so we need to override the correct issuerURL here!

    protected function getBuilder(AccessTokenEntityInterface $accessToken, UserEntityInterface $userEntity)
    {
        global $gLogger;

        $builder = parent::getBuilder($accessToken, $userEntity);
        if (!empty($this->nonce)) {
            $builder = $builder->withClaim('nonce', $this->nonce);
        }
        /*
        * An absent auth_time says that the time of the authentication is not known, while
        * a zero would assert that the user authenticated at the start of the epoch.
        */
        if ($this->authenticationTime !== null && $this->authenticationTime > 0) {
            $builder = $builder->withClaim('auth_time', $this->authenticationTime);
        }
        if ($this->externalSessionId !== '') {
            $builder = $builder->withClaim('sid', $this->externalSessionId);
        } else {
            $gLogger->warning('OIDC ID token is issued without a sid claim because no external session identifier is available.');
        }
        if (!empty($this->authenticationMethods)) {
            $builder = $builder->withClaim('amr', $this->authenticationMethods);
        }
        if ($this->authenticationContext !== null) {
            $builder = $builder->withClaim('acr', $this->authenticationContext);
        }
        return $builder->issuedBy($this->issuerURL);
    }

    /**
     * Whether a user is still logged in to the Admidio session that the sid names. Logging out
     * clears the user of the session but keeps its row.
     */
    private function isSessionActive(): bool
    {
        return (bool) $this->database->queryPrepared(
            'SELECT 1 FROM ' . TBL_SESSIONS . ' WHERE ses_external_session_id = ? AND ses_usr_id IS NOT NULL',
            array($this->externalSessionId)
        )->fetchColumn();
    }

    /**
     * Take what the authorization code knows about the authentication of the user: the time
     * (auth_time), the session (sid), the methods (amr) and the context (acr).
     *
     * An ID token from a refresh states the same authentication as the original one (OpenID
     * Connect Core 12.2), but not its nonce.
     *
     * @param AuthCodeEntity $authCode The code of the authentication
     * @param bool $isRefresh Whether the ID token is issued in exchange for a refresh token
     */
    public function setAuthenticationFromAuthCode(AuthCodeEntity $authCode, bool $isRefresh = false): void
    {
        global $gLogger;

        $prefix = $authCode->getColumnPrefix();

        if (!$isRefresh) {
            $nonce = $authCode->getValue($prefix . '_nonce');
            if (!empty($nonce)) {
                $this->setNonce($nonce);
            }
        }
        $this->requireActiveSession = $isRefresh;

        $authenticationTime = (int) $authCode->getValue($prefix . '_auth_time', 'U');
        $externalSessionId = (string) $authCode->getValue($prefix . '_external_session_id', 'database');
        $authenticationMethods = preg_split('/\s+/',
            trim((string) $authCode->getValue($prefix . '_authentication_methods')),
            -1, PREG_SPLIT_NO_EMPTY
        );
        $authenticationContext = (string) $authCode->getValue($prefix . '_authentication_context');

        if ($externalSessionId !== '') {
            $this->setExternalSessionId($externalSessionId);
        } else {
            $gLogger->warning('OIDC authorization code has no external session identifier; the ID token will be issued without sid.');
        }

        if ($authenticationTime > 0) {
            $this->setAuthenticationTime($authenticationTime);
        }

        if (is_array($authenticationMethods)) {
            $this->setAuthenticationMethods($authenticationMethods);
        }

        if ($authenticationContext !== '') {
            $this->setAuthenticationContext($authenticationContext);
        }
    }

    public function getNonce(): string|null {   
        return $this->nonce;
    }
    public function setNonce(?string $nonce) {
        $this->nonce = $nonce;
    }

    public function setAuthenticationTime(int $authenticationTime): void
    {
        $this->authenticationTime = $authenticationTime;
    }

    public function setExternalSessionId(string $externalSessionId): void
    {
        if ($externalSessionId === '') {
            throw new \InvalidArgumentException('The external session identifier must not be empty.');
        }
        $this->externalSessionId = $externalSessionId;
    }

    /**
     * @param array<int,string> $authenticationMethods
     */
    public function setAuthenticationMethods(array $authenticationMethods): void
    {
        $this->authenticationMethods = $authenticationMethods;
    }

    public function setAuthenticationContext(string $authenticationContext): void
    {
         $this->authenticationContext = $authenticationContext;
     }


}
