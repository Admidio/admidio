<?php
/**
 * Database Abstraction Tests
 *
 * Tests the behaviour Admidio relies on from whichever database it runs against. The Database class
 * hides the differences between MySQL, MariaDB and PostgreSQL, so the same statements and the same
 * values have to come back the same way on all of them.
 *
 * These tests describe what the application assumes. Running them against a second engine is what
 * turns them from a description into a comparison.
 */

namespace Admidio\Tests\Integration\Database;

use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Database;
use Admidio\Roles\Entity\Role;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;

class DatabaseAbstractionTest extends DatabaseTestCase
{
    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * The nesting depth of the open transactions, which the class only keeps internally.
     */
    private function transactionDepth(): int
    {
        $property = new \ReflectionProperty($this->getDatabase(), 'transactions');
        $property->setAccessible(true);

        return (int) $property->getValue($this->getDatabase());
    }

    /**
     * Test that the engine is known
     *
     * @testdox The database reports which engine and product it runs on
     */
    public function testDatabaseReportsWhichEngineItRunsOn(): void
    {
        $db = $this->getDatabase();

        $this->assertContains($db->getEngine(), array('mysql', 'pgsql'));
        $this->assertNotEmpty($db->getName());

        if ($db->getEngine() === Database::PDO_ENGINE_PGSQL) {
            // tableExists() compares information_schema.table_schema with the database name,
            // which on PostgreSQL is the schema and therefore never matches (finding 27)
            $this->markTestSkipped('Database::tableExists() always answers false on PostgreSQL (finding 27).');
        }

        // the tables the application needs are there under the configured prefix
        $this->assertTrue($db->tableExists(TBL_USERS));
        $this->assertTrue($db->tableExists(TBL_ROLES));
        $this->assertFalse($db->tableExists(TABLE_PREFIX . '_not_a_table'));
    }

    /**
     * Test that a boolean survives the round trip
     *
     * @testdox A boolean column is stored and read back as a decision
     */
    public function testBooleanColumnIsStoredAndReadBackAsADecision(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Bool Org', 'boolorg');
        $category = $fixture->createAndSaveCategory('Bool Category', 'ROL', $org['org_id']);

        $role = new Role($this->getDatabase());
        $role->saveChangesWithoutRights();
        $role->setValue('rol_cat_id', $category['cat_id']);
        $role->setValue('rol_name', 'Bool Role');
        $role->setValue('rol_announcements', 1);
        $role->setValue('rol_events', 0);
        $role->save();

        $sql = 'SELECT rol_announcements, rol_events, rol_valid FROM ' . TBL_ROLES . ' WHERE rol_id = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$role->getValue('rol_id')])->fetch();

        $this->assertTrue((bool) $row['rol_announcements']);
        $this->assertFalse((bool) $row['rol_events']);

        // a column with a default is true without anybody setting it
        $this->assertTrue((bool) $row['rol_valid']);
    }

    /**
     * Test that a boolean can be searched for
     *
     * @testdox A boolean can be used as a bound parameter in a condition
     */
    public function testBooleanCanBeUsedAsABoundParameter(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Bool Org', 'boolorg');
        $category = $fixture->createAndSaveCategory('Bool Category', 'ROL', $org['org_id']);
        $fixture->createAndSaveRoleInCategory('Valid Role', $category['cat_id']);
        $invalid = $fixture->createAndSaveRoleInCategory('Invalid Role', $category['cat_id']);
        $fixture->setRoleValidity($invalid['rol_id'], false);

        $db = $this->getDatabase();
        $sql = 'SELECT rol_name FROM ' . TBL_ROLES . ' WHERE rol_cat_id = ? AND rol_valid = ?';

        $valid = array_column($db->queryPrepared($sql, [$category['cat_id'], true])->fetchAll(), 'rol_name');
        $notValid = array_column($db->queryPrepared($sql, [$category['cat_id'], false])->fetchAll(), 'rol_name');

        $this->assertEquals(array('Valid Role'), $valid);
        $this->assertEquals(array('Invalid Role'), $notValid);
    }

