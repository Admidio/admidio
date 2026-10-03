<?php

namespace Admidio\Tests\Integration\CategoryReport;

use Admidio\CategoryReport\Entity\CategoryReport as CategoryReportEntity;
use Admidio\CategoryReport\Entity\CategoryReportColumn;
use Admidio\CategoryReport\Service\CategoryReportGenerator;
use Admidio\CategoryReport\Service\CategoryReportRepository;
use Admidio\Infrastructure\Exception;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\UI\Presenter\FormPresenter;

class CategoryReportTest extends AdministratorTestCase
{
    protected function tearDown(): void
    {
        global $gSettingsManager;

        parent::tearDown();
        $gSettingsManager->resetAll();
    }

    public function testDefaultReportAndRoleNamesAreTranslated(): void
    {
        global $gL10n;

        $configurations = (new CategoryReportRepository())->getConfigArray();
        $defaultReport = array_values(array_filter(
            $configurations,
            static fn(array $values): bool => (bool)$values['default_conf']
        ))[0];
        $this->assertSame($gL10n->get('SYS_GENERAL_ROLE_ASSIGNMENT'), $defaultReport['name']);

        $roleLabel = $gL10n->get('SYS_ROLE') . ': ' . $gL10n->get('SYS_ADMINISTRATOR');
        $headerLabels = array_column((new CategoryReportGenerator())->headerSelection, 'data');
        $this->assertContains($roleLabel, $headerLabels);
    }

    public function testFieldAndRoleNamesRemainPlainTextUntilHtmlRendering(): void
    {
        global $gCurrentOrgId, $gProfileFields;

        $previousProfileFields = $gProfileFields;
        try {
            $profileCategoryId = (int)$this->getDatabase()->queryPrepared(
                'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_type = ? ORDER BY cat_id LIMIT 1',
                array('USF')
            )->fetchColumn();
            $field = new ProfileField($this->getDatabase());
            $field->setValue('usf_cat_id', $profileCategoryId);
            $field->setValue('usf_type', 'TEXT');
            $field->setValue('usf_name', 'Field & "Lead"');
            $field->save();
            $gProfileFields = new ProfileFields($this->getDatabase(), $gCurrentOrgId);

            $fixture = new AdmidioTestFixture($this->getDatabase());
            $role = $fixture->createAndSaveRole('Board & "Lead"', $gCurrentOrgId);
            $user = $fixture->createAndSaveUser('category-report-html', 'category-report-html@example.local');
            $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], '2020-01-01', DATE_MAX);

            $configurations = (new CategoryReportRepository())->saveConfigArray(array(array(
                'id' => '',
                'name' => 'HTML encoding test',
                'description' => '',
                'columns' => array(
                    array('field' => 'p' . $field->getValue('usf_id'), 'condition' => ''),
                    array('field' => 'adummy', 'condition' => '')
                ),
                'selection_role' => (string)$role['rol_id'],
                'selection_cat' => '',
                'number_col' => 0,
                'default_conf' => false
            )));
            $configuration = array_values(array_filter(
                $configurations,
                static fn(array $values): bool => $values['name'] === 'HTML encoding test'
            ))[0];

            $generator = new CategoryReportGenerator();
            $generator->getConfigArray();
            $generator->setConfiguration((int)$configuration['id']);
            $generator->generate_listData();

