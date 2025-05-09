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


/**
 * Custom AuthCodeGrant class to support nonces. The nonce in the auth request is stored 
 * temporarily in the class, and later alongside the auth code in the database.
 * The ID Token generation can then directly retrieve the nonce for the given auth code 
 * from the database and include it in the ID token. 
 */
class OIDCAuthCodeGrant extends AuthCodeGrant
{
    protected ?string $nonce = null;

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
        $authCode->setValue($authCode->getColumnPrefix() . '_nonce', $this->nonce);
        $authCode->save();
        return $authCode;
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $responseType = parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);

        // If we arrive here, the auth code was valid -> No need to check validity again!
        $encryptedAuthCode = $this->getRequestParameter('code', $request);
        $authCodePayload = json_decode($this->decrypt($encryptedAuthCode));

        // Load the AuthCode from the DB (including the nonce) and pass on the nonce to the response type ()
        $authCode = $this->authCodeRepository->getToken($authCodePayload->auth_code_id);
        if (empty($this->nonce) && !($authCode->isNewRecord())) {
            $responseType->setNonce($authCode->getValue($authCode->getColumnPrefix() . '_nonce'));
        }
        return $responseType;
    }
}

