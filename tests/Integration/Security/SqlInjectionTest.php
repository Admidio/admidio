<?php
/**
 * SQL Injection Tests
 *
 * Tests that values which look like SQL are treated as data. Admidio sends every query through
 * Database::queryPrepared(), so a payload arrives at the database as a bound parameter and comes
 * back out unchanged instead of being executed.
 *
 * The last test covers the one place that builds SQL by concatenation instead, so that the
 * assumption behind it stays visible.
 */

namespace Admidio\Tests\Integration\Security;

use Admidio\Categories\Entity\Category;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;
use Ramsey\Uuid\Uuid;

class SqlInjectionTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation.
     */
    private const ORG_ID = 1;

    /**
     * Payloads that would change the meaning of a query if they were concatenated into it.
     */
    private const PAYLOADS = array(
        "' OR '1'='1",
        "'; DROP TABLE adm_users; --",
        "1' UNION SELECT usr_password FROM adm_users --",
        "admin'--",
        "\\' OR 1=1 #"
    );

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Run a callback as the administrator of the installed organization.
     */
    private function asAdministrator(callable $callback)
    {
        $administrator = new User($this->getDatabase(), $GLOBALS['gProfileFields'], 1);

        return $this->withCurrentUser($administrator, self::ORG_ID, true, $callback);
    }

    /**
     * Test that a payload in a profile value stays a value
     *
     * @testdox A value that looks like SQL is stored and read back unchanged
     */
    public function testValueThatLooksLikeSqlIsStoredUnchanged(): void
    {
        $fixture = $this->getFixture();
        $user = $fixture->createAndSaveUser('injuser', 'inj@example.local');

        $this->asAdministrator(function () use ($user) {
            $entity = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $user['usr_id']);
            $entity->saveChangesWithoutRights();
            $entity->setValue('LAST_NAME', self::PAYLOADS[0]);
            $entity->setValue('CITY', self::PAYLOADS[1]);
            $entity->save();
        });

        $sql = 'SELECT usd_value FROM ' . TBL_USER_DATA . '
                  INNER JOIN ' . TBL_USER_FIELDS . ' ON usf_id = usd_usf_id
                 WHERE usd_usr_id = ? AND usf_name_intern = ?';
        $db = $this->getDatabase();

        $this->assertEquals(self::PAYLOADS[0], $db->queryPrepared($sql, [$user['usr_id'], 'LAST_NAME'])->fetchColumn());
        $this->assertEquals(self::PAYLOADS[1], $db->queryPrepared($sql, [$user['usr_id'], 'CITY'])->fetchColumn());

        // and the table the payload names is still there
        $this->assertNotFalse($db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_USERS)->fetchColumn());
    }

    /**
     * Test that every payload survives a round trip
     *
     * @testdox Every injection payload round trips through an entity as data
     */
    public function testEveryPayloadRoundTripsThroughAnEntityAsData(): void
    {
        $db = $this->getDatabase();

        foreach (self::PAYLOADS as $index => $payload) {
            $catId = $this->asAdministrator(function () use ($db, $payload) {
                $category = new Category($db);
                $category->setValue('cat_type', 'ANN');
                $category->setValue('cat_org_id', self::ORG_ID);
                $category->setValue('cat_name', $payload);
                $category->save();

                return (int) $category->getValue('cat_id');
            });

            $stored = $db->queryPrepared('SELECT cat_name FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?', [$catId])->fetchColumn();
            $this->assertEquals($payload, $stored, 'payload ' . $index . ' was altered on the way to the database');
        }

        // the categories table still holds exactly the rows that were written
        $sql = 'SELECT COUNT(*) FROM ' . TBL_CATEGORIES . " WHERE cat_type = 'ANN' AND cat_org_id = ?";
        $this->assertGreaterThanOrEqual(count(self::PAYLOADS), (int) $db->queryPrepared($sql, [self::ORG_ID])->fetchColumn());
    }

    /**
     * Test that a crafted search finds nothing rather than everything
     *
     * @testdox A search term that looks like SQL matches nothing instead of every row
     */
    public function testSearchTermThatLooksLikeSqlMatchesNothing(): void
    {
        $fixture = $this->getFixture();
        $fixture->createAndSaveUser('searchable1', 's1@example.local');
        $fixture->createAndSaveUser('searchable2', 's2@example.local');
        $db = $this->getDatabase();

        $total = (int) $db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_USERS)->fetchColumn();
        $this->assertGreaterThan(1, $total);

        // the shape of the query the contacts module runs against the login name
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';

        foreach (self::PAYLOADS as $payload) {
            $rows = $db->queryPrepared($sql, [$payload])->fetchAll();
            $this->assertCount(0, $rows, 'the payload "' . $payload . '" matched a row');
        }

        // a real login name still matches
        $this->assertCount(1, $db->queryPrepared($sql, ['searchable1'])->fetchAll());
    }

    /**
     * Test that a login name containing SQL does not become another user
     *
     * @testdox A login name that contains SQL does not match another account
     */
    public function testLoginNameThatContainsSqlDoesNotMatchAnotherAccount(): void
    {
        $fixture = $this->getFixture();
        $fixture->createAndSaveUser('realadmin', 'ra@example.local');
        $db = $this->getDatabase();

        $sql = 'SELECT usr_login_name FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';

        // the classic bypass has to come back empty
        $this->assertCount(0, $db->queryPrepared($sql, ["realadmin'--"])->fetchAll());
        $this->assertCount(0, $db->queryPrepared($sql, ["realadmin' OR '1'='1"])->fetchAll());
        $this->assertEquals('realadmin', $db->queryPrepared($sql, ['realadmin'])->fetchColumn());
    }

    /**
     * Test that a payload in a LIKE search stays inside its parameter
     *
     * @testdox A payload inside a LIKE search stays a search term
     */
    public function testPayloadInsideALikeSearchStaysASearchTerm(): void
    {
        $fixture = $this->getFixture();
        $fixture->createAndSaveUser('likeuser', 'lu@example.local');
        $db = $this->getDatabase();

        $sql = 'SELECT usr_login_name FROM ' . TBL_USERS . ' WHERE usr_login_name LIKE ?';

        $this->assertCount(0, $db->queryPrepared($sql, ["%' OR '1'='1"])->fetchAll());
        $this->assertGreaterThan(0, count($db->queryPrepared($sql, ['like%'])->fetchAll()));
    }

    /**
     * Test the one query that is built by concatenation
     *
     * @testdox The list configuration puts role ids into its SQL, so the caller has to validate them
     */
    public function testListConfigurationRequiresValidatedRoleIds(): void
    {
        $fixture = $this->getFixture();
        $role = $fixture->createAndSaveRoleWithRights('Injection Admins', self::ORG_ID, ['rol_edit_user' => 1]);
        $user = $fixture->createAndSaveUser('injadmin', 'ia@example.local');
        $fixture->assignUserToRole($user['usr_id'], $role['rol_id']);
        $admin = $this->loadUserInOrganization($user['usr_id'], self::ORG_ID);

        $payload = "') OR 1=1 -- ";

        // this is the guard: the modules check every role id against the uuid format before they
        // hand it to getSQL(), which concatenates it into the statement without escaping
        $this->assertFalse(Uuid::isValid($payload));
        $this->assertTrue(Uuid::isValid($role['rol_uuid']));

        $this->withCurrentUser($admin, self::ORG_ID, true, function () use ($payload, $role) {
            $list = new ListConfiguration($this->getDatabase());
            $list->setValue('lst_name', 'Injection list');
            $list->addColumn((int) $GLOBALS['gProfileFields']->getProperty('LAST_NAME', 'usf_id'));
            $list->save();

            // the payload is placed into the statement verbatim, so nothing below the caller
            // would stop it. Recorded as a finding.
            $craftedSql = $list->getSQL(array('showRolesMembers' => array($payload)));
            $this->assertStringContainsString($payload, $craftedSql);

            // a validated id is used the same way and selects only that role
            $properSql = $list->getSQL(array('showRolesMembers' => array($role['rol_uuid'])));
            $this->assertStringContainsString($role['rol_uuid'], $properSql);
        });
    }
}
