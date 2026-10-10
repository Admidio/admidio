<?php
/**
 * The checks of the updater login that need no database: the password formats Admidio stores, the
 * codes of an authenticator app and the brake on failed logins.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater as U;

final class LoginTest extends AdmidioTestCase
{
    /**
     * The secret of the RFC 6238 test vectors for SHA-1, "12345678901234567890", in base32.
     */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    /**
     * @testdox A password is checked against every hash format Admidio writes
     */
    public function testPasswordFormats(): void
    {
        $hashes = array(
            'bcrypt' => password_hash('correct horse', PASSWORD_BCRYPT),
            'SHA-512' => crypt('correct horse', '$6$rounds=5000$' . 'abcdefghijklmnop' . '$')
        );
        if (defined('PASSWORD_ARGON2ID')) {
            $hashes['Argon2id'] = password_hash('correct horse', PASSWORD_ARGON2ID);
        }

        foreach ($hashes as $format => $hash) {
            $this->assertSame('valid', U\verifyPassword('correct horse', $hash), $format);
            $this->assertSame('invalid', U\verifyPassword('wrong horse', $hash), $format);
        }
    }

    /**
     * @testdox An MD5 hash of a very old version is not accepted, but recognised
     *
     * The administrator is told to log in to Admidio once, which converts the hash, instead of being
     * told that the password is wrong.
     */
    public function testMd5IsOutdated(): void
    {
        $this->assertSame('outdated', U\verifyPassword('correct horse', md5('correct horse')));
        $this->assertSame('outdated', U\verifyPassword('correct horse', strtoupper(md5('correct horse'))));
        $this->assertSame('invalid', U\verifyPassword('wrong horse', md5('correct horse')));
    }

    /**
     * @testdox A hash of an unknown format matches no password
     */
    public function testUnknownFormat(): void
    {
        $this->assertSame('invalid', U\verifyPassword('', ''));
        $this->assertSame('invalid', U\verifyPassword('secret', 'secret'));
        $this->assertSame('invalid', U\verifyPassword('x', '$P$portablephpass'));
    }

    /**
     * @return array<string,array{0: int, 1: string}>
     */
    public static function rfc6238Vectors(): array
    {
        // RFC 6238, appendix B, SHA-1: time in seconds => 8-digit code
        return array(
            '59' => array(59, '94287082'),
            '1111111109' => array(1111111109, '07081804'),
            '1111111111' => array(1111111111, '14050471'),
            '1234567890' => array(1234567890, '89005924'),
            '2000000000' => array(2000000000, '69279037'),
            '20000000000' => array(20000000000, '65353130')
        );
    }

    /**
     * @testdox The one-time password matches the test vectors of RFC 6238
     * @dataProvider rfc6238Vectors
     */
    public function testRfc6238(int $time, string $code): void
    {
        $key = U\decodeBase32(self::RFC_SECRET);

        $this->assertSame('12345678901234567890', $key);
        $this->assertSame($code, U\totpCode($key, intdiv($time, 30), 8));
        $this->assertTrue(U\verifyTotp(self::RFC_SECRET, substr($code, -6), $time), 'the 6 digits Admidio uses');
    }

    /**
     * @testdox A code of the previous and of the next 30 seconds is accepted, older ones are not
     */
    public function testTotpWindow(): void
    {
        $key = U\decodeBase32(self::RFC_SECRET);
        $now = 1111111111;
        $counter = intdiv($now, 30);

        $this->assertTrue(U\verifyTotp(self::RFC_SECRET, U\totpCode($key, $counter - 1, 6), $now));
        $this->assertTrue(U\verifyTotp(self::RFC_SECRET, U\totpCode($key, $counter + 1, 6), $now));
        $this->assertFalse(U\verifyTotp(self::RFC_SECRET, U\totpCode($key, $counter - 2, 6), $now));
        $this->assertFalse(U\verifyTotp(self::RFC_SECRET, U\totpCode($key, $counter + 2, 6), $now));
    }

    /**
     * @testdox Without two-factor authentication no code is needed; with it, a malformed code fails
     */
    public function testTotpInput(): void
    {
        $this->assertTrue(U\verifyTotp('', '', 0), 'not set up');
        $this->assertTrue(U\verifyTotp('', 'anything', 0), 'not set up');

        $this->assertFalse(U\verifyTotp(self::RFC_SECRET, '', 59));
        $this->assertFalse(U\verifyTotp(self::RFC_SECRET, '28708', 59), 'too short');
        $this->assertFalse(U\verifyTotp(self::RFC_SECRET, 'abcdef', 59));
        $this->assertTrue(U\verifyTotp(self::RFC_SECRET, '287 082', 59), 'spaces as an app shows them');
        $this->assertFalse(U\verifyTotp('not base32!', '287082', 59), 'a broken secret matches nothing');
    }

    /**
     * @testdox Base32 is decoded case-insensitively and without padding
     */
    public function testBase32(): void
    {
        $this->assertSame('foobar', U\decodeBase32('MZXW6YTBOI======'));
        $this->assertSame('foobar', U\decodeBase32('mzxw6ytboi'));
        $this->assertSame('', U\decodeBase32('MZXW1'), '1 is no base32 character');
        $this->assertSame('', U\decodeBase32(''));
    }

    /**
     * @testdox Five failed logins within 15 minutes block the login for 15 minutes
     */
    public function testThrottle(): void
    {
        $file = sys_get_temp_dir() . '/adm_updater_throttle_' . uniqid() . '.json';
        $now = 1791500000;

        try {
            for ($attempt = 1; $attempt < U\MAX_LOGIN_FAILURES; $attempt++) {
                U\recordLoginFailure($file, $now + $attempt);
                $this->assertSame(0, U\loginBlockedUntil($file, $now + $attempt), 'failure ' . $attempt);
            }

            U\recordLoginFailure($file, $now + 100);
            $this->assertSame($now + 100 + U\LOGIN_BLOCK, U\loginBlockedUntil($file, $now + 101));
            $this->assertSame(0, U\loginBlockedUntil($file, $now + 100 + U\LOGIN_BLOCK), 'the block ends');
        } finally {
            @unlink($file);
        }
    }

    /**
     * @testdox Failures older than 15 minutes do not count
     */
    public function testThrottleWindow(): void
    {
        $file = sys_get_temp_dir() . '/adm_updater_throttle_' . uniqid() . '.json';
        $now = 1791500000;

        try {
            for ($attempt = 0; $attempt < U\MAX_LOGIN_FAILURES - 1; $attempt++) {
                U\recordLoginFailure($file, $now);
            }
            U\recordLoginFailure($file, $now + U\LOGIN_WINDOW);
            $this->assertSame(0, U\loginBlockedUntil($file, $now + U\LOGIN_WINDOW), 'the first four are too old');
        } finally {
            @unlink($file);
        }
    }
}
