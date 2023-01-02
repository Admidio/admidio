<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Creates an Admidio specific table with special methods
 *
 * This class inherits the common HtmlTableBasic class and extends their elements
 * with custom Admidio table methods. The class should be used to create the
 * html part of all Admidio tables. It has simple methods to add complete rows with
 * their column values to the table. It's also possible to add the jQuery plugin Datatables
 * to each table. Therefore you only need to set a flag when creating the object.
 *
 * **Code example**
 * ```
 * // create a simple table with one input field and a button
 * $table = new HtmlTable('simple-table');
 * $table->addRowHeadingByArray(array('Firstname', 'Lastname', 'Address', 'Phone', 'E-Mail'));
 * $table->addRowByArray(array('Hans', 'Mustermann', 'Sonnenallee 22', '+49 342 59433', 'h.mustermann@example.org'));
 * $table->addRowByArray(array('Anne', 'Musterfrau', 'Seestraße 6', '+34 7433 7433', 'a.musterfrau@example.org'));
 * $table->show();
 * ```
 *
 * **Code example**
 * ```
 * // create a table with jQuery datatables and align columns to center or right
 * $table = new HtmlTable('simple-table', null, true, true);
 * $table->setColumnAlignByArray(array('left', 'left', 'center', 'right'));
 * $table->addRowHeadingByArray(array('Firstname', 'Lastname', 'Birthday', 'Membership fee'));
 * $table->addRowByArray(array('Hans', 'Mustermann', 'Sonnenallee 22', '14.07.1995', '38,50'));
 * $table->show();
 * ```
 */
class HtmlTable extends HtmlTableBasic
{
    /**
     * @var string Html id attribute of the table.
     */
    protected $id;
    /**
     * @var int Number of rows that should be displayed on one page.
     */
    protected $rowsPerPage = 25;
    /**
     * @var array<int,string> Array with entry for each column with the align of that column of datatables are not used.
     * Values are **right**, **left** or **center**.
     */
    protected $columnsAlign = array();
    /**
     * @var array<int,string> Array with the column number as key and the 'asc' or 'desc' as value.
     */
    protected $columnsOrder = array();
    /**
     * @var int The number of the column which should be used to group the table data.
     */
    protected $groupedColumn = -1;
    /**
     * @var bool A flag if the jQuery plugin DataTables should be used to show the table.
     */
    protected $datatables;
    /**
     * @var array<int,string> An array that stores all necessary DataTables parameters that should be set on initialization of this plugin.
     */
    protected $datatablesInitParameters = array();
    /**
     * @var array<int,string> Array that contains several elements for DataTables columnDefs parameter.
     */
    protected $datatablesColumnDefs = array();
    /**
     * @var string The text that should be shown if no row was added to the table
     */
    protected $messageNoRowsFound;
    /**
     * @var HtmlPage A HtmlPage object that will be used to add javascript code or files to the html output page.
     */
    protected $htmlPage;
    /**
     * @var bool A flag that set the server-side processing for datatables.
     */
    protected $serverSideProcessing = false;
    /**
     * @var string The script that should be called when using server-side processing.
     */
    protected $serverSideFile = '';

    /**
     * Constructor creates the table element
     * @param string   $id         Id of the table
     * @param HtmlPage $htmlPage   (optional) A HtmlPage object that will be used to add javascript code
     *                             or files to the html output page.
     * @param bool     $hoverRows  (optional) If set to **true** then the active selected row will be marked with special css code
     * @param bool     $datatables (optional) If set to **true** then the jQuery plugin Datatables will be used to create the table.
     *                             Then column sort, search within the table and other features are possible.
     * @param string   $class      (optional) An additional css classname. The class **table**
     *                             is set as default and need not set with this parameter.
     */
    public function __construct($id, HtmlPage $htmlPage = null, $hoverRows = true, $datatables = false, $class = null)
    {
        global $gL10n;

        if ($class === null) {
            $class = 'table';
        }

        if ($hoverRows) {
            $class .= ' table-hover';
        }

        parent::__construct($id, $class);

        // initialize class member parameters
        $this->id = $id;
        $this->datatables = $datatables;
        $this->messageNoRowsFound = $gL10n->get('SYS_NO_DATA_FOUND');

        // when using DataTables we must set the width attribute so that all columns will change
        // dynamic their width if the browser window size change.
        if ($datatables) {
            $this->addAttribute('width', '100%');

            $this->datatablesInitParameters[] = '"language": {"url": "' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/datatables/language/datatables.' . $gL10n->getLanguageIsoCode() . '.json"}';
        }

        if ($htmlPage instanceof HtmlPage) {
            $this->htmlPage =& $htmlPage;
        }
    }

