<?php
/**
 * The updater cookie, the CSRF protection of the updater and the brake on its login.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater\Credentials;
use AdmidioCoreUpdater\Csrf;
use AdmidioCoreUpdater\Throttle;

final class CredentialsTest extends AdmidioTestCase
{
    private const UPDATE_ID = '0123456789abcdef0123456789abcdef';
    private const NOW = 1791500000;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    /**
     * @return array<string,mixed>
     */
    private static function state(): array
    {
        return array('updateId' => self::UPDATE_ID, 'credentials' => array(), 'csrfToken' => str_repeat('c', 64));
    }

    /**
     * @testdox The cookie carries the update and a secret, the state only the hash of the secret
     */
    public function testIssue(): void
    {
        $state = self::state();
        $cookie = Credentials::issue($state, self::NOW);

        $this->assertSame('ADMIDIO_club_adm_CORE_UPDATE', Credentials::getCookieName('ADMIDIO_club_adm'));
        $this->assertMatchesRegularExpression('/^' . self::UPDATE_ID . '\.[a-f0-9]{64}$/', $cookie);
        $this->assertCount(1, $state['credentials']);
        $this->assertSame(hash('sha256', substr($cookie, 33)), $state['credentials'][0]['hash']);
        $this->assertStringNotContainsString(substr($cookie, 33), json_encode($state));
        $this->assertSame(self::NOW + Credentials::LIFETIME, $state['credentials'][0]['expiresAt']);
    }

    /**
     * @testdox Every use keeps a credential alive for another day
     */
    public function testSlidingExpiry(): void
    {
        $state = self::state();
        $cookie = Credentials::issue($state, self::NOW);

        $later = self::NOW + Credentials::LIFETIME - 1;
        $this->assertTrue(Credentials::refresh($state, $cookie, $later));
        $this->assertSame($later + Credentials::LIFETIME, $state['credentials'][0]['expiresAt']);

        $this->assertTrue(Credentials::isValid($state, $cookie, $later + Credentials::LIFETIME));
        $this->assertFalse(Credentials::isValid($state, $cookie, $later + Credentials::LIFETIME + 1), 'a day without a request');
    }

    /**
     * @testdox An expired credential is dropped from the state
     */
    public function testExpiredCredentialIsDropped(): void
    {
        $state = self::state();
        $old = Credentials::issue($state, self::NOW);
        $new = Credentials::issue($state, self::NOW + Credentials::LIFETIME);

        $this->assertFalse(Credentials::refresh($state, $old, self::NOW + Credentials::LIFETIME + 1));
        $this->assertCount(1, $state['credentials']);
        $this->assertTrue(Credentials::isValid($state, $new, self::NOW + Credentials::LIFETIME + 1));
    }

    /**
     * @testdox Each browser has a credential of its own
     */
    public function testSeveralBrowsers(): void
    {
        $state = self::state();
        $first = Credentials::issue($state, self::NOW);
        $second = Credentials::issue($state, self::NOW + 10);

        $this->assertNotSame($first, $second);
        $this->assertTrue(Credentials::isValid($state, $first, self::NOW + 20));
        $this->assertTrue(Credentials::isValid($state, $second, self::NOW + 20));
    }

    /**
     * @testdox A cookie of another update, a forged and a malformed cookie are refused
     */
    public function testInvalidCookies(): void
    {
        $state = self::state();
        $cookie = Credentials::issue($state, self::NOW);
        $secret = substr($cookie, 33);

        foreach (array(
            'another update' => str_repeat('f', 32) . '.' . $secret,
            'another secret' => self::UPDATE_ID . '.' . str_repeat('0', 64),
            'no separator' => self::UPDATE_ID . $secret,
            'upper case' => strtoupper($cookie),
            'empty' => '',
            'array' => array($cookie),
            'null' => null
        ) as $case => $value) {
            $this->assertFalse(Credentials::isValid($state, $value, self::NOW), $case);
        }
    }

    /**
     * @testdox When the update is over every credential is revoked
     */
    public function testRevokeAll(): void
    {
        $state = self::state();
        $cookie = Credentials::issue($state, self::NOW);

        Credentials::revokeAll($state);

        $this->assertFalse(Credentials::isValid($state, $cookie, self::NOW));
    }

    /**
     * @testdox A request is only accepted with the CSRF token of its update
     */
    public function testCsrf(): void
    {
        $state = self::state();

        $this->assertTrue(Csrf::isValid($state, str_repeat('c', 64)));
        $this->assertFalse(Csrf::isValid($state, str_repeat('d', 64)));
        $this->assertFalse(Csrf::isValid($state, ''));
        $this->assertFalse(Csrf::isValid($state, null));
        $this->assertFalse(Csrf::isValid(array('updateId' => self::UPDATE_ID), str_repeat('c', 64)), 'a state without a token');
    }

    /**
     * @testdox The login form is only accepted with the token of its cookie
     */
    public function testLoginCsrf(): void
    {
        $token = str_repeat('a1', 32);

        $this->assertTrue(Csrf::isValidLogin($token, $token));
        $this->assertFalse(Csrf::isValidLogin($token, str_repeat('b2', 32)));
        $this->assertFalse(Csrf::isValidLogin(null, $token), 'no cookie, as on a request from another site');
        $this->assertFalse(Csrf::isValidLogin('', ''));
        $this->assertFalse(Csrf::isValidLogin('short', 'short'));
    }

    /**
     * @testdox Five failed logins within 15 minutes block the login for 15 minutes
     */
    public function testThrottle(): void
    {
        $file = sys_get_temp_dir() . '/adm_updater_throttle_' . uniqid() . '.json';
        $throttle = new Throttle($file);

        try {
            for ($attempt = 1; $attempt < Throttle::MAX_FAILURES; $attempt++) {
                $throttle->recordFailure(self::NOW + $attempt);
                $this->assertSame(0, $throttle->getBlockedUntil(self::NOW + $attempt), 'failure ' . $attempt);
            }

            $throttle->recordFailure(self::NOW + 100);
            $this->assertSame(self::NOW + 100 + Throttle::BLOCK, $throttle->getBlockedUntil(self::NOW + 101));
            $this->assertSame(0, $throttle->getBlockedUntil(self::NOW + 100 + Throttle::BLOCK), 'the block ends');
        } finally {
            @unlink($file);
        }
    }

    /**
     * @testdox Failures older than 15 minutes and failures before a successful login do not count
     */
    public function testThrottleWindowAndReset(): void
    {
        $file = sys_get_temp_dir() . '/adm_updater_throttle_' . uniqid() . '.json';
        $throttle = new Throttle($file);

        try {
            for ($attempt = 0; $attempt < Throttle::MAX_FAILURES - 1; $attempt++) {
                $throttle->recordFailure(self::NOW);
            }
            $throttle->recordFailure(self::NOW + Throttle::WINDOW);
            $this->assertSame(0, $throttle->getBlockedUntil(self::NOW + Throttle::WINDOW), 'the first four are too old');

            for ($attempt = 0; $attempt < Throttle::MAX_FAILURES - 2; $attempt++) {
                $throttle->recordFailure(self::NOW + Throttle::WINDOW + 1);
            }
            $throttle->reset();
            $throttle->recordFailure(self::NOW + Throttle::WINDOW + 2);
            $this->assertSame(0, $throttle->getBlockedUntil(self::NOW + Throttle::WINDOW + 2), 'reset by a successful login');
        } finally {
            @unlink($file);
        }
    }
}
