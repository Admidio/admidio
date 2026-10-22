<?php
/**
 * Case Insensitive Lookup Tests
 *
 * Tests the lookups that identify a record by a name the user typed. MySQL folds the case through
 * the collation of its columns, PostgreSQL compares byte by byte, so a lookup that relies on the
 * collation answers differently on the two engines. These tests assert the behaviour Admidio wants,
 * which is the same answer everywhere; on MySQL they would also pass without the explicit folding,
 * on PostgreSQL they only pass with it.
 */

namespace Admidio\Tests\Integration\Database;

use Admidio\Categories\Entity\Category;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Organizations\Entity\Organization;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserImport;

class CaseInsensitiveLookupTest extends DatabaseTestCase
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
     * Test that the short name identifies the organization whatever the case
     *
     * @testdox An organization is found by its short name whatever the case
     */
    public function testAnOrganizationIsFoundByItsShortNameWhateverTheCase(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Case Lookup Org', 'caselook');

        foreach (array('caselook', 'CASELOOK', 'CaseLook') as $spelling) {
            $found = new Organization($this->getDatabase(), $spelling);

            $this->assertEquals(
                $org['org_id'],
                (int) $found->getValue('org_id'),
                'The short name "' . $spelling . '" must find the organization.'
            );
        }

        // a short name that does not exist is still not found, and the object keeps the name that
        // was searched for, which the creation of an organization relies on
        $missing = new Organization($this->getDatabase(), 'nosuchorg');
        $this->assertEquals(0, (int) $missing->getValue('org_id'));
        $this->assertEquals('nosuchorg', $missing->getValue('org_shortname'));
    }

    /**
     * Test that a category name that differs only in case is numbered
     *
     * @testdox An internal category name is numbered when one differs only in case
     */
    public function testAnInternalCategoryNameIsNumberedWhenOneDiffersOnlyInCase(): void
    {
        $names = $this->asAdministrator(function () {
            $names = array();

            foreach (array('Case Category', 'case category') as $name) {
                $category = new Category($this->getDatabase());
                $category->setValue('cat_name', $name);
                $category->setValue('cat_type', 'ANN');
                $category->setValue('cat_org_id', self::ORG_ID);
                $category->save();

                $names[] = $category->getValue('cat_name_intern');
            }

            return $names;
        });

        // both spellings become the same internal name, so the second one has to be numbered
        $this->assertEquals('CASE_CATEGORY', $names[0]);
        $this->assertEquals('CASE_CATEGORY_2', $names[1]);
    }

    /**
     * Test that an item field name that differs only in case is numbered
     *
     * @testdox An internal item field name is numbered when one differs only in case
     */
    public function testAnInternalItemFieldNameIsNumberedWhenOneDiffersOnlyInCase(): void
    {
        $names = $this->asAdministrator(function () {
            $names = array();

            foreach (array('Case Field', 'case field') as $name) {
                $field = new ItemField($this->getDatabase());
                $field->setValue('inf_org_id', self::ORG_ID);
                $field->setValue('inf_type', 'TEXT');
                $field->setValue('inf_name', $name);
                $field->save();

                $names[] = $field->getValue('inf_name_intern');
            }

            return $names;
        });

        $this->assertEquals('CASE_FIELD', $names[0]);
        $this->assertEquals('CASE_FIELD_2', $names[1]);
    }

    /**
     * Test that the import recognises a contact whose name is spelled differently
     *
     * @testdox The import finds an existing contact whatever the case of the name
     */
    public function testTheImportFindsAnExistingContactWhateverTheCaseOfTheName(): void
    {
        $usrId = $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->setImportMode(UserImport::USER_IMPORT_DISPLACE);
            $import->readDataByFirstnameLastName('Anneliese', 'Grossmann');
            $import->setValue('FIRST_NAME', 'Anneliese');
            $import->setValue('LAST_NAME', 'Grossmann');
            $import->setValue('CITY', 'Graz');
            $import->save();

            return (int) $import->getValue('usr_id');
        });
        $this->assertGreaterThan(0, $usrId);

        // the same contact written differently must not create a second record
        $secondId = $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->setImportMode(UserImport::USER_IMPORT_DISPLACE);
            $import->readDataByFirstnameLastName('ANNELIESE', 'grossmann');
            $import->setValue('FIRST_NAME', 'Anneliese');
            $import->setValue('LAST_NAME', 'Grossmann');
            $import->setValue('CITY', 'Linz');
            $import->save();

            return (int) $import->getValue('usr_id');
        });

        $this->assertEquals($usrId, $secondId, 'The differently spelled name must find the same contact.');
    }
}
