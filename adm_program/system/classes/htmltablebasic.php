<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class HtmlTableBasic
 * @brief  Create html tables
 *
 * This class creates html tables.
 * Create a table object, define the elements with optional attributes and pass your content.
 * Several methods allows you to set table rows, columns, header and footer element. Also you can define an array with column widths.
 * The class provides changing class in table rows of body elements using modulo.
 * You can define the class names and the row number for line change.
 * CSS classes are needed using this option for class change !
 * This class supports strings, arrays, bi dimensional arrays and associative arrays for creating the table content.
 * @par Notice
 * Tables should be styled by CSS !
 * Attributes, like 'align', 'bgcolor',... are worse style,
 * and deprecated in HTML5. Please check the reference.
 * @par Data array for example
 * @code
 * $dataArray = array('Data 1', 'Data 2', 'Data 3');
 * @endcode
 * @par Example_1
 * @code
 * // Example without defining table head and table foot elements.
 * // Starting a row directly, all missing table elements are set automatically for semantic table.
 * // Create an table instance with optional table ID, table class.
 * $table = new HtmlTableBasic('Id_Example_1', 'tableClass');
 * // For each key => value a column is to be defined in a table row.
 * $table->addRow($dataArray);
 * // get validated table
 * echo $table->getHtmlTable();
 * @endcode
 * @par Example_2
 * @code
 * // Create an table instance with optional table ID, table class and border
 * $table = new HtmlTableBasic('Id_Example_2', 'tableClass', 1);
 * // we can also set further attributes for the table
 * $table->addAttribute('style', 'width: 100%;');
 * $table->addAttribute('summary', 'Example');
 * // add table header with class attribute and a column as string
 * $table->addTableHeader('class', 'name', 'columntext', 'th'); // $col paremeter 'th' is set by dafault to 'td'
 * // add next row to the header
 * $table->addRow('... some more text ...'); // optional parameters ( $content, $attribute, $value, $col = 'td')
 * // Third row we can also pass single arrays, bidimensional arrays, and assoc. arrays
 * // For each key => value a column is to be defined in a table row
 * $table->addRow($dataArray);
 * // add the table footer
 * $table->addTableFooter('class', 'foot', 'Licensed by Admidio');
 * // add a body element
 * $table->addTableBody('class', 'body', $dataArray);
 * // also we can set further body elements
 * $table->addTableBody('class', 'nextBody', $dataArray);
 * // in this body elemtent for example, we want to define the cols in a table row programmatically
 * // define a new row
 * $table->addRow(); // no data and no attributes for this row
 * $table->addColumn('col1');
 * $table->addColumn('col2', array('class' => 'secondColumn')); // this col has a class attribute
 * $table->addColumn('col3');
 * // also we can pass our Array at the end
 * $table->addColumn($dataArray);
 * // get validated table
 * echo $table->getHtmlTable();
 * @endcode
 * @par Example 3
 * @code
 * // Example with fixed columns width and changing classes for rows in body element and table border
 * $table = new HtmlTableBasic('Id_Example_3', 'tableClass', 1);
 * // Set table width to 600px. Ok, we should do this in the class or id in CSS ! However,...
 * $table->addAttribute('style', 'width: 600px;');
 * // Define columms width as array
 * $table->setColumnsWidth(array('20%', '20%', '60%'));
 * // We also want to have changing class in every 3rd table row in the table body
 * $table->setClassChange('class_1', 'class_2', 3); // Parameters: class names and integer for the line ( Default: 2 )
 * // Define a table header with class="head" and define a column string (arrays are also possible)
 * // and Set a header element for the column (Default: 'td')
 * $table->addTableHeader('class', 'head', 'Headline_1', 'th');
 * // 2 more columns ...
 * $table->addColumn('Headline_2', null, 'th'); // no attribute/value in this example
 * $table->addColumn('Headline_3', null, 'th'); // no attribute/value in this example
 * // Define the footer with a string in center position
 * $table->addTableFooter();
 * // First mention that we do not want to have fixed columns in the footer. So we clear the array and set the text to center positon!
 * $table->setColumnsWidth(array());
 * // Define a new table row
 * $table->addRow();
 * // Add the column with colspan attribute
 * $table->addColumn('', array('colspan' => '3')); // no data here, because first do the settings and after finishend pass the content !
 * // Define center position for the text
 * $table->addAttribute('align', 'center'); // ok, it is worse style!
 * // Now we can set the data if all settings are done!
 * $table->addData('Tablefooter');
 * // Now set the body element of the table
 * // Remember we deleted the columns width array, so we need to set it again
 * $table->setColumnsWidth(array('20%', '20%', '60%'));
 * // Define a table row with array or string for first column
 * $table->addTableBody('class', 'body', $dataArray);
 * // Some more rows with changeclass mode in body element
 * $table->addRow($dataArray);
 * $table->addRow($dataArray);
 * $table->addRow($dataArray);
 * $table->addRow($dataArray);
 * echo $table->getHtmlTable();
 * @endcode
 */
