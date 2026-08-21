<?php
/**
 * Profile Field Definition Tests
 *
 * Tests the definition of the profile fields themselves, as opposed to the values users have for
 * them. An organization can add fields of several types to the profile, group them in categories
 * and mark them as mandatory; the fields Admidio delivers are marked as system fields and are
 * protected against deletion.
 *
 * Profile fields are shared by every organization: their categories carry no organization.
 */

namespace Admidio\Tests\Integration\ProfileFields;

use Admidio\Infrastructure\Exception;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class ProfileFieldDefinitionTest extends DatabaseTestCase
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
     * Run a callback as the administrator of the installed organization.
     */
    private function asAdministrator(callable $callback)
    {
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $usrId = (int) $this->getDatabase()->queryPrepared($sql, ['admin'])->fetchColumn();
        $administrator = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);

        return $this->withCurrentUser($administrator, self::ORG_ID, true, $callback);
    }

    /**
     * The category the delivered profile fields are grouped in.
     */
    private function profileCategoryId(): int
    {
        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . " WHERE cat_type = 'USF' ORDER BY cat_id";

        return (int) $this->getDatabase()->queryPrepared($sql)->fetchColumn();
    }

    /**
     * Add a profile field of the given type.
     */
    private function addField(string $name, string $type, array $values = array()): ProfileField
    {
        $field = new ProfileField($this->getDatabase());
        $field->setValue('usf_cat_id', $this->profileCategoryId());
        $field->setValue('usf_type', $type);
        $field->setValue('usf_name', $name);
        foreach ($values as $column => $value) {
            $field->setValue($column, $value);
        }
        $field->save();

        return $field;
    }

    /**
     * The stored row of a profile field.
     */
    private function storedField(int $usfId)
    {
        $sql = 'SELECT usf_cat_id, usf_type, usf_name, usf_name_intern, usf_system, usf_required_input, usf_usr_id_create
                  FROM ' . TBL_USER_FIELDS . ' WHERE usf_id = ?';

        return $this->getDatabase()->queryPrepared($sql, [$usfId])->fetch();
    }

    /**
     * Test that the profile fields belong to no organization
     *
     * @testdox The categories of the profile fields belong to no organization
     */
    public function testCategoriesOfTheProfileFieldsBelongToNoOrganization(): void
    {
        $sql = 'SELECT cat_org_id, cat_name_intern FROM ' . TBL_CATEGORIES . " WHERE cat_type = 'USF'";
        $categories = $this->getDatabase()->queryPrepared($sql)->fetchAll();

        $this->assertNotEmpty($categories);
        foreach ($categories as $category) {
            // the profile is the same everywhere, just like the user records themselves
            $this->assertNull($category['cat_org_id'], $category['cat_name_intern']);
        }
    }

    /**
     * Test that a field can be added
     *
     * @testdox A new profile field gets an internal name, a unique id and a position
     */
    public function testNewProfileFieldGetsAnInternalNameAndPosition(): void
    {
        $field = $this->asAdministrator(fn () => $this->addField('Nick name', 'TEXT'));

        $this->assertEquals('NICK_NAME', $field->getValue('usf_name_intern'));
        $this->assertNotEmpty($field->getValue('usf_uuid'));
        $this->assertGreaterThan(0, (int) $field->getValue('usf_sequence'));

        $row = $this->storedField((int) $field->getValue('usf_id'));
        $this->assertEquals('Nick name', $row['usf_name']);
        $this->assertEquals('TEXT', $row['usf_type']);
        $this->assertEquals($this->profileCategoryId(), (int) $row['usf_cat_id']);

        // a field somebody added is not a system field
        $this->assertFalse((bool) $row['usf_system']);

        // and it records who added it
        $this->assertGreaterThan(0, (int) $row['usf_usr_id_create']);
    }

    /**
     * Test that internal names stay unique
     *
     * @testdox A second profile field with the same name gets a numbered internal name
     */
    public function testSecondProfileFieldWithTheSameNameGetsANumberedInternalName(): void
    {
        $names = $this->asAdministrator(function () {
            return array(
                $this->addField('Nick name', 'TEXT')->getValue('usf_name_intern'),
                $this->addField('Nick name', 'TEXT')->getValue('usf_name_intern'),
                $this->addField('Nick name', 'TEXT')->getValue('usf_name_intern')
            );
        });

        $this->assertEquals(array('NICK_NAME', 'NICK_NAME_2', 'NICK_NAME_3'), $names);
    }

    /**
     * Test that the field types are stored
     *
     * @testdox A profile field can be added for each of the offered types
     */
    public function testProfileFieldCanBeAddedForEachOfTheOfferedTypes(): void
    {
        $types = array('TEXT', 'TEXT_BIG', 'DATE', 'EMAIL', 'URL', 'PHONE', 'NUMBER', 'CHECKBOX', 'DROPDOWN');

        $stored = $this->asAdministrator(function () use ($types) {
            $result = array();
            foreach ($types as $index => $type) {
                $field = $this->addField('Custom ' . $index, $type);
                $result[$type] = $this->storedField((int) $field->getValue('usf_id'))['usf_type'];
            }

            return $result;
        });

        foreach ($types as $type) {
            $this->assertEquals($type, $stored[$type], 'A profile field of type ' . $type . ' could not be added.');
        }
    }

    /**
     * Test that a selection field carries its options
     *
     * @testdox A dropdown profile field stores its options in the order they were given
     */
    public function testDropdownProfileFieldStoresItsOptions(): void
    {
        $usfId = $this->asAdministrator(function () {
            $field = $this->addField('Shirt size', 'DROPDOWN');
            $field->setSelectOptions(array(
                array('value' => 'Small'),
                array('value' => 'Medium'),
                array('value' => 'Large')
            ));

            return (int) $field->getValue('usf_id');
        });

        $sql = 'SELECT ufo_value, ufo_sequence, ufo_obsolete FROM ' . TBL_USER_FIELD_OPTIONS . '
                 WHERE ufo_usf_id = ? ORDER BY ufo_sequence';
        $options = $this->getDatabase()->queryPrepared($sql, [$usfId])->fetchAll();

        $this->assertEquals(array('Small', 'Medium', 'Large'), array_column($options, 'ufo_value'));
        $this->assertEquals(array(1, 2, 3), array_map('intval', array_column($options, 'ufo_sequence')));

        // a fresh option is offered for new entries
        foreach ($options as $option) {
            $this->assertFalse((bool) $option['ufo_obsolete']);
        }
    }

    /**
     * Test that a field can be made mandatory
     *
     * @testdox A profile field can be marked as required
     */
    public function testProfileFieldCanBeMarkedAsRequired(): void
    {
        $result = $this->asAdministrator(function () {
            $optional = $this->addField('Optional field', 'TEXT');
            $required = $this->addField('Required field', 'TEXT', array(
                'usf_required_input' => ProfileField::USER_FIELD_REQUIRED_INPUT_YES
            ));

            return array(
                'optionalStored' => (int) $this->storedField((int) $optional->getValue('usf_id'))['usf_required_input'],
                'requiredStored' => (int) $this->storedField((int) $required->getValue('usf_id'))['usf_required_input'],
                'optionalAsks' => $optional->hasRequiredInput(),
                'requiredAsks' => $required->hasRequiredInput()
            );
        });

        $this->assertEquals(ProfileField::USER_FIELD_REQUIRED_INPUT_NO, $result['optionalStored']);
        $this->assertEquals(ProfileField::USER_FIELD_REQUIRED_INPUT_YES, $result['requiredStored']);
        $this->assertFalse($result['optionalAsks']);
        $this->assertTrue($result['requiredAsks']);
    }

    /**
     * Test the mode that only applies to the registration form
     *
     * @testdox A profile field can be required only while somebody registers
     */
    public function testProfileFieldCanBeRequiredOnlyWhileSomebodyRegisters(): void
    {
        $result = $this->asAdministrator(function () {
            $field = $this->addField('Registration field', 'TEXT', array(
                'usf_required_input' => ProfileField::USER_FIELD_REQUIRED_INPUT_ONLY_REGISTRATION
            ));

            return array(
                'onRegistration' => $field->hasRequiredInput(0, true),
                'afterwards' => $field->hasRequiredInput(0, false)
            );
        });

        $this->assertTrue($result['onRegistration']);
        $this->assertFalse($result['afterwards']);
    }

    /**
     * Test that the delivered fields are protected
     *
     * @testdox A profile field that Admidio delivers cannot be deleted
     */
    public function testProfileFieldThatAdmidioDeliversCannotBeDeleted(): void
    {
        $this->asAdministrator(function () {
            $field = new ProfileField($this->getDatabase());
            $field->readDataByColumns(array('usf_name_intern' => 'LAST_NAME'));

            $this->assertTrue((bool) $field->getValue('usf_system'));

            $this->expectException(Exception::class);
            $field->delete();
        });
    }

    /**
     * Test that an added field can be removed again
     *
     * @testdox A profile field that was added can be deleted again
     */
    public function testProfileFieldThatWasAddedCanBeDeletedAgain(): void
    {
        $usfId = $this->asAdministrator(function () {
            $field = $this->addField('Temporary field', 'TEXT');
            $usfId = (int) $field->getValue('usf_id');

            $reread = new ProfileField($this->getDatabase(), $usfId);
            $this->assertTrue($reread->delete());

            return $usfId;
        });

        $sql = 'SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_id = ?';
        $this->assertFalse($this->getDatabase()->queryPrepared($sql, [$usfId])->fetch());
    }

    /**
     * Test that deleting a field removes the values people had for it
     *
     * @testdox Deleting a profile field removes the values the users had for it
     */
    public function testDeletingAProfileFieldRemovesTheValuesTheUsersHadForIt(): void
    {
        $fixture = $this->getFixture();
        $user = $fixture->createAndSaveUser('fielduser', 'fu@example.local');

        $usfId = $this->asAdministrator(function () use ($user) {
            $field = $this->addField('Temporary field', 'TEXT');
            $usfId = (int) $field->getValue('usf_id');

            // the profile fields have to be read again so that the new field is known
            $entity = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $user['usr_id']);
            $entity->saveChangesWithoutRights();
            $entity->setValue('LAST_NAME', 'Fielduser');
            $entity->save();

            $sql = 'INSERT INTO ' . TBL_USER_DATA . ' (usd_usr_id, usd_usf_id, usd_value) VALUES (?, ?, ?)';
            $this->getDatabase()->queryPrepared($sql, [$user['usr_id'], $usfId, 'a value']);

            $reread = new ProfileField($this->getDatabase(), $usfId);
            $reread->delete();

            return $usfId;
        });

        $sql = 'SELECT COUNT(*) FROM ' . TBL_USER_DATA . ' WHERE usd_usf_id = ?';
        $this->assertEquals(0, (int) $this->getDatabase()->queryPrepared($sql, [$usfId])->fetchColumn());
    }
}
