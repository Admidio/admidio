<?php

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 */

namespace Admidio\SSO\Grants;

use Admidio\SSO\Entity\TokenEntity;
use DateInterval;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

/**
 * Link the tokens that a grant issues to the authorization code they stem from
 * (oat_oac_id / ort_oac_id), so that they can all be revoked when that code is presented again
 * (RFC 6749, section 4.1.2). The authorization code grant links the tokens of the code exchange,
 * the refresh token grant hands the link on to the tokens it issues in exchange for a refresh
 * token, so the whole chain of rotated tokens stays attached to its code.
 */
trait AuthCodeLinkTrait
{
    /** @var array<int,TokenEntity> Tokens issued by the current request, linked to the code afterwards */
    protected array $issuedTokens = array();

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
     * Link the tokens issued by the current request to an authorization code.
     *
     * @param int $authCodeId Internal id (oac_id) of the authorization code
     */
    protected function linkIssuedTokensToCode(int $authCodeId): void
    {
        foreach ($this->issuedTokens as $token) {
            $token->setValue($token->getColumnPrefix() . '_oac_id', $authCodeId);
            $token->save();
        }
        $this->issuedTokens = array();
    }
}
