<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This **abstract class** parses html elements
 *
 * This abstract class is designed to parse html elements.
 * It is only allowed to use extensions of this class.
 * Create a html object and add your elements programmatically  .
 * Calling as parent instance just define the element you need and add all inline elements
 * or child elements. Also it is possible to define attributes and value for each added
 * element. Content data can be passed as string or as array.
 * The class supports also reading the data from assoc arrays and bi dimensional arrays.
 *
 * **Code example**
 * ```
 * // Example content arrays
 * $dataArray = array('Data 1', 'Data 2', 'Data 3');
 * ```
 *
 * **Code example**
 * ```
 * // Example_1: **unorderedlist**
 *
 * // create as parent instance
 * parent::HtmlElement('ul','class', 'unordered');  // Parameters( element, attribute, value, nesting (true/false ))
 * // we want to have further attributes for the element and set an id, for example
 * HtmlElement::addAttribute('id','mainelement');
 * // set a list element with content as string
 * HtmlElement::addElement('li', 'list 1');
 * // if you need attributes for your setted element then first define the element, set the attributes and after that
 * // pass the content.
 * // Example: Arrays are also supported for content values.
 * HtmlElement::addElement('li');
 * HtmlElement::addAttribute('class', 'from array');
 * HtmlElement::addData($dataArray);
 * // As result you get 3 <li> elements with same class and content from the array
 * // Next example defines a list element with data list, data terms and data descriptions. Therefor we use method addParentElement();
 * // This method logs the selected elements because the endtags must be set later.
 * HtmlElement::addParentElement('li');
 * HtmlElement::addAttribute('class', 'link_1');
 * HtmlElement::addParentElement('dl');
 * HtmlElement::addAttribute('class', 'datalist_1');
 * // now the elements with start and endtags
 * HtmlElement::addElement('dt', 'term');
 * HtmlElement::addElement('dd', 'description');
 * // finally set the endtags for all opened parent elements
 * HtmlElement::closeParentElement('dl');
 * HtmlElement::closeParentElement('li');
 * // Repeat with next list elements
 * HtmlElement::addParentElement('li');
 * HtmlElement::addParentElement('dl');
 * HtmlElement::addElement('dt', 'term2');
 * HtmlElement::addElement('dd', 'description2');
 * HtmlElement::closeParentElement('dl');
 * HtmlElement::closeParentElement('li');
 * $htmlList = HtmlElement::getHtmlElement();
 * echo $htmlList;
 * ```
 *
 * **Code example**
 * ```
 * // Example_2 Nested Div Elements using nesting mode
 *
 * // Creating block elements with nested divs.
 * // Example using nesting mode for html elements
 * // Setting mode to true you are allowed to set the main element ('div' in this example) further times
 * // Default false it is not possible to set the main element again
 *
 * parent::HtmlElement ('div', 'class', 'pagewrap', true);
 * // now we can nest a second div element with a paragaph.
 * // Because of div is the parent of the paragraph element, we must tell the class using method addParentElement();
 * HtmlElement::addParentElement('div');
 * // We want to set an Id for the div element, for example
 * HtmlElement::addAttribute('id', 'Paragraphs', 'div');
 * // Define a paragrph
 * HtmlElement::addElement('p', 'Hello World');
 * // Nested div element must be closed !
 * HtmlElement::closeParentElement('div');
 * // Get the block element
 * $htmlBlock = HtmlElement::getHtmlElement();
 * echo $htmlBlock;
 * ```
 *
 * **Code example**
 * ```
 * // Example_3 Hyperlinks
 *
 * parent::HtmlElement();
 * HtmlElement::addElement('a');
 * HtmlElement::addAttribute('href', 'https://www.admidio.org/');
 * HtmlElement::addData('Admidio Homepage');
 * $hyperlink = HtmlElement::getHtmlElement();
 * echo $hyperlink;
 * ```
 *
 * **Code example**
 * ```
 * // Example_4 Form element
 *
 * // Create a form element
 * parent::HtmlElement('form', 'name', 'testform');
 * HtmlElement::addAttribute('action', 'test.php');
 * HtmlElement::addAttribute('method', 'post');
 * HtmlElement::addAttribute('enctype', 'text/html');
 * // add an input field with label
 * HtmlElement::addElement('input');
 * HtmlElement::addAttribute('type', 'text');
 * HtmlElement::addAttribute('name', 'input');
 * HtmlElement::addHtml('Inputfield:');
 * // pass a whitespace because element has no content
 * HtmlElement::addData(' ', true); // true for self closing element (default: false)
 * // add a checkbox
 * HtmlElement::addElement('input');
 * HtmlElement::addAttribute('type', 'checkbox');
 * HtmlElement::addAttribute('name', 'checkbox');
 * HtmlElement::addHtml('Checkbox:');
 * // pass a whitespace because element has no content
 * HtmlElement::addData(' ', true); // true for self closing element (default: false)
 * // add a submit button
 * HtmlElement::addElement('input');
 * HtmlElement::addAttribute('type', 'submit');
 * HtmlElement::addAttribute('value', 'submit');
 * // pass a whitespace because element has no content
 * HtmlElement::addData(' ', true);
 *
 * echo HtmlElement::getHtmlElement();
 * ```
 */
