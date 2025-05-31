<?php

namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;


class ScopeEntity implements ScopeEntityInterface
{
    use EntityTrait, ScopeTrait;
    public function __construct($identifier) {
        $this->setIdentifier($identifier);
    }
}