    /**
     * Adds a complete row with all columns to the table. Each column element will be a value of the array parameter.
     * @param string               $type            'th' for header row or 'td' for body row
     * @param array<int,mixed>     $arrColumnValues Array with the values for each column. If you use datatables
     *                                              than you could set an array for each value with the following entries:
     *                                              array('value' => $yourValue, 'order' => $sortingValue, 'search' => $searchingValue)
     *                                              With this you can specify special values for sorting and searching.
     * @param string               $id              (optional) Set an unique id for the column.
     * @param array<string,string> $arrAttributes   (optional) Further attributes as array with key/value pairs
     * @param int                  $colspan         (optional) Number of columns that should be join together.
     * @param int                  $colspanOffset   (optional) Number of column where the colspan should start.
     *                                              The first column of a table will be 1.
     */
    private function addRowTypeByArray($type, array $arrColumnValues, $id = null, array $arrAttributes = null, $colspan = 1, $colspanOffset = 1)
    {
        // set an id to the column
        if ($id !== null) {
            $arrAttributes['id'] = $id;
        }

        $this->addRow('', $arrAttributes, $type);

        $this->columnCount = count($arrColumnValues);

        // now add each column to the row
        foreach ($arrColumnValues as $key => $value) {
            $this->prepareAndAddColumn($type, $key, $value, $colspan, $colspanOffset);
        }
    }

    /**
     * Adds a complete row with all columns to the table. This will be the column footer row.
     * Each value of the array represents the heading text for each column.
     * @param array<int,string>    $arrColumnValues Array with the values for each column.
     * @param string               $id              (optional) Set an unique id for the column.
     * @param array<string,string> $arrAttributes   (optional) Further attributes as array with key/value pairs
     * @param int                  $colspan         (optional) Number of columns that should be join together.
     * @param int                  $colspanOffset   (optional) Number of the column where the colspan should start. The first column of a table will be 1.
     */
    public function addRowFooterByArray(array $arrColumnValues, $id = null, array $arrAttributes = null, $colspan = 1, $colspanOffset = 1)
    {
        $this->addTableFooter();
        $this->addRowTypeByArray('td', $arrColumnValues, $id, $arrAttributes, $colspan, $colspanOffset);
    }

    /**
     * Adds a complete row with all columns to the table. This will be the column heading row.
     * Each value of the array represents the heading text for each column.
     * @param array<int,string>    $arrColumnValues Array with the values for each column.
     * @param string               $id              (optional) Set an unique id for the column.
     * @param array<string,string> $arrAttributes   (optional) Further attributes as array with key/value pairs
     * @param int                  $colspan         (optional) Number of columns that should be join together.
     * @param int                  $colspanOffset   (optional) Number of the column where the colspan should start. The first column of a table will be 1.
     */
    public function addRowHeadingByArray(array $arrColumnValues, $id = null, array $arrAttributes = null, $colspan = 1, $colspanOffset = 1)
    {
        $this->addTableHeader();
        $this->addRowTypeByArray('th', $arrColumnValues, $id, $arrAttributes, $colspan, $colspanOffset);
    }

    /**
     * Adds a complete row with all columns to the table. Each column element will be a value of the array parameter.
     * @param array<int,mixed>     $arrColumnValues Array with the values for each column. If you use datatables
     *                                              than you could set an array for each value with the following entries:
     *                                              array('value' => $yourValue, 'order' => $sortingValue, 'search' => $searchingValue)
     *                                              With this you can specify special values for sorting and searching.
     * @param string               $id              (optional) Set an unique id for the column.
     * @param array<string,string> $arrAttributes   (optional) Further attributes as array with key/value pairs
     * @param int                  $colspan         (optional) Number of columns that should be join together.
     * @param int                  $colspanOffset   (optional) Number of the column where the colspan should start.
     *                                              The first column of a table will be 1.
     */
    public function addRowByArray(array $arrColumnValues, $id = null, array $arrAttributes = null, $colspan = 1, $colspanOffset = 1)
    {
        // if body area wasn't defined until now then do it
        if (!$this->tbody) {
            $this->addTableBody();
        }

        $this->addRowTypeByArray('td', $arrColumnValues, $id, $arrAttributes, $colspan, $colspanOffset);
    }

