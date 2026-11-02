<?php

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 */

namespace Admidio\SSO\Grants;

use Admidio\SSO\Entity\IdTokenResponse;
use Admidio\SSO\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use DateInterval;

/**
 * Refresh token grant that authenticates the client the same way as the authorization code
 * grant, so that a client with a URL as client ID can also refresh its tokens, and that keeps
 * the tokens it issues attached to the authorization code the refresh token stems from.
 */
class OIDCRefreshTokenGrant extends RefreshTokenGrant
{
    use ClientCredentialsTrait;
    use AuthCodeLinkTrait;

    /** @var string|null Identifier of the refresh token that is exchanged by the current request */
    protected ?string $exchangedRefreshTokenId = null;

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $this->issuedTokens = array();
        $this->exchangedRefreshTokenId = null;

        $responseType = parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);

        $this->continueAuthenticationOfExchangedToken($responseType);

        return $responseType;
    }

    /**
     * Remember which refresh token the request exchanges; league has validated it by now.
     *
     * @return array<string,mixed>
     */
    protected function validateOldRefreshToken(ServerRequestInterface $request, string $clientId): array
    {
        $refreshTokenData = parent::validateOldRefreshToken($request, $clientId);
        $this->exchangedRefreshTokenId = (string) ($refreshTokenData['refresh_token_id'] ?? '');

        return $refreshTokenData;
    }

    /**
     * Hand on what the authorization code of the exchanged refresh token stands for: the new
     * tokens are linked to the code, and the ID token states the authentication the code
     * recorded (auth_time, sid, acr, amr; OpenID Connect Core 12.2). A refresh token without a
     * code (issued before the link existed) hands on nothing.
     */
    private function continueAuthenticationOfExchangedToken(ResponseTypeInterface $responseType): void
    {
        if ($this->exchangedRefreshTokenId === null || $this->exchangedRefreshTokenId === '') {
            return;
        }

        $oldRefreshToken = $this->refreshTokenRepository->getToken($this->exchangedRefreshTokenId);
        $authCode = $oldRefreshToken instanceof RefreshTokenEntity ? $oldRefreshToken->getAuthCode() : null;

        if ($authCode !== null) {
            $this->linkIssuedTokensToCode((int) $authCode->getValue($authCode->getColumnPrefix() . '_id'));

            if ($responseType instanceof IdTokenResponse) {
                $responseType->setAuthenticationFromAuthCode($authCode, true);
            }
        }
        $this->issuedTokens = array();
    }
}
