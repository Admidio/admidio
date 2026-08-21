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
        // org_homepage and org_email_administrator are NOT NULL without a default. MySQL fills them
        // with an empty string because Admidio switches the session to ANSI mode, PostgreSQL rejects
        // the whole insert, so an organization is only portable if the fixture sets them.
        $org->setValue('org_homepage', 'https://www.example.local');
        $org->setValue('org_email_administrator', 'admin@example.local');
        $org->save();

        // org_shortname is varchar(10) with a unique index, and the collation is case insensitive,
        // so a name that is too long or collides with an existing org leaves the record unsaved.
        // Without this check the test would go on with org_id 0 and assert against nothing.
        if ((int) $org->getValue('org_id') === 0) {
            throw new \RuntimeException(
                'Organization "' . $name . '" (' . $shortName . ') was not saved. The short name must be at most '
                . '10 characters and unique, note that the installed test organization already uses "TEST".'
            );
        }

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
        // no $gCurrentUser is logged in during tests, so the category edit-rights check has to be bypassed
        $role->saveChangesWithoutRights();
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
     * Create and save a role in a specific category
     *
     * @param string $name Role name
     * @param int $catId Category ID the role belongs to
     * @param string $description Role description
     * @return array Role data with rol_id
     */
    public function createAndSaveRoleInCategory(string $name, int $catId, string $description = ''): array
    {
        $role = new Role($this->gDb);
        // no $gCurrentUser is logged in during tests, so the category edit-rights check has to be bypassed
        $role->saveChangesWithoutRights();
        $role->setValue('rol_name', $name);
        $role->setValue('rol_description', $description);
        $role->setValue('rol_cat_id', $catId);
        $role->save();

        $roleData = [
            'rol_id' => (int) $role->getValue('rol_id'),
            'rol_uuid' => $role->getValue('rol_uuid'),
            'rol_name' => $role->getValue('rol_name'),
            'rol_description' => $role->getValue('rol_description'),
            'cat_id' => $catId,
        ];

        $this->createdIds['roles'][] = $roleData['rol_id'];
        return $roleData;
    }

    /**
     * Seed an organization with the default preferences of a fresh installation.
     *
     * An organization created through the Organization entity alone has no preferences at all,
     * unlike one created by the installer, so any Admidio code that reads a setting throws
     * "Settings name ... does not exist". Call this for a test that exercises such code.
     *
     * @param int $orgId Organization ID
     * @return void
     */
    public function seedDefaultPreferences(int $orgId): void
    {
        // the same file the installer reads, it defines $defaultOrgPreferences
        require(ADMIDIO_PATH . FOLDER_INSTALLATION . '/db_scripts/preferences.php');

        // written as one statement instead of through SettingsManager::setMulti(): that saves one
        // Preferences entity per row and takes about twenty seconds for the whole set, which is
        // far too slow to call from a test
        $rows = array();
        $values = array();
        foreach ($defaultOrgPreferences as $name => $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            $rows[] = '(?, ?, ?)';
            array_push($values, $orgId, $name, (string) $value);
        }

        $sql = 'INSERT INTO ' . TBL_PREFERENCES . ' (prf_org_id, prf_name, prf_value)
                VALUES ' . implode(', ', $rows);
        $this->gDb->queryPrepared($sql, $values);
    }

    /**
     * Create and save a role that carries a given set of rights
     *
     * @param string $name Role name
     * @param int $orgId Organization ID
     * @param array<string,int> $rights Role right columns to set, e.g. array('rol_edit_user' => 1)
     * @return array Role data with rol_id
     */
    public function createAndSaveRoleWithRights(string $name, int $orgId, array $rights = []): array
    {
        $role = new Role($this->gDb);
        // no $gCurrentUser is logged in during tests, so the category edit-rights check has to be bypassed
        $role->saveChangesWithoutRights();
        $role->setValue('rol_name', $name);
        $role->setValue('rol_cat_id', $this->getDefaultRoleCategory($orgId));

        foreach ($rights as $column => $value) {
            $role->setValue($column, $value);
        }
        $role->save();

        $roleData = [
            'rol_id' => (int) $role->getValue('rol_id'),
            'rol_uuid' => $role->getValue('rol_uuid'),
            'rol_name' => $role->getValue('rol_name'),
            'org_id' => $orgId,
        ];

        $this->createdIds['roles'][] = $roleData['rol_id'];
        return $roleData;
    }

    /**
     * Assign a user to a role with an explicit membership period and leader flag
     *
     * @param int $usrId User ID
     * @param int $rolId Role ID
     * @param string $begin First day of the membership
     * @param string $end Last day of the membership
     * @param bool $leader Whether the user is a leader of the role
     * @return array Membership data with mem_id
     */
    public function assignUserToRolePeriod(int $usrId, int $rolId, string $begin, string $end, bool $leader = false): array
    {
        $membership = new Membership($this->gDb);
        $membership->setValue('mem_usr_id', $usrId);
        $membership->setValue('mem_rol_id', $rolId);
        $membership->setValue('mem_begin', $begin);
        $membership->setValue('mem_end', $end);
        $membership->setValue('mem_leader', $leader ? 1 : 0);
        $membership->save();

        $memData = [
            'mem_id' => (int) $membership->getValue('mem_id'),
            'mem_usr_id' => $usrId,
            'mem_rol_id' => $rolId,
        ];

        $this->createdIds['memberships'][] = $memData['mem_id'];
        return $memData;
    }

    /**
     * Delete a membership through its entity, so that it stays in the changelog
     *
     * @param int $memId Membership ID
     * @return bool True if deleted
     */
    public function deleteMembership(int $memId): bool
    {
        $membership = new Membership($this->gDb, $memId);
        if ((int) $membership->getValue('mem_id') === 0) {
            return false;
        }
        $membership->delete();

        return true;
    }

    /**
     * Set the rol_valid flag of an existing role
     *
     * @param int $rolId Role ID
     * @param bool $valid Validity flag to store
     * @return void
     */
    public function setRoleValidity(int $rolId, bool $valid): void
    {
        $role = new Role($this->gDb, $rolId);
        $role->saveChangesWithoutRights();
        $role->setValue('rol_valid', $valid ? 1 : 0);
        $role->save();
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

    /**
     * Mark user as valid
     *
     * @param int $usrId User ID
     * @return void
     */
    public function markUserAsValid(int $usrId): void
    {
        $this->setUserValidity($usrId, true);
    }

    /**
     * Set the usr_valid flag of an existing user
     *
     * @param int $usrId User ID
     * @param bool $valid Validity flag to store
     * @return void
     */
    public function setUserValidity(int $usrId, bool $valid): void
    {
        $user = new User($this->gDb);
        $user->readDataById($usrId);
        // no $gCurrentUser is logged in during tests, so the edit-rights check has to be bypassed
        $user->saveChangesWithoutRights();
        $user->setValue('usr_valid', $valid ? 1 : 0);
        $user->save();
    }

    /**
     * Get organization from database by ID
     *
     * @param int $orgId Organization ID
     * @return array Organization data
     */
    public function getOrganizationById(int $orgId): array
    {
        $sql = 'SELECT * FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ?';
        $result = $this->gDb->queryPrepared($sql, [$orgId]);
        return $result->fetch() ?: [];
    }

    /**
     * Get category from database by ID
     *
     * @param int $catId Category ID
     * @return array Category data
     */
    public function getCategoryById(int $catId): array
    {
        $sql = 'SELECT * FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?';
        $result = $this->gDb->queryPrepared($sql, [$catId]);
        return $result->fetch() ?: [];
    }

    /**
     * Check if user is valid
     *
     * @param int $usrId User ID
     * @return bool True if user is valid
     */
    public function isUserValid(int $usrId): bool
    {
        $sql = 'SELECT usr_valid FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $result = $this->gDb->queryPrepared($sql, [$usrId]);
        if ($result && $result->rowCount() > 0) {
            $row = $result->fetch();
            return (bool) ($row['usr_valid'] ?? false);
        }
        return false;
    }
}
