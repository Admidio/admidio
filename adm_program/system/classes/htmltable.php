<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class HtmlTable
 * @brief Creates an Admidio specific table with special methods
 *
 * This class inherits the common HtmlTableBasic class and extends their elements
 * with custom Admidio table methods. The class should be used to create the
 * html part of all Admidio tables. It has simple methods to add complete rows with
 * their column values to the table. It's also possible to add the jQuery plugin Datatables
 * to each table. Therefore you only need to set a flag when creating the object.
 * @par Examples
 * @code // create a simple table with one input field and a button
 * $table = new HtmlTable('simple-table');
 * $table->addRowHeadingByArray(array('Firstname', 'Lastname', 'Address', 'Phone', 'E-Mail'));
 * $table->addRowByArray(array('Hans', 'Mustermann', 'Sonnenallee 22', '+49 342 59433', 'h.mustermann@example.org'));
 * $table->addRowByArray(array('Anne', 'Musterfrau', 'Seestraße 6', '+34 7433 7433', 'a.musterfrau@example.org'));
 * $table->show(); @endcode
 * @code // create a table with jQuery datatables and align columns to center or right
 * $table = new HtmlTable('simple-table', null, true, true);
 * $table->setColumnAlignByArray(array('left', 'left', 'center', 'right'));
 * $table->addRowHeadingByArray(array('Firstname', 'Lastname', 'Birthday', 'Membership fee'));
 * $table->addRowByArray(array('Hans', 'Mustermann', 'Sonnenallee 22', '14.07.1995', '38,50'));
 * $table->show(); @endcode
 */
class HtmlTable extends HtmlTableBasic
{
    protected $id;                       ///< Html id attribute of the table.
    protected $columnAlign;              ///< Array with entry for each column with the align of that column. Values are @b right, @b left or @b center.
    protected $columnCount;              ///< Number of columns in this table. This will be set after columns were added to the table.
    protected $messageNoRowsFound;       ///< The text that should be shown if no row was added to the table
    protected $htmlPage;                 ///< A HtmlPage object that will be used to add javascript code or files to the html output page.
    protected $datatables;               ///< A flag if the jQuery plugin DataTables should be used to show the table.
    protected $datatablesInitParameters; ///< An array that stores all necessary DataTables parameters that should be set on initialization of this plugin.
    protected $datatablesColumnDefs;     ///< Array that contains several elements for DataTables columnDefs parameter.
    protected $groupedColumn;            ///< The number of the column which should be used to group the table data.
    protected $rowsPerPage;              ///< Number of rows that should be displayed on one page.
    protected $orderColumns;             ///< Array with the column number as key and the 'asc' or 'desc' as value.

    /**
     * Constructor creates the table element
     * @param string $id         Id of the table
     * @param object $htmlPage   (optional) A HtmlPage object that will be used to add javascript code
     *                           or files to the html output page.
     * @param bool   $hoverRows  (optional) If set to @b true then the active selected row will be marked with special css code
     * @param bool   $datatables (optional) If set to @b true then the jQuery plugin Datatables will be used to create the table.
     *                           Then column sort, search within the table and other features are possible.
     * @param string $class      (optional) An additional css classname. The class @b table
     *                           is set as default and need not set with this parameter.
     */
    public function __construct($id, $htmlPage = null, $hoverRows = true, $datatables = false, $class = '')
    {
        global $g_root_path, $gL10n;

        if($class === '')
        {
            $class = 'table';
        }

        if($hoverRows)
        {
            $class .= ' table-hover';
        }

        parent::__construct($id, $class);

        // initialize class member parameters
        $this->messageNoRowsFound = $gL10n->get('SYS_NO_DATA_FOUND');
        $this->id            = $id;
        $this->datatables    = $datatables;
        $this->datatablesInitParameters = array();
        $this->groupedColumn = -1;
        $this->columnCount   = 0;
        $this->rowsPerPage   = 25;
        $this->orderColumns  = array();
        $this->datatablesColumnDefs    = array();

        // when using DataTables we must set the width attribute so that all columns will change
        // dynamic their width if the browser window size change.
        if($datatables)
        {
            $this->addAttribute('width', '100%');

            $this->datatablesInitParameters[] = '"language": {"url": "'.$g_root_path.'/adm_program/libs/datatables/language/datatables.'.$gL10n->getLanguageIsoCode().'.lang"}';
        }

        if(is_object($htmlPage))
        {
            $this->htmlPage =& $htmlPage;
        }
    }

