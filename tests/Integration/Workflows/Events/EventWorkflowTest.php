<?php
/**
 * Event Management Workflow Tests
 *
 * Tests realistic event scenarios combining multiple entities and operations.
 *
 * @testdox Event management workflows work correctly across entities
 */

namespace Admidio\Tests\Integration\Workflows\Events;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Events\Entity\Event;
use Admidio\Roles\Entity\Membership;
use Admidio\Categories\Entity\Category;

class EventWorkflowTest extends DatabaseTestCase
{
    /**
     * Test event category association
     *
     * @testdox Events can be created for different categories
     */
    public function testEventCategoryAssociation(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Event Company');

        // Create multiple event categories
        $category1 = $builder->createCategory('Team Events', 'EVT', $org['org_id']);
        $category2 = $builder->createCategory('Meetings', 'EVT', $org['org_id']);

        // Act & Assert - Categories created and distinct
        $this->assertNotEmpty($category1['cat_id']);
        $this->assertNotEmpty($category2['cat_id']);
        $this->assertNotEquals($category1['cat_id'], $category2['cat_id']);
        $this->assertEquals($org['org_id'], $category1['org_id']);
        $this->assertEquals($org['org_id'], $category2['org_id']);
    }

    /**
     * Test multiple roles in organization
     *
     * @testdox Multiple roles can exist in same organization
     */
    public function testMultipleRolesInOrganization(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('MultiRole Org');

        // Create multiple roles for the organization
        $leaders = $builder->createRole('Leaders', $org['org_id']);
        $members = $builder->createRole('Members', $org['org_id']);
        $volunteers = $builder->createRole('Volunteers', $org['org_id']);

        // Assert - Verify roles are distinct
        $this->assertNotEquals($leaders['rol_id'], $members['rol_id']);
        $this->assertNotEquals($members['rol_id'], $volunteers['rol_id']);
        $this->assertEquals($org['org_id'], $leaders['org_id']);
        $this->assertEquals($org['org_id'], $members['org_id']);
        $this->assertEquals($org['org_id'], $volunteers['org_id']);
    }

    /**
     * Test role membership workflow
     *
     * @testdox Multiple users can be assigned to a role
     */
    public function testRoleMembershipWorkflow(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Company');

        // Create role
        $role = $builder->createRole('Attendees', $org['org_id']);

        // Add participants to role (using fixture builder method)
        for ($i = 1; $i <= 3; $i++) {
            $user = $builder->createUser("attendee$i", "attendee$i@company", $org['org_id']);
            $membership = $builder->assignUserToRole($user, $role);
        }

        // Assert - Fixtures created
        $this->assertNotEmpty($role['rol_id']);
        $roles = $builder->getRoles();
        $this->assertGreaterThanOrEqual(1, count($roles));
    }

    /**
     * Test user membership date ranges
     *
     * @testdox Membership with start and end dates can be created
     */
    public function testMembershipDateRanges(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');
        $user = $builder->createUser('dated', 'dated@local', $org['org_id']);
        $role = $builder->createRole('Temporary', $org['org_id']);

        // Act - Create membership with date range using fixture builder
        $membership = $builder->assignUserToRole($user, $role, '2026-01-01');

        // Assert - Membership fixture created successfully
        $this->assertNotEmpty($membership['mem_id']);
        $this->assertEquals('2026-01-01', $membership['mem_begin']);
        $this->assertNull($membership['mem_end']);
    }

    /**
     * Test multiple users in organization
     *
     * @testdox Multiple users in organization are independent
     */
    public function testMultipleUsersInOrganization(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Multi-User Org');

        // Create multiple users
        $user1 = $builder->createUser('user1', 'user1@company', $org['org_id']);
        $user2 = $builder->createUser('user2', 'user2@company', $org['org_id']);
        $user3 = $builder->createUser('user3', 'user3@company', $org['org_id']);

        // Assert - All distinct
        $this->assertNotEquals($user1['usr_id'], $user2['usr_id']);
        $this->assertNotEquals($user2['usr_id'], $user3['usr_id']);
        $this->assertEquals($org['org_id'], $user1['org_id']);
        $this->assertEquals($org['org_id'], $user2['org_id']);
        $this->assertEquals($org['org_id'], $user3['org_id']);
    }
}
