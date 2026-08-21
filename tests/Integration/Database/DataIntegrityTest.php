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
        $org = $fixture->createAndSaveOrganization('Test Org', 'integrity');

        // check the stored row, the fixture return value is only what was passed in
        $stored = $fixture->getOrganizationById($org['org_id']);
        $this->assertNotEmpty($stored);

        $this->assertIsInt($stored['org_id']);
        $this->assertGreaterThan(0, $stored['org_id']);

        $this->assertIsString($stored['org_uuid']);
        $this->assertNotEmpty($stored['org_uuid']);

        $this->assertEquals('Test Org', $stored['org_longname']);
        $this->assertEquals('integrity', $stored['org_shortname']);
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

        $stored = $fixture->getUserById($user['usr_id']);
        $this->assertNotEmpty($stored);

        // Verify types as they come back from the database
        $this->assertIsInt($stored['usr_id']);
        $this->assertIsString($stored['usr_uuid']);
        $this->assertIsString($stored['usr_login_name']);

        // Verify data
        $this->assertGreaterThan(0, $stored['usr_id']);
        $this->assertEquals('integrity_user', $stored['usr_login_name']);
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

        // Re-fetch from database to verify consistency
        $sql = 'SELECT cat_id, cat_uuid, cat_name, cat_type, cat_org_id FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$cat['cat_id']]);
        $dbCat = $result->fetch();

        $this->assertIsInt($dbCat['cat_id']);
        $this->assertIsString($dbCat['cat_uuid']);

        $this->assertEquals($cat['cat_id'], $dbCat['cat_id']);
        $this->assertEquals('Test Category', $dbCat['cat_name']);
        $this->assertEquals('EVT', $dbCat['cat_type']);
        $this->assertEquals($org['org_id'], (int) $dbCat['cat_org_id']);
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
        $cat = $fixture->createAndSaveCategory('Events', 'EVT', $org['org_id']);

        // Retrieve everything back
        $retrievedOrg = $fixture->getOrganizationById($org['org_id']);
        $retrievedUser = $fixture->getUserById($user['usr_id']);
        $retrievedCat = $fixture->getCategoryById($cat['cat_id']);

        // Verify retrieval, unconditionally
        $this->assertNotEmpty($retrievedOrg);
        $this->assertNotEmpty($retrievedUser);
        $this->assertNotEmpty($retrievedCat);

        // Verify data matches
        $this->assertEquals('Retrieve Test', $retrievedOrg['org_longname']);
        $this->assertEquals('retrieve_user', $retrievedUser['usr_login_name']);
        $this->assertEquals('Events', $retrievedCat['cat_name']);
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

        // Create various entities and verify the UUIDs that were actually stored
        $org = $fixture->createAndSaveOrganization('UUID Test');
        $this->assertMatchesRegularExpression(
            $uuidPattern,
            $fixture->getOrganizationById($org['org_id'])['org_uuid']
        );

        $user = $fixture->createAndSaveUser('uuid_user', 'uuid@example.local');
        $this->assertMatchesRegularExpression(
            $uuidPattern,
            $fixture->getUserById($user['usr_id'])['usr_uuid']
        );

        $cat = $fixture->createAndSaveCategory('Events', 'EVT', $org['org_id']);
        $this->assertMatchesRegularExpression(
            $uuidPattern,
            $fixture->getCategoryById($cat['cat_id'])['cat_uuid']
        );

        $role = $fixture->createAndSaveRole('UUID Role', $org['org_id']);
        $this->assertMatchesRegularExpression(
            $uuidPattern,
            $fixture->getRoleById($role['rol_id'])['rol_uuid']
        );
    }

    /**
     * Test that the unique ids really are unique
     *
     * @testdox The unique id of a record is never handed out twice
     */
    public function testUniqueIdOfARecordIsNeverHandedOutTwice(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Unique Org', 'uniqorg');

        $uuids = array();
        for ($index = 0; $index < 5; ++$index) {
            $role = $fixture->createAndSaveRole('Role ' . $index, $org['org_id']);
            $uuids[] = $fixture->getRoleById($role['rol_id'])['rol_uuid'];

            $user = $fixture->createAndSaveUser('uniq' . $index, 'u' . $index . '@example.local');
            $uuids[] = $fixture->getUserById($user['usr_id'])['usr_uuid'];

            $category = $fixture->createAndSaveCategory('Category ' . $index, 'ROL', $org['org_id']);
            $uuids[] = $fixture->getCategoryById($category['cat_id'])['cat_uuid'];
        }

        // a uuid is what links a record across requests, so a repeat would address the wrong record
        $this->assertCount(count($uuids), array_unique($uuids));
    }

    /**
     * Test that records remember when they were created and by whom
     *
     * @testdox A record records when it was created and who created it
     */
    public function testRecordRecordsWhenItWasCreatedAndByWhom(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Stamp Org', 'stamporg');
        $role = $fixture->createAndSaveRole('Stamped Role', $org['org_id']);

        $sql = 'SELECT rol_timestamp_create, rol_usr_id_create, rol_timestamp_change, rol_usr_id_change
                  FROM ' . TBL_ROLES . ' WHERE rol_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$role['rol_id']])->fetch();

        $this->assertNotEmpty($row['rol_timestamp_create']);
        $this->assertEquals(date('Y-m-d'), substr($row['rol_timestamp_create'], 0, 10));

        // nothing has been changed yet, so the change fingerprint is still empty
        $this->assertNull($row['rol_timestamp_change']);
        $this->assertNull($row['rol_usr_id_change']);
    }
}
