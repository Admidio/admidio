<?php
/**
 * user_registration_accepted, dispatched by UserRegistration::acceptRegistration() and
 * RegistrationService::assignRegistration() through Database::registerAfterCommit() - the same
 * primitive EntityHookQueue uses, called directly because this event does not belong to one entity's
 * change set.
 *
 * UserRegistration and RegistrationService both need a full ProfileFields/User/settings stack to
 * construct, more than this harness sets up elsewhere, so this executes the real mechanism they rely
 * on - Database::registerAfterCommit(), inherited by FakeDatabase unchanged - and the real
 * UserRegistration::ACCEPTED_BY_* constants, reproducing the exact call shape of both methods
 * (including CoreTasks::registrationApprove(), the one caller that wraps acceptRegistration() in a
 * transaction of its own) rather than the method bodies themselves.
 */
require __DIR__ . '/bootstrap.php';

use Admidio\Hooks\Hooks;
use Admidio\Tests\Hooks\FakeDatabase;
use Admidio\Users\Entity\UserRegistration;

// ---------------------------------------------------- outside any transaction, it fires immediately
Hooks::reset();
$fired = array();
Hooks::addAction('user_registration_accepted', function ($user, $method) use (&$fired) {
    $fired[] = array($user, $method);
});

$db = new FakeDatabase();
// acceptRegistration()'s own transaction, exactly as confirmRegistration() calls it: nothing wraps it
$db->startTransaction();
$db->endTransaction();
$db->registerAfterCommit(function () use (&$dispatched) {
    Hooks::doAction('user_registration_accepted', 'user-1', UserRegistration::ACCEPTED_BY_APPROVAL);
});
check(
    'with no enclosing transaction the event fires as soon as acceptRegistration() commits',
    $fired === array(array('user-1', UserRegistration::ACCEPTED_BY_APPROVAL)),
    var_export($fired, true)
);

// -------------------------------------- CoreTasks::registrationApprove() wraps it in its own transaction
Hooks::reset();
$fired = array();
Hooks::addAction('user_registration_accepted', function ($user, $method) use (&$fired) {
    $fired[] = array($user, $method);
});

$db = new FakeDatabase();
$db->startTransaction();               // the CLI command's own transaction
    $db->startTransaction();           // acceptRegistration()'s transaction, now nested
    $db->endTransaction();             // only decrements, nothing has really committed yet
    $db->registerAfterCommit(function () {
        Hooks::doAction('user_registration_accepted', 'user-2', UserRegistration::ACCEPTED_BY_APPROVAL);
    });
    check('the event has not fired while the CLI command is still assigning roles', $fired === array());
    // ... the CLI command's own further work would run here, e.g. Role::startMembership() ...
$db->endTransaction();                 // the CLI command's own commit, the real one
check(
    'and fires once the outermost transaction actually commits',
    $fired === array(array('user-2', UserRegistration::ACCEPTED_BY_APPROVAL)),
    var_export($fired, true)
);

// ------------------------------------------------------- a rollback of the outer transaction drops it
Hooks::reset();
$fired = array();
Hooks::addAction('user_registration_accepted', function ($user, $method) use (&$fired) {
    $fired[] = array($user, $method);
});

$db = new FakeDatabase();
$db->startTransaction();
    $db->startTransaction();
    $db->endTransaction();
    $db->registerAfterCommit(function () {
        Hooks::doAction('user_registration_accepted', 'user-3', UserRegistration::ACCEPTED_BY_APPROVAL);
    });
$db->rollback();
check('a rolled back outer transaction drops the event entirely, it is not deferred or retried', $fired === array());

// --------------------------------------------------------------- assignRegistration()'s own method name
Hooks::reset();
$fired = array();
Hooks::addAction('user_registration_accepted', function ($user, $method) use (&$fired) {
    $fired[] = $method;
});
$db = new FakeDatabase();
$db->startTransaction();
$db->endTransaction();
$db->registerAfterCommit(function () {
    Hooks::doAction('user_registration_accepted', 'user-4', UserRegistration::ACCEPTED_BY_ASSIGNMENT);
});
check(
    'assignRegistration() and acceptRegistration() are told apart by the method argument',
    $fired === array(UserRegistration::ACCEPTED_BY_ASSIGNMENT)
    && UserRegistration::ACCEPTED_BY_ASSIGNMENT !== UserRegistration::ACCEPTED_BY_APPROVAL,
    implode(',', $fired)
);

echo "\n";
exit(testSummary());
