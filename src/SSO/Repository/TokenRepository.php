<?php

namespace Admidio\SSO\Repository;
use Admidio\SSO\Entity\TokenEntity;

abstract class TokenRepository
{
    protected $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    /**
     * Creates a Token entity.
     */
    abstract public function getNewToken(): TokenEntity;
    abstract public function getToken(string $tokenId) : TokenEntity;

    /**
     * Persists a new refresh token to the database.
     */
    public function persistNewToken(TokenEntity $token): void {
        $token->save();
    }

    /**
     * Revokes a token by setting revoked to true.
     */
    public function revokeToken($tokenId): void {
        $token = $this->getToken($tokenId);
        if (!$token->isNewRecord()) {
            $token->setValue($token->getColumnPrefix() . '_revoked', true);
            $token->save();
        }
    }

    /**
     * Checks if a token has been revoked.
     */
    public function isTokenRevoked($tokenId): bool {
        $token = $this->getToken($tokenId);
        return ($token->isNewRecord() || $token->getValue($token->getColumnPrefix() . '_revoked'));
    }

    /**
     * Deletes expired tokens to clean up storage.
     */
    public function removeExpiredTokens(): void {
        $token = $this->getNewToken();
        $token->deleteExpiredTokens();
    }
}
