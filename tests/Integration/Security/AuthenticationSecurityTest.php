<?php
/**
 * Authentication Security Tests
 *
 * Tests how a password is stored and who is let in. The password never leaves the database in a
 * readable form, and an account that collected three wrong attempts within a quarter of an hour is
 * closed for that long, which is what turns guessing from cheap into pointless.
 *
 * A refused login writes before it throws: handleIncorrectLogin() increments usr_number_invalid and
 * saves it. Building the exception no longer rolls that back, so the counter survives the refusal
 * and the tests can read the database afterwards.
 */

namespace Admidio\Tests\Integration\Security;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\PasswordUtils;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class AuthenticationSecurityTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation.
     */
    private const ORG_ID = 1;

    /**
     * The password the test users are given.
     */
    private const PASSWORD = 'Correct-Horse-Battery-1';

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Run a callback as the administrator of the installed organization.
     */
    private function asAdministrator(callable $callback)
    {
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $usrId = (int) $this->getDatabase()->queryPrepared($sql, ['admin'])->fetchColumn();

        $administrator = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);

        return $this->withCurrentUser($administrator, self::ORG_ID, true, $callback);
    }

    /**
     * Create a user who is a member of the organization and has a password.
     *
     * @return array{usr_id: int}
     */
    private function createMember(string $login): array
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Login Members ' . $login, self::ORG_ID);
        $user = $fixture->createAndSaveUser($login, $login . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
        $this->setPasswordOf($user['usr_id']);

        return $user;
    }

    /**
     * Give a user the test password.
     */
    private function setPasswordOf(int $usrId): void
    {
        $this->asAdministrator(function () use ($usrId) {
            $entity = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);
            $entity->saveChangesWithoutRights();
            $entity->setPassword(self::PASSWORD);
            $entity->save();
        });
    }

    /**
     * The stored password hash of a user.
     */
    private function hashOf(int $usrId): string
    {
        $sql = 'SELECT usr_password FROM ' . TBL_USERS . ' WHERE usr_id = ?';

        return (string) $this->getDatabase()->queryPrepared($sql, [$usrId])->fetchColumn();
    }

    /**
     * The number of failed login attempts that is stored for a user.
     */
    private function failedAttemptsOf(int $usrId): int
    {
        $sql = 'SELECT usr_number_invalid FROM ' . TBL_USERS . ' WHERE usr_id = ?';

        return (int) $this->getDatabase()->queryPrepared($sql, [$usrId])->fetchColumn();
    }

    /**
     * Record failed login attempts on a user without going through a refusal.
     */
    private function recordFailedAttempts(int $usrId, int $count, string $lastAttempt): void
    {
        $sql = 'UPDATE ' . TBL_USERS . ' SET usr_number_invalid = ?, usr_date_invalid = ? WHERE usr_id = ?';
        $this->getDatabase()->queryPrepared($sql, [$count, $lastAttempt, $usrId]);
    }

    /**
     * Attempt a login and return the reason it was refused, or null when it succeeded.
     */
    private function attemptLogin(int $usrId, string $password): ?string
    {
        return $this->asAdministrator(function () use ($usrId, $password) {
            $user = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);

            try {
                $user->checkLogin($password, false, false, false);

                return null;
            } catch (Exception $e) {
                return $e->getMessage();
            }
        });
    }

    /**
     * Test that the password is not stored as given
     *
     * @testdox A password is stored as a hash and never in a readable form
     */
    public function testPasswordIsStoredAsAHash(): void
    {
        $user = $this->createMember('hashuser');
        $hash = $this->hashOf($user['usr_id']);

        $this->assertNotEquals(self::PASSWORD, $hash);
        $this->assertStringNotContainsString(self::PASSWORD, $hash);

        // Admidio hashes with bcrypt by default
        $this->assertStringStartsWith(PasswordUtils::HASH_INDICATOR_BCRYPT, $hash);
        $this->assertEquals('bcrypt', PasswordUtils::hashInfo($hash)['algoName']);
    }

    /**
     * Test that the hash answers the password
     *
     * @testdox The stored hash accepts the right password and rejects every other
     */
    public function testStoredHashAcceptsOnlyTheRightPassword(): void
    {
        $user = $this->createMember('verifyuser');
        $hash = $this->hashOf($user['usr_id']);

        $this->assertTrue(PasswordUtils::verify(self::PASSWORD, $hash));
        $this->assertFalse(PasswordUtils::verify('Correct-Horse-Battery-2', $hash));
        $this->assertFalse(PasswordUtils::verify(strtolower(self::PASSWORD), $hash));
        $this->assertFalse(PasswordUtils::verify('', $hash));
    }

    /**
     * Test that two users with the same password get different hashes
     *
     * @testdox Two users with the same password do not share a hash
     */
    public function testTwoUsersWithTheSamePasswordDoNotShareAHash(): void
    {
        $first = $this->createMember('saltuser1');
        $second = $this->createMember('saltuser2');

        $firstHash = $this->hashOf($first['usr_id']);
        $secondHash = $this->hashOf($second['usr_id']);

        // each hash carries its own salt, so a stolen table cannot be attacked in one pass
        $this->assertNotEquals($firstHash, $secondHash);
        $this->assertTrue(PasswordUtils::verify(self::PASSWORD, $firstHash));
        $this->assertTrue(PasswordUtils::verify(self::PASSWORD, $secondHash));
    }

    /**
     * Test that a login with the right password works
     *
     * @testdox A member can log in with the right password
     */
    public function testMemberCanLogInWithTheRightPassword(): void
    {
        $user = $this->createMember('loginuser');

        $this->assertNull($this->attemptLogin($user['usr_id'], self::PASSWORD));
    }

    /**
     * Test that a wrong password does not let anybody in
     *
     * @testdox A wrong password is refused without saying which half was wrong
     */
    public function testWrongPasswordIsRefused(): void
    {
        $user = $this->createMember('wronguser');

        $message = $this->attemptLogin($user['usr_id'], 'not-the-password');

        $this->assertNotNull($message);

        // the message does not reveal whether the account exists
        $this->assertStringContainsString('username and/or password', $message);
    }

    /**
     * Test that the refusal itself counts the attempt
     *
     * @testdox A refused login counts the attempt even within an open transaction
     */
    public function testRefusedLoginCountsTheAttemptWithinATransaction(): void
    {
        $user = $this->createMember('countinguser');
        $this->assertEquals(0, $this->failedAttemptsOf($user['usr_id']));

        // this test runs inside a transaction, and handleIncorrectLogin() writes the counter before
        // the refusal is thrown. Building the exception must not discard that write.
        $this->assertNotNull($this->attemptLogin($user['usr_id'], 'not-the-password'));
        $this->assertEquals(1, $this->failedAttemptsOf($user['usr_id']));

        $this->assertNotNull($this->attemptLogin($user['usr_id'], 'still-not-the-password'));
        $this->assertEquals(2, $this->failedAttemptsOf($user['usr_id']));
    }

    /**
     * Test that a series of wrong attempts closes the account
     *
     * @testdox An account with three recent failed attempts is locked
     */
    public function testAccountWithThreeRecentFailedAttemptsIsLocked(): void
    {
        $user = $this->createMember('lockeduser');
        $this->recordFailedAttempts($user['usr_id'], User::MAX_INVALID_LOGINS, DATETIME_NOW);

        // knowing the password is not enough while the account is locked
        $message = $this->attemptLogin($user['usr_id'], self::PASSWORD);

        $this->assertNotNull($message);
        $this->assertStringContainsString('locked', $message);
    }

    /**
     * Test that one attempt short of the limit still lets the owner in
     *
     * @testdox An account below the limit of failed attempts is not locked
     */
    public function testAccountBelowTheLimitIsNotLocked(): void
    {
        $user = $this->createMember('nearlylocked');
        $this->recordFailedAttempts($user['usr_id'], User::MAX_INVALID_LOGINS - 1, DATETIME_NOW);

        $this->assertNull($this->attemptLogin($user['usr_id'], self::PASSWORD));
    }

    /**
     * Test that the lock is not permanent
     *
     * @testdox The lock expires once the failed attempts are old enough
     */
    public function testLockExpiresOnceTheFailedAttemptsAreOldEnough(): void
    {
        $user = $this->createMember('expireduser');

        // the same three failures, but from longer ago than the fifteen minute window
        $this->recordFailedAttempts(
            $user['usr_id'],
            User::MAX_INVALID_LOGINS,
            date('Y-m-d H:i:s', strtotime('-20 minutes'))
        );

        $this->assertNull($this->attemptLogin($user['usr_id'], self::PASSWORD));
    }

    /**
     * Test that the counter can be cleared
     *
     * @testdox Resetting the failed attempts clears the counter and the timestamp
     */
    public function testResettingTheFailedAttemptsClearsTheCounter(): void
    {
        $user = $this->createMember('resetuser');
        $this->recordFailedAttempts($user['usr_id'], 2, DATETIME_NOW);

        $this->asAdministrator(function () use ($user) {
            $entity = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $user['usr_id']);
            $entity->saveChangesWithoutRights();
            $entity->resetInvalidLogins();
        });

        $sql = 'SELECT usr_number_invalid, usr_date_invalid FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$user['usr_id']])->fetch();

        $this->assertEquals(0, (int) $row['usr_number_invalid']);
        $this->assertNull($row['usr_date_invalid']);
    }

    /**
     * Test that an inactive user cannot log in
     *
     * @testdox A user whose account is not activated cannot log in
     */
    public function testUserWhoseAccountIsNotActivatedCannotLogIn(): void
    {
        $fixture = $this->getFixture();
        $user = $this->createMember('inactiveuser');
        $fixture->setUserValidity($user['usr_id'], false);

        $message = $this->attemptLogin($user['usr_id'], self::PASSWORD);

        $this->assertNotNull($message);
        $this->assertStringContainsString('not been activated', $message);
    }

    /**
     * Test that membership is required
     *
     * @testdox A user who is not a member of the organization cannot log in
     */
    public function testUserWhoIsNotAMemberOfTheOrganizationCannotLogIn(): void
    {
        $fixture = $this->getFixture();
        $user = $fixture->createAndSaveUser('nomember', 'nm@example.local');
        $this->setPasswordOf($user['usr_id']);

        // the password is right, but the account belongs to no role of this organization
        $message = $this->attemptLogin($user['usr_id'], self::PASSWORD);

        $this->assertNotNull($message);
        $this->assertStringContainsString('not an active member', $message);
    }

    /**
     * Test that password strength is measured
     *
     * @testdox The strength of a password is scored from weak to strong
     */
    public function testStrengthOfAPasswordIsScored(): void
    {
        $this->assertEquals(0, PasswordUtils::passwordStrength('123456'));
        $this->assertEquals(0, PasswordUtils::passwordStrength('password'));
        $this->assertEquals(4, PasswordUtils::passwordStrength('t7#Qz!vLm3pR9wX'));

        // the score also takes the data of the user into account
        $withoutContext = PasswordUtils::passwordStrength('Muster1963');
        $withContext = PasswordUtils::passwordStrength('Muster1963', array('Muster', '1963'));
        $this->assertLessThan($withoutContext, $withContext);

        $info = PasswordUtils::passwordInfo('Abc1!xyz');
        $this->assertEquals(8, $info['length']);
        $this->assertTrue($info['number']);
        $this->assertTrue($info['lowerCase']);
        $this->assertTrue($info['upperCase']);
        $this->assertTrue($info['symbol']);
    }
}
