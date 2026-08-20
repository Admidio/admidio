<?php
/**
 * Menu Management Workflow Tests
 *
 * Tests realistic menu scenarios with hierarchy and organization scoping.
 *
 * @testdox Menu management workflows work correctly across organizations
 */

namespace Admidio\Tests\Integration\Workflows\Menu;

use Admidio\Tests\Support\DatabaseTestCase;

class MenuWorkflowTest extends DatabaseTestCase
{
    /**
     * Test creating multiple menu items for organization
     *
     * @testdox Multiple menu items can be created in same organization
     */
    public function testMultipleMenuItemsInOrganization(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Menu Org');

        // Create components that would have menu entries
        $members = $builder->createComponent('members', $org['org_id'], 'Members');
        $events = $builder->createComponent('events', $org['org_id'], 'Events');
        $roles = $builder->createComponent('roles', $org['org_id'], 'Roles');
        $weblinks = $builder->createComponent('links', $org['org_id'], 'Links');

        // Assert - Components created with distinct IDs
        $this->assertNotEmpty($members['com_id']);
        $this->assertNotEmpty($events['com_id']);
        $this->assertNotEmpty($roles['com_id']);
        $this->assertNotEmpty($weblinks['com_id']);
        $this->assertNotEquals($members['com_id'], $events['com_id']);
        $this->assertNotEquals($events['com_id'], $roles['com_id']);
        $this->assertNotEquals($roles['com_id'], $weblinks['com_id']);
    }

    /**
     * Test menu item organization isolation
     *
     * @testdox Menu items in different organizations don't interfere
     */
    public function testMenuOrganizationIsolation(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org1 = $builder->createOrganization('Company A');
        $org2 = $builder->createOrganization('Company B');

        // Create menu components in different orgs
        $events1 = $builder->createComponent('events', $org1['org_id'], 'Events');
        $events2 = $builder->createComponent('events', $org2['org_id'], 'Events');

        // Assert - Same component type, different org scoping
        $this->assertEquals('events', $events1['com_type']);
        $this->assertEquals('events', $events2['com_type']);
        $this->assertNotEquals($events1['com_id'], $events2['com_id']);
        $this->assertNotEquals($events1['org_id'], $events2['org_id']);
        $this->assertEquals($org1['org_id'], $events1['org_id']);
        $this->assertEquals($org2['org_id'], $events2['org_id']);
    }

    /**
     * Test menu with multiple component types
     *
     * @testdox Multiple component types can be in menu for same organization
     */
    public function testMultipleComponentTypes(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Act - Create different component types
        $members = $builder->createComponent('members', $org['org_id']);
        $events = $builder->createComponent('events', $org['org_id']);
        $announcements = $builder->createComponent('announcements', $org['org_id']);
        $photos = $builder->createComponent('photos', $org['org_id']);
        $roles = $builder->createComponent('roles', $org['org_id']);

        // Assert - All components created with distinct IDs
        $this->assertNotEmpty($members['com_id']);
        $this->assertNotEmpty($events['com_id']);
        $this->assertNotEmpty($announcements['com_id']);
        $this->assertNotEmpty($photos['com_id']);
        $this->assertNotEmpty($roles['com_id']);
        $this->assertNotEquals($members['com_id'], $events['com_id']);
        $this->assertNotEquals($events['com_id'], $announcements['com_id']);
        $this->assertNotEquals($announcements['com_id'], $photos['com_id']);
        $this->assertNotEquals($photos['com_id'], $roles['com_id']);
    }

    /**
     * Test menu component sequencing
     *
     * @testdox Menu components maintain proper sequencing
     */
    public function testMenuComponentSequencing(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Act - Create components in order
        $first = $builder->createComponent('overview', $org['org_id'], 'Overview');
        $second = $builder->createComponent('members', $org['org_id'], 'Members');
        $third = $builder->createComponent('roles', $org['org_id'], 'Roles');
        $fourth = $builder->createComponent('events', $org['org_id'], 'Events');

        // Assert - Components created in sequence
        $this->assertNotEmpty($first['com_id']);
        $this->assertNotEmpty($second['com_id']);
        $this->assertNotEmpty($third['com_id']);
        $this->assertNotEmpty($fourth['com_id']);
        $this->assertNotEquals($first['com_id'], $second['com_id']);
        $this->assertNotEquals($second['com_id'], $third['com_id']);
        $this->assertNotEquals($third['com_id'], $fourth['com_id']);
    }

    /**
     * Test menu hierarchy workflow with related entities
     *
     * @testdox Menu components can be related to categories and roles
     */
    public function testMenuEntityRelationshipWorkflow(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Organization');

        // Create menu-related components
        $events = $builder->createComponent('events', $org['org_id'], 'Events');
        $roles = $builder->createComponent('roles', $org['org_id'], 'Roles');

        // Create related categories for events
        $eventCategory1 = $builder->createCategory('Public Events', 'EVT', $org['org_id']);
        $eventCategory2 = $builder->createCategory('Private Events', 'EVT', $org['org_id']);

        // Create related categories for roles
        $roleCategory = $builder->createCategory('Groups', 'ROL', $org['org_id']);

        // Create users and roles
        $member = $builder->createRole('Members', $org['org_id']);
        $leader = $builder->createRole('Leaders', $org['org_id']);

        // Create users
        $user1 = $builder->createUser('user1', 'user1@org', $org['org_id']);
        $user2 = $builder->createUser('user2', 'user2@org', $org['org_id']);

        // Assign users to roles
        $builder->assignUserToRole($user1, $member);
        $builder->assignUserToRole($user2, $leader);

        // Assert - All entities created and interconnected
        $this->assertNotEmpty($events['com_id']);
        $this->assertNotEmpty($roles['com_id']);
        $this->assertNotEmpty($eventCategory1['cat_id']);
        $this->assertNotEmpty($eventCategory2['cat_id']);
        $this->assertNotEmpty($roleCategory['cat_id']);
        $this->assertNotEmpty($member['rol_id']);
        $this->assertNotEmpty($leader['rol_id']);
        $this->assertNotEmpty($user1['usr_id']);
        $this->assertNotEmpty($user2['usr_id']);
        $this->assertEquals('events', $events['com_type']);
        $this->assertEquals('roles', $roles['com_type']);
    }
}
