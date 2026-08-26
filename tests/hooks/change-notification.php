<?php
/**
 * ChangeNotification as a listener on the committed change sets. The listener under test is the real
 * one and so are Entity, EntityHookQueue and the SQLite transactions underneath; the three tables of
 * a person are created with the columns of install/db_scripts/db.sql, and $gProfileFields,
 * $gSettingsManager and $gL10n are the stubs at the bottom of this file, because the mail is not what
 * is being tested here - what the listener hears and what it makes of it is.
 */
require __DIR__ . '/bootstrap.php';

use Admidio\Hooks\Hooks;
use Admidio\Hooks\Service\EntityHookQueue;
use Admidio\Infrastructure\ChangeNotification;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Tests\Hooks\FakeDatabase;

define('TBL_USERS', TABLE_PREFIX . '_users');
define('TBL_USER_DATA', TABLE_PREFIX . '_user_data');
define('TBL_MEMBERS', TABLE_PREFIX . '_members');
define('TBL_ROLES', TABLE_PREFIX . '_roles');
define('TBL_CATEGORIES', TABLE_PREFIX . '_categories');

// --------------------------------------------------------------------------------------- entities

function column(string $type, bool $key = false, bool $serial = false): array
{
    return array('type' => $type, 'null' => true, 'key' => $key, 'serial' => $serial, 'default' => null);
}

/** adm_users, with the hook ID, the sensitive columns and the ignored columns of the real User. */
class TestUser extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TBL_USERS, 'usr', $id);
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
        $this->deleteDependentRecords(new TestMembership($this->db), array('mem_id'), 'mem_usr_id = ?', array($usrId));
        $this->deleteDependentRecords(new TestUserData($this->db), array('usd_id'), 'usd_usr_id = ?', array($usrId));
        $returnValue = parent::delete();
        $this->db->endTransaction();

        return $returnValue;
    }
}

/** adm_user_data, one profile field value of one user. */
class TestUserData extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TBL_USER_DATA, 'usd', $id);
    }

    public function getHookId(): ?string
    {
        return 'user_data';
    }
}

/** adm_members, one membership of one user in one role. */
class TestMembership extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TBL_MEMBERS, 'mem', $id);
    }

    public function getHookId(): ?string
    {
        return 'membership';
    }
}

/** adm_user_fields, which takes the values of everybody with it when it is deleted. */
class TestProfileField extends Entity
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
        $this->deleteDependentRecords(new TestUserData($this->db), array('usd_id'), 'usd_usf_id = ?', array($usfId));
        $returnValue = parent::delete();
        $this->db->endTransaction();

        return $returnValue;
    }
}

/** adm_roles, which takes the memberships of everybody with it when it is deleted. */
class TestRole extends Entity
{
    public function __construct(Database $database, int|string $id = '')
    {
        parent::__construct($database, TBL_ROLES, 'rol', $id);
    }

    public function getHookId(): ?string
    {
        return 'role';
    }

