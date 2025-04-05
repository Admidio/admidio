<?php

namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use RuntimeException;

use Admidio\Infrastructure\Database;
use Admidio\SSO\Entity\UserEntity;

class UserRepository implements UserRepositoryInterface
{
    protected $db;
    protected array $allowedRoles; // Roles that are permitted to use OIDC
    protected string $uid_field;

    public function __construct($database, string $uid_field = 'usr_id', array $allowedRoles = [])
    {
        $this->db = $database; // Using Admidio's $gDb instance
        $this->allowedRoles = $allowedRoles;
        $this->uid_field = $uid_field;
    }

    /**
     * Get the user entity by user credentials or return the currently logged-in user.
     */
    public function getUserEntityByUserCredentials($username, $password, $grantType, $clientEntity): ?UserEntityInterface
    {
        global $gCurrentUser, $gDb, $gProfileFields;
        $user = null;
        $uid = null;

        // 1️⃣ Check if the user is already logged in => Since we are in the password 
        // grant, we don't want to authenticate the current user without a password, 
        // but rather explicitly check the passed user/password combination!
        
        // 2️⃣ If no user is logged in, verify credentials
        $user = new UserEntity($this->db, $gProfileFields, $this->uid_field);
        $user->readDataByColumns([($this->uid_field) => $username]);
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

}
