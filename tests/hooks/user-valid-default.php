<?php
/**
 * usr_valid is a value of the record, not a side effect of clear().
 *
 * The real `User` cannot be instantiated here - it needs a `ProfileFields` object, which reads the
 * database. `ProbeUser` therefore carries `User::__construct()` and `User::clear()` as they stand in
 * the source, reduced to the two lines that touch the column, and everything below it is the real
 * `Entity`. The checks are about which columns end up marked as changed, which is what `Entity` alone
 * decides.
 */
require __DIR__ . '/bootstrap.php';

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Tests\Hooks\FakeDatabase;

/** User as it is after the patch: the default belongs to the creation of the record. */
class ProbeUser extends Entity
{
    public function __construct(Database $database, int $userId = 0)
    {
        parent::__construct($database, TABLE_PREFIX . '_users', 'usr', $userId);

        if ($this->newRecord) {
            $this->initializeNewRecord();
        }
    }

    public function getHookId(): ?string
    {
        return 'user';
    }

    protected function initializeNewRecord(): void
    {
        $this->setValue('usr_valid', 1);
    }

    /** clear() no longer touches the column. */
    public function clear(): void
    {
        parent::clear();
    }

    public function clearAsNewUser(): void
    {
        $this->clear();
        $this->initializeNewRecord();
    }

    public function changedFlag(string $column): bool
    {
        return (bool)($this->columnsInfos[$column]['changed'] ?? false);
    }
}

/** User as it is before the patch, to show that the check would have caught it. */
class LegacyUser extends ProbeUser
{
    public function clear(): void
    {
        Entity::clear();
        $this->setValue('usr_valid', 1);
        $this->columnsValueChanged = false;
    }
}

function newDatabase(): FakeDatabase
{
    Hooks::reset();
    $db = new FakeDatabase();
    $db->createTable(TABLE_PREFIX . '_users', array(
        'usr_id' => array('type' => 'integer', 'null' => false, 'key' => true, 'serial' => true, 'default' => null),
        'usr_uuid' => array('type' => 'varchar(36)', 'null' => true, 'key' => false, 'serial' => false, 'default' => null),
        'usr_login_name' => array('type' => 'varchar(254)', 'null' => true, 'key' => false, 'serial' => false, 'default' => null),
        'usr_valid' => array('type' => 'boolean', 'null' => true, 'key' => false, 'serial' => false, 'default' => null)
    ));
    return $db;
}

/** The UPDATE that the next save writes, and the columns the hook reports. */
function saveAndInspect(FakeDatabase $db, ProbeUser $user, string $column, string $value): array
{
    $reported = array();
    Hooks::reset();
    Hooks::addAction('user_updated', function (EntityChangeSet $cs) use (&$reported) {
        $reported = array_keys($cs->getChanges());
    });

    $before = count($db->statements);
    $user->setValue($column, $value);
    $user->save();

    $update = '';
    foreach (array_slice($db->statements, $before) as $statement) {
        if (str_starts_with(ltrim($statement), 'UPDATE')) {
            $update = preg_replace('/\s+/', ' ', trim(explode('WHERE', $statement)[0]));
        }
    }

    return array('update' => $update, 'reported' => $reported);
}

// ------------------------------------------------------------------ a new record is created active

$db = newDatabase();
$user = new ProbeUser($db);
check('a new user object is active', $user->getValue('usr_valid') == 1, var_export($user->getValue('usr_valid'), true));

$user->setValue('usr_login_name', 'jdoe');
$user->save();
$row = $db->fetchAll(TABLE_PREFIX . '_users')[0];
check('and the record is written as active', (int)$row['usr_valid'] === 1, var_export($row['usr_valid'], true));
$id = (int)$user->getValue('usr_id');

// --------------------------------------------------------- a record that was read has no change yet

$again = new ProbeUser($db, $id);
check('a user that was read carries no pending change of usr_valid', !$again->changedFlag('usr_valid'));

$result = saveAndInspect($db, $again, 'usr_login_name', 'renamed');
check(
    'so an ordinary save does not write the column',
    $result['update'] === 'UPDATE adm_users SET usr_login_name = ?',
    $result['update']
);
check(
    'and does not report it as a change',
    $result['reported'] === array('usr_login_name'),
    implode(',', $result['reported'])
);

// ------------------------------------------------------------ the same object, read into afterwards

$reused = new ProbeUser($db);
$reused->readDataById($id);
check('reading into an existing object leaves no pending change either', !$reused->changedFlag('usr_valid'));

// ------------------------------------------------------------------- activation is a change like any

$db = newDatabase();
$registration = new ProbeUser($db);
$registration->setValue('usr_login_name', 'pending');
$registration->setValue('usr_valid', 0);          // UserRegistration::save() does this
$registration->save();
$id = (int)$registration->getValue('usr_id');
check('a registration is created inactive', (int)$db->fetchAll(TABLE_PREFIX . '_users')[0]['usr_valid'] === 0);

$accepted = new ProbeUser($db, $id);
$result = saveAndInspect($db, $accepted, 'usr_valid', '1');
check(
    'accepting it writes the column',
    $result['update'] === 'UPDATE adm_users SET usr_valid = ?',
    $result['update']
);
check('and reports it as a change', $result['reported'] === array('usr_valid'), implode(',', $result['reported']));

// -------------------------------------------------- clear() as "become a new user", as UserImport does

$db = newDatabase();
$user = new ProbeUser($db);
$user->setValue('usr_login_name', 'original');
$user->save();
$duplicate = new ProbeUser($db, (int)$user->getValue('usr_id'));
$duplicate->clearAsNewUser();
$duplicate->setValue('usr_login_name', 'duplicate');
$duplicate->save();
$rows = $db->fetchAll(TABLE_PREFIX . '_users');
check('a duplicated import user is created active', count($rows) === 2 && (int)$rows[1]['usr_valid'] === 1);

// --------------------------------------------------------------- the defect this check exists to stop

$db = newDatabase();
$legacy = new LegacyUser($db);
$legacy->setValue('usr_login_name', 'jdoe');
$legacy->save();
$legacy = new LegacyUser($db, (int)$legacy->getValue('usr_id'));
$result = saveAndInspect($db, $legacy, 'usr_login_name', 'renamed');
check(
    'the previous clear() did mark it changed on every read, which is what this test pins',
    $result['reported'] === array('usr_login_name', 'usr_valid'),
    implode(',', $result['reported'])
);

exit(testSummary());