abstract class HtmlElement extends \Smarty
{
    /**
     * @var bool Flag enables nesting of main elements, e.g div blocks ( Default : true )
     */
    protected $nesting;
    /**
     * @var string String with main element as string
     */
    protected $mainElement;
    /**
     * @var array<string,string> String array with attributes of the main element
     */
    protected $mainElementAttributes = array();
    /**
     * @var bool Flag if the main element was written in the html string
     */
    protected $mainElementWritten = false;
    /**
     * @var string Internal pointer showing to actual element or child element
     */
    protected $currentElement;
    /**
     * @var array<string,string> Attributes of the current element
     */
    protected $currentElementAttributes = array();
    /**
     * @var bool Flag if an element is added but the data is not added
     */
    protected $currentElementDataWritten = true;
    /**
     * @var string String with prepared html
     */
    protected $htmlString = '';
    /**
     * @var bool Flag for setted parent Element
     */
    protected $parentFlag = false;
    /**
     * @var array<int,string> Array with opened child elements
     */
    protected $arrParentElements = array();

    /**
     * Constructor initializing all class variables
     *
     * @param string $element The html element to be defined
     * @param bool   $nesting Enables nesting of main elements ( Default: true )
     */
    public function __construct($element, $nesting = true)
    {
        $this->nesting        = $nesting;
        $this->mainElement    = $element;
        $this->currentElement = $element;

        parent::__construct();
        // initialize php template engine smarty
        if (defined('THEME_PATH')) {
            $this->setTemplateDir(THEME_PATH . '/templates/');
        }

        $this->setCacheDir(ADMIDIO_PATH . FOLDER_DATA . '/templates/cache/');
        $this->setCompileDir(ADMIDIO_PATH . FOLDER_DATA . '/templates/compile/');
        $this->addPluginsDir(ADMIDIO_PATH . '/adm_program/system/smarty-plugins/');

    }

    /**
     * Add attributes to the selected element. If that attribute is already added
     * than the new value will be attached to the current value.
     * @param string $attrKey   Name of the html attribute
     * @param string $attrValue Value of the attribute
     * @param string $element   Optional the element for which the attribute should be set,
     *                          if this is not the current element
     */
    public function addAttribute($attrKey, $attrValue, $element = null)
    {
        if ($element === null) {
            $element = $this->currentElement;
        }

        if ($element === $this->mainElement) {
            if (array_key_exists($attrKey, $this->mainElementAttributes)) {
                $this->mainElementAttributes[$attrKey] = $this->mainElementAttributes[$attrKey] . ' ' . $attrValue;
            } else {
                $this->mainElementAttributes[$attrKey] = $attrValue;
            }
        } else {
            if (array_key_exists($attrKey, $this->currentElementAttributes)) {
                $this->currentElementAttributes[$attrKey] = $this->currentElementAttributes[$attrKey] . ' ' . $attrValue;
            } else {
                $this->currentElementAttributes[$attrKey] = $attrValue;
            }
        }
    }

    /**
     * Set attributes from associative array.
     * @param array<string,mixed> $arrAttributes An array that contains all attribute names as array key
     *                                           and all attribute content as array value
     */
    protected function setAttributesFromArray(array $arrAttributes)
    {
        foreach ($arrAttributes as $key => $value) {
            $this->addAttribute($key, (string) $value);
        }
    }

