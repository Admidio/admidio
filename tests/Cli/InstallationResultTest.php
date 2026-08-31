<?php
/**
 * Headless Installation Tests
 *
 * Tests what a headless installation produces. The class runs the real ./admidio install:run and
 * describes the installation that command created.
 *
 * It installs into the test database but under a table prefix of its own, and not into the shared
 * fixture of the suite. The fixture is the installation every other database test works on: the CLI
 * tests write to it through their own processes, which commit outside the transaction that isolates
 * a test, so what the fixture contains depends on which tests ran before this class. Assertions
 * about a new installation would then be answered by a used one, and a failure somewhere else would
 * be reported a second time here as a broken installation.
 *
 * These are the check that a fresh installation is complete and usable, which is the one thing no
 * upgrade path can reveal.
 */

namespace Admidio\Tests\Cli;

use Admidio\Infrastructure\Database;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;
use Admidio\Tests\Support\CliSubprocess;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\TestDatabaseInitializer;

class InstallationResultTest extends DatabaseTestCase
{
    use CliSubprocess;

    /**
     * Table prefix of the installation under test.
     *
     * Admidio builds its table names out of the prefix while it is bootstrapping, so a second
     * installation is only possible in a second process, which is what install:run is. The prefix
     * must not begin with the one of the fixture, or setting the fixture up would drop these tables
     * along with its own.
     */
    private const PREFIX = 'instrun';

    /**
     * The organization the installation created.
     */
    private const ORG_ID = 1;

    /**
     * The organization the installation is asked to create.
     */
    private const ORG_SHORTNAME = 'INSTRUN';

    /**
     * The administrator the installation is asked to create.
     */
    private const ADMIN_LOGIN = 'admin';
    private const ADMIN_PASSWORD = 'test_admin_123';

    /**
     * Path of the configuration file that describes the installation under test.
     */
    private static string $installationConfigurationFile = '';

    /**
     * Install Admidio once for this class, through the command an administrator would use.
     *
     * @throws \Admidio\Infrastructure\Exception
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $databaseConfig = admidioTestDatabaseConfig();

        // install:run refuses a database that already contains an installation, so a run that was
        // interrupted before it could clean up must not be able to stop the next one
        TestDatabaseInitializer::dropTables(self::$gDb, $databaseConfig, self::PREFIX);

        // the file describes the same database as the fixture, but the prefix of this installation
        self::$installationConfigurationFile = Installation::writeConfigFile(
            InstallationConfig::fromArray(array(
                'dbType' => self::cliDatabaseType($databaseConfig),
                'dbHost' => $databaseConfig['host'],
                'dbPort' => $databaseConfig['port'],
                'dbName' => $databaseConfig['database'],
                'dbUsername' => $databaseConfig['user'],
                'dbPassword' => $databaseConfig['password'],
                'tablePrefix' => self::PREFIX,
                'rootUrl' => 'http://localhost/admidio',
                'timezone' => 'UTC'
            )),
            ADMIDIO_PATH . FOLDER_DATA . '/installation-result-config.php'
        );

        $installation = self::runCli(
            array(
                'install:run',
                '--yes',
                '--no-interaction',
                '--language=en',
                '--organization-shortname=' . self::ORG_SHORTNAME,
                '--organization-name=Installation Result',
                '--organization-email=organization@test.local',
                '--admin-login=' . self::ADMIN_LOGIN,
                '--admin-first-name=Admin',
                '--admin-last-name=User',
                '--admin-email=admin@test.local',
                '--admin-password-stdin',
                '--format=json'
            ),
            self::$installationConfigurationFile,
            self::ADMIN_PASSWORD . PHP_EOL,
            // an installation writes the whole schema and all default data, a query does neither
            600
        );

        self::assertSame(
            0,
            $installation->getExitCode(),
            'The headless installation failed.' . PHP_EOL
            . $installation->getOutput() . PHP_EOL . $installation->getErrorOutput()
        );
    }

    /**
     * Leave the test database as this class found it.
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$gDb instanceof Database) {
            TestDatabaseInitializer::dropTables(self::$gDb, admidioTestDatabaseConfig(), self::PREFIX);
        }

        if (self::$installationConfigurationFile !== '' && is_file(self::$installationConfigurationFile)) {
            unlink(self::$installationConfigurationFile);
        }
        self::$installationConfigurationFile = '';

        parent::tearDownAfterClass();
    }

    /**
     * The name a table has in the installation under test.
     *
     * The assertions name the tables through the constants of Admidio, which are built from the
     * prefix this process was started with. The installation under test has its own prefix.
     */
    private function table(string $table): string
    {
        return self::PREFIX . substr($table, strlen(TABLE_PREFIX));
    }

