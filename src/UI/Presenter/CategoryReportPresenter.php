<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\PdfUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Component\DataTables;
use Admidio\Changelog\Service\ChangelogService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Admidio\Hooks\Hooks;
use Admidio\CategoryReport\Service\CategoryReportGenerator;
use Admidio\CategoryReport\Service\CategoryReportOutput;

/** Renders category reports in the browser and export formats. */
class CategoryReportPresenter
{
    public function showReport(): void
    {
        global $gSettingsManager, $gL10n, $gCurrentOrganization, $gNavigation, $gCurrentUser, $gProfileFields, $gLogger;
        $report = new CategoryReportGenerator();
        $config = Hooks::applyFilters('category_report_config', $report->getConfigArray());
        $getCrtId = admFuncVariableIsValid($_GET, 'crt_id', 'int', array('defaultValue' => $gSettingsManager->get('category_report_default_configuration')));
        $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html', 'validValues' => array('xlsx', 'ods', 'csv-oo', 'html', 'print', 'pdf', 'pdfl')));
        $getFilter = admFuncVariableIsValid($_GET, 'filter', 'string');
        $getExportAndFilter = admFuncVariableIsValid($_GET, 'export_and_filter', 'bool', array('defaultValue' => false));

        if ($config === array()) {
            if ($getMode !== 'html' || !$gCurrentUser->isAdministrator()) {
                throw new Exception('SYS_NO_USER_FOUND');
            }
            $page = PagePresenter::withHtmlIDAndHeadline('adm_category_report', $gL10n->get('SYS_CATEGORY_REPORT'));
            $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category_report.php',
                array('mode' => 'manage'));
            $page->addHtml('<a class="btn btn-primary" href="' . $url . '"><i class="bi bi-gear-fill"></i> ' .
                $gL10n->get('SYS_MANAGE_CATEGORY_REPORTS') . '</a>');
            $page->show();
            return;
        }

        // initialize some special mode parameters
        $charset = '';
        $classTable = '';
        $orientation = '';

        switch ($getMode) {
            case 'xlsx':
            case 'ods':
                $charset = 'utf-8';
                break;
            case 'csv-oo':
                $getMode = 'csv';
                $charset = 'utf-8';
                break;
            case 'pdf':
                $classTable = 'table';
                $orientation = 'P';
                $getMode = 'pdf';
                break;
            case 'pdfl':
                $classTable = 'table';
                $orientation = 'L';
                $getMode = 'pdf';
                break;
            case 'html':
                $classTable = 'table table-condensed table-hover';
                break;
            case 'print':
                $classTable = 'table table-condensed table-striped';
                break;
            default:
                break;
        }

        $csvRows = array();

        // data array
        $data = array('headers' => array(), 'rows' => array(), 'column_align' => array());

        // generate the display list
        $report->setConfiguration($getCrtId);
        $report->generate_listData();

        $profileFieldTypes = array();
        $profileFieldNames = array();
        $profileFieldOptions = array();
        foreach ($report->headerData as $columnHeader) {
            $profileFieldId = (int)$columnHeader['id'];
            if ($profileFieldId > 0) {
                $profileFieldTypes[$profileFieldId] = $gProfileFields->getPropertyById($profileFieldId, 'usf_type');
                $profileFieldNames[$profileFieldId] = $gProfileFields->getPropertyById($profileFieldId, 'usf_name_intern');
            }
        }