    /**
     * Add data to current element
     * @param string|string[] $data        Content for the element as string, or array
     * @param bool            $selfClosing Element has self closing tag ( default: false)
     */
    public function addData($data, $selfClosing = false)
    {
        if ($selfClosing) {
            $startTag = '<' . $this->currentElement . $this->getCurrentElementAttributesString();
            $endTag   = '/>';
        } else {
            $startTag = '<' . $this->currentElement . $this->getCurrentElementAttributesString() . '>';
            $endTag   = '</' . $this->currentElement . '>';
        }

        if (is_array($data)) {
            // data is an array
            foreach ($data as $value) {
                $this->htmlString .= $startTag . $value . $endTag;
            }
        } else {
            // data is a string
            $this->htmlString .= $startTag . $data . $endTag;
        }

        $this->currentElementAttributes = array();
        // set flag that the data of the current element is written to html string
        $this->currentElementDataWritten = true;
    }

    /**
     * @par Add new child element.
     * This method defines the next child element to be written in the output string.
     * If a parent element was defined before, the syntax with all setted attributes is written first from internal buffer to the string.
     * After that, the new element is defined.
     * The method determines that the element has **no own child elements** and has a closing tag.
     * If you need a parent element like a \<div\> with some \<p\> elements, use method addParentElement(); instead and then add the paragraph elements.
     * If nesting mode is active you are allowed to set the main element called with object instance again. Dafault: false
     *
     * @param string $childElement valid child tags for element object
     * @param string $attrKey      Attribute name
     * @param string $attrValue    Value for the attribute
     * @param string $data         content values can be passed as string, array, bidimensional Array and assoc. Array. ( Default: no data )
     * @param bool   $selfClosing  Element has self closing tag ( default: false)
     */
    public function addElement($childElement, $attrKey = '', $attrValue = '', $data = '', $selfClosing = false)
    {
        // if previous current element was not written to html string and the same child element is set
        // than this could be a call of parent class so do not reinitialize the current element
        if (!$this->currentElementDataWritten && $childElement === $this->currentElement) {
            return;
        }

        $this->currentElementDataWritten = false;

        if ($attrKey !== '' || $attrValue !== '') {
            $this->addAttribute($attrKey, $attrValue);
        }

        // check if parent element is set, then write first the tag and attributes for the previous element
        if ($this->parentFlag) {
            // Main element attributes are set in own variable, so in nesting mode main element can be set again
            if ($this->currentElement === $this->mainElement) {
                $this->currentElementAttributes = $this->mainElementAttributes;
            }

            $this->htmlString .= '<' . $this->currentElement . $this->getCurrentElementAttributesString() . '>';
            $this->currentElement = $childElement;
            $this->currentElementAttributes = array();
            $this->parentFlag = false;
        }

        // If first child is set start writing the html beginning with main element and attributes
        if ($this->currentElement === $this->mainElement && $this->mainElement !== '' && !$this->mainElementWritten) {
            $this->htmlString .= '<' . $this->mainElement . $this->getMainElementAttributesString() . '>';
            $this->mainElementWritten = true;
        }

        // If nesting is enabled, main element can be set again
        if ($childElement === $this->mainElement && $this->nesting) {
            // now set as current position
            $this->currentElement = $childElement;
            // clear attribute buffer
            $this->currentElementAttributes = array();
        }

        if ($childElement !== $this->mainElement) {
            // now set as current position
            $this->currentElement = $childElement;
            // clear attribute buffer
            $this->currentElementAttributes = array();
        }

        // add content if exists
        if ($data !== '') {
            $this->addData($data, $selfClosing);
        }
    }

    /**
     * Add any string to the html output. If the main element wasn't written to the
     * html string than this will be done before your string will be added.
     * @param string $string Text as string in current string position
     */
    public function addHtml($string = '')
    {
        // If first child is set start writing the html beginning with main element and attributes
        if ($this->currentElement === $this->mainElement && $this->mainElement !== '' && !$this->mainElementWritten) {
            $this->htmlString .= '<' . $this->mainElement . $this->getMainElementAttributesString() . '>';
            $this->mainElementWritten = true;
        }

        $this->htmlString .= $string;
    }

