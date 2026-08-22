<?php
/**
 * User Import Tests
 *
 * Tests the entity behind the contact import. UserImport identifies an existing contact by first
 * and last name, converts the values of the import file into what Admidio stores, and decides from
 * the import mode what happens to a contact that is already there.
 *
 * Importing needs an administrator, so the tests run as the account the installation created.
 */

namespace Admidio\Tests\Integration\Exchange;

use Admidio\Infrastructure\Exception;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserImport;

class UserImportTest extends DatabaseTestCase
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
     *
     * The user with id 1 is the system user and has no rights, so the account has to be looked up.
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
     * Import a contact and return its id.
     *
     * @param array<string,string> $values Profile values by internal field name
     */
    private function import(array $values, int $mode = UserImport::USER_IMPORT_NOT_EDIT): int
    {
        $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
        $import->setImportMode($mode);
        if (isset($values['FIRST_NAME'], $values['LAST_NAME'])) {
            $import->readDataByFirstnameLastName($values['FIRST_NAME'], $values['LAST_NAME']);
        }
        foreach ($values as $field => $value) {
            $import->setValue($field, $value);
        }
        $import->save();

        return (int) $import->getValue('usr_id');
    }

    /**
     * The profile values of a contact as field name => value.
     *
     * @return array<string,string>
     */
    private function profileOf(int $usrId): array
    {
        $sql = 'SELECT usf_name_intern, usd_value FROM ' . TBL_USER_DATA . '
                  INNER JOIN ' . TBL_USER_FIELDS . ' ON usf_id = usd_usf_id
                 WHERE usd_usr_id = ? ORDER BY usf_name_intern';
        $rows = $this->getDatabase()->queryPrepared($sql, [$usrId])->fetchAll();

        return array_combine(array_column($rows, 'usf_name_intern'), array_column($rows, 'usd_value'));
    }

    /**
     * Test that an import creates a contact
     *
     * @testdox An imported contact is created with its profile values
     */
    public function testImportedContactIsCreatedWithItsProfileValues(): void
    {
        $usrId = $this->asAdministrator(function () {
            return $this->import(array('LAST_NAME' => 'Import', 'FIRST_NAME' => 'Ida', 'CITY' => 'Graz'));
        });

        $this->assertGreaterThan(0, $usrId);
        $this->assertEquals(
            array('CITY' => 'Graz', 'FIRST_NAME' => 'Ida', 'LAST_NAME' => 'Import'),
            $this->profileOf($usrId)
        );

        // an imported contact is active straight away, unlike a registration
        $sql = 'SELECT usr_valid FROM ' . TBL_USERS . ' WHERE usr_id = ?';
        $this->assertTrue((bool) $this->getDatabase()->queryPrepared($sql, [$usrId])->fetchColumn());
    }

    /**
     * Test that a contact is recognised again
     *
     * @testdox An existing contact is recognised by first and last name
     */
    public function testExistingContactIsRecognisedByFirstAndLastName(): void
    {
        $usrId = $this->asAdministrator(function () {
            return $this->import(array('LAST_NAME' => 'Import', 'FIRST_NAME' => 'Ida'));
        });

        $found = $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->readDataByFirstnameLastName('Ida', 'Import');

            return (int) $import->getValue('usr_id');
        });
        $this->assertEquals($usrId, $found);

        // a name that is not there leaves an empty object rather than raising
        $missing = $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->readDataByFirstnameLastName('Nobody', 'Here');

            return (int) $import->getValue('usr_id');
        });
        $this->assertEquals(0, $missing);
    }

    /**
     * Test the mode that protects existing data
     *
     * @testdox In the default mode an existing contact is left untouched
     */
    public function testInTheDefaultModeAnExistingContactIsLeftUntouched(): void
    {
        $usrId = $this->asAdministrator(function () {
            return $this->import(array('LAST_NAME' => 'Import', 'FIRST_NAME' => 'Ida', 'CITY' => 'Graz'));
        });

        $refused = $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->setImportMode(UserImport::USER_IMPORT_NOT_EDIT);
            $import->readDataByFirstnameLastName('Ida', 'Import');
            $accepted = $import->setValue('CITY', 'Linz');
            $import->save();

            return $accepted;
        });

        // the value is not even taken over into the object
        $this->assertFalse($refused);
        $this->assertEquals('Graz', $this->profileOf($usrId)['CITY']);
    }

    /**
     * Test the mode that fills the contact in
     *
     * @testdox The completing mode only fills the fields that are still empty
     */
    public function testCompletingModeOnlyFillsTheFieldsThatAreStillEmpty(): void
    {
        $usrId = $this->asAdministrator(function () {
            return $this->import(array('LAST_NAME' => 'Import', 'FIRST_NAME' => 'Ida', 'CITY' => 'Graz'));
        });

        $accepted = $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->setImportMode(UserImport::USER_IMPORT_COMPLETE);
            $import->readDataByFirstnameLastName('Ida', 'Import');
            $accepted = array(
                'CITY' => $import->setValue('CITY', 'Linz'),
                'STREET' => $import->setValue('STREET', 'Hauptstrasse 1')
            );
            $import->save();

            return $accepted;
        });

        $profile = $this->profileOf($usrId);

        // the field that had no value yet is filled
        $this->assertTrue($accepted['STREET']);
        $this->assertEquals('Hauptstrasse 1', $profile['STREET']);

        // the field that already had a value keeps it, this is the mode that is offered as
        // SYS_COMPLEMENT and it must not overwrite what the contact already has
        $this->assertFalse($accepted['CITY']);
        $this->assertEquals('Graz', $profile['CITY']);
    }

    /**
     * Test that the completing mode does not clear a field
     *
     * @testdox The completing mode does not clear a field with an empty column
     */
    public function testCompletingModeDoesNotClearAFieldWithAnEmptyColumn(): void
    {
        $usrId = $this->asAdministrator(function () {
            return $this->import(array('LAST_NAME' => 'Import', 'FIRST_NAME' => 'Ida', 'CITY' => 'Graz'));
        });

        $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->setImportMode(UserImport::USER_IMPORT_COMPLETE);
            $import->readDataByFirstnameLastName('Ida', 'Import');
            $import->setValue('CITY', '');
            $import->save();
        });

        $this->assertEquals('Graz', $this->profileOf($usrId)['CITY']);
    }

    /**
     * Test the mode that replaces the contact
     *
     * @testdox The displacing mode clears the old values before it writes
     */
    public function testDisplacingModeClearsTheOldValuesBeforeItWrites(): void
    {
        $usrId = $this->asAdministrator(function () {
            return $this->import(array(
                'LAST_NAME' => 'Import',
                'FIRST_NAME' => 'Ida',
                'CITY' => 'Graz',
                'STREET' => 'Hauptstrasse 1'
            ));
        });
        $this->assertArrayHasKey('STREET', $this->profileOf($usrId));

        $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->setImportMode(UserImport::USER_IMPORT_DISPLACE);
            $import->readDataByFirstnameLastName('Ida', 'Import');
            $import->setValue('LAST_NAME', 'Import');
            $import->setValue('FIRST_NAME', 'Ida');
            $import->setValue('CITY', 'Salzburg');
            $import->save();
        });

        $profile = $this->profileOf($usrId);
        $this->assertEquals('Salzburg', $profile['CITY']);

        // the field the import file does not mention is gone, not kept
        $this->assertArrayNotHasKey('STREET', $profile);
    }

    /**
     * Test the mode that keeps both contacts
     *
     * @testdox The duplicating mode creates a second contact of the same name
     */
    public function testDuplicatingModeCreatesASecondContact(): void
    {
        $firstId = $this->asAdministrator(function () {
            return $this->import(array('LAST_NAME' => 'Import', 'FIRST_NAME' => 'Ida', 'CITY' => 'Graz'));
        });

        $secondId = $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->setImportMode(UserImport::USER_IMPORT_DUPLICATE);
            $import->readDataByFirstnameLastName('Ida', 'Import');
            $import->setValue('LAST_NAME', 'Import');
            $import->setValue('FIRST_NAME', 'Ida');
            $import->setValue('CITY', 'Innsbruck');
            $import->save();

            return (int) $import->getValue('usr_id');
        });

        $this->assertNotEquals($firstId, $secondId);
        $this->assertEquals('Graz', $this->profileOf($firstId)['CITY']);
        $this->assertEquals('Innsbruck', $this->profileOf($secondId)['CITY']);
    }

    /**
     * Test that the country is stored as its code
     *
     * @testdox A country name from the import file is stored as its iso code
     */
    public function testCountryNameIsStoredAsItsIsoCode(): void
    {
        $usrId = $this->asAdministrator(function () {
            return $this->import(array('LAST_NAME' => 'Country', 'FIRST_NAME' => 'Carla', 'COUNTRY' => 'Austria'));
        });

        $this->assertEquals('AUT', $this->profileOf($usrId)['COUNTRY']);
    }

    /**
     * Test that an unusable value is dropped
     *
     * @testdox A value that does not fit its field is not taken over
     */
    public function testValueThatDoesNotFitItsFieldIsNotTakenOver(): void
    {
        $accepted = $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);

            return array(
                'invalid' => $import->setValue('EMAIL', 'not-an-address'),
                'afterInvalid' => $import->getValue('EMAIL'),
                'valid' => $import->setValue('EMAIL', 'someone@example.org'),
                'afterValid' => $import->getValue('EMAIL')
            );
        });

        // the import does not stop, the unusable value is simply not taken over
        $this->assertFalse($accepted['invalid']);
        $this->assertEquals('', $accepted['afterInvalid']);
        $this->assertTrue($accepted['valid']);
        $this->assertEquals('someone@example.org', $accepted['afterValid']);
    }

    /**
     * Test that a yes or no column becomes a flag
     *
     * @testdox An affirmative wording in a checkbox column ticks the box
     */
    public function testAffirmativeWordingInACheckboxColumnTicksTheBox(): void
    {
        $checkboxField = $this->fieldOfType('CHECKBOX');
        if ($checkboxField === null) {
            $this->markTestSkipped('The installation provides no checkbox profile field.');
        }

        $values = $this->asAdministrator(function () use ($checkboxField) {
            $result = array();
            foreach (array('yes', 'y', 'j', '1', 'no', 'n', '0') as $input) {
                $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
                $import->setValue($checkboxField, $input);
                $result[$input] = $import->getValue($checkboxField, 'database');
            }

            return $result;
        });

        // the import file may say yes in several ways, including the German one
        $this->assertEquals(array('1', '1', '1', '1'), array($values['yes'], $values['y'], $values['j'], $values['1']));

        // a negative wording leaves the box empty, which is how an unticked box is stored
        $this->assertEquals(array('', '', ''), array($values['no'], $values['n'], $values['0']));
    }

    /**
     * Test that login data needs an administrator
     *
     * @testdox Login data can only be imported by an administrator
     */
    public function testLoginDataCanOnlyBeImportedByAnAdministrator(): void
    {
        $fixture = $this->getFixture();
        $plain = $fixture->createAndSaveUser('importplain', 'ip@example.local');
        $plainUser = $this->loadUserInOrganization($plain['usr_id'], self::ORG_ID);

        $this->withCurrentUser($plainUser, self::ORG_ID, true, function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->setValue('LAST_NAME', 'Login');
            $import->setValue('FIRST_NAME', 'Leo');

            $this->expectException(Exception::class);
            $import->setLoginData('leo', 'A-Strong-Password-42');
        });
    }

    /**
     * Test that an imported password has to be usable
     *
     * @testdox An imported password has to meet the minimum length
     */
    public function testImportedPasswordHasToMeetTheMinimumLength(): void
    {
        $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->setValue('LAST_NAME', 'Login');
            $import->setValue('FIRST_NAME', 'Leo');

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('minimum length');
            $import->setLoginData('leo', 'short');
        });
    }

    /**
     * Test that login data is stored and not overwritten
     *
     * @testdox Login data is stored once and a second attempt is refused
     */
    public function testLoginDataIsStoredOnceAndASecondAttemptIsRefused(): void
    {
        $this->asAdministrator(function () {
            $import = new UserImport($this->getDatabase(), $GLOBALS['gProfileFields']);
            $import->setValue('LAST_NAME', 'Login');
            $import->setValue('FIRST_NAME', 'Leo');
            $import->setLoginData('leo', 'A-Strong-Password-42');

            $this->assertEquals('leo', $import->getValue('usr_login_name'));
            $this->assertNotEmpty($import->getValue('usr_password'));

            // a contact that already has a login must not get a second one from an import file
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('already has a username and password');
            $import->setLoginData('leo2', 'Another-Strong-Password-42');
        });
    }

    /**
     * The internal name of the first profile field of the given type, or null.
     */
    private function fieldOfType(string $type): ?string
    {
        $sql = 'SELECT usf_name_intern FROM ' . TBL_USER_FIELDS . ' WHERE usf_type = ? ORDER BY usf_id';
        $name = $this->getDatabase()->queryPrepared($sql, [$type])->fetchColumn();

        return $name === false ? null : (string) $name;
    }
}
