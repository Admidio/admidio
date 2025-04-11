<?php

namespace Admidio\SSO\Repository;

use Admidio\SSO\Entity\OIDCClient;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use RuntimeException;

use Admidio\Infrastructure\Database;
use Admidio\SSO\Entity\UserEntity;
use Admidio\SSO\Service\OIDCService;

class UserRepository implements UserRepositoryInterface, IdentityProviderInterface 
{
    protected $db;
    protected array $allowedRoles; // Roles that are permitted to use OIDC

    public function __construct($database, array $allowedRoles = [])
    {
        $this->db = $database; // Using Admidio's $gDb instance
        $this->allowedRoles = $allowedRoles;
    }

    /**
     * Get the user entity by user credentials or return the currently logged-in user.
     */
    public function getUserEntityByUserCredentials($username, $password, $grantType, $clientEntity): ?UserEntityInterface
    {
        global $gProfileFields;
        $user = null;
        $client = $clientEntity;

        // 1️⃣ Check if the user is already logged in => Since we are in the password 
        // grant, we don't want to authenticate the current user without a password, 
        // but rather explicitly check the passed user/password combination!
        
        // 2️⃣ If no user is logged in, verify credentials
        $user = new UserEntity($this->db, $gProfileFields, $client);
        $user->readDataByColumns([($client->getUseridField()) => $username]);
        if (!$user->checkLogin($password, false, false, false)) {
            return null;
        }
        
        // 3️⃣ Check if the user has the required role
        $user_roles = $user->getRoleMemberships();
        if (empty(array_intersect($user_roles, $this->allowedRoles))) {
            return null;
        }

        return $user;
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