    /**
     * Test that a result can be cut into pages
     *
     * @testdox A result set can be limited and offset
     */
    public function testResultSetCanBeLimitedAndOffset(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Limit Org', 'limitorg');
        $category = $fixture->createAndSaveCategory('Limit Category', 'ROL', $org['org_id']);
        foreach (array('A', 'B', 'C', 'D', 'E') as $suffix) {
            $fixture->createAndSaveRoleInCategory('Role ' . $suffix, $category['cat_id']);
        }

        $db = $this->getDatabase();
        $sql = 'SELECT rol_name FROM ' . TBL_ROLES . ' WHERE rol_cat_id = ? ORDER BY rol_name';

        $all = array_column($db->queryPrepared($sql, [$category['cat_id']])->fetchAll(), 'rol_name');
        $this->assertEquals(array('Role A', 'Role B', 'Role C', 'Role D', 'Role E'), $all);

        $firstTwo = array_column($db->queryPrepared($sql . ' LIMIT 2', [$category['cat_id']])->fetchAll(), 'rol_name');
        $this->assertEquals(array('Role A', 'Role B'), $firstTwo);

        $nextTwo = array_column($db->queryPrepared($sql . ' LIMIT 2 OFFSET 2', [$category['cat_id']])->fetchAll(), 'rol_name');
        $this->assertEquals(array('Role C', 'Role D'), $nextTwo);
    }

    /**
     * Test that sorting is stable
     *
     * @testdox Sorting returns the rows in the order that was asked for
     */
    public function testSortingReturnsTheRowsInTheOrderThatWasAskedFor(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Sort Org', 'sortorg');
        $category = $fixture->createAndSaveCategory('Sort Category', 'ROL', $org['org_id']);
        foreach (array('Charlie', 'alpha', 'Bravo') as $name) {
            $fixture->createAndSaveRoleInCategory($name, $category['cat_id']);
        }

        $db = $this->getDatabase();
        $sql = 'SELECT rol_name FROM ' . TBL_ROLES . ' WHERE rol_cat_id = ? ORDER BY rol_name ';

        $ascending = array_column($db->queryPrepared($sql . 'ASC', [$category['cat_id']])->fetchAll(), 'rol_name');
        $descending = array_column($db->queryPrepared($sql . 'DESC', [$category['cat_id']])->fetchAll(), 'rol_name');

        $this->assertEquals(array_reverse($ascending), $descending);

        // the collation sorts without regard to case, so the lower case name is not sorted last
        $this->assertEquals(array('alpha', 'Bravo', 'Charlie'), $ascending);
    }

    /**
     * Test how text is compared
     *
     * @testdox Whether text is compared without regard to case depends on the engine
     */
    public function testCaseSensitivityOfTextComparisonDependsOnTheEngine(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Case Org', 'caseorg');
        $fixture->createAndSaveCategory('Mixed Case Name', 'ROL', $org['org_id']);

        $db = $this->getDatabase();
        $sql = 'SELECT COUNT(*) FROM ' . TBL_CATEGORIES . ' WHERE cat_org_id = ? AND cat_name = ?';

        $this->assertEquals(1, (int) $db->queryPrepared($sql, [$org['org_id'], 'Mixed Case Name'])->fetchColumn());

        // MySQL compares under utf8mb4_unicode_ci and finds the row whatever the case, PostgreSQL
        // compares byte by byte and does not. Uniqueness therefore means something different on
        // the two engines, for login names and organization short names as well (finding 28).
        $expected = $db->getEngine() === Database::PDO_ENGINE_PGSQL ? 0 : 1;

        $this->assertEquals($expected, (int) $db->queryPrepared($sql, [$org['org_id'], 'mixed case name'])->fetchColumn());
        $this->assertEquals($expected, (int) $db->queryPrepared($sql, [$org['org_id'], 'MIXED CASE NAME'])->fetchColumn());
    }

    /**
     * Test that text outside the latin alphabet survives
     *
     * @testdox Text of any script is stored and read back unchanged
     */
    public function testTextOfAnyScriptIsStoredAndReadBackUnchanged(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Utf Org', 'utforg');

        $names = array('Grüße aus Wien', 'Ελληνικά', '日本語のテキスト', 'Крокодил', 'Emoji ✓ ☂');
        $ids = array();
        foreach ($names as $index => $name) {
            $category = $fixture->createAndSaveCategory($name, 'ROL', $org['org_id']);
            $ids[$index] = $category['cat_id'];
        }

        $db = $this->getDatabase();
        $sql = 'SELECT cat_name FROM ' . TBL_CATEGORIES . ' WHERE cat_id = ?';
        foreach ($names as $index => $name) {
            $this->assertEquals($name, $db->queryPrepared($sql, [$ids[$index]])->fetchColumn());
        }
    }

    /**
     * Test that a missing value stays missing
     *
     * @testdox A column without a value is read back as null and can be searched for
     */
    public function testColumnWithoutAValueIsReadBackAsNull(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Null Org', 'nullorg');
        $category = $fixture->createAndSaveCategory('Null Category', 'ROL', $org['org_id']);
        $role = $fixture->createAndSaveRoleInCategory('Null Role', $category['cat_id']);

        $db = $this->getDatabase();
        $sql = 'SELECT rol_description, rol_timestamp_change FROM ' . TBL_ROLES . ' WHERE rol_id = ?';
        $row = $db->queryPrepared($sql, [$role['rol_id']])->fetch();

        $this->assertNull($row['rol_timestamp_change']);

        // and a condition finds it, which a comparison with a value would not
        $sql = 'SELECT COUNT(*) FROM ' . TBL_ROLES . ' WHERE rol_id = ? AND rol_timestamp_change IS NULL';
        $this->assertEquals(1, (int) $db->queryPrepared($sql, [$role['rol_id']])->fetchColumn());
    }

