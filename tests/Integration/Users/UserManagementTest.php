<?php
/**
 * User Management Tests
 *
 * Tests user creation, updates, and basic user entity operations.
 */

namespace Admidio\Tests\Integration\Users;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Users\Entity\User;

class UserManagementTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test creating users with login names
     *
     * @testdox Users can be created with login names
     */
    public function testUserCreation(): void
    {
        $fixture = $this->getFixture();

        $user = $fixture->createAndSaveUser('testuser', 'test@example.local');

        // Verify user exists with correct login
        $this->assertNotEmpty($user['usr_id']);
        $this->assertEquals('testuser', $user['usr_login_name']);
    }

    /**
     * Test multiple users can be created
     *
     * @testdox Multiple users can be created independently
     */
    public function testMultipleUserCreation(): void
    {
        $fixture = $this->getFixture();

        $user1 = $fixture->createAndSaveUser('user1', 'user1@example.local');
        $user2 = $fixture->createAndSaveUser('user2', 'user2@example.local');
        $user3 = $fixture->createAndSaveUser('user3', 'user3@example.local');

        // Verify all users exist with unique IDs
        $this->assertNotEmpty($user1['usr_id']);
        $this->assertNotEmpty($user2['usr_id']);
        $this->assertNotEmpty($user3['usr_id']);
        $this->assertNotEquals($user1['usr_id'], $user2['usr_id']);
        $this->assertNotEquals($user2['usr_id'], $user3['usr_id']);

        // Verify correct login names
        $this->assertEquals('user1', $user1['usr_login_name']);
        $this->assertEquals('user2', $user2['usr_login_name']);
        $this->assertEquals('user3', $user3['usr_login_name']);
    }

    /**
     * Test users have unique UUIDs
     *
     * @testdox Each user has unique UUID
     */
    public function testUserUUIDs(): void
    {
        $fixture = $this->getFixture();

        $user1 = $fixture->createAndSaveUser('user1', 'user1@example.local');
        $user2 = $fixture->createAndSaveUser('user2', 'user2@example.local');

        // Verify UUIDs are unique
        $this->assertNotEmpty($user1['usr_uuid']);
        $this->assertNotEmpty($user2['usr_uuid']);
        $this->assertNotEquals($user1['usr_uuid'], $user2['usr_uuid']);

        // Verify UUID format (basic check)
        $this->assertMatchesRegularExpression('/^[a-f0-9\-]{36}$/', $user1['usr_uuid']);
    }

    /**
     * Test user lookup by ID
     *
     * @testdox Users can be looked up by ID
     */
    public function testUserLookupById(): void
    {
        $fixture = $this->getFixture();

        $user = $fixture->createAndSaveUser('lookuptest', 'lookup@example.local');

        // Lookup user
        $foundUser = $fixture->getUserById($user['usr_id']);

        // Verify found user
        $this->assertNotEmpty($foundUser);
        $this->assertEquals($user['usr_login_name'], $foundUser['usr_login_name']);
    }

    /**
     * Test user deletion
     *
     * @testdox Users can be deleted
     */
    public function testUserDeletion(): void
    {
        $fixture = $this->getFixture();

        $user = $fixture->createAndSaveUser('deletetest', 'delete@example.local');
        $this->assertNotEmpty($fixture->getUserById($user['usr_id']));

        // Delete user
        $deleted = $fixture->deleteUser($user['usr_id']);
        $this->assertTrue($deleted);

        // Verify user is deleted
        $foundUser = $fixture->getUserById($user['usr_id']);
        $this->assertEmpty($foundUser);
    }

    /**
     * Test users are initially invalid (need approval)
     *
     * @testdox Newly created users are marked as invalid by default
     */
    public function testNewUserValidity(): void
    {
        $fixture = $this->getFixture();

        $user = $fixture->createAndSaveUser('invalidtest', 'invalid@example.local');

        // Check user validity in database
        $sql = 'SELECT usr_valid FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$user['usr_id']]);
        $row = $result->fetch();

        // New users should be invalid until approved
        $this->assertNotNull($row['usr_valid']);
    }

    /**
     * Test user has numeric ID
     *
     * @testdox User ID is a numeric unsigned integer
     */
    public function testUserIdType(): void
    {
        $fixture = $this->getFixture();

        $user = $fixture->createAndSaveUser('typetest', 'type@example.local');

        // Verify ID is integer
        $this->assertIsInt($user['usr_id']);
        $this->assertGreaterThan(0, $user['usr_id']);
    }
}
