<?php
/**
 * Profile Field Value Tests
 *
 * Tests the profile data of a user: the values do not live in adm_users but as one row per field
 * in adm_user_data, and the field definition decides who may write them and what is accepted.
 */

namespace Admidio\Tests\Integration\ProfileFields;

use Admidio\Infrastructure\Exception;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class ProfileFieldValueTest extends DatabaseTestCase
{
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Read one profile value straight from the database.
     */
    private function storedValue(int $usrId, string $fieldNameIntern): ?string
    {
        $sql = 'SELECT usd_value FROM ' . TBL_USER_DATA . '
                  INNER JOIN ' . TBL_USER_FIELDS . ' ON usf_id = usd_usf_id
                 WHERE usd_usr_id = ? AND usf_name_intern = ?';
        $row = $this->getDatabase()->queryPrepared($sql, [$usrId, $fieldNameIntern])->fetch();

        return $row === false ? null : $row['usd_value'];
    }

    /**
     * Write profile values on an existing user, bypassing the edit-rights check.
     */
    private function writeProfile(int $usrId, int $orgId, array $values): void
    {
        $this->withOrganization($orgId, function () use ($usrId, $values) {
            $user = new User($this->getDatabase());
            $user->readDataById($usrId);
            // no $gCurrentUser is logged in, so the rights check has to be bypassed
            $user->saveChangesWithoutRights();
            foreach ($values as $field => $value) {
                $user->setValue($field, $value);
            }
            $user->save();
        });
    }

    /**
     * Test that profile values are stored as user data
     *
     * @testdox Profile values are stored as one row per field in adm_user_data
     */
    public function testProfileValuesAreStoredAsUserData(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('PF Org', 'pforg');
        $user = $fixture->createAndSaveUser('pfuser', 'pf@example.local');

        $this->writeProfile($user['usr_id'], $org['org_id'], [
            'LAST_NAME' => 'Muster',
            'FIRST_NAME' => 'Erika',
            'CITY' => 'Wien',
        ]);

        $this->assertEquals('Muster', $this->storedValue($user['usr_id'], 'LAST_NAME'));
        $this->assertEquals('Erika', $this->storedValue($user['usr_id'], 'FIRST_NAME'));
        $this->assertEquals('Wien', $this->storedValue($user['usr_id'], 'CITY'));

        // exactly the three fields that were written, no rows for the untouched ones
        $sql = 'SELECT COUNT(*) AS count FROM ' . TBL_USER_DATA . ' WHERE usd_usr_id = ?';
        $count = (int) $this->getDatabase()->queryPrepared($sql, [$user['usr_id']])->fetch()['count'];
        $this->assertEquals(3, $count);
    }

    /**
     * Test that the values survive a round trip through the entity
     *
     * @testdox Profile values are read back through a fresh User object
     */
    public function testProfileValuesAreReadBack(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('PF Org', 'pforg');
        $user = $fixture->createAndSaveUser('pfuser', 'pf@example.local');

        $this->writeProfile($user['usr_id'], $org['org_id'], [
            'LAST_NAME' => 'Muster',
            'EMAIL' => 'erika@example.local',
        ]);

        $reread = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        $this->assertEquals('Muster', $reread->getValue('LAST_NAME'));
        $this->assertEquals('erika@example.local', $reread->getValue('EMAIL'));
    }

    /**
     * Test the value of a field that was never written
     *
     * @testdox A profile field that was never written reads as an empty string
     */
    public function testUnwrittenFieldIsEmpty(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('PF Org', 'pforg');
        $user = $fixture->createAndSaveUser('pfuser', 'pf@example.local');

        $reread = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        $this->assertSame('', $reread->getValue('CITY'));
        $this->assertNull($this->storedValue($user['usr_id'], 'CITY'));
    }

    /**
     * Test that a changed value replaces the old one
     *
     * @testdox Changing a profile value updates its row instead of adding one
     */
    public function testChangingAValueUpdatesTheSameRow(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('PF Org', 'pforg');
        $user = $fixture->createAndSaveUser('pfuser', 'pf@example.local');

        $this->writeProfile($user['usr_id'], $org['org_id'], ['CITY' => 'Wien']);
        $this->writeProfile($user['usr_id'], $org['org_id'], ['CITY' => 'Graz']);

        $this->assertEquals('Graz', $this->storedValue($user['usr_id'], 'CITY'));

        $sql = 'SELECT COUNT(*) AS count FROM ' . TBL_USER_DATA . '
                  INNER JOIN ' . TBL_USER_FIELDS . ' ON usf_id = usd_usf_id
                 WHERE usd_usr_id = ? AND usf_name_intern = ?';
        $count = (int) $this->getDatabase()->queryPrepared($sql, [$user['usr_id'], 'CITY'])->fetch()['count'];
        $this->assertEquals(1, $count);
    }

    /**
     * Test that the field type is validated
     *
     * @testdox An address that does not fit the EMAIL field type is refused
     */
    public function testInvalidEmailIsRefused(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('PF Org', 'pforg');
        $user = $fixture->createAndSaveUser('pfuser', 'pf@example.local');

        $this->expectException(Exception::class);

        $this->writeProfile($user['usr_id'], $org['org_id'], ['EMAIL' => 'not-an-email']);
    }

    /**
     * Test that a well formed address is accepted
     *
     * @testdox A well formed address is accepted for the EMAIL field
     */
    public function testValidEmailIsAccepted(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('PF Org', 'pforg');
        $user = $fixture->createAndSaveUser('pfuser', 'pf@example.local');

        $this->writeProfile($user['usr_id'], $org['org_id'], ['EMAIL' => 'erika@example.local']);

        $this->assertEquals('erika@example.local', $this->storedValue($user['usr_id'], 'EMAIL'));
    }

    /**
     * Test that a disabled field is protected.
     * LAST_NAME and FIRST_NAME are installed with usf_disabled = 1, so only a user who may edit
     * the profile can write them. Without that right setValue() refuses and answers false.
     *
     * @testdox A disabled profile field is not written without the right to edit the profile
     */
    public function testDisabledFieldIsRefusedWithoutEditRight(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('PF Org', 'pforg');
        // hasRightEditProfile() consults the user relation setting of the organization
        $fixture->seedDefaultPreferences($org['org_id']);

        $target = $fixture->createAndSaveUser('pftarget', 'pt@example.local');
        $plain = $fixture->createAndSaveUser('pfplain', 'pp@example.local');

        $plainUser = $this->loadUserInOrganization($plain['usr_id'], $org['org_id']);

        $accepted = $this->withCurrentUser($plainUser, $org['org_id'], true, function () use ($target) {
            $user = new User($this->getDatabase());
            $user->readDataById($target['usr_id']);

            // LAST_NAME is a disabled field and this user may not edit the profile
            return $user->setValue('LAST_NAME', 'Sneaky');
        });

        $this->assertFalse($accepted);
        $this->assertNull($this->storedValue($target['usr_id'], 'LAST_NAME'));
    }

    /**
     * Test that the right opens the disabled field
     *
     * @testdox A user with rol_edit_user may write a disabled profile field
     */
    public function testDisabledFieldIsWritableWithEditRight(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('PF Org', 'pforg');
        // hasRightEditProfile() consults the user relation setting of the organization
        $fixture->seedDefaultPreferences($org['org_id']);

        $role = $fixture->createAndSaveRoleWithRights('User Admins', $org['org_id'], ['rol_edit_user' => 1]);

        $editor = $fixture->createAndSaveUser('pfeditor', 'pe@example.local');
        $target = $fixture->createAndSaveUser('pftarget', 'pt@example.local');
        $fixture->assignUserToRole($editor['usr_id'], $role['rol_id']);

        $editorUser = $this->loadUserInOrganization($editor['usr_id'], $org['org_id']);

        $accepted = $this->withCurrentUser($editorUser, $org['org_id'], true, function () use ($target) {
            $user = new User($this->getDatabase());
            $user->readDataById($target['usr_id']);
            $result = $user->setValue('LAST_NAME', 'Muster');
            $user->save();

            return $result;
        });

        $this->assertTrue($accepted);
        $this->assertEquals('Muster', $this->storedValue($target['usr_id'], 'LAST_NAME'));
    }

    /**
     * Test that profile data belongs to one user only
     *
     * @testdox Profile values of one user do not appear on another
     */
    public function testProfileValuesArePerUser(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('PF Org', 'pforg');
        $one = $fixture->createAndSaveUser('pfone', 'p1@example.local');
        $two = $fixture->createAndSaveUser('pftwo', 'p2@example.local');

        $this->writeProfile($one['usr_id'], $org['org_id'], ['CITY' => 'Wien']);
        $this->writeProfile($two['usr_id'], $org['org_id'], ['CITY' => 'Graz']);

        $this->assertEquals('Wien', $this->storedValue($one['usr_id'], 'CITY'));
        $this->assertEquals('Graz', $this->storedValue($two['usr_id'], 'CITY'));
    }

    /**
     * Test that deleting a user removes their profile data
     *
     * @testdox Deleting a user deletes their profile values
     */
    public function testDeletingUserRemovesProfileValues(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('PF Org', 'pforg');
        $user = $fixture->createAndSaveUser('pfuser', 'pf@example.local');

        $this->writeProfile($user['usr_id'], $org['org_id'], ['CITY' => 'Wien', 'STREET' => 'Hauptstrasse 1']);

        $countSql = 'SELECT COUNT(*) AS count FROM ' . TBL_USER_DATA . ' WHERE usd_usr_id = ?';
        $this->assertEquals(
            2,
            (int) $this->getDatabase()->queryPrepared($countSql, [$user['usr_id']])->fetch()['count']
        );

        $this->assertTrue($fixture->deleteUser($user['usr_id']));

        $this->assertEquals(
            0,
            (int) $this->getDatabase()->queryPrepared($countSql, [$user['usr_id']])->fetch()['count']
        );
    }

    /**
     * Test that the installed field definitions are available
     *
     * @testdox The installation provides the system profile fields
     */
    public function testSystemProfileFieldsExist(): void
    {
        $sql = 'SELECT usf_name_intern, usf_type FROM ' . TBL_USER_FIELDS . ' WHERE usf_name_intern IN (?, ?, ?)';
        $rows = $this->getDatabase()->queryPrepared($sql, ['LAST_NAME', 'FIRST_NAME', 'EMAIL'])->fetchAll();

        $types = array_column($rows, 'usf_type', 'usf_name_intern');

        $this->assertEquals('TEXT', $types['LAST_NAME']);
        $this->assertEquals('TEXT', $types['FIRST_NAME']);
        $this->assertEquals('EMAIL', $types['EMAIL']);
    }
}
