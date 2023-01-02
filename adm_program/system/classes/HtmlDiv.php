<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Create html div elements
 *
 * This class creates html div elements.
 * Create an instance of an div element and nest the inline elements.
 * The class supports nesting of several div elements and allows you to configure all attributes programatically.
 * The parsed div object with inline elements is returned as string.
 *
 * **Code example**
 * ```
 * // Creating a div element
 *
 * $testArray = array('Test_1', 'Test_2','Test_3');
 * // Get the Instance for a new division element
 * $div = new HtmlDiv('ID_Wrapper', 'Class_Wrapper');
 * // add a headline
 * $div->addInline('h1', '', '', 'Headline');
 * // add a paragraph
 * $div->addInline('p', 'ID_P', 'CLASS_TEXT', 'This is a demo of a pargraph element in the division block');
 * // add a paragraph
 * $div->addInline('p', '', 'CLASS_TEXT_ARRAY', $testArray);
 * // very often you need to nest several div elements for styling, etc.
 * // so just add a next div, or further divs
 * $div->addDivElement('ID_PAGE', 'DIV_2');
 * // now the inline element or elements
 * $div->addInline('p', '', '', 'This is a demo of a pargraph element in a nested division block');
 * // Example nesting more div elements.
 * $div->addDivElement('ID_SECOND_LEVEL', 'DIV');
 * $div->addDivElement('ID_THIRD_LEVEL', 'DIV');
 * $div->addDivElement('ID_FOURTH_LEVEL', 'DIV');
 * // now the inline element in fourth div element
 * $div->addInline('p', '', '', 'This is a demo of a pargraph element nested in the fourth level');
 * // If you want to close a div in the current level,...
 * $div->closeParentElement('div');
 * // now the current div is closed and the current level jumps to the third div element
 * // here we can go on adding the inline elments
 * $div->addInline('p', '', 'P_IN_3RD-DIVLEVEL', $testArray);
 * // get the parsed block element -> all opened divs are closed automatically !
 * echo $div->getHtmlDiv();
 * ```
 */
class HtmlDiv extends HtmlElement
{
    /**
     * @var int Integer value for the depth of nested div elements starting with level 1 for the main element
     */
    protected $level = 1;

    /**
     * Constructor creates the element
     *
     * @param string $id    Id of the main div
     * @param string $class Class name of the main div
     */
    public function __construct($id = null, $class = null)
    {
        parent::__construct('div');

        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        if ($class !== null) {
            $this->addAttribute('class', $class);
        }
    }

    /**
     * Add a datalist (dl).
     * @param string $id    Id Attribute
     * @param string $class Class Attribute
     */
    public function addDivElement($id = null, $class = null)
    {
        // Div elements do not need having child elements an can be nested straight forward.
        // For this exception in html we have to take care that the flag of the parent class is always reseted, otherwise the
        // attributes are not parsed, because parent class htmlElement()  determines that the attributes of the
        // parent element are already parsed if flag has value true and the next element is a child with optional attributes and closing tag.
        // So we must overwrite the protected parent variable
        $this->parentFlag = false;
        // Define new div element
        $this->addParentElement('div');

        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        if ($class !== null) {
            $this->addAttribute('class', $class);
        }
        // raise level
        ++$this->level;
    }

    /**
     * Add inline element into current division.
     * @param string $element The inline element
     * @param string $id      Id Attribute
     * @param string $class   Class Attribute
     * @param string $data    Data of the element (optional)
     */
    public function addInline($element, $id = null, $class = null, $data = null)
    {
        $this->addElement($element);

        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        if ($class !== null) {
            $this->addAttribute('class', $class);
        }

        if ($data !== null) {
            $this->addData($data);
        }
    }

    /**
     * This method sets the endtag of the selected element and removes the entry from log array.
     * If nesting mode is not used, the methods looks for the entry in the array and determines that all setted elements after the selected element must be closed as well.
     * All end tags to position are closed automatically starting with last setted element tag.
     *
     * @param string $parentElement Parent element to be closed
     * @return bool
     */
    public function closeParentElement($parentElement)
    {
        // count entries in array
        $totalCount = count($this->arrParentElements);

        if ($totalCount === 0) {
            return false;
        }

        // find position in log array
        $position = array_search($parentElement, $this->arrParentElements, true);

        if (is_int($position)) {
            // if last position set Endtag in string and remove from array
            if ($position === $totalCount) {
                $this->htmlString .= '</' . $this->arrParentElements[$position] . '>';
                unset($this->arrParentElements[$position]);
            } else {
                // all elements setted later must also be closed and removed from array
                for ($i = $totalCount - 1; $i >= $position; --$i) {
                    $this->htmlString .= '</' . $this->arrParentElements[$i] . '>';
                    unset($this->arrParentElements[$i]);
                }
            }
        }

        if ($parentElement === 'div') {
            // set new level
            --$this->level;
        }

        return true;
    }

    /**
     * Get the parsed html division (div)
     *
     * @return string Returns the validated html div as string
     */
    public function getHtmlDiv()
    {
        // first check if open div elements exists and set all endtags if needed
        for ($this->level; $this->level > 2; --$this->level) {
            $this->closeParentElement('div');
        }

        return $this->getHtmlElement();
    }
}
