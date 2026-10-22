<?php
/**
 * Category Tests
 *
 * Tests category creation, typing and organization scoping.
 * Admidio categories are a flat, ordered list per type, they have no parent-child hierarchy.
 */

namespace Admidio\Tests\Integration\Categories;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;

class CategoryHierarchyTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test creating categories of different types
     *
     * @testdox Categories can be created for different entity types
     */
    public function testCategoryTypes(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');

        // Create categories of different types
        $eventCat = $fixture->createAndSaveCategory('Events', 'EVT', $org['org_id']);
        $roleCat = $fixture->createAndSaveCategory('Roles', 'ROL', $org['org_id']);
        $announceCat = $fixture->createAndSaveCategory('Announcements', 'ANN', $org['org_id']);

        // the type has to be the stored one, not the one the fixture echoed back
        $this->assertEquals('EVT', $fixture->getCategoryById($eventCat['cat_id'])['cat_type']);
        $this->assertEquals('ROL', $fixture->getCategoryById($roleCat['cat_id'])['cat_type']);
        $this->assertEquals('ANN', $fixture->getCategoryById($announceCat['cat_id'])['cat_type']);
    }

    /**
     * Test multiple categories of same type
     *
     * @testdox Multiple categories of same type can coexist
     */
    public function testMultipleCategoriesSameType(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');

        $cat1 = $fixture->createAndSaveCategory('Events 1', 'EVT', $org['org_id']);
        $cat2 = $fixture->createAndSaveCategory('Events 2', 'EVT', $org['org_id']);
        $cat3 = $fixture->createAndSaveCategory('Events 3', 'EVT', $org['org_id']);

        $ids = [$cat1['cat_id'], $cat2['cat_id'], $cat3['cat_id']];
        $this->assertCount(3, array_unique($ids));

        // all three are stored, of the same type, under the same organization
        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_org_id = ? AND cat_type = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$org['org_id'], 'EVT']);
        $stored = array_map('intval', array_column($result->fetchAll(), 'cat_id'));

        sort($ids);
        sort($stored);
        $this->assertEquals($ids, $stored);
    }

    /**
     * Test categories have unique UUIDs
     *
     * @testdox Each category has unique UUID
     */
    public function testCategoryUUIDs(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');

        $cat1 = $fixture->createAndSaveCategory('Cat1', 'EVT', $org['org_id']);
        $cat2 = $fixture->createAndSaveCategory('Cat2', 'EVT', $org['org_id']);

        $uuid1 = $fixture->getCategoryById($cat1['cat_id'])['cat_uuid'];
        $uuid2 = $fixture->getCategoryById($cat2['cat_id'])['cat_uuid'];

        // Verify UUIDs are unique
        $this->assertNotEquals($uuid1, $uuid2);

        // Verify UUID format
        $uuidPattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';
        $this->assertMatchesRegularExpression($uuidPattern, $uuid1);
        $this->assertMatchesRegularExpression($uuidPattern, $uuid2);
    }

    /**
     * Test global vs organization-scoped categories
     *
     * @testdox Categories can be created as global or org-scoped
     */
    public function testGlobalVsOrgScopedCategories(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');

        // Create org-scoped category
        $orgCat = $fixture->createAndSaveCategory('Org Events', 'EVT', $org['org_id']);

        // Create global category (no organization)
        $globalCat = $fixture->createAndSaveCategory('Global Events', 'EVT', 0);

        // the scope has to be what the database stores
        $this->assertEquals(
            $org['org_id'],
            (int) $fixture->getCategoryById($orgCat['cat_id'])['cat_org_id']
        );
        $this->assertNull($fixture->getCategoryById($globalCat['cat_id'])['cat_org_id']);
    }

    /**
     * Test category names are stored correctly
     *
     * @testdox Category names are preserved as entered
     */
    public function testCategoryNamesPreserved(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Test Org');

        $names = [
            'Simple Events',
            'Training (2025)',
            'Sports / Recreation',
        ];

        foreach ($names as $name) {
            $cat = $fixture->createAndSaveCategory($name, 'EVT', $org['org_id']);

            // the name has to survive the round trip through the database unchanged
            $this->assertEquals($name, $fixture->getCategoryById($cat['cat_id'])['cat_name']);
        }
    }
}
