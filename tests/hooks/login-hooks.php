<?php
/**
 * The login hooks. Two things are checked here:
 *
 * 1. the control flow around them, through a stand-in that carries the try/catch of
 *    ModuleLogin::checkLogin() verbatim - the real one needs a session, a form, a database and a user;
 * 2. that no login hook is ever handed the password. That one is checked against the real source file,
 *    because it is the property that matters and a stand-in could not prove it.
 */
require __DIR__ . '/bootstrap.php';

use Admidio\Hooks\Hooks;

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
        } catch (\Throwable $exception) {
            Hooks::doActionCatchErrors('login_failed', $loginName, $exception->getMessage(), $organization);

            throw $exception;
        }
    }
}

function aLogin(callable $authenticate): ProbeLogin
{
    Hooks::reset();
    $login = new ProbeLogin();
    $login->authenticate = $authenticate;

    return $login;
}

// ---------------------------------------------------------------- an attempt can be refused outright

$reached = false;
$login = aLogin(function () use (&$reached) {
    $reached = true;
    return true;
});
Hooks::addAction('login_attempt', function (string $loginName) {
    if ($loginName === 'blocked') {
        throw new \RuntimeException('too many attempts');
    }
});

$refused = '';
try {
    $login->checkLogin('blocked', 'example');
} catch (\Throwable $exception) {
    $refused = $exception->getMessage();
}
check('a callback of login_attempt can refuse the attempt', $refused === 'too many attempts', $refused);
check('and the password is then never checked at all', !$reached);

check('an attempt that nobody objects to goes through', $login->checkLogin('jdoe', 'example') === true);
check('and it did reach the authentication', $reached);

// ----------------------------------------------------------------------- a failure keeps its reason

$login = aLogin(function () {
    throw new \RuntimeException('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT');
});
$failures = array();
Hooks::addAction('login_failed', function (string $loginName, string $reason) use (&$failures) {
    $failures[] = $loginName . '/' . $reason;
});

$reported = '';
try {
    $login->checkLogin('ghost', 'example');
} catch (\Throwable $exception) {
    $reported = $exception->getMessage();
}
check('login_failed is told who failed and why', $failures === array('ghost/SYS_LOGIN_USERNAME_PASSWORD_INCORRECT'), implode(',', $failures));
check('and the original reason is still what the user is told', $reported === 'SYS_LOGIN_USERNAME_PASSWORD_INCORRECT', $reported);

// a name that belongs to nobody is refused before a User object exists, and is still an attempt
$login = aLogin(function () {
    throw new \RuntimeException('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT');
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
} catch (\Throwable) {
    // expected
}
check(
    'an unknown user name is reported as an attempt and as a failure',
    $attempts === array('nobody-has-this-name', 'failed:nobody-has-this-name'),
    implode(',', $attempts)
);

// -------------------------------------------------- a broken diagnostic must not change the outcome

$login = aLogin(function () {
    throw new \RuntimeException('SYS_LOGIN_MAX_INVALID_LOGIN');
});
Hooks::addAction('login_failed', function () {
    throw new \LogicException('the audit plugin is broken');
});

$reported = '';
try {
    $login->checkLogin('jdoe', 'example');
} catch (\Throwable $exception) {
    $reported = $exception->getMessage();
}
check(
    'a listener that throws cannot replace the reason the login was refused',
    $reported === 'SYS_LOGIN_MAX_INVALID_LOGIN',
    $reported
);

// ------------------------------------------------------- the password never leaves the login module

$source = file_get_contents(__DIR__ . '/../../system/classes/ModuleLogin.php');
$dispatches = array();
foreach (explode("\n", $source) as $line) {
    if (str_contains($line, 'Hooks::doAction')) {
        $dispatches[] = trim($line);
    }
}

// four sites for three hooks: login_failed is dispatched both when a check throws and, defensively,
// when User::checkLogin() returns false - which it cannot do today, every check of it throws instead
check('the login module dispatches the login hooks and nothing else', count($dispatches) === 4, implode(' | ', $dispatches));

$leaks = array_values(array_filter($dispatches, function (string $line) {
    return str_contains($line, 'Password') || str_contains($line, 'password');
}));
check('and none of them is handed the password', $leaks === array(), implode(' | ', $leaks));

$names = array();
foreach ($dispatches as $line) {
    if (preg_match("/doAction(?:CatchErrors)?\('([a-z_]+)'/", $line, $matches) === 1) {
        $names[] = $matches[1];
    }
}
$names = array_values(array_unique($names));
sort($names);
check('and they are the ones this step promised', $names === array('login_attempt', 'login_failed', 'login_succeeded'), implode(',', $names));

exit(testSummary());
