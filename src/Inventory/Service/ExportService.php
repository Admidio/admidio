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

// TCPDF namespace
use TCPDF;

// Admidio namespaces
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
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
        global $gDb, $gLogger, $gCurrentUser, $gL10n, $gCurrentOrganization, $gSettingsManager;

        $modeSettings = array(
        //  Mode                 mode,      charset,        orientation
            'csv-ms'    => array('csv',     'iso-8859-1',   ''),
            'csv-oo'    => array('csv',     'utf-8',        ''),
            'xlsx'      => array('xlsx',    '',             ''),
            'ods'       => array('ods',     '',             ''),
            'pdf'       => array('pdf',     '',             'P'),
            'pdfl'      => array('pdf',     '',             'L')
        );

        // check if mode is valid
        if (isset($modeSettings[$mode])) {
            [$exportMode, $charset, $orientation] = $modeSettings[$mode];
        } else {
            // if mode is not valid, throw an exception
            throw new InvalidArgumentException('Invalid export mode: ' . $mode);
        }

        $filename = Language::translateIfTranslationStrId($gSettingsManager->getString('inventory_export_filename')) . '.' . $exportMode;
        if ($gSettingsManager->getBool('inventory_add_date')) {
            // add system date format to filename
            $filename = date('Y-m-d') . '_' . $filename;
        }

        $inventoryPage = new InventoryPresenter();
        // check if there are multiple organizations defined
        $sql = 'SELECT COUNT(org_id) AS count FROM ' . TBL_ORGANIZATIONS;
        $result = $gDb->queryPrepared($sql);

        if (($row = $result->fetch()) !== false && $row['count'] > 1) {
            // if there are multiple organizations, add the organization name to the filename
            $inventoryPage->setHeadline($gL10n->get('SYS_INVENTORY') . ' (' . $gCurrentOrganization->getValue('org_longname') . ')');
        } else {
            // if there is only one organization, do not add the organization name to the headline
            $inventoryPage->setHeadline($gL10n->get('SYS_INVENTORY'));
        }
        $data = $inventoryPage->prepareData($exportMode);

        switch ($exportMode) {
            case 'pdf':
                $pdf = new TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

                // set document information
                $pdf->SetCreator(PDF_CREATOR);
                $pdf->SetAuthor('Admidio');
                $pdf->SetTitle($inventoryPage->getHeadline());

                // remove default header/footer
                $pdf->setPrintHeader(true);
                $pdf->setPrintFooter(false);

                // set header and footer fonts
                $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
                $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

                // set auto page breaks
                $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
                $pdf->SetMargins(10, 20, 10);
                $pdf->setHeaderMargin(10);
                $pdf->setFooterMargin(0);

                // headline for PDF
                $pdf->setHeaderData('', 0, $inventoryPage->getHeadline(), '');

                // set font
                $pdf->SetFont('times', '', 10);

                // add a page
                $pdf->AddPage();

                // Using Smarty templating engine for exporting table in PDF
                $smarty = $inventoryPage->createSmartyObject();
                $smarty->assign('attributes', array('border' => '1', 'cellpadding' => '1'));
                $smarty->assign('column_align', $data['column_align']);
                $smarty->assign('headers', $data['headers']);
                $smarty->assign('headersStyle', 'font-size:10;font-weight:bold;background-color:#C7C7C7;');
                $smarty->assign('rows', $data['rows']);
                $smarty->assign('rowsStyle', 'font-size:10;');

                // Fetch the HTML table from our Smarty template
                $htmlTable = $smarty->fetch('modules/inventory.list.export.tpl');

                $pdf->writeHTML($htmlTable, true, false, true);
                $file = ADMIDIO_PATH . FOLDER_DATA . '/temp/' . $filename;
                $pdf->Output($file, 'F');
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');

                // necessary for IE6 to 8, because without it the download with SSL has problems
                header('Cache-Control: private');
                header('Pragma: public');
                readfile($file);
                ignore_user_abort(true);
                try {
                    FileSystemUtils::deleteFileIfExists($file);
                } catch (RuntimeException) {
                    $gLogger->error('Could not delete file!', array('filePath' => $file));
                }
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

                header('Content-disposition: attachment; filename="' . $filename . '"');
                header("Content-Type: $contentType");
                header('Content-Transfer-Encoding: binary');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');

                $spreadsheet = new Spreadsheet();
                $spreadsheet->getProperties()
                    ->setCreator($gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'))
                    ->setTitle($filename)
                    ->setSubject($gL10n->get('PLG_INVENTORY_MANAGER_ITEMLIST'))
                    ->setCompany($gCurrentOrganization->getValue('org_longname'))
                    ->setKeywords($gL10n->get('PLG_INVENTORY_MANAGER_NAME_OF_PLUGIN') . ', ' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM'))
                    ->setDescription($gL10n->get('PLG_INVENTORY_MANAGER_CREATED_WITH'));

                $sheet = $spreadsheet->getActiveSheet();
                $sheet->fromArray(array_keys($data['export_headers']), NULL, 'A1');

                $startRow = 2;
                foreach ($data['rows'] as $rowIndex => $row) {
                    $currentRow = $startRow + $rowIndex;
                    $currentCol = 1;
                    foreach ($row['data'] as $cell) {
                        $hasIndent = false;
                        if (str_contains($cell, '<i')) {
                            $cell = str_replace(['<i>', '</i>'], '', $cell);
                            $hasIndent = true;
                        }
                        $colLetter = Coordinate::stringFromColumnIndex($currentCol);
                        $sheet->setCellValue($colLetter . $currentRow, $cell);
                        if ($hasIndent) {
                            $sheet->getStyle($colLetter . $currentRow)->getFont()->setItalic(true);
                        }
                        $currentCol++;
                    }
                }

                if ($exportMode !== 'csv') {
                    foreach ($data['strikethroughs'] as $index => $strikethrough) {
                        if ($strikethrough) {
                            $sheet->getStyle('A' . ($index + 2) . ':' . $sheet->getHighestColumn() . ($index + 2))
                                ->getFont()->setStrikethrough(true);
                        }
                    }
                    $this->formatSpreadsheet($spreadsheet, count($data['rows'][0]['data']), true);
                }

                $writer = new $writerClass($spreadsheet);
                $writer->save('php://output');
                break;

            default:
                throw new InvalidArgumentException('Invalid mode');
        }
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
