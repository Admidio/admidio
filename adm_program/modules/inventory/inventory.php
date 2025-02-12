<?php
/**
 ***********************************************************************************************
 * Show and manage all items of the organization
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * getAllItems - false : (Default) Show only active items of the current organization
 *               false  : Show active and inactive items of all organizations in database
 ***********************************************************************************************
 */
// PhpSpreadsheet namespaces
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Admidio namespaces
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require_once(__DIR__ . '/../../system/login_valid.php');
    require_once(__DIR__ . '/inventory_function-test.php');

#region Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html', 'validValues' => array('csv-ms', 'csv-oo', 'xlsx', 'ods', 'html', 'print', 'pdf', 'pdfl')));
    $getFilterString = admFuncVariableIsValid($_GET, 'items_filter_string', 'string', array('defaultValue' => '')); // search string
    $getFilterCategory = admFuncVariableIsValid($_GET, 'items_filter_category', 'string', array('defaultValue' => '')); // category selection
    $getFilterKeeper = admFuncVariableIsValid($_GET, 'items_filter_keeper', 'int', array('defaultValue' => 0)); // keeper selection
    $getAllItems = admFuncVariableIsValid($_GET, 'items_show_all', 'bool', array('defaultValue' => false)); // show all items
    $getItemId = admFuncVariableIsValid($_GET, 'item_id', 'int', array('defaultValue' => 0)); // item id
    $getExportAndFilter = admFuncVariableIsValid($_GET, 'export_and_filter', 'bool', array('defaultValue' => false)); // export and filter
#endregion

#region Initialize some special mode parameters
    $page = null;
    $items = null;
    $separator = '';
    $valueQuotes = '';
    $charset = '';
    $classTable = '';
    $orientation = '';
    $filename = $gSettingsManager->get('inventory_export_filename');
    if ($gSettingsManager->getBool('inventory_add_date')) {
        // add system date format to filename
        $filename .= '_' . date($gSettingsManager->get('system_date'));
    }

    $modeSettings = array(
    //  getMode              mode,      seperator,  valueQuotes,    charset,        classTable,                             orientation
        'csv-ms'    => array('csv',     ';',        '"',            'iso-8859-1',   null,                                   null),
        'csv-oo'    => array('csv',     ',',        '"',            'utf-8',        null ,                                  null),
        'xlsx'      => array('xlsx',    null,       null,           null,           null,                                   null),
        'ods'       => array('ods',     null,       null,           null,           null,                                   null),
        'html'      => array('html',    null,       null,           null,           'table table-condensed',                null),
        'print'     => array('print',   null,       null,           null,           'table table-condensed table-striped',  null),
        'pdf'       => array('pdf',     null,       null,           null,           'table',                                'P'),
        'pdfl'      => array('pdf',     null,       null,           null,           'table',                                'L')
    );

    if (isset($modeSettings[$getMode])) {
        [$getMode, $separator, $valueQuotes, $charset, $classTable, $orientation] = $modeSettings[$getMode];
    }
#endregion

    // set headline of the script
    $headline = $gL10n->get('SYS_INVENTORY');// Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-box-seam-fill');

    
