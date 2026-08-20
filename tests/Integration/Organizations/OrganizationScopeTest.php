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

        // the stored owner has to be the org the category was created for
        $this->assertEquals($org1['org_id'], (int) $fixture->getCategoryById($cat1['cat_id'])['cat_org_id']);
        $this->assertEquals($org2['org_id'], (int) $fixture->getCategoryById($cat2['cat_id'])['cat_org_id']);

        // querying org1 must not return org2's category
        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_org_id = ? AND cat_type = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$org1['org_id'], 'EVT']);
        $org1CatIds = array_map('intval', array_column($result->fetchAll(), 'cat_id'));

        $this->assertContains($cat1['cat_id'], $org1CatIds);
        $this->assertNotContains($cat2['cat_id'], $org1CatIds);
    }

    /**
     * Test that the user record itself carries no organization
     *
     * @testdox User records are not scoped to an organization
     */
    public function testGlobalUsersNotScoped(): void
    {
        $fixture = $this->getFixture();
        $fixture->createAndSaveOrganization('Test Org');

        $user = $fixture->createAndSaveUser('globaluser', 'global@example.local');

        // users belong to an organization through their role memberships, so adm_users
        // itself has no org column at all
        $columns = array_keys($fixture->getUserById($user['usr_id']));
        $orgColumns = array_filter($columns, static fn($column) => str_contains($column, 'org'));

        $this->assertSame([], array_values($orgColumns));
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
        $orgShort = 'metadata';

        $org = $fixture->createAndSaveOrganization($orgName, $orgShort);

        // read the row back instead of trusting the values the fixture echoed
        $stored = $fixture->getOrganizationById($org['org_id']);
        $this->assertNotEmpty($stored);

        $this->assertEquals($orgName, $stored['org_longname']);
        $this->assertEquals($orgShort, $stored['org_shortname']);
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',
            $stored['org_uuid']
        );
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

        $ids = [$org1['org_id'], $org2['org_id'], $org3['org_id']];
        $this->assertCount(3, array_unique($ids));

        // all three are readable back with their own short name
        $this->assertEquals('org1', $fixture->getOrganizationById($org1['org_id'])['org_shortname']);
        $this->assertEquals('org2', $fixture->getOrganizationById($org2['org_id'])['org_shortname']);
        $this->assertEquals('org3', $fixture->getOrganizationById($org3['org_id'])['org_shortname']);

        $uuids = [
            $fixture->getOrganizationById($org1['org_id'])['org_uuid'],
            $fixture->getOrganizationById($org2['org_id'])['org_uuid'],
            $fixture->getOrganizationById($org3['org_id'])['org_uuid'],
        ];
        $this->assertCount(3, array_unique($uuids));
    }
}
