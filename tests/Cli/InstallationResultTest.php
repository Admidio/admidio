<?php
/**
 * Headless Installation Tests
 *
 * Tests what a headless installation produces. The test suite installs Admidio from scratch before
 * it runs, through the same Installation service the web installer and the install:run command use,
 * so these tests describe the result of that installation rather than performing another one.
 *
 * They are the check that a fresh installation is complete and usable, which is the one thing no
 * upgrade path can reveal.
 */

namespace Admidio\Tests\Cli;

use Admidio\Infrastructure\Database;
use Admidio\Tests\Support\DatabaseTestCase;

class InstallationResultTest extends DatabaseTestCase
{
    /**
     * The organization the installation created.
     */
    private const ORG_ID = 1;

    /**
     * The names of all tables in the test database.
     *
     * @return array<int,string>
     */
    private function tableNames(): array
    {
        // MySQL reports the database as the schema, PostgreSQL the schema inside it
        $schema = $this->getDatabase()->getEngine() === Database::PDO_ENGINE_PGSQL
            ? 'public'
            : DB_NAME;

        $sql = 'SELECT table_name FROM information_schema.tables WHERE table_schema = ?';
        $rows = $this->getDatabase()->queryPrepared($sql, [$schema])->fetchAll();

        // MySQL 8 answers with the column name in upper case however the query spells it,
        // MariaDB and PostgreSQL keep it as written, so the row is read by position
        return array_map(static function (array $row): string {
            return strtolower((string) reset($row));
        }, $rows);
    }

    /**
     * Test that the schema is there
     *
     * @testdox A headless installation creates the tables the application needs
     */
    public function testHeadlessInstallationCreatesTheTables(): void
    {
        $tables = $this->tableNames();

        $this->assertGreaterThan(40, count($tables));

        foreach (array(
            TBL_ORGANIZATIONS, TBL_USERS, TBL_USER_DATA, TBL_USER_FIELDS, TBL_ROLES, TBL_MEMBERS,
            TBL_CATEGORIES, TBL_COMPONENTS, TBL_PREFERENCES, TBL_MENU, TBL_LISTS, TBL_SESSIONS
        ) as $table) {
            $this->assertContains(strtolower($table), $tables, $table . ' is missing after the installation.');
        }
    }

    /**
     * Test the known gap in the schema
     *
     * @testdox The forum tables are missing after a fresh MySQL installation
     */
    public function testForumTablesAreMissingAfterAFreshInstallation(): void
    {
        $tables = $this->tableNames();

        // install/db_scripts/db.sql ends both forum table definitions with the PostgreSQL clause
        // ENCODING 'UTF8', which MySQL and MariaDB reject, so the two tables are never created and
        // the forum module cannot be opened. Only fresh installations are affected, the update
        // steps create them correctly. Recorded as finding 0; this test fails once it is fixed,
        // which is the point.
        //
        // PostgreSQL is not affected: Database::preparePgSqlQuery() cuts everything behind the
        // last bracket of a CREATE TABLE, so the offending clause never reaches the server and
        // the installation ends up with two tables more than the same installation on MySQL.
        if ($this->getDatabase()->getEngine() === Database::PDO_ENGINE_PGSQL) {
            $this->assertContains(strtolower(TBL_FORUM_TOPICS), $tables);
            $this->assertContains(strtolower(TBL_FORUM_POSTS), $tables);

            return;
        }

        $this->assertNotContains(strtolower(TBL_FORUM_TOPICS), $tables);
        $this->assertNotContains(strtolower(TBL_FORUM_POSTS), $tables);
    }

    /**
     * Test that the organization is set up
     *
     * @testdox A headless installation creates exactly one organization
     */
    public function testHeadlessInstallationCreatesOneOrganization(): void
    {
        $sql = 'SELECT org_id, org_shortname, org_longname FROM ' . TBL_ORGANIZATIONS;
        $organizations = $this->getDatabase()->queryPrepared($sql)->fetchAll();

        $this->assertCount(1, $organizations);
        $this->assertEquals(self::ORG_ID, (int) $organizations[0]['org_id']);
        $this->assertNotEmpty($organizations[0]['org_shortname']);
        $this->assertNotEmpty($organizations[0]['org_longname']);
    }

    /**
     * Test that the accounts are set up
     *
     * @testdox A headless installation creates the system account and the administrator
     */
    public function testHeadlessInstallationCreatesTheSystemAccountAndTheAdministrator(): void
    {
        $sql = 'SELECT usr_id, usr_login_name, usr_valid, usr_password FROM ' . TBL_USERS . ' ORDER BY usr_id';
        $users = $this->getDatabase()->queryPrepared($sql)->fetchAll();

        $this->assertCount(2, $users);

        // the system account records changes that nobody made by hand and cannot log in
        $this->assertEquals('System', $users[0]['usr_login_name']);
        $this->assertFalse((bool) $users[0]['usr_valid']);

        // the administrator is the account the installation was given
        $this->assertEquals('admin', $users[1]['usr_login_name']);
        $this->assertTrue((bool) $users[1]['usr_valid']);
        $this->assertNotEmpty($users[1]['usr_password']);

        // and the administrator can actually administrate
        $sql = 'SELECT rol_administrator FROM ' . TBL_MEMBERS . '
                  INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
                 WHERE mem_usr_id = ? AND rol_administrator = true';
        $this->assertNotFalse($this->getDatabase()->queryPrepared($sql, [$users[1]['usr_id']])->fetch());
    }

