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
     * Read the category ids an organization sees, which are its own plus the global ones.
     *
     * @return int[]
     */
    private function visibleCategoryIds(int $orgId, string $type): array
    {
        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type = ? AND (cat_org_id = ? OR cat_org_id IS NULL)';
        $result = $this->getDatabase()->queryPrepared($sql, [$type, $orgId]);

        return array_map('intval', array_column($result->fetchAll(), 'cat_id'));
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

        // same category name in each org
        $catOrg1 = $fixture->createAndSaveCategory('Events', 'EVT', $org1['org_id']);
        $catOrg2 = $fixture->createAndSaveCategory('Events', 'EVT', $org2['org_id']);

        $this->assertNotEquals($catOrg1['cat_id'], $catOrg2['cat_id']);

        // each org's list contains its own category and not the other one's
        $org1Visible = $this->visibleCategoryIds($org1['org_id'], 'EVT');
        $this->assertContains($catOrg1['cat_id'], $org1Visible);
        $this->assertNotContains($catOrg2['cat_id'], $org1Visible);

        $org2Visible = $this->visibleCategoryIds($org2['org_id'], 'EVT');
        $this->assertContains($catOrg2['cat_id'], $org2Visible);
        $this->assertNotContains($catOrg1['cat_id'], $org2Visible);
    }

    /**
     * Test one user can hold memberships in roles of several organizations
     *
     * @testdox Single user can hold roles in multiple organizations
     */
    public function testUsersAreGlobalAcrossOrgs(): void
    {
        $fixture = $this->getFixture();
        $org1 = $fixture->createAndSaveOrganization('Org 1', 'org1');
        $org2 = $fixture->createAndSaveOrganization('Org 2', 'org2');

        // the user record itself is not org-scoped, the membership is
        $user = $fixture->createAndSaveUser('shareduser', 'shared@example.local');

        $role1 = $fixture->createAndSaveRole('Members Org1', $org1['org_id']);
        $role2 = $fixture->createAndSaveRole('Members Org2', $org2['org_id']);

        $mem1 = $fixture->assignUserToRole($user['usr_id'], $role1['rol_id']);
        $mem2 = $fixture->assignUserToRole($user['usr_id'], $role2['rol_id']);

        $this->assertTrue($fixture->membershipExists($mem1['mem_id']));
        $this->assertTrue($fixture->membershipExists($mem2['mem_id']));

        // the very same user record backs both memberships
        $sql = 'SELECT DISTINCT mem_usr_id FROM ' . TBL_MEMBERS . ' WHERE mem_rol_id IN (?, ?)';
        $result = $this->getDatabase()->queryPrepared($sql, [$role1['rol_id'], $role2['rol_id']]);
        $userIds = array_map('intval', array_column($result->fetchAll(), 'mem_usr_id'));

        $this->assertEquals([$user['usr_id']], $userIds);
    }

    /**
     * Test global categories are visible to all orgs
     *
     * @testdox Global categories (cat_org_id IS NULL) are visible from every org
     */
    public function testGlobalCategoriesAreShared(): void
    {
        $fixture = $this->getFixture();
        $org1 = $fixture->createAndSaveOrganization('Org 1', 'org1');
        $org2 = $fixture->createAndSaveOrganization('Org 2', 'org2');

        // a category created without an org id is global
        $globalCat = $fixture->createAndSaveCategory('Global Events', 'EVT', 0);
        $orgCat1 = $fixture->createAndSaveCategory('Org1 Events', 'EVT', $org1['org_id']);

        // the global one really has no org in the database
        $stored = $fixture->getCategoryById($globalCat['cat_id']);
        $this->assertNull($stored['cat_org_id']);

        // and it shows up for both organizations, while the org-scoped one does not
        $this->assertContains($globalCat['cat_id'], $this->visibleCategoryIds($org1['org_id'], 'EVT'));
        $this->assertContains($globalCat['cat_id'], $this->visibleCategoryIds($org2['org_id'], 'EVT'));
        $this->assertNotContains($orgCat1['cat_id'], $this->visibleCategoryIds($org2['org_id'], 'EVT'));
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

        $cat1 = $fixture->createAndSaveCategory('Org1 Cat', 'EVT', $org1['org_id']);
        $cat2 = $fixture->createAndSaveCategory('Org2 Cat', 'EVT', $org2['org_id']);

        // a strictly org-scoped query returns exactly the org's own category
        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_org_id = ? AND cat_type = ?';

        $result = $this->getDatabase()->queryPrepared($sql, [$org1['org_id'], 'EVT']);
        $org1Ids = array_map('intval', array_column($result->fetchAll(), 'cat_id'));
        $this->assertEquals([$cat1['cat_id']], $org1Ids);

        $result = $this->getDatabase()->queryPrepared($sql, [$org2['org_id'], 'EVT']);
        $org2Ids = array_map('intval', array_column($result->fetchAll(), 'cat_id'));
        $this->assertEquals([$cat2['cat_id']], $org2Ids);
    }

    /**
     * Test multiple organizations can operate independently
     *
     * @testdox Multiple orgs with same structure don't interfere
     */
    public function testMultipleOrgIndependence(): void
    {
        $fixture = $this->getFixture();

        $orgIds = [];
        $catIds = [];

        // three organizations with an identically named category each
        for ($i = 1; $i <= 3; $i++) {
            $org = $fixture->createAndSaveOrganization("Company $i", "company$i");
            $cat = $fixture->createAndSaveCategory('Events', 'EVT', $org['org_id']);

            $orgIds[] = $org['org_id'];
            $catIds[$org['org_id']] = $cat['cat_id'];
        }

        $this->assertCount(3, array_unique($orgIds));
        $this->assertCount(3, array_unique($catIds));

        // despite the shared name, each org resolves to its own category
        foreach ($catIds as $orgId => $catId) {
            $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . '
                     WHERE cat_org_id = ? AND cat_type = ? AND cat_name = ?';
            $result = $this->getDatabase()->queryPrepared($sql, [$orgId, 'EVT', 'Events']);
            $found = array_map('intval', array_column($result->fetchAll(), 'cat_id'));

            $this->assertEquals([$catId], $found);
        }
    }
}
