<?php

namespace Admidio\SSO\Entity;

use Admidio\ProfileFields\ValueObjects\ProfileFields;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\SerializableTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

use Admidio\Infrastructure\Database;
use Admidio\Users\Entity\User; // Use Admidio's User class
use Admidio\Roles\Entity\Role;

class UserEntity extends User implements UserEntityInterface 
{
    /**
     * Create a UserEntity from an Admidio user ID.
     */
    public function __construct(Database $database, ProfileFields $profileFields = null, int $userId = 0)
    {
        parent::__construct($database, $profileFields, $userId);
        // Set the identifier for theetValue('usr_id'));
    }

    public function getIdentifier(): string
    {
        if (!$this->isNewRecord()) {
            return $this->getValue('usr_login_name');
        } else {
            return '';
        }
    }

    /**
     * Get the username (login name) of the user.
     */
    public function getUsername(): string
    {
        if (!$this->isNewRecord()) {
            return $this->getValue('usr_login_name');
        } else {
            return '';
        }
    }

    /**
     * Get the user's full name.
     */
    public function getFullName(): string
    {
        if (!$this->isNewRecord()) {
            return trim($this->readableName());
        } else {
            return '';
        }
    }

    /**
     * Get the user's email address.
     */
    public function getEmail(): string
    {
        if (!$this->isNewRecord()) {
            return $this->getValue('usr_email');
        } else {
            return '';
        }
    }

    /**
     * Get the user’s roles as an array of role IDs.
     */
    public function getRoles(): array
    {
        if (!$this->isNewRecord()) {
            $roles = $this->getRoleMemberships();
            return array_keys($roles);
        } else {
            return [];
        }
    }

    /**
     * Get the user’s roles as a human-readable array.
     */
    public function getRoleNames(): array
    {
        $roleNames = [];
        if (!$this->isNewRecord()) {
            $roles = $this->getRoleMemberships();
            foreach ($roles as $roleId => $roleRights) {
                $role = new Role($this->db, $roleId );
                if (!$role->isNewRecord()) {
                    $roleNames[$roleId] = $role->getValue('rol_name');
                }
            }
        }
        return $roleNames;
    }

    /**
     * Returns OIDC claims for the user.
     */
    public function getClaims(): array
    {
        return [
            'sub'               => $this->getIdentifier(), // Subject (user ID)
            'preferred_username' => $this->getUsername(),
            'name'              => $this->getFullName(),
            'email'             => $this->getEmail(),
            'email_verified'    => true, // Assuming Admidio verifies emails
            'groups'            => $this->getRoleNames(), // User's roles as groups
            'locale'            => $this->getLocale()
        ];
    }

    /**
     * Gets the user's locale/language from Admidio.
     */
    public function getLocale(): string
    {
        global $gSettingsManager;
        return $gSettingsManager->get('system_language') ?: 'de'; // Default to German if not set
    }
}
