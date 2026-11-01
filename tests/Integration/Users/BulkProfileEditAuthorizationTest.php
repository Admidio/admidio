<?php

namespace Admidio\Tests\Integration\Users;

use Admidio\Infrastructure\Exception;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\PermissionContext;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Users\Entity\User;
use Admidio\Users\Service\ProfileEditService;

class BulkProfileEditAuthorizationTest extends AdministratorTestCase
{
    use PermissionContext;

    private const PRIVATE_FIELD = 'BULK_PRIVATE_ID';
    private const PUBLIC_FIELD = 'BULK_PUBLIC_NOTE';
    private const SECRET = 'TH-SECRET-9912';

    private User $leader;
    private User $member;
    private ProfileFields $profileFields;

    protected function setUp(): void
    {
        parent::setUp();

        $db = $this->getDatabase();
        $fixture = new AdmidioTestFixture($db);
        $orgId = (int)$GLOBALS['gCurrentOrgId'];
        $role = $fixture->createAndSaveRoleWithRights('Bulk edit team', $orgId, array(
            'rol_leader_rights' => Role::ROLE_LEADER_MEMBERS_EDIT,
            'rol_profile' => 1
        ));
        $hrRole = $fixture->createAndSaveRole('Private HR access', $orgId);
        $publicCategory = $fixture->createAndSaveCategory('Bulk public data', 'USF');
        $privateCategory = $fixture->createAndSaveCategory('Private HR', 'USF');
        (new RolesRights($db, 'category_view', $privateCategory['cat_id']))->addRoles(array($hrRole['rol_id']));

        foreach (array(
            array('Bulk private ID', $privateCategory['cat_id']),
            array('Bulk public note', $publicCategory['cat_id'])
        ) as [$name, $categoryId]) {
            $field = new ProfileField($db);
            $field->setValue('usf_name', $name);
            $field->setValue('usf_type', 'TEXT');
            $field->setValue('usf_cat_id', $categoryId);
            $field->save();
        }

        $leader = $fixture->createAndSaveUser('bulk-leader', 'bulk-leader@example.local');
        $member = $fixture->createAndSaveUser('bulk-member', 'bulk-member@example.local');
        $fixture->assignUserToRolePeriod($leader['usr_id'], $role['rol_id'], DATE_NOW, DATE_MAX, true);
        $fixture->assignUserToRole($member['usr_id'], $role['rol_id']);

        $this->profileFields = new ProfileFields($db, $orgId);
        $this->leader = new User($db, $this->profileFields, $leader['usr_id']);
        $this->member = new User($db, $this->profileFields, $member['usr_id']);
        foreach (array($this->leader, $this->member) as $user) {
            $user->setValue(self::PRIVATE_FIELD, self::SECRET);
            $user->setValue(self::PUBLIC_FIELD, 'Original note');
            $user->save();
        }
    }

    private function asLeader(callable $callback): void
    {
        $this->withCurrentUser($this->leader, (int)$GLOBALS['gCurrentOrgId'], true, $callback);
    }

    private function createForm(array $fields): FormPresenter
    {
        $form = new FormPresenter('adm_profile_edit_selection_form', 'modules/profile.edit.selection.tpl');
        foreach ($fields as $name) {
            $form->addInput($name, $name, '', array('toggleable' => true));
        }
        return $form;
    }

    public function testBulkFieldsRespectRestrictedCategories(): void
    {
        $this->asLeader(function () {
            // The leader can manage this member, but cannot view or edit the HR category.
            $this->assertFalse($this->leader->isAdministratorUsers());
            $this->assertTrue($this->leader->hasRightEditProfile($this->member));
            $this->assertFalse($this->leader->allowedViewProfileField($this->member, self::PRIVATE_FIELD));
            $this->assertFalse($this->leader->allowedEditProfileField($this->member, self::PRIVATE_FIELD));
            $this->assertSame(self::SECRET, $this->member->getValue(self::PRIVATE_FIELD));

            $service = new ProfileEditService($this->leader);
            $fields = $service->getEditableFields($this->profileFields, array($this->member));
            $this->assertArrayNotHasKey(self::PRIVATE_FIELD, $fields);
            $this->assertArrayHasKey(self::PUBLIC_FIELD, $fields);
        });
    }

