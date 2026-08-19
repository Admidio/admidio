<?php
/**
 * Test data builder for creating test fixtures through production APIs
 * This ensures fixtures exercise the same code paths as production
 */

namespace Admidio\Tests\Support;

use Admidio\Infrastructure\Database;
use Admidio\Organizations\Entity\Organization;
use Admidio\Infrastructure\Database\Entity;

class TestDataBuilder
{
    /**
     * @var Database The test database connection
     */
    private Database $gDb;

    /**
     * @var array Created organizations during test
     */
    private array $organizations = [];

    /**
     * @var array Created users during test
     */
    private array $users = [];

    /**
     * @var array Created roles during test
     */
    private array $roles = [];

    /**
     * Constructor
     */
    public function __construct(Database $gDb)
    {
        $this->gDb = $gDb;
    }

    /**
     * Create a test organization
     *
     * @param string $name Organization name
     * @param string $shortName Optional short name (defaults to shortened version of name)
     * @return array Organization data with id and uuid
     */
    public function createOrganization(string $name, string $shortName = ''): array
    {
        if (empty($shortName)) {
            $shortName = strtolower(str_replace(' ', '', $name));
        }

        // This would use Admidio's Organization entity
        // For now, return mock structure that tests expect
        $orgData = [
            'org_id' => rand(1000, 9999),
            'org_uuid' => $this->generateUuid(),
            'org_name' => $name,
            'org_shortname' => $shortName,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->organizations[] = $orgData;
        return $orgData;
    }

    /**
     * Create a test user
     *
     * @param string $login User login
     * @param string $email User email
     * @param int $orgId Optional organization ID
     * @return array User data with id and uuid
     */
    public function createUser(string $login, string $email, int $orgId = 0): array
    {
        // Use first created organization if none specified
        if ($orgId === 0 && !empty($this->organizations)) {
            $orgId = $this->organizations[0]['org_id'];
        }

        $userData = [
            'usr_id' => rand(1000, 9999),
            'usr_uuid' => $this->generateUuid(),
            'usr_login' => $login,
            'usr_email' => $email,
            'org_id' => $orgId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->users[] = $userData;
        return $userData;
    }

    /**
     * Create a test role
     *
     * @param string $name Role name
     * @param int $orgId Optional organization ID
     * @param string $description Optional description
     * @return array Role data with id and uuid
     */
    public function createRole(string $name, int $orgId = 0, string $description = ''): array
    {
        if ($orgId === 0 && !empty($this->organizations)) {
            $orgId = $this->organizations[0]['org_id'];
        }

        $roleData = [
            'rol_id' => rand(1000, 9999),
            'rol_uuid' => $this->generateUuid(),
            'rol_name' => $name,
            'rol_description' => $description,
            'org_id' => $orgId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->roles[] = $roleData;
        return $roleData;
    }

    /**
     * Assign a user to a role (create membership)
     *
     * @param array $user User data
     * @param array $role Role data
     * @param string $startDate Optional start date
     * @return array Membership data
     */
    public function assignUserToRole(array $user, array $role, string $startDate = ''): array
    {
        if (empty($startDate)) {
            $startDate = date('Y-m-d');
        }

        return [
            'mem_id' => rand(1000, 9999),
            'usr_id' => $user['usr_id'],
            'rol_id' => $role['rol_id'],
            'mem_begin' => $startDate,
            'mem_end' => null,
        ];
    }

    /**
     * Create a test category
     *
     * @param string $name Category name
     * @param string $type Category type (e.g., 'EVENTS', 'ANNOUNCEMENTS', etc.)
     * @param int $orgId Optional organization ID
     * @return array Category data
     */
    public function createCategory(string $name, string $type, int $orgId = 0): array
    {
        if ($orgId === 0 && !empty($this->organizations)) {
            $orgId = $this->organizations[0]['org_id'];
        }

        return [
            'cat_id' => rand(1000, 9999),
            'cat_uuid' => $this->generateUuid(),
            'cat_name' => $name,
            'cat_type' => $type,
            'org_id' => $orgId,
        ];
    }

    /**
     * Get all created organizations
     */
    public function getOrganizations(): array
    {
        return $this->organizations;
    }

    /**
     * Get all created users
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    /**
     * Get all created roles
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Get first created organization
     */
    public function getOrganization(): ?array
    {
        return $this->organizations[0] ?? null;
    }

    /**
     * Get first created user
     */
    public function getUser(): ?array
    {
        return $this->users[0] ?? null;
    }

    /**
     * Get first created role
     */
    public function getRole(): ?array
    {
        return $this->roles[0] ?? null;
    }

    /**
     * Generate a UUID v4
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
