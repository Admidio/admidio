<?php
use Admidio\Infrastructure\Exception;
use Admidio\UI\Component\DataTables;
use Admidio\UI\Presenter\PagePresenter;

/**
 * @brief Creates an Admidio specific table with special methods
 *
 * This class inherits the common HtmlTableBasic class and extends their elements
 * with custom Admidio table methods. The class should be used to create the
 * html part of all Admidio tables. It has simple methods to add complete rows with
 * their column values to the table. It's also possible to add the jQuery plugin Datatables
 * to each table. Therefore, you only need to set a flag when creating the object.
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
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class HtmlTable extends HtmlTableBasic
{
    /**
     * @var string Html id attribute of the table.
     */
    protected string $id;
    /**
     * @var array<int,string> Array with entry for each column with the alignment of that column of datatables are not used.
     * Values are **right**, **left** or **center**.
     */
    protected array $columnsAlign = array();
    /**
     * @var bool A flag if the jQuery plugin DataTables should be used to show the table.
     */
    protected bool $useDatatables;
    /**
     * @var DataTables An object of the DataTables class to handle the Javascript output of the jQuery plugin DataTables.
     */
    protected DataTables $datatables;
    /**
     * @var string The text that should be shown if no row was added to the table
     */
    protected string $messageNoRowsFound;
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
     * @param string $id ID of the table
     * @param HtmlPage|null $htmlPage (optional) A HtmlPage object that will be used to add javascript code
     *                         or files to the html output page.
     * @param bool $hoverRows (optional) If set to **true** then the active selected row will be marked with special css code
     * @param bool $datatables (optional) If set to **true** then the jQuery plugin Datatables will be used to create the table.
     *                         Then column sort, search within the table and other features are possible.
     * @param string $class (optional) An additional css classname. The class **table**
     *                         is set as default and need not set with this parameter.
     * @throws Exception
     */
    public function __construct(string $id, HtmlPage|PagePresenter $htmlPage = null, $hoverRows = true, bool $datatables = false, string $class = '')
    {
        global $gL10n;

        if ($class === '') {
            $class = 'table';
        }

        if ($hoverRows) {
            $class .= ' table-hover';
        }

        parent::__construct($id, $class);

        // initialize class member parameters
        $this->id = $id;
        $this->useDatatables = $datatables;
        $this->messageNoRowsFound = $gL10n->get('SYS_NO_DATA_FOUND');

        if (isset($htmlPage)) {
            $this->htmlPage =& $htmlPage;
        }

        // when using DataTables we must set the width attribute so that all columns will change
        // dynamic their width if the browser window size change.
        if ($this->useDatatables) {
            $this->datatables = new DataTables($this->htmlPage, $this->id);
            $this->addAttribute('width', '100%');
        }
    }

    /**
     * Adds a complete row with all columns to the table. Each column element will be a value of the array parameter.
     * @param string $type            'th' for header row or 'td' for body row
     * @param array<int,mixed>     $arrColumnValues Array with the values for each column. If you use datatables
     *                                              then you could set an array for each value with the following entries:
     *                                              array('value' => $yourValue, 'order' => $sortingValue, 'search' => $searchingValue)
     *                                              With this you can specify special values for sorting and searching.
     * @param string $id           (optional) Set an unique id for the column.
     * @param array<string,string> $arrAttributes   (optional) Further attributes as array with key/value pairs
     * @param int $colspan         (optional) Number of columns that should be joined together.
     * @param int $colspanOffset   (optional) Number of column where the colspan should start.
     *                                              The first column of a table will be 1.
     */
    private function addRowTypeByArray(string $type, array $arrColumnValues, string $id = '', array $arrAttributes = null, int $colspan = 1, int $colspanOffset = 1)
    {
        // set an id to the column
        if ($id !== '') {
            $arrAttributes['id'] = $id;
        }

        $this->addRow('', $arrAttributes, $type);

        $this->columnCount = count($arrColumnValues);

        // now add each column to the row
        foreach ($arrColumnValues as $key => $value) {
            $this->prepareAndAddColumn($type, (int) $key, $value, $colspan, $colspanOffset);
        }
    }

    /**
     * Adds a complete row with all columns to the table. This will be the column footer row.
     * Each value of the array represents the heading text for each column.
     * @param array<int,string>    $arrColumnValues Array with the values for each column.
     * @param string $id              (optional) Set an unique id for the column.
     * @param array<string,string> $arrAttributes   (optional) Further attributes as array with key/value pairs
     * @param int $colspan         (optional) Number of columns that should be joined together.
     * @param int $colspanOffset   (optional) Number of the column where the colspan should start. The first column of a table will be 1.
     */
    public function addRowFooterByArray(array $arrColumnValues, string $id = '', array $arrAttributes = null, int $colspan = 1, int $colspanOffset = 1)
    {
        $this->addTableFooter();
        $this->addRowTypeByArray('td', $arrColumnValues, $id, $arrAttributes, $colspan, $colspanOffset);
    }

    /**
     * Adds a complete row with all columns to the table. This will be the column heading row.
     * Each value of the array represents the heading text for each column.
     * @param array<int,string>    $arrColumnValues Array with the values for each column.
     * @param string $id           (optional) Set an unique id for the column.
     * @param array<string,string> $arrAttributes   (optional) Further attributes as array with key/value pairs
     * @param int $colspan         (optional) Number of columns that should be joined together.
     * @param int $colspanOffset   (optional) Number of the column where the colspan should start. The first column of a table will be 1.
     */
    public function addRowHeadingByArray(array $arrColumnValues, string $id = '', array $arrAttributes = null, int $colspan = 1, int $colspanOffset = 1)
    {
        $this->addTableHeader();
        $this->addRowTypeByArray('th', $arrColumnValues, $id, $arrAttributes, $colspan, $colspanOffset);
    }

    /**
     * Adds a complete row with all columns to the table. Each column element will be a value of the array parameter.
     * @param array<int,mixed>     $arrColumnValues Array with the values for each column. If you use datatables
     *                                              then you could set an array for each value with the following entries:
     *                                              array('value' => $yourValue, 'order' => $sortingValue, 'search' => $searchingValue)
     *                                              With this you can specify special values for sorting and searching.
     * @param string $id           (optional) Set an unique id for the column.
     * @param array<string,string> $arrAttributes   (optional) Further attributes as array with key/value pairs
     * @param int $colspan         (optional) Number of columns that should be joined together.
     * @param int $colspanOffset   (optional) Number of the column where the colspan should start.
     *                                              The first column of a table will be 1.
     */
    public function addRowByArray(array $arrColumnValues, string $id = '', array $arrAttributes = null, int $colspan = 1, int $colspanOffset = 1)
    {
        // if body area wasn't defined until now then do it
        if (!$this->tbody) {
            $this->addTableBody();
        }

        $this->addRowTypeByArray('td', $arrColumnValues, $id, $arrAttributes, $colspan, $colspanOffset);
    }

    /**
     * Disable the sort function for some columns. This is useful if a sorting of the column doesn't make sense
     * because it only shows function icons or something equal.
     * @param array<int,int> $columnsSort An array which contain the columns where the sort should be disabled.
     *                                    The columns of the table starts with 1 (not 0).
     */
    public function disableDatatablesColumnsSort(array $columnsSort)
    {
        if ($this->useDatatables) {
            $this->datatables->disableColumnsSort($columnsSort);
        }
    }

    /**
     * Adds a column to the table.
     * @param string $type          'th' for header row or 'td' for body row.
     * @param int $key            Column number (starts with 0).
     * @param string|string[] $value Column value or array with column value and attributes.
     * @param int $colspan           (optional) Number of columns that should be joined together.
     * @param int $colspanOffset     (optional) Number of the column where the colspan should start.
     *                               The first column of a table will be 1.
     */
    private function prepareAndAddColumn(string $type, int $key, $value, int $colspan = 1, int $colspanOffset = 1)
    {
        $columnAttributes = array();

        // set colspan if parameters are set
        if ($colspan >= 2 && $colspanOffset === ($key + 1)) {
            $columnAttributes['colspan'] = $colspan;
        }

        if (!$this->useDatatables && array_key_exists($key, $this->columnsAlign)) {
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
        if ($this->useDatatables) {
            $this->datatables->setColumnAlignByArray($columnsAlign);
        } else {
            $this->columnsAlign = $columnsAlign;
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
        if ($this->useDatatables) {
            $this->datatables->setAlternativeOrderColumns($selectedColumn, $arrayOrderColumns);
        }
    }

    /**
     * Hide some columns for the user. This is useful if you want to use the column for ordering but
     * won't show the content if this column.
     * @param array<int,int> $columnsHide An array which contain the columns that should be hidden.
     *                                    The columns of the table starts with 1 (not 0).
     */
    public function setDatatablesColumnsHide(array $columnsHide)
    {
        if ($this->useDatatables) {
            $this->datatables->setColumnsHide($columnsHide);
        }
    }

    /**
     * Datatables will automatically hide columns if the screen will be to small e.g. on smartphones. You must then click
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
        if ($this->useDatatables) {
            $this->datatables->setColumnsNotHideResponsive($columnsNotHideResponsive, $priority);
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
        if ($this->useDatatables) {
            $this->datatables->setGroupColumn($columnNumber);
        }
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
        if ($this->useDatatables) {
            $this->datatables->setOrderColumns($arrayOrderColumns);
        }
    }

    /**
     * Set the number of rows that should be displayed on one page if the jQuery plugin DataTables is used.
     * @param int $numberRows Number of rows that should be displayed on one page.
     */
    public function setDatatablesRowsPerPage(int $numberRows)
    {
        if ($this->useDatatables) {
            $this->datatables->setRowsPerPage($numberRows);
        }
    }

    /**
     * Set a text id of the translation files that should be shown if table has no rows.
     * @param string $messageId Text id of the translation file.
     * @param string $messageType (optional) As **default** the text will be shown. If **warning** or **error**
     *                            is set then a box in yellow or red with the message will be shown.
     * @throws Exception
     */
    public function setMessageIfNoRowsFound(string $messageId, string $messageType = 'default')
    {
        global $gL10n;

        $message = $gL10n->get($messageId);

        switch ($messageType) {
            case 'warning':
                $this->messageNoRowsFound = '<div class="alert alert-warning alert-small" role="alert"><i class="bi bi-exclamation-triangle-fill"></i>' . $message . '</div>';
                break;
            case 'error':
                $this->messageNoRowsFound = '<div class="alert alert-danger alert-small" role="alert"><i class="bi bi-exclamation-circle-fill"></i>' . $message . '</div>';
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
    public function setServerSideProcessing(string $file)
    {
        $this->datatables->setServerSideProcessing($file);
        $this->serverSideProcessing = true;
        $this->serverSideFile = $file;
    }

    /**
     * This method send the whole html code of the table to the browser. If the jQuery plugin DataTables
     * is activated then the javascript for that plugin will be added. Call this method if you
     * have finished your form layout. If table has no rows then a message will be shown.
     * @return string Return the html code of the table.
     * @throws Exception
     */
    public function show(): string
    {
        if ($this->rowCount === 0 && !$this->serverSideProcessing) {
            // if table contains no rows then show message and not the table
            return '<p>' . $this->messageNoRowsFound . '</p>';
        }

        // show table content
        if ($this->useDatatables && isset($this->htmlPage)) {
            $this->datatables->createJavascript($this->rowCount, $this->columnCount);

            return $this->getHtmlTable();
        }

        return '<div class="table-responsive">' . $this->getHtmlTable() . '</div>';
    }
}
