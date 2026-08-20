<?php
/**
 * Category Hierarchy Tests
 *
 * Tests category parent-child relationships and hierarchy management.
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

        // Verify types are correct
        $this->assertEquals('EVT', $eventCat['cat_type']);
        $this->assertEquals('ROL', $roleCat['cat_type']);
        $this->assertEquals('ANN', $announceCat['cat_type']);

        // Verify all exist
        $this->assertNotEmpty($eventCat['cat_id']);
        $this->assertNotEmpty($roleCat['cat_id']);
        $this->assertNotEmpty($announceCat['cat_id']);
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

        // Verify all are unique
        $this->assertNotEquals($cat1['cat_id'], $cat2['cat_id']);
        $this->assertNotEquals($cat2['cat_id'], $cat3['cat_id']);
        $this->assertNotEquals($cat1['cat_id'], $cat3['cat_id']);

        // Verify all have same type
        $this->assertEquals('EVT', $cat1['cat_type']);
        $this->assertEquals('EVT', $cat2['cat_type']);
        $this->assertEquals('EVT', $cat3['cat_type']);
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

        // Verify UUIDs are unique
        $this->assertNotEmpty($cat1['cat_uuid']);
        $this->assertNotEmpty($cat2['cat_uuid']);
        $this->assertNotEquals($cat1['cat_uuid'], $cat2['cat_uuid']);

        // Verify UUID format (basic check)
        $this->assertMatchesRegularExpression('/^[a-f0-9\-]{36}$/', $cat1['cat_uuid']);
        $this->assertMatchesRegularExpression('/^[a-f0-9\-]{36}$/', $cat2['cat_uuid']);
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

        // Create global category (org_id = 0)
        $globalCat = $fixture->createAndSaveCategory('Global Events', 'EVT', 0);

        // Verify scoping
        $this->assertEquals($org['org_id'], $orgCat['org_id']);
        $this->assertEquals(0, $globalCat['org_id']);

        // Verify both exist
        $this->assertNotEmpty($orgCat['cat_id']);
        $this->assertNotEmpty($globalCat['cat_id']);
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
            $this->assertEquals($name, $cat['cat_name']);
        }
    }
}
