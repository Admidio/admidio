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
}
