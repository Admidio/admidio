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

    public function getToken(string $tokenId) : AuthCodeEntity {
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
        if (!$this->isTokenRevoked($codeId)) {
            return false;
        }
        $this->revokeTokensOfAuthCode($codeId);
        return true;
    }

    /**
     * Revokes the access and refresh tokens that were issued for an authorization code.
     * A code is revoked when it is exchanged, so a revoked code that is presented again may
     * have been intercepted, and the tokens it produced must not stay valid (RFC 6749,
     * section 4.1.2).
     */
    private function revokeTokensOfAuthCode(string $codeId): void {
        $authCode = $this->getToken($codeId);
        if ($authCode->isNewRecord()) {
            return;
        }

        foreach (array(TBL_OIDC_ACCESS_TOKENS => 'oat', TBL_OIDC_REFRESH_TOKENS => 'ort') as $table => $prefix) {
            $this->db->queryPrepared(
                'UPDATE ' . $table . '
                    SET ' . $prefix . '_revoked = true
                  WHERE ' . $prefix . '_oac_id = ?',
                array((int) $authCode->getValue('oac_id'))
            );
        }
    }
}