    public function testMixedSelectionUsesPermissionsForEveryTargetRegardlessOfOrder(): void
    {
        $this->asLeader(function () {
            $service = new ProfileEditService($this->leader);
            // The own-profile exception must not reveal another member's restricted field.
            $this->assertTrue($this->leader->allowedEditProfileField($this->leader, self::PRIVATE_FIELD));
            $this->assertArrayHasKey(self::PRIVATE_FIELD, $service->getEditableFields($this->profileFields, array($this->leader)));
            foreach (array(array($this->leader, $this->member), array($this->member, $this->leader)) as $users) {
                $fields = $service->getEditableFields($this->profileFields, $users);
                $this->assertArrayNotHasKey(self::PRIVATE_FIELD, $fields);
                $this->assertArrayHasKey(self::PUBLIC_FIELD, $fields);
            }
        });
    }

    public function testForgedPrivateFieldIsRejectedByTheFilteredForm(): void
    {
        $this->asLeader(function () {
            $fields = (new ProfileEditService($this->leader))->getEditableFields($this->profileFields, array($this->member));
            $form = $this->createForm(array_keys($fields));

            $this->expectException(Exception::class);
            $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'toggle_' . self::PRIVATE_FIELD => '1',
                self::PRIVATE_FIELD => 'TH-POC-947105'
            ), true);
        });
    }

    public function testSavingRechecksTargetsEvenWhenPrivateFieldWasInStoredForm(): void
    {
        $this->asLeader(function () {
            $service = new ProfileEditService($this->leader);
            $fields = $service->getEditableFields($this->profileFields, array($this->leader));
            $form = $this->createForm(array_keys($fields));
            $values = $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'toggle_' . self::PRIVATE_FIELD => '1',
                self::PRIVATE_FIELD => 'TH-POC-947105'
            ), true);
            $this->assertArrayHasKey(self::PRIVATE_FIELD, $values);

            // A valid token and form definition must not authorize a different target or a mixed selection.
            $this->expectException(Exception::class);
            $service->validateProfileValues(array($this->leader, $this->member), $values);
        });
    }

    public function testSingleProfileSaveAlsoRechecksFieldPermissions(): void
    {
        $this->asLeader(function () {
            $form = $this->createForm(array(self::PRIVATE_FIELD));
            $values = $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                self::PRIVATE_FIELD => 'TH-POC-947105'
            ));

            $this->expectException(Exception::class);
            (new ProfileEditService($this->leader))->validateProfileValues(array($this->member), $values);
        });
    }

    public function testAuthorizedBulkChangeIsPersisted(): void
    {
        $this->asLeader(function () {
            $form = $this->createForm(array(self::PUBLIC_FIELD));
            $values = $form->validate(array(
                'adm_csrf_token' => $form->getCsrfToken(),
                'toggle_' . self::PUBLIC_FIELD => '1',
                self::PUBLIC_FIELD => 'Updated note'
            ), true);
            $values = (new ProfileEditService($this->leader))->validateProfileValues(array($this->member), $values);
            foreach ($values as $name => $value) {
                $this->member->setValue($name, $value);
            }
            $this->member->save();

            $reloaded = new User($this->getDatabase(), $this->profileFields, (int)$this->member->getValue('usr_id'));
            $this->assertSame('Updated note', $reloaded->getValue(self::PUBLIC_FIELD));
            $this->assertSame(self::SECRET, $reloaded->getValue(self::PRIVATE_FIELD));
        });
    }

    public function testAdministratorCanStillEditRestrictedField(): void
    {
        $service = new ProfileEditService($GLOBALS['gCurrentUser']);
        $this->assertArrayHasKey(self::PRIVATE_FIELD, $service->getEditableFields($this->profileFields, array($this->member)));
        $this->assertSame(array(self::PRIVATE_FIELD => 'Updated ID'), $service->validateProfileValues(
            array($this->member), array(self::PRIVATE_FIELD => 'Updated ID')
        ));
    }

    public function testRegistrationPreservesProfileValuesAndExcludesLoginControls(): void
    {
        $values = (new ProfileEditService(null))->validateProfileValues(array(new User($this->getDatabase())), array(
            'FIRST_NAME' => 'New member', 'usr_login_name' => 'new-login',
            'usr_password' => 'password', 'adm_password_confirm' => 'password',
            'adm_org_id' => '1', 'adm_captcha_code' => 'code'
        ));
        $this->assertSame(array('FIRST_NAME' => 'New member'), $values);
    }

    public function testAnonymousUsersCannotGenerateBulkFields(): void
    {
        $this->expectException(Exception::class);
        (new ProfileEditService(null))->getEditableFields($this->profileFields, array($this->member));
    }
}
