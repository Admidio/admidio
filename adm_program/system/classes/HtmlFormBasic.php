<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Create html form elements
 *
 * This class creates html form elements.
 * Create an instance of an form element and set the input elements inline .
 * The class supports setting all form elements and allows you to configure all attributes programatically.
 * The parsed form object  is returned as string.
 *
 * **Code examples**
 * ```
 * // Example of an array with further attributes
 * $attrArray = array('class' => 'Classname');
 * ```
 *
 * **Code examples**
 * ```
 * // Example: Creating a form element
 *
 * // Get the Instance for a new form element and set an action attribute
 * $form = new HtmlFormBasic('test.php');
 * // XHTML determines that the input elements are inline elements of a block element
 * // so we need somthing like a div Block. In this example we use a fieldset
 * $form->addFieldSet();
 * // we can define a label for the input element with reference ID
 * $form->addLabel('Field_1', 'ID_1');
 * // set an input element like a text field. All valid types are supported
 * // you can define further attributes as associative array and set as parameter in correct position
 * $form->addSimpleInput('text', 'Input_1', 'ID_1', 'Value_1', $attrArray);
 * // add a linebreak
 * $form->linebreak();
 * // next label
 * $form->addLabel('Radio_1', 'ID_2');
 * // next element is a radio button
 * $form->addSimpleInput('radio', 'Radio_1', 'ID_2', 'Value_Radio');
 * // add a linebreak
 * $form->linebreak();
 * // Define a select box
 * $form->addSelect('Select_Name', 'ID_3', $attrArray);
 * // now we can also specify an optiongroup
 * $form->addOptionGroup('Group_1', 'ID_4', $attrArray);
 * // define options
 * $form->addOption('Option_Value_1', 'Option_Label_1');
 * $form->addOption('Option_Value_2', 'Option_Label_2');
 * $form->addOption('Option_Value_3', 'Option_Label_3');
 * // end of option group
 * $form->closeOptionGroup();
 * // end of select box
 * $form->closeSelect();
 * // add a linebreak
 * $form->linebreak();
 * // example of a text area
 * $form->addTextArea('Textarea', '4', '4', 'Input please ...', 'ID_5', $attrArray);
 * // close open fieldset block
 * $form->closeFieldSet();
 * // print the form
 * echo $form->getHtmlForm();
 * ```
 */
class HtmlFormBasic extends HtmlElement
{
    /**
     * Constructor creates the element
     *
     * @param string $action Optional action attribute of the form
     * @param string $id     Id of the form
     * @param string $method Get/Post (Default "get" if not defined)
     * @param string $event  Optional event handler
     * @param string $script Optional script or function called from event handler
     */
    public function __construct($action = null, $id = null, $method = 'get', $event = null, $script = null)
    {
        parent::__construct('form');

        // set action attribute
        if ($action !== null) {
            $this->addAttribute('action', $action);
        }

        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        if ($method !== null) {
            $this->addAttribute('method', $method);
        }

        if ($event !== null && $script !== null) {
            $this->addAttribute($event, $script);
        }
    }

    /**
     * Add a fieldset.
     * @param string $legend Description for optional legend element as string
     * @param string $id     Optional ID
     */
    public function addFieldSet($legend = null, $id = null)
    {
        if ($id !== null) {
            $this->addParentElement('fieldset');
        } else {
            $this->addParentElement('fieldset', 'id', $id);
        }

        if ($legend !== null) {
            $this->addLegend($legend);
        }
    }

    /**
     * Add an input field with attribute properties.
     * @param string               $type          Type of input field e.g. 'text'
     * @param string               $name          Name of the input field
     * @param string               $id            Optional ID for the input
     * @param string               $value         Value of the field (Default: empty)
     * @param array<string,string> $arrAttributes Further attributes as array with key/value pairs
     */
    public function addSimpleInput($type, $name, $id = null, $value = '', array $arrAttributes = [])
    {
        $data = [
            'type' => $type,
            'name' => $name,
            'id' => $id ,
            'value' => $value
        ];
        $data = array_merge($data, $arrAttributes);

        $data = array_filter($data);

        $this->addHtml($this->render('form.input.simple', ["attributes" => $data]));
    }

    /**
     * Add a label to the input field.
     * @param string $string Value of the label as string
     * @param string $refId
     * @param string $attribute
     */
    public function addLabel($string = '', $refId = null, $attribute = 'for')
    {
        $this->addElement('label');

        if ($refId !== null) {
            $this->addAttribute($attribute, $refId);
        }
        $this->addData($string);
    }

    /**
     * Add a legend element in current fieldset.
     * @param string $legend Data for the element as string
     */
    public function addLegend($legend)
    {
        $this->addElement('legend', '', '', $legend);
    }

