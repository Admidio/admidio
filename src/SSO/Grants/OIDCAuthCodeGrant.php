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
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Entity\AuthCodeEntity;
use Admidio\SSO\Entity\OIDCClient;
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
    use AuthCodeLinkTrait {
        issueRefreshToken as protected issueLinkedRefreshToken;
    }

    protected ?string $nonce = null;
    protected ?int $authenticationTime = null;
    protected string $externalSessionId = '';
    protected array $authenticationMethods = array();
    protected ?string $authenticationContext = null;
    /** Response modes the authorization endpoint answers with, as announced by the discovery document */
    public const SUPPORTED_RESPONSE_MODES = array('query', 'form_post');

    /** Error code of the exception for a redirect URI that is not registered for the client */
    public const ERROR_REDIRECT_URI_NOT_REGISTERED = 1001;

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
     * "code", a client that is not allowed to use the authorization code grant, or a request
     * object passed by value (request) or by reference (request_uri), which discovery
     * announces as not supported. Ignoring a request object would silently drop parameters
     * such as state and nonce that the client sent only inside it.
     *
     * Once the client and its redirect URI are verified, the error is sent to that redirect
     * URI (OpenID Connect Core 1.0, sections 3.1.2.6 and 6). An unknown client or an
     * unregistered redirect URI is still answered by the OP itself, as for every other request.
     * @throws OAuthServerException
     */
    private function rejectUnsupportedRequest(ServerRequestInterface $request): void
    {
        $responseType = $this->getQueryStringParameter('response_type', $request);
        $responseMode = $this->getQueryStringParameter('response_mode', $request);
        $hasRequestObject = $this->getQueryStringParameter('request', $request) !== null;
        $hasRequestUri = $this->getQueryStringParameter('request_uri', $request) !== null;

        $clientId = $this->getQueryStringParameter('client_id', $request);
        if ($clientId === null) {
            throw OAuthServerException::invalidRequest('client_id');
        }
        // Not getClientEntityOrFail(), which rejects a client without the authorization code
        // grant before its redirect URI is known, so that the error could not go to the client
        $client = $this->clientRepository->getClientEntity($clientId);
        if ($client === null) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::CLIENT_AUTHENTICATION_FAILED, $request));
            throw OAuthServerException::invalidClient($request);
        }
        $grantAllowed = $this->supportsGrantType($client, $this->getIdentifier());

        $responseModeSupported = $responseMode === null || in_array($responseMode, self::SUPPORTED_RESPONSE_MODES, true);

        if ($responseType === 'code' && !$hasRequestObject && !$hasRequestUri && $grantAllowed && $responseModeSupported) {
            return;
        }

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

        if (!$grantAllowed) {
            throw new OAuthServerException('The client is not allowed to use the authorization code grant.',
                9, 'unauthorized_client', 400,
                'Enable the authorization code grant for client "' . $client->getIdentifier() . '".', $redirectUri);
        }

        if (!$responseModeSupported) {
            throw new OAuthServerException('The authorization server does not support this response mode.',
                3, 'invalid_request', 400,
                'Supported response modes: ' . implode(', ', self::SUPPORTED_RESPONSE_MODES) . '.', $redirectUri);
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

    /**
     * Link the tokens of the current code exchange to the authorization code, so that
     * AuthCodeRepository can revoke them if the code is presented again.
     */
    protected function linkIssuedTokens(AuthCodeEntity $authCode): void
    {
        $this->linkIssuedTokensToCode((int) $authCode->getValue($authCode->getColumnPrefix() . '_id'));
    }

    /**
     * Revoke the tokens of the authorization code the request presents, if that code was
     * exchanged before. Anything that cannot be read as a code of this server is ignored; the
     * request is refused anyway.
     */
    private function revokeTokensOfPresentedCode(ServerRequestInterface $request): void
    {
        try {
            $encryptedAuthCode = $this->getRequestParameter('code', $request);
            $payload = json_decode($this->decrypt($encryptedAuthCode));
            if (is_object($payload) && isset($payload->auth_code_id) && $this->authCodeRepository instanceof AuthCodeRepository) {
                $this->authCodeRepository->revokeTokensOfReusedCode((string) $payload->auth_code_id);
            }
        } catch (\Throwable) {
            // not a code of this server
        }
    }

    /**
     * A refresh token lets the relying party obtain tokens without the user being present, long after
     * the Admidio session has ended, so it is issued only when the user was asked for it by the
     * offline_access scope (OpenID Connect Core 1.0, section 11) and the client may request it.
     */
    protected function issueRefreshToken(AccessTokenEntityInterface $accessToken): ?RefreshTokenEntityInterface
    {
        $scopes = array_map(
            static fn ($scope): string => $scope->getIdentifier(),
            $accessToken->getScopes()
        );
        if (!in_array(OIDCClient::SCOPE_OFFLINE_ACCESS, $scopes, true)) {
            return null;
        }

        return $this->issueLinkedRefreshToken($accessToken);
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $this->issuedTokens = array();
        try {
            $responseType = parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
        } catch (OAuthServerException $exception) {
            // A code that was exchanged before and is presented again (also after it expired,
            // which league reports first) may have been intercepted: revoke what it produced.
            if ($exception->getErrorType() === 'invalid_grant') {
                $this->revokeTokensOfPresentedCode($request);
            }
            throw $exception;
        }
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

            $responseType->setAuthenticationFromAuthCode($authCode);
        }
        return $responseType;
    }
}

