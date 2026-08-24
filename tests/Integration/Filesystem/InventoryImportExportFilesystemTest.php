<?php

namespace Admidio\Tests\Integration\Filesystem;

use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Language;
use Admidio\Inventory\Service\ExportService;
use Admidio\Inventory\Service\ImportService;
use Admidio\Tests\Support\FilesystemTestCase;

/**
 * Regression coverage for the inventory file-import/export service boundary.
 */
class InventoryImportExportFilesystemTest extends FilesystemTestCase
{
    /**
     * @testdox A real CSV is parsed and imported and the persisted item appears in a production CSV export
     */
    public function testCsvImportAndExportRoundTripUsesProductionServices(): void
    {
        global $gCurrentOrgId;

        $db = $this->getDatabase();
        $suffix = bin2hex(random_bytes(5));
        $categoryName = 'Regression import category ' . $suffix;

        $category = new Category($db);
        $category->setValue('cat_name', $categoryName);
        $category->setValue('cat_org_id', $gCurrentOrgId);
        $category->setValue('cat_type', 'IVT');
        $category->save();

        $fieldRows = $db->queryPrepared(
            'SELECT inf_id, inf_name_intern
               FROM ' . TBL_INVENTORY_FIELDS . '
              WHERE inf_org_id = ?
                AND inf_name_intern IN (\'ITEMNAME\', \'CATEGORY\', \'STATUS\')',
            array($gCurrentOrgId)
        )->fetchAll();

        $fieldIds = array();
        foreach ($fieldRows as $fieldRow) {
            $fieldIds[(string)$fieldRow['inf_name_intern']] = (int)$fieldRow['inf_id'];
        }

        $this->assertArrayHasKey('ITEMNAME', $fieldIds);
        $this->assertArrayHasKey('CATEGORY', $fieldIds);
        $this->assertArrayHasKey('STATUS', $fieldIds);

        $statusValue = $db->queryPrepared(
            'SELECT ifo_value
               FROM ' . TBL_INVENTORY_FIELD_OPTIONS . '
              WHERE ifo_inf_id = ?
                AND ifo_obsolete = false
           ORDER BY ifo_sequence, ifo_id
              LIMIT 1',
            array($fieldIds['STATUS'])
        )->fetchColumn();

        $this->assertIsString($statusValue);
        $statusLabel = Language::translateIfTranslationStrId($statusValue);

        $fixtureDirectory = $this->createIsolatedDirectory('inventory-import');
        $csvPath = $fixtureDirectory . '/inventory-' . $suffix . '.csv';
        $itemName = 'Imported regression item ' . $suffix;

        $handle = fopen($csvPath, 'wb');
        $this->assertNotFalse($handle);
        fputcsv($handle, array('Item', 'Category', 'Status'));
        fputcsv($handle, array($itemName, $categoryName, $statusLabel));
        fclose($handle);

        $importService = new ImportService();
        $rows = $importService->readImportFileData($csvPath, 'CSV', 'UTF-8', ',', '"');
        $this->assertCount(2, $rows);
        $this->assertSame($itemName, $rows[1][0]);

        $result = $importService->importData($rows, array(
            'first_row' => '1',
            (string)$fieldIds['ITEMNAME'] => '0',
            (string)$fieldIds['CATEGORY'] => '1',
            (string)$fieldIds['STATUS'] => '2'
        ));
        $this->assertSame('success', $result['success']);

        $itemUuid = $db->queryPrepared(
            'SELECT ini_uuid
               FROM ' . TBL_INVENTORY_ITEMS . '
         INNER JOIN ' . TBL_INVENTORY_ITEM_DATA . ' ON ind_ini_id = ini_id
         INNER JOIN ' . TBL_INVENTORY_FIELDS . ' ON inf_id = ind_inf_id
              WHERE inf_org_id = ?
                AND inf_name_intern = \'ITEMNAME\'
                AND ind_value = ?',
            array($gCurrentOrgId, $itemName)
        )->fetchColumn();

        $this->assertIsString($itemUuid);
        $this->assertNotSame('', $itemUuid);

        $export = (new ExportService())->createExportFile('csv-oo');
        $this->registerCleanupPath($export['path']);

        $this->assertFileExists($export['path']);
        $this->assertGreaterThan(0, filesize($export['path']));
        $this->assertSame('text/csv; charset=utf-8', $export['contentType']);

        $exportRows = $importService->readImportFileData($export['path'], 'CSV', 'UTF-8');
        $flattened = implode(
            "\n",
            array_map(
                static fn (array $row): string => implode(' | ', array_map('strval', $row)),
                $exportRows
            )
        );
        $this->assertStringContainsString($itemName, $flattened);
    }
}
