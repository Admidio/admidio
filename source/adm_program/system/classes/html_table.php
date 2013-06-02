<?php
/*****************************************************************************/
/** @class HtmlTable
 *  @brief  Create html tables
 * 
 *  This class creates html tables.
 *  Create a table object and pass your content.
 *  Several methods allows you to create html tables, just passing header, footer and
 *  body as array and set individual attributes for the table elements, or
 *  create tricky tables step by step with individual attribute settings.
 *  It is also possibe to define a row counter to change background colors .
 *  CSS classes are needed and the content must be passed as array, using this option for class change !
 *  This class supports strings, arrays, bi dimensional arrays and associative arrays for creating the table content.
 *  @par Notice
 *  Tables should be styled by CSS !
 *  Attributes, like 'align', 'bgcolor',... are worse style,
 *  and deprecated in HTML5. Please check the reference.
 *  @par Example data arrays
 *  @code
 *  $header = array('a', 'b', 'c');
 *  $footer = array('foo', 'bar', 'needle');
 *  // Example 2 dim. Array
 *  $content[] = array(1, 2, 3);
 *  $content[] = array(4, 5, 6);
 *  // Also assoc Arrays can be used
 *  $content[] = array('a'=> 7,'b'=> 8,'c'=> 9);
 *  // array with cols width
 *  $columnsWidth = array('33%', '33%', '33%');
 *  @endcode 
 *  @par Example 1: Create a simple table
 *  @code
 *  // create a table instance
 *  $table1 = new HtmlTable('id','class', 1);  // optional parameters( ID, class, border, line number for class change mode )
 *  // set optional attributes
 *  $table1->setTableWidth('600px');          // optional
 *  $table1->setColumnsWidth($columnsWidth);  // optional
 *  $table1->setHeadId('foo1');               // optional
 *  $table1->setHeadClass('bar1');            // optional
 *  $table1->setFootId('foo2');               // optional
 *  $table1->setFootClass('bar2');            // optional
 *  $table1->setBodyId('foo3');               // optional
 *  $table1->setBodyClass('bar3');            // optional
 *
 *  // pass contents and create a html table 
 *  $htmlTable1 = $table1->getHtmlTable($header, $footer, $content);
 *  echo $htmlTable1;
 *  @endcode
 *  @par Example 2: Create a html table step by step 
 *  Create a table using the methods @c setElement() , @c setAttribute() and @c setData().
 *  @code
 *  // create a table instance
 *  $table2 = new HtmlTable();
 *  // Set class change mode for each second row.
 *  // IMPORTANT: Using this mode, the rows must be passed as array !
 *  $table2->setClassChange('odd', 'even', 2);
 *  // set table attributes (optional). All attributes are supported.
 *  // It is recommended styling the table with CSS only.
 *  // However just an example
 *  $table2->setAttribute('style', 'width: 500px;', 'table');
 *  $table2->setAttribute('id', 'table2', 'table');
 *  $table2->setAttribute('summary', 'table2', 'table');
 *  $table2->setAttribute('border', '1', 'table');
 *  // set column width as array
 *  $table2->setColumnsWidth(array('10%', '10%', '80%'));
 *  // set head element for the table
 *  $table2->setElement('thead');
 *  // now set the attributes for the head element
 *  $table2->setAttribute('id', 'head');
 *  // and add the header as content array
 *  $table2->setData($header);
 *  // set the footer element
 *  $table2->setElement('tfoot');
 *  // we want to define a class for this element too
 *  $table2->setAttribute('class', 'footer');
 *  // add footer content
 *  $table2->setData($footer);
 *  // set the table body element
 *  $table2->setElement('tbody');
 *  // set body attributes
 *  $table2->setAttribute('class', 'tbody_1');
 *  // add  the body content
 *  $table2->setData($content);
 *  // several body elements are valid
 *  // set next body element with content in this case.
 *  // It is also possible by function to pass the content directly.
 *  $table2->setElement('tbody', $content);
 *  // set body attributes again
 *  $table2->setAttribute('class', 'tbody_2');
 *  $table2->setElement('tbody');
 *  // set body attributes
 *  $table2->setAttribute('class', 'tbody_3');
 *  // add a headline to full table width as string
 *  $table2->setElement('tr');
 *  $table2->setElement('th');
 *  $table2->setAttribute('class', 'head');
 *  $table2->setAttribute('colspan', '3');
 *  $table2->setData('headline');
 *  // add next row with columns array
 *  $table2->setElement('tr' , $content);
 *  // html table
 *  $htmlTable2 = $table2->getHtmlTable();
 *  echo $htmlTable2;
 *  @endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Author       : Thomas-RCV
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

final class HtmlTable {

    private $tableAttributes;            ///< String with attributes of the table
    private $tableId;                    ///< String with ID attribute and value of the table
    private $tableClass;                 ///< String with class attribute and name of the table
    private $tableWidth;                 ///< String with table width
    private $border;                     ///< String with border attribute and value of the table
    private $classChange;                ///< Integer value for class change mode for table rows.
    private $class_1;                    ///< Class name for standard design of table rows 
    private $class_2;                    ///< Class name for changed design of table rows 
    private $theadAttributes;            ///< String with all attributes defined for 'thead'
    private $theadId;                    ///< String with ID attribute and value of the 'thead' element
    private $theadClass;                 ///< String with class attribute and name of the 'thead' element
    private $theadElement;               ///< String with the created 'thead' content
    private $tfootElement;               ///< String with the created 'tfoot' content
    private $tbodyElement;               ///< String with the created 'tbody' content
    private $tbodyFlag;                  ///< Flag for several body elements. It is need to control the content, if more mody elements are defined in the table
    private $storedHtmlBodies;           ///< Buffer for defined body content
    private $tfootAttributes;            ///< String with all attributes defined for 'tfoot'
    private $tbodyAttributes;            ///< String with all attributes defined for first 'tbody'
    private $tbodyId;                    ///< String with ID attribute and value of the first table body
    private $tbodyClass;                 ///< String with class attribute and name of the first table body
    private $tfootId;                    ///< String with ID attribute and value of the 'tfoot' element
    private $tfootClass;                 ///< String with class attribute and name of the 'tfoot' element
    private $changeClass;                ///< Class name for the next table row using class change mode
    private $columnsWidth;               ///< Array with values for the columns width
    private $currentElement;             ///< Internal pointer of the actual table element
    private $currentElementAttribute;    ///< Attributes of the current table element
    private $anchor;                     ///< Variable with current anchor value. It is needed to know the position in the table main elements ( thead, tfoot, tbody ) creating the table manually
    private $arrAnchors;                 ///< Array with stored anchor values to avoid, e.g. double 'thead' could be set. This is not allowed
    private $refElements;                ///< Array with valid element definitions for html tables

    /**
     * Constructor initializing all class variables
     * 
     * @param $id Id of the table
     * @param $class Class name of the table
     * @param $border Set table border
     * @param $classChange Set linecounter for classChange mode
     */
    public function __construct($id = null, $class = null, $border = 0, $classChange = null)
    {
        $this->border = (is_numeric($border))? $border : 0;
        $this->classChange = ($classChange != null && is_numeric($classChange))? $classChange : null;
        $this->columsWidth = array();
        $this->theadElement = '';
        $this->tfootElement = '';
        $this->tbodyElement = '';
        $this->storedHtmlBodies = '';
        $this->tbodyFlag = false;
        $this->tableAttributes = '';
        $this->theadAttributes = '';
        $this->tfootAttributes = '';
        $this->tbodyAttributes = '';
        $this->currentElement = '';
        $this->currentElementAttribute = '';
        $this->anchor = '';
        $this->arrAnchors = array();
        $this->refElements = array('table', 'thead', 'tfoot', 'tbody', 'th', 'tr', 'td');
        $this->changeclass = '';

        if ($id != null) 
        {
            $this->tableId = ' id="' . $id . '"';
        } 
        if ($class != null) 
        {
            $this->tableClass = ' class="' . $class . '"';
        } 
    } 

    /**
     * Set classChange mode
     * 
     * @param $class_1 Name of the standard class used for classChange mode
     * @param $class_2 Name of the change class used for classChange mode
     * @param $integer Number of the line that is changed to Class_2
     */
    public function setClassChange($class_1 = '', $class_2 = '', $integer)
    {
        $this->classChange = ($integer != null && is_numeric($integer))? $integer : null;
        $this->class_1 = $class_1;
        $this->class_2 = $class_2;
    } 

    /**
     * Set table width
     * 
     * @param $string Width of table for example '600px' or '100%'
     */
    public function setTableWidth($string)
    {
        $this->tableWidth = ' style="width:' . $string . '"';
    } 

    /**
     * Set columns width
     * 
     * @param $array Array with values for each column width
     */
    public function setColumnsWidth($array)
    {
        foreach ($array as $column) 
        {
            $this->columnsWidth[] = ($column != '') ? ' style="width: ' . $column . ';"' : '';
        } 
    } 

    /**
     * Set table class
     * 
     * @param $class Class name of table
     */
    public function setTableClass($name)
    {
        $this->tableClass = ' class="' . $class . '"';
    } 

    /**
     * Set  table id
     * 
     * @param $id Id of table
     */
    public function setTableId($value)
    {
        $this->tableId = ' id="' . $id . '"';
    } 

    /**
     * Set thead class name
     * 
     * @param $class class name of 'thead' element
     */
    public function setHeadClass($name)
    {
        $this->theadClass = ' class="' . $class . '"';
    } 

    /**
     * Setter for theadId 
     * 
     * @param $id Id of 'thead' element
     */
    public function setHeadId($value)
    {
        $this->theadId = ' id="' . $id . '"';
    } 

    /**
     * Setter for tbody class name
     * 
     * @param  $class Class name for body element
     */
    public function setBodyClass($name)
    {
        $this->tbodyClass = ' class="' . $class . '"';
    } 

    /**
     * Setter for tbody id 
     * 
     * @param $id Id of body element
     */
    public function setBodyId($value)
    {
        $this->tbodyId = ' id="' . $id . '"';
    } 

    /**
     * Setter for tfootClass name
     * 
     * @param $class Class name of 'tfoot' element
     */
    public function setFootClass($name)
    {
        $this->tfootClass = ' class="' . $class . '"';
    } 

    /**
     * Setter for tfootId name
     * 
     * @param $id Id of 'tfoot' element
     */
    public function setFootId($value)
    {
        $this->tfootId = ' id="' . $id . '"';
    } 

    /**
     * Helper function that creates attributes
     * 
     * @param $attribute Attribute name
     * @param $value Value for the attribute
     * @return Returns validated string
     */
    private function setElementAttribute($attribute, $string)
    {
        $validatedAttribute = ' '. $attribute .'="' . $string . '"';
        return $validatedAttribute;
    } 

    /**
     * Helper function checks the current element
     * 
     * @param $element
     * @return Returns FALSE, if trying to set attributes to an element that is actually not selected!
     */
    private function checkElement($element)
    {
        if($this->currentElement == $element) 
        {
            return true;
        } 
        return false;
    } 

    /**
     * Set new table element
     * 
     * @param $tableElement Tags used in tables as string (thead, tfoot, tbody, tr, th ,td )
     * @param $data Values for the table content can be passed as string, array, bidimensional Array and assoc. Array. ( Default: no data )
     */
    public function setElement($tableElement, $data = '')
    {
        if(in_array($tableElement, $this->refElements)) 
        {
            // If currently no anchor position in table is set, then set  new anchor
            if($this->anchor == null) 
            {
                switch ($tableElement) 
                {
                    case 'thead':
                    case 'tfoot': 
                        // First save tag in array, because it is only allowed once
                        $this->arrAnchors[] = $tableElement; 
                        // Mark as actual position in table
                        $this->anchor = $tableElement; 
                        // Set current element
                        $this->currentElement = $tableElement;
                        break;

                    case 'tbody': 
                        // Mark as actual position in table
                        $this->anchor = $tableElement; 
                        // Set current element
                        $this->currentElement = $tableElement; 
                        // count first body
                        $this->tbodyFlag = true;
                        break;
                    
                    case 'tr':
                    case 'th':
                    case 'td':
                        // Mark as actual position in table
                        $this->anchor = 'tbody';
                        $this->currentElement = 'tbody';
                        break;
                } 
            } 

            $checkedElement = $this->checkElement($tableElement); 
            // if main element tag already exists and new main element tag is set
            if($this->anchor != $tableElement && $this->anchor != null 
            || $this->anchor == 'tbody' 
            && $this->anchor == $tableElement) 
            {
                switch ($tableElement) 
                {
                    case 'thead':
                    case 'tfoot': 
                        // if not defined in current table object so far
                        if(!in_array($tableElement, $this->arrAnchors)) 
                        {
                            // push main element tag to reference array
                            $this->arrAnchors .= $tableElement; 
                            // and set new main element
                            $this->anchor = $tableElement; 
                            // and also set as current element
                            $this->currentElement = $tableElement;
                        } 
                        break;

                    case 'tbody': 
                        // Several body elements in table are valid
                        // set new main element and check if body element already exists, otherwise set body flag
                        If(!$this->tbodyFlag) 
                        {
                            $this->tbodyFlag = true;
                        } 
                        else
                        {
                            // write body string
                            $this->storedHtmlBodies .= (strlen($this->tbodyAttributes) == null) ? '<tbody ' . $this->tbodyId . $this->tbodyClass . '>' : '<tbody ' . $this->tbodyAttributes . '>';
                            $this->storedHtmlBodies .= $this->tbodyElement . '</tbody>'; 
                            // clear body attributes
                            $this->tbodyAttributes = ''; 
                            // clear body element and clear flag
                            $this->tbodyElement = '';
                            $this->tbodyFlag = true;
                        } 

                        $this->anchor = $tableElement; 
                        // set current element
                        $this->currentElement = $tableElement;
                        break;

                    case 'tr':
                        $this->{$this->anchor . 'Element'} .= ($this->currentElement == 'td' ) ? '</tr>' : '';
                        $this->currentElement = $tableElement;
                        break;

                    case 'th':
                    case 'td':
                    
                        if(!$checkedElement)
                        {
                            $this->{$this->anchor . 'Element'} .= ($this->currentElement == 'tr') ? '<tr' .$this->currentElementAttribute. '>' : '';
                            $this->currentElementAttribute = '';
                        }
                        
                        $this->currentElement = $tableElement;
                } 
            } 
            if($data != '') 
            {
                $this->setData($data);
            } 
        } 
        return $this;
    } 

    /**
     * Setter for attributes of the selected table element
     * 
     * @param $attribute Attribute 
     * @param $value Value of the attribute
     * @param $tableElement Element of the table
     */
    public function setAttribute($attribute, $value, $tableElement = null)
    {
        If($tableElement == null) 
        {
            $tableElement = $this->currentElement;
        } 

        switch ($tableElement) 
        {
            case 'table':

                $this->tableAttributes .= $this->setElementAttribute($attribute, $value);
                break;

            case 'thead':

                $this->theadAttributes .= $this->setElementAttribute($attribute, $value);
                break;

            case 'tfoot':

                $this->tfootAttributes .= $this->setElementAttribute($attribute, $value);
                break;

            case 'tbody':

                $this->tbodyAttributes .= $this->setElementAttribute($attribute, $value);
                break;

            case 'tr':
            case 'th':
            case 'td': 
                // check whether current element is actual
                $checkedElement = $this->checkElement($tableElement);
                if($checkedElement) 
                {
                    $this->currentElementAttribute .= $this->setElementAttribute($attribute, $value);
                } 

                break;
        } 
        return $this;
    } 

    /**
     *  Set data to current element
     * 
     *  @param $data Content for the element as string, or array
     *  @return Returns FALSE is no data is given
     */
    public function setData($data)
    {
        $counter = 0;

        if($data != '') 
        {
            // input is a string
            if(!is_array($data)) 
            {
                switch ($this->currentElement) 
                {   
                    case 'thead':
                    case 'tfoot':
                    case 'tbody':
                        if ($this->classChange != null) 
                        {
                            $this->changeclass = (($counter % $this->classChange) == 0) ? $this->class_1 : $this->class_2;
                            $counter++;
                            $this-> {$this->anchor . 'Element'} .= '<tr class="' . $this->changeclass . '">';
                        }
                        else
                        {
                            $this-> {$this->anchor . 'Element'} .= '<tr' . $this->currentElementAttribute . '>';
                        }
                        // initialize attribute variable because it is already set
                        $this->currentElementAttribute = '';
                        break;
                    
                    case 'tr':

                        break;
                    
                    case 'th':
                        $this-> {$this->anchor . 'Element'} .= '<th' . $this->currentElementAttribute . '>' . $data . '</th>';
                        break;

                    case 'td':
                        $this-> {$this->anchor . 'Element'} .= '<td' . $this->currentElementAttribute . '>' . $data . '</td>';
                        break;
                } 
            } 
            else 
            {
                // validate the content array
               $this-> {$this->anchor . 'Element'} .= $this->readData($data);
            } 
            // initialize attribute variable for columns, because configuration is set
            $this->currentElementAttribute = '';
        } 
        return $this;
    } 

    /**
     *  Prepare html of data set from content arrays
     *  param: $data Array with content for the table columns
     */
    private function readData($data)
    {
        if(isset($data)) 
        {
            $buffer = '';
            $ColumnTag = ($this->currentElement == 'th') ? 'th' : 'td';
            $count = 0; 
            // count entries
            $numberEntries = count($data); 
            // count 1 level deeper.
            $nextLevel = count($data[0]);
            if($nextLevel > 1) 
            {
                for ($i = 0; $i < count($data); $i++) 
                {
                    if ($this->classChange != null) 
                    {
                        $this->changeclass = (($count % $this->classChange) == 0) ? $this->class_1 : $this->class_2;
                        $count++;
                    } 

                    $buffer .= '<tr class="' . $this->changeclass . '">';

                    foreach ($data[$i] as $col => $value) 
                    {
                        $buffer .= '<' . $ColumnTag . $this->currentElementAttribute . '>' . $value . '</' . $ColumnTag . '>';
                    } 
                    $buffer .= '</tr>';
                } 
            } 
            else 
            {
                // single array
                $counter = count($this->columnsWidth);
                $j = 0;
                $buffer .= '<tr class="' . $this->changeclass . '">';
                foreach ($data as $col) 
                {
                    $buffer .= '<' . $ColumnTag . $this->currentElementAttribute . ' ' . $this->columnsWidth[$j] . '>' . $col . '</' . $ColumnTag . '>';
                    if($j <= $counter) 
                    {
                        $j++;
                    } 
                } 
                $buffer .= '</tr>';
            } 
            return $buffer;
        } 

        return false;
    } 

    /**
     * Create the table
     * 
     * @param $thead Array with head content (Default: null)
     * @param $tfoot Array with footer content (Default: null)
     * @param $data Array with body content (Default: null)
     * @return Returns the validated html table as string
     */
    public function getHtmlTable($thead = null, $tfoot = null, $data = null)
    {
        $borderFlag = ($this->border == 0) ? '' : 'border = "1"';
        $countData = 0;
        $table = (strlen($this->tableAttributes) == null) ? '<table' . $this->tableWidth . $this->tableId . $this->tableClass .  $borderFlag . '>': '<table ' . $this->tableAttributes . ' ' . $borderFlag . '>'; 
        // Header
        $table .= (strlen($this->theadAttributes) == null) ? '<thead' . $this->theadId . $this->theadClass . '>' : '<thead ' . $this->theadAttributes . '>';

        if($thead != null) 
        {
            $table .= '<tr>';
            foreach($thead as $content) 
            {
                $style = $this->columnsWidth[$countData];
                $table .= '<td' . $style . '>' . $content . '</td>';
                $countData++;
            } 
            $table .= '</tr>';
        } 
        else 
        {
            $table .= ($this->theadElement == null) ? '<tr><td>' .$this->theadElement. '</td></tr>' : $this->theadElement .'</tr>';
        } 
        // End of table head
        $table .= "</thead>"; 
        // Footer
        $table .= (strlen($this->tfootAttributes) == null) ? '<tfoot' . $this->tfootId . $this->tfootClass . '>' : '<tfoot ' . $this->tfootAttributes . '>';
        if($tfoot != null) 
        {
            $table .= '<tr>';
            foreach($tfoot as $content) 
            {
                $table .= '<td>' . $content . '</td>';
            } 
            $table .= '</tr>';
        } 
        else 
        {
            $table .= ($this->tfootElement == null) ? '<tr><td>' .$this->tfootElement. '</td></tr>' : $this->tfootElement .'</tr>';
        } 
        $table .= '</tfoot>';
        $table .= (strlen($this->tbodyAttributes) == null) ? '<tbody' . $this->tbodyId . $this->tbodyClass . '>' : $this->storedHtmlBodies . '<tbody ' . $this->tbodyAttributes . '>'; 
        // Table body
        if($data != null) 
        {
            $table .= $this->readData($data);
        } 
        else 
        {
            $table .= ($this->currentElement == 'tr') ? $this->tbodyElement  : $this->tbodyElement . '</tr>';
        }
        $table .= ($this->tbodyElement == null) ? '<tr><td>' .$this->tbodyElement. '</td></tr>' : ''; 
        $table .= '</tbody></table>';

        return $table;
    } 
} 

?>
