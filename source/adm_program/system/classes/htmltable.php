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
    protected $columnAlign;    ///< Array with entry for each column with the align of that column. Values are @b right, @b left or @b center

    /** Constructor creates the table element
     *  @param $id    Id of the table
     *  @param $class Optional an additional css classname. The class @b admTable
     *                is set as default and need not set with this parameter.
     */
    public function __construct($id, $class = '')
    {
        if(strlen($class) == 0)
        {
            $class = 'admTable';
        }

        parent::__construct($id, $class);
        $this->addAttribute('cellspacing', '0');
    }

    /** Adds a complete row with all columns to the table. This will be the column heading row.
     *  Each value of the array represents the heading text for each column.
     *  @param $arrayRowValues Array with the values for each column.
     *  @param $id             Optional set an unique id for the column.
     */
    public function addRowHeadingByArray($arrayColumnValues, $id = null)
    {
        $attributes = array('class' => 'admTableRowHeading');
        
        // set an id to the column
        if($id != null)
        {
            $attributes['id'] = $id;
        }

        $this->addTableHeader();
        $this->addRow(null, $attributes);
        
        // now add each column to the row
        foreach($arrayColumnValues as $key => $value)
        {
            if(is_array($this->columnAlign))
            {
                $this->addColumn($value, 'style', 'text-align: '.$this->columnAlign[$key], 'th');
            }
            else
            {
                $this->addColumn($value, '', '', 'th');
            }
        }
    }

    /** Adds a complete row with all columns to the table. Each column element will be a
     *  value of the array parameter.
     *  @param $arrayRowValues Array with the values for each column.
     *  @param $id             Optional set an unique id for the column.
     */
    public function addRowByArray($arrayColumnValues, $id = null)
    {
        $attributes = array('class' => 'admTableRow');
        
        // set an id to the column
        if($id != null)
        {
            $attributes['id'] = $id;
        }
        
        $this->addRow(null, $attributes);
        
        // now add each column to the row
        foreach($arrayColumnValues as $key => $value)
        {
            if(is_array($this->columnAlign))
            {
                $this->addColumn($value, 'style', 'text-align: '.$this->columnAlign[$key], 'td');
            }
            else
            {
                $this->addColumn($value, '', '', 'td');
            }
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
    
	/** This method send the whole html code of the table to the browser. Call this method
	 *  if you have finished your form layout.
     *  @param $directOutput If set to @b true (default) the table html will be directly send
     *                       to the browser. If set to @b false the html will be returned.
     *  @return If $directOutput is set to @b false this method will return the html code of the table.
	 */
    public function show($directOutput = true)
    {
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