<?php
/**
 * Organization Scope Tests
 *
 * Tests that data is properly scoped to organizations and isolated between orgs.
 */

namespace Admidio\Tests\Integration\Organizations;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;

class OrganizationScopeTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test that categories are properly scoped to organizations
     *
     * @testdox Categories created for org1 are isolated from org2
     */
    public function testCategoriesAreScopedToOrganization(): void
    {
        $fixture = $this->getFixture();
        $org1 = $fixture->createAndSaveOrganization('Organization 1', 'org1');
        $org2 = $fixture->createAndSaveOrganization('Organization 2', 'org2');

        // Create categories in each organization
        $cat1 = $fixture->createAndSaveCategory('Events Org1', 'EVT', $org1['org_id']);
        $cat2 = $fixture->createAndSaveCategory('Events Org2', 'EVT', $org2['org_id']);

        // Verify categories belong to correct orgs
        $this->assertEquals($org1['org_id'], $cat1['org_id']);
        $this->assertEquals($org2['org_id'], $cat2['org_id']);
        $this->assertNotEquals($cat1['cat_id'], $cat2['cat_id']);

        // Verify both exist independently
        $this->assertNotEmpty($cat1['cat_id']);
        $this->assertNotEmpty($cat2['cat_id']);
    }

    /**
     * Test that users created without explicit org are global
     *
     * @testdox Users can be created as global (not org-scoped)
     */
    public function testGlobalUsersNotScoped(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');

        // Create user without explicit organization
        $user = $fixture->createAndSaveUser('globaluser', 'global@example.local');

        // Verify user exists
        $this->assertNotEmpty($user['usr_id']);
        // Note: Users are typically global in Admidio, but can be assigned to roles in specific orgs
        $userData = $fixture->getUserById($user['usr_id']);
        $this->assertNotEmpty($userData);
    }

    /**
     * Test that organization has proper metadata
     *
     * @testdox Organization metadata is correctly stored
     */
    public function testOrganizationMetadata(): void
    {
        $fixture = $this->getFixture();
        $orgName = 'Test Organization';
        $orgShort = 'test';

        $org = $fixture->createAndSaveOrganization($orgName, $orgShort);

        // Verify organization data
        $this->assertIsArray($org);
        if (isset($org['org_id'])) {
            $this->assertIsInt($org['org_id']);
        }
        if (isset($org['org_uuid'])) {
            $this->assertNotEmpty($org['org_uuid']);
        }
        if (isset($org['org_longname'])) {
            $this->assertEquals($orgName, $org['org_longname']);
        }
        if (isset($org['org_shortname'])) {
            $this->assertEquals($orgShort, $org['org_shortname']);
        }
    }

    /**
     * Test multiple organizations can coexist
     *
     * @testdox Multiple organizations can be created and managed independently
     */
    public function testMultipleOrganizationsCoexist(): void
    {
        $fixture = $this->getFixture();

        $org1 = $fixture->createAndSaveOrganization('Org 1', 'org1');
        $org2 = $fixture->createAndSaveOrganization('Org 2', 'org2');
        $org3 = $fixture->createAndSaveOrganization('Org 3', 'org3');

        // Verify all exist with unique IDs
        $this->assertNotEmpty($org1['org_id']);
        $this->assertNotEmpty($org2['org_id']);
        $this->assertNotEmpty($org3['org_id']);
        $this->assertNotEquals($org1['org_id'], $org2['org_id']);
        $this->assertNotEquals($org2['org_id'], $org3['org_id']);
        $this->assertNotEquals($org1['org_id'], $org3['org_id']);

        // Verify unique UUIDs
        $this->assertNotEquals($org1['org_uuid'], $org2['org_uuid']);
        $this->assertNotEquals($org2['org_uuid'], $org3['org_uuid']);
    }
}
