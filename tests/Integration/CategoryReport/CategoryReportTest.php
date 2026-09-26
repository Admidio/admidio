<?php

namespace Admidio\Tests\Integration\CategoryReport;

use Admidio\CategoryReport\Entity\CategoryReport as CategoryReportEntity;
use Admidio\CategoryReport\Entity\CategoryReportColumn;
use Admidio\Tests\Support\DatabaseTestCase;

class CategoryReportTest extends DatabaseTestCase
{
    public function testColumnsAreStoredInTheirOwnOrderedRecords(): void
    {
        global $gCurrentOrgId;

        $report = new \CategoryReport();
        $configurations = $report->saveConfigArray(array(array(
            'id' => '',
            'name' => 'Column storage test',
            'columns' => array(
                array('field' => 'p2', 'condition' => 'Smith, John'),
                array('field' => 'r1', 'condition' => ''),
                array('field' => 'p3', 'condition' => '{2020-01-01')
            ),
            'col_fields' => 'p2,r1,p3',
            'col_conditions' => 'Smith, John,,{2020-01-01',
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
            'SELECT crc_number, crc_field, crc_condition
               FROM ' . TBL_CATEGORY_REPORT_COLUMNS . '
              WHERE crc_crt_id = ?
           ORDER BY crc_number',
            array($reportId)
        )->fetchAll();

        $this->assertSame(array(1, 2, 3), array_map('intval', array_column($columns, 'crc_number')));
        $this->assertSame(array('p2', 'r1', 'p3'), array_column($columns, 'crc_field'));
        $this->assertSame(array('Smith, John', '', '{2020-01-01'), array_column($columns, 'crc_condition'));
        $this->assertSame($gCurrentOrgId, (int)$configuration['organization_id']);

        $entity = new CategoryReportEntity($this->getDatabase(), $reportId);
        $this->assertContainsOnlyInstancesOf(CategoryReportColumn::class, $entity->getColumns());
        $entity->setColumns(array(
            array('field' => 'p3', 'condition' => ''),
            array('field' => 'p2', 'condition' => 'Jones')
        ));
        $entity->save();

        $updated = (new \CategoryReport())->getConfigArray();
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