    /**
     * Test that a new record gets an id
     *
     * @testdox Every inserted record is given the next free key
     */
    public function testEveryInsertedRecordIsGivenTheNextFreeKey(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Key Org', 'keyorg');
        $category = $fixture->createAndSaveCategory('Key Category', 'ROL', $org['org_id']);

        $first = $fixture->createAndSaveRoleInCategory('First Role', $category['cat_id']);
        $second = $fixture->createAndSaveRoleInCategory('Second Role', $category['cat_id']);

        $this->assertGreaterThan(0, $first['rol_id']);
        $this->assertGreaterThan($first['rol_id'], $second['rol_id']);

        // the key the insert reports is the key the row actually has
        $sql = 'SELECT rol_name FROM ' . TBL_ROLES . ' WHERE rol_id = ?';
        $this->assertEquals('Second Role', $this->getDatabase()->queryPrepared($sql, [$second['rol_id']])->fetchColumn());
    }

    /**
     * Test that transactions can be nested
     *
     * @testdox Transactions are counted so that an inner one does not commit the outer one
     */
    public function testTransactionsAreCountedSoAnInnerOneDoesNotCommitTheOuter(): void
    {
        $db = $this->getDatabase();

        // the test itself already runs inside a transaction
        $outer = $this->transactionDepth();
        $this->assertGreaterThan(0, $outer);

        $db->startTransaction();
        $this->assertEquals($outer + 1, $this->transactionDepth());

        $db->startTransaction();
        $this->assertEquals($outer + 2, $this->transactionDepth());

        $db->endTransaction();
        $db->endTransaction();

        // back where it started, and the outer transaction is still open
        $this->assertEquals($outer, $this->transactionDepth());
    }

    /**
     * Test that a rollback discards everything
     *
     * @testdox A rollback discards the whole stack of transactions at once
     */
    public function testRollbackDiscardsTheWholeStackAtOnce(): void
    {
        $db = $this->getDatabase();

        $db->startTransaction();
        $db->startTransaction();
        $this->assertGreaterThan(1, $this->transactionDepth());

        $db->rollback();

        // rollback does not unwind one level, it ends every open transaction
        $this->assertEquals(0, $this->transactionDepth());

        // the test has lost its isolation now, so start a fresh transaction for the tear down
        $db->startTransaction();
    }

    /**
     * Test the shortcut that returns rows as an array
     *
     * @testdox A statement can be read straight into an array
     */
    public function testStatementCanBeReadStraightIntoAnArray(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Array Org', 'arrayorg');
        $category = $fixture->createAndSaveCategory('Array Category', 'ROL', $org['org_id']);
        $fixture->createAndSaveRoleInCategory('Array Role', $category['cat_id']);

        $rows = $this->getDatabase()->getArrayFromSql(
            'SELECT rol_name FROM ' . TBL_ROLES . ' WHERE rol_cat_id = ?',
            array($category['cat_id'])
        );

        $this->assertCount(1, $rows);
        $this->assertEquals('Array Role', $rows[0]['rol_name']);
    }

    /**
     * Test that the placeholder list is built for the values
     *
     * @testdox A list of values can be turned into placeholders for a statement
     */
    public function testListOfValuesCanBeTurnedIntoPlaceholders(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('In Org', 'inorg');
        $category = $fixture->createAndSaveCategory('In Category', 'ROL', $org['org_id']);
        $first = $fixture->createAndSaveRoleInCategory('First Role', $category['cat_id']);
        $second = $fixture->createAndSaveRoleInCategory('Second Role', $category['cat_id']);
        $third = $fixture->createAndSaveRoleInCategory('Third Role', $category['cat_id']);

        $wanted = array($first['rol_id'], $third['rol_id']);
        $db = $this->getDatabase();

        $sql = 'SELECT rol_name FROM ' . TBL_ROLES . '
                 WHERE rol_id IN (' . \Admidio\Infrastructure\Database::getQmForValues($wanted) . ')
              ORDER BY rol_name';
        $names = array_column($db->queryPrepared($sql, $wanted)->fetchAll(), 'rol_name');

        $this->assertEquals(array('First Role', 'Third Role'), $names);
        $this->assertNotContains('Second Role', $names);
    }
}