class HtmlTableBasic extends HtmlElement {
    protected $border;                   ///< String with border attribute and value of the table
    protected $lineChange;               ///< Integer value for class change mode for table rows.
    protected $class_1;                  ///< Class name for standard design of table rows
    protected $class_2;                  ///< Class name for changed design of table rows
    protected $changeClass;              ///< Class name for the next table row using class change mode
    protected $columnsWidth;             ///< Array with values for the columns width
    protected $thead;                    ///< Internal Flag for setted thead element
    protected $tfoot;                    ///< Internal Flag for setted tfoot element
    protected $tbody;                    ///< Internal Flag for setted tbody element
    protected $columnCount;              ///< Counter for setted columns
    protected $rowCount;                 ///< Counter for setted rows in body element

    /**
     * Constructor initializing all class variables
     * @param string $id     Id of the table
     * @param string $class  Class name of the table
     * @param int    $border Set table border
     */
    public function __construct($id = '', $class = '', $border = 0)
    {
        $this->border       = (is_numeric($border)) ? $border : 0;
        $this->lineChange   = '';
        $this->changeClass  = '';
        $this->columnsWidth = array();
        $this->thead        = -1;
        $this->tfoot        = -1;
        $this->tbody        = -1;
        $this->columnCount  = 0;
        $this->rowCount     = 0;

        parent::__construct('table', '', '', true);

        if($id !== '')
        {
            $this->addAttribute('id', $id);
        }

        if($class !== '')
        {
            $this->addAttribute('class', $class);
        }

        if($border == 1)
        {
            $this->addAttribute('border', '1');
        }
    }

    /**
     * Add Columns to current table row.
     * This method defines the columns for the current table row.
     * The data can be passed as string or array. Using Arrays, for each key/value a new column is set.
     * You can define an attribute for each column. If you need further attributes for the column first do the settings with addAttribute();
     * If all settings are done for the column use the addData(); to define your column content.
     * @param string|array $data          Content for the column as string, or array
     * @param array        $arrAttributes Further attributes as array with key/value pairs
     * @param string       $col           Column element 'td' or 'th' (Default: 'td')
     */
    public function addColumn($data = '', $arrAttributes = null, $col = 'td')
    {
        if($col === 'td' || $col === 'th')
        {
            $this->addElement($col);
        }

        if(!empty($this->columnsWidth) && isset($this->columnsWidth[$this->columnCount]))
        {
            $this->addAttribute('style', 'width:' . $this->columnsWidth[$this->columnCount].';');
        }

        // Check optional attributes in associative array and set all attributes
        if($arrAttributes !== null && is_array($arrAttributes))
        {
            $this->setAttributesFromArray($arrAttributes);
        }

        $this->addData($data);
        ++$this->columnCount;
    }

