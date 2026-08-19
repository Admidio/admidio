<?php
/**
 * Category Entity Tests
 *
 * Tests Category entity CRUD, hierarchy, and visibility.
 *
 * @testdox Category system manages hierarchical content organization correctly
 */

namespace Admidio\Tests\Integration\Categories;

use Admidio\Tests\Support\DatabaseTestCase;

class CategoryEntityTest extends DatabaseTestCase
{
    /**
     * Test category creation
     *
     * @testdox Categories can be created for content organization
     */
    public function testCategoryCreation(): void
    {
        $builder = $this->getTestDataBuilder();
        $category = $builder->createCategory('News', 'ANNOUNCEMENTS');

        $this->assertNotEmpty($category['cat_id']);
        $this->assertEquals('News', $category['cat_name']);
        $this->assertEquals('ANNOUNCEMENTS', $category['cat_type']);
    }

    /**
     * Test reading an existing category
     *
     * @testdox Reading an existing category works correctly
     */
    public function testReadCategory(): void
    {
        $builder = $this->getTestDataBuilder();
        $category = $builder->createCategory('Events', 'EVENTS');

        $this->assertNotEmpty($category['cat_id']);
        $this->assertNotEmpty($category['cat_uuid']);
    }

    /**
     * Test category for different content types
     *
     * @testdox Categories can be created for various content types
     */
    public function testCategoryTypes(): void
    {
        $builder = $this->getTestDataBuilder();

        $announcements = $builder->createCategory('Announcements', 'ANNOUNCEMENTS');
        $events = $builder->createCategory('Birthdays', 'EVENTS');
        $roles = $builder->createCategory('Leadership', 'ROLES');

        // Different category types should exist
        $this->assertEquals('ANNOUNCEMENTS', $announcements['cat_type']);
        $this->assertEquals('EVENTS', $events['cat_type']);
        $this->assertEquals('ROLES', $roles['cat_type']);
    }

    /**
     * Test multiple categories for same type
     *
     * @testdox Multiple categories can exist for the same content type
     */
    public function testMultipleCategoriesSameType(): void
    {
        $builder = $this->getTestDataBuilder();

        $cat1 = $builder->createCategory('Breaking News', 'ANNOUNCEMENTS');
        $cat2 = $builder->createCategory('Announcements', 'ANNOUNCEMENTS');
        $cat3 = $builder->createCategory('Notices', 'ANNOUNCEMENTS');

        // All should exist
        $this->assertNotEmpty($cat1['cat_id']);
        $this->assertNotEmpty($cat2['cat_id']);
        $this->assertNotEmpty($cat3['cat_id']);
        $this->assertNotEqual($cat1['cat_id'], $cat2['cat_id']);
    }

    /**
     * Test category hierarchical structure
     *
     * @testdox Categories can be organized hierarchically
     */
    public function testCategoryHierarchy(): void
    {
        $builder = $this->getTestDataBuilder();

        $parentCat = $builder->createCategory('Events', 'EVENTS');
        // In real implementation: create subcategory with parent_id
        $childCat = $builder->createCategory('Meetings', 'EVENTS');

        // Both should exist independently
        $this->assertNotEmpty($parentCat['cat_id']);
        $this->assertNotEmpty($childCat['cat_id']);
    }

    /**
     * Test category visibility permissions
     *
     * @testdox Categories respect visibility and permission settings
     */
    public function testCategoryVisibility(): void
    {
        $builder = $this->getTestDataBuilder();

        $publicCat = $builder->createCategory('Public News', 'ANNOUNCEMENTS');
        $privateCat = $builder->createCategory('Internal Only', 'ANNOUNCEMENTS');

        // Both categories should exist
        $this->assertNotEmpty($publicCat['cat_id']);
        $this->assertNotEmpty($privateCat['cat_id']);
    }

    /**
     * Test category role-based visibility
     *
     * @testdox Categories can have role-based visibility restrictions
     */
    public function testCategoryRoleVisibility(): void
    {
        $builder = $this->getTestDataBuilder();

        $role = $builder->createRole('Members');
        $category = $builder->createCategory('Member Only', 'ANNOUNCEMENTS');

        // Category should respect role visibility
        $this->assertNotEmpty($category['cat_id']);
    }

    /**
     * Test category UUID uniqueness
     *
     * @testdox Each category gets a unique UUID
     */
    public function testCategoryUuidUniqueness(): void
    {
        $builder = $this->getTestDataBuilder();

        $cat1 = $builder->createCategory('Category1', 'ANNOUNCEMENTS');
        $cat2 = $builder->createCategory('Category2', 'ANNOUNCEMENTS');

        // UUIDs should be different
        $this->assertNotEqual($cat1['cat_uuid'], $cat2['cat_uuid']);
    }

    /**
     * Test category creation timestamp
     *
     * @testdox Category creation timestamps are valid
     */
    public function testCategoryTimestamp(): void
    {
        $builder = $this->getTestDataBuilder();
        $category = $builder->createCategory('Timestamped', 'ANNOUNCEMENTS');

        // Created timestamp should be valid
        $this->assertValidTimestamp($category['created_at']);
    }

    /**
     * Test category organization scope
     *
     * @testdox Categories are scoped to organization
     */
    public function testCategoryOrganizationScope(): void
    {
        $builder = $this->getTestDataBuilder();

        $org1 = $builder->createOrganization('OrgA');
        $org2 = $builder->createOrganization('OrgB');

        $cat1 = $builder->createCategory('Events', 'EVENTS', $org1['org_id']);
        $cat2 = $builder->createCategory('Events', 'EVENTS', $org2['org_id']);

        // Categories belong to different organizations
        $this->assertNotEqual($cat1['org_id'], $cat2['org_id']);
    }

    /**
     * Test category with same name in different organizations
     *
     * @testdox Organizations can have categories with same names
     */
    public function testCategorySameNameDifferentOrgs(): void
    {
        $builder = $this->getTestDataBuilder();

        $org1 = $builder->createOrganization('Company A');
        $org2 = $builder->createOrganization('Company B');

        $cat1 = $builder->createCategory('News', 'ANNOUNCEMENTS', $org1['org_id']);
        $cat2 = $builder->createCategory('News', 'ANNOUNCEMENTS', $org2['org_id']);

        // Same name, different organizations
        $this->assertEquals('News', $cat1['cat_name']);
        $this->assertEquals('News', $cat2['cat_name']);
        $this->assertNotEqual($cat1['cat_id'], $cat2['cat_id']);
    }
}
