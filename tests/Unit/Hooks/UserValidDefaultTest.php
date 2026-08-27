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

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;

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

class UserValidDefaultTest extends EntityHookTestCase
{
    private function newDatabase(): FakeDatabase
    {
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
    private function saveAndInspect(FakeDatabase $db, ProbeUser $user, string $column, string $value): array
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

    public function testANewUserObjectIsActiveAndWrittenAsActive(): void
    {
        $db = $this->newDatabase();
        $user = new ProbeUser($db);
        $this->assertEquals(1, $user->getValue('usr_valid'), 'a new user object is active');

        $user->setValue('usr_login_name', 'jdoe');
        $user->save();
        $row = $db->fetchAll(TABLE_PREFIX . '_users')[0];
        $this->assertSame(1, (int)$row['usr_valid'], 'and the record is written as active');
    }

    public function testAUserThatWasReadCarriesNoPendingChangeOfUsrValid(): void
    {
        $db = $this->newDatabase();
        $user = new ProbeUser($db);
        $user->setValue('usr_login_name', 'jdoe');
        $user->save();
        $id = (int)$user->getValue('usr_id');

        $again = new ProbeUser($db, $id);
        $this->assertFalse($again->changedFlag('usr_valid'));

        $result = $this->saveAndInspect($db, $again, 'usr_login_name', 'renamed');
        $this->assertSame(
            'UPDATE adm_users SET usr_login_name = ?',
            $result['update'],
            'so an ordinary save does not write the column'
        );
        $this->assertSame(array('usr_login_name'), $result['reported'], 'and does not report it as a change');
    }

    public function testReadingIntoAnExistingObjectLeavesNoPendingChangeEither(): void
    {
        $db = $this->newDatabase();
        $user = new ProbeUser($db);
        $user->setValue('usr_login_name', 'jdoe');
        $user->save();
        $id = (int)$user->getValue('usr_id');

        $reused = new ProbeUser($db);
        $reused->readDataById($id);
        $this->assertFalse($reused->changedFlag('usr_valid'));
    }

    public function testActivationIsAChangeLikeAny(): void
    {
        $db = $this->newDatabase();
        $registration = new ProbeUser($db);
        $registration->setValue('usr_login_name', 'pending');
        $registration->setValue('usr_valid', 0);          // UserRegistration::save() does this
        $registration->save();
        $id = (int)$registration->getValue('usr_id');
        $this->assertSame(0, (int)$db->fetchAll(TABLE_PREFIX . '_users')[0]['usr_valid'], 'a registration is created inactive');

        $accepted = new ProbeUser($db, $id);
        $result = $this->saveAndInspect($db, $accepted, 'usr_valid', '1');
        $this->assertSame('UPDATE adm_users SET usr_valid = ?', $result['update'], 'accepting it writes the column');
        $this->assertSame(array('usr_valid'), $result['reported'], 'and reports it as a change');
    }

    public function testClearAsNewUserActivatesADuplicatedImportUser(): void
    {
        $db = $this->newDatabase();
        $user = new ProbeUser($db);
        $user->setValue('usr_login_name', 'original');
        $user->save();
        $duplicate = new ProbeUser($db, (int)$user->getValue('usr_id'));
        $duplicate->clearAsNewUser();
        $duplicate->setValue('usr_login_name', 'duplicate');
        $duplicate->save();
        $rows = $db->fetchAll(TABLE_PREFIX . '_users');

        $this->assertCount(2, $rows);
        $this->assertSame(1, (int)$rows[1]['usr_valid']);
    }

    /** The defect this check exists to stop: the previous clear() marked usr_valid changed on every read. */
    public function testLegacyClearMarkedUsrValidChangedOnEveryRead(): void
    {
        $db = $this->newDatabase();
        $legacy = new LegacyUser($db);
        $legacy->setValue('usr_login_name', 'jdoe');
        $legacy->save();
        $legacy = new LegacyUser($db, (int)$legacy->getValue('usr_id'));
        $result = $this->saveAndInspect($db, $legacy, 'usr_login_name', 'renamed');

        $this->assertSame(array('usr_login_name', 'usr_valid'), $result['reported']);
    }
}