    /**
     * Disable the sort function for some columns. This is useful if a sorting of the column doesn't make sense
     * because it only show function icons or something equal.
     * @param array<int,int> $columnsSort An array which contain the columns where the sort should be disabled.
     *                                    The columns of the table starts with 1 (not 0).
     */
    public function disableDatatablesColumnsSort(array $columnsSort)
    {
        // internal datatable columns starts with 0
        foreach ($columnsSort as $columnSort) {
            $this->datatablesColumnDefs[] = '{ "orderable": false, "targets": ' . ($columnSort - 1) . ' }';
        }
    }

    /**
     * Return the number of the column which should be grouped when using the jQuery plugin DataTables.
     * @return int Return the number of the column.
     */
    public function getDatatablesGroupColumn()
    {
        return $this->groupedColumn;
    }

    /**
     * Adds javascript libs and code and inits the datatables params for a datatables table
     */
    private function initDatatablesTable()
    {
        global $gSettingsManager;

        $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/datatables/datatables.js');
        $this->htmlPage->addCssFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/datatables/datatables.css');
        $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/luxon/luxon.js');
        $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/datatables/datetime-luxon.js');

        if ($this->rowCount > 10 || $this->serverSideProcessing) {
            // set default page length of the table
            $this->datatablesInitParameters[] = '"pageLength": ' . $this->rowsPerPage;
        } else {
            // disable page length menu
            $this->datatablesInitParameters[] = '"paging": false';
        }

        // set order columns
        $this->datatablesInitParameters[] = '"order": [' . implode(',', $this->columnsOrder) . ']';

        $this->datatablesInitParameters[] = '"fixedHeader": true';

        // use DataTables Responsive extension
        $this->datatablesInitParameters[] = '"responsive": true';

        // set server-side processing
        if ($this->serverSideProcessing) {
            $this->datatablesInitParameters[] = '"processing": true';
            $this->datatablesInitParameters[] = '"serverSide": true';
            $this->datatablesInitParameters[] = '"ajax": "'.$this->serverSideFile.'"';

            // add a callback function to link openPopup to the modal window. This will be used
            // e.g. for the delete button or other things that need a modal window.
            $this->datatablesInitParameters[] = '
                "fnDrawCallback": function( oSettings ) {
                    $(".openPopup").on("click",function(){
                        $(".modal-dialog").attr("class", "modal-dialog " + $(this).attr("data-class"));
                        $(".modal-content").load($(this).attr("data-href"),function(){
                            $("#admidio-modal").modal({
                                show:true
                            });
                        });
                    });
                }
            ';
        }

        $javascriptGroup = '';
        $javascriptGroupFunction = '';

        if ($this->groupedColumn >= 0) {
            $javascriptGroup = ',
                "drawCallback": function(settings) {
                    var api  = this.api();
                    var rows = api.rows({page: "current"}).nodes();
                    var last = null;

                    api.column(' . $this->groupedColumn . ', {page: "current"}).data().each(function(group, i) {
                        if (last !== group) {
                            $(rows).eq(i).before(
                                "<tr class=\"admidio-group-heading\"><td colspan=\"' . $this->columnCount . '\">" + group + "</td></tr>"
                            );

                            last = group;
                        }
                    });
                }';
            $javascriptGroupFunction = '
                // Order by the grouping
                $("#' . $this->id . ' tbody").on("click", "tr.admidio-group-heading", function() {
                    var currentOrder = admidioTable.order()[0];
                    if (currentOrder[0] === ' . $this->groupedColumn . ' && currentOrder[1] === "asc") {
                        admidioTable.order([' . $this->groupedColumn . ', "desc"]).draw();
                    } else {
                        admidioTable.order([' . $this->groupedColumn . ', "asc"]).draw();
                    }
                });';
        }

        // if columnDefs were defined then create a comma separated string with all elements of the array
        if (count($this->datatablesColumnDefs) > 0) {
            $this->datatablesInitParameters[] = '"columnDefs": [' . implode(',', $this->datatablesColumnDefs) . ']';
        }

        // luxon doesn't work properly if we use server side processing. Than an JS error is thrown.
        if (!$this->serverSideProcessing) {
            $this->htmlPage->addJavascript(
                '
            $.fn.dataTable.luxon(formatPhpToLuxon("' . $gSettingsManager->getString('system_date') . '"));
            $.fn.dataTable.luxon(formatPhpToLuxon("' . $gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time') . '"));
            ',true
            );
        }
        $this->htmlPage->addJavascript(
            '
            var admidioTable = $("#' . $this->id . '").DataTable({' .
            implode(',', $this->datatablesInitParameters) .
            $javascriptGroup . '
            });
            ' . $javascriptGroupFunction,
            true
        );
    }