/*     // create html page object
    $page = new HtmlPage('admidio-inventory', $headline);
    $page->setContentFullWidth(); */
    $datatable = false;
    $hoverRows = false;
    
    switch ($getMode) {
        case 'csv':
        case 'ods':
        case 'xlsx':
            break;  // don't show HtmlTable
    
        case 'print':
            // create html page object without the custom theme files
            $page = new PagePresenter('admidio-inventory-print');
            $page->setPrintMode();
            $page->setTitle($headline);
            $page->setHeadline($headline);
            $inventoryTable = new HtmlTable('adm_inventory_table', $page, $hoverRows, $datatable, $classTable);
            break;
    
        case 'pdf':
            $pdf = new TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
            // set document information
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('Admidio');
            $pdf->SetTitle($headline);
    
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
            $pdf->setHeaderData('', 0, $headline, '');
    
            // set font
            $pdf->SetFont('times', '', 10);
    
            // add a page
            $pdf->AddPage();
    
            // Create table object for display
            $inventoryTable = new HtmlTable('adm_inventory_table', $page, $hoverRows, $datatable, $classTable);
    
            $inventoryTable->addAttribute('border', '1');
            $inventoryTable->addAttribute('cellpadding', '1');
            break;
    
        case 'html':
            $datatable = true;
            $hoverRows = true;
    
            // create html page object
            $page = new PagePresenter('admidio-inventory', $headline);
            $page->setContentFullWidth();

#region Navigation and page functions
            $page->addJavascript('
                $("#menu_item_lists_print_view").click(function() {
                    window.open("'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory.php', array(
                                'items_filter_string'   => $getFilterString,
                                'items_filter_category' => $getFilterCategory, 
                                'items_filter_keeper'   => $getFilterKeeper,
                                'items'                 => $getAllItems,
                                'export_and_filter'     => $getExportAndFilter,
                                'mode'                  => 'print'
                            )
                        ) . '",
                        "_blank"
                    );
                });',
                true
            );

#region Filter form
            if ($getExportAndFilter) {
                // link to print overlay and exports
                $page->addPageFunctionsMenuItem(
                    'menu_item_lists_print_view',
                    $gL10n->get('SYS_PRINT_PREVIEW'),
                    'javascript:void(0);',
                    'bi-printer-fill'
                );

#region Dropdown menu with all export possibilities
                $page->addPageFunctionsMenuItem(
                    'menu_item_lists_export',
                    $gL10n->get('SYS_EXPORT_TO'),
                    '#',
                    'bi-download'
                );
                $page->addPageFunctionsMenuItem(
                    'menu_item_lists_xlsx',
                    $gL10n->get('SYS_MICROSOFT_EXCEL') .' (*.xlsx)',
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory.php', array(
                            'items_filter_string'   => $getFilterString,
                            'items_filter_category' => $getFilterCategory,
                            'items_filter_keeper'   => $getFilterKeeper,
                            'items'                 => $getAllItems,
                            'export_and_filter'     => $getExportAndFilter,
                            'mode'                  => 'xlsx'
                        )
                    ),
                    'bi-filetype-xlsx',
                    'menu_item_lists_export'
                );
                $page->addPageFunctionsMenuItem(
                    'menu_item_lists_ods',
                    $gL10n->get('SYS_ODF_SPREADSHEET'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory.php', array(
                            'items_filter_string'   => $getFilterString,
                            'items_filter_category' => $getFilterCategory,
                            'items_filter_keeper'   => $getFilterKeeper,
                            'items'                 => $getAllItems,
                            'export_and_filter'     => $getExportAndFilter,
                            'mode'                  => 'ods'
                        )
                    ),
                    'bi-file-earmark-spreadsheet',
                    'menu_item_lists_export'
                );
                $page->addPageFunctionsMenuItem(
                    'menu_item_lists_csv_ms',
                    $gL10n->get('SYS_CSV') . ' (' . $gL10n->get('SYS_ISO_8859_1') . ')',
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory.php', array(
                            'items_filter_string'   => $getFilterString,
                            'items_filter_category' => $getFilterCategory,
                            'items_filter_keeper'   => $getFilterKeeper,
                            'items'                 => $getAllItems,
                            'export_and_filter'     => $getExportAndFilter,
                            'mode'                  => 'csv-ms'
                        )
                    ),
                    'bi-filetype-csv',
                    'menu_item_lists_export'
                );
                $page->addPageFunctionsMenuItem(
                    'menu_item_lists_csv',
                    $gL10n->get('SYS_CSV') . ' (' . $gL10n->get('SYS_UTF8') . ')',
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory.php', array(
                            'items_filter_string'   => $getFilterString,
                            'items_filter_category' => $getFilterCategory,
                            'items_filter_keeper'   => $getFilterKeeper,
                            'items'                 => $getAllItems,
                            'export_and_filter'     => $getExportAndFilter,
                            'mode'                  => 'csv-oo'
                        )
                    ),
                    'bi-filetype-csv',
                    'menu_item_lists_export'
                );
                $page->addPageFunctionsMenuItem(
                    'menu_item_lists_pdf',
                    $gL10n->get('SYS_PDF') . ' (' . $gL10n->get('SYS_PORTRAIT') . ')',
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory.php', array(
                            'items_filter_string'   => $getFilterString,
                            'items_filter_category' => $getFilterCategory,
                            'items_filter_keeper'   => $getFilterKeeper,
                            'items'                 => $getAllItems,
                            'export_and_filter'     => $getExportAndFilter,
                            'mode'                  => 'pdf'
                        )
                    ),
                    'bi-filetype-pdf',
                    'menu_item_lists_export'
                );
                $page->addPageFunctionsMenuItem(
                    'menu_item_lists_pdfl',
                    $gL10n->get('SYS_PDF') . ' (' . $gL10n->get('SYS_LANDSCAPE') . ')',
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory.php', array(
                            'items_filter_string'   => $getFilterString,
                            'items_filter_category' => $getFilterCategory,
                            'items_filter_keeper'   => $getFilterKeeper,
                            'items'                 => $getAllItems,
                            'export_and_filter'     => $getExportAndFilter,
                            'mode'                  => 'pdfl'
                        )
                    ),
                    'bi-filetype-pdf',
                    'menu_item_lists_export'
                );
            }
            else {
                // if filter is not enabled, reset filterstring
                $getFilterString = '';
                $getFilterCategory = '';
                $getFilterKeeper = 0;
            }