    /**
     * The names of the tables the installation under test created.
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
        $names = array_map(static function (array $row): string {
            return strtolower((string) reset($row));
        }, $rows);

        // the fixture of the suite lives in the same database and is not what is described here
        return array_values(array_filter($names, static function (string $name): bool {
            return str_starts_with($name, self::PREFIX . '_');
        }));
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
            $this->assertContains(
                strtolower($this->table($table)),
                $tables,
                $table . ' is missing after the installation.'
            );
        }
    }

    /**
     * Test the tables that a fresh installation used to miss
     *
     * @testdox A fresh installation creates the forum tables
     */
    public function testFreshInstallationCreatesTheForumTables(): void
    {
        $tables = $this->tableNames();

        // both definitions in install/db_scripts/db.sql used to end with the PostgreSQL clause
        // ENCODING 'UTF8', which MySQL and MariaDB reject, so a fresh installation was missing
        // these two tables and the forum module could not be opened. PostgreSQL never showed it,
        // because Database::preparePgSqlQuery() cuts everything behind the last bracket of a
        // CREATE TABLE and the offending clause never reached the server.
        $this->assertContains(strtolower($this->table(TBL_FORUM_TOPICS)), $tables);
        $this->assertContains(strtolower($this->table(TBL_FORUM_POSTS)), $tables);
    }

    /**
     * Test that the organization is set up
     *
     * @testdox A headless installation creates exactly one organization
     */
    public function testHeadlessInstallationCreatesOneOrganization(): void
    {
        $sql = 'SELECT org_id, org_shortname, org_longname FROM ' . $this->table(TBL_ORGANIZATIONS);
        $organizations = $this->getDatabase()->queryPrepared($sql)->fetchAll();

        $this->assertCount(1, $organizations);
        $this->assertEquals(self::ORG_ID, (int) $organizations[0]['org_id']);

        // the organization of this installation, and not the one of the fixture of the suite
        $this->assertEquals(self::ORG_SHORTNAME, $organizations[0]['org_shortname']);
        $this->assertNotEmpty($organizations[0]['org_longname']);
    }

