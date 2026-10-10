<?php
/**
 * The login of the updater against a real Admidio database: who counts as an
 * administrator, and what the login refuses. The updater reads with plain PDO; it is handed the
 * connection of the test, so that it sees the rows of the test transaction.
 */

namespace Admidio\Tests\Integration\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use AdmidioCoreUpdater as U;
use AdmidioCoreUpdater\UpdaterException;
use PDO;

final class UpdaterLoginTest extends DatabaseTestCase
{
    private const PASSWORD = 'Updater-Test-Password-1';

    private string $directory = '';
    private PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/adm_updater_login_' . uniqid();
        mkdir($this->directory, 0777, true);

        // The updater opens a PDO connection of its own; here it gets the one of the test transaction.
        $pdo = (new \ReflectionProperty($this->getDatabase(), 'pdo'))->getValue($this->getDatabase());
        $this->assertInstanceOf(PDO::class, $pdo);
        $this->pdo = $pdo;
    }

    protected function tearDown(): void
    {
        U\deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * A user with a password, optionally a member of a role with or without administrator rights.
     * @return int The user ID.
     */
    private function createUser(string $loginName, ?bool $administratorRole, string $membershipEnd = '9999-12-31'): int
    {
        $fixture = new AdmidioTestFixture($this->getDatabase());
        $userId = $fixture->createAndSaveUser($loginName, $loginName . '@test.local')['usr_id'];
        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_USERS . ' SET usr_password = ?, usr_valid = ? WHERE usr_id = ?',
            array(password_hash(self::PASSWORD, PASSWORD_DEFAULT), true, $userId)
        );

        if ($administratorRole !== null) {
            $orgId = (int)$this->getDatabase()->queryPrepared('SELECT MIN(org_id) FROM ' . TBL_ORGANIZATIONS)->fetchColumn();
            $roleId = $fixture->createAndSaveRole('Updater test role ' . $loginName, $orgId)['rol_id'];
            $this->getDatabase()->queryPrepared(
                'UPDATE ' . TBL_ROLES . ' SET rol_administrator = ? WHERE rol_id = ?',
                array($administratorRole, $roleId)
            );
            $membershipId = $fixture->assignUserToRole($userId, $roleId, '2020-01-01')['mem_id'];
            $this->getDatabase()->queryPrepared(
                'UPDATE ' . TBL_MEMBERS . ' SET mem_end = ? WHERE mem_id = ?',
                array($membershipEnd, $membershipId)
            );
        }

        return $userId;
    }

    /**
     * Log in with the updater's own login.
     */
    private function authenticate(string $loginName, string $password, string $totpCode, int $now): int
    {
        return U\authenticateAdministrator($this->pdo, TABLE_PREFIX, $this->directory, $loginName, $password, $totpCode, '127.0.0.1', $now);
    }

    /**
     * Assert that a login is refused with the generic message.
     */
    private function assertRefused(string $loginName, string $password, string $reason): void
    {
        try {
            $this->authenticate($loginName, $password, '', time());
            $this->fail('accepted: ' . $reason);
        } catch (UpdaterException $exception) {
            $this->assertSame('SYS_CORE_UPDATE_LOGIN_FAILED', $exception->getMessage(), $reason);
        }
    }

    /**
     * @testdox An administrator logs in with the login name in any case
     */
    public function testAdministrator(): void
    {
        $userId = $this->createUser('updater_admin', true);

        $this->assertSame($userId, $this->authenticate('Updater_Admin', self::PASSWORD, '', time()));
        $this->assertStringContainsString("Updater_Admin\tsuccess, user " . $userId, (string)file_get_contents($this->directory . '/login.log'));
    }

    /**
     * @testdox A member who is no administrator, or no longer one, is refused
     */
    public function testNoAdministrator(): void
    {
        $this->createUser('updater_member', false);
        $this->createUser('updater_former', true, '2021-01-01');
        $this->createUser('updater_nobody', null);

        $this->assertRefused('updater_member', self::PASSWORD, 'member of a role without administrator rights');
        $this->assertRefused('updater_former', self::PASSWORD, 'membership in the administrator role has ended');
        $this->assertRefused('updater_nobody', self::PASSWORD, 'no membership at all');
    }

    /**
     * @testdox A wrong password, an unknown user and an invalid user are refused alike
     */
    public function testWrongCredentials(): void
    {
        $userId = $this->createUser('updater_admin2', true);

        $this->assertRefused('updater_admin2', 'wrong', 'wrong password');
        $this->assertRefused('updater_unknown', self::PASSWORD, 'unknown user');
        $this->assertRefused('', '', 'empty form');

        $this->getDatabase()->queryPrepared('UPDATE ' . TBL_USERS . ' SET usr_valid = ? WHERE usr_id = ?', array(false, $userId));
        $this->assertRefused('updater_admin2', self::PASSWORD, 'user no longer valid');
    }

    /**
     * @testdox An account that Admidio has locked after wrong passwords is refused
     */
    public function testAccountLockedByAdmidio(): void
    {
        $userId = $this->createUser('updater_locked', true);
        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_USERS . ' SET usr_number_invalid = ?, usr_date_invalid = ? WHERE usr_id = ?',
            array(3, date('Y-m-d H:i:s', time() - 60), $userId)
        );
        $this->assertRefused('updater_locked', self::PASSWORD, 'locked a minute ago');

        $this->getDatabase()->queryPrepared(
            'UPDATE ' . TBL_USERS . ' SET usr_date_invalid = ? WHERE usr_id = ?',
            array(date('Y-m-d H:i:s', time() - 3600), $userId)
        );
        $this->assertSame($userId, $this->authenticate('updater_locked', self::PASSWORD, '', time()),
            'the lock of an hour ago is over');
    }

    /**
     * @testdox With two-factor authentication the security code is required
     */
    public function testTwoFactorAuthentication(): void
    {
        $userId = $this->createUser('updater_tfa', true);
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->getDatabase()->queryPrepared('UPDATE ' . TBL_USERS . ' SET usr_tfa_secret = ? WHERE usr_id = ?', array($secret, $userId));

        $this->assertRefused('updater_tfa', self::PASSWORD, 'no security code');

        $now = time();
        $code = U\totpCode(U\decodeBase32($secret), intdiv($now, 30), 6);
        $this->assertSame($userId, $this->authenticate('updater_tfa', self::PASSWORD, $code, $now));
    }

    /**
     * @testdox After five failures even the correct password is refused for 15 minutes
     */
    public function testThrottle(): void
    {
        $userId = $this->createUser('updater_throttled', true);
        $now = time();

        for ($attempt = 0; $attempt < U\MAX_LOGIN_FAILURES; $attempt++) {
            try {
                $this->authenticate('updater_throttled', 'wrong', '', $now);
            } catch (UpdaterException) {
            }
        }

        try {
            $this->authenticate('updater_throttled', self::PASSWORD, '', $now);
            $this->fail('the correct password was accepted during the block');
        } catch (UpdaterException $exception) {
            $this->assertSame('SYS_CORE_UPDATE_LOGIN_BLOCKED', $exception->getMessage());
        }

        $this->assertSame($userId, $this->authenticate('updater_throttled', self::PASSWORD, '', $now + U\LOGIN_BLOCK));
    }
}
