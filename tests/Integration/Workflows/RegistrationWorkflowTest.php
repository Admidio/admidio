<?php
/**
 * Registration Workflow Tests
 *
 * Tests the way from a self registration to a member: the visitor registers, confirms the address
 * through a validation id, and a member of a role with rol_approve_users accepts or rejects the
 * registration. Until it is accepted the user exists but is not valid and cannot log in.
 *
 * The tests run against the installed organization, which is the only one that has the roles and
 * the preferences the workflow reads. System mails are switched off, they are not part of what is
 * tested here.
 */

namespace Admidio\Tests\Integration\Workflows;

use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserRegistration;

class RegistrationWorkflowTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation.
     */
    private const ORG_ID = 1;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * The administrator of the installed organization, who is allowed to approve registrations.
     */
    private function administrator(): User
    {
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $usrId = (int) $this->getDatabase()->queryPrepared($sql, ['admin'])->fetchColumn();

        return new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);
    }

    /**
     * Register a visitor and return their user id and uuid.
     *
     * @return array{usr_id: int, usr_uuid: string}
     */
    private function register(string $loginName, string $lastName): array
    {
        $registration = new UserRegistration($this->getDatabase(), $GLOBALS['gProfileFields'], 0, self::ORG_ID);
        $registration->notSendEmail();
        $registration->setValue('usr_login_name', $loginName);
        $registration->setValue('LAST_NAME', $lastName);
        $registration->setPassword('A-Good-Password-1');
        $registration->save();

        return array(
            'usr_id' => (int) $registration->getValue('usr_id'),
            'usr_uuid' => (string) $registration->getValue('usr_uuid')
        );
    }

    /**
     * Read the registration back the way the module does, by uuid.
     *
     * The object that created the registration must not be reused: Entity::save() leaves the
     * new-record flag set, so UserRegistration::save() would run its "new registration" branch
     * again and set the user back to invalid.
     */
    private function readRegistration(string $userUuid): UserRegistration
    {
        $registration = new UserRegistration($this->getDatabase(), $GLOBALS['gProfileFields'], 0, self::ORG_ID);
        $registration->readDataByUuid($userUuid);
        $registration->notSendEmail();

        return $registration;
    }

    /**
     * Run a callback as the administrator, with system mails switched off.
     */
    private function asAdministrator(callable $callback)
    {
        return $this->withCurrentUser($this->administrator(), self::ORG_ID, true, function () use ($callback) {
            $GLOBALS['gSettingsManager']->set('system_notifications_enabled', 0);

            return $callback();
        });
    }

    /**
     * The registration row of a user.
     */
    private function registrationRow(int $usrId)
    {
        $sql = 'SELECT reg_org_id, reg_usr_id, reg_validation_id, reg_timestamp FROM ' . TBL_REGISTRATIONS . '
                 WHERE reg_usr_id = ?';

        return $this->getDatabase()->queryPrepared($sql, [$usrId])->fetch();
    }

    /**
     * The usr_valid flag as stored.
     */
    private function isValid(int $usrId): bool
    {
        $sql = 'SELECT usr_valid FROM ' . TBL_USERS . ' WHERE usr_id = ?';

        return (bool) $this->getDatabase()->queryPrepared($sql, [$usrId])->fetchColumn();
    }

    /**
     * Test that the roles the workflow depends on exist
     *
     * @testdox The installation provides a role for approving and a role for new members
     */
    public function testInstallationProvidesTheRolesTheWorkflowNeeds(): void
    {
        $db = $this->getDatabase();

        $sql = 'SELECT rol_name FROM ' . TBL_ROLES . ' INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                 WHERE rol_approve_users = true AND cat_org_id = ?';
        $this->assertNotEmpty($db->queryPrepared($sql, [self::ORG_ID])->fetchAll());

        // without a role flagged for registration a new member could not be assigned anywhere
        $sql = 'SELECT rol_name FROM ' . TBL_ROLES . ' INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                 WHERE rol_default_registration = true AND cat_org_id = ?';
        $this->assertNotEmpty($db->queryPrepared($sql, [self::ORG_ID])->fetchAll());
    }

    /**
     * Test that a registration leaves the user inactive
     *
     * @testdox A registration creates a user that is not yet valid
     */
    public function testRegistrationCreatesAUserThatIsNotYetValid(): void
    {
        $registered = $this->asAdministrator(fn () => $this->register('newcomer', 'Newcomer'));

        $this->assertGreaterThan(0, $registered['usr_id']);
        $this->assertFalse($this->isValid($registered['usr_id']));

        // the login name and the password are already stored, they are only not usable yet
        $sql = 'SELECT usr_login_name, usr_password FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$registered['usr_id']])->fetch();
        $this->assertEquals('newcomer', $row['usr_login_name']);
        $this->assertNotEmpty($row['usr_password']);
    }

    /**
     * Test that the registration is recorded per organization
     *
     * @testdox A registration is recorded for the organization with a validation id
     */
    public function testRegistrationIsRecordedWithAValidationId(): void
    {
        $registered = $this->asAdministrator(fn () => $this->register('newcomer', 'Newcomer'));

        $row = $this->registrationRow($registered['usr_id']);
        $this->assertNotFalse($row);
        $this->assertEquals(self::ORG_ID, (int) $row['reg_org_id']);
        $this->assertNotEmpty($row['reg_timestamp']);

        // the id the confirmation link carries
        $this->assertEquals(50, strlen($row['reg_validation_id']));
    }

    /**
     * Test that the validation id has to match
     *
     * @testdox A wrong validation id does not confirm the registration
     */
    public function testWrongValidationIdDoesNotConfirmTheRegistration(): void
    {
        $registered = $this->asAdministrator(fn () => $this->register('newcomer', 'Newcomer'));

        $confirmed = $this->asAdministrator(function () use ($registered) {
            return $this->readRegistration($registered['usr_uuid'])->validate('this-is-not-the-id');
        });

        $this->assertFalse($confirmed);

        // the registration is untouched and still waiting for its confirmation
        $this->assertNotEmpty($this->registrationRow($registered['usr_id'])['reg_validation_id']);
    }

    /**
     * Test that the right validation id confirms the registration
     *
     * @testdox Confirming the registration clears its validation id
     */
    public function testConfirmingTheRegistrationClearsTheValidationId(): void
    {
        $registered = $this->asAdministrator(fn () => $this->register('newcomer', 'Newcomer'));
        $validationId = $this->registrationRow($registered['usr_id'])['reg_validation_id'];

        $confirmed = $this->asAdministrator(function () use ($registered, $validationId) {
            return $this->readRegistration($registered['usr_uuid'])->validate($validationId);
        });

        $this->assertTrue($confirmed);
        $this->assertNull($this->registrationRow($registered['usr_id'])['reg_validation_id']);

        // confirming only proves the address, the user still waits for approval
        $this->assertFalse($this->isValid($registered['usr_id']));
    }

    /**
     * Test that accepting the registration activates the user
     *
     * @testdox Accepting a registration makes the user valid and removes the registration
     */
    public function testAcceptingARegistrationMakesTheUserValid(): void
    {
        $registered = $this->asAdministrator(fn () => $this->register('newcomer', 'Newcomer'));

        $this->asAdministrator(function () use ($registered) {
            $this->readRegistration($registered['usr_uuid'])->acceptRegistration();
        });

        $this->assertTrue($this->isValid($registered['usr_id']));
        $this->assertFalse($this->registrationRow($registered['usr_id']));
    }

    /**
     * Test that accepting assigns the default role
     *
     * @testdox Accepting a registration assigns the roles marked for new registrations
     */
    public function testAcceptingARegistrationAssignsTheDefaultRoles(): void
    {
        $db = $this->getDatabase();
        $registered = $this->asAdministrator(fn () => $this->register('newcomer', 'Newcomer'));

        // before the approval the user is a member of nothing
        $sql = 'SELECT COUNT(*) FROM ' . TBL_MEMBERS . ' WHERE mem_usr_id = ?';
        $this->assertEquals(0, (int) $db->queryPrepared($sql, [$registered['usr_id']])->fetchColumn());

        $this->asAdministrator(function () use ($registered) {
            $this->readRegistration($registered['usr_uuid'])->acceptRegistration();
        });

        $sql = 'SELECT rol_default_registration, mem_begin, mem_end FROM ' . TBL_MEMBERS . '
                  INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
                 WHERE mem_usr_id = ?';
        $memberships = $db->queryPrepared($sql, [$registered['usr_id']])->fetchAll();

        $this->assertCount(1, $memberships);
        $this->assertTrue((bool) $memberships[0]['rol_default_registration']);
        $this->assertEquals(DATE_NOW, $memberships[0]['mem_begin']);
        $this->assertEquals(DATE_MAX, $memberships[0]['mem_end']);
    }

    /**
     * Test that rejecting removes the visitor completely
     *
     * @testdox Rejecting a registration deletes the user that was never activated
     */
    public function testRejectingARegistrationDeletesTheInactiveUser(): void
    {
        $registered = $this->asAdministrator(fn () => $this->register('rejected', 'Rejected'));

        $this->asAdministrator(function () use ($registered) {
            $this->readRegistration($registered['usr_uuid'])->delete();
        });

        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $this->assertFalse($this->getDatabase()->queryPrepared($sql, [$registered['usr_id']])->fetch());
        $this->assertFalse($this->registrationRow($registered['usr_id']));
    }

    /**
     * Test that an active user survives a rejected registration
     *
     * @testdox Rejecting a registration keeps a user that is already active
     */
    public function testRejectingARegistrationKeepsAnActiveUser(): void
    {
        $db = $this->getDatabase();
        $registered = $this->asAdministrator(fn () => $this->register('active', 'Active'));

        // the user is already a member somewhere else, so only the registration may go
        $db->queryPrepared('UPDATE ' . TBL_USERS . ' SET usr_valid = ? WHERE usr_id = ?', [1, $registered['usr_id']]);

        $this->asAdministrator(function () use ($registered) {
            $this->readRegistration($registered['usr_uuid'])->delete();
        });

        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $this->assertNotFalse($db->queryPrepared($sql, [$registered['usr_id']])->fetch());
        $this->assertFalse($this->registrationRow($registered['usr_id']));
    }
}
