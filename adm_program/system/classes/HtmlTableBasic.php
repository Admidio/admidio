<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Create html tables
 *
 * This class creates html tables.
 * Create a table object, define the elements with optional attributes and pass your content.
 * Several methods allows you to set table rows, columns, header and footer element. Also you can define an array with column widths.
 * The class provides changing class in table rows of body elements using modulo.
 * You can define the class names and the row number for line change.
 * CSS classes are needed using this option for class change !
 * This class supports strings, arrays, bi dimensional arrays and associative arrays for creating the table content.
 *
 * **Code example**
 * ```
 * // Data array for example
 * $dataArray = array('Data 1', 'Data 2', 'Data 3');
 * ```
 *
 * **Code example**
 * ```
 * // Example without defining table head and table foot elements.
 * // Starting a row directly, all missing table elements are set automatically for semantic table.
 * // Create an table instance with optional table ID, table class.
 * $table = new HtmlTableBasic('Id_Example_1', 'tableClass');
 * // For each key => value a column is to be defined in a table row.
 * $table->addRow($dataArray);
 * // get validated table
 * echo $table->getHtmlTable();
 * ```
 *
 * **Code example**
 * ```
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
 * ```
 *
 * **Code example**
 * ```
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
 * ```
 */
class HtmlTableBasic extends HtmlElement
{
    /**
     * @var int String with border attribute and value of the table
     */
    protected $border;
    /**
     * @var array<int,string> Class names to design table rows
     */
    protected $rowClasses = array();
    /**
     * @var array<int,string> Array with values for the columns width
     */
    protected $columnsWidth = array();
    /**
     * @var bool Internal Flag for setted thead element
     */
    protected $thead = false;
    /**
     * @var bool Internal Flag for setted tfoot element
     */
    protected $tfoot = false;
    /**
     * @var bool Internal Flag for setted tbody element
     */
    protected $tbody = false;
    /**
     * @var int Counter for setted columns
     */
    protected $columnCount = 0;
    /**
     * @var int Counter for setted rows in body element
     */
    protected $rowCount = 0;

    /**
     * Constructor initializing all class variables
     * @param string $id     Id of the table
     * @param string $class  Class name of the table
     * @param int    $border Set the table border width
     */
    public function __construct($id = null, $class = null, $border = 0)
    {
        $this->border = $border;

        parent::__construct('table');

        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        if ($class !== null) {
            $this->addAttribute('class', $class);
        }

        if ($this->border > 0) {
            $this->addAttribute('border', $this->border);
        }
    }

    /**
     * Add Columns to current table row.
     * This method defines the columns for the current table row.
     * The data can be passed as string or array. Using Arrays, for each key/value a new column is set.
     * You can define an attribute for each column. If you need further attributes for the column first do the settings with addAttribute();
     * If all settings are done for the column use the addData(); to define your column content.
     * @param string|array         $data          Content for the column as string, or array
     * @param array<string,string> $arrAttributes Further attributes as array with key/value pairs
     * @param string               $columnType    Column element 'td' or 'th' (Default: 'td')
     */
    public function addColumn($data = '', array $arrAttributes = null, $columnType = 'td')
    {
        $this->addElement($columnType);

        if (array_key_exists($this->columnCount, $this->columnsWidth) && count($this->columnsWidth) > 0) {
            $this->addAttribute('style', 'width: ' . $this->columnsWidth[$this->columnCount] . ';');
        }

        // Check optional attributes in associative array and set all attributes
        if ($arrAttributes !== null) {
            $this->setAttributesFromArray($arrAttributes);
        }

        $this->addData($data);
        ++$this->columnCount;
    }

    /**
     * @param string|array $data Content for the table row as string, or array
     * @param string       $col  Column element 'td' or 'th' (Default: 'td')
     */
    private function addColumnsData($data = '', $col = 'td')
    {
        if ($data === '') {
            return;
        }

        if (is_array($data)) {
            foreach ($data as $column) {
                $this->addColumn($column, null, $col);
            }
        } else {
            $this->addColumn($data, null, $col);
        }
    }

