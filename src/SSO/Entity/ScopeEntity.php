<?php

namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ScopeEntity implements ScopeEntityInterface
{
    use EntityTrait;

    /**
     * @var string
     */
    protected $description;

    public function __construct($identifier, $description)
    {
        $this->identifier = $identifier;
        $this->description = $description;
    }

    /**
     * Get a description of the scope.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Convert scope to string format.
     */
    public function jsonSerialize()
    {
        return $this->identifier;
    }
}