    public function delete(): bool
    {
        $rolId = $this->getValue('rol_id');
        $this->db->startTransaction();
        $this->deleteDependentRecords(new TestMembership($this->db), array('mem_id'), 'mem_rol_id = ?', array($rolId));
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

// ------------------------------------------------------------------------------------------ stubs

/** The profile fields of the fixture, as much of ProfileFields as the listener asks for. */
class StubProfileFields
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

class StubSettingsManager
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

class StubLanguage
{
    public function get(string $textId, array $params = array()): string
    {
        return $textId;
    }
}

$GLOBALS['gProfileFields'] = new StubProfileFields();
$GLOBALS['gSettingsManager'] = new StubSettingsManager();
$GLOBALS['gL10n'] = new StubLanguage();
$GLOBALS['gCurrentUser'] = null;

// ---------------------------------------------------------------------------------------- fixture

function newDatabase(): FakeDatabase
{
    Hooks::reset();
    EntityHookQueue::reset();

    $db = new FakeDatabase();
    $db->createTable(TBL_USERS, array(
        'usr_id' => column('integer', true, true),
        'usr_uuid' => column('varchar(36)'),
        'usr_login_name' => column('varchar(254)'),
        'usr_password' => column('varchar(255)'),
        'usr_tfa_secret' => column('varchar(255)'),
        'usr_photo' => column('bytea'),
        'usr_text' => column('text'),
        'usr_last_login' => column('timestamp'),
        'usr_actual_login' => column('timestamp'),
        'usr_number_login' => column('integer'),
        'usr_usr_id_create' => column('integer'),
        'usr_timestamp_create' => column('timestamp'),
        'usr_usr_id_change' => column('integer'),
        'usr_timestamp_change' => column('timestamp'),
        'usr_valid' => column('boolean')
    ));
    $db->createTable(TBL_USER_DATA, array(
        'usd_id' => column('integer', true, true),
        'usd_usr_id' => column('integer'),
        'usd_usf_id' => column('integer'),
        'usd_value' => column('varchar(4000)')
    ));
    $db->createTable(TBL_MEMBERS, array(
        'mem_id' => column('integer', true, true),
        'mem_rol_id' => column('integer'),
        'mem_usr_id' => column('integer'),
        'mem_uuid' => column('varchar(36)'),
        'mem_begin' => column('date'),
        'mem_end' => column('date'),
        'mem_leader' => column('boolean'),
        'mem_usr_id_create' => column('integer'),
        'mem_timestamp_create' => column('timestamp'),
        'mem_usr_id_change' => column('integer'),
        'mem_timestamp_change' => column('timestamp')
    ));
    $db->createTable(TABLE_PREFIX . '_user_fields', array(
        'usf_id' => column('integer', true, true),
        'usf_name' => column('varchar(100)')
    ));
    $db->createTable(TBL_ROLES, array(
        'rol_id' => column('integer', true, true),
        'rol_cat_id' => column('integer'),
        'rol_name' => column('varchar(100)')
    ));
    $db->createTable(TBL_CATEGORIES, array(
        'cat_id' => column('integer', true, true),
        'cat_name_intern' => column('varchar(110)')
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

function newNotification(): ProbeNotification
{
    return new ProbeNotification();
}

/** A saved user with a last name, a first name and a membership. */
function aUser(FakeDatabase $db, string $loginName = 'jdoe'): TestUser
{
    $user = new TestUser($db);
    $user->setValue('usr_login_name', $loginName);
    $user->setValue('usr_valid', true);
    $user->save();

    foreach (array(1 => 'Doe', 2 => 'John') as $fieldId => $value) {
        $value_ = new TestUserData($db);
        $value_->setValue('usd_usr_id', $user->getValue('usr_id'));
        $value_->setValue('usd_usf_id', $fieldId);
        $value_->setValue('usd_value', $value);
        $value_->save();
    }

    return $user;
}

function aMembership(FakeDatabase $db, TestUser $user, int $roleId = 1): TestMembership
{
    $membership = new TestMembership($db);
    $membership->setValue('mem_rol_id', $roleId);
    $membership->setValue('mem_usr_id', $user->getValue('usr_id'));
    $membership->setValue('mem_begin', '2026-01-01');
    $membership->setValue('mem_end', '9999-12-31');
    $membership->save();

    return $membership;
}

/** The profile changes of one user as "label: old -> new", so a check can name what it expects. */
function profileChanges(array $collected, int $userID): string
{
    $lines = array();
    foreach ($collected[$userID]['profile_changes'] ?? array() as $change) {
        $lines[] = $change[0] . ': ' . $change[1] . ' -> ' . $change[2];
    }
    return implode(' | ', $lines);
}

function roleChanges(array $collected, int $userID): string
{
    $lines = array();
    foreach ($collected[$userID]['role_changes'] ?? array() as $change) {
        $lines[] = $change[0] . '/' . $change[1] . ': ' . $change[2] . ' -> ' . $change[3];
    }
    return implode(' | ', $lines);
}

// ------------------------------------------------------------------------ a profile value changes

$db = newDatabase();
$user = aUser($db);
$notification = newNotification();

$value = new TestUserData($db);
$value->readDataByColumns(array('usd_usr_id' => $user->getValue('usr_id'), 'usd_usf_id' => 3));
$value->setValue('usd_usf_id', 3);
$value->setValue('usd_usr_id', $user->getValue('usr_id'));
$value->setValue('usd_value', '1980-07-04');
$value->save();

check(
    'a profile value that is written is reported with the format of the field',
    profileChanges($notification->collected(), 1) === 'Birthday:  -> 04.07.1980',
    profileChanges($notification->collected(), 1)
);
check('and it is a reason for the state of the user', array_keys($notification->collected()[1]['reasons']) === array('profile'));

// ---------------------------------------------------------------- a value that was never persisted

$db = newDatabase();
$user = aUser($db);
$notification = newNotification();

$unsaved = new TestUserData($db);
$unsaved->setValue('usd_usr_id', $user->getValue('usr_id'));
$unsaved->setValue('usd_usf_id', 3);
$unsaved->setValue('usd_value', '1980-07-04');

check('a value that was never saved is not reported', $notification->collected() === array());

// ---------------------------------------------------------------------- a change that is rolled back

$db = newDatabase();
$user = aUser($db);
$notification = newNotification();

$db->startTransaction();
$user->setValue('usr_login_name', 'rolled-back');
$user->save();
$db->rollback();

check('a change whose transaction is lost is not reported', $notification->collected() === array());

// --------------------------------------------------------------------------- a value that comes back

$db = newDatabase();
$user = aUser($db);
$notification = newNotification();

$user->setValue('usr_login_name', 'intermediate');
$user->save();
$user->setValue('usr_login_name', 'jdoe');
$user->save();

check(
    'a value that ends where it started is no change',
    profileChanges($notification->collected(), 1) === '',
    profileChanges($notification->collected(), 1)
);

// ------------------------------------------------------------------------------- a login is no change

$db = newDatabase();
$user = aUser($db);
$notification = newNotification();

$user->setValue('usr_last_login', '2026-08-01 10:00:00');
$user->setValue('usr_number_login', 5);
$user->setValue('usr_actual_login', '2026-08-26 10:00:00');
$user->save();

check('a login writes counters and is not a change of the user', $notification->collected() === array());

// ---------------------------------------------------------------------------------- a user is created

$db = newDatabase();
$notification = newNotification();
$user = aUser($db, 'newbie');

$collected = $notification->collected();
check('a new user is reported as a creation', ($collected[1]['created'] ?? false) === true);
check(
    'with the columns of the record and the profile fields that have a value',
    profileChanges($collected, 1) === 'SYS_USERNAME:  -> newbie | Last name:  -> Doe | First name:  -> John',
    profileChanges($collected, 1)
);
check(
    'and both the record and the profile values are reasons',
    array_keys($collected[1]['reasons']) === array('user', 'profile'),
    implode(',', array_keys($collected[1]['reasons']))
);

// ---------------------------------------------------------------------------- a password is withheld

$db = newDatabase();
$user = aUser($db);
$notification = newNotification();

$user->setValue('usr_password', 'a-real-secret');
$user->save();

check(
    'a password change is reported without the password',
    profileChanges($notification->collected(), 1) === 'SYS_PASSWORD:  -> ********',
    profileChanges($notification->collected(), 1)
);

// -------------------------------------------------------------------------------- memberships

$db = newDatabase();
$user = aUser($db);
$notification = newNotification();
$membership = aMembership($db, $user);

check(
    'a new membership reports its start and its end',
    roleChanges($notification->collected(), 1) === 'Choir/SYS_MEMBERSHIP_START:  -> 01.01.2026 | Choir/SYS_MEMBERSHIP_END:  -> 31.12.9999',
    roleChanges($notification->collected(), 1)
);

$membership->setValue('mem_end', '2026-12-31');
$membership->save();
check(
    'ending it reports the end against the value it had',
    roleChanges($notification->collected(), 1) === 'Choir/SYS_MEMBERSHIP_START:  -> 01.01.2026 | Choir/SYS_MEMBERSHIP_END:  -> 31.12.2026',
    roleChanges($notification->collected(), 1)
);

$db = newDatabase();
$user = aUser($db);
$membership = aMembership($db, $user, 2);
$notification = newNotification();
$membership->setValue('mem_end', '2026-12-31');
$membership->save();
check('taking part in an event is not a role of the person', $notification->collected() === array());

// ------------------------------------------------------------------------------- a user is deleted

$db = newDatabase();
$user = aUser($db);
aMembership($db, $user);
$notification = newNotification();
$user->delete();

$collected = $notification->collected();
check('a deleted user is reported as a deletion', ($collected[1]['deleted'] ?? false) === true);
check(
    'the record and the profile values it had are reported as emptied',
    profileChanges($collected, 1) === 'Last name: Doe ->  | First name: John ->  | SYS_USERNAME: jdoe -> ',
    profileChanges($collected, 1)
);
check(
    'and so are the memberships it had',
    roleChanges($collected, 1) === 'Choir/SYS_MEMBERSHIP_START: 01.01.2026 ->  | Choir/SYS_MEMBERSHIP_END: 31.12.9999 -> ',
    roleChanges($collected, 1)
);
check('the login name survives the deletion', ($collected[1]['usr_login_name'] ?? '') === 'jdoe');
check('and so does the name', ($collected[1]['last_name'] ?? '') === 'Doe' && ($collected[1]['first_name'] ?? '') === 'John');
check('a deleted user with a valid account is reported', $notification->reportable(1));

// ------------------------------------------------------ a user that was never activated is not news

$db = newDatabase();
$user = new TestUser($db);
$user->setValue('usr_login_name', 'never-approved');
$user->setValue('usr_valid', false);
$user->save();
$notification = newNotification();
$user->delete();

check('deleting an account that was never activated is not reported', !$notification->reportable(1));

// -------------------------------------------------------------------- a cascade of something else

$db = newDatabase();
$user = aUser($db);
$notification = newNotification();

$field = new TestProfileField($db, 3);
$field->delete();

check('deleting a profile field is not a change of the people who had a value in it', $notification->collected() === array());

$db = newDatabase();
$user = aUser($db);
aMembership($db, $user);
$notification = newNotification();

$role = new TestRole($db, 1);
$role->delete();

check('deleting a role is not a change of its members', $notification->collected() === array());

// ------------------------------------------------------------------------------------ suppression

$db = newDatabase();
$notification = newNotification();
$user = aUser($db, 'registration');
$notification->suppressUser(1);

check('a suppressed user gets no notification', !$notification->reportable(1));

// ------------------------------------------------------------------------------ user_state_changed

$db = newDatabase();
$user = aUser($db);
$notification = newNotification();

$states = array();
Hooks::addAction('user_state_changed', function (?string $uuid, array $reasons) use (&$states) {
    $states[] = ($uuid ?? '') . ':' . implode(',', $reasons);
});

$user->setValue('usr_login_name', 'renamed');
$user->save();
$value = new TestUserData($db);
$value->setValue('usd_usr_id', 1);
$value->setValue('usd_usf_id', 3);
$value->setValue('usd_value', '1980-07-04');
$value->save();
aMembership($db, $user);
$notification->shutdown();

check('one action per affected user and request', count($states) === 1, (string)count($states));
check(
    'naming every part of the person that changed',
    ($states[0] ?? '') === $user->getValue('usr_uuid') . ':user,profile,membership',
    $states[0] ?? ''
);

$db = newDatabase();
$user = aUser($db);
$notification = newNotification();
$states = array();
Hooks::addAction('user_state_changed', function (?string $uuid, array $reasons) use (&$states) {
    $states[] = ($uuid ?? '') . ':' . implode(',', $reasons);
});
$user->setValue('usr_number_login', 7);
$user->setValue('usr_actual_login', '2026-08-26 10:00:00');
$user->save();
$notification->shutdown();

check('a login is not a state change', $states === array(), implode(' ', $states));

exit(testSummary());
