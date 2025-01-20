<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Creates the Javascript output for the jQuery DataTables plugin
 *
 * There are some methods that will help to create the correct Javascript for DataTables and handles some
 * table effects that we want to use in Admidio.
 *
 * **Code example**
 * ```
 * // create a simple DataTables javascript
 * $dataTables = new HtmlDataTables($htmlPage, 'my-table-id');
 * $dataTables->createJavascript(145, 7);
 * ```
 *
 * **Code example**
 * ```
 * // create a DataTables javascript and set some preferences like a group column or disable sorting for
 * // some columns or not hide a column in responsive mode.
 * $dataTables = new HtmlDataTables($htmlPage, 'my-table-id');
 * $dataTables->setDatatablesGroupColumn(1);
 * $dataTables->disableDatatablesColumnsSort(array(3, 8));
 * $dataTables->setDatatablesColumnsNotHideResponsive(array(8));
 * $dataTables->createJavascript(145, 7);
 * ```
 */
class HtmlDataTables
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
     * @var array<int,string> Array with the column number as key and the 'asc' or 'desc' as value.
     */
    protected $columnsOrder = array();
    /**
     * @var int The number of the column which should be used to group the table data.
     */
    protected $groupedColumn = -1;
    /**
     * @var array<int,string> An array that stores all necessary DataTables parameters that should be set on initialization of this plugin.
     */
    protected $datatablesInitParameters = array();
    /**
     * @var array<int,string> Array that contains several elements for DataTables columnDefs parameter.
     */
    protected $datatablesColumnDefs = array();
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
     * @param HtmlPage $htmlPage An object of the current HtmlPage where the HTML table is integrated.
     * @param string $tableID    The HTML ID of the table which should be converted in a DataTables.
     */
    public function __construct(HtmlPage $htmlPage, string $tableID)
    {
        $this->htmlPage = $htmlPage;
        $this->id = $tableID;
    }

    /**
     * Disable the sort function for some columns. This is useful if a sorting of the column doesn't make sense
     * because it only shows function icons or something equal.
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
     * Adds javascript libs and code and inits the datatables params for a datatables table
     * @param int $rowCount    Number of rows of the current table.
     * @param int $columnCount Number of columns of the current table.
     */
    public function createJavascript(int $rowCount = 0, int $columnCount = 0)
    {
        global $gSettingsManager, $gL10n;

        $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/datatables/datatables.js');
        $this->htmlPage->addCssFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/datatables/datatables.css');
        $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/luxon/luxon.js');
        $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/datatables/datetime-luxon.js');

        $this->datatablesInitParameters[] = '"language": {"url": "' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/datatables/language/datatables.' . $gL10n->getLanguageIsoCode() . '.json"}';

        if ($rowCount > 10 || $this->serverSideProcessing) {
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
                    const api  = this.api();
                    const rows = api.rows({page: "current"}).nodes();
                    var last = null;

                    api.column(' . $this->groupedColumn . ', {page: "current"}).data().each(function(group, i) {
                        if (last !== group) {
                            $(rows).eq(i).before(
                                "<tr class=\"admidio-group-heading\"><td colspan=\"' . $columnCount . '\">" + group + "</td></tr>"
                            );

                            last = group;
                        }
                    });
                }';
            $javascriptGroupFunction = '
                // Order by the grouping
                $("#' . $this->id . ' tbody").on("click", "tr.admidio-group-heading", function() {
                    const currentOrder = admidioTable_'. $this->id . '.order()[0];
                    if (currentOrder[0] === ' . $this->groupedColumn . ' && currentOrder[1] === "asc") {
                        admidioTable_'. $this->id . '.order([' . $this->groupedColumn . ', "desc"]).draw();
                    } else {
                        admidioTable_'. $this->id . '.order([' . $this->groupedColumn . ', "asc"]).draw();
                    }
                });';
        }

        // if columnDefs were defined then create a comma separated string with all elements of the array
        if (count($this->datatablesColumnDefs) > 0) {
            $this->datatablesInitParameters[] = '"columnDefs": [' . implode(',', $this->datatablesColumnDefs) . ']';
        }

        // luxon doesn't work properly if we use server side processing. Then an JS error is thrown.
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
            const admidioTable_'. $this->id . ' = $("#' . $this->id . '").DataTable({' .
            implode(',', $this->datatablesInitParameters) .
            $javascriptGroup . '
            });
            ' . $javascriptGroupFunction,
            true
        );
    }

    /**
     * Set the align for each column of the current table. This method must be called
     * before a row is added to the table. Each entry of the array represents a column.
     * @param array<int,string> $columnsAlign An array which contains the align for each column of the table.
     *                                        E.g. array('center', 'left', 'left', 'right') for a table with 4 columns.
     */
    public function setColumnAlignByArray(array $columnsAlign)
    {
        foreach ($columnsAlign as $columnNumber => $align) {
            $this->datatablesColumnDefs[] = '{ targets: ' . $columnNumber . ', className: \'text-'.$align.'\' }';
        }
    }

    /**
     * This method will set for a selected column other columns that should be used to order the datatables.
     * For example if you will click the name column than you could set the columns lastname and firstname
     * as alternative order columns and the table will be ordered by lastname and firstname.
     * @param int $selectedColumn    This is the column the user clicked to be sorted. (started with 1)
     * @param int|int[] $arrayOrderColumns These are the columns the table will internal be sorted. If you have more
     *                                     then 1 column this must be an array. The columns of the table starts with 1 (not 0).
     */
    public function setDatatablesAlternativeOrderColumns(int $selectedColumn, $arrayOrderColumns)
    {
        // internal datatable columns starts with 0
        if (is_array($arrayOrderColumns)) {
            /**
             * @param int $item
             * @return int decremented item
             */
            function decrement(int $item): int
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
     * These columns will always be shown. But be careful if you remove too many columns datatables must hide some columns
     * anyway.
     * @param array<int,int> $columnsNotHideResponsive An array which contain the columns that should not be hidden.
     *                                                 The columns of the table starts with 1 (not 0).
     * @param int $priority                            Optional set a priority so datatable will first hide columns with
     *                                                 low priority and after that with higher priority
     */
    public function setDatatablesColumnsNotHideResponsive(array $columnsNotHideResponsive, int $priority = 1)
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
    public function setDatatablesGroupColumn(int $columnNumber)
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
    public function setDatatablesRowsPerPage(int $numberRows)
    {
        $this->rowsPerPage = $numberRows;
    }

    /**
     * With server-side processing enabled, all paging, searching, ordering actions that DataTables performs
     * are handed off to a server where an SQL engine (or similar) can perform these actions on the large data
     * set. As such, each draw of the table will result in a new Ajax request being made to get the required data.
     * @param string $file The url with the filename that should be called by Datatables to get the data. The
     *                     called script must return a json string.
     */
    public function setServerSideProcessing(string $file)
    {
        $this->serverSideProcessing = true;
        $this->serverSideFile = $file;
    }
}
