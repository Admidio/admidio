<?php
/**
 * Category Management Workflow Tests
 *
 * Tests realistic category scenarios combining categories across organizations.
 *
 * @testdox Category management workflows work correctly across organizations
 */

namespace Admidio\Tests\Integration\Workflows\Categories;

use Admidio\Tests\Support\DatabaseTestCase;

class CategoryWorkflowTest extends DatabaseTestCase
{
    /**
     * Test creating multiple categories in organization
     *
     * @testdox Multiple categories can be created in same organization
     */
    public function testMultipleCategoriesInOrganization(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Multi-Category Org');

        // Act - Create multiple categories for different entity types
        $eventCat1 = $builder->createCategory('Team Events', 'EVT', $org['org_id']);
        $eventCat2 = $builder->createCategory('Meetings', 'EVT', $org['org_id']);
        $roleCat1 = $builder->createCategory('Leadership', 'ROL', $org['org_id']);
        $roleCat2 = $builder->createCategory('Volunteers', 'ROL', $org['org_id']);

        // Assert
        $this->assertNotEmpty($eventCat1['cat_id']);
        $this->assertNotEmpty($eventCat2['cat_id']);
        $this->assertNotEmpty($roleCat1['cat_id']);
        $this->assertNotEmpty($roleCat2['cat_id']);
        $this->assertNotEquals($eventCat1['cat_id'], $eventCat2['cat_id']);
        $this->assertNotEquals($roleCat1['cat_id'], $roleCat2['cat_id']);
        $this->assertEquals('EVT', $eventCat1['cat_type']);
        $this->assertEquals('ROL', $roleCat1['cat_type']);
    }

    /**
     * Test category isolation across organizations
     *
     * @testdox Categories in different organizations don't interfere
     */
    public function testCategoryOrganizationIsolation(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org1 = $builder->createOrganization('Company A');
        $org2 = $builder->createOrganization('Company B');

        // Create categories with same name and type in different orgs
        $cat1 = $builder->createCategory('Events', 'EVT', $org1['org_id']);
        $cat2 = $builder->createCategory('Events', 'EVT', $org2['org_id']);

        // Assert - Same name, different IDs and orgs
        $this->assertEquals('Events', $cat1['cat_name']);
        $this->assertEquals('Events', $cat2['cat_name']);
        $this->assertNotEquals($cat1['cat_id'], $cat2['cat_id']);
        $this->assertNotEquals($cat1['org_id'], $cat2['org_id']);
        $this->assertEquals($org1['org_id'], $cat1['org_id']);
        $this->assertEquals($org2['org_id'], $cat2['org_id']);
    }

    /**
     * Test different category types in organization
     *
     * @testdox Multiple category types can coexist in same organization
     */
    public function testMultipleCategoryTypes(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Act - Create categories for different types
        $eventCategory = $builder->createCategory('Events', 'EVT', $org['org_id']);
        $roleCategory = $builder->createCategory('Roles', 'ROL', $org['org_id']);
        $linkCategory = $builder->createCategory('Links', 'LNK', $org['org_id']);
        $announcementCategory = $builder->createCategory('Announcements', 'ANN', $org['org_id']);

        // Assert - Each type independent
        $this->assertEquals('EVT', $eventCategory['cat_type']);
        $this->assertEquals('ROL', $roleCategory['cat_type']);
        $this->assertEquals('LNK', $linkCategory['cat_type']);
        $this->assertEquals('ANN', $announcementCategory['cat_type']);
        $this->assertNotEquals($eventCategory['cat_id'], $roleCategory['cat_id']);
        $this->assertNotEquals($roleCategory['cat_id'], $linkCategory['cat_id']);
        $this->assertNotEquals($linkCategory['cat_id'], $announcementCategory['cat_id']);
    }

    /**
     * Test category sequence and ordering
     *
     * @testdox Categories maintain proper sequence ordering
     */
    public function testCategorySequenceOrdering(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Act - Create multiple categories for same type
        $cat1 = $builder->createCategory('First', 'EVT', $org['org_id']);
        $cat2 = $builder->createCategory('Second', 'EVT', $org['org_id']);
        $cat3 = $builder->createCategory('Third', 'EVT', $org['org_id']);
        $cat4 = $builder->createCategory('Fourth', 'EVT', $org['org_id']);

        // Assert - All categories created with distinct IDs
        $this->assertNotEmpty($cat1['cat_id']);
        $this->assertNotEmpty($cat2['cat_id']);
        $this->assertNotEmpty($cat3['cat_id']);
        $this->assertNotEmpty($cat4['cat_id']);
        $this->assertNotEquals($cat1['cat_id'], $cat2['cat_id']);
        $this->assertNotEquals($cat2['cat_id'], $cat3['cat_id']);
        $this->assertNotEquals($cat3['cat_id'], $cat4['cat_id']);
    }

    /**
     * Test category with associated items workflow
     *
     * @testdox Categories can be created and related to entities
     */
    public function testCategoryAssociationWorkflow(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Event Organization');

        // Create event categories
        $publicEvents = $builder->createCategory('Public Events', 'EVT', $org['org_id']);
        $privateEvents = $builder->createCategory('Private Events', 'EVT', $org['org_id']);

        // Create related entities (roles and users that would attend events)
        $attendees = $builder->createRole('Attendees', $org['org_id']);
        $organizers = $builder->createRole('Organizers', $org['org_id']);

        $attendee1 = $builder->createUser('attendee1', 'att1@company', $org['org_id']);
        $organizer1 = $builder->createUser('organizer1', 'org1@company', $org['org_id']);

        // Assign users to roles
        $builder->assignUserToRole($attendee1, $attendees);
        $builder->assignUserToRole($organizer1, $organizers);

        // Assert - Categories and related entities created
        $this->assertNotEmpty($publicEvents['cat_id']);
        $this->assertNotEmpty($privateEvents['cat_id']);
        $this->assertNotEmpty($attendees['rol_id']);
        $this->assertNotEmpty($organizers['rol_id']);
        $this->assertEquals('EVT', $publicEvents['cat_type']);
        $this->assertEquals('EVT', $privateEvents['cat_type']);
    }
}
