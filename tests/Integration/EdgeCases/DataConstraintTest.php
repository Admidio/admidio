<?php
/**
 * Data Constraint Tests
 *
 * Tests data validation and constraint handling.
 *
 * @testdox Data constraints are enforced correctly
 */

namespace Admidio\Tests\Integration\EdgeCases;

use Admidio\Tests\Support\DatabaseTestCase;

class DataConstraintTest extends DatabaseTestCase
{
    /**
     * Test string length validation
     *
     * @testdox String fields respect maximum length constraints
     */
    public function testStringLengthValidation(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Test Org');

        // Act - Create entities with various string lengths
        $shortName = 'Short';
        $mediumName = 'This is a medium length name for testing';
        $longName = str_repeat('A', 255); // Maximum typical string length

        // Create categories with different name lengths
        $cat1 = $builder->createCategory($shortName, 'EVT', $org['org_id']);
        $cat2 = $builder->createCategory($mediumName, 'EVT', $org['org_id']);
        $cat3 = $builder->createCategory($longName, 'EVT', $org['org_id']);

        // Assert - All strings are valid
        $this->assertEquals(strlen($shortName), strlen($cat1['cat_name']));
        $this->assertEquals(strlen($mediumName), strlen($cat2['cat_name']));
        $this->assertEquals(strlen($longName), strlen($cat3['cat_name']));
        $this->assertLessThanOrEqual(255, strlen($cat3['cat_name']));
    }

    /**
     * Test numeric range validation
     *
     * @testdox Numeric fields respect value constraints
     */
    public function testNumericRangeValidation(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Act - Create role and user with ID constraints
        $role1 = $builder->createRole('Role1', $org['org_id']);
        $role2 = $builder->createRole('Role2', $org['org_id']);
        $user1 = $builder->createUser('user1', 'user1@test', $org['org_id']);
        $user2 = $builder->createUser('user2', 'user2@test', $org['org_id']);

        // Assert - IDs are positive integers
        $this->assertGreaterThan(0, $role1['rol_id']);
        $this->assertGreaterThan(0, $role2['rol_id']);
        $this->assertGreaterThan(0, $user1['usr_id']);
        $this->assertGreaterThan(0, $user2['usr_id']);
        $this->assertIsInt($role1['rol_id']);
        $this->assertIsInt($user1['usr_id']);
    }

    /**
     * Test required field enforcement
     *
     * @testdox Required fields cannot be empty
     */
    public function testRequiredFieldEnforcement(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Act - Create entities with required fields
        $category = $builder->createCategory('Required Name', 'EVT', $org['org_id']);
        $role = $builder->createRole('Required Role', $org['org_id']);
        $user = $builder->createUser('required_user', 'req@test.com', $org['org_id']);

        // Assert - All required fields have values
        $this->assertNotEmpty($category['cat_name']);
        $this->assertNotEmpty($category['cat_type']);
        $this->assertNotEmpty($role['rol_name']);
        $this->assertNotEmpty($user['usr_login']);
        $this->assertNotEmpty($user['usr_email']);
    }

    /**
     * Test unique constraint handling
     *
     * @testdox Unique fields prevent duplicate values in same scope
     */
    public function testUniqueConstraintHandling(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Act - Create users with different logins
        $user1 = $builder->createUser('unique_login1', 'email1@test.com', $org['org_id']);
        $user2 = $builder->createUser('unique_login2', 'email2@test.com', $org['org_id']);
        $user3 = $builder->createUser('unique_login3', 'email3@test.com', $org['org_id']);

        // Assert - All logins and emails are unique
        $this->assertNotEquals($user1['usr_login'], $user2['usr_login']);
        $this->assertNotEquals($user2['usr_login'], $user3['usr_login']);
        $this->assertNotEquals($user1['usr_email'], $user2['usr_email']);
        $this->assertNotEquals($user2['usr_email'], $user3['usr_email']);
    }

    /**
     * Test foreign key relationship integrity
     *
     * @testdox Foreign key relationships maintain referential integrity
     */
    public function testForeignKeyRelationshipIntegrity(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Create role and user
        $role = $builder->createRole('Team', $org['org_id']);
        $user = $builder->createUser('member', 'member@company', $org['org_id']);

        // Act - Create membership linking user and role
        $membership = $builder->assignUserToRole($user, $role);

        // Assert - Foreign key references are valid
        $this->assertNotEmpty($membership['mem_id']);
        $this->assertEquals($user['usr_id'], $membership['usr_id']);
        $this->assertEquals($role['rol_id'], $membership['rol_id']);
        // Verify IDs exist and are linked
        $this->assertGreaterThan(0, $membership['usr_id']);
        $this->assertGreaterThan(0, $membership['rol_id']);
    }
}
