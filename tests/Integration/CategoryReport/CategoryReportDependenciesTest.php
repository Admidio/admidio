<?php

namespace Admidio\Tests\Integration\CategoryReport;

use Admidio\Categories\Entity\Category;
use Admidio\CategoryReport\Entity\CategoryReport;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\Roles\Entity\Role;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;

class CategoryReportDependenciesTest extends AdministratorTestCase
{
    /**
     * @testdox Deleting a profile field removes its category report columns
     */
    public function testDeletingProfileFieldRemovesCategoryReportColumns(): void
    {
        $categoryId = (int)$this->getDatabase()->queryPrepared(
            'SELECT cat_id
               FROM ' . TBL_CATEGORIES . '
              WHERE cat_type = ?
           ORDER BY cat_id
              LIMIT 1',
            array('USF')
        )->fetchColumn();

        $field = new ProfileField($this->getDatabase());
        $field->setValue('usf_cat_id', $categoryId);
        $field->setValue('usf_type', 'TEXT');
        $field->setValue('usf_name', 'Category report dependency field');
        $field->save();

        $columnId = $this->createReportColumn('p' . $field->getValue('usf_id'));

        $field->delete();

        $this->assertColumnWasDeleted($columnId);
    }

    /**
     * @testdox Deleting a role removes its category report columns
     */
    public function testDeletingRoleRemovesCategoryReportColumns(): void
    {
        global $gCurrentOrgId;

        $fixture = new AdmidioTestFixture($this->getDatabase());
        $role = $fixture->createAndSaveRole('Category report dependency role', $gCurrentOrgId);
        $columnId = $this->createReportColumn('r' . $role['rol_id']);

        (new Role($this->getDatabase(), $role['rol_id']))->delete();

        $this->assertColumnWasDeleted($columnId);
    }

    /**
     * @testdox Deleting a category removes its category report columns
     */
    public function testDeletingCategoryRemovesCategoryReportColumns(): void
    {
        global $gCurrentOrgId;

        $fixture = new AdmidioTestFixture($this->getDatabase());
        $fixture->createAndSaveCategory('Retained category report category', 'EVT', $gCurrentOrgId);
        $category = $fixture->createAndSaveCategory('Category report dependency category', 'EVT', $gCurrentOrgId);
        $columnId = $this->createReportColumn('c' . $category['cat_id']);

        (new Category($this->getDatabase(), $category['cat_id']))->delete();

        $this->assertColumnWasDeleted($columnId);
    }

    private function createReportColumn(string $field): int
    {
        global $gCurrentOrgId;

        $report = new CategoryReport($this->getDatabase());
        $report->setValue('crt_org_id', $gCurrentOrgId);
        $report->setValue('crt_name', 'Category report dependency test');
        $report->setColumns(array(array('field' => $field)));
        $report->save();

        $columns = $report->getColumns();
        $column = reset($columns);

        return (int)$column->getValue('crc_id');
    }

    private function assertColumnWasDeleted(int $columnId): void
    {
        $count = $this->getDatabase()->queryPrepared(
            'SELECT COUNT(*)
               FROM ' . TBL_CATEGORY_REPORT_COLUMNS . '
              WHERE crc_id = ?',
            array($columnId)
        )->fetchColumn();

        $this->assertSame(0, (int)$count);
    }
}
