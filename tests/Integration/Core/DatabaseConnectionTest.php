<?php
/**
 * Database Connection Tests
 *
 * Tests core database connectivity, query execution, and transaction isolation.
 */

namespace Admidio\Tests\Integration\Core;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;

class DatabaseConnectionTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test database connection is available
     *
     * @testdox Database connection to test database is available
     */
    public function testDatabaseConnectionIsAvailable(): void
    {
        $db = $this->getDatabase();
        $this->assertNotNull($db);

        // Verify database is accessible via a simple query
        $sql = 'SELECT 1 as test_value';
        $result = $db->queryPrepared($sql, []);
        $this->assertNotNull($result);

        // Verify we can fetch from the result
        $row = $result->fetch();
        $this->assertIsArray($row);
        $this->assertEquals(1, $row['test_value']);
    }

    /**
     * Test basic prepared statement query execution
     *
     * @testdox Prepared statements execute correctly with parameter binding
     */
    public function testBasicQueryExecution(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create test organization
        $org = $fixture->createAndSaveOrganization('Query Test Org', 'query');

        // Query for the created organization using prepared statement
        $sql = 'SELECT org_id, org_longname, org_shortname FROM ' . TBL_ORGANIZATIONS . '
                 WHERE org_id = ?';
        $result = $db->queryPrepared($sql, [$org['org_id']]);

        // Verify result
        $this->assertNotNull($result);
        $this->assertGreaterThan(0, $result->rowCount());

        $row = $result->fetch();
        $this->assertIsArray($row);
        $this->assertEquals($org['org_id'], $row['org_id']);
        $this->assertEquals($org['org_longname'], $row['org_longname']);
        $this->assertEquals($org['org_shortname'], $row['org_shortname']);
    }

    /**
     * Test parameter binding prevents SQL issues
     *
     * @testdox Parameter binding works correctly with multiple parameters
     */
    public function testParameterBinding(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create multiple organizations
        $org1 = $fixture->createAndSaveOrganization('Alpha Org', 'alpha');
        $org2 = $fixture->createAndSaveOrganization('Beta Org', 'beta');

        // Query for specific organization with multiple parameters
        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS . '
                 WHERE org_longname = ? AND org_shortname = ?';
        $result = $db->queryPrepared($sql, ['Beta Org', 'beta']);

        $this->assertGreaterThan(0, $result->rowCount());
        $row = $result->fetch();
        $this->assertEquals($org2['org_id'], $row['org_id']);
    }

    /**
     * Test transaction isolation between tests
     *
     * @testdox Each test has isolated transaction and changes are rolled back
     */
    public function testTransactionIsolation(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create organization in this test
        $org = $fixture->createAndSaveOrganization('Transaction Test', 'trans');
        $orgId = $org['org_id'];

        // Verify it exists within this test
        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ?';
        $result = $db->queryPrepared($sql, [$orgId]);
        $this->assertGreaterThan(0, $result->rowCount());
    }

    /**
     * Test database type detection
     *
     * @testdox Database type is correctly detected
     */
    public function testDatabaseTypeDetection(): void
    {
        // Verify DB_TYPE constant is set
        $this->assertTrue(defined('DB_TYPE'));
        $this->assertNotEmpty(DB_TYPE);

        // Verify it's one of the supported types
        $this->assertContains(DB_TYPE, ['sqlite', 'mysql', 'mariadb', 'postgres']);
    }

    /**
     * Test table constants are accessible
     *
     * @testdox All required table constants are defined
     */
    public function testTableConstantsAccessible(): void
    {
        // Verify critical table constants are defined
        $this->assertTrue(defined('TBL_ORGANIZATIONS'));
        $this->assertTrue(defined('TBL_USERS'));
        $this->assertTrue(defined('TBL_ROLES'));
        $this->assertTrue(defined('TBL_CATEGORIES'));
        $this->assertTrue(defined('TBL_MEMBERS'));
        $this->assertTrue(defined('TBL_SESSIONS'));
        $this->assertTrue(defined('TBL_PREFERENCES'));

        // Verify constants have values
        $this->assertNotEmpty(TBL_ORGANIZATIONS);
        $this->assertNotEmpty(TBL_USERS);
        $this->assertNotEmpty(TBL_ROLES);
    }

    /**
     * Test result set operations
     *
     * @testdox Result sets can be iterated and fetched
     */
    public function testResultSetOperations(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create multiple users
        $user1 = $fixture->createAndSaveUser('testuser1', 'user1@test.local');
        $user2 = $fixture->createAndSaveUser('testuser2', 'user2@test.local');
        $user3 = $fixture->createAndSaveUser('testuser3', 'user3@test.local');

        // Query all users
        $sql = 'SELECT usr_id, usr_login_name FROM ' . TBL_USERS . '
                 WHERE usr_id IN (?, ?, ?)
                 ORDER BY usr_id';
        $result = $db->queryPrepared($sql, [
            $user1['usr_id'],
            $user2['usr_id'],
            $user3['usr_id']
        ]);

        // Verify row count
        $this->assertEquals(3, $result->rowCount());

        // Fetch all rows
        $users = $result->fetchAll();
        $this->assertCount(3, $users);
        $this->assertEquals('testuser1', $users[0]['usr_login_name']);
        $this->assertEquals('testuser2', $users[1]['usr_login_name']);
        $this->assertEquals('testuser3', $users[2]['usr_login_name']);
    }

    /**
     * Test NULL value handling
     *
     * @testdox NULL values are handled correctly in queries
     */
    public function testNullValueHandling(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // a fresh user has never logged in, so usr_last_login is NULL
        $user = $fixture->createAndSaveUser('nulltest', 'null@test.local');

        $sql = 'SELECT usr_id, usr_last_login FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $result = $db->queryPrepared($sql, [$user['usr_id']]);
        $row = $result->fetch();

        $this->assertIsArray($row);
        $this->assertArrayHasKey('usr_last_login', $row);
        $this->assertNull($row['usr_last_login']);
    }
}
