<?php
namespace Admidio\UI\Component;

use Admidio\UI\Presenter\PagePresenter;

/**
 * @brief Creates the Javascript output for the jQuery DataTables plugin
 *
 * There are some methods that will help to create the correct Javascript for DataTables and handles some
 * table effects that we want to use in Admidio.
 *
 * **Code example**
 * ```
 * // create a simple DataTables javascript
 * $dataTables = new DataTables($htmlPage, 'my-table-id');
 * $dataTables->createJavascript(145, 7);
 * ```
 *
 * **Code example**
 * ```
 * // create a DataTables javascript and set some preferences like a group column or disable sorting for
 * // some columns or not hide a column in responsive mode.
 * $dataTables = new DataTables($htmlPage, 'my-table-id');
 * $dataTables->setDatatablesGroupColumn(1);
 * $dataTables->disableDatatablesColumnsSort(array(3, 8));
 * $dataTables->setDatatablesColumnsNotHideResponsive(array(8));
 * $dataTables->createJavascript(145, 7);
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class DataTables
{
    /**
     * @var string Html id attribute of the table.
     */
    protected string $id;
    /**
     * @var int Number of rows that should be displayed on one page.
     */
    protected int $rowsPerPage = 25;
    /**
     * @var array<int,string> Array with the number of rows that should be displayed on one page as key and the
     *                        text that should be shown in the menu as value.
     *                        E.g. array(10 => '10', 25 => '25', 50 => '50', 100 => '100').
     */
    protected array $rowsPerPageMenuEntries = array(
        10 => '10',
        25 => '25',
        50 => '50',
        100 => '100'
    );
    /**
     * @var array<int,string> Array with the column number as key and the 'asc' or 'desc' as value.
     */
    protected array $columnsOrder = array();
    /**
     * @var int The number of the column which should be used to group the table data.
     */
    protected int $groupedColumn = -1;
    /**
     * @var array<int,string> An array that stores all necessary DataTables parameters that should be set on initialization of this plugin.
     */
    protected array $datatablesInitParameters = array();
    /**
     * @var array<int,string> Array that contains several elements for DataTables columnDefs parameter.
     */
    protected array $datatablesColumnDefs = array();
    /**
     * @var HtmlPage|PagePresenter A HtmlPage object that will be used to add javascript code or files to the html output page.
     */
    protected HtmlPage|PagePresenter $htmlPage;
    /**
     * @var bool A flag that set the server-side processing for datatables.
     */
    protected bool $serverSideProcessing = false;
    /**
     * @var string The script that should be called when using server-side processing.
     */
    protected string $serverSideFile = '';

    /**
     * Constructor creates the table element
     * @param HtmlPage|PagePresenter $htmlPage An object of the current HtmlPage where the HTML table is integrated.
     * @param string $tableID The HTML ID of the table which should be converted in a DataTables.
     */
    public function __construct(HtmlPage|PagePresenter $htmlPage, string $tableID)
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
    public function disableColumnsSort(array $columnsSort)
    {
        // internal datatable columns starts with 0
        foreach ($columnsSort as $columnSort) {
            $this->datatablesColumnDefs[] = '{ "orderable": false, "targets": ' . ($columnSort - 1) . ' }';
        }
    }

    /**
     * Adds javascript libs and code and inits the datatables params for a datatables table
     * @param int $rowCount Number of rows of the current table.
     * @param int $columnCount Number of columns of the current table.
     * @throws \Admidio\Infrastructure\Exception
     */
    public function createJavascript(int $rowCount = 0, int $columnCount = 0)
    {
        global $gSettingsManager, $gL10n;

        $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/datatables/datatables.js');
        $this->htmlPage->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/datatables/datatables.css');
        if (!$this->serverSideProcessing) {
            $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/luxon/luxon.js');
            $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/datatables/datetime-luxon.js');
        }

        $this->datatablesInitParameters[] = '"language": {"url": "' . ADMIDIO_URL . FOLDER_LIBS . '/datatables/language/datatables.' . $gL10n->getLanguageIsoCode() . '.json"}';

        if ($rowCount > 10 || $this->serverSideProcessing) {
            // set default page length of the table
            $this->datatablesInitParameters[] = '"pageLength": ' . $this->rowsPerPage;
            // set page length menu entries
            // check if there is a entry with the value of -1, if not then add it
            if (!array_key_exists(-1, $this->rowsPerPageMenuEntries)) {
                $this->rowsPerPageMenuEntries[-1] = $gL10n->get('SYS_ALL');
            }
            $this->datatablesInitParameters[] = '"lengthMenu": [' . json_encode(array_keys($this->rowsPerPageMenuEntries)) . ', ' . json_encode(array_values($this->rowsPerPageMenuEntries)) . ']';
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
            $this->datatablesInitParameters[] = '"ajax": "' . $this->serverSideFile . '"';
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

                    if (settings.json && settings.json.notice) {
                        $.each(settings.json.notice, function (key, value) {
                            if (value.trim() !== \'\') {
                                $(\'#\' + key).text(value).show();
                            } else {
                                $(\'#\' + key).hide();
                            }
                        });
                    }
                }';
            $javascriptGroupFunction = '
                // Order by the grouping
                $("#' . $this->id . ' tbody").on("click", "tr.admidio-group-heading", function() {
                    const currentOrder = admidioTable_' . $this->id . '.order()[0];
                    if (currentOrder[0] === ' . $this->groupedColumn . ' && currentOrder[1] === "asc") {
                        admidioTable_' . $this->id . '.order([' . $this->groupedColumn . ', "desc"]).draw();
                    } else {
                        admidioTable_' . $this->id . '.order([' . $this->groupedColumn . ', "asc"]).draw();
                    }
                });';
        } else {
            $this->datatablesInitParameters[] = '"drawCallback": function(settings) {
                    if (settings.json && settings.json.notice) {
                        // Iterate through the notice object
                        $.each(settings.json.notice, function (key, value) {
                            if (value.trim() !== \'\') {
                                $(\'#\' + key).html(value).show();
                            } else {
                                $(\'#\' + key).hide();
                            }
                        });
                    }
                }';

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
            ', true
            );
        }
        $this->htmlPage->addJavascript(
            '
            const admidioTable_' . $this->id . ' = $("#' . $this->id . '").DataTable({' .
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
            $this->datatablesColumnDefs[] = '{ targets: ' . $columnNumber . ', className: \'text-' . $align . '\' }';
        }
    }

    /**
     * This method will set for a selected column other columns that should be used to order the datatables.
     * For example if you will click the name column than you could set the columns lastname and firstname
     * as alternative order columns and the table will be ordered by lastname and firstname.
     * @param int $selectedColumn This is the column the user clicked to be sorted. (started with 1)
     * @param int|int[] $arrayOrderColumns These are the columns the table will internal be sorted. If you have more
     *                                     then 1 column this must be an array. The columns of the table starts with 1 (not 0).
     */
    public function setAlternativeOrderColumns(int $selectedColumn, $arrayOrderColumns)
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
    public function setColumnsHide(array $columnsHide)
    {
        // internal datatable columns starts with 0
        foreach ($columnsHide as $columnHide) {
            $this->datatablesColumnDefs[] = '{ "visible": false, "targets": ' . ($columnHide - 1) . ' }';
        }
    }

    /**
     * Datatables will automatically hide columns if the screen will be to small e.g. on smartphones. You must then click
     * on a + button and will view the hidden columns. With this method you can remove specific columns from that feature.
     * These columns will always be shown. But be careful if you remove too many columns datatables must hide some columns
     * anyway.
     * @param array<int,int> $columnsNotHideResponsive An array which contain the columns that should not be hidden.
     *                                                 The columns of the table starts with 1 (not 0).
     * @param int $priority Optional set a priority so datatable will first hide columns with
     *                                                 low priority and after that with higher priority
     */
    public function setColumnsNotHideResponsive(array $columnsNotHideResponsive, int $priority = 1)
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
    public function setGroupColumn(int $columnNumber)
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
     * $table = new HtmlDataTable('simple-table');
     *
     * // sort all rows after first and third column ascending
     * $table->setDatatablesOrderColumns(array(1, 3));
     * // sort all rows after first column descending and third column ascending
     * $table->setDatatablesOrderColumns(array(array(1, 'desc'), array(3, 'asc')));
     * ```
     */
    public function setOrderColumns(array $arrayOrderColumns)
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
     * Set the menu entries that should be shown in the page length menu of the DataTables.
     * @param array<int,string> $menuEntries An array with the number of rows that should be displayed on one page as key
     *                                       and the text that should be shown in the menu as value.
     *                                       E.g. array(10 => '10', 25 => '25', 50 => '50', 100 => '100').
     */
    public function setRowsPerPageMenuEntries(array $menuEntries)
    {
        $this->rowsPerPageMenuEntries = $menuEntries;
    }

    /**
     * Set the number of rows that should be displayed on one page if the jQuery plugin DataTables is used.
     * @param int $numberRows Number of rows that should be displayed on one page.
     */
    public function setRowsPerPage(int $numberRows)
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
