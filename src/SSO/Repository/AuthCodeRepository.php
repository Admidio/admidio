<?php

namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

use Admidio\SSO\Entity\AuthCodeEntity;
use Admidio\SSO\Entity\TokenEntity;

class AuthCodeRepository extends TokenRepository implements AuthCodeRepositoryInterface
{
    /**
     * Creates a new OAuthRefreshToken entity.
     */
    public function getNewAuthCode(): AuthCodeEntity
    {
        return new AuthCodeEntity($this->db);
    }

    public function newToken() : TokenEntity {
        return $this->getNewAuthCode();
    }

    public function getToken(string $tokenId) : TokenEntity {
        return new AuthCodeEntity($this->db,  $tokenId);
    }


    /**
     * Persists a new authorization code to the database.
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCode): void {
        if (!$authCode instanceof AuthCodeEntity) {
            throw OAuthServerException::serverError('Invalid auth code (ID \'' . $authCode->getIdentifier() . '\': not an instance of AuthCodeEntity in AuthCodeRepository->persistNewAuthCode()');
        }
        /** @var AuthCodeEntity $accessToken */
        $this->persistNewToken($authCode);
    }

    /**
     * Revokes an authorization code by marking it as revoked.
     */
    public function revokeAuthCode($codeId): void {
        $this->revokeToken($codeId);
    }

    /**
     * Checks if an authorization code has been revoked.
     */
    public function isAuthCodeRevoked($codeId): bool {
        return $this->isTokenRevoked($codeId);
    }
}
