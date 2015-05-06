<?php
/*****************************************************************************/
/** @class HtmlFormBasic
 *  @brief  Create html form elements
 *
 *  This class creates html form elements.
 *  Create an instance of an form element and set the input elements inline .
 *  The class supports setting all form elements and allows you to configure all attributes programatically.
 *  The parsed form object  is returned as string.
 *
 *  @par Example of an array with further attributes
 *  @code
 *  $attrArray = array('class' => 'Classname');
 *  @endcode
 *  @par Example: Creating a form element
 *  @code
 *  // Get the Instance for a new form element and set an action attribute
 *  $form = new HtmlFormBasic('test.php');
 *  // XHTML determines that the input elements are inline elements of a block element
 *  // so we need somthing like a div Block. In this example we use a fieldset
 *  $form->addFieldSet();
 *  // we can define a label for the input element with reference ID
 *  $form->addLabel('Field_1', 'ID_1');
 *  // set an input element like a text field. All valid types are supported
 *  // you can define further attributes as associative array and set as parameter in correct position
 *  $form->addSimpleInput('text', 'Input_1', 'ID_1', 'Value_1', $attrArray);
 *  // add a linebreak
 *  $form->linebreak();
 *  // next label
 *  $form->addLabel('Radio_1', 'ID_2');
 *  // next element is a radio button
 *  $form->addSimpleInput('radio', 'Radio_1', 'ID_2', 'Value_Radio');
 *  // add a linebreak
 *  $form->linebreak();
 *  // Define a select box
 *  $form->addSelect('Select_Name', 'ID_3', $attrArray);
 *  // now we can also specify an optiongroup
 *  $form->addOptionGroup('Group_1', 'ID_4', $attrArray);
 *  // define options
 *  $form->addOption('Option_Value_1', 'Option_Label_1');
 *  $form->addOption('Option_Value_2', 'Option_Label_2');
 *  $form->addOption('Option_Value_3', 'Option_Label_3');
 *  // end of option group
 *  $form->closeOptionGroup();
 *  // end of select box
 *  $form->closeSelect();
 *  // add a linebreak
 *  $form->linebreak();
 *  // example of a text area
 *  $form->addTextArea('Textarea', '4', '4', 'Input please ...', 'ID_5', $attrArray);
 *  // close open fieldset block
 *  $form->closeFieldSet();
 *  // print the form
 *  echo $form->getHtmlForm();
 *  @endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2015 The Admidio Team
 *  Author       : Thomas-RCV
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class HtmlFormBasic extends HtmlElement
{

    /**
     * Constructor creates the element
     *
     * @param $action Optional action attribute of the form
     * @param $id Id of the form
     * @param $method Get/Post (Default "get" if not defined)
     * @param $event Optional event handler
     * @param $script Optional script or function called from event handler
     */

    public function __construct($action = null, $id = null, $method = null, $event = null, $script = null)
    {
        parent::__construct('form', '', '', true);

        // set action attribute
        if($action !== null)
        {
            $this->addAttribute('action', $action);
        }

        if($id !== null)
        {
            $this->addAttribute('id', $id);
        }

        if($method !== null)
        {
            $this->addAttribute('method', $method);
        }

        if($event !== null && $script !== null)
        {
            $this->addAttribute($event, $script);
        }
    }

    /** Add a fieldset.
     *  @param $id Optional ID
     *  @param $legend Description for optional legend element as string
     */
    public function addFieldSet($id = null, $legend = null)
    {
        $this->addParentElement('fieldset');

        if($legend !== null)
        {
            $this->addLegend($legend);
        }
    }

    /** Add a input field with attribute properties.
     *  @param $type Type of input field e.g. 'text'
     *  @param $name Name of the input field
     *  @param $id Optional ID for the input
     *  @param $value         Value of the field (Default: empty)
     *  @param $arrAttributes Further attributes as array with key/value pairs
     */
    public function addSimpleInput($type, $name, $id = null, $value = '', $arrAttributes = null)
    {
        $this->addElement('input', '', '', '',  true);

        // set all attributes
        $this->addAttribute('type', $type);
        $this->addAttribute('name', $name);

        if($id !== null)
        {
            $this->addAttribute('id', $id);
        }

        $this->addAttribute('value', $value);

        // Check optional attributes in associative array and set all attributes
        if($arrAttributes !== null && is_array($arrAttributes))
        {
            $this->setAttributesFromArray($arrAttributes);
        }

        $this->addData(' ', true);
    }

    /** Add a label to the input field.
     *  @param $string Value of the label as string
     */
    public function addLabel($string = '', $refID = null, $attribute = 'for')
    {
        $this->addElement('label');

        if($refID !== null)
        {
            $this->addAttribute($attribute, $refID);
        }
        $this->addData($string);
    }


    /** Add a legend element in current fieldset.
     *  @param $legend Data for the element as string
     */
    public function addLegend($legend)
    {
        $this->addElement('legend', '', '', $legend);
    }


    /** Add inline element into current division.
     *  @param $value Option value
     *  @param $label Label of the option
     *  @param $id Optional Id of the option
     *  @param $selected Mark as selected (Default: false)
     *  @param $disable Disable option (optional)
     */
    public function addOption($value, $label, $id = null, $selected = false, $disable = false)
    {
        $this->addElement('option');
        // set attributes
        $this->addAttribute('value', $value);

        if($id !== null)
        {
            $this->addAttribute('id', $id);
        }

        if($selected === true)
        {
            $this->addAttribute('selected', 'selected');
        }

        if($disable === true)
        {
            $this->addAttribute('disabled', 'disabled');
        }
        // add label
        $this->addData($label);
    }

    /** Add an option group.
     *  @param $label Label of the option group
     *  @param $id Optional Id of the group
     *  @param $arrAttributes Further attributes as array with key/value pairs
     *  @param $disable Disable option group (Default: false)
     */
    public function addOptionGroup($label, $id = null, $arrAttributes = null, $disable = false)
    {
        $this->addParentElement('optgroup');

        // set attributes
        $this->addAttribute('label', $label);

        if($id !== null)
        {
            $this->addAttribute('id', $id);
        }

        // Check optional attributes in associative array and set all attributes
        if($arrAttributes !== null && is_array($arrAttributes))
        {
            $this->setAttributesFromArray($arrAttributes);
        }

        if($disable === true)
        {
            $this->addAttribute('disabled', 'disabled');
        }
    }

    /** Add an option group.
     *  @param $name Name of the select
     *  @param $id Optional Id of the select
     *  @param $arrAttributes Further attributes as array with key/value pairs
     *  @param $disable Disable select (Default: false)
     */
    public function addSelect($name, $id = null, $arrAttributes = null, $disable = false)
    {
        $this->addParentElement('select', 'name', $name);

        // set attributes
        if($id !== '')
        {
            $this->addAttribute('id', $id);
        }

        // Check optional attributes in associative array and set all attributes
        if($arrAttributes !== null && is_array($arrAttributes))
        {
            $this->setAttributesFromArray($arrAttributes);
        }

        if($disable === true)
        {
            $this->addAttribute('disabled', 'disabled');
        }
    }

    /** Adds a button to the form.
     *  @param $name  Name of the button
     *  @param $type  Type attribute (Allowed: submit, reset, button (Default: button))
     *  @param $value Value of the button
     *  @param $id    Optional ID for the button
     *  @param $link  If set a javascript click event with a page load to this link
     *                will be attached to the button.
     */
    public function addSimpleButton($name, $type = 'button', $value, $id = null, $link = null)
    {
        $this->addElement('button');

        if($id !== '')
        {
            $this->addAttribute('id', $id);
        }

        // if link is set then add a onclick event
        if($link !== '')
        {
            $this->addAttribute('onclick', 'self.location.href=\''.$link.'\'');
        }

        $this->addAttribute('name', $name);
        $this->addAttribute('type', $type);
        $this->addData($value);
    }

    /** Add a text area.
     * @param $name Name of the text area
     * @param $rows Number of rows
     * @param $cols Number of cols
     * @param $text Text as content
     * @param $id Optional Id
     * @param $arrAttributes Further attributes as array with key/value pairs
     * @param $disable Disable text area (Default: false)
     *
     */
    public function addTextArea($name, $rows, $cols, $text = '', $id = null, $arrAttributes = null, $disable = false)
    {
        $this->addElement('textarea');

        // set attributes
        $this->addAttribute('name', $name);
        $this->addAttribute('rows', $rows);
        $this->addAttribute('cols', $cols);

        if($id !== null)
        {
            $this->addAttribute('id', $id);
        }

        // Check optional attributes in associative array and set all attributes
        if($arrAttributes !== null && is_array($arrAttributes))
        {
            $this->setAttributesFromArray($arrAttributes);
        }

        if($disable === true)
        {
            $this->addAttribute('disabled', 'disabled');
        }

        $this->addData($text);
    }

    /**
     *  @par Close current fieldset.
     */
    public function closeFieldSet()
    {
        $this->closeParentElement('fieldset');
    }

    /**
     *  @par Close current option group.
     */
    public function closeOptionGroup()
    {
        $this->closeParentElement('optgroup');
    }

    /**
     *  @par Close current select.
     */
    public function closeSelect()
    {
        $this->closeParentElement('select');
    }

    /** Get the full parsed html form
     *  @return Returns the validated html form as string
     */
    public function getHtmlForm()
    {
        return parent::getHtmlElement();
    }
}

?>
