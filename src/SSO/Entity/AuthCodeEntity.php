<?php

namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;

class AuthCodeEntity implements AuthCodeEntityInterface
{
    use EntityTrait, TokenEntityTrait, AuthCodeTrait;

    /**
     * @var string|null
     */
    protected $redirectUri;

    /**
     * Get the redirect URI associated with the auth code.
     */
    public function getRedirectUri(): string|null
    {
        return $this->redirectUri;
    }

    /**
     * Set the redirect URI for the auth code.
     */
    public function setRedirectUri($uri): void
    {
        $this->redirectUri = $uri;
    }
}
