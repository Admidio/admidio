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
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Entity\AuthCodeEntity;
use Admidio\SSO\Entity\TokenEntity;
use Admidio\SSO\Entity\IdTokenResponse;

/**
 * Custom AuthCodeGrant class to support nonces. The nonce in the auth request is stored 
 * temporarily in the class, and later alongside the auth code in the database.
 * The ID Token generation can then directly retrieve the nonce for the given auth code 
 * from the database and include it in the ID token. 
 */
class OIDCAuthCodeGrant extends AuthCodeGrant
{
    use ClientCredentialsTrait;

    protected ?string $nonce = null;
    protected ?int $authenticationTime = null;
    protected string $externalSessionId = '';
    protected array $authenticationMethods = array();
    protected ?string $authenticationContext = null;
    /** Error code of the exception for a redirect URI that is not registered for the client */
    public const ERROR_REDIRECT_URI_NOT_REGISTERED = 1001;

    /** @var array<int,TokenEntity> Tokens issued by the current code exchange, linked to the code afterwards */
    protected array $issuedTokens = array();

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
        $this->rejectUnsupportedRequest($request);
        $authorizationRequest = parent::validateAuthorizationRequest($request);
        $this->nonce = $this->getQueryStringParameter('nonce', $request);
        return $authorizationRequest;
    }

    /**
     * Reject an authorization request that this OP cannot serve: a response_type other than
     * "code", or a request object passed by value (request) or by reference (request_uri),
     * which discovery announces as not supported. Ignoring a request object would silently
     * drop parameters such as state and nonce that the client sent only inside it.
     *
     * Once the client and its redirect URI are verified, the error is sent to that redirect
     * URI (OpenID Connect Core 1.0, sections 3.1.2.6 and 6). An unknown client or an
     * unregistered redirect URI is still answered by the OP itself, as for every other request.
     * @throws OAuthServerException
     */
    private function rejectUnsupportedRequest(ServerRequestInterface $request): void
    {
        $responseType = $this->getQueryStringParameter('response_type', $request);
        $hasRequestObject = $this->getQueryStringParameter('request', $request) !== null;
        $hasRequestUri = $this->getQueryStringParameter('request_uri', $request) !== null;
        if ($responseType === 'code' && !$hasRequestObject && !$hasRequestUri) {
            return;
        }

        $clientId = $this->getQueryStringParameter('client_id', $request);
        if ($clientId === null) {
            throw OAuthServerException::invalidRequest('client_id');
        }
        $client = $this->getClientEntityOrFail($clientId, $request);

        $redirectUri = $this->getQueryStringParameter('redirect_uri', $request);
        if ($redirectUri !== null) {
            $this->validateRedirectUri($redirectUri, $client, $request);
        } elseif ($client->getRedirectUri() === ''
            || (is_array($client->getRedirectUri()) && count($client->getRedirectUri()) !== 1)
        ) {
            throw OAuthServerException::invalidClient($request);
        } else {
            $redirectUri = $this->getClientRedirectUri($client);
        }

        $state = $this->getQueryStringParameter('state', $request);
        if ($state !== null) {
            $redirectUri = $this->makeRedirectUri($redirectUri, array('state' => $state));
        }

        if ($responseType === null || $responseType === '') {
            throw new OAuthServerException('The request is missing the response_type parameter.',
                3, 'invalid_request', 400, null, $redirectUri);
        }

        if ($responseType !== 'code') {
            throw new OAuthServerException('The authorization server does not support this response type.',
                2, 'unsupported_response_type', 400, 'Only the response type "code" is supported.', $redirectUri);
        }

        if ($hasRequestObject) {
            throw new OAuthServerException('The authorization server does not support request objects.',
                3, 'request_not_supported', 400, null, $redirectUri);
        }

        throw new OAuthServerException('The authorization server does not support the request_uri parameter.',
            3, 'request_uri_not_supported', 400, null, $redirectUri);
    }
    
    /**
     * League reports an unregistered redirect URI as a failed client authentication, which
     * tells neither the user nor the administrator what is wrong. The redirect URI gets an
     * error of its own, which the authorization endpoint shows instead of redirecting.
     * @throws OAuthServerException
     */
    protected function validateRedirectUri(
        string $redirectUri,
        ClientEntityInterface $client,
        ServerRequestInterface $request
    ): void {
        try {
            parent::validateRedirectUri($redirectUri, $client, $request);
        } catch (OAuthServerException $exception) {
            throw new OAuthServerException('The redirect URI is not registered for this client.',
                self::ERROR_REDIRECT_URI_NOT_REGISTERED, 'invalid_request', 400,
                'Register "' . $redirectUri . '" as a redirect URI of client "' . $client->getIdentifier() . '".',
                null, $exception);
        }
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
        // The column is a timestamp, which does not accept a Unix time
        $authCode->setValue($authCode->getColumnPrefix() . '_auth_time',
            $this->authenticationTime > 0 ? date('Y-m-d H:i:s', $this->authenticationTime) : null);
        $authCode->setValue($authCode->getColumnPrefix() . '_external_session_id', $this->externalSessionId);
        $authCode->setValue($authCode->getColumnPrefix() . '_authentication_methods', implode(' ', $this->authenticationMethods));
        $authCode->setValue($authCode->getColumnPrefix() . '_authentication_context', $this->authenticationContext);
        $authCode->save();
        return $authCode;
    }

    protected function issueAccessToken(
        DateInterval $accessTokenTTL,
        ClientEntityInterface $client,
        string|null $userIdentifier,
        array $scopes = []
    ): AccessTokenEntityInterface {
        $accessToken = parent::issueAccessToken($accessTokenTTL, $client, $userIdentifier, $scopes);
        if ($accessToken instanceof TokenEntity) {
            $this->issuedTokens[] = $accessToken;
        }
        return $accessToken;
    }

    protected function issueRefreshToken(AccessTokenEntityInterface $accessToken): ?RefreshTokenEntityInterface
    {
        $refreshToken = parent::issueRefreshToken($accessToken);
        if ($refreshToken instanceof TokenEntity) {
            $this->issuedTokens[] = $refreshToken;
        }
        return $refreshToken;
    }

    /**
     * Link the tokens of the current code exchange to the authorization code, so that
     * AuthCodeRepository can revoke them if the code is presented again.
     */
    protected function linkIssuedTokens(AuthCodeEntity $authCode): void
    {
        foreach ($this->issuedTokens as $token) {
            $token->setValue($token->getColumnPrefix() . '_oac_id', $authCode->getValue($authCode->getColumnPrefix() . '_id'));
            $token->save();
        }
        $this->issuedTokens = array();
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        global $gLogger;

        $this->issuedTokens = array();
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
            $this->linkIssuedTokens($authCode);

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
}

