<?php
/**
 * ChangeNotification as a listener on the committed change sets. The listener under test is the real
 * one and so are Entity, EntityHookQueue and the SQLite transactions underneath; the three tables of
 * a person are created with the columns of install/db_scripts/db.sql, and $gProfileFields,
 * $gSettingsManager and $gL10n are stubs, because the mail is not what is being tested here - what
 * the listener hears and what it makes of it is.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Infrastructure\ChangeNotification;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use DateTime;

function changeNotificationColumn(string $type, bool $key = false, bool $serial = false): array
{
    return array('type' => $type, 'null' => true, 'key' => $key, 'serial' => $serial, 'default' => null);
}

/** adm_users, with the hook ID, the sensitive columns and the ignored columns of the real User. */
class ChangeNotificationTestUser extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TABLE_PREFIX . '_users', 'usr', $id);
    }

    public function getHookId(): ?string
    {
        return 'user';
    }

    public function getSensitiveHookColumns(): array
    {
        return array('usr_password', 'usr_tfa_secret', 'usr_photo');
    }

    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(), array(
            'usr_uuid', 'usr_pw_reset_id', 'usr_pw_reset_timestamp', 'usr_last_login',
            'usr_actual_login', 'usr_number_login', 'usr_date_invalid', 'usr_number_invalid',
            'usr_valid'
        ));
    }

    /** The dependent records the real User::delete() removes, in the same way. */
    public function delete(): bool
    {
        $usrId = $this->getValue('usr_id');
        $this->db->startTransaction();
        $this->deleteDependentRecords(new ChangeNotificationTestMembership($this->db), array('mem_id'), 'mem_usr_id = ?', array($usrId));
        $this->deleteDependentRecords(new ChangeNotificationTestUserData($this->db), array('usd_id'), 'usd_usr_id = ?', array($usrId));
        $returnValue = parent::delete();
        $this->db->endTransaction();

        return $returnValue;
    }
}

/** adm_user_data, one profile field value of one user. */
class ChangeNotificationTestUserData extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TABLE_PREFIX . '_user_data', 'usd', $id);
    }

    public function getHookId(): ?string
    {
        return 'user_data';
    }
}

/** adm_members, one membership of one user in one role. */
class ChangeNotificationTestMembership extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TABLE_PREFIX . '_members', 'mem', $id);
    }

    public function getHookId(): ?string
    {
        return 'membership';
    }
}

/** adm_user_fields, which takes the values of everybody with it when it is deleted. */
class ChangeNotificationTestProfileField extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TABLE_PREFIX . '_user_fields', 'usf', $id);
    }

    public function getHookId(): ?string
    {
        return 'profile_field';
    }

    public function delete(): bool
    {
        $usfId = $this->getValue('usf_id');
        $this->db->startTransaction();
        $this->deleteDependentRecords(new ChangeNotificationTestUserData($this->db), array('usd_id'), 'usd_usf_id = ?', array($usfId));
        $returnValue = parent::delete();
        $this->db->endTransaction();

        return $returnValue;
    }
}

/** adm_roles, which takes the memberships of everybody with it when it is deleted. */
class ChangeNotificationTestRole extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TABLE_PREFIX . '_roles', 'rol', $id);
    }

    public function getHookId(): ?string
    {
        return 'role';
    }

    public function delete(): bool
    {
        $rolId = $this->getValue('rol_id');
        $this->db->startTransaction();
        $this->deleteDependentRecords(new ChangeNotificationTestMembership($this->db), array('mem_id'), 'mem_rol_id = ?', array($rolId));
        $returnValue = parent::delete();
        $this->db->endTransaction();

        return $returnValue;
    }
}

/** Reads what the listener collected, which is what this test is about. */
class ProbeNotification extends ChangeNotification
{
    public function collected(): array
    {
        return $this->changes;
    }

    public function reportable(int $userID): bool
    {
        return array_key_exists($userID, $this->changes) && $this->isReportable($this->changes[$userID]);
    }
}

/** The profile fields of the fixture, as much of ProfileFields as the listener asks for. */
class ChangeNotificationStubProfileFields
{
    private array $fields = array(
        1 => array('intern' => 'LAST_NAME', 'name' => 'Last name', 'type' => 'TEXT'),
        2 => array('intern' => 'FIRST_NAME', 'name' => 'First name', 'type' => 'TEXT'),
        3 => array('intern' => 'BIRTHDAY', 'name' => 'Birthday', 'type' => 'DATE')
    );

