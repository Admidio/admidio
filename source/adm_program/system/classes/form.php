<?php 
/*****************************************************************************/
/** @class Form
 *  @brief Creates an Admidio specific form with special elements
 *
 *  This class creates the organization object and manages the access to the
 *  organization specific preferences of the table adm_preferences. There
 *  are also some method to read the relationship of organizations if the 
 *  database contains more then one organization.
 *  @par Examples
 *  @code // create object and read the value of the language preference
 *  $organization = new Organization($gDb, $organizationId);
 *  $preferences  = $organization->getPreferences();
 *  $language     = $preferences['system_language'];
 *  // language = 'de'@endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class Form extends HtmlForm
{
    protected $flagMandatoryFields; ///< Flag if this form has mandatory fields. Then a notice must be written at the end of the form
    private   $flagFieldListOpen;   ///< Flag if a field list was created. This must be closed later
    
    /** Constructor creates the form element
     *  @param $id Id of the form
     *  @param $action Optional action attribute of the form
     */
    public function __construct($id, $action)
    {        
        
        parent::__construct($action, $id, 'post');
        
        // set specific Admidio css form class
        $this->addAttribute('class', 'admFormLayout');
        $this->flagMandatoryFields = false;
        $this->flagFieldListOpen   = false;
    }
    
    /** Add a new button with a custom text to the form. This button could have 
     *  an icon in front of the text.
     *  @param $id    Id of the button. This will also be the name of the button.
     *  @param $text  Text of the button
     *  @param $icon  Optional parameter. Path and filename of an icon. 
     *                If set a icon will be shown in front of the text.
     *  @param $class Optional an additional css classname. The class @b admButton
     *                is set as default and need not set with this parameter.
     *  @param $type  Optional a button type could be set. The default is @b button.
     */
    public function addButton($id, $text, $icon = '', $class = '', $type = 'button')
    {
        // add text and icon to button
        $value = $text;
        
        if(strlen($icon) > 0)
        {
            $value = '<img src="'.$icon.'" alt="'.$text.'" />'.$value;
        }
        $this->addElement('button');
        $this->addAttribute('class', 'admButton');
        if(strlen($class) > 0)
        {
            $this->addAttribute('class', $class);
        }
        $this->addSimpleButton($id, $type, $value, $id);
    }
    
    /** Creates a html structure for a form field. This structure contains the label
     *  and the div for the form element. After the form element is added the 
     *  method closeFieldStructure must be called.
     *  @param $id        The id of this field structure.
     *  @param $label     The label of the field. This string should already be translated.
     *  @param $mandatory A flag if the field is mandatory. Then the specific css classes will be set.
     */
    public function addFieldStructure($id, $label, $mandatory = false)
    {
        // if necessary set css class for a mandatory element
        $classMandatory = '';
        if($mandatory == true)
        {
            $classMandatory = ' admMandatory';
            $this->flagMandatoryFields = true;
        }
        
        // create a div tag for the field list
        if($this->flagFieldListOpen == false)
        {
            $this->addString('<div class="admFieldList">');
            $this->flagFieldListOpen = true;
        }
        
        // create html
        $this->addString('
        <div class="admFieldRow'.$classMandatory.'">
            <div class="admFieldLabel'.$classMandatory.'"><label for="'.$id.'">'.$label.':</label></div>
            <div class="admFieldElement'.$classMandatory.'">');
    }
    
    /** Add a new password field with a label to the form. The password field could have
     *  maximum 50 characters. You could not set a value to a password field.
     *  @param $id     Id of the password field. This will also be the name of the password field.
     *  @param $label  The label of the password field.
     *  @param $class  Optional an additional css classname. The class @b admTextInput
     *                 is set as default and need not set with this parameter.
     */
    public function addPasswordInput($id, $label, $mandatory = false, $class = '')
    {
        $attributes = array('class' => 'admTextInput admPasswordInput');
        
        if(strlen($class) > 0)
        {
            $attributes['class'] = 'admTextInput '.$class;
        }
        
        $this->addFieldStructure($id, $label, $mandatory);
        $this->addInput('password', $id, $id, null, $attributes);
        $this->addAttribute('class', 'admTextInput');
        $this->closeFieldStructure();
    }
    
    /** Add a new selectbox with a label to the form. The selectbox could have
     *  different values and a default value could be set.
     *  @param $id     Id of the selectbox. This will also be the name of the selectbox.
     *  @param $label  The label of the selectbox.
	 *  @param $values Array with all entries of the select box; 
	 *                 Array key will be the internal value of the entry
	 *                 Array value will be the visual value of the entry
     *  @param $defaultValue    This is the value the selectbox shows when loaded.
     *  @param $setPleaseChoose If set to @b true a new entry will be added to the top of 
     *                          the list with the caption "Please choose".
     *  @param $class  Optional an additional css classname. The class @b admSelectbox
     *                 is set as default and need not set with this parameter.
     */
    public function addSelectBox($id, $label, $values, $mandatory = false, $defaultValue = '', $setPleaseChoose = false, $class = '')
    {
        global $gL10n;
        
        $this->addFieldStructure($id, $label, $mandatory);
        $this->addSelect($id, $id);
        $this->addAttribute('class', 'admSelectBox');

        if($setPleaseChoose == true)
        {
            $defaultEntry = false;
            if($defaultValue == '')
            {
                $defaultEntry = true;
            }
            $this->addOption(' ', '- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -', null, $defaultEntry);
        }

        $value = reset($values);
        for($arrayCount = 0; $arrayCount < count($values); $arrayCount++)
        {
            // create entry in html
            $defaultEntry = false;
            if($defaultValue == key($values))
            {
                $defaultEntry = true;
            }
            
            $this->addOption(key($values), $value, null, $defaultEntry);

            $value = next($values);
        }
        $this->closeSelect();
        $this->closeFieldStructure();
    }
    
    /** Add a new selectbox with a label to the form. The selectbox could have
     *  different values and a default value could be set.
     *  @param $id         Id of the selectbox. This will also be the name of the selectbox.
     *  @param $label      The label of the selectbox.
	 *  @param xmlFile     Serverpath to the xml file
	 *  @param xmlValueTag Name of the xml tag that should contain the internal value of a selectbox entry
	 *  @param xmlViewTag  Name of the xml tag that should contain the visual value of a selectbox entry
     *  @param $defaultValue    This is the value the selectbox shows when loaded.
     *  @param $setPleaseChoose If set to @b true a new entry will be added to the top of 
     *                          the list with the caption "Please choose".
     *  @param $class  Optional an additional css classname. The class @b admSelectbox
     *                 is set as default and need not set with this parameter.
     */
    public function addSelectBoxFromXml($id, $label, $xmlFile, $xmlValueTag, $xmlViewTag, $mandatory = false, $defaultValue= '', $setPleaseChoose = false, $class = '')
    {
		// write content of xml file to an array
		$data = implode('', file($xmlFile));
		$p = xml_parser_create();
		xml_parse_into_struct($p, $data, $vals, $index);
		xml_parser_free($p);
        
        // transform the two complex arrays to one simply array
        for($i = 0; $i < count($index[$xmlValueTag]); $i++)
        {
            $simpleArray[$vals[$index[$xmlValueTag][$i]]['value']] = $vals[$index[$xmlViewTag][$i]]['value'];
        }
        
        // now call default method to create a selectbox
        $this->addSelectBox($id, $label, $simpleArray, $mandatory, $defaultValue, $setPleaseChoose, $class);
    }
    
    /** Add a new button with a custom text to the form. This button could have 
     *  an icon in front of the text. Different to addButton this method adds an
     *  additional @b div around the button and the type of the button is @b submit.
     *  If mandatory fields were set than a notice which marker represents the
     *  mandatory will be shown.
     *  @param $id    Id of the button. This will also be the name of the button.
     *  @param $text  Text of the button
     *  @param $icon  Optional parameter. Path and filename of an icon. 
     *                If set a icon will be shown in front of the text.
     *  @param $typeSubmit If set to true this button get the type @b submit. This will 
     *                be the default.
     *  @param $class Optional an additional css classname. The class @b admButton
     *                is set as default and need not set with this parameter.
     */
    public function addSubmitButton($id, $text, $icon = '', $class = '', $type = 'submit')
    {
        global $gL10n;
        
        // If mandatory fields were set than a notice which marker represents the mandatory will be shown.
        if($this->flagMandatoryFields)
        {
            $this->addString('<div class="admMandatoryDefinition"><span></span> '.$gL10n->get('SYS_MANDATORY_FIELDS').'</div>');
        }
        
        $class .= 'admSubmitButton';
        
        // now add button to form
        $this->addButton($id, $text, $icon, $class, $type);
    }
    
    /** Add a new input field with a label to the form. The input field could have
     *  maximum 50 characters.
     *  @param $id     Id of the input field. This will also be the name of the input field.
     *  @param $label  The label of the input field.
	 *  @param $value  A value for the text field. The field will be created with this value.
     *  @param $class  Optional an additional css classname. The class @b admTextInput
     *                 is set as default and need not set with this parameter.
     */
    public function addTextInput($id, $label, $value, $mandatory = false, $class = '')
    {
        $attributes = array('class' => 'admTextInput');
        
        if(strlen($class) > 0)
        {
            $attributes['class'] = 'admTextInput '.$class;
        }
        
        $this->addFieldStructure($id, $label, $mandatory);
        $this->addInput('text', $id, $id, $value, $attributes);
        $this->addAttribute('class', 'admTextInput');
        $this->closeFieldStructure();
    }
    
    /** Closes a field structure that was added with the method addFieldStructure.
     */
    public function closeFieldStructure()
    {
        $this->addString('</div></div>');
    }
    
    /** Close all html elements of a groupbox that was created before.
     */
    public function closeGroupBox()
    {
        // first check if a field list was opened
        if($this->flagFieldListOpen == true)
        {
            $this->addString('</div>');
            $this->flagFieldListOpen = false;
        }

        $this->addString('</div></div>');
    }
    
    /** Add a new groupbox to the form. This could be used to group some elements 
     *  together. There is also the option to set a headline to this group box.
     *  @param $id       Id the the groupbox.
     *  @param $headline Optional a headline that will be shown to the user.
     */
    public function openGroupBox($id, $headline = '')
    {
        $this->addString('<div id="'.$id.'" class="groupBox">');
        // add headline to groupbox
        if(strlen($headline) > 0)
        {
            $this->addString('<div class="groupBoxHeadline">'.$headline.'</div>');
        }
        $this->addString('<div class="groupBoxBody">');
    }
}
?>