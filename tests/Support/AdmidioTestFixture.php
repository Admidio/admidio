<?php
/**
 * Test fixture using real Admidio Entity API
 *
 * Creates actual database records using Admidio's Entity classes,
 * not just mock fixtures in memory.
 */

namespace Admidio\Tests\Support;

use Admidio\Infrastructure\Database;
use Admidio\Organizations\Entity\Organization;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\Membership;
use Admidio\Users\Entity\User;
use Admidio\Categories\Entity\Category;

class AdmidioTestFixture
{
    /**
     * @var Database The test database connection
     */
    private Database $gDb;

    /**
     * @var array Track created entity IDs for cleanup
     */
    private array $createdIds = [
        'organizations' => [],
        'users' => [],
        'roles' => [],
        'categories' => [],
        'memberships' => [],
    ];

    /**
     * Constructor
     */
    public function __construct(Database $gDb)
    {
        $this->gDb = $gDb;
    }

    /**
     * Create and save an organization to database
     *
     * @param string $name Organization name
     * @param string $shortName Organization short name
     * @return array Organization data with org_id
     */
    public function createAndSaveOrganization(string $name, string $shortName = ''): array
    {
        if (empty($shortName)) {
            $shortName = strtolower(str_replace(' ', '', $name));
        }

        $org = new Organization($this->gDb);
        $org->setValue('org_longname', $name);
        $org->setValue('org_shortname', $shortName);
        $org->save();

        $orgData = [
            'org_id' => (int) $org->getValue('org_id'),
            'org_uuid' => $org->getValue('org_uuid'),
            'org_longname' => $org->getValue('org_longname'),
            'org_shortname' => $org->getValue('org_shortname'),
        ];

        $this->createdIds['organizations'][] = $orgData['org_id'];
        return $orgData;
    }

    /**
     * Create and save a user to database
     *
     * @param string $login User login
     * @param string $email User email
     * @param int $orgId Organization ID
     * @return array User data with usr_id
     */
    public function createAndSaveUser(string $login, string $email, int $orgId = 0): array
    {
        $user = new User($this->gDb);
        $user->setValue('usr_login_name', $login);
        $user->save();

        $userData = [
            'usr_id' => (int) $user->getValue('usr_id'),
            'usr_uuid' => $user->getValue('usr_uuid'),
            'usr_login_name' => $user->getValue('usr_login_name'),
            'org_id' => $orgId,
        ];

        $this->createdIds['users'][] = $userData['usr_id'];
        return $userData;
    }

    /**
     * Create and save a role to database
     *
     * @param string $name Role name
     * @param int $orgId Organization ID
     * @param string $description Role description
     * @return array Role data with rol_id
     */
    public function createAndSaveRole(string $name, int $orgId, string $description = ''): array
    {
        $role = new Role($this->gDb);
        $role->setValue('rol_name', $name);
        $role->setValue('rol_description', $description);
        $role->setValue('rol_cat_id', $this->getDefaultRoleCategory($orgId));
        $role->save();

        $roleData = [
            'rol_id' => (int) $role->getValue('rol_id'),
            'rol_uuid' => $role->getValue('rol_uuid'),
            'rol_name' => $role->getValue('rol_name'),
            'rol_description' => $role->getValue('rol_description'),
            'org_id' => $orgId,
        ];

        $this->createdIds['roles'][] = $roleData['rol_id'];
        return $roleData;
    }

    /**
     * Create and save a category to database
     *
     * @param string $name Category name
     * @param string $type Category type (EVT, ROL, ANN, etc.)
     * @param int $orgId Organization ID
     * @return array Category data with cat_id
     */
    public function createAndSaveCategory(string $name, string $type, int $orgId = 0): array
    {
        $category = new Category($this->gDb);
        $category->setValue('cat_name', $name);
        $category->setValue('cat_type', $type);
        if ($orgId > 0) {
            $category->setValue('cat_org_id', $orgId);
        }
        $category->save();

        $catData = [
            'cat_id' => (int) $category->getValue('cat_id'),
            'cat_uuid' => $category->getValue('cat_uuid'),
            'cat_name' => $category->getValue('cat_name'),
            'cat_type' => $category->getValue('cat_type'),
            'org_id' => $orgId,
        ];

        $this->createdIds['categories'][] = $catData['cat_id'];
        return $catData;
    }

