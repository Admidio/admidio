<?php

namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

use Admidio\SSO\Entity\RefreshTokenEntity;
use Admidio\SSO\Entity\TokenEntity;

class RefreshTokenRepository extends TokenRepository implements RefreshTokenRepositoryInterface
{
    /**
     * Creates a new OAuthRefreshToken entity.
     */
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity($this->db);
    }

    public function newToken() : TokenEntity {
        return $this->getNewRefreshToken();
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
     */
    public function isRefreshTokenRevoked($tokenId): bool {
        return $this->isTokenRevoked($tokenId);
    }

    /**
     * Deletes expired refresh tokens to clean up storage.
     */
    public function removeExpiredRefreshTokens(): void {
        $this->removeExpiredTokens();
    }
}