        $hasSummary = (int)$config[$report->getConfiguration()]['number_col'] === 1;
        $outputRows = CategoryReportOutput::filterRows(
            $report->listData,
            $report->headerData,
            $hasSummary,
            $gL10n->get('SYS_TOTAL'),
            $getMode === 'html' && !$getExportAndFilter ? '' : $getFilter,
            static function (mixed $value, int $key) use ($report, $gProfileFields, $profileFieldNames): string {
                $profileFieldId = (int)($report->headerData[$key]['id'] ?? 0);
                if ($value === true) {
                    return 'X';
                }
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                if ($profileFieldId > 0) {
                    $value = $gProfileFields->getHtmlValue($profileFieldNames[$profileFieldId], $value);
                }
                return html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        );
        $numMembers = count($outputRows) - (int)$hasSummary;

        if ($numMembers === 0 && $getMode !== 'html') {
            throw new Exception('SYS_NO_USER_FOUND');
        }

        $columnCount = count($report->headerData);

        // define title (html) and headline
        // Both are handed to the page below and read back filtered through getFilteredTitle() /
        // getFilteredHeadline() (page_title / page_headline), because the export filename and the PDF
        // header need the filtered text too and neither of them reaches PagePresenter::show().
        $subHeadline = $config[$report->getConfiguration()]['name'];
        $reportDescription = nl2br((string)$config[$report->getConfiguration()]['description']);

        $page = PagePresenter::withHtmlIDAndHeadline('adm_category_report');
        $page->setTitle($gL10n->get('SYS_CATEGORY_REPORT'));
        $page->setHeadline($gL10n->get('SYS_CATEGORY_REPORT'));
        $title = $page->getFilteredTitle();
        $headline = $page->getFilteredHeadline();

        $filename = $gCurrentOrganization->getValue('org_shortname') . '-' . $headline . '-' . $subHeadline;

        if ($getMode === 'html') {
            $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-list-stars');
        }

        if ($getMode !== 'csv') {
            $smarty = $page->createSmartyObject();
            $smarty->assign('l10n', $gL10n);
            if ($getMode === 'print') {
                $page->setContentFullWidth();
                $page->setPrintMode();
                $page->addHtml('<h5 class="admidio-content-subheader">' . $subHeadline . '</h5>');
                if ($reportDescription !== '') {
                    $page->addHtml('<p>' . $reportDescription . '</p>');
                }
                $smarty->assign('classTable', $classTable);
            } elseif ($getMode === 'pdf') {
                if (ini_get('max_execution_time') < 600) {
                    ini_set('max_execution_time', 600); //600 seconds = 10 minutes
                }

                $pdf = PdfUtils::createDocument($orientation, $headline);

                // set subHeadline and class for table
                $smarty->assign('subHeadline', $subHeadline);
                $smarty->assign('reportDescription', $reportDescription);
                $smarty->assign('classTable', $classTable);
            } elseif ($getMode === 'html') {
                // create html page object
                $page->setContentFullWidth();

                $page->addJavascript(
                    '
                $("#menu_item_lists_print_view").click(function() {
                    window.open("' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category_report.php', array(
                        'mode' => 'print',
                        'filter' => $getFilter,
                        'export_and_filter' => $getExportAndFilter,
                        'crt_id' => $getCrtId
                    )) . '", "_blank");
                });',
                    true
                );

                if ($getExportAndFilter) {
                    // link to print overlay and exports
                    $page->addPageFunctionsMenuItem('menu_item_lists_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'bi-printer-fill');

                    // dropdown menu item with all export possibilities
                    $page->addPageFunctionsMenuItem('menu_item_lists_export', $gL10n->get('SYS_EXPORT'), '#', 'bi-download');
                    $page->addPageFunctionsMenuItem(
                        'menu_item_lists_xlsx',
                        $gL10n->get('SYS_MICROSOFT_EXCEL') . ' (*.xlsx)',
                        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category_report.php', array(
                            'crt_id' => $getCrtId,
                            'filter' => $getFilter,
                            'export_and_filter' => $getExportAndFilter,
                            'mode' => 'xlsx')),
                        'bi-file-earmark-excel',
                        'menu_item_lists_export'
                    );
                    $page->addPageFunctionsMenuItem(
                        'menu_item_lists_odf',
                        $gL10n->get('SYS_ODF_SPREADSHEET'),
                        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category_report.php', array(
                            'crt_id' => $getCrtId,
                            'filter' => $getFilter,
                            'export_and_filter' => $getExportAndFilter,
                            'mode' => 'ods')),
                        'bi-file-earmark-spreadsheet',
                        'menu_item_lists_export'
                    );
                    $page->addPageFunctionsMenuItem(
                        'menu_item_lists_csv',
                        $gL10n->get('SYS_COMMA_SEPARATED_FILE'),
                        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category_report.php', array(
                            'crt_id' => $getCrtId,
                            'filter' => $getFilter,
                            'export_and_filter' => $getExportAndFilter,
                            'mode' => 'csv-oo')),
                        'bi-filetype-csv',
                        'menu_item_lists_export'
                    );
                    $page->addPageFunctionsMenuItem(
                        'menu_item_lists_pdf',
                        $gL10n->get('SYS_PDF') . ' (' . $gL10n->get('SYS_PORTRAIT') . ')',
                        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category_report.php', array(
                            'crt_id' => $getCrtId,
                            'filter' => $getFilter,
                            'export_and_filter' => $getExportAndFilter,
                            'mode' => 'pdf')),
                        'bi-file-earmark-pdf',
                        'menu_item_lists_export'
                    );
                    $page->addPageFunctionsMenuItem(
                        'menu_item_lists_pdfl',
                        $gL10n->get('SYS_PDF') . ' (' . $gL10n->get('SYS_LANDSCAPE') . ')',
                        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category_report.php', array(
                            'crt_id' => $getCrtId,
                            'filter' => $getFilter,
                            'export_and_filter' => $getExportAndFilter,
                            'mode' => 'pdfl')),
                        'bi-file-earmark-pdf',
                        'menu_item_lists_export'
                    );
                } else {
                    // if filter is not enabled, reset filterstring
                    $getFilter = '';
                }

                if ($gCurrentUser->isAdministrator()) {
                    $page->addPageFunctionsMenuItem(
                        'menu_item_category_report_manage',
                        $gL10n->get('SYS_MANAGE_CATEGORY_REPORTS'),
                        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category_report.php', array('mode' => 'manage')),
                        'bi-gear-fill'
                    );
                }

                ChangelogService::displayHistoryButton($page, 'categoryreport', 'category_report');

                // process changes in the navbar form with javascript submit
                $page->addJavascript(
                    '
                    $("#export_and_filter").change(function() {
                        $("#adm_navbar_filter_form_category_report").submit();
                    });
                    $("#crt_id").change(function() {
                        $("#adm_navbar_filter_form_category_report").submit();
                    });',
                    true
                );

                foreach ($config as $key => $item) {
                    $selectBoxEntries[$item['id']] = $item['name'];
                }

                // create filter menu with elements for role
                $form = new FormPresenter(
                    'adm_navbar_filter_form_category_report',
                    'sys-template-parts/form.filter.tpl',
                    '',
                    $page,
                    array('type' => 'navbar', 'setFocus' => false)
                );
                $form->addSelectBox(
                    'crt_id',
                    $gL10n->get('SYS_SELECT_REPORT'),
                    $selectBoxEntries,
                    array('showContextDependentFirstEntry' => false, 'defaultValue' => $getCrtId)
                );
                if ($getExportAndFilter) {
                    $form->addInput('filter', $gL10n->get('SYS_FILTER'), $getFilter);
                }
                $form->addCheckbox('export_and_filter', $gL10n->get('SYS_FILTER_TO_EXPORT'), $getExportAndFilter);
                $form->addToHtmlPage();

                $page->addHtml('<h5 class="admidio-content-subheader">' . $subHeadline . '</h5>');
                if ($reportDescription !== '') {
                    $page->addHtml('<p>' . $reportDescription . '</p>');
                }
                if ($numMembers === 0) {
                    $page->addHtml('<div class="alert alert-info">' . $gL10n->get('SYS_NO_USER_FOUND') . '</div>');
                }

                $smarty->assign('classTable', $classTable);
                if (!$getExportAndFilter && $numMembers > 0) {
                    $categoryReportTable = new DataTables($page, 'adm_lists_table');
                    $categoryReportTable->setRowsPerPage($gSettingsManager->getInt('groups_roles_members_per_page'));
                }
            }
        }

        $columnAlign = array('right');
        $columnValues = array($gL10n->get('SYS_ABR_NO'));
        $columnNumber = 1;
        foreach ($report->headerData as $columnHeader) {
            // bei Profilfeldern ist in 'id' die usf_id, ansonsten 0
            $usf_id = $columnHeader['id'];
            $fieldType = $profileFieldTypes[$usf_id] ?? '';

            if ($fieldType == 'NUMBER' || $fieldType == 'DECIMAL_NUMBER') {
                $columnAlign[] = 'right';
            } elseif ($fieldType == 'CHECKBOX' || $usf_id === 0) {
                // bei allen Feldern, die kein Profilfeld sind (usf_id = 0) und Checkboxen wird zentriert
                $columnAlign[] = 'center';
            } else {
                $columnAlign[] = 'left';
            }

            if ($getMode == 'csv') {
                if ($columnNumber === 1) {
                    $csvRows[] = array($gL10n->get('SYS_ABR_NO'));
                }
                $csvRows[0][] = html_entity_decode((string)$columnHeader['data'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (in_array($getMode, array('xlsx', 'ods'), true)) {
                // convert html characters to plain text
                $columnValues[] = html_entity_decode($columnHeader['data'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif ($getMode == 'html' || $getMode == 'print' || $getMode == 'pdf') {
                $columnValues[] = $columnHeader['data'];
            }
            $columnNumber++;
        }

        if (in_array($getMode, array('xlsx', 'ods'), true)) {
            $spreadsheet = new Spreadsheet();
            $activeSheet = $spreadsheet->getActiveSheet();
            $activeSheet->fromArray(array_values($columnValues));
        } else {
            $data['headers'] = $columnValues;
            $data['column_align'] = $columnAlign;
        }

        // die Daten einlesen
        foreach ($outputRows as $outputRow) {
            $member = $outputRow['member'];
            $memberdata = $outputRow['data'];
            $isSummary = $outputRow['summary'];
            $rowNumber = $outputRow['number'];
            $columnValues = array();
            $csvValues = array($isSummary ? '' : $rowNumber);
            $userUuid = $report->userUuids[(int)$member] ?? '';

            // Felder zu Datensatz
            $columnNumber = 1;
            foreach ($memberdata as $key => $content) {
                if (in_array($getMode, array('html', 'print', 'pdf', 'xlsx', 'ods'), true)) {
                    if ($columnNumber === 1) {
                        // die Laufende Nummer noch davorsetzen
                        $columnValues[] = $isSummary ? '' : $rowNumber;
                    }
                }


                // create output format


                $usf_id = $report->headerData[$key]['id'];
                $fieldType = $profileFieldTypes[$usf_id] ?? '';

                if ($usf_id !== 0
                    && in_array($getMode, array('xlsx', 'ods', 'csv', 'pdf'), true)
                    && $content > 0
                    && in_array($fieldType, array('DROPDOWN', 'DROPDOWN_MULTISELECT', 'RADIO_BUTTON'), true)) {
                    // show selected text of optionfield or combobox
                    if (!isset($profileFieldOptions[$usf_id])) {
                        $profileFieldOptions[$usf_id] = $gProfileFields->getPropertyById($usf_id, 'ufo_usf_options', 'text');
                    }
                    $arrOptions = $profileFieldOptions[$usf_id];
                    // if the content is an array, then we have to loop through the array
                    if (is_array($content)) {
                        $content = array_map(function ($value) use ($arrOptions) {
                            return isset($arrOptions[$value]) ? $arrOptions[$value] : '';
                        }, $content);
                        $content = implode(', ', $content);
                    } else {
                        $content = $arrOptions[$content];
                    }
                }

                if ($usf_id === 0 && $content === true) {       // alle Spalten außer Profilfelder
                    if (in_array($getMode, array('xlsx', 'ods', 'csv', 'pdf'), true)) {
                        $content = 'X';
                    } else {
                        $content = '<i class="bi bi-check-lg"></i>';
                    }
                }

                if ($getMode == 'csv') {
                    // special case for checkbox profile fields
                    if ($usf_id !== 0 && $fieldType === 'CHECKBOX') {
                        $content = ($content) ? 'X' : '';
                    }
                    $csvValues[] = (string)$content;
                } // pdf should show only text and not much html content
                elseif ($getMode === 'pdf') {
                    // special case for checkbox profile fields
                    if ($usf_id !== 0 && $fieldType === 'CHECKBOX') {
                        $content = ($content) ? 'X' : '';
                    }
                    $columnValues[] = $content;
                } else {                   // create output in html layout for getMode = html or print
                    if ($usf_id !== 0) {     // profile fields
                        if ($getMode === 'html'
                            && ($usf_id === (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id')
                                || $usf_id === (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'))) {
                            $htmlValue = $gProfileFields->getHtmlValue($profileFieldNames[$usf_id], $content);
                            $columnValues[] = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $userUuid)) . '">' . $htmlValue . '</a>';
                        } else {
                            // within print or spreadsheet mode no links should be set
                            if (in_array($getMode, array('print', 'xlsx', 'ods'), true)
                                && in_array($fieldType, array('EMAIL', 'PHONE', 'URL'), true)) {
                                $columnValues[] = $content;
                            } elseif (in_array($getMode, array('xlsx', 'ods'), true)
                                && in_array($fieldType, array('DROPDOWN', 'DROPDOWN_MULTISELECT', 'RADIO_BUTTON', 'CHECKBOX'), true)) {
                                if ($fieldType === 'CHECKBOX') {
                                    $columnValues[] = ($content) ? 'X' : '';
                                } else {
                                    $columnValues[] = $content;
                                }
                            } else {
                                // checkbox must set a sorting value
                                if ($fieldType === 'CHECKBOX') {
                                    $columnValues[] = array('value' => $gProfileFields->getHtmlValue($profileFieldNames[$usf_id], $content), 'order' => $content);
                                } else {
                                    $columnValues[] = $gProfileFields->getHtmlValue($profileFieldNames[$usf_id], $content, $userUuid);
                                }
                            }
                        }
                    } else {            // all other fields except profile fields
                        // if empty string pass a whitespace
                        if (strlen($content) > 0) {
                            $columnValues[] = $content;
                        } else {
                            $columnValues[] = '&nbsp;';
                        }
                    }
                }
                $columnNumber++;
            }

            if ($getMode == 'csv') {
                $csvRows[] = $csvValues;
            } elseif (in_array($getMode, array('xlsx', 'ods'), true)) {
                $currentRow = ($isSummary ? $numMembers + 1 : $rowNumber) + 1; // +1 for headerColumn offset
                foreach ($columnValues as $currentCol => $cell) {
                    $currentCol += 1; // array starting with 0 but first column is 1 in spreadsheet

                    // convert html characters to plain text
                    $cell = html_entity_decode((string)$cell, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $colLetter = Coordinate::stringFromColumnIndex($currentCol);
                    $activeSheet->setCellValue($colLetter . $currentRow, $cell);
                }
            } else {
                $data['rows'][] = array('id' => $isSummary ? 'row-total' : 'row-' . $rowNumber,
                    'data' => $columnValues);
            }
        }  // End-For (jeder gefundene User)

    // Settings for export file
        if ($getMode === 'csv' || $getMode === 'pdf') {
            $filename = FileSystemUtils::getSanitizedPathEntry($filename) . '.' . $getMode;

            header('Content-Disposition: attachment; filename="' . $filename . '"');

            // necessary for IE6 to 8, because without it the download with SSL has problems
            header('Cache-Control: private');
            header('Pragma: public');
        }

        if ($getMode === 'csv') {
            // download CSV file
            header('Content-Type: text/comma-separated-values; charset=' . $charset);
            echo CategoryReportOutput::createCsv($csvRows);
        } elseif (in_array($getMode, array('xlsx', 'ods'), true)) {
            $filename = FileSystemUtils::getSanitizedPathEntry($filename) . '.' . $getMode;
            self::formatSpreadsheet($spreadsheet, $columnCount + 1, true);

            if ($getMode === 'xlsx') {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=' . $charset);
                $writer = new Xlsx($spreadsheet);
            } else {
                header('Content-Type: application/vnd.oasis.opendocument.spreadsheet; charset=' . $charset);
                $writer = new Ods($spreadsheet);
            }
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
        } elseif ($getMode === 'pdf') {
            // send the new PDF to the User
            // Using Smarty templating engine for exporting table in PDF
            $smarty->assign('attributes', array('border' => '1', 'cellpadding' => '1'));
            $smarty->assign('columnAlign', $data['column_align']);
            $smarty->assign('headers', $data['headers']);
            $smarty->assign('headersStyle', 'font-size:10pt;background-color:#C7C7C7;');
            $smarty->assign('rows', $data['rows']);
            $smarty->assign('rowsStyle', 'font-size:10pt;');

            // Fetch the HTML table from our Smarty template
            $smarty->assign('exportMode', true);
            $htmlTable = $smarty->fetch('modules/category-report.list.tpl');

            // output the HTML content
            $pdf->writeTable($htmlTable);

            $file = ADMIDIO_PATH . FOLDER_TEMP_DATA . '/' . $filename;

            // Save PDF to file
            // Preserve the exact export path instead of the engine's sanitized filename.
            FileSystemUtils::writeFile($file, $pdf->getOutPDFString());

            // Redirect
            header('Content-Type: application/pdf');

            readfile($file);
            ignore_user_abort(true);

            try {
                FileSystemUtils::deleteFileIfExists($file);
            } catch (\RuntimeException $exception) {
                $gLogger->error('Could not delete file!', array('filePath' => $file));
                // TODO
            }
        } elseif ($getMode == 'html' && $getExportAndFilter) {
            $page->addJavascript(
                '
                const categoryReportScrollContainer = document.getElementById("adm_category_report_scroll");
                const resizeCategoryReportScrollContainer = function() {
                    const minimumHeight = 320;
                    const bottomSpacing = 16;
                    const availableHeight = document.documentElement.clientHeight
                        - categoryReportScrollContainer.getBoundingClientRect().top
                        - bottomSpacing;

                    categoryReportScrollContainer.style.height = Math.max(minimumHeight, availableHeight) + "px";
                };

                resizeCategoryReportScrollContainer();
                $(window).on("resize", resizeCategoryReportScrollContainer);',
                true
            );
            $page->addHtml('<div id="adm_category_report_scroll" class="admidio-category-report-scroll">');
            $smarty->assign('columnAlign', $data['column_align']);
            $smarty->assign('headers', $data['headers']);
            $smarty->assign('rows', $data['rows']);

            // Fetch the HTML table from our Smarty template
            $htmlTable = $smarty->fetch('modules/category-report.list.tpl');
            $page->addHtml($htmlTable);
            $page->addHtml('</div>');
            $page->show();
        } elseif (($getMode == 'html' && !$getExportAndFilter) || $getMode == 'print') {
            if (isset($categoryReportTable)) {
                // we are in datatable mode
                $categoryReportTable->createJavascript(count($data['rows']), count($data['headers']));
                $categoryReportTable->setColumnAlignByArray($data['column_align']);
                $page->addHtml('<div class="table-responsive">');
            }

            $smarty->assign('columnAlign', $data['column_align']);
            $smarty->assign('headers', $data['headers']);
            $smarty->assign('rows', $data['rows']);

            // Fetch the HTML table from our Smarty template
            $htmlTable = $smarty->fetch('modules/category-report.list.tpl');
            $page->addHtml($htmlTable);
            if (isset($categoryReportTable)) {
                $page->addHtml('</div>');
            }
            $page->show();
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
    private static function formatSpreadsheet(Spreadsheet $spreadsheet, int $columnCount, bool $containsHeadline) : void
    {
        $activeSheet = $spreadsheet->getActiveSheet();
        $lastColumn  = Coordinate::stringFromColumnIndex($columnCount);

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