    /**
     * Assign user to role (create membership)
     *
     * @param int $usrId User ID
     * @param int $rolId Role ID
     * @param string $startDate Optional start date
     * @return array Membership data with mem_id
     */
    public function assignUserToRole(int $usrId, int $rolId, string $startDate = ''): array
    {
        if (empty($startDate)) {
            $startDate = date('Y-m-d');
        }

        $membership = new Membership($this->gDb);
        $membership->setValue('mem_usr_id', $usrId);
        $membership->setValue('mem_rol_id', $rolId);
        $membership->setValue('mem_begin', $startDate);
        $membership->save();

        $memData = [
            'mem_id' => (int) $membership->getValue('mem_id'),
            'mem_usr_id' => $usrId,
            'mem_rol_id' => $rolId,
            'mem_begin' => $membership->getValue('mem_begin'),
            'mem_end' => $membership->getValue('mem_end'),
        ];

        $this->createdIds['memberships'][] = $memData['mem_id'];
        return $memData;
    }

    /**
     * Get the default role category for an organization
     *
     * @param int $orgId Organization ID
     * @return int Category ID
     */
    private function getDefaultRoleCategory(int $orgId): int
    {
        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . '
                WHERE cat_type = \'ROL\' AND (cat_org_id = ? OR cat_org_id IS NULL)
                LIMIT 1';
        $result = $this->gDb->queryPrepared($sql, [$orgId]);

        if ($row = $result->fetch()) {
            return (int) $row['cat_id'];
        }

        // Create default category if none exists
        return $this->createAndSaveCategory('Roles', 'ROL', $orgId)['cat_id'];
    }

    /**
     * Get user from database by ID
     *
     * @param int $usrId User ID
     * @return array User data
     */
    public function getUserById(int $usrId): array
    {
        $sql = 'SELECT * FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $result = $this->gDb->queryPrepared($sql, [$usrId]);
        return $result->fetch() ?: [];
    }

    /**
     * Get role from database by ID
     *
     * @param int $rolId Role ID
     * @return array Role data
     */
    public function getRoleById(int $rolId): array
    {
        $sql = 'SELECT * FROM ' . TBL_ROLES . ' WHERE rol_id = ?';
        $result = $this->gDb->queryPrepared($sql, [$rolId]);
        return $result->fetch() ?: [];
    }

    /**
     * Delete a role and verify cascade behavior
     *
     * @param int $rolId Role ID
     * @return bool True if deleted
     */
    public function deleteRole(int $rolId): bool
    {
        $role = new Role($this->gDb, $rolId);
        if ($role->getValue('rol_id')) {
            $role->delete();
            return true;
        }
        return false;
    }

    /**
     * Delete a user
     *
     * @param int $usrId User ID
     * @return bool True if deleted
     */
    public function deleteUser(int $usrId): bool
    {
        $user = new User($this->gDb);
        $user->readDataById($usrId);
        if ($user->getValue('usr_id')) {
            $user->delete();
            return true;
        }
        return false;
    }

    /**
     * Count memberships for a role
     *
     * @param int $rolId Role ID
     * @return int Number of memberships
     */
    public function countRoleMemberships(int $rolId): int
    {
        $sql = 'SELECT COUNT(*) as count FROM ' . TBL_MEMBERS . ' WHERE mem_rol_id = ?';
        $result = $this->gDb->queryPrepared($sql, [$rolId]);
        if ($result && $result->rowCount() > 0) {
            $row = $result->fetch();
            return (int) ($row['count'] ?? 0);
        }
        return 0;
    }

    /**
     * Get all memberships for a role
     *
     * @param int $rolId Role ID
     * @return array Membership data
     */
    public function getRoleMemberships(int $rolId): array
    {
        $sql = 'SELECT * FROM ' . TBL_MEMBERS . ' WHERE mem_rol_id = ?';
        $result = $this->gDb->queryPrepared($sql, [$rolId]);
        return ($result && $result->rowCount() > 0) ? $result->fetchAll() : [];
    }

    /**
     * Get created IDs for tracking
     *
     * @return array Created entity IDs
     */
    public function getCreatedIds(): array
    {
        return $this->createdIds;
    }

    /**
     * Check if role exists in database
     *
     * @param int $rolId Role ID
     * @return bool True if role exists
     */
    public function roleExists(int $rolId): bool
    {
        $sql = 'SELECT 1 FROM ' . TBL_ROLES . ' WHERE rol_id = ?';
        $result = $this->gDb->queryPrepared($sql, [$rolId]);
        return $result->rowCount() > 0;
    }

    /**
     * Check if membership exists
     *
     * @param int $memId Membership ID
     * @return bool True if membership exists
     */
    public function membershipExists(int $memId): bool
    {
        $sql = 'SELECT 1 FROM ' . TBL_MEMBERS . ' WHERE mem_id = ?';
        $result = $this->gDb->queryPrepared($sql, [$memId]);
        return $result->rowCount() > 0;
    }
}
