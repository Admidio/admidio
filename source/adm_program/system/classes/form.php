<?php 
/*****************************************************************************/
/** @class Form
 *  @brief Creates an Admidio specific form with special elements
 *
 *  This class inherits the common HtmlForm class and extends their elements
 *  with custom Admidio form elements. The class should be used to create the 
 *  html part of all Admidio forms. The Admidio elements will contain
 *  the label of fields and some other specific features like a identification
 *  of mandatory fields, help buttons and special css classes for every
 *  element.
 *  @par Examples
 *  @code // create a simple form with one input field and a button
 *  $form = new Form('simple-form', 'next_page.php');
 *  $form->openGroupBox('gbSimpleForm', $gL10n->get('SYS_SIMPLE_FORM'));
 *  $form->addTextInput('name', $gL10n->get('SYS_NAME'), $formName, true);
 *  $form->addSelectBox('type', $gL10n->get('SYS_TYPE'), array('simple' => 'SYS_SIMPLE', 'very-simple' => 'SYS_VERY_SIMPLE'), true, 'simple', true);
 *  $form->closeGroupBox();
 *  $form->addSubmitButton('next-page', $gL10n->get('SYS_NEXT'), 'layout/forward.png');
 *  $form->show();@endcode
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
		
		// first field of form should get focus
		$js = '<script type="text/javascript"><!--
			       $(document).ready(function() { $("form:first *:input[type!=hidden]:first").focus();});
			   //--></script>';
		$this->addString($js);
		
        $this->flagMandatoryFields = false;
        $this->flagFieldListOpen   = false;
    }
    
    /** Add a new button with a custom text to the form. This button could have 
     *  an icon in front of the text.
     *  @param $id    Id of the button. This will also be the name of the button.
     *  @param $text  Text of the button
     *  @param $icon  Optional parameter. Path and filename of an icon. 
     *                If set a icon will be shown in front of the text.
     *  @param $link  If set a javascript click event with a page load to this link 
     *                will be attached to the button.
     *  @param $class Optional an additional css classname. The class @b admButton
     *                is set as default and need not set with this parameter.
     *  @param $type  Optional a button type could be set. The default is @b button.
     */
    public function addButton($id, $text, $icon = '', $link = '', $class = '', $type = 'button')
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
        $this->addSimpleButton($id, $type, $value, $id, $link);
    }
    
    
    /** Add a new checkbox with a label to the form.
     *  @param $id         Id of the checkbox. This will also be the name of the checkbox.
     *  @param $label      The label of the checkbox.
	 *  @param $value      A value for the checkbox. This value will be send with the form if the checkbox is checked.
     *  @param $mandatory  A flag if the field is mandatory. Then the specific css classes will be set.
	 *  @param $helpTextId If set a help icon will be shown after the checkbox and on mouseover the translated text 
	 *                     of this id will be displayed e.g. SYS_ENTRY_MULTI_ORGA.
     *  @param $class      Optional an additional css classname. The class @b admCheckbox
     *                     is set as default and need not set with this parameter.
     */
    public function addCheckbox($id, $label, $value, $mandatory = false, $helpTextId = null, $class = null)
    {
        $attributes = array('class' => 'admCheckbox');

        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }
        
        $this->openFieldStructure($id, null, $mandatory, 'admCheckboxRow');
        $this->addInput('checkbox', $id, $id, $value, $attributes);
		$this->addString('<label for="'.$id.'">'.$label.'</label>');
		$this->setHelpText($helpTextId);
        $this->closeFieldStructure();
    }
    
    /** Add a line with a custom description to the form. No form elements will be 
     *  displayed in this line.
     *  @param $text The (html) text that should be displayed.
     */
    public function addDescription($text)
    {
        $this->addString('<div class="admFieldRow"><div class="admDescription">'.$text.'</div></div>');
    }
	
    /** Add a new CKEditor element to the form. 
     *  @param $id         Id of the password field. This will also be the name of the password field.
     *  @param $label      The label of the password field.
     *  @param $mandatory  A flag if the field is mandatory. Then the specific css classes will be set.
     *  @param $toolbar    Optional set a predefined toolbar for the editor. Possible values are 
     *                     @b AdmidioDefault, @b Admidio Guestbook, @b AdmidioEcard and @b AdmidioPlugin_WC
     *  @param $height     Optional set the height in pixel of the editor. The default will be 300px.
	 *  @param $helpTextId If set a help icon will be shown after the password field and on mouseover the translated text 
	 *                     of this id will be displayed e.g. SYS_ENTRY_MULTI_ORGA.
     *  @param $class      Optional an additional css classname. The class @b admTextInput
     *                     is set as default and need not set with this parameter.
     */
	public function addEditor($id, $label, $value, $mandatory = false, $toolbar = 'AdmidioDefault', $height = '300px', $helpTextId = null, $class = '')
	{
        $attributes = array('class' => 'admEditor');

        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }

        // set specific toolbar for editor
        if(strlen($toolbar) == 0)
        {
            $toolbar = 'AdmidioDefault';
        }
        
        // set specific height for editor
        if(strlen($height) == 0)
        {
            $height = '300px';
        }

		$ckEditor = new CKEditorSpecial();

        $this->openFieldStructure($id, $label, $mandatory, 'admEditorRow');
		$this->addString('<div class="'.$attributes['class'].'">'.$ckEditor->createEditor($id, $value, $toolbar, $height).'</div>');
		$this->setHelpText($helpTextId);
        $this->closeFieldStructure();
	}
    
    /** Add a new password field with a label to the form. The password field has not a limit of characters.
     *  You could not set a value to a password field.
     *  @param $id         Id of the password field. This will also be the name of the password field.
     *  @param $label      The label of the password field.
     *  @param $mandatory  A flag if the field is mandatory. Then the specific css classes will be set.
	 *  @param $helpTextId If set a help icon will be shown after the password field and on mouseover the translated text 
	 *                     of this id will be displayed e.g. SYS_ENTRY_MULTI_ORGA.
     *  @param $class      Optional an additional css classname. The class @b admTextInput
     *                     is set as default and need not set with this parameter.
     */
    public function addPasswordInput($id, $label, $mandatory = false, $helpTextId = null, $class = '')
    {
        $attributes = array('class' => 'admTextInput admPasswordInput');
        
        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }
        
        $this->openFieldStructure($id, $label, $mandatory, 'admPasswordInputRow');
        $this->addInput('password', $id, $id, null, $attributes);
        $this->addAttribute('class', 'admTextInput');
		$this->setHelpText($helpTextId);
        $this->closeFieldStructure();
    }
    
    /** Add a new selectbox with a label to the form. The selectbox could have
     *  different values and a default value could be set.
     *  @param $id     Id of the selectbox. This will also be the name of the selectbox.
     *  @param $label  The label of the selectbox.
	 *  @param $values Array with all entries of the select box; 
	 *                 Array key will be the internal value of the entry
	 *                 Array value will be the visual value of the entry
     *  @param $mandatory       A flag if the field is mandatory. Then the specific css classes will be set.
     *  @param $defaultValue    This is the value the selectbox shows when loaded.
     *  @param $setPleaseChoose If set to @b true a new entry will be added to the top of 
     *                          the list with the caption "Please choose".
	 *  @param $helpTextId      If set a help icon will be shown after the selectbox and on mouseover the translated text 
	 *                          of this id will be displayed e.g. SYS_ENTRY_MULTI_ORGA.
     *  @param $class  Optional an additional css classname. The class @b admSelectbox
     *                 is set as default and need not set with this parameter.
     */
    public function addSelectBox($id, $label, $values, $mandatory = false, $defaultValue = '', $setPleaseChoose = false, $helpTextId = null, $class = '')
    {
        global $gL10n;

        $attributes = array('class' => 'admSelectBox');
        
        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }
        
        $this->openFieldStructure($id, $label, $mandatory, 'admSelectboxRow');
        $this->addSelect($id, $id, $attributes);

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
		$this->setHelpText($helpTextId);
        $this->closeFieldStructure();
    }
    
    /** Add a new selectbox with a label to the form. The selectbox get their data from a sql statement.
     *  You can create any sql statement and method should create a selectbox with the found data.
     *  @par Examples
     *  @code // create a selectbox with all profile fields of a specific category
     *  $sql = 'SELECT usf_id, usf_name FROM '.TBL_USER_FIELDS.' WHERE usf_cat_id = 4711'
     *  $form = new Form('simple-form', 'next_page.php');
     *  $form->addSelectBoxFromSql('admProfileFieldsBox', $gL10n->get('SYS_FIELDS'), $gDb, $sql, false, $gL10n->get('SYS_SURNAME'), true);
     *  $form->show();@endcode
     *  @param $id              Id of the selectbox. This will also be the name of the selectbox.
     *  @param $label           The label of the selectbox.
     *  @param $databaseObject  A Admidio database object that contains a valid connection to a database
	 *  @param $sql             Any SQL statement that return 2 columns. The first column will be the internal value of the
     *                          selectbox item and will be submitted with the form. The second column represents the
     *                          displayed value of the item. Each row of the result will be a new selectbox entry.
     *  @param $mandatory       A flag if the field is mandatory. Then the specific css classes will be set.
     *  @param $defaultValue    This is the value the selectbox shows when loaded.
     *  @param $setPleaseChoose If set to @b true a new entry will be added to the top of 
     *                          the list with the caption "Please choose".
	 *  @param $helpTextId      If set a help icon will be shown after the selectbox and on mouseover the translated text 
	 *                          of this id will be displayed e.g. SYS_ENTRY_MULTI_ORGA.
     *  @param $class           Optional an additional css classname. The class @b admSelectbox
     *                          is set as default and need not set with this parameter.
     */
    public function addSelectBoxFromSql($id, $label, &$databaseObject, $sql, $mandatory = false, $defaultValue= '', 
                                        $setPleaseChoose = false, $helpTextId = null, $class = '')
    {
        $selectboxEntries = array();
    
        // execute the sql statement
        $result = $databaseObject->query($sql);
        
        // create array from sql result
        while($row = $databaseObject->fetch_array($result))
        {
            $selectboxEntries[$row[0]] = $row[1];
        }
        
        // now call default method to create a selectbox
        $this->addSelectBox($id, $label, $selectboxEntries, $mandatory, $defaultValue, $setPleaseChoose, $class);
    }
    
    /** Add a new selectbox with a label to the form. The selectbox could have
     *  different values and a default value could be set.
     *  @param $id           Id of the selectbox. This will also be the name of the selectbox.
     *  @param $label        The label of the selectbox.
	 *  @param $xmlFile      Serverpath to the xml file
	 *  @param $xmlValueTag  Name of the xml tag that should contain the internal value of a selectbox entry
	 *  @param $xmlViewTag   Name of the xml tag that should contain the visual value of a selectbox entry
     *  @param $mandatory    A flag if the field is mandatory. Then the specific css classes will be set.
     *  @param $defaultValue This is the value the selectbox shows when loaded.
     *  @param $setPleaseChoose If set to @b true a new entry will be added to the top of 
     *                          the list with the caption "Please choose".
	 *  @param $helpTextId   If set a help icon will be shown after the selectbox and on mouseover the translated text 
	 *                       of this id will be displayed e.g. SYS_ENTRY_MULTI_ORGA.
     *  @param $class        Optional an additional css classname. The class @b admSelectbox
     *                       is set as default and need not set with this parameter.
     */
    public function addSelectBoxFromXml($id, $label, $xmlFile, $xmlValueTag, $xmlViewTag, $mandatory = false, $defaultValue= '', 
                                        $setPleaseChoose = false, $helpTextId = null, $class = '')
    {
        $selectboxEntries = array();
        
		// write content of xml file to an array
		$data = implode('', file($xmlFile));
		$p = xml_parser_create();
		xml_parse_into_struct($p, $data, $vals, $index);
		xml_parser_free($p);
        
        // transform the two complex arrays to one simply array
        for($i = 0; $i < count($index[$xmlValueTag]); $i++)
        {
            $selectboxEntries[$vals[$index[$xmlValueTag][$i]]['value']] = $vals[$index[$xmlViewTag][$i]]['value'];
        }
        
        // now call default method to create a selectbox
        $this->addSelectBox($id, $label, $selectboxEntries, $mandatory, $defaultValue, $setPleaseChoose, $class);
    }
    
    /** Add a new selectbox with a label to the form. The selectbox get their data from table adm_categories. You must
     *  define the category type (roles, dates, links ...). All categories of this type will be shown.
     *  @param $id                 Id of the selectbox. This will also be the name of the selectbox.
     *  @param $label              The label of the selectbox.
     *  @param $databaseObject     A Admidio database object that contains a valid connection to a database
	 *  @param $categoryType	   Type of category ('DAT', 'LNK', 'ROL', 'USF') that should be shown
	 *  @param $defaultValue	   Id of category that should be selected per default.
     *  @param $setPleaseChoose    If set to @b true a new entry will be added to the top of 
     *                             the list with the caption "Please choose".
	 *  @param $helpTextId         If set a help icon will be shown after the selectbox and on mouseover the translated text 
	 *                             of this id will be displayed e.g. SYS_ENTRY_MULTI_ORGA.
	 *  @param $showCategoryChoice This mode shows only categories with elements and if default category will be selected 
	 *                             there must be at least more then one category to select
	 *  @param $showSystemCategory Show user defined and system categories
     *  @param $class              Optional an additional css classname. The class @b admSelectbox
     *                             is set as default and need not set with this parameter.
	 */
	public function addSelectBoxForCategories($id, $label, &$databaseObject, $categoryType, $mandatory = false, $defaultValue = 0, $setPleaseChoose = false, 
	                                          $helpTextId = null, $showCategoryChoice = false, $showSystemCategory = true, $class = '')
	{
		global $gCurrentOrganization, $gValidLogin;

        $sqlTables      = TBL_CATEGORIES;
        $sqlCondidtions = '';
        $selectBoxHtml  = '';

		// create sql conditions if category must have child elements
		if($showCategoryChoice)
		{
            if($categoryType == 'DAT')
            {
                $sqlTables = TBL_CATEGORIES.', '.TBL_DATES;
                $sqlCondidtions = ' AND cat_id = dat_cat_id ';
            }
            elseif($categoryType == 'LNK')
            {
                $sqlTables = TBL_CATEGORIES.', '.TBL_LINKS;
                $sqlCondidtions = ' AND cat_id = lnk_cat_id ';
            }
            elseif($categoryType == 'ROL')
            {
				// don't show system categories
                $sqlTables = TBL_CATEGORIES.', '.TBL_ROLES;
                $sqlCondidtions = ' AND cat_id = rol_cat_id 
                                    AND rol_visible = 1 ';
            }
		}
		
		if($showSystemCategory == false)
		{
			 $sqlCondidtions .= ' AND cat_system = 0 ';
		}
		
		if($gValidLogin == false)
		{
			 $sqlCondidtions .= ' AND cat_hidden = 0 ';
		}
		
		// the sql statement which returns all found categories
		$sql = 'SELECT DISTINCT cat_id, cat_name, cat_default 
		          FROM '.$sqlTables.'
				 WHERE (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
					   OR cat_org_id IS NULL )
				   AND cat_type   = \''.$categoryType.'\'
				       '.$sqlCondidtions.'
				 ORDER BY cat_sequence ASC ';
		$result = $databaseObject->query($sql);
		$countCategories = $databaseObject->num_rows($result);
		
		// if no default value was set then try to set one
		if($defaultValue == null)
		{
    		// if only one category exists then select this
		    if($countCategories == 1)
		    {
		        $row = $databaseObject->fetch_array($result);
                $defaultValue = $row['car_id'];
            }
            // if several categories exist than select default category
            elseif($countCategories > 1)
            {
                while($row = $databaseObject->fetch_array($result))
                {
                    if($row['cat_default'] == 1)
                    {
                        $defaultValue = $row['cat_id'];
                    }
                }
            }
		}
        
        // now call method to create selectbox from sql
        $this->addSelectBoxFromSql($id, $label, $databaseObject, $sql, $mandatory, $defaultValue, $setPleaseChoose, $helpTextId, $class);
	}


    /** Add a new small input field with a label to the form.
     *  @param $id         Id of the input field. This will also be the name of the input field.
     *  @param $label      The label of the input field.
	 *  @param $value      A value for the text field. The field will be created with this value.
     *  @param $maxLength  The maximum number of characters that are allowed in this field.
     *  @param $mandatory  A flag if the field is mandatory. Then the specific css classes will be set.
	 *  @param $helpTextId If set a help icon will be shown after the input field and on mouseover the translated text 
	 *                     of this id will be displayed e.g. SYS_ENTRY_MULTI_ORGA.
     *  @param $class      Optional an additional css classname. The class @b admTextInput
     *                     is set as default and need not set with this parameter.
     */
    public function addSmallTextInput($id, $label, $value, $maxLength = 0, $mandatory = false, $helpTextId = null, $class = '')
    {
        $attributes = array('class' => 'admSmallTextInput');
        
        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }

        // set max input length
        if($maxLength > 0)
        {
            $attributes['maxlength'] = $maxLength;
        }
        
        $this->openFieldStructure($id, $label, $mandatory, 'admSmallTextInputRow');
        $this->addInput('text', $id, $id, $value, $attributes);
		$this->setHelpText($helpTextId);
        $this->closeFieldStructure();
    }
    
    /** Add a new button with a custom text to the form. This button could have 
     *  an icon in front of the text. Different to addButton this method adds an
     *  additional @b div around the button and the type of the button is @b submit.
     *  @param $id    Id of the button. This will also be the name of the button.
     *  @param $text  Text of the button
     *  @param $icon  Optional parameter. Path and filename of an icon. 
     *                If set a icon will be shown in front of the text.
     *  @param $link  If set a javascript click event with a page load to this link 
     *                will be attached to the button.
     *  @param $typeSubmit If set to true this button get the type @b submit. This will 
     *                be the default.
     *  @param $class Optional an additional css classname. The class @b admButton
     *                is set as default and need not set with this parameter.
     */
    public function addSubmitButton($id, $text, $icon = '', $link = '', $class = '', $type = 'submit')
    {
        $class .= ' admSubmitButton';
        
        // now add button to form
        $this->addButton($id, $text, $icon, $link, $class, $type);
    }
    
    /** Add a new input field with a label to the form.
     *  @param $id         Id of the input field. This will also be the name of the input field.
     *  @param $label      The label of the input field.
	 *  @param $value      A value for the text field. The field will be created with this value.
     *  @param $maxLength  The maximum number of characters that are allowed in this field.
     *  @param $mandatory  A flag if the field is mandatory. Then the specific css classes will be set.
	 *  @param $helpTextId If set a help icon will be shown after the input field and on mouseover the translated text 
	 *                     of this id will be displayed e.g. SYS_ENTRY_MULTI_ORGA.
     *  @param $class      Optional an additional css classname. The class @b admTextInput
     *                     is set as default and need not set with this parameter.
     */
    public function addTextInput($id, $label, $value, $maxLength = 0, $mandatory = false, $helpTextId = null, $class = '')
    {
        $attributes = array('class' => 'admTextInput');

        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }

        // set max input length
        if($maxLength > 0)
        {
            $attributes['maxlength'] = $maxLength;
        }
        
        $this->openFieldStructure($id, $label, $mandatory, 'admTextInputRow');
        $this->addInput('text', $id, $id, $value, $attributes);
		$this->setHelpText($helpTextId);
        $this->closeFieldStructure();
    }
    
    /** Closes a field structure that was added with the method openFieldStructure.
     */
    protected function closeFieldStructure()
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
    
    /** Creates a html structure for a form field. This structure contains the label
     *  and the div for the form element. After the form element is added the 
     *  method closeFieldStructure must be called.
     *  @param $id        The id of this field structure.
     *  @param $label     The label of the field. This string should already be translated.
     *  @param $mandatory A flag if the field is mandatory. Then the specific css classes will be set.
     *  @param $class     Optional an additional css classname for the row. The class @b admFieldRow
     *                    is set as default and need not set with this parameter.
     */
    protected function openFieldStructure($id, $label, $mandatory = false, $class = '')
    {
        $cssClassRow       = '';
        $cssClassMandatory = '';
		$htmlLabel         = '';

        // set specific css class for this row
        if(strlen($class) > 0)
        {
            $cssClassRow .= ' '.$class;
        }

        // if necessary set css class for a mandatory element
        if($mandatory == true)
        {
			$cssClassMandatory = ' admMandatory';
            $cssClassRow .= $cssClassMandatory;
            $this->flagMandatoryFields = true;
        }
        
        // create a div tag for the field list
        if($this->flagFieldListOpen == false)
        {
            $this->addString('<div class="admFieldList">');
            $this->flagFieldListOpen = true;
        }
		
		if(strlen($label) > 0)
		{
			$htmlLabel = '<label for="'.$id.'">'.$label.':</label>';
		}
        
        // create html
        $this->addString('
        <div class="admFieldRow'.$cssClassRow.'">
            <div class="admFieldLabel'.$cssClassMandatory.'">'.$htmlLabel.'</div>
            <div class="admFieldElement'.$cssClassMandatory.'">');
    }
	
    /** Add a new groupbox to the form. This could be used to group some elements 
     *  together. There is also the option to set a headline to this group box.
     *  @param $id       Id the the groupbox.
     *  @param $headline Optional a headline that will be shown to the user.
     */
    public function openGroupBox($id, $headline = '')
    {
        $this->addString('<div id="'.$id.'" class="admGroupBox">');
        // add headline to groupbox
        if(strlen($headline) > 0)
        {
            $this->addString('<div class="admGroupBoxHeadline">'.$headline.'</div>');
        }
        $this->addString('<div class="admGroupBoxBody">');
    }
	
	/** Add a small help icon to the form at the current element which shows the
	 *  translated text of the text-id on mouseover or when you click on the icon.
	 *  @param $textId A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
	 */
	protected function setHelpText($textId)
	{
		if(strlen($textId) > 0)
		{
			global $g_root_path;
			
			$this->addString('<a class="admIconHelpLink" rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id='.$textId.'&amp;inline=true"><img 
								   onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id='.$textId.'\',this)" 
								   onmouseout="ajax_hideTooltip()" src="'. THEME_PATH. '/icons/help.png" alt="help" title="" /></a>');
		}
	}
    
	/** This method send the whole html code of the form to the browser. Call this method
	 *  if you have finished your form layout. If mandatory fields were set than a notice 
     *  which marker represents the mandatory will be shown before the form.
     *  @param $directOutput If set to @b true (default) the form html will be directly send
     *                       to the browser. If set to @b false the html will be returned.
     *  @return If $directOutput is set to @b false this method will return the html code of the form.
	 */
    public function show($directOutput = true)
    {
		global $gL10n;
		$html = '';

	    // If mandatory fields were set than a notice which marker represents the mandatory will be shown.
        if($this->flagMandatoryFields)
        {
            $html .= '<div class="admMandatoryDefinition"><span>'.$gL10n->get('SYS_MANDATORY_FIELDS').'</span></div>';
        }
		
		// now get whole form html code
        $html .= $this->getHtmlForm();
        
        if($directOutput)
        {
            echo $html;
        }
        else
        {
            return $html;
        }
    }
}
?>