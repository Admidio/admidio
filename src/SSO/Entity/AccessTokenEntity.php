<?php

namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

use Admidio\Infrastructure\Database;


class AccessTokenEntity extends TokenEntity implements AccessTokenEntityInterface
{
    use AccessTokenTrait {
        toString as protected buildJwtString;
    }

    /** @var string|null The signed JWT, built once: the token response and the at_hash of the ID token must see the same string */
    private ?string $jwtString = null;

    public function __construct(Database $database, string $tokenId = '') {
        parent::__construct($database, TBL_OIDC_ACCESS_TOKENS, 'oat', $tokenId);
    }

    /**
     * The JWT contains the time it is issued at, so a second call a second later would give another string.
     * The ID token states a hash of the access token (at_hash), which has to match what the client receives.
     */
    public function toString(): string
    {
        return $this->jwtString ??= $this->buildJwtString();
    }
}
