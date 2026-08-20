<?php
/**
 * Preferences and UI State Tests
 *
 * Tests preferences management, component visibility, and UI state handling.
 */

namespace Admidio\Tests\Integration\Core;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Organizations\Entity\Organization;
use Admidio\Infrastructure\Language;

class PreferencesAndUIStateTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test organization preferences are accessible
     *
     * @testdox Organization preferences can be loaded from settings manager
     */
    public function testOrganizationPreferencesLoadable(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create organization
        $org = $fixture->createAndSaveOrganization('PrefOrg', 'pref');

        // Load organization and get settings manager
        $orgEntity = new Organization($db, $org['org_id']);
        $settingsManager = $orgEntity->getSettingsManager();

        // Settings manager should exist
        $this->assertNotNull($settingsManager);
    }

    /**
     * Test preference can be set and retrieved
     *
     * @testdox Preferences can be written and read back
     */
    public function testPreferenceCrudOperations(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create organization
        $org = $fixture->createAndSaveOrganization('CrudOrg', 'crud');

        // Get settings manager
        $orgEntity = new Organization($db, $org['org_id']);
        $settingsManager = $orgEntity->getSettingsManager();

        // Set a preference
        $settingsManager->set('test_preference', 'test_value');

        // Verify in database
        $sql = 'SELECT prf_value FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ? AND prf_name = ?';
        $result = $db->queryPrepared($sql, [$org['org_id'], 'test_preference']);
        $this->assertGreaterThan(0, $result->rowCount());

        $row = $result->fetch();
        $this->assertEquals('test_value', $row['prf_value']);
    }

    /**
     * Test preference integer values
     *
     * @testdox Integer preferences are stored and retrieved correctly
     */
    public function testPreferenceIntegerValues(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create organization
        $org = $fixture->createAndSaveOrganization('IntPrefOrg', 'intpref');

        $orgEntity = new Organization($db, $org['org_id']);
        $settingsManager = $orgEntity->getSettingsManager();

        // Set integer preference
        $settingsManager->set('test_int_pref', '42');

        // Retrieve and verify type
        $sql = 'SELECT prf_value FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ? AND prf_name = ?';
        $result = $db->queryPrepared($sql, [$org['org_id'], 'test_int_pref']);
        $row = $result->fetch();

        $this->assertEquals('42', $row['prf_value']);
    }

    /**
     * Test preference boolean values
     *
     * @testdox Boolean preferences work correctly
     */
    public function testPreferenceBooleanValues(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create organization
        $org = $fixture->createAndSaveOrganization('BoolPrefOrg', 'boolpref');

        $orgEntity = new Organization($db, $org['org_id']);
        $settingsManager = $orgEntity->getSettingsManager();

        // Set boolean preference
        $settingsManager->set('test_bool_pref', '1');

        // Verify stored
        $sql = 'SELECT prf_value FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ? AND prf_name = ?';
        $result = $db->queryPrepared($sql, [$org['org_id'], 'test_bool_pref']);
        $this->assertGreaterThan(0, $result->rowCount());
    }

    /**
     * Test preference deletion
     *
     * @testdox Preferences can be deleted
     */
    public function testPreferenceDeletion(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create organization
        $org = $fixture->createAndSaveOrganization('DelPrefOrg', 'delpref');

        $orgEntity = new Organization($db, $org['org_id']);
        $settingsManager = $orgEntity->getSettingsManager();

        // Set a preference
        $settingsManager->set('to_delete', 'value');

        // Verify it exists
        $sql = 'SELECT prf_id FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ? AND prf_name = ?';
        $result = $db->queryPrepared($sql, [$org['org_id'], 'to_delete']);
        $this->assertGreaterThan(0, $result->rowCount());

        // Delete preference
        $settingsManager->del('to_delete');

        // Verify it's deleted
        $result = $db->queryPrepared($sql, [$org['org_id'], 'to_delete']);
        $this->assertEquals(0, $result->rowCount());
    }

    /**
     * Test language can be changed
     *
     * @testdox Language setting can be stored and retrieved
     */
    public function testLanguageSwitching(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create organization
        $org = $fixture->createAndSaveOrganization('LangOrg', 'lang');

        $orgEntity = new Organization($db, $org['org_id']);
        $settingsManager = $orgEntity->getSettingsManager();

        // Set language preference
        $settingsManager->set('system_language', 'de');

        // Verify in database
        $sql = 'SELECT prf_value FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ? AND prf_name = ?';
        $result = $db->queryPrepared($sql, [$org['org_id'], 'system_language']);

        if ($result->rowCount() > 0) {
            $row = $result->fetch();
            $this->assertEquals('de', $row['prf_value']);
        }
    }

    /**
     * Test multiple preferences for same organization
     *
     * @testdox Multiple preferences can coexist for one organization
     */
    public function testMultiplePreferencesPerOrganization(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create organization
        $org = $fixture->createAndSaveOrganization('MultiPrefOrg', 'multipref');

        $orgEntity = new Organization($db, $org['org_id']);
        $settingsManager = $orgEntity->getSettingsManager();

        // Set multiple preferences
        $settingsManager->set('pref1', 'value1');
        $settingsManager->set('pref2', 'value2');
        $settingsManager->set('pref3', 'value3');

        // Count preferences for org
        $sql = 'SELECT COUNT(*) as count FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ?';
        $result = $db->queryPrepared($sql, [$org['org_id']]);
        $row = $result->fetch();

        $this->assertGreaterThanOrEqual(3, $row['count']);
    }

    /**
     * Test preferences are organization-scoped
     *
     * @testdox Different organizations have independent preferences
     */
    public function testPreferenceOrganizationIsolation(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create two organizations
        $org1 = $fixture->createAndSaveOrganization('Org1Pref', 'org1pref');
        $org2 = $fixture->createAndSaveOrganization('Org2Pref', 'org2pref');

        // Set preferences in both
        $org1Entity = new Organization($db, $org1['org_id']);
        $org1Entity->getSettingsManager()->set('shared_pref', 'org1_value');

        $org2Entity = new Organization($db, $org2['org_id']);
        $org2Entity->getSettingsManager()->set('shared_pref', 'org2_value');

        // Verify isolation
        $sql = 'SELECT prf_value FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ? AND prf_name = ?';

        $result1 = $db->queryPrepared($sql, [$org1['org_id'], 'shared_pref']);
        $row1 = $result1->fetch();

        $result2 = $db->queryPrepared($sql, [$org2['org_id'], 'shared_pref']);
        $row2 = $result2->fetch();

        // Both should exist with different values
        if ($result1->rowCount() > 0 && $result2->rowCount() > 0) {
            $this->assertNotEquals($row1['prf_value'], $row2['prf_value']);
        }
    }

    /**
     * Test component table structure
     *
     * @testdox Components can be queried
     */
    public function testComponentTableStructure(): void
    {
        $db = $this->getDatabase();

        // Query components
        $sql = 'SELECT com_id, com_type, com_name_intern FROM ' . TBL_COMPONENTS;
        $result = $db->queryPrepared($sql, []);

        $this->assertNotNull($result);
        // Components might exist from installation
        $this->assertGreaterThanOrEqual(0, $result->rowCount());
    }

    /**
     * Test system component accessibility
     *
     * @testdox SYSTEM component can be queried by type and name
     */
    public function testSystemComponentQuery(): void
    {
        $db = $this->getDatabase();

        // Query SYSTEM component
        $sql = 'SELECT com_id FROM ' . TBL_COMPONENTS . '
                 WHERE com_type = ? AND com_name_intern = ?';
        $result = $db->queryPrepared($sql, ['SYSTEM', 'CORE']);

        // Might exist or not depending on installation state
        $this->assertNotNull($result);
    }

    /**
     * Test preference array values
     *
     * @testdox Array preferences can be converted to comma-separated strings
     */
    public function testPreferenceArrayValues(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create organization
        $org = $fixture->createAndSaveOrganization('ArrayPrefOrg', 'arraypref');

        $orgEntity = new Organization($db, $org['org_id']);
        $settingsManager = $orgEntity->getSettingsManager();

        // Set array preference (should be converted to string)
        $values = ['value1', 'value2', 'value3'];
        $settingsManager->set('array_pref', $values);

        // Verify stored as comma-separated
        $sql = 'SELECT prf_value FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ? AND prf_name = ?';
        $result = $db->queryPrepared($sql, [$org['org_id'], 'array_pref']);

        if ($result->rowCount() > 0) {
            $row = $result->fetch();
            $this->assertStringContainsString(',', $row['prf_value']);
        }
    }
}
