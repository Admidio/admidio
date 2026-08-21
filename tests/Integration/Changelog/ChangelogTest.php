<?php
/**
 * Changelog Tests
 *
 * Tests the audit trail. Every change to a record that Admidio logs is written to adm_log_changes by
 * Entity::save() and Entity::delete(), one row for the creation or deletion and one row per changed
 * column, together with the old value, the new value and who made the change.
 *
 * Which tables are logged is a preference per table, and the whole trail can be switched off.
 *
 * The tests enable logging explicitly: building a Session object switches the trail off for the rest
 * of the process, and the test suite has done that long before these tests run.
 */

namespace Admidio\Tests\Integration\Changelog;

use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Roles\Entity\Membership;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class ChangelogTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation.
     */
    private const ORG_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Entity::setLoggingEnabled(true);
    }

    protected function tearDown(): void
    {
        // put the flag back the way the rest of the suite found it
        Entity::setLoggingEnabled(false);

        parent::tearDown();
    }

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * The administrator that the installation created.
     */
    private function administrator(): User
    {
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $usrId = (int) $this->getDatabase()->queryPrepared($sql, ['admin'])->fetchColumn();

        return new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);
    }

    /**
     * Run a callback as the administrator of the installed organization.
     */
    private function asAdministrator(callable $callback)
    {
        return $this->withCurrentUser($this->administrator(), self::ORG_ID, true, $callback);
    }

    /**
     * The id of the newest log entry, to tell the entries of one action from the ones before it.
     */
    private function lastLogId(): int
    {
        $sql = 'SELECT COALESCE(MAX(log_id), 0) FROM ' . TBL_LOG_CHANGES;

        return (int) $this->getDatabase()->queryPrepared($sql)->fetchColumn();
    }

    /**
     * The log entries written after the given id.
     */
    private function logEntriesSince(int $logId): array
    {
        $sql = 'SELECT log_table, log_record_id, log_record_name, log_field, log_field_name,
                       log_action, log_value_old, log_value_new, log_usr_id_create
                  FROM ' . TBL_LOG_CHANGES . ' WHERE log_id > ? ORDER BY log_id';

        return $this->getDatabase()->queryPrepared($sql, [$logId])->fetchAll();
    }

    /**
     * The entries of one table out of a set of log entries.
     */
    private function entriesOfTable(array $entries, string $table): array
    {
        return array_values(array_filter($entries, static fn ($entry) => $entry['log_table'] === $table));
    }

    /**
     * Test that the trail is on for the tables that matter
     *
     * @testdox The installation logs the tables that carry the membership data
     */
    public function testInstallationLogsTheTablesThatCarryTheMembershipData(): void
    {
        $sql = 'SELECT prf_name, prf_value FROM ' . TBL_PREFERENCES . "
                 WHERE prf_org_id = ? AND prf_name LIKE 'changelog_%'";
        $rows = $this->getDatabase()->queryPrepared($sql, [self::ORG_ID])->fetchAll();
        $settings = array_combine(array_column($rows, 'prf_name'), array_column($rows, 'prf_value'));

        $this->assertEquals('1', $settings['changelog_module_enabled']);

        // who belongs to the organization and what their profile says is the part worth auditing
        $this->assertEquals('1', $settings['changelog_table_users']);
        $this->assertEquals('1', $settings['changelog_table_user_data']);
        $this->assertEquals('1', $settings['changelog_table_members']);

        // the configuration of the installation is not audited by default
        $this->assertEquals('0', $settings['changelog_table_categories']);
        $this->assertEquals('0', $settings['changelog_table_roles']);
    }

    /**
     * Test what creating a record records
     *
     * @testdox Creating a record is logged as a creation with one entry per stored column
     */
    public function testCreatingARecordIsLoggedAsACreation(): void
    {
        $entries = $this->asAdministrator(function () {
            $before = $this->lastLogId();

            $user = new User($this->getDatabase(), $GLOBALS['gProfileFields']);
            $user->setValue('usr_login_name', 'audited');
            $user->setValue('LAST_NAME', 'Audited');
            $user->save();

            return $this->logEntriesSince($before);
        });

        $userEntries = $this->entriesOfTable($entries, 'users');
        $this->assertEquals('CREATED', $userEntries[0]['log_action']);
        $this->assertNull($userEntries[0]['log_field']);

        // and the columns that were filled are listed one by one
        $this->assertEquals('MODIFY', $userEntries[1]['log_action']);
        $this->assertEquals('usr_login_name', $userEntries[1]['log_field']);
        $this->assertNull($userEntries[1]['log_value_old']);
        $this->assertEquals('audited', $userEntries[1]['log_value_new']);
    }

    /**
     * Test that a change records both values
     *
     * @testdox A change is logged with the value before and after it
     */
    public function testChangeIsLoggedWithTheValueBeforeAndAfterIt(): void
    {
        $entries = $this->asAdministrator(function () {
            $user = new User($this->getDatabase(), $GLOBALS['gProfileFields']);
            $user->setValue('usr_login_name', 'audited');
            $user->setValue('LAST_NAME', 'Audited');
            $user->save();

            $before = $this->lastLogId();
            $reread = new User($this->getDatabase(), $GLOBALS['gProfileFields'], (int) $user->getValue('usr_id'));
            $reread->saveChangesWithoutRights();
            $reread->setValue('usr_login_name', 'renamed');
            $reread->save();

            return $this->logEntriesSince($before);
        });

        $this->assertCount(1, $entries);
        $this->assertEquals('MODIFY', $entries[0]['log_action']);
        $this->assertEquals('usr_login_name', $entries[0]['log_field']);
        $this->assertEquals('audited', $entries[0]['log_value_old']);
        $this->assertEquals('renamed', $entries[0]['log_value_new']);
    }

    /**
     * Test that the trail names who changed something
     *
     * @testdox Every entry records who made the change
     */
    public function testEveryEntryRecordsWhoMadeTheChange(): void
    {
        $administratorId = (int) $this->administrator()->getValue('usr_id');

        $entries = $this->asAdministrator(function () {
            $before = $this->lastLogId();

            $user = new User($this->getDatabase(), $GLOBALS['gProfileFields']);
            $user->setValue('usr_login_name', 'audited');
            $user->setValue('LAST_NAME', 'Audited');
            $user->save();

            return $this->logEntriesSince($before);
        });

        $this->assertNotEmpty($entries);
        foreach ($entries as $entry) {
            $this->assertEquals($administratorId, (int) $entry['log_usr_id_create']);

            // and which record it was about, in a form that survives its deletion
            $this->assertNotEmpty($entry['log_record_name']);
        }
    }

    /**
     * Test that profile values are logged per field
     *
     * @testdox A profile value is logged against the field it belongs to
     */
    public function testProfileValueIsLoggedAgainstTheFieldItBelongsTo(): void
    {
        $entries = $this->asAdministrator(function () {
            $user = new User($this->getDatabase(), $GLOBALS['gProfileFields']);
            $user->setValue('usr_login_name', 'audited');
            $user->setValue('LAST_NAME', 'Audited');
            $user->save();

            $before = $this->lastLogId();
            $reread = new User($this->getDatabase(), $GLOBALS['gProfileFields'], (int) $user->getValue('usr_id'));
            $reread->saveChangesWithoutRights();
            $reread->setValue('CITY', 'Wien');
            $reread->save();

            return $this->logEntriesSince($before);
        });

        $dataEntries = $this->entriesOfTable($entries, 'user_data');
        $this->assertCount(1, $dataEntries);
        $this->assertEquals('Wien', $dataEntries[0]['log_value_new']);

        // the field is identified by its id and named by the label of the profile field
        $this->assertEquals(
            (int) $GLOBALS['gProfileFields']->getProperty('CITY', 'usf_id'),
            (int) $dataEntries[0]['log_field']
        );
        $this->assertNotEmpty($dataEntries[0]['log_field_name']);
    }

    /**
     * Test that technical columns stay out of the trail
     *
     * @testdox Columns that only Admidio maintains are not logged
     */
    public function testColumnsThatOnlyAdmidioMaintainsAreNotLogged(): void
    {
        $entries = $this->asAdministrator(function () {
            $user = new User($this->getDatabase(), $GLOBALS['gProfileFields']);
            $user->setValue('usr_login_name', 'audited');
            $user->setValue('LAST_NAME', 'Audited');
            $user->save();

            $before = $this->lastLogId();
            $reread = new User($this->getDatabase(), $GLOBALS['gProfileFields'], (int) $user->getValue('usr_id'));
            $reread->saveChangesWithoutRights();
            $reread->setValue('usr_valid', 0);
            $reread->save();

            return $this->logEntriesSince($before);
        });

        // the login counters, the timestamps and the active flag are noise, not an audit trail
        $this->assertCount(0, $entries);
    }

    /**
     * Test that a membership is audited
     *
     * @testdox Starting and ending a membership is logged
     */
    public function testStartingAndEndingAMembershipIsLogged(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRole('Audited Role', self::ORG_ID);
        $user = $fixture->createAndSaveUser('auditedmember', 'am@example.local');

        $result = $this->asAdministrator(function () use ($role, $user) {
            $beforeStart = $this->lastLogId();
            $membership = new Membership($this->getDatabase());
            $membership->startMembership($role['rol_id'], $user['usr_id']);
            $started = $this->logEntriesSince($beforeStart);

            $beforeDelete = $this->lastLogId();
            $stop = new Membership($this->getDatabase());
            $stop->readDataByColumns(array('mem_rol_id' => $role['rol_id'], 'mem_usr_id' => $user['usr_id']));
            $stop->delete();

            return array('started' => $started, 'deleted' => $this->logEntriesSince($beforeDelete));
        });

        $started = $this->entriesOfTable($result['started'], 'members');
        $this->assertEquals('CREATED', $started[0]['log_action']);
        $this->assertContains('mem_begin', array_column($started, 'log_field'));

        $deleted = $this->entriesOfTable($result['deleted'], 'members');
        $this->assertCount(1, $deleted);
        $this->assertEquals('DELETED', $deleted[0]['log_action']);

        // the name stays in the trail although the record is gone
        $this->assertNotEmpty($deleted[0]['log_record_name']);
    }

    /**
     * Test that a table can be left out
     *
     * @testdox A table whose logging is switched off leaves no trail
     */
    public function testTableWhoseLoggingIsSwitchedOffLeavesNoTrail(): void
    {
        $entries = $this->asAdministrator(function () {
            $before = $this->lastLogId();

            $category = new Category($this->getDatabase());
            $category->setValue('cat_type', 'ANN');
            $category->setValue('cat_org_id', self::ORG_ID);
            $category->setValue('cat_name', 'Unaudited Category');
            $category->save();

            return $this->logEntriesSince($before);
        });

        $this->assertCount(0, $entries);
    }

    /**
     * Test that a table can be switched on
     *
     * @testdox A table can be added to the trail through its preference
     */
    public function testTableCanBeAddedToTheTrailThroughItsPreference(): void
    {
        $entries = $this->asAdministrator(function () {
            $GLOBALS['gSettingsManager']->set('changelog_table_categories', 1);

            try {
                $before = $this->lastLogId();

                $category = new Category($this->getDatabase());
                $category->setValue('cat_type', 'ANN');
                $category->setValue('cat_org_id', self::ORG_ID);
                $category->setValue('cat_name', 'Audited Category');
                $category->save();

                return $this->logEntriesSince($before);
            } finally {
                $GLOBALS['gSettingsManager']->set('changelog_table_categories', 0);
            }
        });

        $categoryEntries = $this->entriesOfTable($entries, 'categories');
        $this->assertNotEmpty($categoryEntries);
        $this->assertEquals('CREATED', $categoryEntries[0]['log_action']);
        $this->assertEquals('Audited Category', $categoryEntries[0]['log_record_name']);
        $this->assertContains('cat_name', array_column($categoryEntries, 'log_field'));
    }

    /**
     * Test that the whole trail can be switched off
     *
     * @testdox Switching the changelog off stops every entry
     */
    public function testSwitchingTheChangelogOffStopsEveryEntry(): void
    {
        $entries = $this->asAdministrator(function () {
            $GLOBALS['gSettingsManager']->set('changelog_module_enabled', 0);

            try {
                $before = $this->lastLogId();

                $user = new User($this->getDatabase(), $GLOBALS['gProfileFields']);
                $user->setValue('usr_login_name', 'unaudited');
                $user->setValue('LAST_NAME', 'Unaudited');
                $user->save();

                return $this->logEntriesSince($before);
            } finally {
                $GLOBALS['gSettingsManager']->set('changelog_module_enabled', 1);
            }
        });

        $this->assertCount(0, $entries);
    }

    /**
     * Test that deleting a record is recorded
     *
     * @testdox Deleting a record is logged as a deletion
     */
    public function testDeletingARecordIsLoggedAsADeletion(): void
    {
        $entries = $this->asAdministrator(function () {
            $user = new User($this->getDatabase(), $GLOBALS['gProfileFields']);
            $user->setValue('usr_login_name', 'audited');
            $user->setValue('LAST_NAME', 'Audited');
            $user->save();
            $usrId = (int) $user->getValue('usr_id');

            $before = $this->lastLogId();
            $toDelete = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);
            $toDelete->delete();

            return $this->logEntriesSince($before);
        });

        $userEntries = $this->entriesOfTable($entries, 'users');
        $this->assertNotEmpty($userEntries);
        $this->assertEquals('DELETED', $userEntries[0]['log_action']);
        $this->assertEquals('Audited,', $userEntries[0]['log_record_name']);
    }
}
