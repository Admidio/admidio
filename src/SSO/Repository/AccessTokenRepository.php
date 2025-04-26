<?php
namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use League\OAuth2\Server\Exception\OAuthServerException;


use Admidio\SSO\Entity\AccessTokenEntity;
use Admidio\SSO\Entity\TokenEntity;

// use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
// use Admidio\SSO\Entity\ClientEntity;
use Admidio\Infrastructure\Database;


/**
 * Class AccessTokenRepository, implements AccessTokenRepositoryInterface
 *  - Handles access to the access tokens stored in adm_oauth2_access_tokens
 */

 class AccessTokenRepository extends TokenRepository implements AccessTokenRepositoryInterface {
    private $inactivityTimeout = 1800;

    /**
     *  Dummy implementation since the TokenRepository class declares the method abstract, so we MUST implement it, even though we don't need it...
     * @return TokenEntity
     */
    public function newToken(): TokenEntity {
        return new TokenEntity($this->db);
    }

    public function getToken(string $tokenId) : TokenEntity {
        return new AccessTokenEntity($this->db,  $tokenId);
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, string|null $userIdentifier = null ): AccessTokenEntityInterface {
        $token = new AccessTokenEntity($this->db);
        foreach ($scopes as $sc) {
            $token->addScope($sc);
        }
        $token->setClient($clientEntity);
        $token->setUserIdentifier($userIdentifier);
        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessToken): void {
        if (!$accessToken instanceof AccessTokenEntity) {
            throw OAuthServerException::serverError('Invalid access token (not an instance of AccessTokenEntity in AccessTokenRepository->persistNewAccessToken())');
        }
        /** @var AccessTokenEntity $accessToken */
        $this->persistNewToken($accessToken);
    }

    public function revokeAccessToken(string $tokenId): void {
        $this->revokeToken($tokenId);
    }
    public function isAccessTokenRevoked(string $tokenId): bool {
        return $this->isTokenRevoked($tokenId);
    }

    private function getUserClaims(?UserEntityInterface $user): array {
        return $user instanceof UserEntity ? $user->getClaims() : [];
    }

    public function getUserIdByAccessToken(string $accessToken): ?string {
        $token = $this->getToken($accessToken);
        $now = new \DateTime();
        if (!$token->isNewRecord() && ($token->getExpiryDateTime() > $now)) {
            return $token->getUserIdentifier();
        } else {
            return null;
        }
    }
}
