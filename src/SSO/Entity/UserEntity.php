<?php

namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use OpenIDConnectServer\Entities\ClaimSetInterface;

use Admidio\Infrastructure\Database;
use Admidio\Users\Entity\User;
use Admidio\Roles\Entity\Role;
use Admidio\ProfileFields\ValueObjects\ProfileFields;

class UserEntity extends User implements UserEntityInterface, ClaimSetInterface
{
    protected ?OIDCClient $client = null; // The OIDC client associated with this user

    /**
     * Create a UserEntity from an Admidio user ID.
     */
    public function __construct(Database $database, ProfileFields $profileFields = null, ?OIDCClient $client = null, int $userId = 0)
    {
        parent::__construct($database, $profileFields, $userId);
        $this->client = $client;
    }

    public function getClient(): OIDCClient {
        return $this->client;
    }
    public function setClient(OIDCClient $client): void {
        $this->client = $client;
    }

    public function getIdentifier(): string
    {
        if (!$this->isNewRecord() && $this->client) {
            return $this->getValue($this->client->getUseridField());
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
            return $this->getValue('EMAIL');
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
        $client = $this->getClient();
        if (!$client) {
            throw new OAuthServerException('Client not set', 6, 'invalid_client');
        }

        $groups =  $this->client->getMappedRoleMemberships($this);
        if (!$client->getFieldMappingNoDefault()) {

            $userInfo = [
                'sub'               => $this->getIdentifier(), // Subject (user ID)
                'uuid'              => $this->getValue('usr_uuid'),
                'preferred_username' => $this->getUsername(),
                'name'              => $this->getFullName(),
                'family_name'       => $this->getValue('LAST_NAME'),
                'given_name'        => $this->getValue('FIRST_NAME'),
                // 'profile'        => $this->getValue(''),
                // 'picture'        => $this->getValue(''),

                'email'             => $this->getEmail(),
                'groups'            => $this->getRoleNames(), // User's roles as groups
                'locale'            => $this->getLocale(),

                'website'           => $this->getValue('WEBSITE'),
                'gender'            => $this->getValue('GENDER'),
                'birthdate'         => $this->getValue('BIRTHDAY'),
            ];
            if (!empty($this->getValue('MOBILE'))) {
                $userInfo['phone_number'] = $this->getValue('MOBILE');
            } elseif (!empty($this->getValue('PHONE'))) {
                $userInfo['phone_number'] = $this->getValue('PHONE');
            }

            $userInfo['address'] = [
                'street_address' => $this->getValue('STREET'),
                'locality' => $this->getValue('CITY'),
                'region' => $this->getValue('BUNDESLAND'),
                'postal_code' => $this->getValue('POSTCODE'),
                'country' => $this->getValue('COUNTRY')
            ];

            // User Roles / Group (after mapping)
            $userInfo['groups'] = $groups;

            // Filter out empty fields:
            $userInfo['address'] = array_filter($userInfo['address'], function ($value) {
                return $value !== '' && $value !== null;
            });
        }
        
        // The field mapping in the client config can add new fields to the user info, but it can also override existing ones.
        // For example, if the client config has 'fullname' mapped to 'name', it will override the default name.
        foreach ($client->getFieldMapping() as $key => $value) {
            if ($value === 'fullname') {
                $userInfo[$key] = $this->readableName();
            } elseif ($value === 'roles') {
                $userInfo[$key] = $groups;
            } else {
                $userInfo[$key] = $this->getValue($value);
            }
        }

        $userInfo = array_filter($userInfo, function ($value) {
            return $value !== '' && $value !== null && $value !== [];
        });


        return $userInfo;
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
