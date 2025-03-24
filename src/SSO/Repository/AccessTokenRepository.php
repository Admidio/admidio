<?php
namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;


use Admidio\SSO\Entity\AccessTokenEntity;

// use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
// use Admidio\SSO\Entity\ClientEntity;
use Admidio\Infrastructure\Database;


/**
 * Class AccessTokenRepository, implements AccessTokenRepositoryInterface
 *  - Handles access to the access tokens stored in adm_oauth2_access_tokens
 */

 class AccessTokenRepository implements AccessTokenRepositoryInterface {
    use TokenEntityTrait;

    protected $db;

    private $inactivityTimeout = 1800;

    public function __construct($database) {
        $this->db = $database;
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, string|null $userIdentifier = null ): AccessTokenEntityInterface {
// TODO
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessToken): void {
        $usr_id = $accessToken->getUserIdentifier();

        $token = new AccessTokenEntity($this->db);
        $token->setValue('oat_token_id', $accessToken->getIdentifier());
        $token->setValue('oat_usr_id', $accessToken->getUserIdentifier());
        $token->setValue('oat_ocl_id', $accessToken->getClient()->getIdentifier());
        $token->setValue('oat_expires_at', $accessToken->getExpiryDateTime()->format('Y-m-d H:i:s'));
        $token->setValue('oat_scopes', json_encode($accessToken->getScopes()));
        $token->setValue('oat_claims', json_encode($this->getUserClaims($accessToken->getUser()))); // TODO_RK
        $token->setValue('oat_revoked', false);
        $token->setValue('oat_last_activity', DATETIME_NOW);
        $token->save();
    }
    public function revokeAccessToken(string $tokenId): void {
        $token = new AccessTokenEntity($this->db, $tokenId);
        if (!$token->isNewRecord()) {
            $token->setValue('oat_revoked', true);
            $token->save();
        }
    }
    public function isAccessTokenRevoked(string $tokenId): bool {
        $token = new AccessTokenEntity($this->db, $tokenId);
        return (bool) $token->getValue('oat_revoked');
    }

    private function getUserClaims(?UserEntityInterface $user): array
    {
        return $user instanceof UserEntity ? $user->getClaims() : [];
    }

    public function getUserIdByAccessToken(string $accessToken): ?int
    {
        // TODO_RK
        $sql = "SELECT user_id FROM oauth_access_tokens WHERE access_token = ? AND expires_at > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$accessToken]);
        
        return $stmt->fetchColumn() ?: null;
    }

    public function revokeTokensForUser($userId)
    {
        // TODO_RK
        $sql = "UPDATE oauth_access_tokens SET oat_revoked = 1 WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
    }

    public function isTokenExpiredDueToInactivity($tokenId)
    {
        $sql = "SELECT TIMESTAMPDIFF(SECOND, last_activity, NOW()) AS inactive_time 
                FROM oauth_access_tokens WHERE token_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tokenId]);
        $inactiveTime = $stmt->fetchColumn();
    
        return $inactiveTime !== false && $inactiveTime > $this->inactivityTimeout;
    }
    
    public function updateLastActivity($tokenId)
    {
        $token = new AccessTokenEntity($this->db, $tokenId);
        if (!$token->isNewRecord()) {
            $token->setValue('oat_last_activity', DATETIME_NOW);
            $token->save();
        }
    }
    

}
