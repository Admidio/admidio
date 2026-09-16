<?php
/**
 * user_registration_accepted, dispatched by UserRegistration::acceptRegistration() and
 * RegistrationService::assignRegistration() through Database::registerAfterCommit() - the same
 * primitive EntityHookQueue uses, called directly because this event does not belong to one entity's
 * change set.
 *
 * UserRegistration and RegistrationService both need a full ProfileFields/User/settings stack to
 * construct, more than FakeDatabase sets up, so this executes the real mechanism they rely on -
 * Database::registerAfterCommit(), inherited by FakeDatabase unchanged - and the real
 * UserRegistration::ACCEPTED_BY_* constants, reproducing the exact call shape of both methods
 * (including CoreTasks::registrationApprove(), the one caller that wraps acceptRegistration() in a
 * transaction of its own) rather than the method bodies themselves.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use Admidio\Users\Entity\UserRegistration;

class RegistrationAcceptedTest extends EntityHookTestCase
{
    public function testFiresImmediatelyWithNoEnclosingTransaction(): void
    {
        $fired = array();
        Hooks::addAction('user_registration_accepted', function ($user, $method) use (&$fired) {
            $fired[] = array($user, $method);
        });

        $db = new FakeDatabase();
        // acceptRegistration()'s own transaction, exactly as confirmRegistration() calls it: nothing wraps it
        $db->startTransaction();
        $db->endTransaction();
        $db->registerAfterCommit(function () {
            Hooks::doAction('user_registration_accepted', 'user-1', UserRegistration::ACCEPTED_BY_APPROVAL);
        });

        $this->assertSame(array(array('user-1', UserRegistration::ACCEPTED_BY_APPROVAL)), $fired);
    }

    public function testWaitsForTheOutermostTransactionWhenNested(): void
    {
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
        $this->assertSame(array(), $fired, 'the event has not fired while the CLI command is still assigning roles');
        // ... the CLI command's own further work would run here, e.g. Role::startMembership() ...
        $db->endTransaction();                 // the CLI command's own commit, the real one

        $this->assertSame(
            array(array('user-2', UserRegistration::ACCEPTED_BY_APPROVAL)),
            $fired,
            'and fires once the outermost transaction actually commits'
        );
    }

    public function testARolledBackOuterTransactionDropsTheEventEntirely(): void
    {
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

        $this->assertSame(array(), $fired, 'it is not deferred or retried');
    }

    public function testAssignRegistrationAndAcceptRegistrationAreToldApartByTheMethodArgument(): void
    {
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

        $this->assertSame(array(UserRegistration::ACCEPTED_BY_ASSIGNMENT), $fired);
        $this->assertNotSame(UserRegistration::ACCEPTED_BY_APPROVAL, UserRegistration::ACCEPTED_BY_ASSIGNMENT);
    }
}
