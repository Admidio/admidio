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
    protected ?string $nonce;
    private string $issuerURL;
    protected ?int $authenticationTime = null;
    protected string $externalSessionId = '';
    protected array $authenticationMethods = array();
    protected ?string $authenticationContext = null;
    private Database $database;

    public function __construct(
        IdentityProviderInterface $identityProvider,
        ClaimExtractor $claimExtractor,
        string $issuerURL,
        Database $database,
        ?string $keyIdentifier = null
    ) {
        parent::__construct($identityProvider, $claimExtractor, $keyIdentifier);
        $this->issuerURL = $issuerURL;
        $this->database = $database;
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
        global $gLogger;
        if ($this->externalSessionId === '') {
            $gLogger->warning('OIDC session participant was not persisted because no external session identifier is available.');
            return;
        }

        $client = $accessToken->getClient();
        if (!$client instanceof OIDCClient) {
            throw new \RuntimeException('Cannot persist an OIDC participant for an invalid client.');
        }

        $userId = (int) $accessToken->getUserIdentifier();
        if ($userId <= 0) {
            throw new \RuntimeException('Cannot persist an OIDC participant without a valid user identifier.');
        }

        $participantService = new OIDCSessionParticipantService($this->database);
        $participantService->persistParticipant(
            (int) $client->getValue('ocl_org_id'),
            $userId,
            (int) $client->getValue('ocl_id'),
            $this->externalSessionId,
            (string) $accessToken->getUserIdentifier(),
            $accessToken->getExpiryDateTime()
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
        if ($this->authenticationTime !== null) {
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
