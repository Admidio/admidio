<?php

namespace Admidio\Inventory\Service;

// PhpSpreadsheet namespaces
use Admidio\Infrastructure\Language;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Admidio namespaces
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\PdfUtils;
use Admidio\Infrastructure\Utils\SpreadsheetUtils;
use Admidio\UI\Presenter\InventoryPresenter;

// PHP namespaces
use InvalidArgumentException;
use RuntimeException;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ExportService
{
    /**
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createExport(string $mode = 'pdf'): void
    {
        global $gLogger;

        $export = $this->createExportFile($mode);

        header('Content-disposition: attachment; filename="' . $export['filename'] . '"');
        header('Content-Type: ' . $export['contentType']);
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($export['path']);
        ignore_user_abort(true);

        try {
            FileSystemUtils::deleteFileIfExists($export['path']);
        } catch (RuntimeException) {
            $gLogger->error('Could not delete file!', array('filePath' => $export['path']));
        }
    }

    /**
     * Create an inventory export in a temporary file without sending HTTP headers or output.
     *
     * @return array{path:string,filename:string,contentType:string}
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createExportFile(string $mode = 'pdf'): array
    {
        global $gDb, $gCurrentUser, $gL10n, $gCurrentOrganization, $gSettingsManager;

        $modeSettings = array(
        //  Mode                 mode,      charset,        orientation
            'csv-ms'    => array('csv',     'iso-8859-1',   ''),
            'csv-oo'    => array('csv',     'utf-8',        ''),
            'xlsx'      => array('xlsx',    '',             ''),
            'ods'       => array('ods',     '',             ''),
            'pdf'       => array('pdf',     '',             'P'),
            'pdfl'      => array('pdf',     '',             'L')
        );

        if (isset($modeSettings[$mode])) {
            [$exportMode, $charset, $orientation] = $modeSettings[$mode];
        } else {
            throw new InvalidArgumentException('Invalid export mode: ' . $mode);
        }

        $filename = Language::translateIfTranslationStrId(
            $gSettingsManager->getString('inventory_export_filename')
        ) . '.' . $exportMode;
        if ($gSettingsManager->getBool('inventory_add_date')) {
            $filename = date('Y-m-d') . '_' . $filename;
        }

        $inventoryPage = new InventoryPresenter(false);

        $sql = 'SELECT COUNT(org_id) AS count FROM ' . TBL_ORGANIZATIONS;
        $result = $gDb->queryPrepared($sql);

        if (($row = $result->fetch()) !== false && $row['count'] > 1) {
            $inventoryPage->setHeadline(
                $gL10n->get('SYS_INVENTORY')
                . ' (' . $gCurrentOrganization->getValue('org_longname') . ')'
            );
        } else {
            $inventoryPage->setHeadline($gL10n->get('SYS_INVENTORY'));
        }

        $data = $inventoryPage->prepareData($exportMode);
        $file = tempnam(ADMIDIO_PATH . FOLDER_TEMP_DATA, 'inventory-export-');
        if ($file === false) {
            throw new RuntimeException('Could not create temporary inventory export file.');
        }

        switch ($exportMode) {
            case 'pdf':
                $pdf = PdfUtils::createDocument(
                    $orientation,
                    $inventoryPage->getHeadline(),
                    $gL10n->getLanguageIsoCode()
                );

                $smarty = $inventoryPage->createSmartyObject();

                // createExportFile() is also used from the CLI bootstrap, where THEME_PATH and
                // THEME_FALLBACK_PATH are intentionally not defined. Add the same template
                // directories that system/common.php would configure for a web request.
                if (!defined('THEME_PATH')) {
                    $theme = $gSettingsManager->has('theme')
                        ? $gSettingsManager->getString('theme')
                        : 'simple';
                    if ($theme === '' || $theme === 'modern') {
                        $theme = 'simple';
                    }

                    $smarty->setTemplateDir(
                        ADMIDIO_PATH . FOLDER_THEMES . '/' . $theme . '/templates/'
                    );

                    if ($gSettingsManager->has('theme_fallback')
                        && $gSettingsManager->getString('theme_fallback') !== '') {
                        $smarty->addTemplateDir(
                            ADMIDIO_PATH . FOLDER_THEMES . '/'
                            . $gSettingsManager->getString('theme_fallback')
                            . '/templates/'
                        );
                    }
                }

                $smarty->assign('attributes', array('border' => '1', 'cellpadding' => '1'));
                $smarty->assign('column_align', $data['column_align']);
                $smarty->assign('column_widths', $this->calculatePdfColumnWidths(
                    $data['headers'],
                    $data['rows'],
                    $data['column_align']
                ));
                $smarty->assign('headers', $data['headers']);
                $smarty->assign('headersStyle', 'font-size:10pt;font-weight:bold;background-color:#C7C7C7;');
                $smarty->assign('rows', $data['rows']);
                $smarty->assign('rowsStyle', 'font-size:10pt;');

                $htmlTable = $smarty->fetch('modules/inventory.list.export.tpl');
                $pdf->writeTable($htmlTable);
                // Preserve the exact export path instead of the engine's sanitized filename.
                FileSystemUtils::writeFile($file, $pdf->getOutPDFString());

                $contentType = 'application/pdf';
                break;

            case 'csv':
            case 'ods':
            case 'xlsx':
                $contentType = match ($exportMode) {
                    'csv' => 'text/csv; charset=' . $charset,
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
                    default => throw new InvalidArgumentException('Invalid mode'),
                };

                $writerClass = match ($exportMode) {
                    'csv' => Csv::class,
                    'xlsx' => Xlsx::class,
                    'ods' => Ods::class,
                    default => throw new InvalidArgumentException('Invalid mode'),
                };

                $spreadsheet = new Spreadsheet();
                $spreadsheet->getProperties()
                    ->setCreator(
                        $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME')
                    )
                    ->setTitle($filename)
                    ->setSubject($gL10n->get('SYS_INVENTORY_ITEMS'))
                    ->setCompany($gCurrentOrganization->getValue('org_longname'))
                    ->setKeywords(
                        $gL10n->get('SYS_INVENTORY')
                        . ', ' . $gL10n->get('SYS_INVENTORY_ITEMS')
                    );

                $sheet = $spreadsheet->getActiveSheet();
                foreach (array_keys($data['export_headers']) as $columnIndex => $header) {
                    SpreadsheetUtils::setCellValue(
                        $sheet,
                        Coordinate::stringFromColumnIndex($columnIndex + 1) . '1',
                        $header,
                        $exportMode === 'csv'
                    );
                }

                $startRow = 2;
                foreach ($data['rows'] as $rowIndex => $row) {
                    $currentRow = $startRow + $rowIndex;
                    $currentCol = 1;
                    foreach ($row['data'] as $cell) {
                        $hasIndent = false;
                        if (str_contains($cell, '<i')) {
                            $cell = str_replace(array('<i>', '</i>'), '', $cell);
                            $hasIndent = true;
                        }

                        $colLetter = Coordinate::stringFromColumnIndex($currentCol);
                        SpreadsheetUtils::setCellValue(
                            $sheet,
                            $colLetter . $currentRow,
                            $cell,
                            $exportMode === 'csv'
                        );
                        if ($hasIndent) {
                            $sheet->getStyle($colLetter . $currentRow)->getFont()->setItalic(true);
                        }
                        ++$currentCol;
                    }
                }

                if ($exportMode !== 'csv') {
                    foreach ($data['strikethroughs'] as $index => $strikethrough) {
                        if ($strikethrough) {
                            $sheet->getStyle(
                                'A' . ($index + 2) . ':' . $sheet->getHighestColumn() . ($index + 2)
                            )->getFont()->setStrikethrough(true);
                        }
                    }
                    $this->formatSpreadsheet($spreadsheet, count($data['rows'][0]['data']), true);
                }

                $writer = new $writerClass($spreadsheet);
                $writer->save($file);
                break;

            default:
                throw new InvalidArgumentException('Invalid mode');
        }

        return array(
            'path' => $file,
            'filename' => $filename,
            'contentType' => $contentType
        );
    }

    /**
     * Calculate fixed, content-aware column widths for the PDF table.
     *
     * The PDF renderer needs fixed widths to keep wide exports inside the page. Giving every
     * column the same width, however, makes short number and date columns unnecessarily wide.
     * We therefore use the visible header and cell lengths as a bounded weight.
     *
     * @param array<int,string> $headers
     * @param array<int,array<string,array<int,string>>> $rows
     * @param array<int,string> $columnAlign
     * @return array<int,float>
     */
    private function calculatePdfColumnWidths(array $headers, array $rows, array $columnAlign): array
    {
        $visibleLength = static function (string $value): int {
            $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

            return mb_strlen($value);
        };

        $weights = array();
        foreach ($headers as $index => $header) {
            // Number columns are right-aligned and should remain compact even if their header is long.
            $minimum = ($columnAlign[$index] ?? 'start') === 'end' ? 4.0 : 7.0;
            // Long words such as the localized date headings cannot be wrapped by the PDF renderer.
            $weights[$index] = max($minimum, min(17.0, 3.0 + $visibleLength($header) * 0.9));
        }

        foreach ($rows as $row) {
            foreach ($row['data'] as $index => $cell) {
                if (!isset($weights[$index])) {
                    continue;
                }

                $minimum = ($columnAlign[$index] ?? 'start') === 'end' ? 4.0 : 7.0;
                $weights[$index] = max(
                    $weights[$index],
                    max($minimum, min(17.0, 5.0 + $visibleLength($cell) * 0.3))
                );
            }
        }

        $total = array_sum($weights);
        if ($total === 0.0) {
            return array();
        }

        $widths = array();
        $remainingWidth = 100.0;
        $lastIndex = array_key_last($weights);
        foreach ($weights as $index => $weight) {
            if ($index === $lastIndex) {
                $widths[$index] = round($remainingWidth, 2);
                break;
            }

            $widths[$index] = round($weight / $total * 100, 2);
            $remainingWidth -= $widths[$index];
        }

        return $widths;
    }

    /**
     * Formats the spreadsheet
     *
     * @param Spreadsheet $spreadsheet
     * @param int $columnCount
     * @param bool $containsHeadline
     * @throws Exception
     */
    function formatSpreadsheet(Spreadsheet $spreadsheet, int $columnCount, bool $containsHeadline): void
    {
        $activeSheet = $spreadsheet->getActiveSheet();
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        if ($containsHeadline) {
            $range = "A1:{$lastColumn}1";
            $style = $activeSheet->getStyle($range);

            $style->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFDDDDDD');
            $style->getFont()
                ->setBold(true);
        }

        for ($i = 1; $i <= $columnCount; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $activeSheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        try {
            $spreadsheet->getDefaultStyle()->getAlignment()->setWrapText(true);
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            throw new Exception($e);
        }
    }
}