    /**
     * Add new table row.
     * Starting the table directly with a row, the class automatically defines 'thead' and 'tfoot' element with an empty row.
     * The method checks if a row is already defined and must be closed first.
     * You can define 1 attribute/value pair for the row, calling the method. If you need further attributes for the new row, use method addAttribute(), before passing the content.
     * The element and attributes are stored in buffer first and will be parsed and written in the output string if the content is defined.
     * After all settings are done use addColumn(); to define your columns with content.
     * @param string|array         $data          Content for the table row as string, or array
     * @param array<string,string> $arrAttributes Further attributes as array with key/value pairs
     * @param string               $columnType    Column element 'td' or 'th' (Default: 'td')
     */
    public function addRow($data = '', array $arrAttributes = null, $columnType = 'td')
    {
        // Clear column counter
        $this->columnCount = 0;

        // If row is active we must close it first before starting new one
        if (in_array('tr', $this->arrParentElements, true)) {
            $this->closeParentElement('tr');
        }

        $this->addParentElement('tr');

        // Check optional attributes in associative array and set all attributes
        if ($arrAttributes !== null) {
            $this->setAttributesFromArray($arrAttributes);
        }

        if (count($this->rowClasses) === 0) {
            if (count($this->columnsWidth) === 0) {
                if ($data !== '') {
                    $this->addColumn($data, null, $columnType);
                    $this->closeParentElement('tr');
                }
            } else {
                $this->addColumnsData($data, $columnType);
            }
        } else {
            if ($this->tbody) {
                // Only allowed in body element of the table
                $rowClass = $this->rowClasses[$this->rowCount % count($this->rowClasses)];
                $this->addAttribute('class', $rowClass, 'tr');
            }

            $this->addColumnsData($data, $columnType);
        }

        // only increase rowCount if this is a data row and not the header
        if ($columnType === 'td') {
            ++$this->rowCount;
        }
    }

    /**
     * @param string       $element   Element (thead, tbody, tfoot)
     * @param string       $attribute Attribute
     * @param string       $value     Value of the attribute
     * @param string|array $data      Content for the element as string, or array
     * @param string       $col
     */
    private function addTableSection($element, $attribute = null, $value = null, $data = '', $col = 'td')
    {
        $this->addParentElement($element);

        $this->{$element} = true;

        if ($attribute !== null && $value !== null) {
            $this->addAttribute($attribute, $value);
        }

        if ($data !== '') {
            $this->addRow($data, null, $col);
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
    public function addTableBody($attribute = null, $value = null, $data = '', $col = 'td')
    {
        // always close tr if that was open before
        $this->closeParentElement('tr');

        if ($this->tfoot) {
            $this->closeParentElement('tfoot');
        }

        if ($this->thead) {
            $this->closeParentElement('thead');
        }

        $this->addTableSection('tbody', $attribute, $value, $data, $col);
    }

    /**
     * @par Define table footer
     * Please have a look at the description addRow(); and addColumn(); how you can define further attribute settings
     * @param string       $attribute Attribute
     * @param string       $value     Value of the attribute
     * @param string|array $data      Content for the element as string, or array
     * @param string       $col
     * @return bool Returns **false** if tfoot element is already set
     */
    public function addTableFooter($attribute = null, $value = null, $data = '', $col = 'td')
    {
        if ($this->thead && in_array('thead', $this->arrParentElements, true)) {
            $this->closeParentElement('thead');
        }

        // Check if table footer already exists
        if ($this->tfoot) {
            return false;
        }

        $this->closeParentElement('thead');

        $this->addTableSection('tfoot', $attribute, $value, $data, $col);

        return true;
    }

    /**
     * Define table header
     * Please have a look at the description addRow(); and addColumn(); how you can define further attribute settings
     * @param string       $attribute Attribute
     * @param string       $value     Value of the attribute
     * @param string|array $data      Content for the element as string, or array
     * @param string       $col
     * @return bool Returns **false** if thead element is already set
     */
    public function addTableHeader($attribute = null, $value = null, $data = '', $col = 'td')
    {
        // Check if table head already exists
        if ($this->thead) {
            return false;
        }

        $this->addTableSection('thead', $attribute, $value, $data, $col);

        return true;
    }

    /**
     * Get the parsed html table
     * @return string Returns the validated html table as string
     */
    public function getHtmlTable()
    {
        $this->closeParentElement('tr');
        $this->closeParentElement('tbody');

        return $this->getHtmlElement();
    }

    /**
     * In body elements you can use this option. You have to define class names.
     * @param array<int,string> $rowClasses Name of the standard class used for lineChange mode
     */
    public function setRowClasses(array $rowClasses)
    {
        $this->rowClasses = $rowClasses;
    }

    /**
     * Set a specific width for all columns of the table. This is useful if the automatically
     * that will be set by the browser doesn't fit your needs.
     * @param array<int,string> $columnsWidth Array with all width values of each column.
     *                                        Here you can set all valid CSS values e.g. '100%' or '300px'
     */
    public function setColumnsWidth(array $columnsWidth)
    {
        $this->columnsWidth = $columnsWidth;
    }

    /**
     * Set a specific width for one column of the table. This is useful if you have one column
     * that will not get a useful width automatically by the browser.
     * @param int    $column The column number where you want to set the width. The columns of the table starts with 1 (not 0).
     * @param string $width  The new width of the column. Here you can set all valid CSS values e.g. '100%' or '300px'
     */
    public function setColumnWidth($column, $width)
    {
        // internal datatable columns starts with 0
        $this->columnsWidth[$column - 1] = $width;
    }
}
