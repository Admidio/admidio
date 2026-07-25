<?php

namespace Admidio\SSO\Repository;

use Admidio\SSO\Entity\OIDCClient;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;

use Admidio\Infrastructure\Database;
use Admidio\SSO\Entity\UserEntity;
use Admidio\SSO\Service\OIDCService;

class UserRepository implements IdentityProviderInterface 
{
    protected $db;

    public function __construct($database)
    {
        $this->db = $database; // Using Admidio's $gDb instance
    }

    /**
     * Get the user entity by user credentials or return the currently logged-in user.
     */
    public function getUserEntityByIdentifier($identifier): ?UserEntityInterface
    {
        global $gProfileFields;
        $client = OIDCService::getClient();
        $user = new UserEntity($this->db, $gProfileFields, $client);

        $user->readDataByColumns([($client->getUseridField()) => $identifier]);
        return $user;
    }

}