    /**
     * Adds a column to the table.
     * @param string          $type          'th' for header row or 'td' for body row.
     * @param int             $key           Column number (starts with 0).
     * @param string|string[] $value         Column value or array with column value and attributes.
     * @param int             $colspan       (optional) Number of columns that should be join together.
     * @param int             $colspanOffset (optional) Number of the column where the colspan should start.
     *                                       The first column of a table will be 1.
     */
    private function prepareAndAddColumn($type, $key, $value, $colspan = 1, $colspanOffset = 1)
    {
        $columnAttributes = array();

        // set colspan if parameters are set
        if ($colspan >= 2 && $colspanOffset === ($key + 1)) {
            $columnAttributes['colspan'] = $colspan;
        }

        if (!$this->datatables && array_key_exists($key, $this->columnsAlign)) {
            $columnAttributes['style'] = 'text-align: ' . $this->columnsAlign[$key] . ';';
        }

        // if is array than check for sort or search values
        if (is_array($value)) {
            $columnValue = $value['value'];

            if (array_key_exists('order', $value)) {
                $columnAttributes['data-order'] = $value['order'];
            }
            if (array_key_exists('search', $value)) {
                $columnAttributes['data-search'] = $value['search'];
            }
        } else {
            $columnValue = $value;
        }

        // now add column to row
        $this->addColumn($columnValue, $columnAttributes, $type);
    }

    /**
     * Set the align for each column of the current table. This method must be called
     * before a row is added to the table. Each entry of the array represents a column.
     * @param array<int,string> $columnsAlign An array which contains the align for each column of the table.
     *                                        E.g. array('center', 'left', 'left', 'right') for a table with 4 columns.
     */
    public function setColumnAlignByArray(array $columnsAlign)
    {
        if ($this->datatables) {
            foreach ($columnsAlign as $columnNumber => $align) {
                $this->datatablesColumnDefs[] = '{ targets: ' . $columnNumber . ', className: \'text-'.$align.'\' }';
            }
        } else {
            $this->columnsAlign = $columnsAlign;
        }
    }

    /**
     * This method will set for a selected column other columns that should be used to order the datatables.
     * For example if you will click the name column than you could set the columns lastname and firstname
     * as alternative order columns and the table will be ordered by lastname and firstname.
     * @param int       $selectedColumn    This is the column the user clicked to be sorted. (started with 1)
     * @param int|int[] $arrayOrderColumns This are the columns the table will internal be sorted. If you have more
     *                                     than 1 column this must be an array. The columns of the table starts with 1 (not 0).
     */
    public function setDatatablesAlternativeOrderColumns($selectedColumn, $arrayOrderColumns)
    {
        // internal datatable columns starts with 0
        if (is_array($arrayOrderColumns)) {
            /**
             * @param int $item
             * @return int decremented item
             */
            function decrement($item)
            {
                return --$item;
            }
            $orderData = implode(',', array_map('decrement', $arrayOrderColumns));
        } else {
            $orderData = --$arrayOrderColumns;
        }

        $this->datatablesColumnDefs[] = '{ "targets": [' . --$selectedColumn . '], "orderData": [' . $orderData . '] }';
    }

    /**
     * Hide some columns for the user. This is useful if you want to use the column for ordering but
     * won't show the content if this column.
     * @param array<int,int> $columnsHide An array which contain the columns that should be hidden.
     *                                    The columns of the table starts with 1 (not 0).
     */
    public function setDatatablesColumnsHide(array $columnsHide)
    {
        // internal datatable columns starts with 0
        foreach ($columnsHide as $columnHide) {
            $this->datatablesColumnDefs[] = '{ "visible": false, "targets": ' . ($columnHide - 1) . ' }';
        }
    }

    /**
     * Datatables will automatically hide columns if the screen will be to small e.g. on smartphones. You must than click
     * on a + button and will view the hidden columns. With this method you can remove specific columns from that feature.
     * These columns will always be shown. But be careful if you remove to much columns datatables must hide some columns
     * anyway.
     * @param array<int,int> $columnsNotHideResponsive An array which contain the columns that should not be hidden.
     *                                                 The columns of the table starts with 1 (not 0).
     * @param int            $priority                 Optional set a priority so datatable will first hide columns with
     *                                                 low priority and after that with higher priority
     */
    public function setDatatablesColumnsNotHideResponsive(array $columnsNotHideResponsive, $priority = 1)
    {
        // internal datatable columns starts with 0
        foreach ($columnsNotHideResponsive as $columnNotHideResponsive) {
            $this->datatablesColumnDefs[] = '{ "responsivePriority": ' . $priority . ', "targets": ' . ($columnNotHideResponsive - 1) . ' }';
        }
    }