    public function getPropertyById(int $fieldId, string $column, string $format = ''): array|string
    {
        if (!array_key_exists($fieldId, $this->fields)) {
            return '';
        }

        return ($column === 'usf_name_intern') ? $this->fields[$fieldId]['intern'] : $this->fields[$fieldId]['name'];
    }

    public function getProperty(string $fieldNameIntern, string $column, string $format = ''): mixed
    {
        foreach ($this->fields as $fieldId => $field) {
            if ($field['intern'] === $fieldNameIntern) {
                return $fieldId;
            }
        }

        return 0;
    }

    public function formatValue(string $fieldNameIntern, mixed $value, string $format = ''): mixed
    {
        foreach ($this->fields as $field) {
            if ($field['intern'] === $fieldNameIntern && $field['type'] === 'DATE' && $value !== '') {
                $date = DateTime::createFromFormat('Y-m-d', (string)$value);
                if ($date !== false) {
                    return $date->format('d.m.Y');
                }
            }
        }

        return $value;
    }
}

class ChangeNotificationStubSettingsManager
{
    public function has(string $name): bool
    {
        return true;
    }

    public function getBool(string $name): bool
    {
        // no mail is sent in this test, the collected changes are what is checked
        return false;
    }

    public function getString(string $name): string
    {
        return 'd.m.Y';
    }
}

class ChangeNotificationStubLanguage
{
    public function get(string $textId, array $params = array()): string
    {
        return $textId;
    }
}

