<?php

namespace Admidio\Tests\Integration\CategoryReport;

use Admidio\CategoryReport\Entity\CategoryReport as CategoryReportEntity;
use Admidio\CategoryReport\Entity\CategoryReportColumn;
use Admidio\CategoryReport\Service\CategoryReportGenerator;
use Admidio\CategoryReport\Service\CategoryReportRepository;
use Admidio\Tests\Support\DatabaseTestCase;

class CategoryReportTest extends DatabaseTestCase
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
}
