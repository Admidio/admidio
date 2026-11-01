<?php

namespace Admidio\Tests\Unit\Session;

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Session\Entity\AutoLogin;
use Admidio\Session\Entity\Session;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use Admidio\Users\Entity\User;
use ReflectionProperty;

/** Execute revocation and session refresh against an in-memory database. */
final class LoginRevocationTest extends EntityHookTestCase
{
    private FakeDatabase $database;
    private array $previousGlobals;
    private array $previousCookies;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!defined('COOKIE_PREFIX')) {
            define('COOKIE_PREFIX', 'ADMIDIO_revocation');
        }
        foreach (array('TBL_SESSIONS' => 'sessions', 'TBL_AUTO_LOGIN' => 'auto_login') as $constant => $table) {
            if (!defined($constant)) {
                define($constant, TABLE_PREFIX . '_' . $table);
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousGlobals = array();
        foreach (array('gCurrentSession', 'gCurrentUserId', 'gCheckIpAddress') as $name) {
            $this->previousGlobals[$name] = $GLOBALS[$name] ?? null;
        }
        $this->previousCookies = $_COOKIE;
        $_COOKIE = array();
        $GLOBALS['gCheckIpAddress'] = false;

        $this->database = new FakeDatabase();
        $this->database->createTable(TBL_SESSIONS, $this->columns('ses', array(
            'ses_usr_id', 'ses_org_id', 'ses_session_id', 'ses_timestamp', 'ses_ip_address',
            'ses_authentication_time', 'ses_authentication_methods', 'ses_external_session_id'
        )));
        $this->database->createTable(TBL_AUTO_LOGIN, $this->columns('atl', array(
            'atl_usr_id', 'atl_org_id', 'atl_auto_login_id', 'atl_session_id'
        )));
        foreach (array(1 => 42, 2 => 42, 3 => 84, 4 => null) as $id => $userId) {
            $this->database->queryPrepared(
                'INSERT INTO ' . TBL_SESSIONS . ' (ses_id, ses_usr_id, ses_session_id, ses_timestamp, ses_authentication_time)
                 VALUES (?, ?, ?, ?, ?)',
                array($id, $userId, 'session-' . $id, DATETIME_NOW, DATETIME_NOW)
            );
        }
        foreach (array(1 => 42, 2 => 84) as $id => $userId) {
            $this->database->queryPrepared(
                'INSERT INTO ' . TBL_AUTO_LOGIN . ' (atl_id, atl_usr_id, atl_auto_login_id) VALUES (?, ?, ?)',
                array($id, $userId, $userId . ':remember-me')
            );
        }
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->previousCookies;
        foreach ($this->previousGlobals as $name => $value) {
            if ($value === null) {
                unset($GLOBALS[$name]);
            } else {
                $GLOBALS[$name] = $value;
            }
        }
        parent::tearDown();
    }

    private function columns(string $prefix, array $names): array
    {
        $columns = FakeDatabase::columnDefinition($prefix);
        foreach ($names as $name) {
            $type = in_array($name, array('ses_usr_id', 'ses_org_id', 'atl_usr_id', 'atl_org_id'), true) ? 'integer' : 'varchar(255)';
            if (in_array($name, array('ses_timestamp', 'ses_authentication_time'), true)) {
                $type = 'timestamp';
            }
            $columns[$name] = array('type' => $type, 'null' => true, 'key' => false, 'serial' => false, 'default' => null);
        }
        return $columns;
    }

    private function session(int $id, bool $remember = false): Session
    {
        $_COOKIE[COOKIE_PREFIX . '_SESSION_ID'] = 'session-' . $id;
        $session = new Session($this->database, COOKIE_PREFIX);
        if ($remember) {
            (new ReflectionProperty(Session::class, 'mAutoLogin'))->setValue(
                $session, new AutoLogin($this->database, '42:remember-me')
            );
        }
        return $session;
    }

    private function user(int $id = 42): User
    {
        // Profile fields and permissions do not participate in login revocation.
        $user = $this->getMockBuilder(User::class)->disableOriginalConstructor()->onlyMethods(array('getValue'))->getMock();
        $user->method('getValue')->with('usr_id')->willReturn($id);
        (new ReflectionProperty(Entity::class, 'db'))->setValue($user, $this->database);
        return $user;
    }

    public static function actors(): array
    {
        return array(
            'anonymous password reset' => array(0, 4, array(3, 4)),
            'administrator changes another account' => array(84, 3, array(3, 4)),
            'own password change keeps current session' => array(42, 1, array(1, 3, 4)),
            'no current session' => array(0, null, array(3, 4)),
        );
    }

    /** @dataProvider actors */
    public function testRevocationTargetsTheAccount(int $actorId, ?int $sessionId, array $remainingSessions): void
    {
        $GLOBALS['gCurrentUserId'] = $actorId;
        $GLOBALS['gCurrentSession'] = $sessionId === null ? null : $this->session($sessionId);

        $this->assertTrue($this->user()->invalidateAllOtherLogins());
        $this->assertSame($remainingSessions, array_map('intval', array_column($this->database->fetchAll(TBL_SESSIONS), 'ses_id')));
        $this->assertSame(array(84), array_map('intval', array_column($this->database->fetchAll(TBL_AUTO_LOGIN), 'atl_usr_id')));
        $this->assertSame(0, (int)(new AutoLogin($this->database, '42:remember-me'))->getValue('atl_id'));
    }

    public function testUnsavedUserDoesNotInvalidateVisitorSessions(): void
    {
        $GLOBALS['gCurrentUserId'] = 0;
        $GLOBALS['gCurrentSession'] = $this->session(4);
        $this->assertTrue($this->user(0)->invalidateAllOtherLogins());
        $this->assertCount(4, $this->database->fetchAll(TBL_SESSIONS));
        $this->assertCount(2, $this->database->fetchAll(TBL_AUTO_LOGIN));
    }

    public static function rememberedSessions(): array
    {
        return array('ordinary session' => array(false), 'cached remember-me session' => array(true));
    }

    /** @dataProvider rememberedSessions */
    public function testRevokedSessionCannotRestoreTheUser(bool $remember): void
    {
        $stolenSession = $this->session(1, $remember);
        // Model completed revocation independently of User::invalidateAllOtherLogins(),
        // so this also detects the cached-token bug when the DELETEs already target the right user.
        $this->database->queryPrepared('DELETE FROM ' . TBL_SESSIONS . ' WHERE ses_usr_id = ?', array(42));
        $this->database->queryPrepared('DELETE FROM ' . TBL_AUTO_LOGIN . ' WHERE atl_usr_id = ?', array(42));

        // The cached object represents the PHP session loaded on the next request.
        $stolenSession->refresh();
        $this->assertSame(0, (int)$stolenSession->getValue('ses_usr_id'));
        $this->assertFalse($stolenSession->isValidLogin(42));
    }

    public function testValidCachedTokenCanRestoreAnExpiredDatabaseSession(): void
    {
        $session = $this->session(1, true);
        $this->database->queryPrepared('DELETE FROM ' . TBL_SESSIONS . ' WHERE ses_id = ?', array(1));
        $session->refresh();
        $this->assertSame(42, (int)$session->getValue('ses_usr_id'));
        $this->assertTrue($session->isValidLogin(42));
    }
}
