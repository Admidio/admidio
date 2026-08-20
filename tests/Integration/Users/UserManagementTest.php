<?php
/**
 * User Management Tests
 *
 * Tests user creation, updates, and basic user entity operations.
 */

namespace Admidio\Tests\Integration\Users;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;

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

        // the row has to exist in the database, not just in the object the fixture returned
        $stored = $fixture->getUserById($user['usr_id']);
        $this->assertNotEmpty($stored);
        $this->assertEquals('testuser', $stored['usr_login_name']);
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
        $this->assertNotEquals($user1['usr_id'], $user2['usr_id']);
        $this->assertNotEquals($user2['usr_id'], $user3['usr_id']);
        $this->assertNotEquals($user1['usr_id'], $user3['usr_id']);

        // each one has to be readable back with its own login name
        $this->assertEquals('user1', $fixture->getUserById($user1['usr_id'])['usr_login_name']);
        $this->assertEquals('user2', $fixture->getUserById($user2['usr_id'])['usr_login_name']);
        $this->assertEquals('user3', $fixture->getUserById($user3['usr_id'])['usr_login_name']);
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

        $stored1 = $fixture->getUserById($user1['usr_id']);
        $stored2 = $fixture->getUserById($user2['usr_id']);

        // Verify UUIDs are unique
        $this->assertNotEquals($stored1['usr_uuid'], $stored2['usr_uuid']);

        // Verify UUID format
        $uuidPattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';
        $this->assertMatchesRegularExpression($uuidPattern, $stored1['usr_uuid']);
        $this->assertMatchesRegularExpression($uuidPattern, $stored2['usr_uuid']);
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
        $this->assertEquals($user['usr_id'], $foundUser['usr_id']);
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
     * Test that a user created through the Entity API is valid right away.
     * User::clear() sets usr_valid to 1 ("new user should be valid (except registration)"),
     * so only UserRegistration creates a user that still needs approval.
     *
     * @testdox Users created through the Entity API are valid immediately
     */
    public function testNewUserValidity(): void
    {
        $fixture = $this->getFixture();

        $user = $fixture->createAndSaveUser('validitytest', 'validity@example.local');

        $sql = 'SELECT usr_valid FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$user['usr_id']]);
        $row = $result->fetch();

        $this->assertTrue((bool) $row['usr_valid']);
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

        // read the key back from the database, the fixture casts its own return value to int
        $stored = $fixture->getUserById($user['usr_id']);
        $this->assertIsInt($stored['usr_id']);
        $this->assertGreaterThan(0, $stored['usr_id']);
    }
}