    /**
     * Test that the accounts are set up
     *
     * @testdox A headless installation creates the system account and the administrator
     */
    public function testHeadlessInstallationCreatesTheSystemAccountAndTheAdministrator(): void
    {
        $sql = 'SELECT usr_id, usr_login_name, usr_valid, usr_password FROM ' . $this->table(TBL_USERS)
            . ' ORDER BY usr_id';
        $users = $this->getDatabase()->queryPrepared($sql)->fetchAll();

        $this->assertCount(2, $users);

        // the system account records changes that nobody made by hand and cannot log in
        $this->assertEquals('System', $users[0]['usr_login_name']);
        $this->assertFalse((bool) $users[0]['usr_valid']);

        // the administrator is the account the installation was given
        $this->assertEquals(self::ADMIN_LOGIN, $users[1]['usr_login_name']);
        $this->assertTrue((bool) $users[1]['usr_valid']);
        $this->assertNotEmpty($users[1]['usr_password']);

        // and the administrator can actually administrate
        $sql = 'SELECT rol_administrator FROM ' . $this->table(TBL_MEMBERS) . '
                  INNER JOIN ' . $this->table(TBL_ROLES) . ' ON rol_id = mem_rol_id
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
        $sql = 'SELECT com_name_intern FROM ' . $this->table(TBL_COMPONENTS);
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
     * Test that every component was given its own identifier
     *
     * @testdox A headless installation gives every component a uuid of its own
     */
    public function testHeadlessInstallationGivesEveryComponentAUuid(): void
    {
        $sql = 'SELECT com_name_intern, com_uuid FROM ' . $this->table(TBL_COMPONENTS);
        $components = $this->getDatabase()->queryPrepared($sql)->fetchAll();

        $uuids = array_column($components, 'com_uuid');

        foreach ($components as $component) {
            $this->assertNotEmpty(
                $component['com_uuid'],
                $component['com_name_intern'] . ' was installed without a uuid.'
            );
        }
        $this->assertCount(count($uuids), array_unique($uuids));
    }

    /**
     * Test that the organization can be used
     *
     * @testdox A headless installation fills the preferences of the organization
     */
    public function testHeadlessInstallationFillsThePreferences(): void
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->table(TBL_PREFERENCES) . ' WHERE prf_org_id = ?';
        $count = (int) $this->getDatabase()->queryPrepared($sql, [self::ORG_ID])->fetchColumn();

        // without them every read of a setting throws
        $this->assertGreaterThan(100, $count);

        // the list configurations the modules point at were created and are real ids
        $sql = 'SELECT prf_name, prf_value FROM ' . $this->table(TBL_PREFERENCES) . '
                 WHERE prf_org_id = ? AND prf_name IN (?, ?, ?)';
        $listPreferences = $this->getDatabase()->queryPrepared($sql, [
            self::ORG_ID,
            'groups_roles_default_configuration',
            'events_list_configuration',
            'contacts_list_configuration'
        ])->fetchAll();

        $this->assertCount(3, $listPreferences);
        foreach ($listPreferences as $preference) {
            $sql = 'SELECT lst_id FROM ' . $this->table(TBL_LISTS) . ' WHERE lst_id = ?';
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

        $sql = 'SELECT rol_name, rol_administrator, rol_default_registration FROM ' . $this->table(TBL_ROLES);
        $roles = $db->queryPrepared($sql)->fetchAll();
        $this->assertNotEmpty($roles);

        // exactly one role administrates and exactly one receives new registrations
        $this->assertCount(1, array_filter($roles, static fn ($role) => (bool) $role['rol_administrator']));
        $this->assertCount(1, array_filter($roles, static fn ($role) => (bool) $role['rol_default_registration']));

        // and every module that groups its records has a category to put them in
        $sql = 'SELECT DISTINCT cat_type FROM ' . $this->table(TBL_CATEGORIES) . ' WHERE cat_org_id = ?';
        $types = array_column($db->queryPrepared($sql, [self::ORG_ID])->fetchAll(), 'cat_type');
        foreach (array('ROL', 'ANN', 'EVT', 'LNK', 'IVT') as $type) {
            $this->assertContains($type, $types, 'No category of type ' . $type . ' was created.');
        }

        // the categories of the profile fields belong to no organization, because the profile is
        // shared by all of them, just like the user records themselves
        $sql = 'SELECT COUNT(*) FROM ' . $this->table(TBL_CATEGORIES) . " WHERE cat_type = 'USF' AND cat_org_id IS NULL";
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

        $sql = 'SELECT usf_name_intern FROM ' . $this->table(TBL_USER_FIELDS);
        $fields = array_column($db->queryPrepared($sql)->fetchAll(), 'usf_name_intern');
        foreach (array('LAST_NAME', 'FIRST_NAME', 'EMAIL', 'STREET', 'CITY', 'BIRTHDAY') as $field) {
            $this->assertContains($field, $fields, $field . ' is missing from the profile.');
        }

        // the menu is delivered as nodes with their entries below them
        $sql = 'SELECT COUNT(*) FROM ' . $this->table(TBL_MENU) . ' WHERE men_men_id_parent IS NULL';
        $this->assertGreaterThan(0, (int) $db->queryPrepared($sql)->fetchColumn());

        $sql = 'SELECT COUNT(*) FROM ' . $this->table(TBL_MENU) . ' WHERE men_men_id_parent IS NOT NULL';
        $this->assertGreaterThan(0, (int) $db->queryPrepared($sql)->fetchColumn());

        // and every entry of the standard menu is marked as one, so it is not deleted by accident
        $sql = 'SELECT COUNT(*) FROM ' . $this->table(TBL_MENU) . ' WHERE men_standard = false';
        $this->assertEquals(0, (int) $db->queryPrepared($sql)->fetchColumn());
    }
}
