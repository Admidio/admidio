<?php

namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use Admidio\SSO\Entity\RefreshTokenEntity;
use PDO;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    /**
     * Creates a new OAuthRefreshToken entity.
     */
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    /**
     * Persists a new refresh token to the database.
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshToken): void
    {
        $sql = "INSERT INTO oauth_refresh_tokens (token_id, access_token_id, expires_at, revoked) 
                VALUES (?, ?, ?, 0)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $refreshToken->getIdentifier(),
            $refreshToken->getAccessToken()->getIdentifier(),
            $refreshToken->getExpiryDateTime()->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Revokes a refresh token by setting revoked to true.
     */
    public function revokeRefreshToken($tokenId): void
    {
        $sql = "UPDATE oauth_refresh_tokens SET revoked = 1 WHERE token_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tokenId]);
    }

    /**
     * Checks if a refresh token has been revoked.
     */
    public function isRefreshTokenRevoked($tokenId): bool
    {
        $sql = "SELECT revoked FROM oauth_refresh_tokens WHERE token_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tokenId]);
        $revoked = $stmt->fetchColumn();

        return $revoked === false || (int)$revoked === 1;
    }

    /**
     * Deletes expired refresh tokens to clean up storage.
     */
    public function removeExpiredRefreshTokens(): void
    {
        $sql = "DELETE FROM oauth_refresh_tokens WHERE expires_at < NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }
}