    /**
     * @par Add a parent element that has own child's.
     * This method is needed if an element can have several child elements and the closing tag must be set after own child elements.
     * It logs the setted element in an array. Each time you define a new parent element, the function checks the log array, if the element already was set.
     * If the current element already was defined, then the function determines that the still opened tag must be closed first until it can be set again.
     * The method closeParentElement(); is called automatically to close the previous element.
     * By default it is not allowed to define several elements from same type. If needed use option **nesting mode true**!
     *
     * @param string $parentElement Parent element to be set
     * @param string $attrKey       Attribute name
     * @param string $attrValue     Value for the attribute
     */
    public function addParentElement($parentElement, $attrKey = '', $attrValue = '')
    {
        // Only possible for child elements of the main element or nesting mode is active!
        if (!$this->nesting && $this->currentElement === $this->mainElement) {
            return;
        }

        // check if already parent element is set, then write first the tag and attributes for the previous element
        if ($this->parentFlag) {
            $this->htmlString .= '<' . $this->currentElement . $this->getCurrentElementAttributesString() . '>';
        //$this->currentElementAttributes = array();
        } else {
            // set Flag
            $this->parentFlag = true;

            if ($this->currentElement === $this->mainElement && $this->nesting && !$this->mainElementWritten) {
                $this->htmlString .= '<' . $this->currentElement . $this->getMainElementAttributesString() . '>';
                $this->mainElementAttributes = array();
            }
        }

        if (!in_array($parentElement, $this->arrParentElements, true)) {
            // If currently not defined and element has own child elements then log in array to define endtags later
            $this->arrParentElements[] = $parentElement;
        } elseif ($this->nesting) {
            // in nesting mode always log elements
            $this->arrParentElements[] = $parentElement;
        } else {
            // already set and we need the endtag first before setting again
            $this->closeParentElement($parentElement);
            $this->arrParentElements[] = $parentElement;
        }
        // set parent element to current element
        $this->currentElement = $parentElement;
        // initialize attributes because parent element should not get attributes of previous element
        $this->currentElementAttributes = array();

        // save attribute for parent element
        if ($attrKey !== '') {
            $this->addAttribute($attrKey, $attrValue);
        }
        //$this->mainElementAttributes = array();
    }

    /**
     * @par Close parent element.
     * This method sets the endtag of the selected element and removes the entry from log array.
     * If nesting mode is not used, the methods looks for the entry in the array and determines
     * that all setted elements after the selected element must be closed as well.
     * All end tags to position are closed automatically starting with last setted element tag.
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

        if (!$this->nesting && is_int($position)) {
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
        } else {
            // close last tag and delete whitespaces in log array
            $this->htmlString .= '</' . $this->arrParentElements[$totalCount - 1] . '>';
            unset($this->arrParentElements[$totalCount - 1]);
        }

        $this->arrParentElements = array_values($this->arrParentElements);

        return true;
    }

    /**
     * Create a valid html compatible string with all attributes and their values of the given element.
     * @param array<string,string> $elementAttributes
     * @return string Returns a string with all attributes and values.
     */
    private function getElementAttributesString(array $elementAttributes)
    {
        if (count($elementAttributes) === 0) {
            return '';
        }

        $attributes = array();
        foreach ($elementAttributes as $key => $value) {
            $attributes[] = $key . '="' . htmlspecialchars($value) . '"';
        }

        return ' ' . implode(' ', $attributes);
    }

    /**
     * Create a valid html compatible string with all attributes and their values of the last added element.
     * @return string Returns a string with all attributes and values.
     */
    private function getCurrentElementAttributesString()
    {
        return $this->getElementAttributesString($this->currentElementAttributes);
    }

    /**
     * Create a valid html compatible string with all attributes and their values of the main element.
     * @return string Returns a string with all attributes and values.
     */
    private function getMainElementAttributesString()
    {
        return $this->getElementAttributesString($this->mainElementAttributes);
    }

    /**
     * Return the element as string
     * @return string Returns the parsed html as string
     */
    public function getHtmlElement()
    {
        $this->htmlString .= '</' . $this->mainElement . '>';

        return $this->htmlString;
    }

    public function render($templateName, $assigns) {
        global $gL10n;
        foreach($assigns as $key => $assign) {
            $this->assign($key, $assign);
        }
        $this->assign("ADMIDIO_URL", ADMIDIO_URL);
        $this->assign("FOLDER_LIBS_SERVER", FOLDER_LIBS_SERVER);
        $this->assign("data", $assigns);

        $this->assign('l10n', $gL10n);
        return $this->fetch("sys-template-parts/".$templateName.'.tpl');
    }
}