    /**
     * Add new table row.
     * Starting the table directly with a row, the class automatically defines 'thead' and 'tfoot' element with an empty row.
     * The method checks if a row is already defined and must be closed first.
     * You can define 1 attribute/value pair for the row, calling the method. If you need further attributes for the new row, use method addAttribute(), before passing the content.
     * The element and attributes are stored in buffer first and will be parsed and written in the output string if the content is defined.
     * After all settings are done use addColumn(); to define your columns with content.
     * @param string|array $data          Content for the table row as string, or array
     * @param array        $arrAttributes Further attributes as array with key/value pairs
     * @param string       $col           Column element 'td' or 'th' (Default: 'td')
     */
    public function addRow($data = '', $arrAttributes = null, $col = 'td')
    {
        // Clear column counter
        $this->columnCount = 0;

        // If row is active we must close it first before starting new one
        if(in_array('tr', $this->arrParentElements, true))
        {
            $this->closeParentElement('tr');
        }

        if($this->lineChange === '' && empty($this->columnsWidth))
        {
            $this->addParentElement('tr');

            // Check optional attributes in associative array and set all attributes
            if($arrAttributes !== null && is_array($arrAttributes))
            {
                $this->setAttributesFromArray($arrAttributes);
            }

            if($data !== '')
            {
                $this->addColumn($data, null, $col);
                $this->closeParentElement('tr');
            }

        }
        elseif($this->lineChange === '' && !empty($this->columnsWidth))
        {
            $this->addParentElement('tr');

            // Check optional attributes in associative array and set all attributes
            if($arrAttributes != null && is_array($arrAttributes))
            {
                $this->setAttributesFromArray($arrAttributes);
            }

            if($data !== '')
            {
                if(is_array($data))
                {
                    foreach($data as $column)
                    {
                        $this->addColumn($column, null, $col);
                    }
                }
                else
                {
                    // String
                    $this->addColumn($data, null, $col);
                }
            }
        }
        elseif($this->lineChange !== '' && empty($this->columnsWidth))
        {
            $this->addParentElement('tr');

            // Check optional attributes in associative array and set all attributes
            if($arrAttributes != null && is_array($arrAttributes))
            {
                $this->setAttributesFromArray($arrAttributes);
            }

            if($this->tbody == 1)
            {
                // Only allowed in body element of the table
                if($this->rowCount % $this->lineChange == 0)
                {
                    $this->changeClass = $this->class_1;
                }
                else
                {
                    $this->changeClass = $this->class_2;
                }
                $modulo = $this->changeClass;
                $this->addAttribute('class', $modulo, 'tr');
            }

            if($data !== '')
            {
                if(is_array($data))
                {
                    foreach($data as $column)
                    {
                        $this->addColumn($column, null, $col);
                    }
                }
                else
                {
                        $this->addColumn($data, null, $col);
                }
            }
        }
        else
        {
            $this->addParentElement('tr');

            // Check optional attributes in associative array and set all attributes
            if($arrAttributes != null && is_array($arrAttributes))
            {
                $this->setAttributesFromArray($arrAttributes);
            }

            if($this->tbody == 1)
            {
                // Only allowed in body element of the table
                if($this->rowCount % $this->lineChange == 0)
                {
                    $this->changeClass = $this->class_1;
                }
                else
                {
                    $this->changeClass = $this->class_2;
                }
                $modulo = $this->changeClass;
                $this->addAttribute('class', $modulo, 'tr');
            }

            if($data !== '')
            {
                if(is_array($data))
                {
                    foreach($data as $column)
                    {
                        $this->addColumn($column, null, $col);
                    }
                }
                else
                {
                    // String
                    $this->addColumn($data, null, $col);
                }
            }
        }

        // only increase rowcount if this is a data row and not the header
        if($col === 'td')
        {
            ++$this->rowCount;
        }
    }

    /**
     * Define table body.
     * Please have a look at the description addRow(); and addColumn(); how you can define further attribute settings
     * @param string       $attribute Attribute
     * @param string       $value     Value of the attribute
     * @param string|array $data      Content for the element as string, or array
     * @param string       $col
     */
    public function addTableBody($attribute = '', $value = '', $data = '', $col = 'td')
    {
        if($this->tfoot != -1 && in_array('tfoot', $this->arrParentElements, true));
        {
            $this->closeParentElement('tr');
        }

        if($this->tfoot == 1)
        {
            $this->closeParentElement('tfoot');
        }

        if($this->thead == 1)
        {
            $this->closeParentElement('thead');
        }

        $this->addParentElement('tbody');
        $this->tbody = 1;
        if($attribute !== '' && $value !== '')
        {
            $this->addAttribute($attribute, $value);
        }

        if($data !== '')
        {
            $this->addRow($data, null, $col);
        }
    }

