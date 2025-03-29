<?php

namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Database;

class AuthCodeEntity extends TokenEntity implements AuthCodeEntityInterface
{
    public function __construct(Database $database, string $codeId = '') {
        parent::__construct($database, TBL_OIDC_AUTH_CODES, 'oac', $codeId);
    }

    /**
     * Get the redirect URI associated with the auth code.
     */
    public function getRedirectUri(): string|null
    {
        return $this->getValue($this->columnPrefix . '_redirect_uri');
    }

    /**
     * Set the redirect URI for the auth code.
     */
    public function setRedirectUri($uri): void
    {
        $this->setValue($this->columnPrefix . '_redirect_uri', $uri);
    }
    
}
