<?php

namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

use Admidio\Infrastructure\Database;


class AccessTokenEntity extends TokenEntity implements AccessTokenEntityInterface
{
    use AccessTokenTrait;

    public function __construct(Database $database, string $tokenId = '') {
        parent::__construct($database, TBL_OIDC_ACCESS_TOKENS, 'oat', $tokenId);
    }
}