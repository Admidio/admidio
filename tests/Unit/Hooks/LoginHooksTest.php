<?php
/**
 * The login hooks. Two things are checked here:
 *
 * 1. the control flow around them, through a stand-in that carries the try/catch of
 *    ModuleLogin::checkLogin() verbatim - the real one needs a session, a form, a database and a user;
 * 2. that no login hook is ever handed the password. That one is checked against the real source
 *    file, because it is the property that matters and a stand-in could not prove it.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Tests\Support\AdmidioTestCase;
use LogicException;
use RuntimeException;
use Throwable;

/** The head of ModuleLogin::checkLogin(), with the lookup replaced by something the test controls. */
class ProbeLogin
{
    /** @var callable what authenticate() does */
    public $authenticate;

    public function checkLogin(string $loginName, string $organization): bool
    {
        Hooks::doAction('login_attempt', $loginName, $organization);

        try {
            return ($this->authenticate)($loginName, $organization);
        } catch (Throwable $exception) {
            Hooks::doActionCatchErrors('login_failed', $loginName, $exception->getMessage(), $organization);

            throw $exception;
        }
    }
}

class LoginHooksTest extends AdmidioTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Hooks::reset();
    }

    private function aLogin(callable $authenticate): ProbeLogin
    {
        Hooks::reset();
        $login = new ProbeLogin();
        $login->authenticate = $authenticate;

        return $login;
    }

    public function testAnAttemptCanBeRefusedOutrightBeforeThePasswordIsEverChecked(): void
    {
        $reached = false;
        $login = $this->aLogin(function () use (&$reached) {
            $reached = true;
            return true;
        });
        Hooks::addAction('login_attempt', function (string $loginName) {
            if ($loginName === 'blocked') {
                throw new RuntimeException('too many attempts');
            }
        });

        $refused = '';
        try {
            $login->checkLogin('blocked', 'example');
        } catch (Throwable $exception) {
            $refused = $exception->getMessage();
        }
        $this->assertSame('too many attempts', $refused, 'a callback of login_attempt can refuse the attempt');
        $this->assertFalse($reached, 'and the password is then never checked at all');

        $this->assertTrue($login->checkLogin('jdoe', 'example'), 'an attempt that nobody objects to goes through');
        $this->assertTrue($reached, 'and it did reach the authentication');
    }

    public function testAFailureKeepsItsReason(): void
    {
        $login = $this->aLogin(function () {
            throw new RuntimeException('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT');
        });
        $failures = array();
        Hooks::addAction('login_failed', function (string $loginName, string $reason) use (&$failures) {
            $failures[] = $loginName . '/' . $reason;
        });

        $reported = '';
        try {
            $login->checkLogin('ghost', 'example');
        } catch (Throwable $exception) {
            $reported = $exception->getMessage();
        }
        $this->assertSame(array('ghost/SYS_LOGIN_USERNAME_PASSWORD_INCORRECT'), $failures, 'login_failed is told who failed and why');
        $this->assertSame('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT', $reported, 'and the original reason is still what the user is told');
    }

    public function testAnUnknownUserNameIsReportedAsAnAttemptAndAsAFailure(): void
    {
        $login = $this->aLogin(function () {
            throw new RuntimeException('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT');
        });
        $attempts = array();
        Hooks::addAction('login_attempt', function (string $loginName) use (&$attempts) {
            $attempts[] = $loginName;
        });
        Hooks::addAction('login_failed', function (string $loginName) use (&$attempts) {
            $attempts[] = 'failed:' . $loginName;
        });
        try {
            $login->checkLogin('nobody-has-this-name', 'example');
        } catch (Throwable) {
            // expected
        }

        $this->assertSame(array('nobody-has-this-name', 'failed:nobody-has-this-name'), $attempts);
    }

    public function testABrokenDiagnosticMustNotChangeTheOutcome(): void
    {
        $login = $this->aLogin(function () {
            throw new RuntimeException('SYS_LOGIN_MAX_INVALID_LOGIN');
        });
        Hooks::addAction('login_failed', function () {
            throw new LogicException('the audit plugin is broken');
        });

        $reported = '';
        try {
            $login->checkLogin('jdoe', 'example');
        } catch (Throwable $exception) {
            $reported = $exception->getMessage();
        }
        $this->assertSame(
            'SYS_LOGIN_MAX_INVALID_LOGIN',
            $reported,
            'a listener that throws cannot replace the reason the login was refused'
        );
    }

    public function testThePasswordNeverLeavesTheLoginModule(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/system/classes/ModuleLogin.php');
        $dispatches = array();
        foreach (explode("\n", $source) as $line) {
            if (str_contains($line, 'Hooks::doAction')) {
                $dispatches[] = trim($line);
            }
        }

        // four sites for three hooks: login_failed is dispatched both when a check throws and,
        // defensively, when User::checkLogin() returns false - which it cannot do today, every check
        // of it throws instead
        $this->assertCount(4, $dispatches, 'the login module dispatches the login hooks and nothing else');

        $leaks = array_values(array_filter($dispatches, function (string $line) {
            return str_contains($line, 'Password') || str_contains($line, 'password');
        }));
        $this->assertSame(array(), $leaks, 'and none of them is handed the password');

        $names = array();
        foreach ($dispatches as $line) {
            if (preg_match("/doAction(?:CatchErrors)?\('([a-z_]+)'/", $line, $matches) === 1) {
                $names[] = $matches[1];
            }
        }
        $names = array_values(array_unique($names));
        sort($names);
        $this->assertSame(array('login_attempt', 'login_failed', 'login_succeeded'), $names, 'and they are the ones this step promised');
    }
}