#endregion
#endregion

            if ($gCurrentUser->editUsers()) {
                if ($gSettingsManager->getBool('changelog_module_enabled')) {
                    // show link to view profile field change history
                    //ChangelogService::displayHistoryButton($page, 'inventory', 'inventory');
                    $page->addPageFunctionsMenuItem(
                        'menu_item_inventory_change_history',
                        $gL10n->get('SYS_CHANGE_HISTORY'),
                        ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory_history.php',
                        'bi-clock-history'
                    );
                }
                
                $page->addJavascript('
                    $("#menu_item_inventory_create_item").attr("href", "javascript:void(0);");
                    $("#menu_item_inventory_create_item").attr("data-href", "' . ADMIDIO_URL . FOLDER_MODULES . '/inventory/items_new.php");
                    $("#menu_item_inventory_create_item").attr("class", "nav-link btn btn-secondary openPopup");
                    ',
                    true
                );

                // show link to create new item
                $page->addPageFunctionsMenuItem(
                    'menu_item_inventory_create_item',
                    $gL10n->get('SYS_INVENTORY_CREATE_ITEM'),
                    ADMIDIO_URL . FOLDER_MODULES . '/inventory/items_new.php',
                    'bi-plus-circle-fill'
                );

                // show link to import users
                $page->addPageFunctionsMenuItem(
                    'menu_item_inventory_import_items',
                    $gL10n->get('SYS_INVENTORY_IMPORT_ITEMS'),
                    ADMIDIO_URL . FOLDER_MODULES . '/inventory/import.php',
                    'bi-upload'
                );
            }
            
            if ($gCurrentUser->isAdministrator()) {
                // show link to maintain fields
                $page->addPageFunctionsMenuItem(
                    'menu_item_inventory_item_fields',
                    $gL10n->get('SYS_INVENTORY_EDIT_ITEM_FIELDS'),
                    ADMIDIO_URL . FOLDER_MODULES . '/item-fields.php',
                    'bi-ui-radios'
                );
            }
#endregion

