<?php
/**
 * User Authentication Tests
 *
 * Tests user login, logout, session management, and authentication flows.
 */

namespace Admidio\Tests\Integration\Core;

use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Session\Entity\Session;
use Admidio\Users\Entity\User;

class UserAuthenticationTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Build a Session for an explicit session id. The Session constructor resolves its id from the
     * session cookie before falling back to session_id(), which is the only way to get more than one
     * distinct session per process: session_regenerate_id() cannot run once PHPUnit has written output.
     */
    private function createSessionWithId(string $sessionId): Session
    {
        $_COOKIE[COOKIE_PREFIX . '_SESSION_ID'] = $sessionId;

        return new Session($this->getDatabase(), COOKIE_PREFIX);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[COOKIE_PREFIX . '_SESSION_ID']);
        parent::tearDown();
    }

    /**
     * Test user can be marked as valid
     *
     * @testdox User can be marked as valid in database
     */
    public function testUserCanBeMarkedAsValid(): void
    {
        $fixture = $this->getFixture();

        // Create user
        $user = $fixture->createAndSaveUser('validuser', 'valid@test.local');

        // Mark as valid
        $fixture->markUserAsValid($user['usr_id']);

        // Verify in database
        $this->assertTrue($fixture->isUserValid($user['usr_id']));

        // Verify directly in database
        $sql = 'SELECT usr_valid FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $result = $this->getDatabase()->queryPrepared($sql, [$user['usr_id']]);
        $row = $result->fetch();
        $this->assertTrue((bool) $row['usr_valid']);
    }

    /**
     * Test user can be added to session
     *
     * @testdox User object can be stored in and retrieved from session
     */
    public function testUserCanBeAddedToSession(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create user and mark valid
        $user = $fixture->createAndSaveUser('sessionuser', 'session@test.local');
        $fixture->markUserAsValid($user['usr_id']);

        // Create session
        $session = new Session($db, COOKIE_PREFIX);

        // Load user entity
        $userEntity = new User($db);
        $userEntity->readDataById($user['usr_id']);

        // Add user to session
        $session->addObject('gCurrentUser', $userEntity);

        // Verify user in session
        $this->assertTrue($session->hasObject('gCurrentUser'));

        // Retrieve user from session
        $retrievedUser = $session->getObject('gCurrentUser');
        $this->assertNotNull($retrievedUser);
        $this->assertEquals($user['usr_id'], $retrievedUser->getValue('usr_id'));
    }

    /**
     * Test session user ID tracking
     *
     * @testdox Session correctly tracks current user ID
     */
    public function testSessionUserIdMatching(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create user
        $user = $fixture->createAndSaveUser('idmatch', 'idmatch@test.local');
        $fixture->markUserAsValid($user['usr_id']);

        // Create session and set user
        $session = new Session($db, COOKIE_PREFIX);
        $session->setValue('ses_usr_id', $user['usr_id']);

        // Verify session tracks the ID
        $this->assertEquals($user['usr_id'], $session->getValue('ses_usr_id'));
    }

    /**
     * Test multiple concurrent users in different sessions
     *
     * @testdox Multiple users can have independent sessions
     */
    public function testMultipleUserSessions(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create users
        $user1 = $fixture->createAndSaveUser('sessionuser1', 'u1@test.local');
        $user2 = $fixture->createAndSaveUser('sessionuser2', 'u2@test.local');

        // Create two distinct sessions
        $session1 = $this->createSessionWithId('session-one');
        $session2 = $this->createSessionWithId('session-two');

        // Add to different sessions
        $session1->setValue('ses_usr_id', $user1['usr_id']);
        $session2->setValue('ses_usr_id', $user2['usr_id']);

        // Verify isolation
        $this->assertEquals($user1['usr_id'], $session1->getValue('ses_usr_id'));
        $this->assertEquals($user2['usr_id'], $session2->getValue('ses_usr_id'));
        $this->assertNotEquals(
            $session1->getValue('ses_usr_id'),
            $session2->getValue('ses_usr_id')
        );
    }

    /**
     * Test user logout clears session
     *
     * @testdox Session user is cleared on logout
     */
    public function testUserLogout(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create user and session
        $user = $fixture->createAndSaveUser('logoutuser', 'logout@test.local');
        $fixture->markUserAsValid($user['usr_id']);

        $session = new Session($db, COOKIE_PREFIX);
        $session->setValue('ses_usr_id', $user['usr_id']);

        // Verify user is in session
        $this->assertEquals($user['usr_id'], $session->getValue('ses_usr_id'));

        // Logout by clearing user ID
        $session->setValue('ses_usr_id', 0);

        // Verify user cleared
        $this->assertEquals(0, $session->getValue('ses_usr_id'));
    }

    /**
     * Test user validity flag can be revoked
     *
     * @testdox User validity can be revoked and is persisted
     */
    public function testUserValidityCanBeRevoked(): void
    {
        $fixture = $this->getFixture();

        $user = $fixture->createAndSaveUser('revokeuser', 'revoke@test.local');

        $fixture->setUserValidity($user['usr_id'], true);
        $this->assertTrue($fixture->isUserValid($user['usr_id']));

        $fixture->setUserValidity($user['usr_id'], false);
        $this->assertFalse($fixture->isUserValid($user['usr_id']));
    }

    /**
     * Test session expiration logic
     *
     * @testdox Session timestamp tracking supports expiration logic
     */
    public function testSessionTimestampTracking(): void
    {
        $db = $this->getDatabase();

        // Create session
        $session = new Session($db, COOKIE_PREFIX);

        // Get session timestamp
        $timestamp = $session->getValue('ses_timestamp');
        $this->assertNotEmpty($timestamp);
        $this->assertNotNull($timestamp);

        // ses_timestamp is set from DATETIME_NOW, which is frozen when the bootstrap runs, so the
        // value only has to be a parseable timestamp from this test run, not from this second
        $sessionTime = strtotime($timestamp);
        $this->assertNotFalse($sessionTime);
        $this->assertLessThanOrEqual(time(), $sessionTime);
        $this->assertGreaterThan(time() - 3600, $sessionTime);
    }

    /**
     * Test user role assignment affects session
     *
     * @testdox User with role membership can be tracked in session
     */
    public function testUserRoleInSession(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create organization
        $org = $fixture->createAndSaveOrganization('AuthOrg', 'auth');

        // Create user
        $user = $fixture->createAndSaveUser('roleuser', 'role@test.local');
        $fixture->markUserAsValid($user['usr_id']);

        // Create role
        $role = $fixture->createAndSaveRole('Test Role', $org['org_id']);

        // Assign user to role
        $membership = $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        // Verify membership exists
        $this->assertTrue($fixture->membershipExists($membership['mem_id']));

        // Session can now track user
        $session = new Session($db, COOKIE_PREFIX);
        $session->setValue('ses_usr_id', $user['usr_id']);
        $session->setValue('ses_org_id', $org['org_id']);

        $this->assertEquals($user['usr_id'], $session->getValue('ses_usr_id'));
        $this->assertEquals($org['org_id'], $session->getValue('ses_org_id'));
    }

    /**
     * Test session organization tracking
     *
     * @testdox Session can track which organization context user is in
     */
    public function testSessionOrganizationTracking(): void
    {
        $fixture = $this->getFixture();
        $db = $this->getDatabase();

        // Create two organizations
        $org1 = $fixture->createAndSaveOrganization('Org A', 'orga');
        $org2 = $fixture->createAndSaveOrganization('Org B', 'orgb');

        // Create user
        $user = $fixture->createAndSaveUser('orguser', 'org@test.local');

        // Create sessions for different organizations
        $session1 = $this->createSessionWithId('org-session-one');
        $session2 = $this->createSessionWithId('org-session-two');

        // Track organization context
        $session1->setValue('ses_usr_id', $user['usr_id']);
        $session1->setValue('ses_org_id', $org1['org_id']);

        $session2->setValue('ses_usr_id', $user['usr_id']);
        $session2->setValue('ses_org_id', $org2['org_id']);

        // Verify different organization contexts
        $this->assertEquals($org1['org_id'], $session1->getValue('ses_org_id'));
        $this->assertEquals($org2['org_id'], $session2->getValue('ses_org_id'));
    }
}
