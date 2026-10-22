<?php

namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

use Admidio\SSO\Entity\RefreshTokenEntity;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Entity\TokenEntity;
use Admidio\SSO\Entity\UserEntity;

class RefreshTokenRepository extends TokenRepository implements RefreshTokenRepositoryInterface
{
    /**
     * Creates a new OAuthRefreshToken entity.
     */
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity($this->db);
    }

    public function getToken(string $tokenId) : TokenEntity {
        return new RefreshTokenEntity($this->db,  $tokenId);
    }


    /**
     * Persists a new refresh token to the database.
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshToken): void{
        if (!$refreshToken instanceof RefreshTokenEntity) {
            throw OAuthServerException::serverError('Invalid refresh token (ID \'' . $refreshToken->getIdentifier() . '\': not an instance of RefreshTokenEntity in RefreshTokenRepository->persistNewRefreshToken()');
        }
        /** @var RefreshTokenEntity $accessToken */
        $this->persistNewToken($refreshToken);
    }

    /**
     * Revokes a refresh token by setting revoked to true.
     */
    public function revokeRefreshToken($tokenId): void {
        $this->revokeToken($tokenId);
    }

    /**
     * Checks if a refresh token has been revoked.
     *
     * A refresh token may only be redeemed while the user is still allowed to use the client it
     * was issued for. Losing the role that granted the access revokes the tokens right away, but a
     * role membership can also simply expire, and nothing in Admidio announces that, so the right
     * is judged again here. A token that no longer has one is revoked instead of only refused.
     */
    public function isRefreshTokenRevoked($tokenId): bool {
        if ($this->isTokenRevoked($tokenId)) {
            return true;
        }

        $token = $this->getToken($tokenId);

        // A row without a user is not a token of a person, so there is no access right to judge.
        if ((int) $token->getValue($token->getColumnPrefix() . '_usr_id') === 0) {
            return false;
        }

        $client = $token->getClient();
        $user = $token->getUser();

        if (!$client instanceof OIDCClient || !$user instanceof UserEntity) {
            return false;
        }

        if ($client->hasAccessRight($user)) {
            return false;
        }

        $this->revokeRefreshToken($tokenId);

        return true;
    }

}