    /**
     * Adds a complete row with all columns to the table. This will be the column heading row.
     * Each value of the array represents the heading text for each column.
     * @param array  $arrayColumnValues Array with the values for each column.
     * @param string $id                (optional) Set an unique id for the column.
     * @param array  $arrAttributes     (optional) Further attributes as array with key/value pairs
     * @param int    $startColspan      (optional) Number of column where the colspan should start. The first column of a table will be 1.
     * @param int    $colspan           (optional) Number of columns that should be join together.
     */
    public function addRowHeadingByArray($arrayColumnValues, $id = null, $arrAttributes = null, $startColspan = 0, $colspan = 0)
    {
        // set an id to the column
        if($id !== null)
        {
            $arrAttributes['id'] = $id;
        }

        $this->addTableHeader();
        $this->addRow('', $arrAttributes, 'th');

        // now add each column to the row
        foreach($arrayColumnValues as $key => $value)
        {
            $columnAttributes = array();

            // set colspan if parameters are set
            if(($key + 1) == $startColspan && $colspan > 0)
            {
                $columnAttributes['colspan'] = $colspan;
            }

            if(is_array($this->columnAlign))
            {
                $columnAttributes['style'] = 'text-align: '.$this->columnAlign[$key];
            }

            // now add column to row
            $this->addColumn($value, $columnAttributes, 'th');
        }

        $this->columnCount = count($arrayColumnValues);
    }

    /**
     * Adds a complete row with all columns to the table. Each column element will be a value of the array parameter.
     * @param array  $arrayColumnValues Array with the values for each column. If you use datatables than you could set
     *                                  an array for each value with the following entries:
     *                                  array('value' => $yourValue, 'order' => $sortingValue, 'search' => $searchingValue)
     *                                  With this you can specify special values for sorting and searching.
     * @param string $id                (optional) Set an unique id for the column.
     * @param array  $arrAttributes     (optional) Further attributes as array with key/value pairs
     * @param int    $startColspan      (optional) Number of column where the colspan should start. The first column of a table will be 1.
     * @param int    $colspan           (optional) Number of columns that should be join together.
     */
    public function addRowByArray($arrayColumnValues, $id = null, $arrAttributes = null, $startColspan = 0, $colspan = 0)
    {
        // set an id to the column
        if($id !== null)
        {
            $arrAttributes['id'] = $id;
        }

        // if body area wasn't defined until now then do it
        if($this->tbody == -1)
        {
            $this->addTableBody();
        }

        $this->addRow('', $arrAttributes);

        // now add each column to the row
        foreach($arrayColumnValues as $key => $columnProperties)
        {
            $columnAttributes = array();

            // set colspan if parameters are set
            if(($key + 1) == $startColspan && $colspan > 0)
            {
                $columnAttributes['colspan'] = $colspan;
            }

            if(is_array($this->columnAlign))
            {
                $columnAttributes['style'] = 'text-align: '.$this->columnAlign[$key];
            }

            // if is array than check for sort or search values
            if(is_array($columnProperties))
            {
                $columnValue = $columnProperties['value'];

                if(isset($columnProperties['order']))
                {
                    $columnAttributes['data-order'] = $columnProperties['order'];
                }
                if(isset($columnProperties['search']))
                {
                    $columnAttributes['data-search'] = $columnProperties['search'];
                }
            }
            else
            {
                $columnValue = $columnProperties;
            }

            // now add column to row
            $this->addColumn($columnValue, $columnAttributes, 'td');
        }

        $this->columnCount = count($arrayColumnValues);
    }

