<?php

namespace Admidio\Tests\Integration\Filesystem;

use Admidio\Categories\Entity\Category;
use Admidio\Infrastructure\Language;
use Admidio\Inventory\Service\ExportService;
use Admidio\Inventory\Service\ImportService;
use Admidio\Inventory\Entity\ItemField;
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
        foreach (['csv-oo', 'csv-ms', 'xlsx', 'ods', 'pdf', 'pdfl'] as $mode) {
            $cases[$mode . ' matching search'] = [$mode, 'kamera', ['Kamera ÄÖÜ']];
            $cases[$mode . ' empty search result'] = [$mode, 'missing', []];
            $cases[$mode . ' words in different fields'] = [$mode, 'kamera optics', ['Kamera ÄÖÜ']];
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
                $encoding = $mode === 'csv-ms' ? 'ISO-8859-1' : 'UTF-8';
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
            'words in different fields' => ['kamera optics', ['Kamera ÄÖÜ']],
            'literal comma' => ['kamera,stativ', []],
            'literal minus' => ['kamera -ÄÖÜ', []],
            'hyphenated term' => ['tripod-kit', ['Stativ']],
            'case insensitive umlauts' => ['äöü', ['Kamera ÄÖÜ']],
            'whitespace only' => ['   ', ['Kamera ÄÖÜ', 'Stativ']],
            'no matches' => ['missing', []],
        ];
    }

    /** @dataProvider printSearches */
    public function testPrintSearchFiltersStoredFieldValues(string $search, array $expectedNames): void
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
        $field = new ItemField($this->getDatabase());
        $field->setValue('inf_org_id', $gCurrentOrgId);
        $field->setValue('inf_name', 'Export description');
        $field->setValue('inf_type', 'TEXT');
        $field->save();
        $fieldName = $field->getValue('inf_name_intern');
        $items = new ItemsData($this->getDatabase(), $gCurrentOrgId);
        foreach (['Kamera ÄÖÜ', 'Stativ'] as $name) {
            $items->readItemData('');
            $items->createNewItem($categoryUuid);
            $items->setValue('ITEMNAME', $name);
            $items->setValue('KEEPER', (string) $gCurrentUserId);
            $items->setValue($fieldName, $name === 'Stativ' ? 'tripod-kit' : 'optics');
            $items->saveItemData();
        }
    }

    public static function duplicateImports(): array
    {
        return [
            'swapped values belong to different fields' => ['Tripod', 'Camera', 2],
            'same values in the same fields are a duplicate' => ['Camera', 'Tripod', 1],
            'changed description is a new item' => ['Camera', 'Stand', 2],
            'new value in a previously empty field' => ['Camera', 'Tripod', 2, 1, ''],
            'unchanged empty field is a duplicate' => ['Camera', '', 1, 1, ''],
        ];
    }

    /** @dataProvider duplicateImports */
    public function testImportDuplicatesRespectFieldAssignments(string $name, string $description, int $expectedCount, int $existingCount = 1, string $existingDescription = 'Tripod'): void
    {
        global $gCurrentOrgId;

        $db = $this->getDatabase();
        $field = new ItemField($db);
        $field->setValue('inf_org_id', $gCurrentOrgId);
        $field->setValue('inf_name', 'Import description');
        $field->setValue('inf_type', 'TEXT');
        $field->save();
        $category = $db->queryPrepared('SELECT cat_uuid, cat_name FROM ' . TBL_CATEGORIES
            . " WHERE cat_type = 'IVT' AND cat_org_id = ? LIMIT 1", [$gCurrentOrgId])->fetch();
        $items = new ItemsData($db, $gCurrentOrgId);
        for ($i = 1; $i < $existingCount; ++$i) {
            $items->readItemData('');
            $items->createNewItem($category['cat_uuid']);
            $items->setValue('ITEMNAME', 'Other import item ' . $i);
            $items->saveItemData();
        }
        $items->readItemData('');
        $items->createNewItem($category['cat_uuid']);
        $items->setValue('ITEMNAME', 'Camera');
        $items->setValue($field->getValue('inf_name_intern'), $existingDescription);
        $items->setValue('LAST_RECEIVER', 'Alice');
        $items->setValue('BORROW_DATE', '2020-01-01');
        $items->saveItemData();
        $status = $db->queryPrepared('SELECT ifo_value FROM ' . TBL_INVENTORY_FIELD_OPTIONS
            . ' WHERE ifo_inf_id = ? ORDER BY ifo_sequence, ifo_id LIMIT 1', [$items->getProperty('STATUS', 'inf_id')])->fetchColumn();
        $path = $this->createIsolatedDirectory('inventory-duplicates') . '/items.csv';
        $handle = fopen($path, 'wb');
        fputcsv($handle, [$name, $description, Language::translateIfTranslationStrId($category['cat_name']), Language::translateIfTranslationStrId($status), 'Bob', '2020-01-01'], ',', '"', '');
        fclose($handle);
        $service = new ImportService();
        $service->importData($service->readImportFileData($path, 'CSV', 'UTF-8', ',', '"'), [
            $items->getProperty('ITEMNAME', 'inf_id') => 0,
            $field->getValue('inf_id') => 1,
            $items->getProperty('CATEGORY', 'inf_id') => 2,
            $items->getProperty('STATUS', 'inf_id') => 3,
            $items->getProperty('LAST_RECEIVER', 'inf_id') => 4,
            $items->getProperty('BORROW_DATE', 'inf_id') => 5,
        ]);
        $this->assertSame($expectedCount, (int) $db->queryPrepared('SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEMS)->fetchColumn());
        $matches = $db->queryPrepared('SELECT n.ind_ini_id FROM ' . TBL_INVENTORY_ITEM_DATA . ' n
            LEFT JOIN ' . TBL_INVENTORY_ITEM_DATA . ' d ON d.ind_ini_id = n.ind_ini_id AND d.ind_inf_id = ?
            WHERE n.ind_inf_id = ? AND n.ind_value = ? AND COALESCE(d.ind_value, \'\') = ?',
            [$field->getValue('inf_id'), $items->getProperty('ITEMNAME', 'inf_id'), $name, $description])->fetchAll();
        $this->assertCount(1, $matches);
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
