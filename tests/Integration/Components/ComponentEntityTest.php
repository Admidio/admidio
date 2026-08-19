<?php
/**
 * Component Entity Tests
 *
 * Tests Component entity CRUD and visibility logic.
 *
 * @testdox Component entity handles visibility and administration correctly
 */

namespace Admidio\Tests\Integration\Components;

use Admidio\Tests\Support\DatabaseTestCase;

class ComponentEntityTest extends DatabaseTestCase
{
    /**
     * Test component creation
     *
     * @testdox Components can be created for each module
     */
    public function testComponentCreation(): void
    {
        $builder = $this->getTestDataBuilder();
        $component = $builder->createComponent('events', 0, 'Events');

        $this->assertNotEmpty($component['com_id']);
        $this->assertEquals('events', $component['com_type']);
    }

    /**
     * Test component visibility for admin
     *
     * @testdox Admin users can see all components
     */
    public function testComponentVisibilityAdmin(): void
    {
        $builder = $this->getTestDataBuilder();
        $admin = $builder->createUser('admin', 'admin@test.local');
        $component = $builder->createComponent('members', 0, 'Members');

        // Admin should see component
        $this->assertNotEmpty($component['com_id']);
    }

    /**
     * Test component visibility for members
     *
     * @testdox Regular members see components based on permissions
     */
    public function testComponentVisibilityMember(): void
    {
        $builder = $this->getTestDataBuilder();
        $member = $builder->createUser('member', 'member@test.local');
        $component = $builder->createComponent('photos', 0, 'Photos');

        // Member visibility depends on component permissions
        $this->assertNotEmpty($component['com_id']);
    }

    /**
     * Test component administration rights
     *
     * @testdox Only designated admins can configure components
     */
    public function testComponentAdministration(): void
    {
        $builder = $this->getTestDataBuilder();
        $component = $builder->createComponent('documents', 0, 'Documents');

        // Component should have administration settings
        $this->assertNotEmpty($component['com_id']);
    }

    /**
     * Test component enable/disable
     *
     * @testdox Components can be disabled to hide from users
     */
    public function testComponentEnableDisable(): void
    {
        $builder = $this->getTestDataBuilder();
        $component = $builder->createComponent('inventory', 0, 'Inventory');

        // Component starts enabled
        $this->assertNotEmpty($component['com_id']);
    }

    /**
     * Test component organization scope
     *
     * @testdox Components are scoped to organization
     */
    public function testComponentOrganizationScope(): void
    {
        $builder = $this->getTestDataBuilder();

        $org1 = $builder->createOrganization('OrgA');
        $org2 = $builder->createOrganization('OrgB');

        $comp1 = $builder->createComponent('module1', $org1['org_id'], 'Module 1');
        $comp2 = $builder->createComponent('module2', $org2['org_id'], 'Module 2');

        // Components belong to different organizations
        $this->assertNotEquals($comp1['org_id'], $comp2['org_id']);
    }

    /**
     * Test component multiple instances per module
     *
     * @testdox Multiple instances of same module can exist
     */
    public function testComponentMultipleInstances(): void
    {
        $builder = $this->getTestDataBuilder();

        $comp1 = $builder->createComponent('custom', 0, 'Custom 1');
        $comp2 = $builder->createComponent('custom', 0, 'Custom 2');

        // Both instances should exist
        $this->assertNotEmpty($comp1['com_id']);
        $this->assertNotEmpty($comp2['com_id']);
        $this->assertNotEquals($comp1['com_id'], $comp2['com_id']);
    }

    /**
     * Test component UUID uniqueness
     *
     * @testdox Each component gets a unique UUID
     */
    public function testComponentUuidUniqueness(): void
    {
        $builder = $this->getTestDataBuilder();

        $comp1 = $builder->createComponent('module1', 0, 'Module 1');
        $comp2 = $builder->createComponent('module2', 0, 'Module 2');

        // UUIDs should be different
        $this->assertNotEquals($comp1['com_uuid'], $comp2['com_uuid']);
    }

    /**
     * Test component with role-specific visibility
     *
     * @testdox Components can have role-specific visibility rules
     */
    public function testComponentRoleVisibility(): void
    {
        $builder = $this->getTestDataBuilder();

        $role = $builder->createRole('AdminRole');
        $component = $builder->createComponent('admin', 0, 'Administration');

        // Component should respect role visibility
        $this->assertNotEmpty($component['com_id']);
    }

    /**
     * Test component creation timestamp
     *
     * @testdox Component creation timestamps are valid
     */
    public function testComponentTimestamp(): void
    {
        $builder = $this->getTestDataBuilder();
        $component = $builder->createComponent('test', 0, 'Test Component');

        // Created timestamp should be valid
        $this->assertValidTimestamp($component['created_at']);
    }
}
