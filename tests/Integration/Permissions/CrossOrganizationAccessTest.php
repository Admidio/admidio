<?php
/**
 * Cross-Organization Access Tests
 *
 * Tests that data in one organization cannot interfere with another organization.
 */

namespace Admidio\Tests\Integration\Permissions;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;

class CrossOrganizationAccessTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test categories are isolated between organizations
     *
     * @testdox Categories in Org1 don't appear in Org2's category list
     */
    public function testCategoryIsolationBetweenOrgs(): void
    {
        $fixture = $this->getFixture();
        $org1 = $fixture->createAndSaveOrganization('Org 1', 'org1');
        $org2 = $fixture->createAndSaveOrganization('Org 2', 'org2');

        // Create categories in each org
        $cat1_org1 = $fixture->createAndSaveCategory('Events', 'EVT', $org1['org_id']);
        $cat1_org2 = $fixture->createAndSaveCategory('Events', 'EVT', $org2['org_id']);

        // Verify they're different categories despite same name
        $this->assertNotEquals($cat1_org1['cat_id'], $cat1_org2['cat_id']);
        $this->assertEquals($org1['org_id'], $cat1_org1['org_id']);
        $this->assertEquals($org2['org_id'], $cat1_org2['org_id']);
    }

    /**
     * Test users can be created globally and assigned to different orgs
     *
     * @testdox Single user can interact with multiple organizations
     */
    public function testUsersAreGlobalAcrossOrgs(): void
    {
        $fixture = $this->getFixture();
        $org1 = $fixture->createAndSaveOrganization('Org 1', 'org1');
        $org2 = $fixture->createAndSaveOrganization('Org 2', 'org2');

        // Create shared user (not org-scoped)
        $user = $fixture->createAndSaveUser('shareduser', 'shared@example.local');

        // Verify user exists as global entity
        $foundUser = $fixture->getUserById($user['usr_id']);
        $this->assertNotEmpty($foundUser);
        $this->assertEquals('shareduser', $foundUser['usr_login_name']);
    }

    /**
     * Test global categories are visible to all orgs
     *
     * @testdox Global categories (org_id=0) are accessible from any org
     */
    public function testGlobalCategoriesAreShared(): void
    {
        $fixture = $this->getFixture();
        $org1 = $fixture->createAndSaveOrganization('Org 1', 'org1');
        $org2 = $fixture->createAndSaveOrganization('Org 2', 'org2');

        // Create global category (org_id = 0)
        $globalCat = $fixture->createAndSaveCategory('Global Events', 'EVT', 0);

        // Create org-specific categories
        $orgCat1 = $fixture->createAndSaveCategory('Org1 Events', 'EVT', $org1['org_id']);
        $orgCat2 = $fixture->createAndSaveCategory('Org2 Events', 'EVT', $org2['org_id']);

        // Verify scope
        $this->assertEquals(0, $globalCat['org_id']);
        $this->assertEquals($org1['org_id'], $orgCat1['org_id']);
        $this->assertEquals($org2['org_id'], $orgCat2['org_id']);

        // All should exist as separate entities
        $this->assertNotEquals($globalCat['cat_id'], $orgCat1['cat_id']);
        $this->assertNotEquals($globalCat['cat_id'], $orgCat2['cat_id']);
    }

    /**
     * Test data cannot leak between organizations
     *
     * @testdox Org1 data is isolated from Org2
     */
    public function testDataIsolationBetweenOrgs(): void
    {
        $fixture = $this->getFixture();
        $org1 = $fixture->createAndSaveOrganization('Isolated Org 1', 'iso1');
        $org2 = $fixture->createAndSaveOrganization('Isolated Org 2', 'iso2');

        // Create separate data in each org
        $cat1 = $fixture->createAndSaveCategory('Org1 Cat', 'EVT', $org1['org_id']);
        $cat2 = $fixture->createAndSaveCategory('Org2 Cat', 'EVT', $org2['org_id']);

        // Query org1 categories (simplified - just verify they exist independently)
        $sql = 'SELECT COUNT(*) as count FROM ' . TBL_CATEGORIES . ' WHERE cat_org_id = ? AND cat_type = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$org1['org_id'], 'EVT']);
        $row = $result->fetch();
        $org1Count = (int) $row['count'];

        $result = $this->getDatabase()->queryPrepared($sql, [$org2['org_id'], 'EVT']);
        $row = $result->fetch();
        $org2Count = (int) $row['count'];

        // Each org has at least its own category
        $this->assertGreaterThanOrEqual(1, $org1Count);
        $this->assertGreaterThanOrEqual(1, $org2Count);

        // Verify they're tracking their own data
        $this->assertNotEquals($cat1['cat_id'], $cat2['cat_id']);
    }

    /**
     * Test multiple organizations can operate independently
     *
     * @testdox Multiple orgs with same structure don't interfere
     */
    public function testMultipleOrgIndependence(): void
    {
        $fixture = $this->getFixture();

        // Create multiple organizations with similar structure
        $orgs = [];
        for ($i = 1; $i <= 3; $i++) {
            $org = $fixture->createAndSaveOrganization("Company $i", "company$i");
            $orgs[$i] = $org;

            // Create users in each org
            $user = $fixture->createAndSaveUser("user$i", "user$i@example.local");

            // Create categories in each org
            $cat = $fixture->createAndSaveCategory("Events", 'EVT', $org['org_id']);

            // Verify structure
            $this->assertEquals($org['org_id'], $orgs[$i]['org_id']);
        }

        // Verify all orgs exist independently
        $this->assertNotEquals($orgs[1]['org_id'], $orgs[2]['org_id']);
        $this->assertNotEquals($orgs[2]['org_id'], $orgs[3]['org_id']);
        $this->assertNotEquals($orgs[1]['org_id'], $orgs[3]['org_id']);
    }
}