    /**
     * Add inline element into current division.
     * @param string               $value         Option value
     * @param string               $label         Label of the option
     * @param string               $id            Optional Id of the option
     * @param bool                 $selected      Mark as selected (Default: false)
     * @param bool                 $disable       Disable option (optional)
     * @param array<string,string> $arrAttributes Further attributes as array with key/value pairs
     */
    public function addOption($value, $label, $id = null, $selected = false, $disable = false, array $arrAttributes = null)
    {
        $this->addElement('option');
        // replace quotes with html entities to prevent xss attacks
        $this->addAttribute('value', $value);

        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        if ($selected) {
            $this->addAttribute('selected', 'selected');
        }

        if ($disable) {
            $this->addAttribute('disabled', 'disabled');
        }

        // Check optional attributes in associative array and set all attributes
        if ($arrAttributes !== null) {
            $this->setAttributesFromArray($arrAttributes);
        }

        // add label
        $this->addData($label);
    }

    /**
     * Add an option group.
     * @param string               $label         Label of the option group
     * @param string               $id            Optional Id of the group
     * @param bool                 $disable       Disable option group (Default: false)
     * @param array<string,string> $arrAttributes Further attributes as array with key/value pairs
     */
    public function addOptionGroup($label, $id = null, $disable = false, array $arrAttributes = null)
    {
        $this->addParentElement('optgroup');

        // set attributes
        $this->addAttribute('label', $label);

        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        // Check optional attributes in associative array and set all attributes
        if ($arrAttributes !== null) {
            $this->setAttributesFromArray($arrAttributes);
        }

        if ($disable) {
            $this->addAttribute('disabled', 'disabled');
        }
    }

    /**
     * Add an option group.
     * @param string               $name          Name of the select
     * @param string               $id            Optional Id of the select
     * @param array<string,string> $arrAttributes Further attributes as array with key/value pairs
     * @param bool                 $disable       Disable select (Default: false)
     */
    public function addSelect($name, $id = null, array $arrAttributes = null, $disable = false)
    {
        $this->addParentElement('select', 'name', $name);

        // set attributes
        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        // Check optional attributes in associative array and set all attributes
        if ($arrAttributes !== null) {
            $this->setAttributesFromArray($arrAttributes);
        }

        if ($disable) {
            $this->addAttribute('disabled', 'disabled');
        }
    }

    /**
     * Adds a button to the form.
     * @param string $name  Name of the button
     * @param string $type  Type attribute (Allowed: submit, reset, button (Default: button))
     * @param string $value Value of the button
     * @param string $id    Optional ID for the button
     * @param string $link  If set a javascript click event with a page load to this link
     *                      will be attached to the button.
     */
    public function addSimpleButton($name, $type, $value, $id = null, $link = null)
    {
        $this->addElement('button');

        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        // if link is set then add a onclick event
        if ($link !== null) {
            $this->addAttribute('onclick', 'self.location.href=\'' . $link . '\'');
        }

        $this->addAttribute('name', $name);
        $this->addAttribute('type', $type);
        $this->addData($value);
    }

    /**
     * Add a text area.
     * @param string               $name          Name of the text area
     * @param int                  $rows          Number of rows
     * @param int                  $cols          Number of cols
     * @param string               $text          Text as content
     * @param string               $id            Optional Id
     * @param array<string,string> $arrAttributes Further attributes as array with key/value pairs
     * @param bool                 $disable       Disable text area (Default: false)
     */
    public function addTextArea($name, $rows, $cols, $text = '', $id = null, array $arrAttributes = null, $disable = false)
    {
        $this->addElement('textarea');

        // set attributes
        $this->addAttribute('name', $name);
        $this->addAttribute('rows', (string) $rows);
        $this->addAttribute('cols', (string) $cols);

        if ($id !== null) {
            $this->addAttribute('id', $id);
        }

        // Check optional attributes in associative array and set all attributes
        if ($arrAttributes !== null) {
            $this->setAttributesFromArray($arrAttributes);
        }

        if ($disable) {
            $this->addAttribute('disabled', 'disabled');
        }

        $this->addData($text);
    }

    /**
     * @par Close current fieldset.
     */
    public function closeFieldSet()
    {
        $this->closeParentElement('fieldset');
    }

    /**
     * @par Close current option group.
     */
    public function closeOptionGroup()
    {
        $this->closeParentElement('optgroup');
    }

    /**
     * @par Close current select.
     */
    public function closeSelect()
    {
        $this->closeParentElement('select');
    }

    /**
     * Get the full parsed html form
     * @return string Returns the validated html form as string
     */
    public function getHtmlForm()
    {
        return $this->getHtmlElement();
    }
}
