<?php
namespace Admidio\SSO\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Roles\Entity\Role;
use Admidio\Users\Entity\User;



class SSOClient extends Entity 
{
    /**
     * @var RolesRights|null Object with all roles that haver permission for the current client
     */
    protected ?RolesRights $rolesAccess;
    protected string $ssoType = '';

    public function __construct(Database $database, string $tableName, string $columnPrefix, $client_id = null) {
        $this->ssoType = 'saml';
        if (is_numeric($client_id)) {
            parent::__construct($database, $tableName, $columnPrefix, $client_id);
        } else {
            parent::__construct($database, $tableName, $columnPrefix);
            if (!empty($client_id)) {
                $this->readDataByColumns([$this->columnPrefix . '_client_id' => $client_id]);
            }  else {
                // Set default values for clock skew, assertion lifetime and included fields
                $this->dbColumns[$this->columnPrefix . '_field_mapping'] = '{"username":"usr_login_name","fullname":"fullname","email":"EMAIL","roles":"roles"}';
                $this->dbColumns[$this->columnPrefix . '_userid_field'] = 'usr_login_name';
            }
        }
    }

    public function getIdentifier(): string {
        return $this->getValue($this->columnPrefix . '_client_id')??'';
    }

    public function getName(): string {
        return $this->getValue($this->columnPrefix . '_client_name')??'';
    }


    /**
     * Deletes the selected record of the table and all references in other tables.
     * @return bool **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
    {
        // delete all roles assignments that have the right to login to this client
        if (!empty($this->rolesAccess)) {
            $this->rolesAccess->delete();
        }
        return parent::delete();
    }

    /**
     * Enables/disables the selected record of the table and all references in other tables.
     * @throws Exception
     */
    public function enable(bool $enabled = true)
    {
        $this->setValue($this->columnPrefix . '_enabled', $enabled);
    }

    /**
     * Returns whether the client is enabled or not.
     * @return bool Whether the client is enabled
     */
    public function isEnabled(): bool
    {
        return $this->getValue($this->columnPrefix . '_enabled');
    }

   /**
     * Add all roles of the array to the current SAML/OIDC client. 
     * @param array<int,int> $rolesArray Array with all role IDs that should be added.
     * @throws Exception
     */
    public function addRolesOnClient(array $rolesArray)
    {
        $this->editRolesOnClient('add', $rolesArray);
    }
    /**
     * Remove all roles of the array from the current SAML/OIDC client.
     * @param array<int,int> $rolesArray Array with all role IDs that should be removed.
     * @throws Exception
     */
    public function removeRolesOnClient(array $rolesArray)
    {
        $this->editRolesOnClient('remove', $rolesArray);
    }
    /**
     * Add all roles of the array to the permitted login roles. 
     * @param string $mode "mode" could be "add" or "remove"
     * @param array<int,int> $rolesArray
     * @throws Exception
     */
    private function editRolesOnClient(string $mode, array $rolesArray)
    {
        if (count($rolesArray) === 0) {
            return;
        }
        if (empty($this->rolesAccess)) {
            return;
        }

        if ($mode === 'add') {
            $this->rolesAccess->addRoles($rolesArray);
        } else {
            $this->rolesAccess->removeRoles($rolesArray);
        }
    }

    /**
     * Returns an array with all role IDs that have access rights to log in to the SAML/OIDC client.
     * @return array<int,int> Returns an array with all role ids that have login rights to the SAML client.
     */
    public function getAccessRolesIds(): array
    {
        if (!empty($this->rolesAccess)) {
            return $this->rolesAccess->getRolesIds();
        } else {
            return array();
        }
    }

    /**
     * Returns an array with all role names that have access rights to log in to the SAML client.
     * @return array<int,int> Returns an array with all role names that have login rights to the SAML client.
     */
    public function getAccessRolesNames(): array
    {
        if (empty($this->rolesAccess)) {
            return array();
        } else {
            return $this->rolesAccess->getRolesNames();
        }
    }

    /**
     * Returns the User/Profile field that is used by the particular client to identify users.
     * @return string
     */
    public function getUserIdField(): string
    {
        return $this->getValue($this->columnPrefix . '_userid_field')??'';
    }

    /**
     * Returns an associative array with all selected user fields (internal names) to be submitted to the client / Service Provider upon successful login.
     * The keys are the SAML field names, the values are the Admidio fields. This means, that the same Admidio field can be used for multiple SAML attributes.
     * @return array<string,string> Returns an array with all selected user field names that are sent to the SAML client. The keys are the SAML field names, the values are the Admidio fields.
     */
    public function getFieldMapping(): array
    {
        $fields = $this->getValue($this->columnPrefix . '_field_mapping', 'database'); // Read the raw string from the database, so html tags don't get replaced!
        $mapping = json_decode($fields ?? '', true);
        if (empty($mapping)) {
            return array();
        } else {
            unset($mapping['*']);
            return $mapping;
        }
    }

    /**
     * Returns whether all available Admidio profile fiels shall be submitted to the SAML client, too, with the internal field name as attribute name.
     * @return bool Returns **true** if all available Admidio profile fields shall be submitted to the SAML client, too, with the internal field name as attribute name.
     */
    public function getFieldMappingCatchall(): bool
    {
        $fields = $this->getValue($this->columnPrefix . '_field_mapping', 'database')??''; // Read the raw string from the database, so html tags don't get replaced!
        $mapping = json_decode($fields, true);
        if (empty($mapping)) {
            return false;
        } else {
            return $mapping['*']??false;
        }
    }

