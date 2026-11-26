<?php

namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;

use Admidio\Infrastructure\Database;


class RefreshTokenEntity extends TokenEntity implements RefreshTokenEntityInterface
{
    // use RefreshTokenTrait;
    
    protected AccessTokenEntity $accessToken; 

    public function __construct(Database $database, string $tokenId = '') {
        parent::__construct($database, TBL_OIDC_REFRESH_TOKENS, 'ort', $tokenId);
    }

    /**
     * Get the access token that is linked to this refresh token.
     */
    public function getAccessToken(): AccessTokenEntityInterface
    {
        return $this->accessToken;
    }

    /**
     * Set the access token for this refresh token.
     */
    public function setAccessToken($accessToken): void
    {
        $this->accessToken = $accessToken;
        $this->setClient($accessToken->getClient());
        $this->setUserIdentifier($accessToken->getUserIdentifier());
        foreach ($accessToken->getScopes() as $scope) {
            $this->addScope($scope);
        }
    }

    /**
     * Get the authorization code this token stems from, directly or through the refresh tokens
     * it replaced.
     *
     * @return AuthCodeEntity|null The code, or null for a token issued before the link existed
     *                             or whose code was purged.
     */
    public function getAuthCode(): ?AuthCodeEntity
    {
        $authCodeId = (int) $this->getValue($this->columnPrefix . '_oac_id');
        if ($authCodeId <= 0) {
            return null;
        }

        $authCode = new AuthCodeEntity($this->db);
        $authCode->readDataById($authCodeId);

        return $authCode->isNewRecord() ? null : $authCode;
    }
}
