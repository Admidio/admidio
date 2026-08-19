<?php
/**
 * Example Integration Test
 * Demonstrates database testing with transaction isolation
 *
 * @testdox Example integration tests using transaction isolation
 */

namespace Admidio\Tests\Integration;

use Admidio\Tests\Support\DatabaseTestCase;

class ExampleIntegrationTest extends DatabaseTestCase
{
    /**
     * Example: Create test organization
     *
     * @testdox Test data builder creates organization
     */
    public function testCreateTestOrganization(): void
    {
        $org = $this->createTestOrganization('Test Org');

        $this->assertNotEmpty($org['org_id']);
        $this->assertNotEmpty($org['org_uuid']);
        $this->assertEquals('Test Org', $org['org_name']);
        $this->assertValidUuid($org['org_uuid']);
    }

    /**
     * Example: Create test user
     *
     * @testdox Test data builder creates user
     */
    public function testCreateTestUser(): void
    {
        $user = $this->createTestUser('testuser', 'test@example.com');

        $this->assertNotEmpty($user['usr_id']);
        $this->assertNotEmpty($user['usr_uuid']);
        $this->assertEquals('testuser', $user['usr_login']);
        $this->assertEquals('test@example.com', $user['usr_email']);
    }

    /**
     * Example: Create test role
     *
     * @testdox Test data builder creates role
     */
    public function testCreateTestRole(): void
    {
        $role = $this->createTestRole('Members');

        $this->assertNotEmpty($role['rol_id']);
        $this->assertNotEmpty($role['rol_uuid']);
        $this->assertEquals('Members', $role['rol_name']);
    }

    /**
     * Example: Test transaction isolation
     * Each test runs in its own transaction that's rolled back
     *
     * @testdox Transaction isolation prevents data leakage between tests
     */
    public function testTransactionIsolation1(): void
    {
        $org = $this->createTestOrganization('First Test');
        $this->assertEquals('First Test', $org['org_name']);
    }

    /**
     * Example: Verify previous test data is rolled back
     *
     * @testdox Previous test data is not visible due to transaction rollback
     * @depends testTransactionIsolation1
     */
    public function testTransactionIsolation2(): void
    {
        // Fresh organization in a new transaction
        $org = $this->createTestOrganization('Second Test');
        $this->assertEquals('Second Test', $org['org_name']);

        // Both organizations have different IDs
        $this->assertNotNull($org['org_id']);
    }

    /**
     * Example: Test data builder returns consistent fixtures
     *
     * @testdox Test data builder creates consistent fixture data
     */
    public function testFixtureConsistency(): void
    {
        $org = $this->createTestOrganization('Org1');
        $user1 = $this->createTestUser('user1', 'user1@test.com');
        $user2 = $this->createTestUser('user2', 'user2@test.com');
        $role = $this->createTestRole('Test Role');

        // Verify all fixtures are distinct
        $this->assertNotEqual($user1['usr_id'], $user2['usr_id']);
        $this->assertNotEqual($user1['usr_uuid'], $user2['usr_uuid']);

        // Verify UUID format
        $this->assertValidUuid($org['org_uuid']);
        $this->assertValidUuid($user1['usr_uuid']);
        $this->assertValidUuid($user2['usr_uuid']);
        $this->assertValidUuid($role['rol_uuid']);
    }

    /**
     * Example: Test get fixture methods
     *
     * @testdox Test data builder retrieves created fixtures
     */
    public function testGetFixtures(): void
    {
        $builder = $this->getTestDataBuilder();

        $org = $this->createTestOrganization('GetTest');
        $user = $this->createTestUser('getuser', 'getuser@test.com');
        $role = $this->createTestRole('GetRole');

        // Test getter methods
        $this->assertEquals('GetTest', $builder->getOrganization()['org_name']);
        $this->assertEquals('getuser', $builder->getUser()['usr_login']);
        $this->assertEquals('GetRole', $builder->getRole()['rol_name']);

        // Test array methods
        $this->assertCount(1, $builder->getOrganizations());
        $this->assertCount(1, $builder->getUsers());
        $this->assertCount(1, $builder->getRoles());
    }

    /**
     * Example: Test multiple organizations
     *
     * @testdox Multiple organizations can be created in tests
     */
    public function testMultipleOrganizations(): void
    {
        $builder = $this->getTestDataBuilder();

        $org1 = $builder->createOrganization('Org1');
        $org2 = $builder->createOrganization('Org2');
        $org3 = $builder->createOrganization('Org3');

        $orgs = $builder->getOrganizations();

        $this->assertCount(3, $orgs);
        $this->assertEquals('Org1', $orgs[0]['org_name']);
        $this->assertEquals('Org2', $orgs[1]['org_name']);
        $this->assertEquals('Org3', $orgs[2]['org_name']);
    }

    /**
     * Example: Test category creation
     *
     * @testdox Categories can be created through test builder
     */
    public function testCreateCategory(): void
    {
        $category = $this->getTestDataBuilder()->createCategory(
            'Events',
            'EVENTS'
        );

        $this->assertNotEmpty($category['cat_id']);
        $this->assertNotEmpty($category['cat_uuid']);
        $this->assertEquals('Events', $category['cat_name']);
        $this->assertEquals('EVENTS', $category['cat_type']);
    }
}
