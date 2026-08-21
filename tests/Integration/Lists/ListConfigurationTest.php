<?php
/**
 * List Configuration Tests
 *
 * Tests the configurations behind the member lists. A configuration is a row in adm_lists plus one
 * row per column in adm_list_columns, and a column is either a profile field (lsc_usf_id) or one of
 * a fixed set of special fields (lsc_special_field). From that the configuration builds the SQL
 * that selects the members and their data.
 *
 * The tests use the installed organization: ListConfiguration::delete() reads the three list
 * configuration preferences as integers, and only an organization set up by the installer has them.
 */

namespace Admidio\Tests\Integration\Lists;

use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class ListConfigurationTest extends DatabaseTestCase
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
     * Build a user that may administrate the members of the installed organization.
     */
    private function makeUserAdministrator(string $login): User
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRoleWithRights('List Administrators', self::ORG_ID, ['rol_edit_user' => 1]);
        $user = $fixture->createAndSaveUser($login, $login . '@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);

        return $this->loadUserInOrganization($user['usr_id'], self::ORG_ID);
    }

    /**
     * The usf_id of a profile field.
     */
    private function profileField(string $nameIntern): int
    {
        return (int) $GLOBALS['gProfileFields']->getProperty($nameIntern, 'usf_id');
    }

    /**
     * Read the stored columns of a list.
     */
    private function storedColumns(int $lstId): array
    {
        $sql = 'SELECT lsc_number, lsc_usf_id, lsc_special_field, lsc_sort, lsc_filter
                  FROM ' . TBL_LIST_COLUMNS . ' WHERE lsc_lst_id = ? ORDER BY lsc_number';

        return $this->getDatabase()->queryPrepared($sql, [$lstId])->fetchAll();
    }

    /**
     * Test that the installation delivers list configurations
     *
     * @testdox The installation creates the default list configurations
     */
    public function testInstallationCreatesTheDefaultListConfigurations(): void
    {
        $db = $this->getDatabase();

        $lists = $db->queryPrepared('SELECT lst_id, lst_name, lst_global, lst_org_id FROM ' . TBL_LISTS . '
                                      WHERE lst_org_id = ? ORDER BY lst_id', [self::ORG_ID])->fetchAll();
        $this->assertNotEmpty($lists);

        // the delivered configurations belong to the organization and are available to everybody
        foreach ($lists as $list) {
            $this->assertEquals(self::ORG_ID, (int) $list['lst_org_id']);
            $this->assertTrue((bool) $list['lst_global']);
            $this->assertNotEmpty($list['lst_name']);
        }

        // the modules point at three of them, and they are stored as ids
        $sql = 'SELECT prf_name, prf_value FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ? AND prf_name IN (?, ?, ?)';
        $preferences = $db->queryPrepared($sql, [
            self::ORG_ID,
            'groups_roles_default_configuration',
            'events_list_configuration',
            'contacts_list_configuration'
        ])->fetchAll();
        $this->assertCount(3, $preferences);
        foreach ($preferences as $preference) {
            $this->assertGreaterThan(0, (int) $preference['prf_value'], $preference['prf_name']);
        }
    }

    /**
     * Test that a list records where and by whom it was created
     *
     * @testdox A list belongs to the current organization and the user that saved it
     */
    public function testListBelongsToTheOrganizationAndTheUserThatSavedIt(): void
    {
        $admin = $this->makeUserAdministrator('listowner');

        $lstId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $list = new ListConfiguration($this->getDatabase());
            $list->setValue('lst_name', 'My list');
            $list->addColumn($this->profileField('LAST_NAME'));
            $list->save();

            return (int) $list->getValue('lst_id');
        });

        $sql = 'SELECT lst_org_id, lst_usr_id, lst_name, lst_global, lst_uuid, lst_timestamp
                  FROM ' . TBL_LISTS . ' WHERE lst_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$lstId])->fetch();

        $this->assertEquals(self::ORG_ID, (int) $row['lst_org_id']);
        $this->assertEquals((int) $admin->getValue('usr_id'), (int) $row['lst_usr_id']);
        $this->assertEquals('My list', $row['lst_name']);
        $this->assertNotEmpty($row['lst_uuid']);
        $this->assertNotEmpty($row['lst_timestamp']);

        // a list is private unless it is explicitly marked global
        $this->assertFalse((bool) $row['lst_global']);
    }

    /**
     * Test that a list can be shared with the organization
     *
     * @testdox A list can be marked as available to the whole organization
     */
    public function testListCanBeMarkedGlobal(): void
    {
        $admin = $this->makeUserAdministrator('listglobal');

        $lstId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $list = new ListConfiguration($this->getDatabase());
            $list->setValue('lst_name', 'Shared list');
            $list->setValue('lst_global', 1);
            $list->addColumn($this->profileField('LAST_NAME'));
            $list->save();

            return (int) $list->getValue('lst_id');
        });

        $sql = 'SELECT lst_global FROM ' . TBL_LISTS . ' WHERE lst_id = ?';
        $this->assertTrue((bool) $this->getDatabase()->queryPrepared($sql, [$lstId])->fetchColumn());
    }

    /**
     * Test that profile field columns are stored with their position
     *
     * @testdox Columns are stored with their number, their field and their sorting
     */
    public function testColumnsAreStoredWithTheirNumberAndSorting(): void
    {
        $admin = $this->makeUserAdministrator('listcolumns');

        $lstId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $list = new ListConfiguration($this->getDatabase());
            $list->setValue('lst_name', 'Sorted list');
            $list->addColumn($this->profileField('LAST_NAME'), 0, 'ASC');
            $list->addColumn($this->profileField('FIRST_NAME'), 0, 'DESC');
            $list->addColumn($this->profileField('CITY'), 0, '', 'Wien');
            $list->save();

            return (int) $list->getValue('lst_id');
        });

        $columns = $this->storedColumns($lstId);
        $this->assertCount(3, $columns);

        // the columns are numbered in the order they were added, starting at one
        $this->assertEquals([1, 2, 3], array_map('intval', array_column($columns, 'lsc_number')));
        $this->assertEquals($this->profileField('LAST_NAME'), (int) $columns[0]['lsc_usf_id']);
        $this->assertEquals('ASC', $columns[0]['lsc_sort']);
        $this->assertEquals('DESC', $columns[1]['lsc_sort']);
        $this->assertEquals('Wien', $columns[2]['lsc_filter']);

        // a profile field column has no special field
        $this->assertNull($columns[0]['lsc_special_field']);
    }

    /**
     * Test that special columns are stored separately
     *
     * @testdox A special column is stored by name instead of by profile field
     */
    public function testSpecialColumnIsStoredByName(): void
    {
        $admin = $this->makeUserAdministrator('listspecial');

        $lstId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $list = new ListConfiguration($this->getDatabase());
            $list->setValue('lst_name', 'Membership list');
            $list->addColumn($this->profileField('LAST_NAME'));
            $list->addColumn('mem_begin');
            $list->addColumn('mem_end');
            $list->save();

            return (int) $list->getValue('lst_id');
        });

        $columns = $this->storedColumns($lstId);
        $this->assertEquals([null, 'mem_begin', 'mem_end'], array_column($columns, 'lsc_special_field'));
        $this->assertNull($columns[1]['lsc_usf_id']);
    }

    /**
     * Test that only known special columns are accepted
     *
     * @testdox A special column that is not on the allowed list is refused
     */
    public function testUnknownSpecialColumnIsRefused(): void
    {
        $admin = $this->makeUserAdministrator('listunknown');

        $results = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $list = new ListConfiguration($this->getDatabase());
            $list->setValue('lst_name', 'Bad list');

            return array(
                'allowed' => $list->addColumn('mem_begin'),
                'refused' => $list->addColumn('rol_name'),
                'refusedSql' => $list->addColumn('usr_password'),
                'count' => $list->countColumns()
            );
        });

        $this->assertTrue($results['allowed']);
        $this->assertFalse($results['refused']);
        $this->assertFalse($results['refusedSql']);

        // only the accepted column made it into the configuration
        $this->assertEquals(1, $results['count']);
    }

    /**
     * Test that the uuid column is reserved for user administrators
     *
     * @testdox The unique id column may only be added by a user administrator
     */
    public function testUuidColumnNeedsTheUserAdministrationRight(): void
    {
        $fixture = $this->getFixture();
        $admin = $this->makeUserAdministrator('listuuidadmin');
        $plain = $fixture->createAndSaveUser('listuuidplain', 'lup@example.local');
        $plainUser = $this->loadUserInOrganization($plain['usr_id'], self::ORG_ID);

        $asAdmin = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $list = new ListConfiguration($this->getDatabase());

            return $list->addColumn('usr_uuid');
        });
        $asPlain = $this->withCurrentUser($plainUser, self::ORG_ID, true, function () {
            $list = new ListConfiguration($this->getDatabase());

            return $list->addColumn('usr_uuid');
        });

        $this->assertTrue($asAdmin);
        $this->assertFalse($asPlain);
    }

    /**
     * Test that the column names come from the profile fields
     *
     * @testdox The column names are taken from the profile field definitions
     */
    public function testColumnNamesComeFromTheProfileFields(): void
    {
        $admin = $this->makeUserAdministrator('listnames');

        $names = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $list = new ListConfiguration($this->getDatabase());
            $list->setValue('lst_name', 'Named list');
            $list->addColumn($this->profileField('LAST_NAME'));
            $list->addColumn($this->profileField('FIRST_NAME'));
            $list->addColumn('mem_begin');
            $list->save();

            $reread = new ListConfiguration($this->getDatabase(), (int) $list->getValue('lst_id'));

            return array($reread->getColumnNames(), $reread->countColumns());
        });

        $this->assertEquals(3, $names[1]);
        $this->assertEquals(['Surname', 'First name', 'Start'], $names[0]);
    }

    /**
     * Test that the configuration produces a working query
     *
     * @testdox The generated SQL selects the members of the given role
     */
    public function testGeneratedSqlSelectsTheMembersOfARole(): void
    {
        $fixture = $this->getFixture();
        $admin = $this->makeUserAdministrator('listsql');
        $role = $fixture->createAndSaveRole('List Role', self::ORG_ID);
        $memberA = $fixture->createAndSaveUser('listmembera', 'lma@example.local');
        $memberB = $fixture->createAndSaveUser('listmemberb', 'lmb@example.local');
        $outsider = $fixture->createAndSaveUser('listoutsider', 'lo@example.local');
        $fixture->assignUserToRole($memberA['usr_id'], $role['rol_id']);
        $fixture->assignUserToRole($memberB['usr_id'], $role['rol_id']);

        $uuids = $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($role) {
            $list = new ListConfiguration($this->getDatabase());
            $list->setValue('lst_name', 'Role list');
            $list->addColumn($this->profileField('LAST_NAME'), 0, 'ASC');
            $list->save();

            // the unique id of the user is only selected when it is asked for
            $sql = $list->getSQL(array('showRolesMembers' => array($role['rol_uuid']), 'showUserUUID' => true));

            return array_column($this->getDatabase()->queryPrepared($sql)->fetchAll(), 'usr_uuid');
        });

        $this->assertContains($memberA['usr_uuid'], $uuids);
        $this->assertContains($memberB['usr_uuid'], $uuids);
        $this->assertNotContains($outsider['usr_uuid'], $uuids);
    }

    /**
     * Test that deleting a column closes the gap
     *
     * @testdox Deleting a column moves the following columns one place up
     */
    public function testDeletingAColumnClosesTheGap(): void
    {
        $admin = $this->makeUserAdministrator('listdelcol');

        $lstId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $list = new ListConfiguration($this->getDatabase());
            $list->setValue('lst_name', 'Gap list');
            $list->addColumn($this->profileField('LAST_NAME'));
            $list->addColumn($this->profileField('FIRST_NAME'));
            $list->addColumn($this->profileField('CITY'));
            $list->save();

            $reread = new ListConfiguration($this->getDatabase(), (int) $list->getValue('lst_id'));
            $reread->deleteColumn(1);

            return (int) $list->getValue('lst_id');
        });

        $columns = $this->storedColumns($lstId);
        $this->assertCount(2, $columns);
        $this->assertEquals([1, 2], array_map('intval', array_column($columns, 'lsc_number')));

        // the first column is gone and the other two moved up
        $this->assertEquals($this->profileField('FIRST_NAME'), (int) $columns[0]['lsc_usf_id']);
        $this->assertEquals($this->profileField('CITY'), (int) $columns[1]['lsc_usf_id']);
    }

    /**
     * Test that deleting a list removes its columns
     *
     * @testdox Deleting a list removes its columns as well
     */
    public function testDeletingAListRemovesItsColumns(): void
    {
        $admin = $this->makeUserAdministrator('listdelete');

        $lstId = $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $list = new ListConfiguration($this->getDatabase());
            $list->setValue('lst_name', 'Doomed list');
            $list->addColumn($this->profileField('LAST_NAME'));
            $list->addColumn($this->profileField('FIRST_NAME'));
            $list->save();

            $reread = new ListConfiguration($this->getDatabase(), (int) $list->getValue('lst_id'));
            $reread->delete();

            return (int) $list->getValue('lst_id');
        });

        $db = $this->getDatabase();
        $this->assertFalse($db->queryPrepared('SELECT lst_id FROM ' . TBL_LISTS . ' WHERE lst_id = ?', [$lstId])->fetch());
        $this->assertCount(0, $this->storedColumns($lstId));
    }

    /**
     * Test that the default configuration of a module is protected
     *
     * @testdox The list that a module uses as its default configuration cannot be deleted
     */
    public function testDefaultConfigurationOfAModuleCannotBeDeleted(): void
    {
        $admin = $this->makeUserAdministrator('listdefault');

        $this->withCurrentUser($admin, self::ORG_ID, true, function () {
            $defaultId = $GLOBALS['gSettingsManager']->getInt('groups_roles_default_configuration');
            $this->assertGreaterThan(0, $defaultId);

            $list = new ListConfiguration($this->getDatabase(), $defaultId);

            try {
                $list->delete();
                $this->fail('The default configuration of a module must not be deletable.');
            } catch (Exception $e) {
                $this->assertStringContainsString('default configuration', $e->getMessage());
            }

            // and it is still there
            $sql = 'SELECT lst_id FROM ' . TBL_LISTS . ' WHERE lst_id = ?';
            $this->assertNotFalse($this->getDatabase()->queryPrepared($sql, [$defaultId])->fetch());
        });
    }
}
