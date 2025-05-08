<?php

namespace Admidio\UI\Presenter;

// Admidio namespaces
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\UI\Component\DataTables;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

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
    /**
     * @var ItemsData Object of the class ItemsData that contains all data of the inventory items.
     */
    protected ItemsData $itemsData;
    /**
     * @var string filter string for the search field
     */
    protected string $getFilterString = '';
    /**
     * @var string filter string for the category selection
     */
    protected string $getFilterCategory = '';
    /**
     * @var int filter id for the keeper selection
     */
    protected int $getFilterKeeper = 0;
    /**
     * @var bool filter for all items
     */
    protected bool $getAllItems =  false;

    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $objectUUID UUID of an object that represents the page. The data shown at the page will belong
     *                           to this object.
     * @throws Exception
     */
    public function __construct(string $objectUUID = 'admidio-inventory')
    {
        global $gDb, $gCurrentOrgId;

        // initialize the parameters
        $this->getFilterString = admFuncVariableIsValid($_GET, 'items_filter_string', 'string', array('defaultValue' => ''));
        $this->getFilterCategory = admFuncVariableIsValid($_GET, 'items_filter_category', 'string', array('defaultValue' => ''));
        $this->getFilterKeeper = admFuncVariableIsValid($_GET, 'items_filter_keeper', 'int', array('defaultValue' => 0));
        $this->getAllItems = admFuncVariableIsValid($_GET, 'items_show_all', 'bool', array('defaultValue' => false));

        $this->itemsData = new ItemsData($gDb, $gCurrentOrgId);
        $this->itemsData->showFormerItems($this->getAllItems);
        $this->itemsData->readItems();

        parent::__construct($objectUUID);
    }

    /**
     * Create a functions menu and a filter navbar.
     * @return void
     * @throws Exception
     */
    protected function createHeader(): void
    {
        global $gCurrentUser, $gL10n, $gDb, $gCurrentOrgId, $gProfileFields;

        // link to print overlay and exports
        $this->addPageFunctionsMenuItem(
            'menu_item_lists_print_view',
            $gL10n->get('SYS_PRINT_PREVIEW'),
            'javascript:void(0);',
            'bi-printer-fill'
        );

        // dropdown menu for export options
        $this->createExportDropdown(false);

        $showFilterForm = FormPresenter::FIELD_DISABLED;
        if ($gCurrentUser->isAdministratorInventory()) {
            $showFilterForm = FormPresenter::FIELD_DEFAULT;

            // show link to view inventory history
            ChangelogService::displayHistoryButton($this, 'inventory', 'inventory_fields,inventory_items,inventory_data');
           
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
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php' , array('mode' => 'import_file_selection')),
                'bi-upload'
            );

            // show link to maintain fields
            $this->addPageFunctionsMenuItem(
                'menu_item_inventory_item_fields',
                $gL10n->get('SYS_INVENTORY_ITEMFIELDS_EDIT'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php' , array('mode' => 'field_list')),
                'bi-ui-radios'
            );
        }

            $form = new FormPresenter(
                'adm_navbar_filter_form',
                'sys-template-parts/form.filter.tpl',
                '',
                $this,
                array('type' => 'navbar', 'setFocus' => false)
            );
            
            $initialFilter = addslashes($this->getFilterString);
            $printBaseUrl  = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'print_preview'));
            
            $this->addJavascript('
                $(document).ready(function(){
                    // only submit non-empty filter values
                    $("#items_filter_category, #items_filter_keeper, #items_show_all").on("change", function(){
                        var form = $("#adm_navbar_filter_form");

                        // Text-Filter
                        var textFilterInput = $("#items_filter_string");
                        if (textFilterInput.val() === "") {
                        textFilterInput.removeAttr("name");
                        } else {
                        textFilterInput.attr("name", "items_filter_string");
                        }

                        // Category
                        var categorySelect = $("#items_filter_category");
                        if (categorySelect.val() === "") {
                        categorySelect.removeAttr("name");
                        } else {
                        categorySelect.attr("name", "items_filter_category");
                        }

                        // Keeper
                        var keeperSelect = $("#items_filter_keeper");
                        if (keeperSelect.val() === "") {
                        keeperSelect.removeAttr("name");
                        } else {
                        keeperSelect.attr("name", "items_filter_keeper");
                        }

                        // Show All
                        var showAllCheckbox = $("#items_show_all");
                        if (!showAllCheckbox.is(":checked")) {
                        showAllCheckbox.removeAttr("name");
                        } else {
                        showAllCheckbox.attr("name", "items_show_all");
                        }

                        form.submit();
                    });

                    // fill the DataTable filter string with the current search value
                    var table = $("#adm_inventory_table").DataTable();            
                    var initFilter = "' . $initialFilter . '";
                    if (initFilter !== "") {
                        table.search(initFilter).draw();
                    }
                
                    // set the filter string in the form when the DataTable is searched
                    table.on("search.dt", function(){
                    var textFilter = table.search() || "";
                    $("#adm_navbar_filter_form")
                        .find("input[name=\'items_filter_string\']")
                        .val(textFilter);
                    });
                
                    // create the print view link with the current filter values
                    $("#menu_item_lists_print_view").off("click").on("click", function(e){
                        e.preventDefault();
                        var textFilter     = $("#items_filter_string").val() || "";
                        var category     = $("#items_filter_category").val()   || "";
                        var keeper  = $("#items_filter_keeper").val()     || "";
                        var showAll = $("#items_show_all").is(":checked") ? 1 : 0;
                        var url = "' . $printBaseUrl . '"
                                + "&items_filter_string="   + encodeURIComponent(textFilter)
                                + "&items_filter_category=" + encodeURIComponent(category)
                                + "&items_filter_keeper="   + encodeURIComponent(keeper)
                                + "&items_show_all="        + showAll;
                    
                        window.open(url, "_blank");
                    });
                });',
                true
            );
            
            // filter string
            $form->addInput('items_filter_string', $gL10n->get('SYS_FILTER'), "", array('property' => FormPresenter::FIELD_HIDDEN));
            
            foreach ($this->itemsData->getItemFields() as $itemField) {  
                $infNameIntern = $itemField->getValue('inf_name_intern');
            
                if ($this->itemsData->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN') {
                    $arrListValues = $this->itemsData->getProperty($infNameIntern, 'inf_value_list');

                    // filter category
                    $form->addSelectBox(
                        'items_filter_category',
                        $gL10n->get('SYS_CATEGORY'),
                        $arrListValues,
                        array(
                            'property' => $showFilterForm,
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
                array(
                    'property' => $showFilterForm,
                    'defaultValue' => $this->getFilterKeeper,
                    'showContextDependentFirstEntry' => true
                )
            );

            // filter all items
            $form->addCheckbox(
                'items_show_all',
                $gL10n->get('SYS_SHOW_ALL'),
                $this->getAllItems,
                array(
                    'property' => $showFilterForm,
                    'helpTextId' => 'SYS_SHOW_ALL_DESC'
                )
            );

            $form->addToHtmlPage();
    }

    /**
     * Create the export dropdown menu for the inventory items.
     * This method adds various export options to the page functions menu.
     *
     * @return void
     * @throws Exception
     */
    protected function createExportDropdown() : void
    {
        global $gL10n;
        // create the export dropdown menu
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
                    'mode'                  => 'print_pdfl'
                )
            ),
            'bi-filetype-pdf',
            'menu_item_lists_export'
        );

        // add javascript for the export dropdown menu to change the URL of the export link
        $this->addJavascript('
            $(document).ready(function(){
                var buttons = {
                    xlsx:    "print_xlsx",
                    ods:     "print_ods",
                    csv_ms:  "print_csv-ms",
                    csv:     "print_csv-oo",
                    pdf:     "print_pdf",
                    pdfl:    "print_pdfl"
                };

                $.each(buttons, function(suffix, modeValue){
                    var selector = "#menu_item_lists_" + suffix;
                    $(selector).on("click", function(e){
                        var textFilter = $("#items_filter_string").val()            || "";
                        var category   = $("#items_filter_category").val()         || "";
                        var keeper     = $("#items_filter_keeper").val()           || "";
                        var showAll    = $("#items_show_all").is(":checked") ? 1 : 0;
                        var base = this.href.split("?")[0];
                        var qs = [
                        "items_filter_string="   + encodeURIComponent(textFilter),
                        "items_filter_category=" + encodeURIComponent(category),
                        "items_filter_keeper="   + encodeURIComponent(keeper),
                        "items="                 + showAll,
                        "mode="                  + modeValue
                        ].join("&");
                        this.href = base + "?" + qs;
                    });
                });
            });',
            true
        );
    }

    /**
     * Create the list of all items in the inventory. This method is used to display the items in a table format.
     * It prepares the data for the table and handles the print view if required.
     *
     * @return void
     * @throws Exception
     */
    public function createList() : void
    {
        global $gSettingsManager;

        if (!$this->printView) {
            $this->createHeader();
            $templateData = $this->prepareData('html');

            // initialize and set the parameter for DataTables
            $dataTables = new DataTables($this, 'adm_inventory_table');
            $dataTables->disableColumnsSort(array(count($templateData['headers'])));
            $dataTables->setColumnsNotHideResponsive(array(count($templateData['headers'])));
            $dataTables->createJavascript(count($templateData['rows']), count($templateData['headers']));
            $dataTables->setColumnAlignByArray($templateData['column_align']);
            $dataTables->setRowsPerPage($gSettingsManager->getInt('inventory_items_per_page'));
        }
        else {
            $templateData = $this->prepareData('print');
        }
        
        $this->smarty->assign('list', $templateData);
        $this->smarty->assign('print', $this->printView);
        $this->pageContent .= $this->smarty->fetch('modules/inventory.list.tpl');
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

        if ($gSettingsManager->getBool('inventory_allow_keeper_edit')) {
            if (isset($keeper) && $keeper === $gCurrentUser->getValue('usr_id')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Populate the inventory table with the data of the inventory items.
     * This method supports various output formats and fills the table based on the
     * provided display mode. The mode parameter allows selecting different table
     * representations such as HTML, PDF, CSV, ODS, or XLSX.
     *
     * @param string $mode Mode of the table (html, pdf, csv, ods, xlsx)
     * @return array Returns an array with the following keys:
     *               - column_align: array indicating the alignment for each column
     *               - headers: array of header labels for the table
     *               - export_headers: array of headers used for export formats
     *               - rows: array containing all the table rows data
     *               - strikethroughs: array indicating which rows should have strikethrough formatting
     * @throws Exception
     */
    public function prepareData(string $mode = 'html') : array
    {
        global $gCurrentUser, $gL10n, $gDb, $gCurrentOrganization, $gProfileFields, $gCurrentSession;

        // Initialize the result array
        $preparedData = array(
            'column_align'    => array(),
            'headers'         => array(),
            'export_headers'  => array(),
            'rows'            => array(),
            'strikethroughs'  => array()
        );

        // Set default alignment and headers for the first column (abbreviation)
        $columnAlign = ($mode === 'html') ? ['left'] : ['center'];
        $headers     = array();
        $exportHeaders = array();
        $columnNumber = 1;

        // Build headers and column alignment for each item field
        foreach ($this->itemsData->getItemFields() as $itemField) {
            $infNameIntern = $itemField->getValue('inf_name_intern');
            $columnHeader  = $this->itemsData->getProperty($infNameIntern, 'inf_name');

            // Decide alignment based on inf_type
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

            // For the first column, add specific header configurations for export modes
            if ($columnNumber === 1) {
                if (in_array($mode, ['csv', 'ods', 'xlsx'])) {
                    $exportHeaders[$gL10n->get('SYS_ABR_NO')] = 'string';
                }
                else {
                    $headers[] = $gL10n->get('SYS_ABR_NO');
                }
            }

            // Add header depending on mode
            if (in_array($mode, ['csv', 'ods', 'xlsx'])) {
                $exportHeaders[$columnHeader] = 'string';
            } else {
                $headers[] = $columnHeader;
            }

            $columnNumber++;
        }

        if ($mode === 'html') {
            $columnAlign[] = 'right';
            $headers[]     = '&nbsp;';
        }

        $preparedData['headers']         = $headers;
        $preparedData['export_headers']  = $exportHeaders;
        $preparedData['column_align']    = $columnAlign;

        // Create a user object for later use
        $user = new User($gDb, $gProfileFields);

        $rows = array();
        $strikethroughs = array();
        $listRowNumber = 1;

        // Iterate over each item to fill the table rows
        foreach ($this->itemsData->getItems() as $item) {
            $this->itemsData->readItemData($item['ini_id']);
            $rowValues = array();
            $strikethrough = $item['ini_former'];
            $columnNumber = 1;

            foreach ($this->itemsData->getItemFields() as $itemField) {
                $infNameIntern = $itemField->getValue('inf_name_intern');

                // Apply filters for CATEGORY and KEEPER
                if (
                    ($this->getFilterCategory !== '' && $infNameIntern === 'CATEGORY' && $this->getFilterCategory != $this->itemsData->getValue($infNameIntern, 'database')) ||
                    ($this->getFilterKeeper !== 0 && $infNameIntern === 'KEEPER' && $this->getFilterKeeper != $this->itemsData->getValue($infNameIntern))
                ) {
                    continue 2;
                }

                if ($columnNumber === 1) {
                    $rowValues[] = $listRowNumber;
                }

                $content = $this->itemsData->getValue($infNameIntern, 'database');
                $infType = $this->itemsData->getProperty($infNameIntern, 'inf_type');

                // Process KEEPER column
                if ($infNameIntern === 'KEEPER' && strlen($content) > 0) {
                    $found = $user->readDataById($content);
                    if (!$found) {
                        $orgName = '"' . $gCurrentOrganization->getValue('org_longname') . '"';
                        $content = $mode === 'html'
                            ? '<i>' . SecurityUtils::encodeHTML(StringUtils::strStripTags($gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', [$orgName]))) . '</i>'
                            : '<i>' . $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', [$orgName]) . '</i>';
                    } else {
                        if ($mode === 'html') {
                            $content = '<a href="' . SecurityUtils::encodeUrl(
                                ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php',
                                ['user_uuid' => $user->getValue('usr_uuid')]
                            ) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                        } else {
                            $sql = $this->itemsData->getSqlOrganizationsUsersComplete();
                            $result = $gDb->queryPrepared($sql);
                            $content = $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME');
                            while ($row = $result->fetch()) {
                                if ($row['usr_id'] == $user->getValue('usr_id')) {
                                    $content = $row['name'];
                                    break;
                                }
                            }
                        }
                    }
                }

                // Process LAST_RECEIVER column
                if ($infNameIntern === 'LAST_RECEIVER' && strlen($content) > 0 && is_numeric($content)) {
                    $found = $user->readDataById($content);
                    if ($found) {
                        if ($mode === 'html') {
                            $content = '<a href="' . SecurityUtils::encodeUrl(
                                ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php',
                                ['user_uuid' => $user->getValue('usr_uuid')]
                            ) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                        } else {
                            $sql = $this->itemsData->getSqlOrganizationsUsersComplete();
                            $result = $gDb->queryPrepared($sql);
                            $content = $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME');
                            while ($row = $result->fetch()) {
                                if ($row['usr_id'] == $user->getValue('usr_id')) {
                                    $content = $row['name'];
                                    break;
                                }
                            }
                        }
                    }
                }

                // Format content based on the field type
                if ($infType === 'CHECKBOX') {
                    $content = ($content != 1) ? 0 : 1;
                    $content = in_array($mode, ['csv', 'pdf', 'xlsx', 'ods'])
                        ? ($content == 1 ? $gL10n->get('SYS_YES') : $gL10n->get('SYS_NO'))
                        : $this->itemsData->getHtmlValue($infNameIntern, $content);
                } elseif (in_array($infType, ['DATE', 'DROPDOWN'])) {
                    $content = $this->itemsData->getHtmlValue($infNameIntern, $content);
                } elseif ($infType === 'RADIO_BUTTON') {
                    $content = $mode === 'html'
                        ? $this->itemsData->getHtmlValue($infNameIntern, $content)
                        : $this->itemsData->getValue($infNameIntern, 'database');
                }

                $rowValues[] = ($strikethrough && !in_array($mode, ['csv', 'ods', 'xlsx']))
                    ? '<s>' . $content . '</s>'
                    : $content;
                $columnNumber++;
            }

            // Append admin action column for HTML mode
            if ($mode === 'html') {
                $tempValue = '';
                $tempValue .= ChangelogService::displayHistoryButtonTable(
                    'inventory_items,inventory_data',
                    $gCurrentUser->isAdministratorInventory(),
                    ['related_id' => $item['ini_id']]
                );

                if ($gCurrentUser->isAdministratorInventory() || $this->isKeeperAuthorizedToEdit((int)$this->itemsData->getValue('KEEPER', 'database'))) {
                    if ($gCurrentUser->isAdministratorInventory() || ($this->isKeeperAuthorizedToEdit((int)$this->itemsData->getValue('KEEPER', 'database')) && !$item['ini_former'])) {
                        $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(
                            ADMIDIO_URL . FOLDER_MODULES . '/inventory.php',
                            ['mode' => 'item_edit', 'item_id' => $item['ini_id'], 'item_former' => $item['ini_former']]
                        ) . '">
                            <i class="bi bi-pencil-square" title="' . $gL10n->get('SYS_INVENTORY_ITEM_EDIT') . '"></i>
                        </a>';
                        $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(
                            ADMIDIO_URL . FOLDER_MODULES . '/inventory.php',
                            ['mode' => 'item_edit', 'item_id' => $item['ini_id'], 'copy' => true]
                        ) . '">
                            <i class="bi bi-copy" title="' . $gL10n->get('SYS_COPY') . '"></i>
                        </a>';
                    }

                    if ($item['ini_former']) {
                        $tempValue .= '<a class="admidio-icon-link admidio-messagebox"
                            href="javascript:void(0);"
                            data-buttons="yes-no"
                            data-message="' . $gL10n->get('SYS_INVENTORY_UNDO_FORMER_CONFIRM') . '"
                            data-href="callUrlHideElement(\'no_element\', \'' . SecurityUtils::encodeUrl(
                                ADMIDIO_URL . FOLDER_MODULES . '/inventory.php',
                                ['mode' => 'item_undo_former', 'item_id' => $item['ini_id'], 'item_former' => $item['ini_former']]
                            ) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                            <i class="bi bi-eye" title="' . $gL10n->get('SYS_INVENTORY_UNDO_FORMER') . '"></i>
                        </a>';
                    }

                    if ($gCurrentUser->isAdministratorInventory() || ($this->isKeeperAuthorizedToEdit((int)$this->itemsData->getValue('KEEPER', 'database')) && !$item['ini_former'])) {
                        $tempValue .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                            data-href="' . SecurityUtils::encodeUrl(
                                ADMIDIO_URL . FOLDER_MODULES . '/inventory.php',
                                ['mode' => 'item_delete_explain_msg', 'item_id' => $item['ini_id'], 'item_former' => $item['ini_former']]
                            ) . '">
                            <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DELETE') . '"></i>
                        </a>';
                    }
                }
                $rowValues[] = $tempValue;
            }

            // Filter rows based on filter string
            $showRow = true;

            if ($mode !== 'html') {
                $showRow = ($this->getFilterString === '');
                if ($this->getFilterString !== '') {
                    $showRowException = false;
                    $filterArray = array_map('trim', explode(',', $this->getFilterString));
                    $filterColumnValues = array_map(function ($value) {
                        $parts = explode(',', $value);
                        return count($parts) > 1 ? implode(',', array_slice($parts, 0, 2)) : $value;
                    }, $rowValues);

                    foreach ($filterArray as $filterString) {
                        if (strpos($filterString, '-') === 0) {
                            $cleanFilter = substr($filterString, 1);
                            if (stripos(implode('', $filterColumnValues), $cleanFilter) !== false) {
                                $showRowException = true;
                            }
                        }
                        if (stripos(implode(' ', $filterColumnValues), $filterString) !== false) {
                            $showRow = true;
                        }
                    }
                    if ($showRowException) {
                        $showRow = false;
                    }
                }
            }

            if ($showRow) {
                $rows[] = $rowValues;
                if (in_array($mode, ['csv', 'xlsx', 'ods'])) {
                    $strikethroughs[] = $strikethrough;
                }
            }

            $listRowNumber++;
        }

        $preparedData['rows'] = $rows;
        $preparedData['strikethroughs'] = $strikethroughs;

        return $preparedData;
    }

    /**
     * Populate the inventory table with the data of the inventory items in HTML mode.
     * This method uses a predefined ItemsData element and always returns the HTML version
     * of the table.
     *
     * @return array Returns an array with the following keys:
     *               - headers: array of header labels for the table
     *               - column_align: array indicating the alignment for each column
     *               - rows: array containing all the table rows data
     *               - strikethroughs: array indicating which rows should have strikethrough formatting
     * @throws Exception
     */
    public function prepareDataProfile(ItemsData $itemsData, string $itemFieldFilter = 'KEEPER') : array
    {
        global $gCurrentUser, $gL10n, $gDb, $gCurrentOrganization, $gProfileFields, $gCurrentSession, $gSettingsManager;

        // Create a user object for later use
        $user = new User($gDb, $gProfileFields);

        // Initialize the result array
        $preparedData = array(
            'headers'         => array(),
            'column_align'    => array(),
            'rows'            => array(),
            'strikethroughs'  => array()
        );

        // Build headers and set column alignment (only for HTML mode)
        $columnAlign = array('left'); // first column alignment
        $headers     = array();
        $columnNumber = 1;

        // create array with all column heading values
        $profileItemFields = array('ITEMNAME');

        foreach (explode(',', $gSettingsManager->getString('inventory_profile_view')) as $itemField) {
            // we are in the keeper view, so we dont need the keeper field in the table
            if ($itemField !== $itemFieldFilter && $itemField !== "0") {
                $profileItemFields[] = $itemField;
            }
        }

        foreach ($itemsData->getItemFields() as $itemField) {
            $infNameIntern = $itemField->getValue('inf_name_intern');

            if (!in_array($infNameIntern, $profileItemFields, true)) {
                continue;
            }

            $columnHeader  = $itemsData->getProperty($infNameIntern, 'inf_name');

            // Decide alignment based on field type
            switch ($itemsData->getProperty($infNameIntern, 'inf_type')) {
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

            // For the first column, add a specific header
            if ($columnNumber === 1) {
                $headers[] = $gL10n->get('SYS_ABR_NO');
            }

            $headers[] = $columnHeader;
            $columnNumber++;
        }

        // Append the admin action column
        $columnAlign[] = 'right';
        $headers[]     = '&nbsp;';

        $preparedData['headers']      = $headers;
        $preparedData['column_align'] = $columnAlign;

        // Build table rows from the predefined ItemsData element (HTML mode only)
        $rows          = array();
        $strikethroughs = array();
        $listRowNumber = 1;

        foreach ($itemsData->getItems() as $item) {
            $itemsData->readItemData($item['ini_id']);
            $rowValues     = array();
            $strikethrough = $item['ini_former'];
            $columnNumber  = 1;

            foreach ($itemsData->getItemFields() as $itemField) {
                $infNameIntern = $itemField->getValue('inf_name_intern');

                if (!in_array($infNameIntern, $profileItemFields, true)) {
                    continue;
                }
                
                // For the first column, add a row number
                if ($columnNumber === 1) {
                    $rowValues[] = $listRowNumber;
                }

                $content = $itemsData->getValue($infNameIntern, 'database');
                $infType = $itemsData->getProperty($infNameIntern, 'inf_type');

                // Process the KEEPER column
                if ($infNameIntern === 'KEEPER' && strlen($content) > 0) {
                    $found = $user->readDataById($content);
                    if (!$found) {
                        $orgName = '"' . $gCurrentOrganization->getValue('org_longname') . '"';
                        $content = '<i>' . SecurityUtils::encodeHTML(StringUtils::strStripTags($gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', [$orgName]))) . '</i>';
                    } else {
                        $content = '<a href="' . SecurityUtils::encodeUrl(
                            ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php',
                            ['user_uuid' => $user->getValue('usr_uuid')]
                        ) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                    }
                }

                // Process the LAST_RECEIVER column
                if ($infNameIntern === 'LAST_RECEIVER' && strlen($content) > 0 && is_numeric($content)) {
                    $found = $user->readDataById($content);
                    if ($found) {
                        $content = '<a href="' . SecurityUtils::encodeUrl(
                            ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php',
                            ['user_uuid' => $user->getValue('usr_uuid')]
                        ) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                    }
                }

                // Format the content based on the field type
                if ($infType === 'CHECKBOX') {
                    $content = ($content != 1) ? 0 : 1;
                    $content = $itemsData->getHtmlValue($infNameIntern, $content);
                } elseif (in_array($infType, ['DATE', 'DROPDOWN'])) {
                    $content = $itemsData->getHtmlValue($infNameIntern, $content);
                } elseif ($infType === 'RADIO_BUTTON') {
                    $content = $itemsData->getHtmlValue($infNameIntern, $content);
                }

                $rowValues[] = ($strikethrough) ? '<s>' . $content . '</s>' : $content;
                $columnNumber++;
            }

            // Append admin action column
            $tempValue = '';
            $tempValue .= ChangelogService::displayHistoryButtonTable(
                'inventory_items,inventory_data',
                $gCurrentUser->isAdministratorInventory(),
                ['related_id' => $item['ini_id']]
            );

            if ($gCurrentUser->isAdministratorInventory() || $this->isKeeperAuthorizedToEdit((int)$itemsData->getValue('KEEPER', 'database'))) {
                if ($gCurrentUser->isAdministratorInventory() || (!$item['ini_former'] && $this->isKeeperAuthorizedToEdit((int)$itemsData->getValue('KEEPER', 'database')))) {
                    $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(
                        ADMIDIO_URL . FOLDER_MODULES . '/inventory.php',
                        ['mode' => 'item_edit', 'item_id' => $item['ini_id'], 'item_former' => $item['ini_former']]
                    ) . '">
                        <i class="bi bi-pencil-square" title="' . $gL10n->get('SYS_INVENTORY_ITEM_EDIT') . '"></i>
                    </a>';
                    $tempValue .= '<a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(
                        ADMIDIO_URL . FOLDER_MODULES . '/inventory.php',
                        ['mode' => 'item_edit', 'item_id' => $item['ini_id'], 'copy' => true]
                    ) . '">
                        <i class="bi bi-copy" title="' . $gL10n->get('SYS_COPY') . '"></i>
                    </a>';
                }

                if ($item['ini_former']) {
                    $tempValue .= '<a class="admidio-icon-link admidio-messagebox"
                        href="javascript:void(0);"
                        data-buttons="yes-no"
                        data-message="' . $gL10n->get('SYS_INVENTORY_UNDO_FORMER_CONFIRM') . '"
                        data-href="callUrlHideElement(\'no_element\', \'' . SecurityUtils::encodeUrl(
                            ADMIDIO_URL . FOLDER_MODULES . '/inventory.php',
                            ['mode' => 'item_undo_former', 'item_id' => $item['ini_id'], 'item_former' => $item['ini_former']]
                        ) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                        <i class="bi bi-eye" title="' . $gL10n->get('SYS_INVENTORY_UNDO_FORMER') . '"></i>
                    </a>';
                }

                if ($gCurrentUser->isAdministratorInventory() || (!$item['ini_former'] && $this->isKeeperAuthorizedToEdit((int)$itemsData->getValue('KEEPER', 'database')))) {
                    $tempValue .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                        data-href="' . SecurityUtils::encodeUrl(
                            ADMIDIO_URL . FOLDER_MODULES . '/inventory.php',
                            ['mode' => 'item_delete_explain_msg', 'item_id' => $item['ini_id'], 'item_former' => $item['ini_former']]
                        ) . '">
                        <i class="bi bi-trash" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_DELETE') . '"></i>
                    </a>';
                }
            }

            $rowValues[] = $tempValue;
            $rows[] = $rowValues;
            $strikethroughs[] = $strikethrough;
            $listRowNumber++;
        }

        $preparedData['rows'] = $rows;
        $preparedData['strikethroughs'] = $strikethroughs;

        return $preparedData;
    }
}