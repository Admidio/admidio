<?php
/**
 * User Entity Tests
 *
 * Tests User entity CRUD operations and core functionality.
 *
 * @testdox User entity handles creation, reading, updating, and deletion correctly
 */

namespace Admidio\Tests\Integration\Users;

use Admidio\Tests\Support\DatabaseTestCase;

class UserEntityTest extends DatabaseTestCase
{
    /**
     * Every test in this class builds its data with TestDataBuilder, which returns generated
     * arrays and never writes to the database, so none of them verifies any Admidio behaviour.
     * They are kept as a specification of what still needs coverage and will be reimplemented
     * against the real database for whatever the later test phases do not already cover.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestIncomplete('Uses the in-memory TestDataBuilder, needs a real database test.');
    }

    /**
     * Test creating a new user
     *
     * @testdox Creating a new user via Entity API works correctly
     */
    public function testCreateUser(): void
    {
        $builder = $this->getTestDataBuilder();
        $user = $builder->createUser('newuser', 'new@test.local');

        $this->assertNotEmpty($user['usr_id']);
        $this->assertNotEmpty($user['usr_uuid']);
        $this->assertEquals('newuser', $user['usr_login']);
        $this->assertEquals('new@test.local', $user['usr_email']);
        $this->assertValidUuid($user['usr_uuid']);
    }

    /**
     * Test reading an existing user
     *
     * @testdox Reading an existing user via Entity API works correctly
     */
    public function testReadUser(): void
    {
        $builder = $this->getTestDataBuilder();
        $createdUser = $builder->createUser('readtest', 'read@test.local');

        // In real implementation, would use User::readDataById()
        // This demonstrates the test pattern
        $this->assertEquals('readtest', $createdUser['usr_login']);
    }

    /**
     * Test updating user data
     *
     * @testdox Updating user data via Entity API works correctly
     */
    public function testUpdateUser(): void
    {
        $builder = $this->getTestDataBuilder();
        $user = $builder->createUser('updatetest', 'update@test.local');

        // In real implementation:
        // $userEntity = new User($gDb, TBL_USERS, 'usr', $user['usr_id']);
        // $userEntity->setValue('usr_email', 'newemail@test.local');
        // $userEntity->save();

        $this->assertNotEmpty($user['usr_id']);
    }

    /**
     * Test deleting a user
     *
     * @testdox Deleting a user via Entity API works correctly
     */
    public function testDeleteUser(): void
    {
        $builder = $this->getTestDataBuilder();
        $user = $builder->createUser('deletetest', 'delete@test.local');

        $userId = $user['usr_id'];
        $this->assertNotEmpty($userId);

        // In real implementation:
        // $userEntity = new User($gDb, TBL_USERS, 'usr', $userId);
        // $userEntity->delete();
    }

    /**
     * Test user UUID retrieval
     *
     * @testdox User UUID is always valid and unique
     */
    public function testUserUuid(): void
    {
        $builder = $this->getTestDataBuilder();

        $user1 = $builder->createUser('uuid1', 'uuid1@test.local');
        $user2 = $builder->createUser('uuid2', 'uuid2@test.local');

        // UUIDs should be different
        $this->assertNotEquals($user1['usr_uuid'], $user2['usr_uuid']);

        // Both should be valid UUIDs
        $this->assertValidUuid($user1['usr_uuid']);
        $this->assertValidUuid($user2['usr_uuid']);
    }

    /**
     * Test multiple users in same organization
     *
     * @testdox Multiple users can be created in the same organization
     */
    public function testMultipleUsersInOrganization(): void
    {
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('MultiUserOrg');

        $user1 = $builder->createUser('multi1', 'multi1@test.local', $org['org_id']);
        $user2 = $builder->createUser('multi2', 'multi2@test.local', $org['org_id']);
        $user3 = $builder->createUser('multi3', 'multi3@test.local', $org['org_id']);

        // All users should reference same organization
        $this->assertEquals($org['org_id'], $user1['org_id']);
        $this->assertEquals($org['org_id'], $user2['org_id']);
        $this->assertEquals($org['org_id'], $user3['org_id']);

        // All users should have different IDs
        $this->assertNotEquals($user1['usr_id'], $user2['usr_id']);
        $this->assertNotEquals($user2['usr_id'], $user3['usr_id']);
    }

    /**
     * Test user email validation
     *
     * @testdox User email addresses are stored and validated correctly
     */
    public function testUserEmail(): void
    {
        $builder = $this->getTestDataBuilder();

        $validEmails = [
            'simple@test.local',
            'user.name@test.local',
            'user+tag@test.local',
            'äöü@test.local', // UTF-8 email
        ];

        foreach ($validEmails as $email) {
            $user = $builder->createUser('emailtest', $email);
            $this->assertEquals($email, $user['usr_email']);
        }
    }

    /**
     * Test user creation timestamps
     *
     * @testdox User creation timestamps are valid
     */
    public function testUserTimestamp(): void
    {
        $builder = $this->getTestDataBuilder();
        $user = $builder->createUser('timetest', 'time@test.local');

        // Created timestamp should be valid
        $this->assertValidTimestamp($user['created_at']);
    }

    /**
     * Test changelog entry creation when user is created
     *
     * @testdox Creating a user generates a changelog entry
     */
    public function testUserChangelogOnCreate(): void
    {
        $builder = $this->getTestDataBuilder();
        $user = $builder->createUser('changelogtest', 'changelog@test.local');

        // In real implementation, would check changelog table:
        // SELECT * FROM adm_changelog WHERE chn_table_name = 'adm_users'
        //   AND chn_record_id = $user['usr_id'] AND chn_action = 'INSERT'

        $this->assertNotEmpty($user['usr_id']);
    }

    /**
     * Test user status/active flag
     *
     * @testdox User active status is handled correctly
     */
    public function testUserActiveStatus(): void
    {
        $builder = $this->getTestDataBuilder();

        $user = $builder->createUser('statustest', 'status@test.local');

        // New users should be active by default
        $this->assertNotEmpty($user['usr_id']);
    }

    /**
     * Test users in different organizations are isolated
     *
     * @testdox Users in different organizations are properly isolated
     */
    public function testUserOrganizationIsolation(): void
    {
        $builder = $this->getTestDataBuilder();

        $org1 = $builder->createOrganization('Org1');
        $org2 = $builder->createOrganization('Org2');

        $user1 = $builder->createUser('org1user', 'org1@test.local', $org1['org_id']);
        $user2 = $builder->createUser('org2user', 'org2@test.local', $org2['org_id']);

        // Users should belong to different organizations
        $this->assertNotEquals($user1['org_id'], $user2['org_id']);
    }
}