    /**
     * Specify a column that should be used to group data. Everytime the value of this column
     * changed then a new subheader row will be created with the name of the new value.
     * @param int $columnNumber Number of the column that should be grouped. The first column starts with 1.
     *                          The columns were set with the method **addRowByArray**.
     */
    public function setDatatablesGroupColumn($columnNumber)
    {
        $this->groupedColumn = $columnNumber - 1;

        // grouped column must be first order column
        array_unshift($this->columnsOrder, '[' . $this->groupedColumn . ', "asc"]');

        // hide the grouped column
        $this->datatablesColumnDefs[] = '{ "visible": false, "targets": ' . $this->groupedColumn . ' }';
    }

    /**
     * Set the order of the columns which should be used to sort the rows.
     * @param array<int,int|array<int,int|string>> $arrayOrderColumns An array which could contain the columns that should be
     *                                                                ascending ordered or contain arrays where each array
     *                                                                contain the column and the sorting 'asc' or 'desc'. The columns
     *                                                                of the table starts with 1 (not 0).
     *                                                                Optional this could also only be a numeric value than the
     *                                                                datatable will be ordered by the number of this column ascending.
     *
     * **Code examples**
     * ```
     * $table = new HtmlTable('simple-table');
     *
     * // sort all rows after first and third column ascending
     * $table->setDatatablesOrderColumns(array(1, 3));
     * // sort all rows after first column descending and third column ascending
     * $table->setDatatablesOrderColumns(array(array(1, 'desc'), array(3, 'asc')));
     * ```
     */
    public function setDatatablesOrderColumns(array $arrayOrderColumns)
    {
        // internal datatable columns starts with 0
        foreach ($arrayOrderColumns as $column) {
            if (is_array($column)) {
                $this->columnsOrder[] = '[' . ($column[0] - 1) . ', "' . $column[1] . '"]';
            } else {
                $this->columnsOrder[] = '[' . ($column - 1) . ', "asc"]';
            }
        }
    }

    /**
     * Set the number of rows that should be displayed on one page if the jQuery plugin DataTables is used.
     * @param int $numberRows Number of rows that should be displayed on one page.
     */
    public function setDatatablesRowsPerPage($numberRows)
    {
        $this->rowsPerPage = $numberRows;
    }

    /**
     * Set a text id of the translation files that should be shown if table has no rows.
     * @param string $messageId   Text id of the translation file.
     * @param string $messageType (optional) As **default** the text will be shown. If **warning** or **error**
     *                            is set then a box in yellow or red with the message will be shown.
     */
    public function setMessageIfNoRowsFound($messageId, $messageType = 'default')
    {
        global $gL10n;

        $message = $gL10n->get($messageId);

        switch ($messageType) {
            case 'warning':
                $this->messageNoRowsFound = '<div class="alert alert-warning alert-small" role="alert"><i class="fas fa-exclamation-triangle"></i>' . $message . '</div>';
                break;
            case 'error':
                $this->messageNoRowsFound = '<div class="alert alert-danger alert-small" role="alert"><i class="fas fa-exclamation-circle"></i>' . $message . '</div>';
                break;
            default:
                $this->messageNoRowsFound = $message;
        }
    }

    /**
     * With server-side processing enabled, all paging, searching, ordering actions that DataTables performs
     * are handed off to a server where an SQL engine (or similar) can perform these actions on the large data
     * set. As such, each draw of the table will result in a new Ajax request being made to get the required data.
     * @param string $file The url with the filename that should be called by Datatables to get the data. The
     *                     called script must return a json string.
     */
    public function setServerSideProcessing($file)
    {
        $this->serverSideProcessing = true;
        $this->serverSideFile = $file;
    }

    /**
     * This method send the whole html code of the table to the browser. If the jQuery plugin DataTables
     * is activated then the javascript for that plugin will be added. Call this method if you
     * have finished your form layout. If table has no rows then a message will be shown.
     * @return string Return the html code of the table.
     */
    public function show()
    {
        if ($this->rowCount === 0 && !$this->serverSideProcessing) {
            // if table contains no rows then show message and not the table
            return '<p>' . $this->messageNoRowsFound . '</p>';
        }

        // show table content
        if ($this->datatables && $this->htmlPage instanceof HtmlPage) {
            $this->initDatatablesTable();

            return $this->getHtmlTable();
        }

        return '<div class="table-responsive">' . $this->getHtmlTable() . '</div>';
    }
}