class ChangeNotificationTest extends EntityHookTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!defined('TBL_USERS')) {
            define('TBL_USERS', TABLE_PREFIX . '_users');
        }
        if (!defined('TBL_USER_DATA')) {
            define('TBL_USER_DATA', TABLE_PREFIX . '_user_data');
        }
        if (!defined('TBL_MEMBERS')) {
            define('TBL_MEMBERS', TABLE_PREFIX . '_members');
        }
        if (!defined('TBL_ROLES')) {
            define('TBL_ROLES', TABLE_PREFIX . '_roles');
        }
        if (!defined('TBL_CATEGORIES')) {
            define('TBL_CATEGORIES', TABLE_PREFIX . '_categories');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['gProfileFields'] = new ChangeNotificationStubProfileFields();
        $GLOBALS['gSettingsManager'] = new ChangeNotificationStubSettingsManager();
        $GLOBALS['gL10n'] = new ChangeNotificationStubLanguage();
        $GLOBALS['gCurrentUser'] = null;
    }

    private function newDatabase(): FakeDatabase
    {
        $db = new FakeDatabase();
        $db->createTable(TBL_USERS, array(
            'usr_id' => changeNotificationColumn('integer', true, true),
            'usr_uuid' => changeNotificationColumn('varchar(36)'),
            'usr_login_name' => changeNotificationColumn('varchar(254)'),
            'usr_password' => changeNotificationColumn('varchar(255)'),
            'usr_tfa_secret' => changeNotificationColumn('varchar(255)'),
            'usr_photo' => changeNotificationColumn('bytea'),
            'usr_text' => changeNotificationColumn('text'),
            'usr_last_login' => changeNotificationColumn('timestamp'),
            'usr_actual_login' => changeNotificationColumn('timestamp'),
            'usr_number_login' => changeNotificationColumn('integer'),
            'usr_usr_id_create' => changeNotificationColumn('integer'),
            'usr_timestamp_create' => changeNotificationColumn('timestamp'),
            'usr_usr_id_change' => changeNotificationColumn('integer'),
            'usr_timestamp_change' => changeNotificationColumn('timestamp'),
            'usr_valid' => changeNotificationColumn('boolean')
        ));
        $db->createTable(TBL_USER_DATA, array(
            'usd_id' => changeNotificationColumn('integer', true, true),
            'usd_usr_id' => changeNotificationColumn('integer'),
            'usd_usf_id' => changeNotificationColumn('integer'),
            'usd_value' => changeNotificationColumn('varchar(4000)')
        ));
        $db->createTable(TBL_MEMBERS, array(
            'mem_id' => changeNotificationColumn('integer', true, true),
            'mem_rol_id' => changeNotificationColumn('integer'),
            'mem_usr_id' => changeNotificationColumn('integer'),
            'mem_uuid' => changeNotificationColumn('varchar(36)'),
            'mem_begin' => changeNotificationColumn('date'),
            'mem_end' => changeNotificationColumn('date'),
            'mem_leader' => changeNotificationColumn('boolean'),
            'mem_usr_id_create' => changeNotificationColumn('integer'),
            'mem_timestamp_create' => changeNotificationColumn('timestamp'),
            'mem_usr_id_change' => changeNotificationColumn('integer'),
            'mem_timestamp_change' => changeNotificationColumn('timestamp')
        ));
        $db->createTable(TABLE_PREFIX . '_user_fields', array(
            'usf_id' => changeNotificationColumn('integer', true, true),
            'usf_name' => changeNotificationColumn('varchar(100)')
        ));
        $db->createTable(TBL_ROLES, array(
            'rol_id' => changeNotificationColumn('integer', true, true),
            'rol_cat_id' => changeNotificationColumn('integer'),
            'rol_name' => changeNotificationColumn('varchar(100)')
        ));
        $db->createTable(TBL_CATEGORIES, array(
            'cat_id' => changeNotificationColumn('integer', true, true),
            'cat_name_intern' => changeNotificationColumn('varchar(110)')
        ));

        // one ordinary role and one that belongs to an event
        $db->queryPrepared('INSERT INTO ' . TBL_CATEGORIES . ' (cat_id, cat_name_intern) VALUES (1, ?)', array('COMMON'));
        $db->queryPrepared('INSERT INTO ' . TBL_CATEGORIES . ' (cat_id, cat_name_intern) VALUES (2, ?)', array('EVENTS'));
        $db->queryPrepared('INSERT INTO ' . TBL_ROLES . ' (rol_id, rol_cat_id, rol_name) VALUES (1, 1, ?)', array('Choir'));
        $db->queryPrepared('INSERT INTO ' . TBL_ROLES . ' (rol_id, rol_cat_id, rol_name) VALUES (2, 2, ?)', array('Summer camp'));
        $db->queryPrepared('INSERT INTO ' . TABLE_PREFIX . '_user_fields (usf_id, usf_name) VALUES (1, ?)', array('Last name'));
        $db->queryPrepared('INSERT INTO ' . TABLE_PREFIX . '_user_fields (usf_id, usf_name) VALUES (2, ?)', array('First name'));
        $db->queryPrepared('INSERT INTO ' . TABLE_PREFIX . '_user_fields (usf_id, usf_name) VALUES (3, ?)', array('Birthday'));

        $GLOBALS['gDb'] = $db;

        return $db;
    }

    private function newNotification(): ProbeNotification
    {
        return new ProbeNotification();
    }

    /** A saved user with a last name, a first name and a membership. */
    private function aUser(FakeDatabase $db, string $loginName = 'jdoe'): ChangeNotificationTestUser
    {
        $user = new ChangeNotificationTestUser($db);
        $user->setValue('usr_login_name', $loginName);
        $user->setValue('usr_valid', true);
        $user->save();

        foreach (array(1 => 'Doe', 2 => 'John') as $fieldId => $value) {
            $value_ = new ChangeNotificationTestUserData($db);
            $value_->setValue('usd_usr_id', $user->getValue('usr_id'));
            $value_->setValue('usd_usf_id', $fieldId);
            $value_->setValue('usd_value', $value);
            $value_->save();
        }

        return $user;
    }

    private function aMembership(FakeDatabase $db, ChangeNotificationTestUser $user, int $roleId = 1): ChangeNotificationTestMembership
    {
        $membership = new ChangeNotificationTestMembership($db);
        $membership->setValue('mem_rol_id', $roleId);
        $membership->setValue('mem_usr_id', $user->getValue('usr_id'));
        $membership->setValue('mem_begin', '2026-01-01');
        $membership->setValue('mem_end', '9999-12-31');
        $membership->save();

        return $membership;
    }

    /** The profile changes of one user as "label: old -> new", so an assertion can name what it expects. */
    private function profileChanges(array $collected, int $userID): string
    {
        $lines = array();
        foreach ($collected[$userID]['profile_changes'] ?? array() as $change) {
            $lines[] = $change[0] . ': ' . $change[1] . ' -> ' . $change[2];
        }
        return implode(' | ', $lines);
    }

    private function roleChanges(array $collected, int $userID): string
    {
        $lines = array();
        foreach ($collected[$userID]['role_changes'] ?? array() as $change) {
            $lines[] = $change[0] . '/' . $change[1] . ': ' . $change[2] . ' -> ' . $change[3];
        }
        return implode(' | ', $lines);
    }

    public function testAProfileValueThatIsWrittenIsReportedWithTheFormatOfTheField(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $notification = $this->newNotification();

        $value = new ChangeNotificationTestUserData($db);
        $value->readDataByColumns(array('usd_usr_id' => $user->getValue('usr_id'), 'usd_usf_id' => 3));
        $value->setValue('usd_usf_id', 3);
        $value->setValue('usd_usr_id', $user->getValue('usr_id'));
        $value->setValue('usd_value', '1980-07-04');
        $value->save();

        $this->assertSame('Birthday:  -> 04.07.1980', $this->profileChanges($notification->collected(), 1));
        $this->assertSame(array('profile'), array_keys($notification->collected()[1]['reasons']), 'it is a reason for the state of the user');
    }

    public function testAValueThatWasNeverPersistedIsNotReported(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $notification = $this->newNotification();

        $unsaved = new ChangeNotificationTestUserData($db);
        $unsaved->setValue('usd_usr_id', $user->getValue('usr_id'));
        $unsaved->setValue('usd_usf_id', 3);
        $unsaved->setValue('usd_value', '1980-07-04');

        $this->assertSame(array(), $notification->collected());
    }

    public function testAChangeWhoseTransactionIsLostIsNotReported(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $notification = $this->newNotification();

        $db->startTransaction();
        $user->setValue('usr_login_name', 'rolled-back');
        $user->save();
        $db->rollback();

        $this->assertSame(array(), $notification->collected());
    }

    public function testAValueThatEndsWhereItStartedIsNoChange(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $notification = $this->newNotification();

        $user->setValue('usr_login_name', 'intermediate');
        $user->save();
        $user->setValue('usr_login_name', 'jdoe');
        $user->save();

        $this->assertSame('', $this->profileChanges($notification->collected(), 1));
    }

    public function testALoginWritesCountersAndIsNotAChangeOfTheUser(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $notification = $this->newNotification();

        $user->setValue('usr_last_login', '2026-08-01 10:00:00');
        $user->setValue('usr_number_login', 5);
        $user->setValue('usr_actual_login', '2026-08-26 10:00:00');
        $user->save();

        $this->assertSame(array(), $notification->collected());
    }

    public function testANewUserIsReportedAsACreation(): void
    {
        $db = $this->newDatabase();
        $notification = $this->newNotification();
        $this->aUser($db, 'newbie');

        $collected = $notification->collected();
        $this->assertTrue($collected[1]['created'] ?? false, 'a new user is reported as a creation');
        $this->assertSame(
            'SYS_USERNAME:  -> newbie | Last name:  -> Doe | First name:  -> John',
            $this->profileChanges($collected, 1),
            'with the columns of the record and the profile fields that have a value'
        );
        $this->assertSame(
            array('user', 'profile'),
            array_keys($collected[1]['reasons']),
            'both the record and the profile values are reasons'
        );
    }

    public function testAPasswordChangeIsReportedWithoutThePassword(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $notification = $this->newNotification();

        $user->setValue('usr_password', 'a-real-secret');
        $user->save();

        $this->assertSame('SYS_PASSWORD:  -> ********', $this->profileChanges($notification->collected(), 1));
    }

    public function testANewMembershipReportsItsStartAndEnd(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $notification = $this->newNotification();
        $membership = $this->aMembership($db, $user);

        $this->assertSame(
            'Choir/SYS_MEMBERSHIP_START:  -> 01.01.2026 | Choir/SYS_MEMBERSHIP_END:  -> 31.12.9999',
            $this->roleChanges($notification->collected(), 1)
        );

        $membership->setValue('mem_end', '2026-12-31');
        $membership->save();
        $this->assertSame(
            'Choir/SYS_MEMBERSHIP_START:  -> 01.01.2026 | Choir/SYS_MEMBERSHIP_END:  -> 31.12.2026',
            $this->roleChanges($notification->collected(), 1),
            'ending it reports the end against the value it had'
        );
    }

    public function testParticipatingInAnEventIsNotARoleOfThePerson(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $membership = $this->aMembership($db, $user, 2);
        $notification = $this->newNotification();
        $membership->setValue('mem_end', '2026-12-31');
        $membership->save();

        $this->assertSame(array(), $notification->collected());
    }

    public function testADeletedUserIsReportedAsADeletion(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $this->aMembership($db, $user);
        $notification = $this->newNotification();
        $user->delete();

        $collected = $notification->collected();
        $this->assertTrue($collected[1]['deleted'] ?? false, 'a deleted user is reported as a deletion');
        $this->assertSame(
            'Last name: Doe ->  | First name: John ->  | SYS_USERNAME: jdoe -> ',
            $this->profileChanges($collected, 1),
            'the record and the profile values it had are reported as emptied'
        );
        $this->assertSame(
            'Choir/SYS_MEMBERSHIP_START: 01.01.2026 ->  | Choir/SYS_MEMBERSHIP_END: 31.12.9999 -> ',
            $this->roleChanges($collected, 1),
            'and so are the memberships it had'
        );
        $this->assertSame('jdoe', $collected[1]['usr_login_name'] ?? '', 'the login name survives the deletion');
        $this->assertSame('Doe', $collected[1]['last_name'] ?? '', 'and so does the name');
        $this->assertSame('John', $collected[1]['first_name'] ?? '');
        $this->assertTrue($notification->reportable(1), 'a deleted user with a valid account is reported');
    }

    public function testAUserThatWasNeverActivatedIsNotNewsWhenDeleted(): void
    {
        $db = $this->newDatabase();
        $user = new ChangeNotificationTestUser($db);
        $user->setValue('usr_login_name', 'never-approved');
        $user->setValue('usr_valid', false);
        $user->save();
        $notification = $this->newNotification();
        $user->delete();

        $this->assertFalse($notification->reportable(1));
    }

    public function testDeletingAProfileFieldIsNotAChangeOfThePeopleWhoHadAValueInIt(): void
    {
        $db = $this->newDatabase();
        $this->aUser($db);
        $notification = $this->newNotification();

        $field = new ChangeNotificationTestProfileField($db, 3);
        $field->delete();

        $this->assertSame(array(), $notification->collected());
    }

    /**
     * Three users, of whom only two ever filled the field in. Deleting it must describe two rows,
     * not three: hookBulkDeletion() reads the rows that exist, and a profile field that was never
     * filled in has no row at all.
     */
    public function testDeletingAFieldOnlyDescribesTheUsersWhoHadAValueInIt(): void
    {
        $db = $this->newDatabase();
        $withValue = array();
        foreach (array('a', 'b', 'c') as $index => $loginName) {
            $person = $this->aUser($db, $loginName);
            if ($index < 2) {
                $value = new ChangeNotificationTestUserData($db);
                $value->setValue('usd_usr_id', $person->getValue('usr_id'));
                $value->setValue('usd_usf_id', 3);
                $value->setValue('usd_value', '1980-07-0' . ($index + 1));
                $value->save();
                $withValue[] = (int)$person->getValue('usr_id');
            }
        }

        $notification = $this->newNotification();
        $deletions = array();
        Hooks::addAction('user_data_deleted', function (EntityChangeSet $cs) use (&$deletions) {
            $deletions[] = (int)$cs->getOldValue('usd_usr_id');
        });

        $field = new ChangeNotificationTestProfileField($db, 3);
        $field->delete();

        $this->assertSame($withValue, $deletions, 'only the users who had a value in the field are described at all');
        $this->assertSame(array(), $notification->collected(), 'and none of them gets a notification');
    }

    public function testDeletingARoleIsNotAChangeOfItsMembers(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $this->aMembership($db, $user);
        $notification = $this->newNotification();

        $role = new ChangeNotificationTestRole($db, 1);
        $role->delete();

        $this->assertSame(array(), $notification->collected());
    }

    public function testASuppressedUserGetsNoNotification(): void
    {
        $db = $this->newDatabase();
        $notification = $this->newNotification();
        $this->aUser($db, 'registration');
        $notification->suppressUser(1);

        $this->assertFalse($notification->reportable(1));
    }

    public function testUserChangesCumulatedFiresOncePerAffectedUserAndRequestNamingEveryReason(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $notification = $this->newNotification();

        $states = array();
        Hooks::addAction('user_changes_cumulated', function (?string $uuid, array $reasons) use (&$states) {
            $states[] = ($uuid ?? '') . ':' . implode(',', $reasons);
        });

        $user->setValue('usr_login_name', 'renamed');
        $user->save();
        $value = new ChangeNotificationTestUserData($db);
        $value->setValue('usd_usr_id', 1);
        $value->setValue('usd_usf_id', 3);
        $value->setValue('usd_value', '1980-07-04');
        $value->save();
        $this->aMembership($db, $user);
        $notification->shutdown();

        $this->assertCount(1, $states, 'one action per affected user and request');
        $this->assertSame($user->getValue('usr_uuid') . ':user,profile,membership', $states[0] ?? '', 'naming every part of the person that changed');
    }

    public function testALoginIsNotAChangeOfTheUserForUserChangesCumulated(): void
    {
        $db = $this->newDatabase();
        $user = $this->aUser($db);
        $notification = $this->newNotification();
        $states = array();
        Hooks::addAction('user_changes_cumulated', function (?string $uuid, array $reasons) use (&$states) {
            $states[] = ($uuid ?? '') . ':' . implode(',', $reasons);
        });
        $user->setValue('usr_number_login', 7);
        $user->setValue('usr_actual_login', '2026-08-26 10:00:00');
        $user->save();
        $notification->shutdown();

        $this->assertSame(array(), $states);
    }
}
