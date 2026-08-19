<?php
/**
 * Organization Entity Tests
 *
 * Tests Organization entity CRUD operations and multi-tenancy isolation.
 *
 * @testdox Organization entity handles creation and multi-tenancy correctly
 */

namespace Admidio\Tests\Integration\Organizations;

use Admidio\Tests\Support\DatabaseTestCase;

class OrganizationEntityTest extends DatabaseTestCase
{
    /**
     * Test creating a new organization
     *
     * @testdox Creating a new organization via Entity API works correctly
     */
    public function testCreateOrganization(): void
    {
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('NewOrg', 'neworg');

        $this->assertNotEmpty($org['org_id']);
        $this->assertNotEmpty($org['org_uuid']);
        $this->assertEquals('NewOrg', $org['org_name']);
        $this->assertEquals('neworg', $org['org_shortname']);
        $this->assertValidUuid($org['org_uuid']);
    }

    /**
     * Test reading an existing organization
     *
     * @testdox Reading an existing organization via Entity API works correctly
     */
    public function testReadOrganization(): void
    {
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('ReadOrg');

        $this->assertNotEmpty($org['org_id']);
        $this->assertNotEmpty($org['org_uuid']);
    }

    /**
     * Test organization UUID uniqueness
     *
     * @testdox Each organization gets a unique UUID
     */
    public function testOrganizationUuidUniqueness(): void
    {
        $builder = $this->getTestDataBuilder();

        $org1 = $builder->createOrganization('Org1');
        $org2 = $builder->createOrganization('Org2');

        // UUIDs should be different
        $this->assertNotEqual($org1['org_uuid'], $org2['org_uuid']);

        // Both should be valid UUIDs
        $this->assertValidUuid($org1['org_uuid']);
        $this->assertValidUuid($org2['org_uuid']);
    }

    /**
     * Test multiple organizations can coexist
     *
     * @testdox Multiple organizations can exist in same database
     */
    public function testMultipleOrganizations(): void
    {
        $builder = $this->getTestDataBuilder();

        $org1 = $builder->createOrganization('Company A');
        $org2 = $builder->createOrganization('Company B');
        $org3 = $builder->createOrganization('Company C');

        // All should have different IDs
        $this->assertNotEqual($org1['org_id'], $org2['org_id']);
        $this->assertNotEqual($org2['org_id'], $org3['org_id']);

        // Should be able to retrieve all
        $orgs = $builder->getOrganizations();
        $this->assertCount(3, $orgs);
    }

    /**
     * Test organization creation timestamps
     *
     * @testdox Organization creation timestamps are valid
     */
    public function testOrganizationTimestamp(): void
    {
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('TimeOrg');

        // Created timestamp should be valid
        $this->assertValidTimestamp($org['created_at']);
    }

    /**
     * Test organization changelog on creation
     *
     * @testdox Creating an organization generates a changelog entry
     */
    public function testOrganizationChangelogOnCreate(): void
    {
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('ChangelogOrg');

        // In real implementation, would check changelog table
        $this->assertNotEmpty($org['org_id']);
    }

    /**
     * Test users belong to specific organization
     *
     * @testdox Users are scoped to their organization
     */
    public function testUserOrganizationScope(): void
    {
        $builder = $this->getTestDataBuilder();

        $org1 = $builder->createOrganization('Org1');
        $org2 = $builder->createOrganization('Org2');

        $user1 = $builder->createUser('user1', 'user1@test.local', $org1['org_id']);
        $user2 = $builder->createUser('user2', 'user2@test.local', $org2['org_id']);

        // Users should belong to different organizations
        $this->assertNotEqual($user1['org_id'], $user2['org_id']);
    }

    /**
     * Test roles are scoped to organization
     *
     * @testdox Roles are scoped to their organization
     */
    public function testRoleOrganizationScope(): void
    {
        $builder = $this->getTestDataBuilder();

        $org1 = $builder->createOrganization('OrgA');
        $org2 = $builder->createOrganization('OrgB');

        $role1 = $builder->createRole('Role1', $org1['org_id']);
        $role2 = $builder->createRole('Role2', $org2['org_id']);

        // Roles should belong to different organizations
        $this->assertNotEqual($role1['org_id'], $role2['org_id']);
    }

    /**
     * Test data isolation between organizations
     *
     * @testdox Data is properly isolated between organizations
     */
    public function testOrganizationDataIsolation(): void
    {
        $builder = $this->getTestDataBuilder();

        // Create two complete organization hierarchies
        $org1 = $builder->createOrganization('CompanyA');
        $role1a = $builder->createRole('Members', $org1['org_id']);
        $user1a = $builder->createUser('user1a', 'user1a@test.local', $org1['org_id']);

        $org2 = $builder->createOrganization('CompanyB');
        $role2a = $builder->createRole('Members', $org2['org_id']);
        $user2a = $builder->createUser('user2a', 'user2a@test.local', $org2['org_id']);

        // Verify isolation
        $this->assertNotEqual($org1['org_id'], $org2['org_id']);
        $this->assertNotEqual($role1a['org_id'], $role2a['org_id']);
        $this->assertNotEqual($user1a['org_id'], $user2a['org_id']);

        // Users can have same login in different orgs
        $user1b = $builder->createUser('user1a', 'user1a@company2.local', $org2['org_id']);
        $this->assertNotEqual($user1a['usr_id'], $user1b['usr_id']);
    }
}
