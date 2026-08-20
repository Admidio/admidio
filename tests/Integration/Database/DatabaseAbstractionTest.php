<?php
/**
 * Database Abstraction Layer Tests
 *
 * Validates that Admidio's generic SQL works correctly across all supported database engines.
 * Tests MySQL, MariaDB, and PostgreSQL behavior.
 *
 * @testdox Database abstraction layer handles cross-database SQL correctly
 */

namespace Admidio\Tests\Integration\Database;

use Admidio\Tests\Support\DatabaseTestCase;

class DatabaseAbstractionTest extends DatabaseTestCase
{
    /**
     * Every test in this class builds its data with TestDataBuilder, which returns generated
     * arrays and never writes to the database, so none of them verifies any Admidio behaviour.
     * They are kept as a specification of what still needs coverage and will be reimplemented
     * against the real database for whatever the later test phases do not already cover.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestIncomplete('Uses the in-memory TestDataBuilder, needs a real database test.');
    }

    /**
     * Test database connection and basic query execution
     *
     * @testdox Database connection is established and working
     */
    public function testDatabaseConnection(): void
    {
        $database = $this->getDatabase();
        $this->assertNotNull($database);

        // Execute simple query
        $result = $database->queryPrepared('SELECT 1 as test_value');
        $this->assertNotNull($result);
    }

    /**
     * Test boolean value handling across engines
     *
     * @testdox Boolean values are stored and retrieved correctly
     */
    public function testBooleanHandling(): void
    {
        $database = $this->getDatabase();

        // Create a test user with boolean fields
        $builder = $this->getTestDataBuilder();
        $user = $builder->createUser('booltest', 'bool@test.local');

        // Most Admidio fields use tinyint(1) for booleans
        // This test verifies the pattern works
        $this->assertNotEmpty($user['usr_id']);
    }

    /**
     * Test UUID/GUID handling across engines
     *
     * @testdox UUIDs are stored and retrieved as valid values
     */
    public function testUuidHandling(): void
    {
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('UUIDTest');

        // Verify UUID format is valid across engines
        $this->assertNotEmpty($org['org_uuid']);
        $this->assertValidUuid($org['org_uuid']);
    }

    /**
     * Test LIMIT/OFFSET behavior across engines
     *
     * @testdox LIMIT and OFFSET work correctly on all engines
     */
    public function testLimitOffset(): void
    {
        $builder = $this->getTestDataBuilder();

        // Create multiple test records
        for ($i = 0; $i < 5; $i++) {
            $builder->createCategory("Category $i", 'TEST');
        }

        // In real tests, we'd query the database directly with LIMIT/OFFSET
        // This demonstrates the pattern
        $this->assertTrue(true);
    }

    /**
     * Test NULL value handling across engines
     *
     * @testdox NULL values are handled correctly in all engines
     */
    public function testNullHandling(): void
    {
        $builder = $this->getTestDataBuilder();
        $user = $builder->createUser('nulltest', 'null@test.local');

        // User has NULL end date for membership (indefinite)
        // This tests NULL handling across engines
        $this->assertNotNull($user['usr_id']);
    }

    /**
     * Test date/time handling across engines
     *
     * @testdox Date and time values are stored and retrieved correctly
     */
    public function testDateTimeHandling(): void
    {
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('DateTimeTest');

        // Timestamps should be valid
        $this->assertValidTimestamp($org['created_at']);
    }

    /**
     * Test transaction support across engines
     *
     * @testdox Transactions work with nested transaction support
     */
    public function testTransactionSupport(): void
    {
        $database = $this->getDatabase();
        $builder = $this->getTestDataBuilder();

        // Outer transaction started in setUp
        $org1 = $builder->createOrganization('TransTest1');
        $this->assertNotEmpty($org1['org_id']);

        // Create another org in same transaction
        $org2 = $builder->createOrganization('TransTest2');
        $this->assertNotEmpty($org2['org_id']);

        // Both should exist until rollback in tearDown
        $this->assertNotEquals($org1['org_id'], $org2['org_id']);
    }

    /**
     * Test foreign key constraints are enforced
     *
     * @testdox Foreign key relationships are validated
     */
    public function testForeignKeyHandling(): void
    {
        $builder = $this->getTestDataBuilder();

        // Create organization and user
        $org = $builder->createOrganization('FKTest');
        $user = $builder->createUser('fktest', 'fk@test.local', $org['org_id']);

        // User should reference valid organization
        $this->assertEquals($org['org_id'], $user['org_id']);
    }

    /**
     * Test auto-increment/SEQUENCE behavior across engines
     *
     * @testdox Auto-increment values are assigned correctly on all engines
     */
    public function testAutoIncrementBehavior(): void
    {
        $builder = $this->getTestDataBuilder();

        $org1 = $builder->createOrganization('AutoInc1');
        $org2 = $builder->createOrganization('AutoInc2');

        // IDs should be numeric and incrementing
        $this->assertIsInt($org1['org_id']);
        $this->assertIsInt($org2['org_id']);
        $this->assertGreaterThan(0, $org1['org_id']);
        $this->assertGreaterThan(0, $org2['org_id']);
    }

    /**
     * Test character encoding across engines
     *
     * @testdox UTF-8 characters are stored and retrieved correctly
     */
    public function testCharacterEncoding(): void
    {
        $builder = $this->getTestDataBuilder();

        // Test with UTF-8 characters
        $orgName = 'Ümläutë Tëst Örgänïzätîön';
        $org = $builder->createOrganization($orgName);

        $this->assertEquals($orgName, $org['org_name']);
    }

    /**
     * Test case sensitivity across engines
     *
     * @testdox String comparisons are case-sensitive where expected
     */
    public function testCaseSensitivity(): void
    {
        $builder = $this->getTestDataBuilder();

        $user1 = $builder->createUser('CaseTest', 'case@test.local');
        $user2 = $builder->createUser('casetest', 'case2@test.local');

        // Both should be created as different users
        $this->assertNotEquals($user1['usr_id'], $user2['usr_id']);
    }

    /**
     * Test ORDER BY behavior across engines
     *
     * @testdox ORDER BY clause works consistently across engines
     */
    public function testOrderByBehavior(): void
    {
        $builder = $this->getTestDataBuilder();

        // Create multiple organizations with different names
        $builder->createOrganization('Zebra');
        $builder->createOrganization('Alpha');
        $builder->createOrganization('Beta');

        $orgs = $builder->getOrganizations();

        // Should have all 3 organizations
        $this->assertCount(3, $orgs);
    }
}