    /**
     * @par Define table footer
     * Please have a look at the description addRow(); and addColumn(); how you can define further attribute settings
     * @param string       $attribute Attribute
     * @param string       $value     Value of the attribute
     * @param string|array $data      Content for the element as string, or array
     * @param string       $col
     * @return bool Returns @b false if tfoot element is already set
     */
    public function addTableFooter($attribute = '', $value = '', $data = '', $col = 'td')
    {
        if($this->thead != -1 && in_array('thead', $this->arrParentElements, true));
        {
            $this->closeParentElement('thead');
        }
        // Check if table footer already exists
        if($this->tfoot != 1)
        {
            $this->closeParentElement('thead');
            $this->addParentElement('tfoot');
            $this->tfoot = 1;

            if($attribute !== '' && $value !== '')
            {
                $this->addAttribute($attribute, $value);
            }

            if($data !== '')
            {
                $this->addRow($data, null, $col);
            }
            return true;
        }
        return false;
    }

    /**
     * Define table header
     * Please have a look at the description addRow(); and addColumn(); how you can define further attribute settings
     * @param string       $attribute Attribute
     * @param string       $value     Value of the attribute
     * @param string|array $data      Content for the element as string, or array
     * @param string       $col
     * @return bool Returns @b false if thead element is already set
     */
    public function addTableHeader($attribute = '', $value = '', $data = '', $col = 'td')
    {
        // Check if table head already exists
        if($this->thead != 1)
        {
            $this->addParentElement('thead');
            $this->thead = 1;

            if($attribute !== '' && $value !== '')
            {
                $this->addAttribute($attribute, $value);
            }

            if($data !== '')
            {
                $this->addRow($data, null, $col);
            }
            return true;
        }
        return false;
    }

    /**
     * Get the parsed html table
     * @return string Returns the validated html table as string
     */
    public function getHtmlTable()
    {
        $this->closeParentElement('tr');
        $this->closeParentElement('tbody');
        $table = '<div class="table-responsive">'.$this->getHtmlElement().'</div>';
        return $table;
    }

    /**
     * @par Set line Change mode
     * In body elements you can use this option. You have to define two class names and a counter as integer value.
     * The first class name is the standard class and the second name is the class used if the class is changed regarding the counter.
     * As default value, every second row is to be changed.
     *
     * @param string $class_1 Name of the standard class used for lineChange mode
     * @param string $class_2 Name of the change class used for lineChange mode
     * @param int    $line    Number of the line that is changed to Class_2 (Default: 2)
     * @return void|false
     */
    public function setClassChange($class_1 = '', $class_2 = '', $line = 2)
    {
        if(is_numeric($line))
        {
            $this->lineChange = $line;
        }
        else
        {
            return false;
        }

        $this->class_1 = $class_1;
        $this->class_2 = $class_2;
    }

    /**
     * Set a specific width for all columns of the table. This is useful if the automatically
     * that will be set by the browser doesn't fit your needs.
     * @param array $array Array with all width values of each column. Here you can set all valid CSS values e.g. '100%' or '300px'
     */
    public function setColumnsWidth($array)
    {
        if(is_array($array))
        {
            foreach ($array as $column)
            {
                if($column !== '')
                {
                    $this->columnsWidth[] = $column;
                }
                else
                {
                    $this->columnsWidth[] = '';
                }
            }
        }
    }

    /**
     * Set a specific width for one column of the table. This is useful if you have one column
     * that will not get a useful width automatically by the browser.
     * @param int    $column The column number where you want to set the width. The columns of the table starts with 1 (not 0).
     * @param string $width  The new width of the column. Here you can set all valid CSS values e.g. '100%' or '300px'
     */
    public function setColumnWidth($column, $width)
    {
        if($column > 0 && $width !== '')
        {
            // internal datatable columns starts with 0
            $this->columnsWidth[$column-1] = $width;
        }
    }
}
