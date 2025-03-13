<?php

namespace Admidio\UI\Presenter;

// PhpSpreadsheet namespaces
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// TCPDF namespace
use TCPDF;

// Admidio namespaces
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

use HtmlTable;
use InvalidArgumentException;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new Menu('adm_menu', $headline);
 * $page->createEditForm();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class InventoryPresenter extends PagePresenter
{
    protected ItemsData $itemsData;
    private HtmlTable $inventoryTable;

    protected string $getFilterString = ''; //admFuncVariableIsValid($_GET, 'items_filter_string', 'string', array('defaultValue' => '')); // search string
    protected string $getFilterCategory = ''; // admFuncVariableIsValid($_GET, 'items_filter_category', 'string', array('defaultValue' => '')); // category selection
    protected int $getFilterKeeper = 0; // admFuncVariableIsValid($_GET, 'items_filter_keeper', 'int', array('defaultValue' => 0)); // keeper selection
    protected bool $getAllItems =  false; //admFuncVariableIsValid($_GET, 'items_show_all', 'bool', array('defaultValue' => false)); // show all items
    protected int $getItemId = 0; //admFuncVariableIsValid($_GET, 'item_id', 'int', array('defaultValue' => 0)); // item id
    protected bool $getExportAndFilter = false; //admFuncVariableIsValid($_GET, 'export_and_filter', 'bool', array('defaultValue' => false)); // export and filter

    protected string $mode = '';
    protected string $separator = '';
    protected string $valueQuotes = '';
    protected string $charset = '';
    protected string $classTable = '';
    protected string $orientation = '';
    protected string $filename = '';

    protected array  $exportHeader = array();
    protected array  $exportRows = array();
    protected array  $exportStrikethroughs = array();
    protected array  $modeSettings = array();
    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $categoryUUID UUID of the category for which the topics should be filtered.
     * @throws Exception
     */
    public function __construct(string $id = 'admidio-inventory')
    {
        global $gDb, $gL10n, $gSettingsManager, $gCurrentOrgId;

#region Initialize and check the parameters
        //$this->getPrintMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'print', 'validValues' => array('csv-ms', 'csv-oo', 'xlsx', 'ods', 'print', 'pdf', 'pdfl')));
        $this->getFilterString = admFuncVariableIsValid($_GET, 'items_filter_string', 'string', array('defaultValue' => '')); // search string
        $this->getFilterCategory = admFuncVariableIsValid($_GET, 'items_filter_category', 'string', array('defaultValue' => '')); // category selection
        $this->getFilterKeeper = admFuncVariableIsValid($_GET, 'items_filter_keeper', 'int', array('defaultValue' => 0)); // keeper selection
        $this->getAllItems = admFuncVariableIsValid($_GET, 'items_show_all', 'bool', array('defaultValue' => false)); // show all items
        $this->getItemId = admFuncVariableIsValid($_GET, 'item_id', 'int', array('defaultValue' => 0)); // item id
        $this->getExportAndFilter = admFuncVariableIsValid($_GET, 'export_and_filter', 'bool', array('defaultValue' => false)); // export and filter
#endregion

#region Initialize some special mode parameters
        $this->filename = $gSettingsManager->get('inventory_export_filename');
        if ($gSettingsManager->getBool('inventory_add_date')) {
            // add system date format to filename
            $this->filename .= '_' . date($gSettingsManager->get('system_date'));
        }

        $this->modeSettings = array(
        //  Mode                 mode,      seperator,  valueQuotes,    charset,        classTable,                             orientation
            'csv-ms'    => array('csv',     ';',        '"',            'iso-8859-1',   '',                                     ''),
            'csv-oo'    => array('csv',     ',',        '"',            'utf-8',        '',                                     ''),
            'xlsx'      => array('xlsx',    '',         '',             '',             '',                                     ''),
            'ods'       => array('ods',     '',         '',             '',             '',                                     ''),
            'html'      => array('html',    '',         '',             '',             'table table-condensed',                ''),
            'print'     => array('print',   '',         '',             '',             'table table-condensed table-striped',  ''),
            'pdf'       => array('pdf',     '',         '',             '',             'table',                                'P'),
            'pdfl'      => array('pdf',     '',         '',             '',             'table',                                'L')
        );
#endregion

        //$this->categoryUUID = $categoryUUID;
        $this->itemsData = new ItemsData($gDb, $gCurrentOrgId);
        $this->itemsData->showFormerItems($this->getAllItems);
        $this->itemsData->readItems();

        $this->setHeadline($gL10n->get('SYS_INVENTORY'));
        $this->setContentFullWidth();

        parent::__construct($id);
    }

    /**
     * Set the mode of the table.
     * @param string $mode Mode of the table (html, pdf, csv, ods, xlsx)
     */
    private function SetMode(string $mode = 'html')
    {
        if (isset($this->modeSettings[$mode])) {
            [$this->mode, $this->separator, $this->valueQuotes, $this->charset, $this->classTable, $this->orientation] = $this->modeSettings[$mode];
        }
    }

    /**
     * Create the data for the edit form of a item field.
     * @param string $itemFieldID ID of the item field that should be edited.
     * @throws Exception
     */
    public function createHTMLPage()
    {
        global $gCurrentUser, $gL10n, $gDb, $gSettingsManager, $gCurrentOrgId, $gProfileFields;

        $this->addJavascript('
            $("#menu_item_lists_print_view").click(function() {
                window.open("'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array(
                            'items_filter_string'   => $this->getFilterString,
                            'items_filter_category' => $this->getFilterCategory, 
                            'items_filter_keeper'   => $this->getFilterKeeper,
                            'items'                 => $this->getAllItems,
                            'export_and_filter'     => $this->getExportAndFilter,
                            'mode'                  => 'print_preview'
                        )
                    ) . '",
                    "_blank"
                );
            });',
            true
        );

        if ($this->getExportAndFilter) {
            // link to print overlay and exports
            $this->addPageFunctionsMenuItem(
                'menu_item_lists_print_view',
                $gL10n->get('SYS_PRINT_PREVIEW'),
                'javascript:void(0);',
                'bi-printer-fill'
            );

            $this->addPageFunctionsMenuItem(
                'menu_item_lists_export',
                $gL10n->get('SYS_EXPORT_TO'),
                '#',
                'bi-download'
            );
            $this->addPageFunctionsMenuItem(
                'menu_item_lists_xlsx',
                $gL10n->get('SYS_MICROSOFT_EXCEL') .' (*.xlsx)',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array(
                        'items_filter_string'   => $this->getFilterString,
                        'items_filter_category' => $this->getFilterCategory,
                        'items_filter_keeper'   => $this->getFilterKeeper,
                        'items'                 => $this->getAllItems,
                        'export_and_filter'     => $this->getExportAndFilter,
                        'mode'                  => 'print_xlsx'
                    )
                ),
                'bi-filetype-xlsx',
                'menu_item_lists_export'
            );
            $this->addPageFunctionsMenuItem(
                'menu_item_lists_ods',
                $gL10n->get('SYS_ODF_SPREADSHEET'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array(
                        'items_filter_string'   => $this->getFilterString,
                        'items_filter_category' => $this->getFilterCategory,
                        'items_filter_keeper'   => $this->getFilterKeeper,
                        'items'                 => $this->getAllItems,
                        'export_and_filter'     => $this->getExportAndFilter,
                        'mode'                  => 'print_ods'
                    )
                ),
                'bi-file-earmark-spreadsheet',
                'menu_item_lists_export'
            );
            $this->addPageFunctionsMenuItem(
                'menu_item_lists_csv_ms',
                $gL10n->get('SYS_CSV') . ' (' . $gL10n->get('SYS_ISO_8859_1') . ')',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array(
                        'items_filter_string'   => $this->getFilterString,
                        'items_filter_category' => $this->getFilterCategory,
                        'items_filter_keeper'   => $this->getFilterKeeper,
                        'items'                 => $this->getAllItems,
                        'export_and_filter'     => $this->getExportAndFilter,
                        'mode'                  => 'print_csv-ms'
                    )
                ),
                'bi-filetype-csv',
                'menu_item_lists_export'
            );
            $this->addPageFunctionsMenuItem(
                'menu_item_lists_csv',
                $gL10n->get('SYS_CSV') . ' (' . $gL10n->get('SYS_UTF8') . ')',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array(
                        'items_filter_string'   => $this->getFilterString,
                        'items_filter_category' => $this->getFilterCategory,
                        'items_filter_keeper'   => $this->getFilterKeeper,
                        'items'                 => $this->getAllItems,
                        'export_and_filter'     => $this->getExportAndFilter,
                        'mode'                  => 'print_csv-oo'
                    )
                ),
                'bi-filetype-csv',
                'menu_item_lists_export'
            );
            $this->addPageFunctionsMenuItem(
                'menu_item_lists_pdf',
                $gL10n->get('SYS_PDF') . ' (' . $gL10n->get('SYS_PORTRAIT') . ')',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array(
                        'items_filter_string'   => $this->getFilterString,
                        'items_filter_category' => $this->getFilterCategory,
                        'items_filter_keeper'   => $this->getFilterKeeper,
                        'items'                 => $this->getAllItems,
                        'export_and_filter'     => $this->getExportAndFilter,
                        'mode'                  => 'print_pdf'
                    )
                ),
                'bi-filetype-pdf',
                'menu_item_lists_export'
            );
            $this->addPageFunctionsMenuItem(
                'menu_item_lists_pdfl',
                $gL10n->get('SYS_PDF') . ' (' . $gL10n->get('SYS_LANDSCAPE') . ')',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array(
                        'items_filter_string'   => $this->getFilterString,
                        'items_filter_category' => $this->getFilterCategory,
                        'items_filter_keeper'   => $this->getFilterKeeper,
                        'items'                 => $this->getAllItems,
                        'export_and_filter'     => $this->getExportAndFilter,
                        'mode'                  => 'print_pdfl'
                    )
                ),
                'bi-filetype-pdf',
                'menu_item_lists_export'
            );
        }
        else {
            // if filter is not enabled, reset filterstring
            $this->getFilterString = '';
            $this->getFilterCategory = '';
            $this->getFilterKeeper = 0;
        }

        if ($gCurrentUser->editInventory()) {
            if ($gSettingsManager->getBool('changelog_module_enabled')) {
                // show link to view profile field change history
                //ChangelogService::displayHistoryButton($page, 'inventory', 'inventory');
                $this->addPageFunctionsMenuItem(
                    'menu_item_inventory_change_history',
                    $gL10n->get('SYS_CHANGE_HISTORY'),
                    ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory_history.php',
                    'bi-clock-history'
                );
            }
            
            // show link to create new item
            $this->addPageFunctionsMenuItem(
                'menu_item_inventory_create_item',
                $gL10n->get('SYS_INVENTORY_ITEM_CREATE'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php' , array('mode' => 'item_edit')),
                'bi-plus-circle-fill'
            );

            // show link to import users
            $this->addPageFunctionsMenuItem(
                'menu_item_inventory_import_items',
                $gL10n->get('SYS_INVENTORY_IMPORT_ITEMS'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php' , array('mode' => 'items_import')),
                'bi-upload'
            );
        }
        
        if ($gCurrentUser->isAdministrator()) {
            // show link to maintain fields
            $this->addPageFunctionsMenuItem(
                'menu_item_inventory_item_fields',
                $gL10n->get('SYS_INVENTORY_ITEMFIELDS_EDIT'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php' , array('mode' => 'field_list')),
                'bi-ui-radios'
            );
        }

        if ($gCurrentUser->editInventory()) {
            $this->addJavascript('
                $("#export_and_filter").change(function() {
                    $("#adm_navbar_filter_form").submit();
                });

                // change mode of items that should be shown
                $("#items_show_all").change(function() {
                    $("#adm_navbar_filter_form").submit();
                });',
                true
            );

            $form = new FormPresenter(
                'adm_navbar_filter_form',
                'sys-template-parts/form.filter.tpl',
                '',
                $this,
                array('type' => 'navbar', 'setFocus' => false)
            );
            
            if ($this->getExportAndFilter) {
                $this->addJavascript('
                    $("#items_filter_category").change(function () {
                        self.location.href = "'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array(
                                'items_filter_string'   => $this->getFilterString,
                                'items_filter_keeper'   => $this->getFilterKeeper,
                                'items'                 => $this->getAllItems,
                                'export_and_filter'     => $this->getExportAndFilter
                            )
                        ) . '&items_filter_category=" + $(this).val();
                    });

                    $("#items_filter_keeper").change(function () {
                        self.location.href = "'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array(
                                'items_filter_string'   => $this->getFilterString,
                                'items_filter_category' => $this->getFilterCategory,
                                'items'                 => $this->getAllItems,
                                'export_and_filter'     => $this->getExportAndFilter
                                )
                        ) . '&items_filter_keeper=" + $(this).val();
                    });

                    let timer;
                    document.getElementById("items_filter_string").addEventListener("input", function() {
                        clearTimeout(timer); // Clear any previous timer

                        timer = setTimeout(function() {
                            $("#adm_navbar_filter_form").submit(); // Submit after timeout
                        }, 250);
                    });',
                    true
                );

                // filter string
                $form->addInput('items_filter_string', $gL10n->get('SYS_FILTER'), $this->getFilterString);
                
                foreach ($this->itemsData->getItemFields() as $itemField) {  
                    $infNameIntern = $itemField->getValue('inf_name_intern');
                
                    if ($this->itemsData->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN') {
                        $arrListValues = $this->itemsData->getProperty($infNameIntern, 'inf_value_list');
                        $defaultValue  = $this->itemsData->getValue($infNameIntern, 'database');

                        // filter category
                        $form->addSelectBox(
                            'items_filter_category',
                            $gL10n->get('SYS_CATEGORY'),
                            $arrListValues,
                            array(
                                'defaultValue'    => $this->getFilterCategory,
                                'showContextDependentFirstEntry' => true
                            )
                        );
                    }
                }
            
                // read all keeper
                $sql = 'SELECT DISTINCT ind_value, 
                    CASE 
                        WHEN ind_value = -1 THEN \'n/a\'
                        ELSE CONCAT_WS(\', \', last_name.usd_value, first_name.usd_value)
                    END as keeper_name
                    FROM '.TBL_INVENTORY_DATA.'
                    INNER JOIN '.TBL_INVENTORY_FIELDS.'
                        ON inf_id = ind_inf_id
                    LEFT JOIN '. TBL_USER_DATA. ' as last_name
                        ON last_name.usd_usr_id = ind_value
                        AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
                    LEFT JOIN '. TBL_USER_DATA. ' as first_name
                        ON first_name.usd_usr_id = ind_value
                        AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
                    WHERE (inf_org_id  = '. $gCurrentOrgId .'
                        OR inf_org_id IS NULL)
                    AND inf_name_intern = \'KEEPER\'
                    ORDER BY keeper_name ASC;';

                // filter keeper
                $form->addSelectBoxFromSql(
                    'items_filter_keeper',
                    $gL10n->get('SYS_INVENTORY_KEEPER'),
                    $gDb,
                    $sql,
                    array('defaultValue' => $this->getFilterKeeper , 'showContextDependentFirstEntry' => true)
                );
            }

            // fiter for export
            $form->addCheckbox(
                'export_and_filter',
                $gL10n->get('SYS_FILTER_TO_EXPORT'),
                $this->getExportAndFilter
            );

            // filter all items
            $form->addCheckbox(
                'items_show_all',
                $gL10n->get('SYS_SHOW_ALL'),
                $this->getAllItems,
                array('helpTextId' => 'SYS_SHOW_ALL_DESC')
            );

            $form->addToHtmlPage();
        }

        $this->SetMode('html');

        $this->inventoryTable = new HtmlTable('adm_inventory_table', $this, true, true, $this->classTable);
        $this->inventoryTable->setDatatablesRowsPerPage($gSettingsManager->getInt('inventory_items_per_page'));

        $this->FillTable();

        $this->addHtml($this->inventoryTable->show());
    }

    /**
     * Create the data for the print preview of the inventory.
     * @throws Exception
     */
    public function createPrintPreview(): void
    {
        $this->SetMode('print');

        $this->inventoryTable = new HtmlTable('adm_inventory_table', $this, false, false, $this->classTable);

        $this->FillTable();
        $this->addHtml($this->inventoryTable->show());
    }


    public function createExport(string $mode = 'pdf'): void
    {
        global  $gLogger, $gCurrentUser, $gL10n, $gCurrentOrganization;
        $this->SetMode($mode);

        switch ($this->mode) {
            case 'pdf':
                $pdf = new TCPDF($this->orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

                // set document information
                $pdf->SetCreator(PDF_CREATOR);
                $pdf->SetAuthor('Admidio');
                $pdf->SetTitle($this->headline);
        
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
                $pdf->setHeaderData('', 0, $this->headline, '');
        
                // set font
                $pdf->SetFont('times', '', 10);
        
                // add a page
                $pdf->AddPage();
        
                // Create table object for display
                $this->inventoryTable = new HtmlTable('adm_inventory_table', $this, false, false, $this->classTable);

                $this->inventoryTable->addAttribute('border', '1');
                $this->inventoryTable->addAttribute('cellpadding', '1');
        
                $this->FillTable();

                $pdf->writeHTML($this->inventoryTable->getHtmlTable(), true, false, true);
                $file = ADMIDIO_PATH . FOLDER_DATA . '/temp/' . $this->filename;
                $pdf->Output($file, 'F');
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $this->filename . '"');

                // necessary for IE6 to 8, because without it the download with SSL has problems
                header('Cache-Control: private');
                header('Pragma: public');
                readfile($file);
                ignore_user_abort(true);
                try {
                    FileSystemUtils::deleteFileIfExists($file);
                }
                catch (\RuntimeException $exception) {
                    $gLogger->error('Could not delete file!', array('filePath' => $file));
                }
                break;
        
            case 'csv':
            case 'ods':
            case 'xlsx':
                $this->FillTable();

                $contentType = match ($this->mode) {
                    'csv' => 'text/csv; charset=' . $this->charset,
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
                    default => throw new InvalidArgumentException('Invalid mode'),
                };
        
                $writerClass = match ($this->mode) {
                    'csv' => Csv::class,
                    'xlsx' => Xlsx::class,
                    'ods' => Ods::class,
                    default => throw new InvalidArgumentException('Invalid mode'),
                };
        
                header('Content-disposition: attachment; filename="' . $this->filename . '"');
                header("Content-Type: $contentType");
                header('Content-Transfer-Encoding: binary');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
        
                $spreadsheet = new Spreadsheet();
                $spreadsheet->getProperties()
                    ->setCreator($gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'))
                    ->setTitle($this->filename)
                    ->setSubject($gL10n->get('PLG_INVENTORY_MANAGER_ITEMLIST'))
                    ->setCompany($gCurrentOrganization->getValue('org_longname'))
                    ->setKeywords($gL10n->get('PLG_INVENTORY_MANAGER_NAME_OF_PLUGIN') . ', ' . $gL10n->get('PLG_INVENTORY_MANAGER_ITEM'))
                    ->setDescription($gL10n->get('PLG_INVENTORY_MANAGER_CREATED_WITH'));
        
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->fromArray(array_keys($this->exportHeader), NULL, 'A1');
                $sheet->fromArray($this->exportRows, NULL, 'A2');
        
                if (!$this->mode == 'csv') {
                    foreach ($this->exportStrikethroughs as $index => $strikethrough) {
                        if ($strikethrough) {
                            $sheet->getStyle('A' . ($index + 2) . ':' . $sheet->getHighestColumn() . ($index + 2))
                                ->getFont()->setStrikethrough(true);
                        }
                    }
        
                    $this->formatSpreadsheet($spreadsheet, $this->exportRows, true);
                }
        
                $writer = new $writerClass($spreadsheet);
                $writer->save('php://output');
                break;

            default:
                throw new InvalidArgumentException('Invalid mode');
        }
    }

    /**
     * Check if the keeper is authorized to edit spezific item data
     * 
     * @param int|null $keeper 			The user ID of the keeper
     * @return bool 					true if the keeper is authorized
     */
    private function isKeeperAuthorizedToEdit(?int $keeper = null): bool
    {
        global $gSettingsManager, $gCurrentUser;

        if ($gSettingsManager->get('inventory_allow_keeper_edit') === 1) {
            if (isset($keeper) && $keeper === $gCurrentUser->getValue('usr_id')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fill the table with the data of the items.
     * @param string $mode Mode of the table (html, pdf, csv, ods, xlsx)
     */
    private function FillTable()
    {
        global $gCurrentUser, $gL10n, $gDb, $gSettingsManager, $gCurrentOrgId, $gCurrentOrganization, $gProfileFields, $gCurrentSession;

        // initialize array parameters for table and set the first column for the counter
        $columnAlign  = ($this->mode == 'html') ? array('left') : array('center');
        $columnValues = array($gL10n->get('SYS_ABR_NO'));
    
        // headlines for columns
        $columnNumber = 1;

        foreach ($this->itemsData->getItemFields() as $itemField) {
            $infNameIntern = $itemField->getValue('inf_name_intern');
            $columnHeader = $this->itemsData->getProperty($infNameIntern, 'inf_name');

            switch ($this->itemsData->getProperty($infNameIntern, 'inf_type')) {
                case 'CHECKBOX':
                case 'RADIO_BUTTON':
                case 'GENDER':
                    $columnAlign[] = 'center';
                    break;

                case 'NUMBER':
                case 'DECIMAL':
                    $columnAlign[] = 'right';
                    break;

                default:
                    $columnAlign[] = 'left';
                    break;
            }

            if ($this->mode == 'pdf' && $columnNumber === 1) {
                $arrValidColumns[] = $gL10n->get('SYS_ABR_NO');
            }

            if ($this->mode == 'csv' || $this->mode == "ods" || $this->mode == 'xlsx' && $columnNumber === 1) {
                $this->exportHeader[$gL10n->get('SYS_ABR_NO')] = 'string';
            }

            switch ($this->mode) {
                case 'csv':
                case "ods":
                case 'xlsx':
                    $this->exportHeader[$columnHeader] = 'string';
                    break;

                case 'pdf':
                    $arrValidColumns[] = $columnHeader;
                    break;

                case 'html':
                case 'print':
                    $columnValues[] = $columnHeader;
                    break;
            }

            $columnNumber++;
        }

        if ($this->mode == 'html') {
            $columnAlign[]  = 'right';
            $columnValues[] = '&nbsp;';
            $this->inventoryTable->disableDatatablesColumnsSort(array(count($columnValues)));
            $this->inventoryTable->setDatatablesColumnsNotHideResponsive(array(count($columnValues)));
        }

        if ($this->mode == 'html' || $this->mode == 'print') {
            $this->inventoryTable->setColumnAlignByArray($columnAlign);
            $this->inventoryTable->addRowHeadingByArray($columnValues);
        }
        elseif ($this->mode == 'pdf') {
            $this->inventoryTable->setColumnAlignByArray($columnAlign);
            $this->inventoryTable->addTableHeader();
            $this->inventoryTable->addRow();
            foreach ($arrValidColumns as $column) {
                $this->inventoryTable->addColumn($column, array('style' => 'text-align:center;font-size:10;font-weight:bold;background-color:#C7C7C7;'), 'th');
            }
        }

        // create user object
        $user = new User($gDb, $gProfileFields);

        $listRowNumber = 1;

        foreach ($this->itemsData->getItems() as $item) {
            $this->itemsData->readItemData($item['ini_id']);
            $columnValues = array();
            $strikethrough = $item['ini_former'];
            $columnNumber = 1;

            foreach ($this->itemsData->getItemFields() as $itemField) {
                $infNameIntern = $itemField->getValue('inf_name_intern');

                if (($this->getFilterCategory !== '' && $infNameIntern == 'CATEGORY' && $this->getFilterCategory != $this->itemsData->getValue($infNameIntern, 'database')) ||
                        ($this->getFilterKeeper !== 0 && $infNameIntern == 'KEEPER' && $this->getFilterKeeper != $this->itemsData->getValue($infNameIntern))) {
                    continue 2;
                }

                if ($columnNumber === 1) {
                    $columnValues[] = $listRowNumber;
                }

                $content = $this->itemsData->getValue($infNameIntern, 'database');

                if ($infNameIntern == 'KEEPER' && strlen($content) > 0) {
                    $found = $user->readDataById($content);
                    if (!$found) {
                        $orgName = '"' . $gCurrentOrganization->getValue('org_longname'). '"';
                        $content = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION',array($orgName));
                    }
                    else {
                        if ($this->mode == 'html') {
                            $content = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                        }
                        else {
                            $sql = $this->itemsData->getSqlOrganizationsUsersComplete();
                            
                            $result = $gDb->queryPrepared($sql);

                            while ($row = $result->fetch()) {
                                if ($row['usr_id'] == $user->getValue('usr_id')) {
                                    $content = $row['name'];
                                    break;
                                }
                                $content = $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME');
                            }        
                        }
                    }
                }

                if ($infNameIntern == 'LAST_RECEIVER' && strlen($content) > 0) {
                    if (is_numeric($content)) {
                        $found = $user->readDataById($content);
                        if ($found) {
                            if ($this->mode == 'html') {
                                $content = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                            }
                            else {
                                $sql = $this->itemsData->getSqlOrganizationsUsersComplete();
                                $result = $gDb->queryPrepared($sql);
            
                                while ($row = $result->fetch()) {
                                    if ($row['usr_id'] == $user->getValue('usr_id')) {
                                        $content = $row['name'];
                                        break;
                                    }
                                    $content = $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME');
                                }        
                            }
                        }
                    }
                }
                if ($this->itemsData->getProperty($infNameIntern, 'inf_type') == 'CHECKBOX') {
                    $content = ($content != 1) ? 0 : 1;
                    $content = ($this->mode == 'csv' || $this->mode == 'pdf' || $this->mode == 'xlsx'|| $this->mode == 'ods') ?
                        ($content == 1 ? $gL10n->get('SYS_YES') : $gL10n->get('SYS_NO')) :
                        $this->itemsData->getHtmlValue($infNameIntern, $content);
                }
                elseif ($this->itemsData->getProperty($infNameIntern, 'inf_type') == 'DATE') {
                    $content = $this->itemsData->getHtmlValue($infNameIntern, $content);
                }
                elseif ($this->itemsData->getProperty($infNameIntern, 'inf_type') == 'DROPDOWN') {
                    $content = $this->itemsData->getHtmlValue($infNameIntern, $content);
                }
                elseif ($this->itemsData->getProperty($infNameIntern, 'inf_type') == 'RADIO_BUTTON') {
                    $content = ($this->mode == 'html') ?
                        $this->itemsData->getHtmlValue($infNameIntern, $content) :
                        $this->itemsData->getValue($infNameIntern, 'database');
                }

                $columnValues[] = ($strikethrough && $this->mode != 'csv' && $this->mode != 'ods' && $this->mode != 'xlsx') ? '<s>' . $content . '</s>' : $content;
                $columnNumber++;
            }

            if ($this->mode == 'html') {
                $tempValue = '';

                // show link to view profile field change history
/*                 if ($gSettingsManager->getBool('changelog_module_enabled')) {
                    $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/items_history.php', array('item_id' => $item['ini_id'])) . '">
                                    <i class="bi bi-clock-history" title="' . $gL10n->get('SYS_CHANGE_HISTORY') . '"></i>
                                </a>';
                } */

                // show link to edit, make former or undo former and delete item (if authorized)
                if ($gCurrentUser->isAdministrator() || $this->isKeeperAuthorizedToEdit((int)$this->itemsData->getValue('KEEPER', 'database'))) {
                    if ($gCurrentUser->isAdministrator() || ($this->isKeeperAuthorizedToEdit((int)$this->itemsData->getValue('KEEPER', 'database')) && !$item['ini_former'])) {
                        $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php' , array('mode' => 'item_edit', 'item_id' => $item['ini_id'], 'item_former' => $item['ini_former'])) . '">
                                        <i class="bi bi-pencil-square" title="' . $gL10n->get('SYS_INVENTORY_ITEM_EDIT') . '"></i>
                                    </a>';
                        $tempValue .='<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php' , array('mode' => 'item_edit', 'item_id' => $item['ini_id'], 'copy' => true)) . '">
                                        <i class="bi bi-copy" title="' . $gL10n->get('SYS_COPY') . '"></i>
                                    </a>';
        
                    }

                    if ($item['ini_former']) {
                        $tempValue .= '<a class="admidio-icon-link admidio-messagebox"
                                        href="javascript:void(0);"
                                        data-buttons="yes-no"
                                        data-message="' . $gL10n->get('SYS_INVENTORY_UNDO_FORMER_CONFIRM') . '"
                                        data-href="callUrlHideElement(\'no_element\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_undo_former', 'item_id' => $item['ini_id'], 'item_former' => $item['ini_former'])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                                        <i class="bi bi-eye" title="' . $gL10n->get('SYS_INVENTORY_UNDO_FORMER') . '"></i>
                                    </a>';
                    }

                    if ($gCurrentUser->isAdministrator() || ($this->isKeeperAuthorizedToEdit((int)$this->itemsData->getValue('KEEPER', 'database')) && !$item['ini_former'])) {
                        $tempValue .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                            data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_delete_explain_msg', 'item_id' => $item['ini_id'], 'item_former' => $item['ini_former'])) .'">
                                            <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DELETE') . '"></i>
                                        </a>';
                    }
                }
                $columnValues[] = $tempValue;
            }

            $showRow = ($this->getFilterString == '') ? true : false;

            if ($this->getFilterString !== '') {
                $showRowException = false;
                $filterArray = explode(',', $this->getFilterString);
                foreach ($filterArray as $filterString) {
                    $filterString = trim($filterString);
                    if (substr($filterString, 0, 1) == '-') {
                        $filterString = substr($filterString, 1);
                        if (stristr(implode('', $columnValues), $filterString)) {
                            $showRowException = true;
                        }
                    }
                    if (stristr(implode('', $columnValues), $filterString)) {
                        $showRow = true;
                    }
                }
                if ($showRowException) {
                    $showRow = false;
                }
            }

            if ($showRow) {
                switch ($this->mode) {
                    case 'csv':
                    case 'ods':
                    case 'xlsx':
                        $this->exportRows[] = $columnValues;
                        $this->exportStrikethroughs[] = $strikethrough;
                        break;

                    default:
                        $this->inventoryTable->addRowByArray($columnValues, 'adm_item_' . $item['ini_id'], array('nobr' => 'true'));
                        break;
                    }
            }

            ++$listRowNumber;
        }
    }

    /**
     * Formats the spreadsheet
     *
     * @param PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @param array $data
     * @param bool $containsHeadline
     */
    function formatSpreadsheet($spreadsheet, $data, $containsHeadline) : void
    {
        $alphabet = range('A', 'Z');
        $column = $alphabet[count($data[0])-1];

        if ($containsHeadline) {
            $spreadsheet
                ->getActiveSheet()
                ->getStyle('A1:'.$column.'1')
                ->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('ffdddddd');
            $spreadsheet
                ->getActiveSheet()
                ->getStyle('A1:'.$column.'1')
                ->getFont()
                ->setBold(true);
        }

        for($number = 0; $number < count($data[0]); $number++) {
            $spreadsheet->getActiveSheet()->getColumnDimension($alphabet[$number])->setAutoSize(true);
        }
        $spreadsheet->getDefaultStyle()->getAlignment()->setWrapText(true);
    }
}