    /**
     * Disable the sort function for some columns. This is useful if a sorting of the column doesn't make sense
     * because it only show function icons or something equal.
     * @param array|int $arrayColumnsSort An array which contain the columns where the sort should be disabled.
     *                                    The columns of the table starts with 1 (not 0).
     */
    public function disableDatatablesColumnsSort($arrayColumnsSort)
    {
        if(is_array($arrayColumnsSort))
        {
            // internal datatable columns starts with 0
            foreach($arrayColumnsSort as $column)
            {
                $this->datatablesColumnDefs[] = '{ "orderable":false, "targets":'.($column-1).' }';
            }
        }
        elseif(is_numeric($arrayColumnsSort))
        {
            $this->datatablesColumnDefs[] = '{ "orderable":false, "targets":'.($arrayColumnsSort-1).' }';
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
     * Set the align for each column of the current table. This method must be called
     * before a row is added to the table. Each entry of the array represents a column.
     * @param string[] $arrayColumnAlign An array which contains the align for each column of the table.
     *                                   E.g. array('center', 'left', 'left', 'right') for a table with 4 columns.
     */
    public function setColumnAlignByArray($arrayColumnAlign)
    {
        $this->columnAlign = $arrayColumnAlign;
    }

    /**
     * This method will set for a selected column other columns that should be used to order the datatables.
     * For example if you will click the name column than you could set the columns lastname and firstname
     * as alternative order columns and the table will be ordered by lastname and firstname.
     * @param int       $selectedColumn    This is the column the user clicked to be sorted.
     * @param int|array $arrayOrderColumns This are the columns the table will internal be sorted. If you have more
     *                                     than 1 column this must be an array. The columns of the table starts with 1 (not 0).
     */
    public function setDatatablesAlternativOrderColumns($selectedColumn, $arrayOrderColumns)
    {
        if(is_array($arrayOrderColumns))
        {
            // internal datatable columns starts with 0
            foreach($arrayOrderColumns as $key => $column)
            {
                $arrayOrderColumns[$key] = $column - 1;
            }

            $this->datatablesColumnDefs[] = '{ "targets": ['.($selectedColumn-1).'], "orderData": ['.(implode(',', $arrayOrderColumns)).'] }';
        }
        else
        {
            $this->datatablesColumnDefs[] = '{ "targets": ['.($selectedColumn-1).'], "orderData": ['.($arrayOrderColumns-1).'] }';
        }
    }

    /**
     * Hide some columns for the user. This is useful if you want to use the column for ordering but
     * won't show the content if this column.
     * @param array|int $arrayColumnsHide An array which contain the columns that should be hidden. The columns
     *                                    of the table starts with 1 (not 0).
     */
    public function setDatatablesColumnsHide($arrayColumnsHide)
    {
        if(is_array($arrayColumnsHide))
        {
            // internal datatable columns starts with 0
            foreach($arrayColumnsHide as $column)
            {
                $this->datatablesColumnDefs[] = '{ "visible":false, "targets":'.($column-1).' }';
            }
        }
        elseif(is_numeric($arrayColumnsHide))
        {
            $this->datatablesColumnDefs[] = '{ "visible":false, "targets":'.($arrayColumnsHide-1).' }';
        }
    }

    /**
     * Specify a column that should be used to group data. Everytime the value of this column
     * changed then a new subheader row will be created with the name of the new value.
     * @param int $columnNumber Number of the column that should be grouped. The first column starts with 1.
     *                          The columns were set with the method @b addRowByArray.
     */
    public function setDatatablesGroupColumn($columnNumber)
    {
        $this->groupedColumn = $columnNumber - 1;

        // grouped column must be first order column
        array_unshift($this->orderColumns, '['.$this->groupedColumn.', "asc"]');
        // hide the grouped column
        $this->datatablesColumnDefs[] = '{ "visible":false, "targets":'.$this->groupedColumn.' }';
    }

    /**
     * Set the order of the columns which should be used to sort the rows.
     * @param array $arrayOrderColumns An array which could contain the columns that should be
     *                                 ascending ordered or contain arrays where each array
     *                                 contain the column and the sorting 'asc' or 'desc'. The columns
     *                                 of the table starts with 1 (not 0).
     *                                 Optional this could also only be a numeric value than the
     *                                 datatable will be ordered by the number of this column ascending.
     * @par Examples
     * @code $table = new HtmlTable('simple-table');
     *
     * // sort all rows after first and third column ascending
     * $table->setDatatablesOrderColumns(array(1, 3));
     * // sort all rows after first column descending and third column ascending
     * $table->setDatatablesOrderColumns(array(array(1, 'desc'), array(3, 'asc'))); @endcode
     */
    public function setDatatablesOrderColumns($arrayOrderColumns)
    {
        if(is_array($arrayOrderColumns))
        {
            // internal datatable columns starts with 0
            foreach($arrayOrderColumns as $column)
            {
                if(is_array($column))
                {
                    $this->orderColumns[] = '['.($column[0]-1).', "'.$column[1].'"]';
                }
                else
                {
                    $this->orderColumns[] = '['.($column-1).', "asc"]';
                }
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
     * @param string $messageType (optional) As @b default the text will be shown. If @b warning or @b error
     *                            is set then a box in yellow or red with the message will be shown.
     */
    public function setMessageIfNoRowsFound($messageId, $messageType = 'default')
    {
        global $gL10n;

        switch($messageType)
        {
            case 'default':
                $this->messageNoRowsFound = $gL10n->get($messageId);
                break;
            case 'warning':
                $this->messageNoRowsFound = '<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get($messageId).'</div>';
                break;
            case 'error':
                $this->messageNoRowsFound = '<div class="alert alert-danger alert-small" role="alert"><span class="glyphicon glyphicon-exclamation-sign"></span>'.$gL10n->get($messageId).'</div>';
                break;
        }
    }

    /**
     * This method send the whole html code of the table to the browser. If the jQuery plugin DataTables
     * is activated then the javascript for that plugin will be added. Call this method if you
     * have finished your form layout. If table has no rows then a message will be shown.
     * @param  bool        $directOutput (optional) If set to @b true (default) the table html will be directly send
     *                                   to the browser. If set to @b false the html will be returned.
     * @return string|void If $directOutput is set to @b false this method will return the html code of the table.
     */
    public function show($directOutput = true)
    {
        global $g_root_path, $gPreferences;

        if($this->rowCount === 0)
        {
            // if table contains no rows then show message and not the table
            if($directOutput)
            {
                echo '<p>'.$this->messageNoRowsFound.'</p>';
            }
            else
            {
                return '<p>'.$this->messageNoRowsFound.'</p>';
            }
        }
        else
        {
            // show table content
            if($this->datatables && is_object($this->htmlPage))
            {
                $javascriptGroup = '';
                $javascriptGroupFunction = '';

                $this->htmlPage->addJavascriptFile($g_root_path.'/adm_program/libs/datatables/js/jquery.dataTables.js');
                $this->htmlPage->addJavascriptFile($g_root_path.'/adm_program/libs/datatables/js/dataTables.bootstrap.js');
                $this->htmlPage->addJavascriptFile($g_root_path.'/adm_program/libs/moment/moment.js');
                $this->htmlPage->addJavascriptFile($g_root_path.'/adm_program/libs/moment/datetime-moment.js');
                $this->htmlPage->addCssFile($g_root_path.'/adm_program/libs/datatables/css/dataTables.bootstrap.css');

                if($this->rowCount > 10)
                {
                    // set default page length of the table
                    $this->datatablesInitParameters[] = '"pageLength": '.$this->rowsPerPage;
                }
                else
                {
                    // disable page length menu
                    $this->datatablesInitParameters[] = '"paging": false';
                }

                // set order columns
                $this->datatablesInitParameters[] = '"order": ['.implode(',', $this->orderColumns).']';

                if($this->groupedColumn >= 0)
                {
                    $javascriptGroup = ',
                        "drawCallback": function ( settings ) {
                            var api  = this.api();
                            var rows = api.rows( {page:\'current\'} ).nodes();
                            var last = null;

                            api.column('.$this->groupedColumn.', {page:\'current\'} ).data().each( function ( group, i ) {
                                if ( last !== group ) {
                                    $(rows).eq( i ).before(
                                        \'<tr class="admidio-group-heading"><td colspan="'.$this->columnCount.'">\'+group+\'</td></tr>\'
                                    );

                                    last = group;
                                }
                            } );
                        }';
                    $javascriptGroupFunction = '
                        // Order by the grouping
                        $("#'.$this->id.' tbody").on( "click", "tr.admidio-group-heading", function () {
                            var currentOrder = table.order()[0];
                            if ( currentOrder[0] === '.$this->groupedColumn.' && currentOrder[1] === "asc" ) {
                                table.order( [ '.$this->groupedColumn.', "desc" ] ).draw();
                            } else {
                                table.order( [ '.$this->groupedColumn.', "asc" ] ).draw();
                            }
                        } );';
                }

                // if columnDefs were defined then create a comma separated string with all elements of the array
                if(count($this->datatablesColumnDefs) > 0)
                {
                    $this->datatablesInitParameters[] = '"columnDefs": ['.implode(',', $this->datatablesColumnDefs).']';
                }

                $this->htmlPage->addJavascript('
                    $.fn.dataTable.moment(formatPhpToMoment("'.$gPreferences['system_date'].'"));
                    $.fn.dataTable.moment(formatPhpToMoment("'.$gPreferences['system_date'].' '.$gPreferences['system_time'].'"));

                    var admidioTable = $("#'.$this->id.'").DataTable( {'.
                        implode(',', $this->datatablesInitParameters).
                        $javascriptGroup.'
                    });
                    '.$javascriptGroupFunction, true);
            }

            if($directOutput)
            {
                echo $this->getHtmlTable();
            }
            else
            {
                return $this->getHtmlTable();
            }
        }
    }
}
