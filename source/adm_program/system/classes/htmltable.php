<?php 
/*****************************************************************************/
/** @class HtmlTable
 *  @brief Creates an Admidio specific table with special methods
 *
 *  This class inherits the common HtmlTableBasic class and extends their elements
 *  with custom Admidio table methods. The class should be used to create the 
 *  html part of all Admidio tables. It has simple methods to add complete rows with
 *  their column values to the table.
 *  @par Examples
 *  @code // create a simple table with one input field and a button
 *  $table = new HtmlTable('simple-table');
 *  $table->addRowHeadingByArray(array('Firstname', 'Lastname', 'Address', 'Phone', 'E-Mail'));
 *  $table->addRowByArray(array('Hans', 'Mustermann', 'Sonnenallee 22', '+49 342 59433', 'h.mustermann@example.org'));
 *  $table->addRowByArray(array('Anne', 'Musterfrau', 'Seestraße 6', '+34 7433 7433', 'a.musterfrau@example.org'));
 *  $table->show();@endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class HtmlTable extends HtmlTableBasic
{
    private   $id;                   ///< Html id attribute of the table.
    protected $columnAlign;          ///< Array with entry for each column with the align of that column. Values are @b right, @b left or @b center.
    protected $columnCount;          ///< Number of columns in this table. This will be set after columns were added to the table.
    protected $highlightSelectedRow; ///< If set to true then the current selected row will be highlighted.
    private   $htmlPage;             ///< A HtmlPage object that will be used to add javascript code or files to the html output page.
    private   $datatables;           ///< A flag if the jQuery plugin DataTables should be used to show the table.
    private   $groupedColumn;        ///< The number of the column which should be used to group the table data.
    private   $hiddenColumns;        ///< Array with the column numbers that should not be shown
    private   $rowsPerPage;          ///< Number of rows that should be displayed on one page.
    private   $orderColumns;         ///< Array with the column number as key and the 'asc' or 'desc' as value.

    /** Constructor creates the table element
     *  @param $id         Id of the table
     *  @param $datatables If set to @b true then the jQuery plugin Datatables will be
     *                     used to create the table. Then column sort, search within the
     *                     table and other features are possible.
     *  @param $htmlPage   Optional a HtmlPage object that will be used to add javascript code 
     *                     or files to the html output page.
     *  @param $class      Optional an additional css classname. The class @b admTable
     *                     is set as default and need not set with this parameter.
     */
    public function __construct($id, $datatables = false, &$htmlPage = null, $class = '')
    {
        if(strlen($class) == 0)
        {
            $class = 'admTable';
        }

        parent::__construct($id, $class);
        
        // initialize class member parameters
        $this->id = $id;
        $this->highlightSelectedRow = false;
        $this->datatables    = $datatables;
        $this->groupedColumn = -1;
        $this->hiddenColumns = array();
        $this->columnCount   = 0;
        $this->rowsPerPage   = 25;
        $this->orderColumns  = array();

        if(is_object($htmlPage))
        {
            $this->htmlPage =& $htmlPage;
        }
    }

    /** Adds a complete row with all columns to the table. This will be the column heading row.
     *  Each value of the array represents the heading text for each column.
     *  @param $arrayRowValues Array with the values for each column.
     *  @param $id             Optional set an unique id for the column.
     *  @param $arrAttributes  Further attributes as array with key/value pairs
     *  @param $startColspan   Number of column where the colspan should start. The first column of a table will be 1.
     *  @param $colspan        Number of columns that should be join together.
     */
    public function addRowHeadingByArray($arrayColumnValues, $id = null, $arrAttributes = null, $startColspan = 0, $colspan = 0)
    {
        $arrAttributes['class'] = 'admTableRowHeading';
        
        // set an id to the column
        if($id != null)
        {
            $arrAttributes['id'] = $id;
        }

        $this->addTableHeader();
        $this->addRow(null, $arrAttributes);
        
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

    /** Adds a complete row with all columns to the table. Each column element will be a
     *  value of the array parameter.
     *  @param $arrayRowValues Array with the values for each column.
     *  @param $id             Optional set an unique id for the column.
     *  @param $arrAttributes  Further attributes as array with key/value pairs
     *  @param $startColspan   Number of column where the colspan should start. The first column of a table will be 1.
     *  @param $colspan        Number of columns that should be join together.
     */
    public function addRowByArray($arrayColumnValues, $id = null, $arrAttributes = null, $startColspan = 0, $colspan = 0)
    {
        if(is_array($arrAttributes) == false || array_key_exists('class', $arrAttributes) == false)
        {
            $arrAttributes['class'] = 'admTableRow';
        }
        
        if($this->highlightSelectedRow == true)
        {
            $arrAttributes['class'] .= ' admTableRowHighlight';
        }
        
        // set an id to the column
        if($id != null)
        {
            $arrAttributes['id'] = $id;
        }
        
        // if body area wasn't defined until now then do it
        if($this->tbody == -1)
        {
            $this->addTableBody();
        }
        
        $this->addRow(null, $arrAttributes);
        
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
            $this->addColumn($value, $columnAttributes, 'td');
        }
        
        $this->columnCount = count($arrayColumnValues);
    }
    
    /** Return the number of the column which should be grouped when using the
     *  jQuery plugin DataTables.
     *  @return Return the number of the column.
     */
    public function getDatatablesGroupColumn()
    {
        return $this->groupedColumn;
    }
    
    /** If this flag will be set to true then the current selected row of the table will
     *  be highlighted. Therefore the row element @b tr will get the css class @b admTableRowHighlight.
     *  @param $highlight If set to true the current row of the table will be highlighted.
     */
    public function highlightSelectedRow($highlight)
    {
        If($highlight == true)
        {
            $this->highlightSelectedRow = true;
        }
        else
        {
            $this->highlightSelectedRow = false;
        }
    }
    
    /** Set the align for each column of the current table. This method must be called
     *  before a row is added to the table. Each entry of the array represents a column.
     *  @param $arrayColumnAlign An array which contains the align for each column of the table.
     *                           E.g. array('center', 'left', 'left', 'right') for a table with 4 columns.
     */
    public function setColumnAlignByArray($arrayColumnAlign)
    {
        $this->columnAlign = $arrayColumnAlign;
    }
    
    /** Specify a column that should be used to group data. Everytime the value of this column
     *  changed then a new subheader row will be created with the name of the new value.
     *  @param $columnNumber Number of the column that should be grouped. The first column 
     *                       starts with 1. The columns were set with the method @b addRowByArray.
     */
    public function setDatatablesGroupColumn($columnNumber)
    {
        $this->groupedColumn = $columnNumber - 1;
    }
    
    /** Hide some columns for the user. This is useful if you want to use the column for ordering but
     *  won't show the content if this column.
     *  @param $arrayHideColumns An array which contain the columns that should be hidden. The columns
     *                           of the table starts with 1 (not 0).
     */
    public function setDatatablesHideColumns($arrayHideColumns)
    {
        if(is_array($arrayHideColumns))
        {
            // internal datatable columns starts with 0
            foreach($arrayHideColumns as $column)
            {
                $this->hiddenColumns[] = ($column-1);
            }
        }
        elseif(is_numeric($arrayHideColumns))
        {
            $this->hiddenColumns[] = ($arrayHideColumns-1);            
        }
    }
    
    /** Set the order of the columns which should be used to sort the rows.
     *  @param $arrayOrderColumns An array which could contain the columns that should be
     *                            ascending ordered or contain arrays where each array 
     *                            contain the column and the sorting 'asc' or 'desc'. The columns
     *                            of the table starts with 1 (not 0).
     *                            Optional this could also only be a numeric value than the
     *                            datatable will be ordered by the number of this column ascending.
     *  @par Examples
     *  @code $table = new HtmlTable('simple-table');
     *  // sort all rows after first and third column ascending
     *  $table->setDatatablesOrderColumns(array(1, 3));
     *  // sort all rows after first column descending and third column ascending
     *  $table->setDatatablesOrderColumns(array(array(1, 'desc'), array(3, 'asc')));@endcode
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
                    $this->orderColumns[($column[0]-1)] = $column[1];
                }
                else
                {
                    $this->orderColumns[($column-1)] = 'asc';
                }
            }
        }
    }
    
    /** Set the number of rows that should be displayed on one page if the jQuery plugin
     *  DataTables is used.
     *  @param $numberRows Number of rows that should be displayed on one page.
     */
    public function setDatatablesRowsPerPage($numberRows)
    {
        $this->rowsPerPage = $numberRows;
    }
    
	/** This method send the whole html code of the table to the browser. If the jQuery plugin DataTables
	 *  is activated then the javascript for that plugin will be added. Call this method if you
	 *  have finished your form layout.
     *  @param $directOutput If set to @b true (default) the table html will be directly send
     *                       to the browser. If set to @b false the html will be returned.
     *  @return If $directOutput is set to @b false this method will return the html code of the table.
	 */
    public function show($directOutput = true)
    {
        global $g_root_path, $gPreferences;
        
        if($this->datatables && is_object($this->htmlPage))
        {
            $datatablesOrder  = '';
            $datatablesHidden = '';
            $javascriptGroup  = '';
            $javascriptGroupFunction = '';
            
            $this->htmlPage->addJavascriptFile($g_root_path.'/adm_program/libs/datatables/jquery.datatables.min.js');
            $this->htmlPage->addCssFile(THEME_PATH.'/css/jquery.datatables.css');

            // set order columns
            if(count($this->orderColumns) > 0)
            {
                foreach($this->orderColumns as $columnNumber => $order)
                {
                    $datatablesOrder .= ', ['.$columnNumber.', "'.$order.'"]';
                }
                
                // remove leading comma from string
                $datatablesOrder = substr($datatablesOrder, 1);
                
                // grouped column must be first order column
                if($this->groupedColumn >= 0)
                {
                    $datatablesOrder = ' ['.$this->groupedColumn.', "asc"], '.$datatablesOrder;
                }
                
                $datatablesOrder = ' "order": ['.$datatablesOrder.'], ';
            }

            // hide columns
            if(count($this->hiddenColumns) > 0)
            {
                foreach($this->hiddenColumns as $columnNumber)
                {
                    $datatablesHidden .= ', { "visible":false, "targets":['.$columnNumber.']}';
                }
                
                // remove leading comma from string
                $datatablesHidden = substr($datatablesHidden, 1);
                
                $datatablesHidden = ' "columnDefs": ['.$datatablesHidden.'], ';
            }
            
            if($this->groupedColumn >= 0)
            {
                $javascriptGroup = ', 
                    "columnDefs": [
                        { "visible": false, "targets": '.$this->groupedColumn.' }
                    ],
                    "order": [[ '.$this->groupedColumn.', \'asc\' ]],
                    "drawCallback": function ( settings ) {
                        var api  = this.api();
                        var rows = api.rows( {page:\'current\'} ).nodes();
                        var last = null;
             
                        api.column('.$this->groupedColumn.', {page:\'current\'} ).data().each( function ( group, i ) {
                            if ( last !== group ) {
                                $(rows).eq( i ).before(
                                    \'<tr class="group admTableSubHeader"><td colspan="'.$this->columnCount.'">\'+group+\'</td></tr>\'
                                );
             
                                last = group;
                            }
                        } );
                    }';
                $javascriptGroupFunction = '
                    // Order by the grouping
                    $("#'.$this->id.' tbody").on( "click", "tr.group", function () {
                        var currentOrder = table.order()[0];
                        if ( currentOrder[0] === '.$this->groupedColumn.' && currentOrder[1] === "asc" ) {
                            table.order( [ '.$this->groupedColumn.', "desc" ] ).draw();
                        }
                        else {
                            table.order( [ '.$this->groupedColumn.', "asc" ] ).draw();
                        }
                    } );';                
            }

            $this->htmlPage->addJavascript('
                var table = $("#'.$this->id.'").DataTable( {
                    "pageLength": '.$this->rowsPerPage.','.
                    $datatablesHidden.
                    $datatablesOrder.'
                    "language": {"url": "'.$g_root_path.'/adm_program/libs/datatables/language/dataTables.'.$gPreferences['system_language'].'.lang"}
                    '.$javascriptGroup.'
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
?>