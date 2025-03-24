<?php

namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class AccessTokenEntity implements AccessTokenEntityInterface
{
    use EntityTrait, TokenEntityTrait, AccessTokenTrait;

    /**
     * Convert the access token entity to a serialized JSON format.
     */
    public function jsonSerialize()
    {
        return [
            'identifier'   => $this->getIdentifier(),
            'expiry_date'  => $this->getExpiryDateTime()->format('Y-m-d H:i:s'),
            'scopes'       => array_map(fn($scope) => $scope->getIdentifier(), $this->getScopes()),
            'user_id'      => $this->getUserIdentifier(),
            'client_id'    => $this->getClient()->getIdentifier(),
        ];
    }
}