#region Filter form and edit mode
            if ($gCurrentUser->editInventory()) {
                $page->addJavascript('
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
                    $page,
                    array('type' => 'navbar', 'setFocus' => false)
                );
                
                if ($getExportAndFilter) {
                    $page->addJavascript('
                        $("#items_filter_category").change(function () {
                            self.location.href = "'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory.php', array(
                                    'mode'                  => 'html',
                                    'items_filter_string'   => $getFilterString,
                                    'items_filter_keeper'   => $getFilterKeeper,
                                    'items'                 => $getAllItems,
                                    'export_and_filter'     => $getExportAndFilter
                                )
                            ) . '&items_filter_category=" + $(this).val();
                        });

                        $("#items_filter_keeper").change(function () {
                            self.location.href = "'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/inventory.php', array(
                                    'mode'                  => 'html',
                                    'items_filter_string'   => $getFilterString,
                                    'items_filter_category' => $getFilterCategory,
                                    'items'                 => $getAllItems,
                                    'export_and_filter'     => $getExportAndFilter
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
                    $form->addInput('items_filter_string', $gL10n->get('SYS_FILTER'), $getFilterString);
                    
                    $items = new ItemsData($gDb, $gCurrentOrgId);
                    $items->showFormerItems($getAllItems);
                    foreach ($items->getItemFields() as $itemField) {  
                        $infNameIntern = $itemField->getValue('inf_name_intern');
                    
                        if ($items->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN') {
                            $arrListValues = $items->getProperty($infNameIntern, 'inf_value_list');
                            $defaultValue  = $items->getValue($infNameIntern, 'database');

                            // filter category
                            $form->addSelectBox(
                                'items_filter_category',
                                $gL10n->get('SYS_CATEGORY'),
                                $arrListValues,
                                array(
                                    'defaultValue'    => $getFilterCategory,
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
                        array('defaultValue' => $getFilterKeeper , 'showContextDependentFirstEntry' => true)
                    );
                }

                // fiter for export
                $form->addCheckbox(
                    'export_and_filter',
                    $gL10n->get('SYS_FILTER_TO_EXPORT'),
                    $getExportAndFilter
                );

                // filter all items
                $form->addCheckbox(
                    'items_show_all',
                    $gL10n->get('SYS_SHOW_ALL'),
                    $getAllItems,
                    array('helpTextId' => 'SYS_SHOW_ALL_DESC')
                );
    
                $form->addToHtmlPage();
            }
/*             else {
                $contactsListConfig->setModeShowOnlyNames();
            } */
            $inventoryTable = new HtmlTable('adm_inventory_table', $page, $hoverRows, $datatable, $classTable);
            $inventoryTable->setDatatablesRowsPerPage($gSettingsManager->getInt('inventory_items_per_page'));
            break;
    
        default:
            $inventoryTable = new HtmlTable('adm_inventory_table', $page, $hoverRows, $datatable, $classTable);
            break;
    }
    
#endregion

#region Display table
    // initialize array parameters for table and set the first column for the counter
    $columnAlign  = ($getMode == 'html') ? array('left') : array('center');
    $columnValues = array($gL10n->get('SYS_ABR_NO'));

    if (is_null($items)) {
        $items = new ItemsData($gDb, $gCurrentOrgId);
        $items->showFormerItems($getAllItems);
    }
    $items->readItems($gCurrentOrgId);

    // headlines for columns
    $columnNumber = 1;

    foreach ($items->getItemFields() as $itemField) {
        $infNameIntern = $itemField->getValue('inf_name_intern');
        $columnHeader = $items->getProperty($infNameIntern, 'inf_name');

        switch ($items->getProperty($infNameIntern, 'inf_type')) {
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

        if ($getMode == 'pdf' && $columnNumber === 1) {
            $arrValidColumns[] = $gL10n->get('SYS_ABR_NO');
        }

        if ($getMode == 'csv' || $getMode == "ods" || $getMode == 'xlsx' && $columnNumber === 1) {
            $header[$gL10n->get('SYS_ABR_NO')] = 'string';
        }

        switch ($getMode) {
            case 'csv':
            case "ods":
            case 'xlsx':
                $header[$columnHeader] = 'string';
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

    if ($getMode == 'html') {
        $columnAlign[]  = 'center';
        $columnValues[] = '&nbsp;';
        $inventoryTable->disableDatatablesColumnsSort(array(count($columnValues)));
    }

    if ($getMode == 'html' || $getMode == 'print') {
        $inventoryTable->setColumnAlignByArray($columnAlign);
        $inventoryTable->addRowHeadingByArray($columnValues);
    }
    elseif ($getMode == 'pdf') {
        $inventoryTable->setColumnAlignByArray($columnAlign);
        $inventoryTable->addTableHeader();
        $inventoryTable->addRow();
        foreach ($arrValidColumns as $column) {
            $inventoryTable->addColumn($column, array('style' => 'text-align:center;font-size:10;font-weight:bold;background-color:#C7C7C7;'), 'th');
        }
    }

    // create user object
    $user = new User($gDb, $gProfileFields);

    $listRowNumber = 1;

    foreach ($items->getItems() as $item) {
        $items->readItemData($item['ini_id'], $gCurrentOrgId);
        $columnValues = array();
        $strikethrough = $item['ini_former'];
        $columnNumber = 1;

        foreach ($items->getItemFields() as $itemField) {
            $infNameIntern = $itemField->getValue('inf_name_intern');

            if (($getFilterCategory !== '' && $infNameIntern == 'CATEGORY' && $getFilterCategory != $items->getValue($infNameIntern, 'database')) ||
                    ($getFilterKeeper !== 0 && $infNameIntern == 'KEEPER' && $getFilterKeeper != $items->getValue($infNameIntern))) {
                continue 2;
            }

            if ($columnNumber === 1) {
                $columnValues[] = $listRowNumber;
            }

            $content = $items->getValue($infNameIntern, 'database');

            if ($infNameIntern == 'KEEPER' && strlen($content) > 0) {
                $found = $user->readDataById($content);
                if (!$found) {
                    $orgName = '"' . $gCurrentOrganization->getValue('org_longname'). '"';
                    $content = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION',array($orgName));
                }
                else {
                    if ($getMode == 'html') {
                        $content = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                    }
                    else {
                        $sql = getSqlOrganizationsUsersCompletePIM();
                        
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
                        if ($getMode == 'html') {
                            $content = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                        }
                        else {
                            $sql = getSqlOrganizationsUsersCompletePIM();
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
            if ($items->getProperty($infNameIntern, 'inf_type') == 'CHECKBOX') {
                $content = ($content != 1) ? 0 : 1;
                $content = ($getMode == 'csv' || $getMode == 'pdf' || $getMode == 'xlsx'|| $getMode == 'ods') ?
                    ($content == 1 ? $gL10n->get('SYS_YES') : $gL10n->get('SYS_NO')) :
                    $items->getHtmlValue($infNameIntern, $content);
            }
            elseif ($items->getProperty($infNameIntern, 'inf_type') == 'DATE') {
                $content = $items->getHtmlValue($infNameIntern, $content);
            }
            elseif (in_array($items->getProperty($infNameIntern, 'inf_type'), array('DROPDOWN', 'RADIO_BUTTON'))) {
                $content = $items->getHtmlValue($infNameIntern, $content);
            }

            $columnValues[] = ($strikethrough && $getMode != 'csv' && $getMode != 'ods' && $getMode != 'xlsx') ? '<s>' . $content . '</s>' : $content;
            $columnNumber++;
        }

        if ($getMode == 'html') {
            $tempValue = '';

            // show link to view profile field change history
            if ($gSettingsManager->getBool('changelog_module_enabled')) {
                $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/items_history.php', array('item_id' => $item['ini_id'])) . '">
                                <i class="bi bi-clock-history" title="' . $gL10n->get('SYS_CHANGE_HISTORY') . '"></i>
                            </a>';
            }

            // show link to edit, make former or undo former and delete item (if authorized)
            if ($gCurrentUser->isAdministrator() || isKeeperAuthorizedToEdit((int)$items->getValue('KEEPER', 'database'))) {
                if ($gCurrentUser->isAdministrator() || (isKeeperAuthorizedToEdit((int)$items->getValue('KEEPER', 'database')) && !$item['ini_former'])) {
                    $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/items_edit_new.php', array('item_id' => $item['ini_id'], 'item_former' => $item['ini_former'])) . '">
                                    <i class="bi bi-pencil-square" title="' . $gL10n->get('SYS_INVENTORY_ITEM_EDIT') . '"></i>
                                </a>';
                }

                if ($item['ini_former']) {
                    $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/items_delete.php', array('item_id' => $item['ini_id'], 'item_former' => $item['ini_former'], 'mode' => 4)) . '">
                                    <i class="bi bi-eye" title="' . $gL10n->get('SYS_INVENTORY_UNDO_FORMER') . '"></i>
                                </a>';
                }

                if ($gCurrentUser->isAdministrator() || (isKeeperAuthorizedToEdit((int)$items->getValue('KEEPER', 'database')) && !$item['ini_former'])) {
                    $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory/items_delete.php', array('item_id' => $item['ini_id'], 'item_former' => $item['ini_former'])) . '">
                                    <i class="bi bi-trash" title="' . $gL10n->get('SYS_INVENTORY_ITEM_DELETE') . '"></i>
                                </a>';
                }
            }
            $columnValues[] = $tempValue;
        }

        $showRow = ($getFilterString == '') ? true : false;

        if ($getFilterString !== '') {
            $showRowException = false;
            $filterArray = explode(',', $getFilterString);
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
            switch ($getMode) {
                case 'csv':
                case 'ods':
                case 'xlsx':
                    $rows[] = $columnValues;
                    $strikethroughs[] = $strikethrough;
                    break;

                default:
                    $inventoryTable->addRowByArray($columnValues, '', array('nobr' => 'true'));
                    break;
                }
        }

        ++$listRowNumber;
    }

    if (in_array($getMode, array('csv', 'pdf', 'xlsx', 'ods'))) {
        $filename = FileSystemUtils::getSanitizedPathEntry($filename) . '.' . $getMode;
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
            $filename = urlencode($filename);
        }
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private');
        header('Pragma: public');
    }

    switch ($getMode) {
        case 'pdf':
            $pdf->writeHTML($inventoryTable->getHtmlTable(), true, false, true);
            $file = ADMIDIO_PATH . FOLDER_DATA . '/temp/' . $filename;
            $pdf->Output($file, 'F');
            header('Content-Type: application/pdf');
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
            $contentType = match ($getMode) {
                'csv' => 'text/csv; charset=' . $charset,
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
                default => throw new InvalidArgumentException('Invalid mode'),
            };

            $writerClass = match ($getMode) {
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
                ->setSubject($gL10n->get('SYS_INVENTORY_ITEMLIST'))
                ->setCompany($gCurrentOrganization->getValue('org_longname'))
                ->setKeywords($gL10n->get('SYS_INVENTORY') . ', ' . $gL10n->get('SYS_INVENTORY_ITEM'))
                ->setDescription($gL10n->get('SYS_INVENTORY_CREATED_WITH'));

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray(array_keys($header), NULL, 'A1');
            $sheet->fromArray($rows, NULL, 'A2');

            if (!$getMode == 'csv') {
                foreach ($strikethroughs as $index => $strikethrough) {
                    if ($strikethrough) {
                        $sheet->getStyle('A' . ($index + 2) . ':' . $sheet->getHighestColumn() . ($index + 2))
                            ->getFont()->setStrikethrough(true);
                    }
                }

                formatSpreadsheet($spreadsheet, $rows, true);
            }

            $writer = new $writerClass($spreadsheet);
            $writer->save('php://output');
            break;

        case 'print':
        case 'html':
            $page->addHtml($inventoryTable->show());
            $page->show();
            break;
    }
 #endregion
}
catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
