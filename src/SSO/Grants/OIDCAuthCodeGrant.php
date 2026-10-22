<?php

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 */

namespace Admidio\SSO\Grants;

use DateInterval;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Entity\AuthCodeEntity;
use Admidio\SSO\Entity\IdTokenResponse;

/**
 * Custom AuthCodeGrant class to support nonces. The nonce in the auth request is stored 
 * temporarily in the class, and later alongside the auth code in the database.
 * The ID Token generation can then directly retrieve the nonce for the given auth code 
 * from the database and include it in the ID token. 
 */
class OIDCAuthCodeGrant extends AuthCodeGrant
{
    protected ?string $nonce = null;
    protected ?int $authenticationTime = null;
    protected string $externalSessionId = '';
    protected array $authenticationMethods = array();
    protected ?string $authenticationContext = null;

    public function __construct(
        AuthCodeRepositoryInterface $authCodeRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        DateInterval $authCodeTTL
    ) {
        parent::__construct($authCodeRepository, $refreshTokenRepository, $authCodeTTL);
    }

    public function setAuthenticationTime(int $authenticationTime): void {
        $this->authenticationTime = $authenticationTime;
    }

    public function setExternalSessionId(string $externalSessionId): void {
        if ($externalSessionId === '') {
            throw new \InvalidArgumentException('The external session identifier must not be empty.');
        }
        $this->externalSessionId = $externalSessionId;
    }

    public function setAuthenticationMethods(array $authenticationMethods): void {
        $this->authenticationMethods = $authenticationMethods;
    }

    public function setAuthenticationContext(string $authenticationContext): void {
        $this->authenticationContext = $authenticationContext;
    }

    public function validateAuthorizationRequest(ServerRequestInterface $request): AuthorizationRequestInterface
    {
        $authorizationRequest = parent::validateAuthorizationRequest($request);
        $this->nonce = $this->getQueryStringParameter('nonce', $request);
        return $authorizationRequest;
    }
    
    protected function issueAuthCode(
        DateInterval $authCodeTTL,
        ClientEntityInterface $client,
        string $userIdentifier,
        ?string $redirectUri,
        array $scopes = []
    ): AuthCodeEntityInterface {
        $authCode = parent::issueAuthCode($authCodeTTL, $client, $userIdentifier, $redirectUri, $scopes);
        if (!$authCode instanceof AuthCodeEntity) {
            throw OAuthServerException::serverError('Authorization code is not an instance of AuthCodeEntity.');
        }
        $authCode->setValue($authCode->getColumnPrefix() . '_nonce', $this->nonce);
        $authCode->setValue($authCode->getColumnPrefix() . '_auth_time', $this->authenticationTime);
        $authCode->setValue($authCode->getColumnPrefix() . '_external_session_id', $this->externalSessionId);
        $authCode->setValue($authCode->getColumnPrefix() . '_authentication_methods', implode(' ', $this->authenticationMethods));
        $authCode->setValue($authCode->getColumnPrefix() . '_authentication_context', $this->authenticationContext);
        $authCode->save();
        return $authCode;
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        global $gLogger;

        $responseType = parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
        if (!$responseType instanceof IdTokenResponse) {
            throw OAuthServerException::serverError('Response type is not an instance of IdTokenResponse.');
        }

        // If we arrive here, the auth code was valid -> No need to check validity again!
        $encryptedAuthCode = $this->getRequestParameter('code', $request);
        $authCodePayload = json_decode($this->decrypt($encryptedAuthCode));

        // Load the AuthCode from the DB (including the nonce) and pass on the nonce to the response type ()
        if (!$this->authCodeRepository instanceof AuthCodeRepository) {
            throw OAuthServerException::serverError('Invalid authorization code repository.');
        }

        $authCode = $this->authCodeRepository->getToken($authCodePayload->auth_code_id);
        if (!($authCode->isNewRecord())) {
            $nonce = $authCode->getValue($authCode->getColumnPrefix() . '_nonce');
            if (!empty($nonce)) {
                $responseType->setNonce($nonce);
            }

            $authenticationTime = (int) $authCode->getValue($authCode->getColumnPrefix() . '_auth_time', 'U');
            $externalSessionId = (string) $authCode->getValue($authCode->getColumnPrefix() . '_external_session_id', 'database');
            $authenticationMethods = preg_split('/\s+/',
                trim((string) $authCode->getValue($authCode->getColumnPrefix() . '_authentication_methods')),
                -1, PREG_SPLIT_NO_EMPTY
            );
            $authenticationContext = (string) $authCode->getValue($authCode->getColumnPrefix() . '_authentication_context');

            if ($externalSessionId !== '') {
                $responseType->setExternalSessionId($externalSessionId);
            } else {
                $gLogger->warning('OIDC authorization code has no external session identifier; the ID token will be issued without sid.');
             }

            if ($authenticationTime > 0) {
                $responseType->setAuthenticationTime($authenticationTime);
            }

            if (is_array($authenticationMethods)) {
                $responseType->setAuthenticationMethods($authenticationMethods);
            }

            if ($authenticationContext !== '') {
                $responseType->setAuthenticationContext($authenticationContext);
            }
        }
        return $responseType;
    }

    /**
     * WORKAROUND for clients using basic http authorization and clientIDs that 
     * contain colons... The OIDC spec (rather: basic auth spec) does not allow
     * usernames with colon, but the URL contains a colon and is used by many 
     * OpenID RPs. -> Disregard the spec if the credentials string starts with 
     * http: or https: and treat the first colon as part of the username!
     * 
     * Retrieve HTTP Basic Auth credentials with the Authorization header
     * of a request. First index of the returned array is the username,
     * second is the password (so list() will work). If the header does
     * not exist, or is otherwise an invalid HTTP Basic header, return
     * [null, null].
     *
     * @return array{0:non-empty-string,1:string}|array{0:null,1:null}
     */
    protected function getBasicAuthCredentials(ServerRequestInterface $request): array
    {
        [$username, $password] = parent::getBasicAuthCredentials($request);
        // Workaround for clients that use URL-encoding of the http basic auth (even though the spec says to directly base64-encode the user:password string!)
        if (!empty($username)) {
            $decoded = urldecode($username);
            if (urlencode($decoded) == $username) {
                $username = $decoded;
            }
        }
        if (!empty($password)) {
            $decoded = urldecode($password);
            if (urlencode($decoded) == $password) {
                $password = $decoded;
            }
        }

        if (($username == "http" || $username == "https") && 
            str_starts_with($password, "//") &&
            str_contains($password, ':') ) {
            [$username2, $password] = explode(':', $password, 2);
            $username = $username . ':' . $username2;
        }
        return [$username, $password];
    }
}

