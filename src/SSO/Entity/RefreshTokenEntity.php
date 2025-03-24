<?php

namespace Admidio\SSO\Entity;


use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

class RefreshTokenEntity implements RefreshTokenEntityInterface
{
    use EntityTrait, TokenEntityTrait, AccessTokenTrait;
    
    protected AccessTokenEntity $accessToken; 

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
    }
}
