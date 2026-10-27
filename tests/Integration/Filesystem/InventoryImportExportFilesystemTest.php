<?php

namespace Admidio\Tests\Integration\Filesystem;

use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Language;
use Admidio\Inventory\Service\ExportService;
use Admidio\Inventory\Service\ImportService;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Tests\Support\FilesystemTestCase;
use Admidio\UI\Presenter\InventoryPresenter;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Regression coverage for the inventory file-import/export service boundary.
 */
class InventoryImportExportFilesystemTest extends FilesystemTestCase
{
    public static function filteredExports(): array
    {
        $cases = [];
        foreach (['csv-oo', 'xlsx', 'ods', 'pdf', 'pdfl'] as $mode) {
            $cases[$mode . ' matching search'] = [$mode, 'kamera', ['Kamera ÄÖÜ']];
            $cases[$mode . ' empty search result'] = [$mode, 'missing', []];
        }
        return $cases;
    }

    /** @dataProvider filteredExports */
    public function testFilteredExportsContainOnlyMatchingItems(string $mode, string $search, array $expectedNames): void
    {
        $this->createExportItems();
        $previousGet = $_GET;
        try {
            $_GET = ['items_filter_string' => $search];
            $export = (new ExportService())->createExportFile($mode);
            $this->registerCleanupPath($export['path']);
            $this->assertFileExists($export['path']);

            if (in_array($mode, ['pdf', 'pdfl'], true)) {
                $this->assertSame('application/pdf', $export['contentType']);
                $this->assertStringStartsWith('%PDF-', file_get_contents($export['path']));
                return;
            }

            $reader = match ($mode) {
                'xlsx' => new Xlsx(),
                'ods' => new Ods(),
                default => new Csv(),
            };
            if ($reader instanceof Csv) {
                $encoding = 'UTF-8';
                $reader->setInputEncoding($encoding);
                $this->assertSame('text/csv; charset=' . strtolower($encoding), $export['contentType']);
                if ($expectedNames !== []) {
                    $this->assertStringContainsString(
                        mb_convert_encoding($expectedNames[0], $encoding, 'UTF-8'),
                        file_get_contents($export['path'])
                    );
                }
            }

            $spreadsheet = $reader->load($export['path']);
            $rows = $spreadsheet->getActiveSheet()->toArray();
            $spreadsheet->disconnectWorksheets();
            array_shift($rows);
            $this->assertSame($expectedNames, array_column($rows, 0));
        } finally {
            $_GET = $previousGet;
        }
    }

    public static function printSearches(): array
    {
        return [
            'case insensitive field search' => ['kamera', ['Kamera ÄÖÜ']],
            'comma separated alternatives' => ['kamera,stativ', ['Kamera ÄÖÜ', 'Stativ']],
            'excluded term' => ['kamera,-ÄÖÜ', []],
            'no matches' => ['missing', []],
        ];
    }

    /** @dataProvider printSearches */
    public function testPrintSearchFiltersVisibleFieldValues(string $search, array $expectedNames): void
    {
        $this->createExportItems();
        $previousGet = $_GET;
        try {
            $_GET = ['items_filter_string' => $search];
            $data = (new InventoryPresenter(false))->prepareData('print');
            $names = array_map(static fn(array $row): string => $row['data'][0], $data['rows']);
            sort($names);
            $this->assertSame($expectedNames, $names);
        } finally {
            $_GET = $previousGet;
        }
    }

    private function createExportItems(): void
    {
        global $gCurrentOrgId, $gCurrentUserId;

        $categoryUuid = $this->getDatabase()->queryPrepared(
            'SELECT cat_uuid FROM ' . TBL_CATEGORIES . ' WHERE cat_type = ? AND cat_org_id = ? LIMIT 1',
            ['IVT', $gCurrentOrgId]
        )->fetchColumn();
        $items = new ItemsData($this->getDatabase(), $gCurrentOrgId);
        foreach (['Kamera ÄÖÜ', 'Stativ'] as $name) {
            $items->readItemData('');
            $items->createNewItem($categoryUuid);
            $items->setValue('ITEMNAME', $name);
            $items->setValue('KEEPER', (string) $gCurrentUserId);
            $items->saveItemData();
        }
    }

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
        fputcsv($handle, array('Item', 'Category', 'Status'), ',', '"', '');
        fputcsv($handle, array($itemName, $categoryName, $statusLabel), ',', '"', '');
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

        $formulaItemName = '=Imported regression item ' . $suffix;
        $db->queryPrepared(
            'UPDATE ' . TBL_INVENTORY_ITEM_DATA . '
                SET ind_value = ?
              WHERE ind_ini_id = (SELECT ini_id FROM ' . TBL_INVENTORY_ITEMS . ' WHERE ini_uuid = ?)
                AND ind_inf_id = ?',
            array($formulaItemName, $itemUuid, $fieldIds['ITEMNAME'])
        );

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
        $this->assertStringContainsString("'" . $formulaItemName, $flattened);
    }
}
