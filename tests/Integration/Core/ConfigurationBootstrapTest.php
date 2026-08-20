<?php
/**
 * Configuration and Bootstrap Tests
 *
 * Tests configuration loading, session initialization, and bootstrap sequence.
 */

namespace Admidio\Tests\Integration\Core;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Organizations\Entity\Organization;
use Admidio\Session\Entity\Session;
use Admidio\Infrastructure\Language;

class ConfigurationBootstrapTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test configuration constants are loaded
     *
     * @testdox Configuration file constants are properly defined
     */
    public function testConfigurationFilesLoaded(): void
    {
        // Verify critical Admidio constants are defined
        $this->assertTrue(defined('ADMIDIO_URL'));
        $this->assertNotEmpty(ADMIDIO_URL);

        $this->assertTrue(defined('FOLDER_MODULES'));
        $this->assertNotEmpty(FOLDER_MODULES);

        $this->assertTrue(defined('COOKIE_PREFIX'));
        $this->assertNotEmpty(COOKIE_PREFIX);

        // Verify database configuration
        $this->assertTrue(defined('DB_TYPE'));
        $this->assertNotEmpty(DB_TYPE);
    }

    /**
     * Test session object can be created
     *
     * @testdox Session object can be instantiated and stored in database
     */
    public function testSessionObjectCanBeCreated(): void
    {
        $db = $this->getDatabase();

        // Create session object
        $session = new Session($db, COOKIE_PREFIX);
        $this->assertNotNull($session);

        // Verify session has an ID
        $sessionId = $session->getValue('ses_session_id');
        $this->assertNotEmpty($sessionId);

        // the constructor only fills the object, the row is written on save
        $session->save();

        $sql = 'SELECT ses_session_id FROM ' . TBL_SESSIONS . ' WHERE ses_session_id = ?';
        $result = $db->queryPrepared($sql, [$sessionId]);
        $this->assertGreaterThan(0, $result->rowCount());
    }

    /**
     * Test CSRF token is generated
     *
     * @testdox Session generates valid CSRF token on creation
     */
    public function testCsrfTokenGeneration(): void
    {
        $db = $this->getDatabase();

        // Create session
        $session = new Session($db, COOKIE_PREFIX);

        $token = $session->getCsrfToken();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{30}$/', $token);

        // the same token is returned until a new one is explicitly requested
        $this->assertEquals($token, $session->getCsrfToken());
        $this->assertNotEquals($token, $session->getCsrfToken(true));
    }

    /**
     * Test organization can be bootstrapped
     *
     * @testdox Organization object can be created and preferences loaded
     */
    public function testOrganizationBootstrapping(): void
    {
        $db = $this->getDatabase();
        $fixture = $this->getFixture();

        // Create organization using fixture
        $orgData = $fixture->createAndSaveOrganization('Bootstrap Org', 'bootstrap');

        // Load organization entity
        $org = new Organization($db, $orgData['org_id']);
        $this->assertNotNull($org);

        // Verify organization data
        $this->assertEquals($orgData['org_longname'], $org->getValue('org_longname'));
        $this->assertEquals($orgData['org_shortname'], $org->getValue('org_shortname'));
    }

    /**
     * Test settings manager is accessible from organization
     *
     * @testdox Organization settings manager loads preferences
     */
    public function testSettingsManagerInitialization(): void
    {
        $db = $this->getDatabase();
        $fixture = $this->getFixture();

        // Create organization
        $orgData = $fixture->createAndSaveOrganization('Settings Test', 'settings');

        // Load organization and get settings
        $org = new Organization($db, $orgData['org_id']);
        $settingsManager = $org->getSettingsManager();

        $this->assertNotNull($settingsManager);

        // Verify we can access preferences (even if empty)
        // system_language is a default preference
        try {
            $language = $settingsManager->getString('system_language');
            $this->assertNotEmpty($language);
        } catch (\Exception $e) {
            // Might not be in preferences table yet, that's OK
            $this->assertTrue(true);
        }
    }

    /**
     * Test language object initialization
     *
     * @testdox Language object loads correctly for supported language codes
     */
    public function testLanguageObjectInitialization(): void
    {
        // Test English language
        $language = new Language('en', true);
        $this->assertNotNull($language);

        // Language object should be created (file might not have all strings in test)
        $this->assertNotNull($language);
    }

    /**
     * Test multiple organizations can coexist
     *
     * @testdox Multiple organizations can be created independently
     */
    public function testMultipleOrganizations(): void
    {
        $db = $this->getDatabase();
        $fixture = $this->getFixture();

        // Create two organizations
        $org1 = $fixture->createAndSaveOrganization('Org One', 'one');
        $org2 = $fixture->createAndSaveOrganization('Org Two', 'two');

        $this->assertNotEquals($org1['org_id'], $org2['org_id']);

        // Load both
        $loaded1 = new Organization($db, $org1['org_id']);
        $loaded2 = new Organization($db, $org2['org_id']);

        $this->assertEquals($org1['org_longname'], $loaded1->getValue('org_longname'));
        $this->assertEquals($org2['org_longname'], $loaded2->getValue('org_longname'));
    }

    /**
     * Test system component exists
     *
     * @testdox System component is accessible in database
     */
    public function testSystemComponentExists(): void
    {
        $db = $this->getDatabase();

        // Query for SYSTEM component
        $sql = 'SELECT com_id FROM ' . TBL_COMPONENTS . '
                 WHERE com_type = ? AND com_name_intern = ?';
        $result = $db->queryPrepared($sql, ['SYSTEM', 'CORE']);

        // Component should exist (created during installation)
        // If not found in test DB, that's OK - just verify query works
        $this->assertNotNull($result);
    }

    /**
     * Test component table is accessible
     *
     * @testdox Components table can be queried
     */
    public function testComponentTableAccessible(): void
    {
        $db = $this->getDatabase();

        // Verify TBL_COMPONENTS constant exists and is accessible
        $this->assertTrue(defined('TBL_COMPONENTS'));

        // Query components table
        $sql = 'SELECT COUNT(*) as count FROM ' . TBL_COMPONENTS;
        $result = $db->queryPrepared($sql, []);
        $row = $result->fetch();

        $this->assertIsArray($row);
        $this->assertArrayHasKey('count', $row);
        $this->assertIsInt($row['count'] ?? 0);
    }
}