    /**
     * Sets the selected user fields to be sent to SAML clients upon login
     */
    public function setFieldMapping($fields, $catchall = false)
    {
        $fields['*'] = $catchall;
        $this->setValue($this->columnPrefix . '_field_mapping', json_encode($fields));
    }

    /**
     * Returns an associative array with all selected user roles (internal names) to be submitted to the client / Service Provider upon successful login.
     * The keys are the SAML role names, the values are the Admidio roles. This means, that the same Admidio role can be used for multiple SAML attributes.
     * @return array<string,string> Returns an array with all selected user role names that are sent to the SAML client. The keys are the SAML role names, the values are the Admidio roles.
     */
    public function getRoleMapping(): array
    {
        $roles = $this->getValue($this->columnPrefix . '_role_mapping', 'database'); // Read the raw string from the database, so html tags don't get replaced!
        if (empty($roles)) {
            return array();
        }
        $mapping = json_decode($roles, true);
        if (empty($mapping)) {
            return array();
        } else {
            unset($mapping['*']);
            return $mapping;
        }
    }

    /**
     * Returns whether all available Admidio profile fiels shall be submitted to the SAML client, too, with the internal role name as attribute name.
     * @return bool Returns **true** if all available Admidio profile roles shall be submitted to the SAML client, too, with the internal role name as attribute name.
     */
    public function getRoleMappingCatchall(): bool
    {
        $roles = $this->getValue($this->columnPrefix . '_role_mapping', 'database')??''; // Read the raw string from the database, so html tags don't get replaced!
        $mapping = json_decode($roles, true);
        if (empty($mapping)) {
            return false;
        } else {
            return $mapping['*']??false;
        }
    }

    /**
     * Sets the selected user roles to be sent to SAML clients upon login
     */
    public function setRoleMapping($roles, $catchall = false)
    {
        $roles['*'] = $catchall;
        $this->setValue($this->columnPrefix . '_role_mapping', json_encode($roles));
    }

    public function getMappedRoleMemberships(User $user): array
    {
        $mapping = $this->getRoleMapping();
        $includeAll = $this->getRoleMappingCatchall();

        $mappedRoles = array();
        // Loop through all roles of the user. If it is part of the mapping, or catchall is set, append it to the attribute
        foreach ($user->getRoleMemberships() as $roleId) {
            $isLeader = $user->isLeaderOfRole($roleId);

            $rolesFound = array_keys($mapping, $roleId);
            $mappedRoles = array_merge($mappedRoles, $rolesFound);
            $rolesLeaderFound = array_keys($mapping, -$roleId);
            $mappedRoles = array_merge($mappedRoles, $rolesLeaderFound);

            // The catchall applies only to "normal" group memberships. Role leaderships are not implicitly
            // added, only when they are explicitly mapped to a particular role.
            if (empty($rolesFound) && $includeAll) {
                // CATCHALL: Add role with its admidio role name
                $role = new Role($this->db, $roleId);
                $mappedRoles[] = $role->getValue('rol_name');
            }
        }
        return $mappedRoles;
    }

    /**
     * Checks if the current user has access rights to the SAML client.
     * @return bool Return **true** if the user has access rights to the SAML client
     * @throws Exception
     */
   public function hasAccessRight(): bool
    {
        global $gCurrentUser;
        if (empty($this->rolesAccess) || empty($this->rolesAccess->getRolesIds())) {
            return true;
        } else {
            return $this->rolesAccess->hasRight($gCurrentUser->getRoleMemberships()) || $gCurrentUser->isAdministrator();
        }
    }

    /**
     * Reads a record out of the table in database selected by the conditions of the param **$sqlWhereCondition** out of the table.
     * If the sql find more than one record the method returns **false**.
     * Per default all columns of the default table will be read and stored in the object.
     * @param string $sqlWhereCondition Conditions for the table to select one record
     * @param array<int,mixed> $queryParams The query params for the prepared statement
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByUuid
     * @see Entity#readDataByColumns
     * @see Entity#readDataById
     */
    protected function readData(string $sqlWhereCondition, array $queryParams = array()): bool
    {
        if (parent::readData($sqlWhereCondition, $queryParams)) {
            $clientId = (int)$this->getValue($this->columnPrefix . '_id');
            $this->rolesAccess = new RolesRights($this->db, 'sso_' . $this->ssoType . '_access', $clientId);
            return true;
        }
        return false;
    }

    public function readDatabyEntityId(string $entityId): bool
    {
        return $this->readDataByColumns([$this->columnPrefix . '_client_id' => $entityId]);
    }

    /**
     * Return a human-readable representation of this client.
     * 
     * @return string The readable representation of the record (can also be a translatable identifier)
     */
    public function readableName(): string
    {
        return $this->dbColumns[$this->columnPrefix . '_client_name']??'';
    }



    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * In addition to the default ignored columns, don't log fot_views
     *
     * @return array Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(),
        ($this->newRecord)?[$this->columnPrefix.'_client_name']:[]);
    }
}
