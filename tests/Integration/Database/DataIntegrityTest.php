<?php
/**
 * Data Integrity Tests
 *
 * Tests that data is correctly stored and retrieved with proper types and formats.
 */

namespace Admidio\Tests\Integration\Database;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;

class DataIntegrityTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test organization data types
     *
     * @testdox Organization data is stored with correct types
     */
    public function testOrganizationDataTypes(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org', 'test');

        // Verify organization was created
        $this->assertIsArray($org);
        $this->assertNotEmpty($org);

        // Verify data types and values
        $this->assertArrayHasKey('org_id', $org);
        $this->assertIsInt($org['org_id']);

        $this->assertArrayHasKey('org_uuid', $org);
        $this->assertIsString($org['org_uuid']);
        $this->assertNotEmpty($org['org_uuid']);

        $this->assertArrayHasKey('org_longname', $org);
        $this->assertIsString($org['org_longname']);
        $this->assertNotEmpty($org['org_longname']);

        $this->assertArrayHasKey('org_shortname', $org);
        $this->assertIsString($org['org_shortname']);
        $this->assertNotEmpty($org['org_shortname']);
    }

    /**
     * Test user data types and consistency
     *
     * @testdox User data maintains type consistency
     */
    public function testUserDataTypes(): void
    {
        $fixture = $this->getFixture();
        $user = $fixture->createAndSaveUser('integrity_user', 'integrity@example.local');

        // Verify types
        $this->assertIsInt($user['usr_id']);
        $this->assertIsString($user['usr_uuid']);
        $this->assertIsString($user['usr_login_name']);

        // Verify data
        $this->assertGreaterThan(0, $user['usr_id']);
        $this->assertNotEmpty($user['usr_uuid']);
        $this->assertNotEmpty($user['usr_login_name']);
    }

    /**
     * Test category data consistency
     *
     * @testdox Category data is stored consistently
     */
    public function testCategoryDataConsistency(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');
        $cat = $fixture->createAndSaveCategory('Test Category', 'EVT', $org['org_id']);

        // Verify data
        $this->assertIsInt($cat['cat_id']);
        $this->assertIsString($cat['cat_uuid']);
        $this->assertIsString($cat['cat_name']);
        $this->assertIsString($cat['cat_type']);
        $this->assertIsInt($cat['org_id']);

        // Re-fetch from database to verify consistency
        $sql = 'SELECT cat_id, cat_uuid, cat_name, cat_type, cat_org_id FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$cat['cat_id']]);
        $dbCat = $result->fetch();

        $this->assertEquals($cat['cat_id'], $dbCat['cat_id']);
        $this->assertEquals($cat['cat_name'], $dbCat['cat_name']);
        $this->assertEquals($cat['cat_type'], $dbCat['cat_type']);
        $this->assertEquals($cat['org_id'], $dbCat['cat_org_id']);
    }


    /**
     * Test data retrieval after creation
     *
     * @testdox Created data can be reliably retrieved
     */
    public function testDataRetrievalConsistency(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Retrieve Test', 'retrieve');
        $user = $fixture->createAndSaveUser('retrieve_user', 'retrieve@example.local');

        // Create data
        $cat = $fixture->createAndSaveCategory('Events', 'EVT', $org['org_id']);

        // Retrieve using fixture
        $retrievedOrg = $this->getOrganization($org['org_id']);
        $retrievedUser = $fixture->getUserById($user['usr_id']);
        $retrievedCat = $this->getCategory($cat['cat_id']);

        // Verify retrieval
        $this->assertNotNull($retrievedOrg);
        $this->assertNotEmpty($retrievedUser);
        $this->assertNotNull($retrievedCat);

        // Verify data matches
        if ($retrievedOrg) {
            $this->assertEquals($org['org_longname'], $retrievedOrg['org_longname']);
        }
        $this->assertEquals($user['usr_login_name'], $retrievedUser['usr_login_name']);
        if ($retrievedCat) {
            $this->assertEquals($cat['cat_name'], $retrievedCat['cat_name']);
        }
    }

    /**
     * Test UUID format consistency
     *
     * @testdox All UUIDs follow standard format
     */
    public function testUUIDFormatConsistency(): void
    {
        $fixture = $this->getFixture();
        $uuidPattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';

        // Create various entities and verify UUID formats
        $org = $fixture->createAndSaveOrganization('UUID Test');
        $this->assertMatchesRegularExpression($uuidPattern, $org['org_uuid']);

        $user = $fixture->createAndSaveUser('uuid_user', 'uuid@example.local');
        $this->assertMatchesRegularExpression($uuidPattern, $user['usr_uuid']);

        $cat = $fixture->createAndSaveCategory('Events', 'EVT', $org['org_id']);
        $this->assertMatchesRegularExpression($uuidPattern, $cat['cat_uuid']);
    }

    /**
     * Helper: Get organization from database
     */
    private function getOrganization(int $orgId): ?array
    {
        $sql = 'SELECT * FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$orgId]);
        return $result->fetch() ?: null;
    }

    /**
     * Helper: Get category from database
     */
    private function getCategory(int $catId): ?array
    {
        $sql = 'SELECT * FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$catId]);
        return $result->fetch() ?: null;
    }
}