    /**
     * Test that the modules are registered
     *
     * @testdox A headless installation registers the components of every module
     */
    public function testHeadlessInstallationRegistersTheComponents(): void
    {
        $sql = 'SELECT com_name_intern FROM ' . TBL_COMPONENTS;
        $components = array_column($this->getDatabase()->queryPrepared($sql)->fetchAll(), 'com_name_intern');

        $this->assertContains('CORE', $components);

        foreach (array(
            'ANNOUNCEMENTS', 'EVENTS', 'MESSAGES', 'GROUPS-ROLES', 'CONTACTS', 'DOCUMENTS-FILES',
            'INVENTORY', 'PHOTOS', 'LINKS', 'FORUM', 'MENU', 'PREFERENCES', 'REGISTRATION'
        ) as $module) {
            $this->assertContains($module, $components, $module . ' is not registered as a component.');
        }
    }

    /**
     * Test that the organization can be used
     *
     * @testdox A headless installation fills the preferences of the organization
     */
    public function testHeadlessInstallationFillsThePreferences(): void
    {
        $sql = 'SELECT COUNT(*) FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id = ?';
        $count = (int) $this->getDatabase()->queryPrepared($sql, [self::ORG_ID])->fetchColumn();

        // without them every read of a setting throws
        $this->assertGreaterThan(100, $count);

        // the list configurations the modules point at were created and are real ids
        $sql = 'SELECT prf_name, prf_value FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ? AND prf_name IN (?, ?, ?)';
        $listPreferences = $this->getDatabase()->queryPrepared($sql, [
            self::ORG_ID,
            'groups_roles_default_configuration',
            'events_list_configuration',
            'contacts_list_configuration'
        ])->fetchAll();

        $this->assertCount(3, $listPreferences);
        foreach ($listPreferences as $preference) {
            $sql = 'SELECT lst_id FROM ' . TBL_LISTS . ' WHERE lst_id = ?';
            $this->assertNotFalse(
                $this->getDatabase()->queryPrepared($sql, [$preference['prf_value']])->fetch(),
                $preference['prf_name'] . ' points at a list that does not exist.'
            );
        }
    }

    /**
     * Test that the organization has a structure to work with
     *
     * @testdox A headless installation creates the roles and categories a new organization needs
     */
    public function testHeadlessInstallationCreatesRolesAndCategories(): void
    {
        $db = $this->getDatabase();

        $sql = 'SELECT rol_name, rol_administrator, rol_default_registration FROM ' . TBL_ROLES;
        $roles = $db->queryPrepared($sql)->fetchAll();
        $this->assertNotEmpty($roles);

        // exactly one role administrates and exactly one receives new registrations
        $this->assertCount(1, array_filter($roles, static fn ($role) => (bool) $role['rol_administrator']));
        $this->assertCount(1, array_filter($roles, static fn ($role) => (bool) $role['rol_default_registration']));

        // and every module that groups its records has a category to put them in
        $sql = 'SELECT DISTINCT cat_type FROM ' . TBL_CATEGORIES . ' WHERE cat_org_id = ?';
        $types = array_column($db->queryPrepared($sql, [self::ORG_ID])->fetchAll(), 'cat_type');
        foreach (array('ROL', 'ANN', 'EVT', 'LNK', 'IVT') as $type) {
            $this->assertContains($type, $types, 'No category of type ' . $type . ' was created.');
        }

        // the categories of the profile fields belong to no organization, because the profile is
        // shared by all of them, just like the user records themselves
        $sql = 'SELECT COUNT(*) FROM ' . TBL_CATEGORIES . " WHERE cat_type = 'USF' AND cat_org_id IS NULL";
        $this->assertGreaterThan(0, (int) $db->queryPrepared($sql)->fetchColumn());
    }

    /**
     * Test that a profile can be filled in
     *
     * @testdox A headless installation creates the profile fields and the menu
     */
    public function testHeadlessInstallationCreatesProfileFieldsAndMenu(): void
    {
        $db = $this->getDatabase();

        $sql = 'SELECT usf_name_intern FROM ' . TBL_USER_FIELDS;
        $fields = array_column($db->queryPrepared($sql)->fetchAll(), 'usf_name_intern');
        foreach (array('LAST_NAME', 'FIRST_NAME', 'EMAIL', 'STREET', 'CITY', 'BIRTHDAY') as $field) {
            $this->assertContains($field, $fields, $field . ' is missing from the profile.');
        }

        // the menu is delivered as nodes with their entries below them
        $sql = 'SELECT COUNT(*) FROM ' . TBL_MENU . ' WHERE men_men_id_parent IS NULL';
        $this->assertGreaterThan(0, (int) $db->queryPrepared($sql)->fetchColumn());

        $sql = 'SELECT COUNT(*) FROM ' . TBL_MENU . ' WHERE men_men_id_parent IS NOT NULL';
        $this->assertGreaterThan(0, (int) $db->queryPrepared($sql)->fetchColumn());

        // and every entry of the standard menu is marked as one, so it is not deleted by accident
        $sql = 'SELECT COUNT(*) FROM ' . TBL_MENU . ' WHERE men_standard = false';
        $this->assertEquals(0, (int) $db->queryPrepared($sql)->fetchColumn());
    }
}
