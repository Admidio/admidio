<?php 
/*****************************************************************************/
/** @class HtmlForm
 *  @brief Creates an Admidio specific form with special elements
 *
 *  This class inherits the common HtmlFormBasic class and extends their elements
 *  with custom Admidio form elements. The class should be used to create the 
 *  html part of all Admidio forms. The Admidio elements will contain
 *  the label of fields and some other specific features like a identification
 *  of mandatory fields, help buttons and special css classes for every
 *  element.
 *  @par Examples
 *  @code // create a simple form with one input field and a button
 *  $form = new HtmlForm('simple-form', 'next_page.php');
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

// constants for field property
define('FIELD_DEFAULT', 0);
define('FIELD_MANDATORY', 1);
define('FIELD_DISABLED', 2);

class HtmlForm extends HtmlFormBasic
{
    protected $flagMandatoryFields; ///< Flag if this form has mandatory fields. Then a notice must be written at the end of the form
    protected $flagFieldListOpen;   ///< Flag if a field list was created. This must be closed later
    protected $htmlPage;            ///< A HtmlPage object that will be used to add javascript code or files to the html output page.
    protected $countElements;       ///< Number of elements in this form
    protected $datepickerInitialized; ///< Flag if datepicker is already initialized
    protected $type;                ///< Form type. Possible values are @b default, @b vertical or @b navbar.
    protected $id;                  ///< Id of the form
    protected $buttonGroupOpen;     ///< Flag that indicates if a bootstrap button-group is open and should be closed later
    
    /** Constructor creates the form element
     *  @param $id               Id of the form
     *  @param $action           Optional action attribute of the form
     *  @param $htmlPage         Optional a HtmlPage object that will be used to add javascript code 
     *                           or files to the html output page.
     *  @param $enableFileUpload Set specific parameters that are necessary for file upload with a form
     *  @param $type             Set the form type. Every type has some special features:
     *                           default  : A form that can be used to edit and save data of a database table. The label
     *                                      and the element have a horizontal orientation.
     *                           vertical : A form that can be used to edit and save data but has a vertical orientation.
     *                                      The label is positioned above the form element.
     *                           navbar   : A form that should be used in a navbar. The form content will
     *                                      be send with the 'GET' method and this form should not get a default focus.
     *  @param $class            Optional an additional css classname. The class @b form-horizontal
     *                           is set as default and need not set with this parameter.
     */
    public function __construct($id, $action, $htmlPage = null, $type = 'default', $enableFileUpload = false, $class = null)
    {        
        // navbar forms should send the data as GET
        if($type == 'navbar')
        {
            parent::__construct($action, $id, 'get');
        }
        else
        {
            parent::__construct($action, $id, 'post');            
        }

        $this->flagMandatoryFields   = false;
        $this->flagFieldListOpen     = false;
        $this->countFields           = 0;
        $this->datepickerInitialized = false;
        $this->type                  = $type;
        $this->id                    = $id;
        $this->buttonGroupOpen       = false;
        
        // set specific Admidio css form class
        $this->addAttribute('role', 'form');
        
        if($this->type == 'default')
        {
            $class .= ' form-horizontal form-dialog';
        }
        elseif($this->type == 'vertical')
        {
            $class .= ' form-vertical form-dialog';
        }
        elseif($this->type == 'navbar')
        {
            $class .= ' form-horizontal navbar-form navbar-left';
        }
        
        if(strlen($class) > 0)
        {
            $this->addAttribute('class', $class);            
        }
		
        // Set specific parameters that are necessary for file upload with a form
        if($enableFileUpload == true)
        {
            $this->addAttribute('enctype', 'multipart/form-data');
        }        

        if(is_object($htmlPage))
        {
            $this->htmlPage =& $htmlPage;
        }
        
		// if its not a navbar form and not a static form then first field of form should get focus
        if($this->type != 'navbar' && strlen($action) > 0)
        {
            if(is_object($htmlPage))
            {
                $this->htmlPage->addJavascript('$(".form-dialog:first *:input:enabled:first").focus();', true);
            }
            else
            {
        		$this->addHtml('<script type="text/javascript"><!--
                    $(document).ready(function() { $(".form-dialog:first *:input:enabled:first").focus();});
                //--></script>');
            }
        }
    }
    
    /** Add a new button with a custom text to the form. This button could have 
     *  an icon in front of the text.
     *  @param $id    Id of the button. This will also be the name of the button.
     *  @param $text  Text of the button
     *  @param $icon  Optional parameter. Path and filename of an icon. 
     *                If set a icon will be shown in front of the text.
     *  @param $link  If set a javascript click event with a page load to this link 
     *                will be attached to the button.
     *  @param $onClickText A text that will be shown after a click on this button 
     *                until the next page is loaded. The button will be disabled after click.
     *  @param $class Optional an additional css classname. The class @b admButton
     *                is set as default and need not set with this parameter.
     *  @param $type  Optional a button type could be set. The default is @b button.
     */
    public function addButton($id, $text, $icon = null, $link = null, $onClickText = null, $class = null, $type = 'button')
    {
        $this->countElements++;
        // add text and icon to button
        $value = $text;
        
        if(strlen($icon) > 0)
        {
            $value = '<img src="'.$icon.'" alt="'.$text.'" />'.$value;
        }
        $this->addElement('button');
        $this->addAttribute('class', 'btn btn-default btn-form');
        
        if(strlen($onClickText) > 0)
        {
            $this->addAttribute('data-loading-text', $onClickText);
            $this->addAttribute('autocomplete', 'off');
        }
        
        // add javascript for stateful button and/or 
        // a different link that should be loaded after click
        if(strlen($onClickText) > 0 || strlen($link) > 0)
        {
            $javascriptCode = '';
            
            if(strlen($link) > 0)
            {
                $javascriptCode .= '// disable default form submit
                    self.location.href="'.$link.'";';
            }

            if(strlen($onClickText) > 0)
            {
                $javascriptCode .= '$btn = $(this).button("loading");';
            }
            
            if($type == 'submit')
            {
                $javascriptCode .= '$("#'.$this->id.'").submit();';
            }
            
            $javascriptCode = '$("#'.$id.'").click(function(event) {
                '.$javascriptCode.'
            });';
            
            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if(is_object($this->htmlPage))
            {
                $this->htmlPage->addJavascript($javascriptCode, true);
            }
            else
            {
                $this->addHtml('
                <script type="text/javascript"><!--
                    $(document).ready(function() {
                        '.$javascriptCode.'
                 	}); 	
                //--></script>');
            }
        }
        
        if(strlen($class) > 0)
        {
            $this->addAttribute('class', $class);
        }
        
        $this->addSimpleButton($id, $type, $value, $id);
    }
    
    /** Add a captcha with an input field to the form. The captcha could be a picture with a character code
     *  or a simple mathematical calculation that must be solved.
     *  @param $id         Id of the captcha field. This will also be the name of the captcha field.
     *  @param $type       The of captcha that should be shown. This can be characters in a image or 
     *                     simple mathematical calculation. Possible values are @b pic or @b calc.
     *  @param $class      Optional an additional css classname. The class @b admTextInput
     *                     is set as default and need not set with this parameter.
     */
    public function addCaptcha($id, $type, $class = '')
    {
        global $gL10n, $g_root_path;
        
        $attributes = array('class' => 'captcha');
        $this->countElements++;

        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }

        // add a row with the captcha puzzle
        $this->openControlStructure('captcha_puzzle', null);
        if($type == 'pic')
        {
            $this->addHtml('<img src="'.$g_root_path.'/adm_program/system/classes/captcha.php?id='. time(). '&amp;type=pic" alt="'.$gL10n->get('SYS_CAPTCHA').'" />');
            $captchaLabel = $gL10n->get('SYS_CAPTCHA_CONFIRMATION_CODE');
            $captchaDescription = 'SYS_CAPTCHA_DESCRIPTION';
        }
        elseif($type == 'calc')
        {
            $captcha = new Captcha();
            $this->addHtml($captcha->getCaptchaCalc($gL10n->get('SYS_CAPTCHA_CALC_PART1'),$gL10n->get('SYS_CAPTCHA_CALC_PART2'),
                                                      $gL10n->get('SYS_CAPTCHA_CALC_PART3_THIRD'),$gL10n->get('SYS_CAPTCHA_CALC_PART3_HALF'),$gL10n->get('SYS_CAPTCHA_CALC_PART4')));
            $captchaLabel = $gL10n->get('SYS_CAPTCHA_CALC');
            $captchaDescription = 'SYS_CAPTCHA_CALC_DESCRIPTION';
        }
        $this->closeControlStructure();
        
        // now add a row with a text field where the user can write the solution for the puzzle
        $this->addTextInput($id, $captchaLabel, null, 0, FIELD_MANDATORY, 'text', $captchaDescription, false, null, 'form-control-small');
    }
    
    /** Add a new checkbox with a label to the form.
     *  @param $id         Id of the checkbox. This will also be the name of the checkbox.
     *  @param $label      The label of the checkbox.
	 *  @param $value      A value for the checkbox. The value could only be @b 0 or @b 1. If the value is @b 1 then 
	 *                     the checkbox will be checked when displayed.
     *  @param $property   With this param you can set the following properties: 
     *                     @b FIELD_DEFAULT The field can accept an input.
     *                     @b FIELD_MANDATORY The field will be marked as a mandatory field where the user must insert a value.
     *                     @b FIELD_DISABLED The field will be disabled and could not accept an input.
	 *  @param $helpTextIdLabel  A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set a help icon will be shown after the control label where the user can see the text if he hover over the icon.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
	 *  @param $helpTextIdInline A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set the complete text will be shown after the form element.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon       Opional an icon can be set. This will be placed in front of the checkbox text.
     *  @param $class      Optional an additional css classname. The class @b admCheckbox
     *                     is set as default and need not set with this parameter.
     */
    public function addCheckbox($id, $label, $value, $property = FIELD_DEFAULT, $helpTextIdLabel = null, $helpTextIdInline = null, $icon = null, $class = null)
    {
        global $gL10n;
        $attributes   = array('class' => '');
        $htmlIcon     = '';
        $htmlHelpIcon = '';
        $cssClasses   = 'checkbox';
        $this->countElements++;

        // disable field
        if($property == FIELD_DISABLED)
        {
            $attributes['disabled'] = 'disabled';
        }
		else if ($property == FIELD_MANDATORY)
		{
		    $attributes['required'] = 'required';
		}
        
        // if value = 1 then set checkbox checked
        if($value == '1')
        {
            $attributes['checked'] = 'checked';            
        }

        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }
        
		if(strlen($icon) > 0)
		{
			// create html for icon
			if(strpos(admStrToLower($icon), 'http') === 0 && strValidCharacters($icon, 'url'))
			{
				$htmlIcon = '<img class="icon-information" src="'.$icon.'" title="'.$label.'" alt="'.$label.'" />';
			}
			elseif(admStrIsValidFileName($icon, true))
			{
				$htmlIcon = '<img class="icon-information" src="'.THEME_PATH.'/icons/'.$icon.'" title="'.$label.'" alt="'.$label.'" />';
			}
		}
        
        if($helpTextIdLabel != null)
        {
            $htmlHelpIcon = $this->getHelpTextIcon($helpTextIdLabel);
        }
        
        // now create html for the field
        $this->openControlStructure($id, null, null);
        $this->addHtml('<div class="'.$cssClasses.'"><label>');
        $this->addInput('checkbox', $id, $id, '1', $attributes);
		$this->addHtml($htmlIcon.$label.$htmlHelpIcon.'</label></div>');
		$this->closeControlStructure($helpTextIdInline);
    }
    
    
    /** Add custom html content to the form within the default field structure. The Label will be set 
     *  but instead of an form control you can define any html. If you don't need the field structure
     *  and want to add html then use the method addHtml()
     *  @param $label      The label of the custom content.
     *  @param $content    A simple Text or html that would be placed instead of an form element.
     *  @param $referenceId Optional the id of a form control if this is defined within the custom content
	 *  @param $helpTextIdLabel  A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set a help icon will be shown after the control label where the user can see the text if he hover over the icon.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
	 *  @param $helpTextIdInline A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set the complete text will be shown after the form element.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon       Opional an icon can be set. This will be placed in front of the checkbox text.
     *  @param $class      Optional an additional css classname.
     */
    public function addCustomContent($label, $content, $referenceId = null, $helpTextIdLabel = null, $helpTextIdInline = null, $icon = null, $class = null)
    {
        $this->countElements++;
    
        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }

        $this->openControlStructure($referenceId, $label, FIELD_DEFAULT, $helpTextIdLabel, $icon, 'form-custom-content');
        $this->addHtml($content);
        $this->closeControlStructure($helpTextIdInline);
    }
    
    /** Add a line with a custom description to the form. No form elements will be 
     *  displayed in this line.
     *  @param $text The (html) text that should be displayed.
     */
    public function addDescription($text)
    {
        $this->addHtml('<p>'.$text.'</p>');
    }
	
    /** Add a new CKEditor element to the form. 
     *  @param $id         Id of the password field. This will also be the name of the password field.
     *  @param $label      The label of the password field.
	 *  @param $value      A value for the editor field. The editor will contain this value when created.
     *  @param $property   With this param you can set the following properties: 
     *                     @b FIELD_DEFAULT The field can accept an input.
     *                     @b FIELD_MANDATORY The field will be marked as a mandatory field where the user must insert a value.
     *  @param $toolbar    Optional set a predefined toolbar for the editor. Possible values are 
     *                     @b AdmidioDefault, @b Admidio Guestbook, @b AdmidioEcard and @b AdmidioPlugin_WC
     *  @param $height     Optional set the height in pixel of the editor. The default will be 300px.
	 *  @param $helpTextId A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                     If set a help icon will be shown where the user can see the text if he hover over the icon.
     *                     If you need an additional parameter for the text you can add an array. The first entry must
     *                     be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon       Opional an icon can be set. This will be placed in front of the label.
     *  @param $labelVertical If set to @b true (default) then the label will be display above the control and the control get a width of 100%.
     *                        Otherwise the label will be displayed in front of the control.
     *  @param $class      Optional an additional css classname. The class @b admTextInput
     *                     is set as default and need not set with this parameter.
     */
	public function addEditor($id, $label, $value, $property = FIELD_DEFAULT, $toolbar = 'AdmidioDefault', $height = '300px', 
	                          $helpTextId = null, $icon = null, $labelVertical = true, $class = null)
	{
        $this->countElements++;
        $attributes = array('class' => 'editor');
        $flagLabelVertical = $this->type;
        
        if($labelVertical == true)
        {
            $this->type = 'vertical';
        }
		
		if ($property == FIELD_MANDATORY)
		{
		    $attributes['required'] = 'required';
		}

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

        $this->openControlStructure($id, $label, $property, $helpTextId, $icon, 'form-group-editor');
		$this->addHtml('<div class="'.$attributes['class'].'">'.$ckEditor->createEditor($id, $value, $toolbar, $height).'</div>');
        $this->closeControlStructure();
        
        $this->type = $flagLabelVertical;
	}
    
    /** Add a field for file upload. If necessary multiple files could be uploaded. The fields for multiple upload could 
     *  be added dynamically to the form by the user.
     *  @param $id            Id of the input field. This will also be the name of the input field.
     *  @param $label         The label of the input field.
	 *  @param $maxUploadSize The size in byte that could be maximum uploaded
	 *  @param $enableMultiUploads If set to true a button will be added where the user can 
	 *                             add new upload fields to upload more than one file.
	 *  @param $multiUploadLabel   The label for the button who will add new upload fields to the form.
	 *  @param $hideUploadField    Hide the upload field if multi uploads are enabled. Then the first
	 *                             upload field will be shown if the user will click the multi upload button.
     *  @param $property      With this param you can set the following properties: 
     *                        @b FIELD_DEFAULT The field can accept an input.
     *                        @b FIELD_MANDATORY The field will be marked as a mandatory field where the user must insert a value.
     *                        @b FIELD_DISABLED The field will be disabled and could not accept an input.
	 *  @param $helpTextId    A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                        If set a help icon will be shown where the user can see the text if he hover over the icon.
     *                        If you need an additional parameter for the text you can add an array. The first entry must
     *                        be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon       Opional an icon can be set. This will be placed in front of the label.
     *  @param $class         Optional an additional css classname. The class @b admTextInput       
     *                        is set as default and need not set with this parameter.
     */
    public function addFileUpload($id, $label, $maxUploadSize, $enableMultiUploads = false, $multiUploadLabel = null, 
                                  $hideUploadField = false, $property = FIELD_DEFAULT, $helpTextId = null, $icon = null, $class = '')
    {
        $attributes = array('class' => 'form-control');
        $this->countElements++;

        // disable field
        if($property == FIELD_DISABLED)
        {
            $attributes['disabled'] = 'disabled';
        }
		else if ($property == FIELD_MANDATORY)
		{
		    $attributes['required'] = 'required';
		}

        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }
        
        // if multiple uploads are enabled then add javascript that will
        // dynamically add new upload fields to the form
        if($enableMultiUploads)
        {
            $javascriptCode = '
        		$(".add-attachement-link").css("cursor", "pointer");
        		
        		// add new line to add new attachment to this mail
        		$(".add-attachement-link").click(function () {
        			newAttachment = document.createElement("input");
        			$(newAttachment).attr("type", "file");
        			$(newAttachment).attr("name", "userfile[]");
        			$(newAttachment).attr("class", "'.$attributes['class'].'");
        			$(newAttachment).hide();
        			$("#adm_add_attachment").before(newAttachment);
        			$(newAttachment).show("slow");
        		});';
            
            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if(is_object($this->htmlPage))
            {
                $this->htmlPage->addJavascript($javascriptCode, true);
            }
            else
            {
                $this->addHtml('
                <script type="text/javascript"><!--
                    $(document).ready(function() {
                        '.$javascriptCode.'
                 	}); 	
                //--></script>');
            }
        }
        
        $this->openControlStructure($id, $label, $property, $helpTextId, $icon, 'form-upload');
        $this->addInput('hidden', 'MAX_FILE_SIZE', 'MAX_FILE_SIZE', $maxUploadSize);
        
        // if multi uploads are enabled then the file upload field could be hidden
        // until the user will click on the button to add a new upload field
        if($hideUploadField == false || $enableMultiUploads == false)
        {
            $this->addInput('file', 'userfile[]', null, null, $attributes);
        }

        if($enableMultiUploads)
        {
            // show button to add new upload field to form
            $this->addHtml('
                <span id="adm_add_attachment" style="display: block;">
    				<a class="icon-text-link add-attachement-link"><img
                        src="'. THEME_PATH. '/icons/add.png" alt="'.$multiUploadLabel.'" />'.$multiUploadLabel.'</a>
                </span>');
        }
        $this->closeControlStructure();
    }

	/** Add a simple line to the form. This could be used to structure a form.
	 *  The line has only a visual effect.
	 */
	public function addLine()
	{
        $this->addHtml('<hr />');
	}
	
    
    /** Add a new textarea field with a label to the form.
     *  @param $id         Id of the input field. This will also be the name of the input field.
     *  @param $label      The label of the input field.
	 *  @param $value      A value for the text field. The field will be created with this value.
     *  @param $rows       The number of rows that the textarea field should have.
     *  @param $maxLength  The maximum number of characters that are allowed in this field. If set
     *                     then show a counter how many characters still available
     *  @param $property   With this param you can set the following properties: 
     *                     @b FIELD_DEFAULT The field can accept an input.
     *                     @b FIELD_MANDATORY The field will be marked as a mandatory field where the user must insert a value.
     *                     @b FIELD_DISABLED The field will be disabled and could not accept an input.
	 *  @param $helpTextIdLabel  A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set a help icon will be shown after the control label where the user can see the text if he hover over the icon.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
	 *  @param $helpTextIdInline A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set the complete text will be shown after the form element.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon       Opional an icon can be set. This will be placed in front of the label.
     *  @param $class      Optional an additional css classname. The class @b admTextInput
     *                     is set as default and need not set with this parameter.
     */
    public function addMultilineTextInput($id, $label, $value, $rows, $maxLength = 0, $property = FIELD_DEFAULT, 
                                          $helpTextIdLabel = null, $helpTextIdInline = null, $icon = null, $class = null)
    {
        global $gL10n, $g_root_path;

        $attributes = array('class' => 'form-control');
        $this->countElements++;

        // disable field
        if($property == FIELD_DISABLED)
        {
            $attributes['disabled'] = 'disabled';
        }
		else if ($property == FIELD_MANDATORY)
		{
		    $attributes['required'] = 'required';
		}

        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }
        
        if($maxLength > 0)
        {
			$attributes['maxlength'] = $maxLength;
			
            // if max field length is set then show a counter how many characters still available
            $javascriptCode = '
                $(\'#'.$id.'\').NobleCount(\'#'.$id.'_counter\',{
                    max_chars: '.$maxLength.',
                    on_negative: \'systeminfoBad\',
                    block_negative: true
                });';

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if(is_object($this->htmlPage))
            {
                $this->htmlPage->addJavascriptFile($g_root_path.'/adm_program/libs/jquery/jquery.noblecount.min.js');
                $this->htmlPage->addJavascript($javascriptCode, true);
            }
            else
            {
                $this->addHtml('<script type="text/javascript">
                        $(document).ready(function(){
                            '.$javascriptCode.'
                        });
                    </script>');
            }
        }
        
        $this->openControlStructure($id, $label, $property, $helpTextIdLabel, $icon);
        $this->addTextArea($id, $rows, 80, $value, $id, $attributes);
        if($maxLength > 0)
        {
            // if max field length is set then show a counter how many characters still available
            $this->addHtml('<small class="characters-count">('.$gL10n->get('SYS_STILL_X_CHARACTERS', '<span id="'.$id.'_counter" class="">255</span>').')</small>');
        }
        $this->closeControlStructure($helpTextIdInline);
    }
    
    /** Add a new radio button with a label to the form. The radio button could have different status 
     *  which could be defined with an array.
     *  @param $id           Id of the radio button. This will also be the name of the radio button.
     *  @param $label        The label of the radio button.
	 *  @param $values       Array with all entries of the radio button; 
	 *                       Array key will be the internal value of the entry
	 *                       Array value will be the visual value of the entry
     *  @param $property     With this param you can set the following properties: 
     *                       @b FIELD_DEFAULT The field can accept an input.
     *                       @b FIELD_MANDATORY The field will be marked as a mandatory field where the user must insert a value.
     *                       @b FIELD_DISABLED The field will be disabled and could not accept an input.
     *  @param $defaultValue This is the value of that radio button that is preselected.
     *  @param $setDummyButton If set to true than one radio with no value will be set in front of the other array. 
     *                         This could be used if the user should also be able to set no radio to value.
	 *  @param $helpTextId   A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                       If set a help icon will be shown where the user can see the text if he hover over the icon.
     *                       If you need an additional parameter for the text you can add an array. The first entry must
     *                       be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon         Opional an icon can be set. This will be placed in front of the label.
     *  @param $class        Optional an additional css classname. The class @b admRadioInput
     *                       is set as default and need not set with this parameter.
     */
    public function addRadioButton($id, $label, $values, $property = FIELD_DEFAULT, $defaultValue = '', $setDummyButton = false, $helpTextId = null, $icon = null, $class = '')
    {
        $attributes = array('class' => '');
        $this->countElements++;

        // disable field
        if($property == FIELD_DISABLED)
        {
            $attributes['disabled'] = 'disabled';
        }
		else if ($property == FIELD_MANDATORY)
		{
		    $attributes['required'] = 'required';
		}
        
        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }
        
        $this->openControlStructure($id, $label, $property, $helpTextId, $icon);
        
        // set one radio button with no value will be set in front of the other array.
        if($setDummyButton == true)
        {
	        if(strlen($defaultValue) == 0)
	        {
	            $attributes['checked'] = 'checked';
	        }
            
	        $this->addHtml('<label for="'.($id.'_0').'" class="radio-inline">');
	        $this->addInput('radio', $id, ($id.'_0'), null, $attributes);
	        $this->addHtml('---</label>');
        }
        
		// for each entry of the array create an input radio field
		foreach($values as $key => $value)
		{
	        unset($attributes['checked']);
	        
	        if($defaultValue == $key)
	        {
	            $attributes['checked'] = 'checked';
	        }
	        
	        $this->addHtml('<label for="'.($id.'_'.$key).'" class="radio-inline">');
	        $this->addInput('radio', $id, ($id.'_'.$key), $key, $attributes);
	        $this->addHtml($value.'</label>');
		}
        
        $this->closeControlStructure();
    }
    
    /** Add a new selectbox with a label to the form. The selectbox could have
     *  different values and a default value could be set.
     *  @param $id         Id of the selectbox. This will also be the name of the selectbox.
     *  @param $label      The label of the selectbox.
	 *  @param $values     Array with all entries of the select box; 
	 *                     Array key will be the internal value of the entry
	 *                     Array value will be the visual value of the entry
     *  @param $property   With this param you can set the following properties: 
     *                     @b FIELD_DEFAULT The field can accept an input.
     *                     @b FIELD_MANDATORY The field will be marked as a mandatory field where the user must insert a value.
     *                     @b FIELD_DISABLED The field will be disabled and could not accept an input.
     *  @param $defaultValue     This is the value the selectbox shows when loaded. If @b multiselect is activated than
     *                           an array with all default values could be set.
     *  @param $showContextDependentFirstEntry  If set to @b true the select box will get an additional first entry.
     *                           If FIELD_MANDATORY is set than "Please choose" will be the first entry otherwise
     *                           an emptry entry will be added so you must not select something.
     *  @param $multiselect      If set to @b true than the jQuery plugin Select2 will be used to create a selectbox
     *                           where the user could select multiple values from the selectbox. Then an array will be 
     *                           created within the $_POST array.
	 *  @param $helpTextIdLabel  A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set a help icon will be shown after the control label where the user can see the text if he hover over the icon.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
	 *  @param $helpTextIdInline A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set the complete text will be shown after the form element.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon       Opional an icon can be set. This will be placed in front of the label.
     *  @param $class      Optional an additional css classname. The class @b admSelectbox
     *                     is set as default and need not set with this parameter.
     */
    public function addSelectBox($id, $label, $values, $property = FIELD_DEFAULT, $defaultValue = null, $showContextDependentFirstEntry = true, 
                                 $multiselect = false, $helpTextIdLabel = null, $helpTextIdInline = null, $icon = null, $class = null)
    {
        global $gL10n, $g_root_path, $gPreferences;

        $attributes = array('class' => 'form-control');
        $name       = $id;
        $this->countElements++;

        // disable field
        if($property == FIELD_DISABLED)
        {
            $attributes['disabled'] = 'disabled';
        }
		else if ($property == FIELD_MANDATORY)
		{
		    $attributes['required'] = 'required';
		}
        
        if($multiselect == true)
        {
            $attributes['multiple'] = 'multiple';
            $name                   = $id.'[]';
            
            if($defaultValue != null && is_array($defaultValue) == false)
            {
                $defaultValue = array($defaultValue);
            }
        }
        
        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }
        
        // now create html for the field
        $this->openControlStructure($id, $label, $property, $helpTextIdLabel, $icon);
        
        $this->addSelect($name, $id, $attributes);

        // add an additional first entry to the select box and set this as preselected if necessary
        if($showContextDependentFirstEntry == true)
        {
            $defaultEntry = false;
            if($defaultValue == null)
            {
                $defaultEntry = true;
            }
    
            if($property == FIELD_MANDATORY)
            {
                $this->addOption(null, '- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -', null, $defaultEntry);
            }
            else
            {
                $this->addOption('', ' ', null, $defaultEntry);            
            }
        }

        $value = reset($values);
        $optionGroup = null;
        
        for($arrayCount = 0; $arrayCount < count($values); $arrayCount++)
        {
            // create entry in html
            $defaultEntry = false;
            
            // if each array element is an array then create option groups
            if(is_array($value))
            {
                // add optiongroup if neccessary
    			if($optionGroup != $values[$arrayCount][2])
    			{
    				if($optionGroup != null)
    				{
    					$this->closeOptionGroup();
    				}
    				$this->addOptionGroup($values[$arrayCount][2]);
    				$optionGroup = $values[$arrayCount][2];
    			}
    			
    			// add option
                if($multiselect == false && $defaultValue == $values[$arrayCount][0])
                {
                    $defaultEntry = true;
                }
                
                $this->addOption($values[$arrayCount][0], $values[$arrayCount][1], null, $defaultEntry);
            }
            else
            {
                // array has only key and value then create a normal selectbox without optiongroups
                if($multiselect == false && $defaultValue == key($values))
                {
                    $defaultEntry = true;
                }
                
                $this->addOption(key($values), $value, null, $defaultEntry);
            }

            $value = next($values);
        }
        
        if($optionGroup != null)
        {
            $this->closeOptionGroup();
        }
        
        if($multiselect == true)
        {
            $javascriptCode = '$("#'.$id.'").select2();';

            // add default values to multi select
            if(array_count_values($defaultValue) > 0)
            {
                $htmlDefaultValues = '';
                foreach($defaultValue as $key => $htmlDefaultValue)
                {
                    $htmlDefaultValues .= '"'.$htmlDefaultValue.'",';
                }
                $htmlDefaultValues = substr($htmlDefaultValues, 0, strlen($htmlDefaultValues)-1);
                
                $javascriptCode .= ' $("#'.$id.'").val(['.$htmlDefaultValues.']).trigger("change");';
            }

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if(is_object($this->htmlPage))
            {
                $this->htmlPage->addCssFile($g_root_path.'/adm_program/libs/select2/select2.css');
                $this->htmlPage->addCssFile($g_root_path.'/adm_program/libs/select2/select2-bootstrap.css');
                $this->htmlPage->addJavascriptFile($g_root_path.'/adm_program/libs/select2/select2.min.js');
                $this->htmlPage->addJavascriptFile($g_root_path.'/adm_program/libs/select2/select2_locale_'.$gPreferences['system_language'].'.js');
                $this->htmlPage->addJavascript($javascriptCode, true);
            }
            else
            {
                $this->addHtml('<script type="text/javascript">'.$javascriptCode.'</script>');
            }
        }
        
        $this->closeSelect();
        $this->closeControlStructure($helpTextIdInline);
    }
    
    /** Add a new selectbox with a label to the form. The selectbox get their data from a sql statement.
     *  You can create any sql statement and this method should create a selectbox with the found data.
     *  The sql must contain at least two columns. The first column represents the value and the second 
     *  column represents the label of each option of the selectbox. Optional you can add a third column
     *  to the sql statement. This column will be used as label for an optiongroup. Each time the value
     *  of the third column changed a new optiongroup will be created.
     *  @par Examples
     *  @code // create a selectbox with all profile fields of a specific category
     *  $sql = 'SELECT usf_id, usf_name FROM '.TBL_USER_FIELDS.' WHERE usf_cat_id = 4711'
     *  $form = new HtmlForm('simple-form', 'next_page.php');
     *  $form->addSelectBoxFromSql('admProfileFieldsBox', $gL10n->get('SYS_FIELDS'), $gDb, $sql, false, $gL10n->get('SYS_SURNAME'), true);
     *  $form->show();@endcode
     *  @param $id              Id of the selectbox. This will also be the name of the selectbox.
     *  @param $label            The label of the selectbox.
     *  @param $databaseObject   A Admidio database object that contains a valid connection to a database
	 *  @param $sql              Any SQL statement that return 2 columns. The first column will be the internal value of the
     *                           selectbox item and will be submitted with the form. The second column represents the
     *                           displayed value of the item. Each row of the result will be a new selectbox entry.
     *  @param $property         With this param you can set the following properties: 
     *                           @b FIELD_DEFAULT The field can accept an input.
     *                           @b FIELD_MANDATORY The field will be marked as a mandatory field where the user must insert a value.
     *                           @b FIELD_DISABLED The field will be disabled and could not accept an input.
     *  @param $defaultValue     This is the value the selectbox shows when loaded. If @b multiselect is activated than
     *                           an array with all default values could be set.
     *  @param $showContextDependentFirstEntry  If set to @b true the select box will get an additional first entry.
     *                           If FIELD_MANDATORY is set than "Please choose" will be the first entry otherwise
     *                           an emptry entry will be added so you must not select something.
     *  @param $multiselect      If set to @b true than the jQuery plugin Select2 will be used to create a selectbox
     *                           where the user could select multiple values from the selectbox. Then an array will be 
     *                           created within the $_POST array.
	 *  @param $helpTextIdLabel  A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set a help icon will be shown after the control label where the user can see the text if he hover over the icon.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
	 *  @param $helpTextIdInline A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set the complete text will be shown after the form element.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon             Opional an icon can be set. This will be placed in front of the label.
     *  @param $class            Optional an additional css classname. The class @b admSelectbox
     *                           is set as default and need not set with this parameter.
     */
    public function addSelectBoxFromSql($id, $label, $databaseObject, $sql, $property = FIELD_DEFAULT, $defaultValue= null, $showContextDependentFirstEntry = true, 
                                        $multiselect = false, $helpTextIdLabel = null, $helpTextIdInline = null, $icon = null, $class = null)
    {
        $selectboxEntries = array();
    
        // execute the sql statement
        $result = $databaseObject->query($sql);
        
        // create array from sql result
        while($row = $databaseObject->fetch_array($result))
        {
            // if result has 3 columns then create a array in array
            if(array_key_exists(2, $row))
            {
                $selectboxEntries[] = array($row[0], $row[1], $row[2]);
            }
            else
            {
                $selectboxEntries[$row[0]] = $row[1];
            }
        }
        
        // now call default method to create a selectbox
        $this->addSelectBox($id, $label, $selectboxEntries, $property, $defaultValue, $showContextDependentFirstEntry, $multiselect, $helpTextIdLabel, $helpTextIdInline, $icon, $class);
    }
    
    /** Add a new selectbox with a label to the form. The selectbox could have
     *  different values and a default value could be set.
     *  @param $id          Id of the selectbox. This will also be the name of the selectbox.
     *  @param $label       The label of the selectbox.
	 *  @param $xmlFile     Serverpath to the xml file
	 *  @param $xmlValueTag Name of the xml tag that should contain the internal value of a selectbox entry
	 *  @param $xmlViewTag  Name of the xml tag that should contain the visual value of a selectbox entry
     *  @param $property    With this param you can set the following properties: 
     *                      @b FIELD_DEFAULT The field can accept an input.
     *                      @b FIELD_MANDATORY The field will be marked as a mandatory field where the user must insert a value.
     *                      @b FIELD_DISABLED The field will be disabled and could not accept an input.
     *  @param $defaultValue     This is the value the selectbox shows when loaded. If @b multiselect is activated than
     *                           an array with all default values could be set.
     *  @param $showContextDependentFirstEntry  If set to @b true the select box will get an additional first entry.
     *                           If FIELD_MANDATORY is set than "Please choose" will be the first entry otherwise
     *                           an emptry entry will be added so you must not select something.
     *  @param $multiselect      If set to @b true than the jQuery plugin Select2 will be used to create a selectbox
     *                           where the user could select multiple values from the selectbox. Then an array will be 
     *                           created within the $_POST array.
	 *  @param $helpTextIdLabel  A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set a help icon will be shown after the control label where the user can see the text if he hover over the icon.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
	 *  @param $helpTextIdInline A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set the complete text will be shown after the form element.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon        Opional an icon can be set. This will be placed in front of the label.
     *  @param $class       Optional an additional css classname. The class @b admSelectbox
     *                      is set as default and need not set with this parameter.
     */
    public function addSelectBoxFromXml($id, $label, $xmlFile, $xmlValueTag, $xmlViewTag, $property = FIELD_DEFAULT, $defaultValue= null, $showContextDependentFirstEntry = true, 
                                        $multiselect = false, $helpTextIdLabel = null, $helpTextIdInline = null, $icon = null, $class = null)
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
        $this->addSelectBox($id, $label, $selectboxEntries, $property, $defaultValue, $showContextDependentFirstEntry, $multiselect, $helpTextIdLabel, $helpTextIdInline, $icon, $class);
    }
    
    /** Add a new selectbox with a label to the form. The selectbox get their data from table adm_categories. You must
     *  define the category type (roles, dates, links ...). All categories of this type will be shown.
     *  @param $id                 Id of the selectbox. This will also be the name of the selectbox.
     *  @param $label              The label of the selectbox.
     *  @param $databaseObject     A Admidio database object that contains a valid connection to a database
	 *  @param $categoryType	   Type of category ('DAT', 'LNK', 'ROL', 'USF') that should be shown
	 *  @param $selectboxModus     The selectbox could be shown in 2 different modus.
	 *                             @b EDIT_CATEGORIES First entry will be "Please choose" and default category will be preselected.
	 *                             @b FILTER_CATEGORIES First entry will be "All" and only categories with childs will be shown.
     *  @param $property   With this param you can set the following properties: 
     *                     @b FIELD_DEFAULT The field can accept an input.
     *                     @b FIELD_MANDATORY The field will be marked as a mandatory field where the user must insert a value.
     *                     @b FIELD_DISABLED The field will be disabled and could not accept an input.
	 *  @param $defaultValue	   Id of category that should be selected per default.
	 *  @param $helpTextIdLabel    A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                             If set a help icon will be shown after the control label where the user can see the text if he hover over the icon.
     *                             If you need an additional parameter for the text you can add an array. The first entry must
     *                             be the unique text id and the second entry will be a parameter of the text id.     
	 *  @param $helpTextIdInline   A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                             If set the complete text will be shown after the form element.
     *                             If you need an additional parameter for the text you can add an array. The first entry must
     *                             be the unique text id and the second entry will be a parameter of the text id.     
	 *  @param $showSystemCategory Show user defined and system categories
     *  @param $class              Optional an additional css classname. The class @b admSelectbox
     *                             is set as default and need not set with this parameter.
	 */
	public function addSelectBoxForCategories($id, $label, $databaseObject, $categoryType, $selectboxModus, $property = FIELD_DEFAULT, $defaultValue = 0, 
	                                          $helpTextIdLabel = null, $helpTextIdInline = null, $showSystemCategory = true, $class = '')
	{
		global $gCurrentOrganization, $gValidLogin, $gL10n;

        $sqlTables        = TBL_CATEGORIES;
        $sqlCondidtions   = '';
        $showContextDependentFirstEntry = true;
        $categoriesArray  = array();

		// create sql conditions if category must have child elements
		if($selectboxModus == 'FILTER_CATEGORIES')
		{
            $showContextDependentFirstEntry  = false;
            
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
		
		// if only one category exists then select this
	    if($countCategories == 1 && $defaultValue == null)
	    {
	        $row = $databaseObject->fetch_array($result);
            $defaultValue = $row['cat_id'];
            $categoriesArray['cat_id'] = $row['cat_name'];
        }
        // if several categories exist than select default category
        elseif($countCategories > 1)
        {
    		if($selectboxModus == 'FILTER_CATEGORIES')
    		{
                $categoriesArray[0] = $gL10n->get('SYS_ALL');
    		}
        
            while($row = $databaseObject->fetch_array($result))
            {
                $categoriesArray[$row['cat_id']] = $row['cat_name'];

                if($row['cat_default'] == 1 && $defaultValue == null)
                {
                    $defaultValue = $row['cat_id'];
                }
            }
        }
        
        // now call method to create selectbox from array
        $this->addSelectBox($id, $label, $categoriesArray, $property, $defaultValue, $showContextDependentFirstEntry, $helpTextIdLabel, $helpTextIdInline, null, $class);
	}
    
    /** Add a new static control to the form. A static control is only a simple text instead of an input field. This 
     *  could be used if the value should not be changed by the user.
     *  @param $id         Id of the static control. This will also be the name of the static control.
     *  @param $label      The label of the static control.
	 *  @param $value      A value of the static control. The control will be created with this value.
	 *  @param $helpTextIdLabel  A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set a help icon will be shown after the control label where the user can see the text if he hover over the icon.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
	 *  @param $helpTextIdInline A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set the complete text will be shown after the form element.
     *                           If you need an additional parameter for the text you can add an array. The first entry must
     *                           be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon       Opional an icon can be set. This will be placed in front of the label.
     *  @param $sclass      Optional an additional css classname. The class @b form-control-static
     *                     is set as default and need not set with this parameter.
     */
    public function addStaticControl($id, $label, $value, $helpTextIdLabel = null, $helpTextIdInline = null, $icon = null, $class = '')
    {
        $attributes = array('class' => 'form-control-static');
        $this->countElements++;

        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }

        // now create html for the field
        $this->openControlStructure(null, $label, FIELD_DEFAULT, $helpTextIdLabel, $icon);
        $this->addHtml('<p class="form-control-static">'.$value.'</p>');
        $this->closeControlStructure($helpTextIdInline);
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
     *  @param $onClickText A text that will be shown after a click on this button 
     *                until the next page is loaded. The button will be disabled after click.
     *  @param $class Optional an additional css classname. The class @b admButton
     *                is set as default and need not set with this parameter.
     *  @param $type  If set to true this button get the type @b submit. This will 
     *                be the default.
     */
    public function addSubmitButton($id, $text, $icon = null, $link = null, $onClickText = null, $class = null, $type = 'submit')
    {
        $class .= ' btn-primary btn-form';

        // now add button to form
        $this->addButton($id, $text, $icon, $link, $onClickText, $class, $type);
        
        if($this->buttonGroupOpen == false)
        {
            $this->addHtml('<div class="form-alert" style="display: none">&nbsp;</div>');
        }
    }
    
    /** Add a new input field with a label to the form.
     *  @param $id         Id of the input field. This will also be the name of the input field.
     *  @param $label      The label of the input field.
	 *  @param $value      A value for the text field. The field will be created with this value.
     *  @param $conditions Here you can define conditions to the field depending on the field $type
     *                     $type 'text'   = The maximum number of characters that are allowed in this field.
     *                     $type 'number' = An array that contains the min number, the max number and the steps
     *                                      e.g. array(0, 10000, 5) then a value between 0 and 10000 is allowed
     *                                      in steps of 5:   5, 10, 15 ...
     *  @param $property   With this param you can set the following properties: 
     *                     @b FIELD_DEFAULT The field can accept an input.
     *                     @b FIELD_MANDATORY The field will be marked as a mandatory field where the user must insert a value.
     *                     @b FIELD_DISABLED The field will be disabled and could not accept an input.
     *  @param $type       Set the type if the field. Default will be @b text. Possible values are @b text, @b number, @b date 
     *                     or @datetime. If @b date or @datetime are set than a small calendar will be shown if the date field
     *                     will be selected.
	 *  @param $helpTextIdLabel    A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                             If set a help icon will be shown after the control label where the user can see the text if he hover over the icon.
     *                             If you need an additional parameter for the text you can add an array. The first entry must
     *                             be the unique text id and the second entry will be a parameter of the text id.     
	 *  @param $helpTextIdInline   A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                             If set the complete text will be shown after the form element.
     *                             If you need an additional parameter for the text you can add an array. The first entry must
     *                             be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon       Opional an icon can be set. This will be placed in front of the label.
     *  @param $class      Optional an additional css classname. The class @b admTextInput
     *                     is set as default and need not set with this parameter.
     */
    public function addTextInput($id, $label, $value, $conditions = 0, $property = FIELD_DEFAULT, $type = 'text', 
                                 $helpTextIdLabel = null, $helpTextIdInline = null, $icon = null, $class = '')
    {
        global $gL10n, $gPreferences, $g_root_path;
        
        $this->countElements++;

        $attributes = array('class' => 'form-control');

        // set max input length
        if($type == 'text' && $conditions > 0)
        {
            $attributes['maxlength'] = $conditions;
        }
        elseif($type == 'number' && is_array($conditions))
        {
            $attributes['min'] = $conditions[0];
            $attributes['max'] = $conditions[1];
            $attributes['step'] = $conditions[2];
        }

        // disable field
        if($property == FIELD_DISABLED)
        {
            $attributes['disabled'] = 'disabled';
        }
		else if ($property == FIELD_MANDATORY)
		{
		    $attributes['required'] = 'required';
		}

        // set specific css class for this field
        if(strlen($class) > 0)
        {
            $attributes['class'] .= ' '.$class;
        }
        
        // add a nice modern datepicker to date inputs
        if($type == 'date' || $type == 'datetime')
        {
            $datepickerOptions = '';
            
            $attributes['data-provide'] = 'datepicker';
            $javascriptCode             = '';
            

            if($this->datepickerInitialized == false)
            {
                $javascriptCode = '
                    $("input[data-provide=\'datepicker\']").datepicker({
                        language: "'.$gPreferences['system_language'].'",
                        format: "'.DateTimeExtended::getDateFormatForDatepicker($gPreferences['system_date']).'",
                        '.$datepickerOptions.'todayHighlight: "true",
                        autoclose: "true"
                    });';
                $this->datepickerInitialized = true;
            }

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if(is_object($this->htmlPage))
            {
                $this->htmlPage->addCssFile($g_root_path.'/adm_program/libs/bootstrap-datepicker/css/datepicker3.css');
                $this->htmlPage->addJavascriptFile($g_root_path.'/adm_program/libs/bootstrap-datepicker/js/bootstrap-datepicker.js');
                $this->htmlPage->addJavascriptFile($g_root_path.'/adm_program/libs/bootstrap-datepicker/js/locales/bootstrap-datepicker.'.$gPreferences['system_language'].'.js');
                $this->htmlPage->addJavascript($javascriptCode, true);
            }
            else
            {
                $this->addHtml('<script type="text/javascript">'.$javascriptCode.'</script>');
            }
        }
        
        // now create html for the field
        $this->openControlStructure($id, $label, $property, $helpTextIdLabel, $icon);
        
        // if datetime then add a time field behind the date field
        if($type == 'datetime')
        {
            // first try to split datetime to a date and a time value
            $datetime = new DateTimeExtended($value, $gPreferences['system_date'].' '.$gPreferences['system_time']);
            $dateValue = $datetime->format($gPreferences['system_date']);
            $timeValue = $datetime->format($gPreferences['system_time']);
        
            // now add a date and a time field to the form
            $attributes['class']    .= ' datetime-date-control';
            $this->addInput('text', $id, $id, $dateValue, $attributes);  
            $attributes['class']    .= ' datetime-time-control';
            $attributes['maxlength'] = '5';
            $attributes['data-provide'] = '';
            $this->addInput('text', $id.'_time', $id.'_time', $timeValue, $attributes);        
        }
        else
        {
            // a date type has some problems with chrome so we set it as text type
            if($type == 'date')
            {
                $type = 'text';
            }
            $this->addInput($type, $id, $id, $value, $attributes);        
        }
        $this->closeControlStructure($helpTextIdInline);
    }
    
    /** Close an open bootstrap btn-group
     */
    public function closeButtonGroup()
    {
        $this->buttonGroupOpen = false;
        $this->addHtml('</div>
        <div class="form-alert" style="display: none">&nbsp;</div>');
    }
    
    /** Closes a field structure that was added with the method openControlStructure.
     *  @param $helpTextId A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                     If set the complete text will be shown after the form element.
     *                     If you need an additional parameter for the text you can add an array. The first entry must
     *                     be the unique text id and the second entry will be a parameter of the text id.     
     */
    protected function closeControlStructure($helpTextId = null)
    {
        global $gL10n;
        
        if($helpTextId != null)
        {
            if(is_array($helpTextId))
            {
                // if text is a translation-id then translate it
    			if(strpos($helpTextId[1], '_') == 3)
                {
                    $this->addHtml('<div class="help-block">'.$gL10n->get($helpTextId[0], $gL10n->get($helpTextId[1])).'</div>');
                }
                else
                {
                    $this->addHtml('<div class="help-block">'.$gL10n->get($helpTextId[0], $helpTextId[1]).'</div>');
                }
            }
            else
            {
                // if text is a translation-id then translate it
    			if(strpos($helpTextId, '_') == 3)
                {
                    $this->addHtml('<div class="help-block">'.$gL10n->get($helpTextId).'</div>');
                }
                else
                {
                    $this->addHtml('<div class="help-block">'.$helpTextId.'</div>');                    
                }
            }
        }
        
        if($this->type == 'vertical' || $this->type == 'navbar')
        {
            $this->addHtml('</div>');            
        }
        else
        {
            $this->addHtml('</div></div>');
        }
    }
    
    /** Close all html elements of a groupbox that was created before.
     */
    public function closeGroupBox()
    {
        $this->addHtml('</div></div>');
    }
    
    /** Open a bootstrap btn-group if the form need more than one button.
     */
    public function openButtonGroup()
    {
        $this->buttonGroupOpen = true;
        $this->addHtml('<div class="btn-group">');
    }
    
    /** Creates a html structure for a form field. This structure contains the label
     *  and the div for the form element. After the form element is added the 
     *  method closeControlStructure must be called.
     *  @param $id        The id of this field structure.
     *  @param $label     The label of the field. This string should already be translated.
     *  @param $property   With this param you can set the following properties: 
     *                     @b FIELD_DEFAULT The field can accept an input.
     *                     @b FIELD_MANDATORY The field will be marked as a mandatory field where the user must insert a value.
     *                     @b FIELD_DISABLED The field will be disabled and could not accept an input.
	 *  @param $helpTextId A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                     If set a help icon will be shown where the user can see the text if he hover over the icon.
     *                     If you need an additional parameter for the text you can add an array. The first entry must
     *                     be the unique text id and the second entry will be a parameter of the text id.     
     *  @param $icon       Opional an icon can be set. This will be placed in front of the label.
     *  @param $class      Optional an additional css classname for the row. The class @b admFieldRow
     *                     is set as default and need not set with this parameter.
     */
    protected function openControlStructure($id, $label, $property = FIELD_DEFAULT, $helpTextId = null, $icon = null, $class = '')
    {
        $cssClassRow       = '';
        $cssClassMandatory = '';
		$htmlLabel         = '';
		$htmlIcon          = '';
        $htmlHelpIcon      = '';
        $htmlIdFor         = '';

        // set specific css class for this row
        if(strlen($class) > 0)
        {
            $cssClassRow .= ' '.$class;
        }

        // if necessary set css class for a mandatory element
        if($property == FIELD_MANDATORY)
        {
			$cssClassMandatory = ' control-mandatory';
            $cssClassRow .= $cssClassMandatory;
            $this->flagMandatoryFields = true;
        }
        
        if(strlen($id) > 0)
        {
            $htmlIdFor = ' for="'.$id.'"';
            $this->addHtml('<div id="'.$id.'_group" class="form-group'.$cssClassRow.'">');
        }
        else
        {
            $this->addHtml('<div class="form-group'.$cssClassRow.'">');        
        }
		
		if($icon != null)
		{
			// create html for icon
			if(strpos(admStrToLower($icon), 'http') === 0 && strValidCharacters($icon, 'url'))
			{
				$htmlIcon = '<img class="icon-information" src="'.$icon.'" title="'.$label.'" alt="'.$label.'" />';
			}
			elseif(admStrIsValidFileName($icon, true))
			{
				$htmlIcon = '<img class="icon-information" src="'.THEME_PATH.'/icons/'.$icon.'" title="'.$label.'" alt="'.$label.'" />';
			}
		}
        
        if($helpTextId != null)
        {
            $htmlHelpIcon = $this->getHelpTextIcon($helpTextId);
        }
		        
        // add label element
        if($this->type == 'vertical' || $this->type == 'navbar')
        {
            if(strlen($label) > 0)
            {
                $this->addHtml('<label'.$htmlIdFor.'>'.$htmlIcon.$label.$htmlHelpIcon.'</label>');
            }
        }
        else
        {
            if(strlen($label) > 0)
            {
                $this->addHtml('<label'.$htmlIdFor.' class="col-sm-3 control-label">'.$htmlIcon.$label.$htmlHelpIcon.'</label>
                    <div class="col-sm-9">');
            }
            else
            {
                $this->addHtml('<div class="col-sm-offset-3 col-sm-9">');
            }
        }
    }
    
    /** Add a new groupbox to the form. This could be used to group some elements 
     *  together. There is also the option to set a headline to this group box.
     *  @param $id       Id the the groupbox.
     *  @param $headline Optional a headline that will be shown to the user.
     */
    public function openGroupBox($id, $headline = '')
    {
        $this->addHtml('<div id="'.$id.'" class="panel panel-default">');
        // add headline to groupbox
        if(strlen($headline) > 0)
        {
            $this->addHtml('<div class="panel-heading">'.$headline.'</div>');
        }
        $this->addHtml('<div class="panel-body">');
    }
	
	/** Add a small help icon to the form at the current element which shows the
	 *  translated text of the text-id on mouseover or when you click on the icon.
	 *  @param $textId A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                 If you need an additional parameter for the text you can add an array. The first entry must
     *                 be the unique text id and the second entry will be a parameter of the text id.
     *  @return Return a html snippet that contains a help icon with a link to a popup box that shows the message.
	 */
	protected function getHelpTextIcon($textId)
	{
        global $g_root_path, $gL10n, $gProfileFields;
        $parameters = null;
        $text       = null;
        
        if(is_array($textId))
        {
            $parameters = 'message_id='.$textId[0].'&amp;message_var1='.$textId[1];
            if($textId[0] == 'user_field_description')
            {
                $text = $gProfileFields->getProperty($textId[1], 'usf_description');
            }
            else
            {
                $text = $gL10n->get($textId[0], $textId[1]);
            }
        }
        else
        {
            if(strlen($textId) > 0)
            {
                $parameters = 'message_id='.$textId;
                $text = $gL10n->get($textId);
            }
        }
        
        if($parameters != null)
        {
            return '<a class="icon-link colorbox-dialog" title="" href="'. $g_root_path. '/adm_program/system/msg_window.php?'.$parameters.'&amp;inline=true" 
                        data-toggle="tooltip" data-html="true" data-original-title="'.str_replace('"', '\'', $text).'"><img src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>';
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
            $html .= '<div class="control-mandatory-definition"><span>'.$gL10n->get('SYS_MANDATORY_FIELDS').'</span></div>';
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