            $this->assertSame('Field & "Lead"', $generator->headerData[1]['data']);
            $this->assertSame('Board & "Lead"', $generator->listData[$user['usr_id']][2]);
        } finally {
            $gProfileFields = $previousProfileFields;
        }
    }

    public function testForeignRoleSelectionIsRejected(): void
    {
        global $gCurrentOrgId;

        $fixture = new AdmidioTestFixture($this->getDatabase());
        $organization = $fixture->createAndSaveOrganization('Foreign report org', 'forreport');
        $category = $fixture->createAndSaveCategory('Foreign role category', 'ROL', $organization['org_id']);
        $role = $fixture->createAndSaveRoleInCategory('Foreign report role', $category['cat_id']);

        $this->assertSelectionIsRejected(array($role['rol_id']), array());
    }

    public function testForeignRoleCategorySelectionIsRejected(): void
    {
        $fixture = new AdmidioTestFixture($this->getDatabase());
        $organization = $fixture->createAndSaveOrganization('Foreign category org', 'forcat');
        $category = $fixture->createAndSaveCategory('Foreign report category', 'ROL', $organization['org_id']);
        $fixture->createAndSaveRoleInCategory('Foreign category role', $category['cat_id']);

        $this->assertSelectionIsRejected(array(), array($category['cat_id']));
    }

    public function testColumnsAreStoredInTheirOwnOrderedRecords(): void
    {
        global $gCurrentOrgId;

        $report = new \Admidio\CategoryReport\Service\CategoryReportRepository();
        $configurations = $report->saveConfigArray(array(array(
            'id' => '',
            'name' => 'Column storage test',
            'description' => 'Description storage test',
            'columns' => array(
                array('field' => 'p2', 'condition' => 'Smith, John'),
                array('field' => 'w1', 'condition' => ''),
                array('field' => 'uuuid', 'condition' => ''),
                array('field' => 'p3', 'condition' => '{2020-01-01')
            ),
            'col_fields' => 'p2,w1,uuuid,p3',
            'col_conditions' => 'Smith, John,,,{2020-01-01',
            'selection_role' => '',
            'selection_cat' => '',
            'number_col' => 0,
            'default_conf' => false
        )));

        $configuration = array_values(array_filter(
            $configurations,
            static fn(array $values): bool => $values['name'] === 'Column storage test'
        ))[0];
        $reportId = (int)$configuration['id'];

        $columns = $this->getDatabase()->queryPrepared(
            'SELECT crc_number, crc_field_type, crc_usf_id, crc_rol_id, crc_cat_id,
                    crc_special_field, crc_condition
               FROM ' . TBL_CATEGORY_REPORT_COLUMNS . '
              WHERE crc_crt_id = ?
           ORDER BY crc_number',
            array($reportId)
        )->fetchAll();

        $this->assertSame(array(1, 2, 3, 4), array_map('intval', array_column($columns, 'crc_number')));
        $this->assertSame(
            array('profile_field', 'role_membership_without_leader', 'user_field', 'profile_field'),
            array_column($columns, 'crc_field_type')
        );
        $this->assertSame(array(2, 0, 0, 3), array_map('intval', array_column($columns, 'crc_usf_id')));
        $this->assertSame(array(0, 1, 0, 0), array_map('intval', array_column($columns, 'crc_rol_id')));
        $this->assertNull($columns[0]['crc_special_field']);
        $this->assertSame('uuid', $columns[2]['crc_special_field']);
        $this->assertSame(array('Smith, John', null, null, '{2020-01-01'), array_column($columns, 'crc_condition'));
        $this->assertSame($gCurrentOrgId, (int)$configuration['organization_id']);
        $this->assertSame('Description storage test', $configuration['description']);

        $entity = new CategoryReportEntity($this->getDatabase(), $reportId);
        $this->assertContainsOnlyInstancesOf(CategoryReportColumn::class, $entity->getColumns());
        $entity->setColumns(array(
            array('field' => 'p3', 'condition' => ''),
            array('field' => 'p2', 'condition' => 'Jones')
        ));
        $entity->save();

        $updated = (new \Admidio\CategoryReport\Service\CategoryReportRepository())->getConfigArray();
        $updatedConfiguration = array_values(array_filter(
            $updated,
            static fn(array $values): bool => (int)$values['id'] === $reportId
        ))[0];

        $this->assertSame('p3,p2', $updatedConfiguration['col_fields']);
        $this->assertSame(',Jones', $updatedConfiguration['col_conditions']);
        $this->assertSame($entity->getColumnDefinitions(), $updatedConfiguration['columns']);

        $entity->delete();
        $this->assertSame(0, (int)$this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_CATEGORY_REPORT . ' WHERE crt_id = ?',
            array($reportId)
        )->fetchColumn());
        $this->assertSame(0, (int)$this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_CATEGORY_REPORT_COLUMNS . ' WHERE crc_crt_id = ?',
            array($reportId)
        )->fetchColumn());
    }

    public function testDurationIncludesCurrentRepeatedMembership(): void
    {
        global $gCurrentOrgId;

        $fixture = new AdmidioTestFixture($this->getDatabase());
        $role = $fixture->createAndSaveRole('Repeated membership role', $gCurrentOrgId);
        $user = $fixture->createAndSaveUser('category-report-repeat', 'category-report-repeat@example.local');
        $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], '2018-01-01', '2018-12-31');
        $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], '2020-01-01', DATE_MAX);

        $repository = new CategoryReportRepository();
        $configurations = $repository->saveConfigArray(array(array(
            'id' => '',
            'name' => 'Repeated membership duration test',
            'description' => '',
            'columns' => array(array('field' => 'ddummy', 'condition' => '')),
            'col_fields' => 'ddummy',
            'col_conditions' => '',
            'selection_role' => '',
            'selection_cat' => '',
            'number_col' => 0,
            'default_conf' => false
        )));
        $configuration = array_values(array_filter(
            $configurations,
            static fn(array $values): bool => $values['name'] === 'Repeated membership duration test'
        ))[0];

        $generator = new CategoryReportGenerator();
        $generator->getConfigArray();
        $generator->setConfiguration((int)$configuration['id']);
        $generator->generate_listData();

        $this->assertArrayHasKey($user['usr_id'], $generator->listData);
        $this->assertStringStartsWith(
            'Repeated membership role: ',
            $generator->listData[$user['usr_id']][1]
        );
    }

    public function testCategorySelectionIncludesMemberOfMultipleRolesInSameCategory(): void
    {
        global $gCurrentOrgId;

        $fixture = new AdmidioTestFixture($this->getDatabase());
        $category = $fixture->createAndSaveCategory('Report category', 'ROL', $gCurrentOrgId);
        $firstRole = $fixture->createAndSaveRoleInCategory('First report role', $category['cat_id']);
        $secondRole = $fixture->createAndSaveRoleInCategory('Second report role', $category['cat_id']);
        $user = $fixture->createAndSaveUser('category-report-two-roles', 'category-report-two-roles@example.local');
        $fixture->assignUserToRolePeriod($user['usr_id'], $firstRole['rol_id'], '2020-01-01', DATE_MAX);
        $fixture->assignUserToRolePeriod($user['usr_id'], $secondRole['rol_id'], '2020-01-01', DATE_MAX);

        $generator = $this->createCategoryFilteredGenerator((int)$category['cat_id']);
        $generator->generate_listData();

        $this->assertArrayHasKey($user['usr_id'], $generator->listData);
    }

    public function testCategorySelectionUsesReportDate(): void
    {
        global $gCurrentOrgId;

        $fixture = new AdmidioTestFixture($this->getDatabase());
        $category = $fixture->createAndSaveCategory('Historical report category', 'ROL', $gCurrentOrgId);
        $role = $fixture->createAndSaveRoleInCategory('Historical report role', $category['cat_id']);
        $user = $fixture->createAndSaveUser('category-report-history', 'category-report-history@example.local');
        $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], '2020-01-01', '2021-01-01');

        $generator = $this->createCategoryFilteredGenerator((int)$category['cat_id']);
        $generator->generate_listData('2020-06-01');

        $this->assertArrayHasKey($user['usr_id'], $generator->listData);

    }

    public function testMemberDataIsLoadedInFixedNumberOfQueries(): void
    {
        global $gCurrentOrgId, $gLogger;

        $fixture = new AdmidioTestFixture($this->getDatabase());
        $role = $fixture->createAndSaveRole('Batch report role', $gCurrentOrgId);
        $firstUser = $fixture->createAndSaveUser(
            'category-report-batch-1',
            'category-report-batch-1@example.local'
        );
        $fixture->assignUserToRolePeriod($firstUser['usr_id'], $role['rol_id'], '2020-01-01', DATE_MAX);
        $users = array($firstUser);

        $configurations = (new CategoryReportRepository())->saveConfigArray(array(array(
            'id' => '',
            'name' => 'Batch query test',
            'description' => '',
            'columns' => array(
                array('field' => 'p2', 'condition' => ''),
                array('field' => 'ulogin_name', 'condition' => ''),
                array('field' => 'adummy', 'condition' => ''),
                array('field' => 'b' . $role['rol_id'], 'condition' => '')
            ),
            'selection_role' => (string)$role['rol_id'],
            'selection_cat' => '',
            'number_col' => 0,
            'default_conf' => false
        )));
        $configuration = array_values(array_filter(
            $configurations,
            static fn(array $values): bool => $values['name'] === 'Batch query test'
        ))[0];

        $generator = new CategoryReportGenerator();
        $generator->getConfigArray();
        $generator->setConfiguration((int)$configuration['id']);
        $gLogger->resetQueryCount();
        $generator->generate_listData();
        $singleMemberQueryCount = $gLogger->getQueryCount();

        for ($index = 2; $index <= 25; ++$index) {
            $login = 'category-report-batch-' . $index;
            $user = $fixture->createAndSaveUser($login, $login . '@example.local');
            $fixture->assignUserToRolePeriod($user['usr_id'], $role['rol_id'], '2020-01-01', DATE_MAX);
            $users[] = $user;
        }

        $generator = new CategoryReportGenerator();
        $generator->getConfigArray();
        $generator->setConfiguration((int)$configuration['id']);
        $gLogger->resetQueryCount();
        $generator->generate_listData();
        $multipleMembersQueryCount = $gLogger->getQueryCount();

        $this->assertSame(5, $singleMemberQueryCount);
        $this->assertSame($singleMemberQueryCount, $multipleMembersQueryCount);
        foreach ($users as $index => $user) {
            $this->assertArrayHasKey($user['usr_id'], $generator->listData);
            $this->assertSame($user['usr_uuid'], $generator->userUuids[$user['usr_id']]);
            $this->assertSame('', $generator->listData[$user['usr_id']][1]);
            $this->assertSame('category-report-batch-' . ($index + 1), $generator->listData[$user['usr_id']][2]);
            $this->assertSame('Batch report role', $generator->listData[$user['usr_id']][3]);
            $this->assertSame('2020-01-01', $generator->listData[$user['usr_id']][4]);
        }
    }

    private function createCategoryFilteredGenerator(int $categoryId): CategoryReportGenerator
    {
        $configurations = (new CategoryReportRepository())->saveConfigArray(array(array(
            'id' => '',
            'name' => 'Category selection test',
            'description' => '',
            'columns' => array(array('field' => 'uuuid', 'condition' => '')),
            'selection_role' => '',
            'selection_cat' => (string)$categoryId,
            'number_col' => 0,
            'default_conf' => false
        )));
        $configuration = array_values(array_filter(
            $configurations,
            static fn(array $values): bool => $values['name'] === 'Category selection test'
        ))[0];

        $generator = new CategoryReportGenerator();
        $generator->getConfigArray();
        $generator->setConfiguration((int)$configuration['id']);
        return $generator;
    }

    private function assertSelectionIsRejected(array $roles, array $categories): void
    {
        global $gCurrentSession, $gL10n;

        $reportCount = (int)$this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_CATEGORY_REPORT
        )->fetchColumn();
        $form = new FormPresenter('category_report_security_test', 'modules/category-report.edit.tpl');
        $previousSession = $gCurrentSession;
        $gCurrentSession = new class ($form) {
            public function __construct(private FormPresenter $form)
            {
            }

            public function getFormObject(string $token): ?FormPresenter
            {
                return $token === $this->form->getCsrfToken() ? $this->form : null;
            }
        };
        $request = array(
            'adm_csrf_token' => $form->getCsrfToken(),
            'report_action' => 'new',
            'source_id' => 0,
            'name' => 'Manipulated report',
            'description' => '',
            'columns' => array('uuuid'),
            'columnsRoleProp' => array(''),
            'conditions' => array(''),
            'selection_role' => $roles,
            'selection_cat' => $categories
        );

        try {
            try {
                (new CategoryReportRepository())->saveFromRequest($request);
                $this->fail('A selection from another organization was accepted.');
            } catch (Exception $exception) {
                $this->assertSame($gL10n->get('SYS_INVALID_PAGE_VIEW'), $exception->getMessage());
            }
        } finally {
            $gCurrentSession = $previousSession;
        }
        $this->assertSame($reportCount, (int)$this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . TBL_CATEGORY_REPORT
        )->fetchColumn());
    }
}
