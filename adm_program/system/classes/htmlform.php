<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// constants for field property
define('FIELD_DEFAULT',  0);
define('FIELD_REQUIRED', 1);
define('FIELD_DISABLED', 2);
define('FIELD_READONLY', 3);
define('FIELD_HIDDEN',   4);

/**
 * @class HtmlForm
 * @brief Creates an Admidio specific form with special elements
 *
 * This class inherits the common HtmlFormBasic class and extends their elements
 * with custom Admidio form elements. The class should be used to create the
 * html part of all Admidio forms. The Admidio elements will contain
 * the label of fields and some other specific features like a identification
 * of mandatory fields, help buttons and special css classes for every
 * element.
 * @par Examples
 * @code // create a simple form with one input field and a button
 * $form = new HtmlForm('simple-form', 'next_page.php');
 * $form->openGroupBox('gbSimpleForm', $gL10n->get('SYS_SIMPLE_FORM'));
 * $form->addInput('name', $gL10n->get('SYS_NAME'), $formName);
 * $form->addSelectBox('type', $gL10n->get('SYS_TYPE'), array('simple' => 'SYS_SIMPLE', 'very-simple' => 'SYS_VERY_SIMPLE'),
 *                     array('defaultValue' => 'simple', 'showContextDependentFirstEntry' => true));
 * $form->closeGroupBox();
 * $form->addSubmitButton('next-page', $gL10n->get('SYS_NEXT'), array('icon' => 'layout/forward.png'));
 * $form->show(); @endcode
 */
class HtmlForm extends HtmlFormBasic
{
    protected $flagRequiredFields;    ///< Flag if this form has required fields. Then a notice must be written at the end of the form
    protected $flagFieldListOpen;     ///< Flag if a field list was created. This must be closed later
    protected $showRequiredFields;    ///< Flag if required fields should get a special css class to make them more visible to the user.
    protected $htmlPage;              ///< A HtmlPage object that will be used to add javascript code or files to the html output page.
    protected $countElements;         ///< Number of elements in this form
    protected $datepickerInitialized; ///< Flag if datepicker is already initialized
    protected $type;                  ///< Form type. Possible values are @b default, @b vertical or @b navbar.
    protected $id;                    ///< Id of the form
    protected $buttonGroupOpen;       ///< Flag that indicates if a bootstrap button-group is open and should be closed later

    /**
     * Constructor creates the form element
     * @param string    $id       Id of the form
     * @param string    $action   Action attribute of the form
     * @param \HtmlPage $htmlPage (optional) A HtmlPage object that will be used to add javascript code or files to the html output page.
     * @param array     $options  (optional) An array with the following possible entries:
     *                            - @b type : Set the form type. Every type has some special features:
     *                              + @b default  : A form that can be used to edit and save data of a database table. The label
     *                                and the element have a horizontal orientation.
     *                              + @b vertical : A form that can be used to edit and save data but has a vertical orientation.
     *                                The label is positioned above the form element.
     *                              + @b navbar   : A form that should be used in a navbar. The form content will
     *                                be send with the 'GET' method and this form should not get a default focus.
     *                            - @b enableFileUpload : Set specific parameters that are necessary for file upload with a form
     *                            - @b showRequiredFields : If this is set to @b true (default) then every required field got a special
     *                              css class and also the form got a @b div that explains the required layout.
     *                              If this is set to @b false then only the html flag @b required will be set.
     *                            - @b setFocus : Default is set to @b true. Set the focus on page load to the first field
     *                              of this form.
     *                            - @b class : An additional css classname. The class @b form-horizontal
     *                              is set as default and need not set with this parameter.
     */
    public function __construct($id, $action, HtmlPage $htmlPage = null, array $options = array())
    {
        // create array with all options
        $optionsDefault = array(
            'type'               => 'default',
            'enableFileUpload'   => false,
            'showRequiredFields' => true,
            'setFocus'           => true,
            'class'              => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // navbar forms should send the data as GET
        if ($optionsAll['type'] === 'navbar')
        {
            parent::__construct($action, $id, 'get');
        }
        else
        {
            parent::__construct($action, $id, 'post');
        }

        $this->flagRequiredFields    = false;
        $this->flagFieldListOpen     = false;
        $this->showRequiredFields    = $optionsAll['showRequiredFields'];
        $this->countElements         = 0;
        $this->datepickerInitialized = false;
        $this->type                  = $optionsAll['type'];
        $this->id                    = $id;
        $this->buttonGroupOpen       = false;

        // set specific Admidio css form class
        $this->addAttribute('role', 'form');

        if ($this->type === 'default')
        {
            $optionsAll['class'] .= ' form-horizontal form-dialog';
        }
        elseif ($this->type === 'vertical')
        {
            $optionsAll['class'] .= ' admidio-form-vertical form-dialog';
        }
        elseif ($this->type === 'navbar')
        {
            $optionsAll['class'] .= ' form-horizontal navbar-form navbar-left';
        }

        if ($optionsAll['class'] !== '')
        {
            $this->addAttribute('class', $optionsAll['class']);
        }

        // Set specific parameters that are necessary for file upload with a form
        if ($optionsAll['enableFileUpload'])
        {
            $this->addAttribute('enctype', 'multipart/form-data');
        }

        if ($htmlPage instanceof \HtmlPage)
        {
            $this->htmlPage =& $htmlPage;
        }

        // if its not a navbar form and not a static form then first field of form should get focus
        if ($optionsAll['setFocus'])
        {
            $this->addJavascriptCode('$(".form-dialog:first *:input:enabled:first").focus();', true);
        }
    }

    /**
     * Adds any javascript content to the page. The javascript will be added to the page header or as inline script.
     * @param string $javascriptCode       A valid javascript code that will be added to the header of the page or as inline script.
     * @param bool   $executeAfterPageLoad (optional) If set to @b true the javascript code will be executed after
     *                                     the page is fully loaded.
     */
    protected function addJavascriptCode($javascriptCode, $executeAfterPageLoad = false)
    {
        if ($this->htmlPage instanceof \HtmlPage)
        {
            $this->htmlPage->addJavascript($javascriptCode, $executeAfterPageLoad);
            return;
        }

        if ($executeAfterPageLoad)
        {
            $javascriptCode = '$(function() { ' . $javascriptCode . ' });';
        }
        $this->addHtml('<script type="text/javascript">' . $javascriptCode . '</script>');
    }

    /**
     * Add a new button with a custom text to the form. This button could have
     * an icon in front of the text.
     * @param string $id      Id of the button. This will also be the name of the button.
     * @param string $text    Text of the button
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b icon : Optional parameter. Path and filename of an icon.
     *                          If set a icon will be shown in front of the text.
     *                        - @b link : If set a javascript click event with a page load to this link
     *                          will be attached to the button.
     *                        - @b onClickText : A text that will be shown after a click on this button
     *                          until the next page is loaded. The button will be disabled after click.
     *                        - @b class : Optional an additional css classname. The class @b admButton
     *                          is set as default and need not set with this parameter.
     *                        - @b type : Optional a button type could be set. The default is @b button.
     */
    public function addButton($id, $text, array $options = array())
    {
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array('icon' => '', 'link' => '', 'onClickText' => '', 'class' => '', 'type' => 'button', 'data-admidio' => '');
        $optionsAll     = array_replace($optionsDefault, $options);

        // add text and icon to button
        $value = $text;

        if ($optionsAll['icon'] !== '')
        {
            $value = '<img src="' . $optionsAll['icon'] . '" alt="' . $text . '" />' . $value;
        }
        $this->addElement('button');
        $this->addAttribute('class', 'btn btn-default');

        if ($optionsAll['data-admidio'] !== '')
        {
            $this->addAttribute('data-admidio', $optionsAll['data-admidio']);
        }

        if ($optionsAll['onClickText'] !== '')
        {
            $this->addAttribute('data-loading-text', $optionsAll['onClickText']);
            $this->addAttribute('autocomplete', 'off');
        }

        // add javascript for stateful button and/or
        // a different link that should be loaded after click

        $javascriptCode = '';

        if ($optionsAll['link'] !== '')
        {
            // disable default form submit
            $javascriptCode .= 'self.location.href="' . $optionsAll['link'] . '";';
        }

        if ($optionsAll['onClickText'] !== '')
        {
            $javascriptCode .= '$(this).button("loading");';
        }

        if ($optionsAll['link'] !== '' || $optionsAll['onClickText'] !== '')
        {
            if ($optionsAll['type'] === 'submit')
            {
                $javascriptCode .= '$(this).submit();';
            }

            $javascriptCode = '
                $("#' . $id . '").click(function(event) {
                    ' . $javascriptCode . '
                });';

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            $this->addJavascriptCode($javascriptCode, true);
        }

        if ($optionsAll['class'] !== '')
        {
            $this->addAttribute('class', $optionsAll['class']);
        }

        $this->addSimpleButton($id, $optionsAll['type'], $value, $id);
    }

    /**
     * Add a new button with a custom text to the form. This button could have
     * an icon in front of the text. Different to addButton this method adds an
     * additional @b div around the button and the type of the button is @b submit.
     * @param string $id      Id of the button. This will also be the name of the button.
     * @param string $text    Text of the button
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b icon : Optional parameter. Path and filename of an icon.
     *                          If set a icon will be shown in front of the text.
     *                        - @b link : If set a javascript click event with a page load to this link
     *                          will be attached to the button.
     *                        - @b onClickText : A text that will be shown after a click on this button
     *                          until the next page is loaded. The button will be disabled after click.
     *                        - @b class : Optional an additional css classname. The class @b admButton
     *                          is set as default and need not set with this parameter.
     *                        - @b type : If set to true this button get the type @b submit. This will
     *                          be the default.
     */
    public function addSubmitButton($id, $text, array $options = array())
    {
        // create array with all options
        $optionsDefault = array('icon' => '', 'link' => '', 'onClickText' => '', 'class' => '', 'type' => 'submit');
        $optionsAll     = array_replace($optionsDefault, $options);

        // add default css class
        $optionsAll['class'] .= ' btn-primary';

        // now add button to form
        $this->addButton($id, $text, $optionsAll);

        if (!$this->buttonGroupOpen)
        {
            $this->addHtml('<div class="form-alert" style="display: none;">&nbsp;</div>');
        }
    }

    /**
     * Add a captcha with an input field to the form. The captcha could be a picture with a character code
     * or a simple mathematical calculation that must be solved.
     * @param string $id    Id of the captcha field. This will also be the name of the captcha field.
     * @param string $class (optional) An additional css classname. The class @b admTextInput
     *                      is set as default and need not set with this parameter.
     */
    public function addCaptcha($id, $class = '')
    {
        global $gL10n;

        $attributes = array('class' => 'captcha');
        ++$this->countElements;

        // set specific css class for this field
        if ($class !== '')
        {
            $attributes['class'] .= ' ' . $class;
        }

        // add a row with the captcha puzzle
        $this->openControlStructure('captcha_puzzle', '', FIELD_DEFAULT, '', '', $attributes['class']);
        $onClickCode = 'document.getElementById(\'captcha\').src=\'' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/securimage/securimage_show.php?\' + Math.random(); return false;';
        $this->addHtml('<img id="captcha" src="' . ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/securimage/securimage_show.php" alt="CAPTCHA Image" />
                        <a class="admidio-icon-link" href="javascript:void(0)" onclick="' . $onClickCode . '"><img
                            src="' . THEME_URL . '/icons/view-refresh.png" alt="' . $gL10n->get('SYS_RELOAD') . '" title="' . $gL10n->get('SYS_RELOAD') . '" /></a>');
        $this->closeControlStructure();

        // now add a row with a text field where the user can write the solution for the puzzle
        $this->addInput($id, $gL10n->get('SYS_CAPTCHA_CONFIRMATION_CODE'), '', array('property'        => FIELD_REQUIRED,
                                                                                     'helpTextIdLabel' => 'SYS_CAPTCHA_DESCRIPTION',
                                                                                     'class'           => 'form-control-small'));
    }

    /**
     * Add a new checkbox with a label to the form.
     * @param string $id      Id of the checkbox. This will also be the name of the checkbox.
     * @param string $label   The label of the checkbox.
     * @param bool   $checked A value for the checkbox. The value could only be @b 0 or @b 1. If the value is @b 1 then
     *                        the checkbox will be checked when displayed.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b property : With this param you can set the following properties:
     *                          + @b FIELD_DEFAULT  : The field can accept an input.
     *                          + @b FIELD_REQUIRED : The field will be marked as a mandatory field where the user must insert a value.
     *                          + @b FIELD_DISABLED : The field will be disabled and could not accept an input.
     *                        - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hover over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - @b icon : An icon can be set. This will be placed in front of the label.
     *                        - @b class : An additional css classname. The class @b admSelectbox
     *                          is set as default and need not set with this parameter.
     */
    public function addCheckbox($id, $label, $checked = false, array $options = array())
    {
        $attributes   = array('class' => '');
        $htmlIcon     = '';
        $htmlHelpIcon = '';
        $cssClasses   = 'checkbox';
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'property'         => FIELD_DEFAULT,
            'helpTextIdLabel'  => '',
            'helpTextIdInline' => '',
            'icon'             => '',
            'class'            => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // disable field
        if ($optionsAll['property'] === FIELD_DISABLED)
        {
            $attributes['disabled'] = 'disabled';
        }
        elseif ($optionsAll['property'] === FIELD_REQUIRED)
        {
            $attributes['required'] = 'required';
        }

        // if checked = true then set checkbox checked
        if ($checked)
        {
            $attributes['checked'] = 'checked';
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '')
        {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        if ($optionsAll['icon'] !== '')
        {
            // create html for icon
            if (strpos(admStrToLower($optionsAll['icon']), 'http') === 0 && strValidCharacters($optionsAll['icon'], 'url'))
            {
                $htmlIcon = '<img class="admidio-icon-info" src="' . $optionsAll['icon'] . '" title="' . $label . '" alt="' . $label . '" />';
            }
            elseif (admStrIsValidFileName($optionsAll['icon'], true))
            {
                $htmlIcon = '<img class="admidio-icon-info" src="' . THEME_URL . '/icons/' . $optionsAll['icon'] . '" title="' . $label . '" alt="' . $label . '" />';
            }
        }

        if ($optionsAll['helpTextIdLabel'] !== '')
        {
            $htmlHelpIcon = self::getHelpTextIcon($optionsAll['helpTextIdLabel']);
        }

        // now create html for the field
        $this->openControlStructure($id, '');
        $this->addHtml('<div class="' . $cssClasses . '"><label>');
        $this->addSimpleInput('checkbox', $id, $id, '1', $attributes);
        $this->addHtml($htmlIcon . $label . $htmlHelpIcon . '</label></div>');
        $this->closeControlStructure($optionsAll['helpTextIdInline']);
    }

    /**
     * Add custom html content to the form within the default field structure.
     * The Label will be set but instead of an form control you can define any html.
     * If you don't need the field structure and want to add html then use the method addHtml()
     * @param string $label   The label of the custom content.
     * @param string $content A simple Text or html that would be placed instead of an form element.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b referenceId : Optional the id of a form control if this is defined within the custom content
     *                        - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hover over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - @b icon : An icon can be set. This will be placed in front of the label.
     *                        - @b class : An additional css classname. The class @b admSelectbox
     *                          is set as default and need not set with this parameter.
     */
    public function addCustomContent($label, $content, array $options = array())
    {
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'referenceId'      => '',
            'helpTextIdLabel'  => '',
            'helpTextIdInline' => '',
            'icon'             => '',
            'class'            => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        $this->openControlStructure(
            $optionsAll['referenceId'], $label, FIELD_DEFAULT,
            $optionsAll['helpTextIdLabel'], $optionsAll['icon'], 'form-custom-content'
        );
        $this->addHtml($content);
        $this->closeControlStructure($optionsAll['helpTextIdInline']);
    }

    /**
     * Add a line with a custom description to the form. No form elements will be displayed in this line.
     * @param string $text The (html) text that should be displayed.
     */
    public function addDescription($text)
    {
        $this->addHtml('<p>' . $text . '</p>');
    }

    /**
     * Add a new CKEditor element to the form.
     * @param string $id      Id of the password field. This will also be the name of the password field.
     * @param string $label   The label of the password field.
     * @param string $value   A value for the editor field. The editor will contain this value when created.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b property : With this param you can set the following properties:
     *                          + @b FIELD_DEFAULT  : The field can accept an input.
     *                          + @b FIELD_REQUIRED : The field will be marked as a mandatory field where the user must insert a value.
     *                        - @b toolbar : Optional set a predefined toolbar for the editor. Possible values are
     *                          @b AdmidioDefault, @b AdmidioGuestbook and @b AdmidioPlugin_WC
     *                        - @b height : Optional set the height in pixel of the editor. The default will be 300.
     *                        - @b labelVertical : If set to @b true (default) then the label will be display above the control and the control get a width of 100%.
     *                          Otherwise the label will be displayed in front of the control.
     *                        - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hover over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - @b icon : An icon can be set. This will be placed in front of the label.
     *                        - @b class : An additional css classname. The class @b admSelectbox
     *                          is set as default and need not set with this parameter.
     */
    public function addEditor($id, $label, $value, array $options = array())
    {
        global $gPreferences, $gL10n;

        ++$this->countElements;
        $attributes = array('class' => 'editor');
        $flagLabelVertical = $this->type;

        // create array with all options
        $optionsDefault = array(
            'property'         => FIELD_DEFAULT,
            'toolbar'          => 'AdmidioDefault',
            'height'           => '300',
            'helpTextIdLabel'  => '',
            'helpTextIdInline' => '',
            'labelVertical'    => true,
            'icon'             => '',
            'class'            => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        if ($optionsAll['labelVertical'])
        {
            $this->type = 'vertical';
        }

        if ($optionsAll['property'] === FIELD_REQUIRED)
        {
            $attributes['required'] = 'required';
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '')
        {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        $javascriptCode = '
            CKEDITOR.replace("' . $id . '", {
                toolbar: "' . $optionsAll['toolbar'] . '",
                language: "' . $gL10n->getLanguageIsoCode() . '",
                uiColor: "' . $gPreferences['system_js_editor_color'] . '",
                filebrowserImageUploadUrl: "' . ADMIDIO_URL . '/adm_program/system/ckeditor_upload_handler.php"
            });
            CKEDITOR.config.height = "' . $optionsAll['height'] . '";';

        if ((int) $gPreferences['system_js_editor_enabled'] === 1)
        {
            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if ($this->htmlPage instanceof \HtmlPage)
            {
                $this->htmlPage->addJavascriptFile('adm_program/libs/ckeditor/ckeditor.js');
            }
            $this->addJavascriptCode($javascriptCode, true);
        }

        $this->openControlStructure(
            $id, $label, $optionsAll['property'], $optionsAll['helpTextIdLabel'],
            $optionsAll['icon'], 'form-group-editor'
        );
        $this->addHtml(
            '<div class="' . $attributes['class'] . '">
                <textarea id="' . $id . '" name="' . $id . '" style="width: 100%;">' . $value . '</textarea>
            </div>'
        );
        $this->closeControlStructure($optionsAll['helpTextIdInline']);

        $this->type = $flagLabelVertical;
    }

    /**
     * Add a field for file upload. If necessary multiple files could be uploaded.
     * The fields for multiple upload could be added dynamically to the form by the user.
     * @param string $id      Id of the input field. This will also be the name of the input field.
     * @param string $label   The label of the input field.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b property : With this param you can set the following properties:
     *                          + @b FIELD_DEFAULT  : The field can accept an input.
     *                          + @b FIELD_REQUIRED : The field will be marked as a mandatory field where the user must insert a value.
     *                          + @b FIELD_DISABLED : The field will be disabled and could not accept an input.
     *                        - @b allowedMimeTypes : An array with the allowed MIME types (https://wiki.selfhtml.org/wiki/Referenz:MIME-Typen).
     *                          If this is set then the user can only choose the specified files with the browser file dialog.
     *                          You should check the uploaded file against the MIME type because the file could be manipulated.
     *                        - @b maxUploadSize : The size in byte that could be maximum uploaded.
     *                          The default will be $gPreferences['max_file_upload_size'] * 1024 * 1024.
     *                        - @b enableMultiUploads : If set to true a button will be added where the user can
     *                          add new upload fields to upload more than one file.
     *                        - @b multiUploadLabel : The label for the button who will add new upload fields to the form.
     *                        - @b hideUploadField : Hide the upload field if multi uploads are enabled. Then the first
     *                          upload field will be shown if the user will click the multi upload button.
     *                        - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hover over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - @b icon : An icon can be set. This will be placed in front of the label.
     *                        - @b class : An additional css classname. The class @b admSelectbox
     *                          is set as default and need not set with this parameter.
     */
    public function addFileUpload($id, $label, array $options = array())
    {
        global $gPreferences;
        $attributes = array('class' => 'form-control');
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'property'           => FIELD_DEFAULT,
            'maxUploadSize'      => $gPreferences['max_file_upload_size'] * 1024 * 1024, // MiB
            'allowedMimeTypes'   => array(),
            'enableMultiUploads' => false,
            'hideUploadField'    => false,
            'multiUploadLabel'   => '',
            'helpTextIdLabel'    => '',
            'helpTextIdInline'   => '',
            'icon'               => '',
            'class'              => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // disable field
        if ($optionsAll['property'] === FIELD_DISABLED)
        {
            $attributes['disabled'] = 'disabled';
        }
        elseif ($optionsAll['property'] === FIELD_REQUIRED)
        {
            $attributes['required'] = 'required';
        }

        if (count($optionsAll['allowedMimeTypes']) > 0)
        {
            $attributes['accept'] = implode(',', $optionsAll['allowedMimeTypes']);
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '')
        {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        // if multiple uploads are enabled then add javascript that will
        // dynamically add new upload fields to the form
        if ($optionsAll['enableMultiUploads'])
        {
            $javascriptCode = '
                // add new line to add new attachment to this mail
                $("#btn_add_attachment_' . $id . '").click(function () {
                    newAttachment = document.createElement("input");
                    $(newAttachment).attr("type", "file");
                    $(newAttachment).attr("name", "userfile[]");
                    $(newAttachment).attr("class", "' . $attributes['class'] . '");
                    $(newAttachment).hide();
                    $("#btn_add_attachment_' . $id . '").before(newAttachment);
                    $(newAttachment).show("slow");
                });';

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            $this->addJavascriptCode($javascriptCode, true);
        }

        $this->openControlStructure($id, $label, $optionsAll['property'], $optionsAll['helpTextIdLabel'],
                                    $optionsAll['icon'], 'form-upload');
        $this->addSimpleInput('hidden', 'MAX_FILE_SIZE', 'MAX_FILE_SIZE', $optionsAll['maxUploadSize']);

        // if multi uploads are enabled then the file upload field could be hidden
        // until the user will click on the button to add a new upload field
        if (!$optionsAll['hideUploadField'] || !$optionsAll['enableMultiUploads'])
        {
            $this->addSimpleInput('file', 'userfile[]', null, '', $attributes);
        }

        if ($optionsAll['enableMultiUploads'])
        {
            // show button to add new upload field to form
            $this->addHtml(
                '<button type="button" id="btn_add_attachment_' . $id . '" class="btn btn-default">
                    <img src="' . THEME_URL . '/icons/add.png" alt="' . $optionsAll['multiUploadLabel'] . '" />'
                    . $optionsAll['multiUploadLabel'] .
                '</button>'
            );
        }
        $this->closeControlStructure($optionsAll['helpTextIdInline']);
    }

    /**
     * Add a new input field with a label to the form.
     * @param string $id      Id of the input field. This will also be the name of the input field.
     * @param string $label   The label of the input field.
     * @param string $value   A value for the text field. The field will be created with this value.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b type : Set the type if the field. Default will be @b text. Possible values are @b text,
     *                          @b number, @b date, @b datetime or @b birthday. If @b date, @b datetime or @b birthday are set
     *                          than a small calendar will be shown if the date field will be selected.
     *                        - @b maxLength : The maximum number of characters that are allowed in a text field.
     *                        - @b minNumber : The minimum number that is allowed in a number field.
     *                        - @b maxNumber : The maximum number that is allowed in a number field.
     *                        - @b step : The steps between two numbers that are allowed.
     *                          E.g. if steps is set to 5 then only values 5, 10, 15 ... are allowed
     *                        - @b property : With this param you can set the following properties:
     *                          + @b FIELD_DEFAULT  : The field can accept an input.
     *                          + @b FIELD_REQUIRED : The field will be marked as a mandatory field where the user must insert a value.
     *                          + @b FIELD_DISABLED : The field will be disabled and could not accept an input.
     *                          + @b FIELD_HIDDEN   : The field will not be shown. Useful to transport additional informations.
     *                        - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hover over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - @b icon : An icon can be set. This will be placed in front of the label.
     *                        - @b class : An additional css classname. The class @b admSelectbox
     *                          is set as default and need not set with this parameter.
     *                        - @b htmlAfter : Add html code after the input field.
     */
    public function addInput($id, $label, $value, array $options = array())
    {
        global $gL10n, $gPreferences;

        $attributes = array('class' => 'form-control');
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'type'             => 'text',
            'minLength'        => null,
            'maxLength'        => 0,
            'minNumber'        => null,
            'maxNumber'        => null,
            'step'             => 1,
            'property'         => FIELD_DEFAULT,
            'passwordStrength' => false,
            'passwordUserData' => array(),
            'helpTextIdLabel'  => '',
            'helpTextIdInline' => '',
            'icon'             => '',
            'class'            => '',
            'htmlAfter'        => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // set min/max input length
        switch ($optionsAll['type'])
        {
            case 'text':
            case 'search':
            case 'email':
            case 'url':
            case 'tel':
            case 'password':
                $attributes['minlength'] = $optionsAll['minLength'];

                if ($optionsAll['maxLength'] > 0)
                {
                    $attributes['maxlength'] = $optionsAll['maxLength'];
                }
                break;
            case 'number':
                $attributes['min'] = $optionsAll['minNumber'];
                $attributes['max'] = $optionsAll['maxNumber'];
                $attributes['step'] = $optionsAll['step'];
                break;
        }

        // disable field
        switch ($optionsAll['property'])
        {
            case FIELD_DISABLED:
                $attributes['disabled'] = 'disabled';
                break;

            case FIELD_READONLY:
                $attributes['readonly'] = 'readonly';
                break;

            case FIELD_REQUIRED:
                $attributes['required'] = 'required';
                break;

            case FIELD_HIDDEN:
                $attributes['hidden'] = 'hidden';
                $attributes['class'] .= ' hide';
                break;
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '')
        {
            $attributes['class'] .= ' '.$optionsAll['class'];
        }

        // add a nice modern datepicker to date inputs
        if ($optionsAll['type'] === 'date' || $optionsAll['type'] === 'datetime' || $optionsAll['type'] === 'birthday')
        {
            $attributes['placeholder'] = DateTimeExtended::getDateFormatForDatepicker($gPreferences['system_date']);
            $javascriptCode = '';

            // if you have a birthday field than start with the years selection
            if ($optionsAll['type'] === 'birthday')
            {
                $attributes['data-provide'] = 'datepicker-birthday';
                $datepickerOptions = ' startView: 2, ';
            }
            else
            {
                $attributes['data-provide'] = 'datepicker';
                $datepickerOptions = ' todayBtn: "linked", ';
            }

            if (!$this->datepickerInitialized || $optionsAll['type'] === 'birthday')
            {
                $javascriptCode = '
                    $("input[data-provide=\'' . $attributes['data-provide'] . '\']").datepicker({
                        language: "' . $gL10n->getLanguageIsoCode() . '",
                        format: "' . DateTimeExtended::getDateFormatForDatepicker($gPreferences['system_date']) . '",
                        ' . $datepickerOptions . '
                        todayHighlight: "true"
                    });';

                if ($optionsAll['type'] !== 'birthday')
                {
                    $this->datepickerInitialized = true;
                }
            }

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if ($this->htmlPage instanceof \HtmlPage)
            {
                $this->htmlPage->addCssFile('adm_program/libs/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css');
                $this->htmlPage->addJavascriptFile('adm_program/libs/bootstrap-datepicker/dist/js/bootstrap-datepicker.js');
                $this->htmlPage->addJavascriptFile('adm_program/libs/bootstrap-datepicker/dist/locales/bootstrap-datepicker.' . $gL10n->getLanguageIsoCode() . '.min.js');
            }
            $this->addJavascriptCode($javascriptCode, true);
        }

        if ($optionsAll['property'] !== FIELD_HIDDEN)
        {
            // now create html for the field
            $this->openControlStructure($id, $label, $optionsAll['property'], $optionsAll['helpTextIdLabel'], $optionsAll['icon']);
        }

        // if datetime then add a time field behind the date field
        if ($optionsAll['type'] === 'datetime')
        {
            // first try to split datetime to a date and a time value
            $datetime = DateTime::createFromFormat($gPreferences['system_date'] . ' ' . $gPreferences['system_time'], $value);
            $dateValue = $datetime->format($gPreferences['system_date']);
            $timeValue = $datetime->format($gPreferences['system_time']);

            // now add a date and a time field to the form
            $attributes['class'] .= ' datetime-date-control';
            $this->addSimpleInput('text', $id, $id, $dateValue, $attributes);
            $attributes['class'] .= ' datetime-time-control';
            $attributes['maxlength'] = '8';
            $attributes['data-provide'] = '';
            $this->addSimpleInput('text', $id . '_time', $id . '_time', $timeValue, $attributes);
        }
        else
        {
            // a date type has some problems with chrome so we set it as text type
            if ($optionsAll['type'] === 'date' || $optionsAll['type'] === 'birthday')
            {
                $optionsAll['type'] = 'text';
            }
            $this->addSimpleInput($optionsAll['type'], $id, $id, $value, $attributes);
        }

        if ($optionsAll['htmlAfter'] !== '')
        {
            $this->addHtml($optionsAll['htmlAfter']);
        }

        if ($optionsAll['passwordStrength'])
        {
            $passwordStrengthLevel = 1;
            if ($gPreferences['password_min_strength'])
            {
                $passwordStrengthLevel = $gPreferences['password_min_strength'];
            }

            if ($this->htmlPage instanceof \HtmlPage)
            {
                $zxcvbnUserInputs = json_encode($optionsAll['passwordUserData'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $javascriptCode = '
                    $("#admidio-password-strength-minimum").css("margin-left", "calc(" + $("#admidio-password-strength").css("width") + " / 4 * '.$passwordStrengthLevel.')");

                    $("#' . $id . '").keyup(function(e) {
                        var result = zxcvbn(e.target.value, ' . $zxcvbnUserInputs . ');
                        var cssClasses = ["progress-bar-danger", "progress-bar-danger", "progress-bar-warning", "progress-bar-info", "progress-bar-success"];

                        var progressBar = $("#admidio-password-strength .progress-bar");
                        progressBar.attr("aria-valuenow", result.score * 25);
                        progressBar.css("width", result.score * 25 + "%");
                        progressBar.removeClass(cssClasses.join(" "));
                        progressBar.addClass(cssClasses[result.score]);
                    });
                ';
                $this->htmlPage->addJavascriptFile('adm_program/libs/zxcvbn/dist/zxcvbn.js');
                $this->htmlPage->addJavascript($javascriptCode, true);
            }

            $this->addHtml('
                <div id="admidio-password-strength" class="progress ' . $optionsAll['class'] . '">
                    <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div>
                    <div id="admidio-password-strength-minimum"></div>
                </div>
            ');
        }

        if ($optionsAll['property'] !== FIELD_HIDDEN)
        {
            $this->closeControlStructure($optionsAll['helpTextIdInline']);
        }
    }

    /**
     * Add a simple line to the form. This could be used to structure a form. The line has only a visual effect.
     */
    public function addLine()
    {
        $this->addHtml('<hr />');
    }

    /**
     * Add a new textarea field with a label to the form.
     * @param string $id      Id of the input field. This will also be the name of the input field.
     * @param string $label   The label of the input field.
     * @param string $value   A value for the text field. The field will be created with this value.
     * @param int    $rows    The number of rows that the textarea field should have.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b maxLength : The maximum number of characters that are allowed in this field. If set
     *                          then show a counter how many characters still available
     *                        - @b property : With this param you can set the following properties:
     *                          + @b FIELD_DEFAULT  : The field can accept an input.
     *                          + @b FIELD_REQUIRED : The field will be marked as a mandatory field where the user must insert a value.
     *                          + @b FIELD_DISABLED : The field will be disabled and could not accept an input.
     *                        - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hover over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - @b icon : An icon can be set. This will be placed in front of the label.
     *                        - @b class : An additional css classname. The class @b admSelectbox
     *                          is set as default and need not set with this parameter.
     */
    public function addMultilineTextInput($id, $label, $value, $rows, array $options = array())
    {
        global $gL10n;

        $attributes = array('class' => 'form-control');
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'property'         => FIELD_DEFAULT,
            'maxLength'        => 0,
            'helpTextIdLabel'  => '',
            'helpTextIdInline' => '',
            'icon'             => '',
            'class'            => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // disable field
        if ($optionsAll['property'] === FIELD_DISABLED)
        {
            $attributes['disabled'] = 'disabled';
        }
        elseif ($optionsAll['property'] === FIELD_REQUIRED)
        {
            $attributes['required'] = 'required';
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '')
        {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        if ($optionsAll['maxLength'] > 0)
        {
            $attributes['maxlength'] = $optionsAll['maxLength'];

            // if max field length is set then show a counter how many characters still available
            $javascriptCode = '
                $("#' . $id . '").NobleCount("#' . $id . '_counter", {
                    max_chars: ' . $optionsAll['maxLength'] . ',
                    on_negative: "systeminfoBad",
                    block_negative: true
                });';

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if ($this->htmlPage instanceof \HtmlPage)
            {
                $this->htmlPage->addJavascriptFile('adm_program/libs/noblecount/jquery.noblecount.js');
            }
            $this->addJavascriptCode($javascriptCode, true);
        }

        $this->openControlStructure($id, $label, $optionsAll['property'], $optionsAll['helpTextIdLabel'], $optionsAll['icon']);
        $this->addTextArea($id, $rows, 80, $value, $id, $attributes);

        if ($optionsAll['maxLength'] > 0)
        {
            // if max field length is set then show a counter how many characters still available
            $this->addHtml('
                <small class="characters-count">('
                    .$gL10n->get('SYS_STILL_X_CHARACTERS', '<span id="' . $id . '_counter" class="">255</span>').
                ')</small>'
            );
        }
        $this->closeControlStructure($optionsAll['helpTextIdInline']);
    }

    /**
     * Add a new radio button with a label to the form. The radio button could have different status
     * which could be defined with an array.
     * @param string $id      Id of the radio button. This will also be the name of the radio button.
     * @param string $label   The label of the radio button.
     * @param array  $values  Array with all entries of the radio button;
     *                        Array key will be the internal value of the entry
     *                        Array value will be the visual value of the entry
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b property : With this param you can set the following properties:
     *                          + @b FIELD_DEFAULT  : The field can accept an input.
     *                          + @b FIELD_REQUIRED : The field will be marked as a mandatory field where the user must insert a value.
     *                          + @b FIELD_DISABLED : The field will be disabled and could not accept an input.
     *                        - @b defaultValue : This is the value of that radio button that is preselected.
     *                        - @b showNoValueButton : If set to true than one radio with no value will be set in front of the other array.
     *                          This could be used if the user should also be able to set no radio to value.
     *                        - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hover over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - @b icon : An icon can be set. This will be placed in front of the label.
     *                        - @b class : An additional css classname. The class @b admSelectbox
     *                          is set as default and need not set with this parameter.
     */
    public function addRadioButton($id, $label, array $values, array $options = array())
    {
        $attributes = array('class' => '');
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'property'          => FIELD_DEFAULT,
            'defaultValue'      => '',
            'showNoValueButton' => false,
            'helpTextIdLabel'   => '',
            'helpTextIdInline'  => '',
            'icon'              => '',
            'class'             => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // disable field
        if ($optionsAll['property'] === FIELD_DISABLED)
        {
            $attributes['disabled'] = 'disabled';
        }
        elseif ($optionsAll['property'] === FIELD_REQUIRED)
        {
            $attributes['required'] = 'required';
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '')
        {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        $this->openControlStructure($id, $label, $optionsAll['property'], $optionsAll['helpTextIdLabel'], $optionsAll['icon']);

        // set one radio button with no value will be set in front of the other array.
        if ($optionsAll['showNoValueButton'])
        {
            if ($optionsAll['defaultValue'] === '')
            {
                $attributes['checked'] = 'checked';
            }

            $this->addHtml('<label for="' . $id . '_0' . '" class="radio-inline">');
            $this->addSimpleInput('radio', $id, $id . '_0', '', $attributes);
            $this->addHtml('---</label>');
        }

        // for each entry of the array create an input radio field
        foreach ($values as $key => $value)
        {
            unset($attributes['checked']);

            if ($optionsAll['defaultValue'] == $key)
            {
                $attributes['checked'] = 'checked';
            }

            $this->addHtml('<label for="' . $id . '_' . $key . '" class="radio-inline">');
            $this->addSimpleInput('radio', $id, $id . '_' . $key, $key, $attributes);
            $this->addHtml($value . '</label>');
        }

        $this->closeControlStructure($optionsAll['helpTextIdInline']);
    }

    /**
     * Add a new selectbox with a label to the form. The selectbox
     * could have different values and a default value could be set.
     * @param string $id      Id of the selectbox. This will also be the name of the selectbox.
     * @param string $label   The label of the selectbox.
     * @param array  $values  Array with all entries of the select box;
     *                        Array key will be the internal value of the entry
     *                        Array value will be the visual value of the entry
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b property : With this param you can set the following properties:
     *                          + @b FIELD_DEFAULT  : The field can accept an input.
     *                          + @b FIELD_REQUIRED : The field will be marked as a mandatory field where the user must insert a value.
     *                          + @b FIELD_DISABLED : The field will be disabled and could not accept an input.
     *                        - @b defaultValue : This is the value the selectbox shows when loaded. If @b multiselect is activated than
     *                          an array with all default values could be set.
     *                        - @b showContextDependentFirstEntry : If set to @b true the select box will get an additional first entry.
     *                          If FIELD_REQUIRED is set than "Please choose" will be the first entry otherwise
     *                          an empty entry will be added so you must not select something.
     *                        - @b firstEntry : Here you can define a string that should be shown as firstEntry and will be the
     *                          default value if no other value is set. This entry will only be added if @b showContextDependentFirstEntry
     *                          is set to false!
     *                        - @b multiselect : If set to @b true than the jQuery plugin Select2 will be used to create a selectbox
     *                          where the user could select multiple values from the selectbox. Then an array will be
     *                          created within the $_POST array.
     *                        - @b search : If set to @b true the jQuery plugin Select2 will be used to create a selectbox
     *                          with a search field.
     *                        - @b maximumSelectionNumber : If @b multiselect is enabled then you can configure the maximum number
     *                          of selections that could be done. If this limit is reached the user can't add another entry to the selectbox.
     *                        - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hover over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - @b icon : An icon can be set. This will be placed in front of the label.
     *                        - @b class : An additional css classname. The class @b admSelectbox
     *                          is set as default and need not set with this parameter.
     */
    public function addSelectBox($id, $label, array $values, array $options = array())
    {
        global $gL10n;

        $attributes = array('class' => 'form-control');
        $name = $id;

        if (count($values) > 0)
        {
            ++$this->countElements;
        }

        // create array with all options
        $optionsDefault = array(
            'property'                       => FIELD_DEFAULT,
            'defaultValue'                   => '',
            'showContextDependentFirstEntry' => true,
            'firstEntry'                     => '',
            'multiselect'                    => false,
            'search'                         => false,
            'maximumSelectionNumber'         => 0,
            'helpTextIdLabel'                => '',
            'helpTextIdInline'               => '',
            'icon'                           => '',
            'class'                          => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // disable field
        if ($optionsAll['property'] === FIELD_DISABLED)
        {
            $attributes['disabled'] = 'disabled';
        }
        // multiselect couldn't handle the required property
        elseif ($optionsAll['property'] === FIELD_REQUIRED && !$optionsAll['multiselect'])
        {
            $attributes['required'] = 'required';
        }

        $placeholder = '';
        if ($optionsAll['multiselect'])
        {
            $attributes['multiple'] = 'multiple';
            $name = $id . '[]';

            if ($optionsAll['defaultValue'] !== '' && !is_array($optionsAll['defaultValue']))
            {
                $optionsAll['defaultValue'] = array($optionsAll['defaultValue']);
            }

            if ($optionsAll['showContextDependentFirstEntry'] && $optionsAll['property'] === FIELD_REQUIRED)
            {
                $placeholder = $gL10n->get('SYS_SELECT_FROM_LIST');

                // reset the preferences so the logic for not multiselect will not be performed
                $optionsAll['showContextDependentFirstEntry'] = false;
            }
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '')
        {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        // now create html for the field
        $this->openControlStructure($id, $label, $optionsAll['property'], $optionsAll['helpTextIdLabel'], $optionsAll['icon']);

        $this->addSelect($name, $id, $attributes);

        // add an additional first entry to the select box and set this as preselected if necessary
        $defaultEntry = false;
        if ($optionsAll['firstEntry'] !== '' || $optionsAll['showContextDependentFirstEntry'])
        {
            if ($optionsAll['defaultValue'] === '')
            {
                $defaultEntry = true;
            }
        }

        if ($optionsAll['firstEntry'] !== '')
        {
            if(is_array($optionsAll['firstEntry']))
            {
                $this->addOption($optionsAll['firstEntry'][0], $optionsAll['firstEntry'][1], null, $defaultEntry);
            }
            else
            {
                $this->addOption('', '- ' . $optionsAll['firstEntry'] . ' -', null, $defaultEntry);
            }
        }
        elseif ($optionsAll['showContextDependentFirstEntry'])
        {
            if ($optionsAll['property'] === FIELD_REQUIRED)
            {
                $this->addOption('', '- ' . $gL10n->get('SYS_PLEASE_CHOOSE') . ' -', null, $defaultEntry);
            }
            else
            {
                $this->addOption('', ' ', null, $defaultEntry);
            }
        }

        $optionGroup = null;

        foreach ($values as $key => $value)
        {
            // create entry in html
            $defaultEntry = false;

            // if each array element is an array then create option groups
            if (is_array($value))
            {
                // add optiongroup if necessary
                if ($optionGroup !== $value[2])
                {
                    if ($optionGroup !== null)
                    {
                        $this->closeOptionGroup();
                    }
                    $this->addOptionGroup($value[2]);
                    $optionGroup = $value[2];
                }

                // add option
                if (!$optionsAll['multiselect'] && $optionsAll['defaultValue'] == $value[0])
                {
                    $defaultEntry = true;
                }

                $this->addOption($value[0], $value[1], null, $defaultEntry);
            }
            else
            {
                // array has only key and value then create a normal selectbox without optiongroups
                if (!$optionsAll['multiselect'] && $optionsAll['defaultValue'] == $key)
                {
                    $defaultEntry = true;
                }

                $this->addOption($key, $value, null, $defaultEntry);
            }
        }

        if ($optionGroup !== null)
        {
            $this->closeOptionGroup();
        }

        if ($optionsAll['multiselect'] || $optionsAll['search'])
        {
            $maximumSelectionNumber = '';
            $allowClear = 'false';

            if ($optionsAll['maximumSelectionNumber'] > 0)
            {
                $maximumSelectionNumber = ' maximumSelectionLength: ' . $optionsAll['maximumSelectionNumber'] . ', ';
                $allowClear = 'true';
            }

            $javascriptCode = '
                $("#' . $id . '").select2({
                    theme: "bootstrap",
                    allowClear: ' . $allowClear . ',
                    ' . $maximumSelectionNumber . '
                    placeholder: "' . $placeholder . '",
                    language: "' . $gL10n->getLanguage() . '"
                });';

            // add default values to multi select
            if (is_array($optionsAll['defaultValue']) && count($optionsAll['defaultValue']) > 0)
            {
                $htmlDefaultValues = '';
                foreach ($optionsAll['defaultValue'] as $key => $htmlDefaultValue)
                {
                    $htmlDefaultValues .= '"' . $htmlDefaultValue . '",';
                }
                $htmlDefaultValues = substr($htmlDefaultValues, 0, -1);

                $javascriptCode .= ' $("#' . $id . '").val([' . $htmlDefaultValues . ']).trigger("change");';
            }

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if ($this->htmlPage instanceof \HtmlPage)
            {
                $this->htmlPage->addCssFile('adm_program/libs/select2/dist/css/select2.css');
                $this->htmlPage->addCssFile('adm_program/libs/select2-bootstrap-theme/dist/select2-bootstrap.css');
                $this->htmlPage->addJavascriptFile('adm_program/libs/select2/dist/js/select2.js');
                $this->htmlPage->addJavascriptFile('adm_program/libs/select2/dist/js/i18n/' . $gL10n->getLanguageIsoCode() . '.js');
            }
            $this->addJavascriptCode($javascriptCode, true);
        }

        $this->closeSelect();
        $this->closeControlStructure($optionsAll['helpTextIdInline']);
    }

    /**
     * Add a new selectbox with a label to the form. The selectbox get their data from a sql statement.
     * You can create any sql statement and this method should create a selectbox with the found data.
     * The sql must contain at least two columns. The first column represents the value and the second
     * column represents the label of each option of the selectbox. Optional you can add a third column
     * to the sql statement. This column will be used as label for an optiongroup. Each time the value
     * of the third column changed a new optiongroup will be created.
     * @param string    $id             Id of the selectbox. This will also be the name of the selectbox.
     * @param string    $label          The label of the selectbox.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param string    $sql            Any SQL statement that return 2 columns. The first column will be the internal value of the
     *                                  selectbox item and will be submitted with the form. The second column represents the
     *                                  displayed value of the item. Each row of the result will be a new selectbox entry.
     * @param array     $options (optional) An array with the following possible entries:
     *                           - @b property : With this param you can set the following properties:
     *                             + @b FIELD_DEFAULT  : The field can accept an input.
     *                             + @b FIELD_REQUIRED : The field will be marked as a mandatory field where the user must insert a value.
     *                             + @b FIELD_DISABLED : The field will be disabled and could not accept an input.
     *                           - @b defaultValue : This is the value the selectbox shows when loaded. If @b multiselect is activated than
     *                             an array with all default values could be set.
     *                           - @b showContextDependentFirstEntry : If set to @b true the select box will get an additional first entry.
     *                             If FIELD_REQUIRED is set than "Please choose" will be the first entry otherwise
     *                             an empty entry will be added so you must not select something.
     *                           - @b firstEntry : Here you can define a string that should be shown as firstEntry and will be the
     *                             default value if no other value is set. This entry will only be added if @b showContextDependentFirstEntry
     *                             is set to false!
     *                           - @b multiselect : If set to @b true than the jQuery plugin Select2 will be used to create a selectbox
     *                             where the user could select multiple values from the selectbox. Then an array will be
     *                             created within the $_POST array.
     *                           - @b maximumSelectionNumber : If @b multiselect is enabled then you can configure the maximum number
     *                             of selections that could be done. If this limit is reached the user can't add another entry to the selectbox.
     *                           - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                             e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                             the user can see the text if he hover over the icon. If you need an additional parameter
     *                             for the text you can add an array. The first entry must be the unique text id and the second
     *                             entry will be a parameter of the text id.
     *                           - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                             e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                             If you need an additional parameter for the text you can add an array. The first entry must
     *                             be the unique text id and the second entry will be a parameter of the text id.
     *                           - @b icon : An icon can be set. This will be placed in front of the label.
     *                           - @b class : An additional css classname. The class @b admSelectbox
     *                             is set as default and need not set with this parameter.
     *
     * @par Examples
     * @code // create a selectbox with all profile fields of a specific category
     * $sql = 'SELECT usf_id, usf_name FROM '.TBL_USER_FIELDS.' WHERE usf_cat_id = 4711'
     * $form = new HtmlForm('simple-form', 'next_page.php');
     * $form->addSelectBoxFromSql('admProfileFieldsBox', $gL10n->get('SYS_FIELDS'), $gDb, $sql, array('defaultValue' => $gL10n->get('SYS_SURNAME'), 'showContextDependentFirstEntry' => true));
     * $form->show(); @endcode
     */
    public function addSelectBoxFromSql($id, $label, Database $database, $sql, array $options = array())
    {
        global $gL10n;

        $selectBoxEntries = array();

        // execute the sql statement
        $pdoStatement = $database->query($sql);

        // create array from sql result
        while ($row = $pdoStatement->fetch())
        {
            // if result has 3 columns then create a array in array
            if(array_key_exists(2, $row))
            {
                // translate category name
                if (strpos($row[2], '_') === 3)
                {
                    $selectBoxEntries[] = array($row[0], $row[1], $gL10n->get(admStrToUpper($row[2])));
                }
                else
                {
                    $selectBoxEntries[] = array($row[0], $row[1], $row[2]);
                }
            }
            else
            {
                $selectBoxEntries[$row[0]] = $row[1];
            }
        }

        // now call default method to create a selectbox
        $this->addSelectBox($id, $label, $selectBoxEntries, $options);
    }

    /**
     * Add a new selectbox with a label to the form. The selectbox could have
     * different values and a default value could be set.
     * @param string $id          Id of the selectbox. This will also be the name of the selectbox.
     * @param string $label       The label of the selectbox.
     * @param string $xmlFile     Serverpath to the xml file
     * @param string $xmlValueTag Name of the xml tag that should contain the internal value of a selectbox entry
     * @param string $xmlViewTag  Name of the xml tag that should contain the visual value of a selectbox entry
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b property : With this param you can set the following properties:
     *                          + @b FIELD_DEFAULT  : The field can accept an input.
     *                          + @b FIELD_REQUIRED : The field will be marked as a mandatory field where the user must insert a value.
     *                          + @b FIELD_DISABLED : The field will be disabled and could not accept an input.
     *                        - @b defaultValue : This is the value the selectbox shows when loaded. If @b multiselect is activated than
     *                          an array with all default values could be set.
     *                        - @b showContextDependentFirstEntry : If set to @b true the select box will get an additional first entry.
     *                          If FIELD_REQUIRED is set than "Please choose" will be the first entry otherwise
     *                          an empty entry will be added so you must not select something.
     *                        - @b firstEntry : Here you can define a string that should be shown as firstEntry and will be the
     *                          default value if no other value is set. This entry will only be added if @b showContextDependentFirstEntry
     *                          is set to false!
     *                        - @b multiselect : If set to @b true than the jQuery plugin Select2 will be used to create a selectbox
     *                          where the user could select multiple values from the selectbox. Then an array will be
     *                          created within the $_POST array.
     *                        - @b maximumSelectionNumber : If @b multiselect is enabled then you can configure the maximum number
     *                          of selections that could be done. If this limit is reached the user can't add another entry to the selectbox.
     *                        - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hover over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - @b icon : An icon can be set. This will be placed in front of the label.
     *                        - @b class : An additional css classname. The class @b admSelectbox
     *                          is set as default and need not set with this parameter.
     */
    public function addSelectBoxFromXml($id, $label, $xmlFile, $xmlValueTag, $xmlViewTag, array $options = array())
    {
        $selectBoxEntries = array();

        $xmlRootNode = new SimpleXMLElement($xmlFile, null, true);
        foreach ($xmlRootNode->children() as $xmlChildNode)
        {
            $key   = '';
            $value = '';

            /**
             * @var SimpleXMLElement $xmlChildNode
             */
            foreach ($xmlChildNode->children() as $xmlChildChildNode)
            {
                /**
                 * @var SimpleXMLElement $xmlChildChildNode
                 */
                if ($xmlChildChildNode->getName() === $xmlValueTag)
                {
                    $key = (string) $xmlChildChildNode;
                }
                if ($xmlChildChildNode->getName() === $xmlViewTag)
                {
                    $value = (string) $xmlChildChildNode;
                }
            }

            $selectBoxEntries[$key] = $value;
        }

        // now call default method to create a selectbox
        $this->addSelectBox($id, $label, $selectBoxEntries, $options);
    }

    /**
     * Add a new selectbox with a label to the form. The selectbox get their data from table adm_categories.
     * You must define the category type (roles, dates, links ...). All categories of this type will be shown.
     * @param string    $id             Id of the selectbox. This will also be the name of the selectbox.
     * @param string    $label          The label of the selectbox.
     * @param \Database $database       A Admidio database object that contains a valid connection to a database
     * @param string    $categoryType   Type of category ('DAT', 'LNK', 'ROL', 'USF') that should be shown
     * @param string    $selectBoxModus The selectbox could be shown in 2 different modus.
     *                                  - @b EDIT_CATEGORIES : First entry will be "Please choose" and default category will be preselected.
     *                                  - @b FILTER_CATEGORIES : First entry will be "All" and only categories with childs will be shown.
     * @param array     $options (optional) An array with the following possible entries:
     *                            - @b property : With this param you can set the following properties:
     *                              + @b FIELD_DEFAULT  : The field can accept an input.
     *                              + @b FIELD_REQUIRED : The field will be marked as a mandatory field where the user must insert a value.
     *                              + @b FIELD_DISABLED : The field will be disabled and could not accept an input.
     *                            - @b defaultValue : Id of category that should be selected per default.
     *                            - @b showSystemCategory : Show user defined and system categories
     *                            - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                              e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                              the user can see the text if he hover over the icon. If you need an additional parameter
     *                              for the text you can add an array. The first entry must be the unique text id and the second
     *                              entry will be a parameter of the text id.
     *                            - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                              e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                              If you need an additional parameter for the text you can add an array. The first entry must
     *                              be the unique text id and the second entry will be a parameter of the text id.
     *                            - @b icon : An icon can be set. This will be placed in front of the label.
     *                            - @b class : An additional css classname. The class @b admSelectbox
     *                              is set as default and need not set with this parameter.
     */
    public function addSelectBoxForCategories($id, $label, Database $database, $categoryType, $selectBoxModus, array $options = array())
    {
        global $gCurrentOrganization, $gValidLogin, $gL10n;

        // create array with all options
        $optionsDefault = array(
            'property'                       => FIELD_DEFAULT,
            'defaultValue'                   => '',
            'showContextDependentFirstEntry' => true,
            'multiselect'                    => false,
            'showSystemCategory'             => true,
            'helpTextIdLabel'                => '',
            'helpTextIdInline'               => '',
            'icon'                           => '',
            'class'                          => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        $sqlTables      = '';
        $sqlCondidtions = '';

        // create sql conditions if category must have child elements
        if ($selectBoxModus === 'FILTER_CATEGORIES')
        {
            $optionsAll['showContextDependentFirstEntry'] = false;

            switch ($categoryType)
            {
                case 'DAT':
                    $sqlTables = ' INNER JOIN ' . TBL_DATES . ' ON cat_id = dat_cat_id ';
                    break;
                case 'LNK':
                    $sqlTables = ' INNER JOIN ' . TBL_LINKS . ' ON cat_id = lnk_cat_id ';
                    break;
                case 'ROL':
                    // don't show system categories
                    $sqlTables = ' INNER JOIN ' . TBL_ROLES . ' ON cat_id = rol_cat_id';
                    $sqlCondidtions = ' AND rol_visible = 1 ';
                    break;
                case 'INF':
                    $sqlTables = ' INNER JOIN ' . TBL_INVENT_FIELDS . ' ON cat_id = inf_cat_id ';
                    break;
            }
        }

        if (!$optionsAll['showSystemCategory'])
        {
            $sqlCondidtions .= ' AND cat_system = 0 ';
        }

        if (!$gValidLogin)
        {
            $sqlCondidtions .= ' AND cat_hidden = 0 ';
        }

        // the sql statement which returns all found categories
        $sql = 'SELECT DISTINCT cat_id, cat_name, cat_default, cat_sequence
                  FROM ' . TBL_CATEGORIES . '
                       '.$sqlTables.'
                 WHERE (  cat_org_id = ' . $gCurrentOrganization->getValue('org_id') . '
                       OR cat_org_id IS NULL )
                   AND cat_type = \'' . $categoryType . '\'
                       ' . $sqlCondidtions . '
              ORDER BY cat_sequence ASC';
        $pdoStatement = $database->query($sql);
        $countCategories = $pdoStatement->rowCount();

        // if no or only one category exist and in filter modus, than don't show category
        if (($countCategories === 0 || $countCategories === 1) && $selectBoxModus === 'FILTER_CATEGORIES')
        {
            return;
        }

        $categoriesArray = array();

        if ($countCategories > 1 && $selectBoxModus === 'FILTER_CATEGORIES')
        {
            $categoriesArray[0] = $gL10n->get('SYS_ALL');
        }

        while ($row = $pdoStatement->fetch())
        {
            // if several categories exist than select default category
            if ($optionsAll['defaultValue'] === '' && ($countCategories === 1 || $row['cat_default'] === '1'))
            {
                $optionsAll['defaultValue'] = $row['cat_id'];
            }

            // if text is a translation-id then translate it
            if (strpos($row['cat_name'], '_') === 3)
            {
                $categoriesArray[$row['cat_id']] = $gL10n->get(admStrToUpper($row['cat_name']));
            }
            else
            {
                $categoriesArray[$row['cat_id']] = $row['cat_name'];
            }
        }

        // now call method to create selectbox from array
        $this->addSelectBox($id, $label, $categoriesArray, $optionsAll);
    }

    /**
     * Add a new static control to the form. A static control is only a simple text instead of an input field.
     * This could be used if the value should not be changed by the user.
     * @param string $id      Id of the static control. This will also be the name of the static control.
     * @param string $label   The label of the static control.
     * @param string $value   A value of the static control. The control will be created with this value.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - @b helpTextIdLabel : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hover over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - @b helpTextIdInline : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_ENTRY_MULTI_ORGA. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - @b icon : An icon can be set. This will be placed in front of the label.
     *                        - @b class : An additional css classname. The class @b admSelectbox
     *                          is set as default and need not set with this parameter.
     */
    public function addStaticControl($id, $label, $value, array $options = array())
    {
        $attributes = array('class' => 'form-control-static');
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array('helpTextIdLabel' => '', 'helpTextIdInline' => '', 'icon' => '', 'class' => '');
        $optionsAll     = array_replace($optionsDefault, $options);

        // set specific css class for this field
        if ($optionsAll['class'] !== '')
        {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        // now create html for the field
        $this->openControlStructure($id, $label, FIELD_DEFAULT, $optionsAll['helpTextIdLabel'], $optionsAll['icon']);
        $this->addHtml('<p class="' . $attributes['class'] . '">' . $value . '</p>');
        $this->closeControlStructure($optionsAll['helpTextIdInline']);
    }

    /**
     * Open a bootstrap btn-group if the form need more than one button.
     */
    public function openButtonGroup()
    {
        $this->buttonGroupOpen = true;
        $this->addHtml('<div class="btn-group" role="group">');
    }

    /**
     * Close an open bootstrap btn-group
     */
    public function closeButtonGroup()
    {
        $this->buttonGroupOpen = false;
        $this->addHtml('</div><div class="form-alert" style="display: none;">&nbsp;</div>');
    }

    /**
     * Closes a field structure that was added with the method openControlStructure.
     * @param string|string[] $helpTextId A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                                    If set the complete text will be shown after the form element.
     * @param string[]        $parameters If you need an additional parameter for the text you can set this array.
     */
    protected function closeControlStructure($helpTextId = '', array $parameters = array())
    {
        global $gL10n;

        // backwards compatibility
        if (is_array($helpTextId))
        {
            $parameters = $helpTextId;
            $helpTextId = array_shift($parameters);
        }

        if ($helpTextId !== '')
        {
            if (count($parameters) === 0)
            {
                // if text is a translation-id then translate it
                if (strpos($helpTextId, '_') === 3)
                {
                    $helpText = $gL10n->get($helpTextId);
                }
                else
                {
                    $helpText = $helpTextId;
                }
            }
            else
            {
                foreach ($parameters as &$parameter)
                {
                    // if parameter is a translation-id then translate it
                    if (strpos($parameter, '_') === 3)
                    {
                        $parameter = $gL10n->get($parameter);
                    }
                }
                unset($parameter);

                // PHP 5.6+ use: $helpText = $gL10n->get($helpTextId, ...$parameters);
                if (count($parameters) === 1)
                {
                    $helpText = $gL10n->get($helpTextId, $parameters[0]);
                }
                else
                {
                    $helpText = $gL10n->get($helpTextId, $parameters[0], $parameters[1]);
                }
            }

            $this->addHtml('<div class="help-block">' . $helpText . '</div>');
        }

        if ($this->type === 'vertical' || $this->type === 'navbar')
        {
            $this->addHtml('</div>');
        }
        else
        {
            $this->addHtml('</div></div>');
        }
    }

    /**
     * Creates a html structure for a form field. This structure contains the label and the div for the form element.
     * After the form element is added the method closeControlStructure must be called.
     * @param string $id         The id of this field structure.
     * @param string $label      The label of the field. This string should already be translated.
     * @param int    $property   (optional) With this param you can set the following properties:
     *                           - @b FIELD_DEFAULT  : The field can accept an input.
     *                           - @b FIELD_REQUIRED : The field will be marked as a mandatory field where the user must insert a value.
     *                           - @b FIELD_DISABLED : The field will be disabled and could not accept an input.
     * @param string $helpTextId (optional) A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     *                           If set a help icon will be shown where the user can see the text if he hover over the icon.
     *                           If you need an additional parameter for the text you can add an array. The first entry
     *                           must be the unique text id and the second entry will be a parameter of the text id.
     * @param string $icon       (optional) An icon can be set. This will be placed in front of the label.
     * @param string $class      (optional) An additional css classname for the row. The class @b admFieldRow
     *                           is set as default and need not set with this parameter.
     */
    protected function openControlStructure($id, $label, $property = FIELD_DEFAULT, $helpTextId = '', $icon = '', $class = '')
    {
        $cssClassRow  = '';
        $htmlIcon     = '';
        $htmlHelpIcon = '';
        $htmlIdFor    = '';

        // set specific css class for this row
        if ($class !== '')
        {
            $cssClassRow .= ' ' . $class;
        }

        // if necessary set css class for a mandatory element
        if ($property === FIELD_REQUIRED && $this->showRequiredFields)
        {
            $cssClassRow .= ' admidio-form-group-required';
            $this->flagRequiredFields = true;
        }

        if ($id !== '')
        {
            $htmlIdFor = ' for="' . $id . '"';
            $this->addHtml('<div id="' . $id . '_group" class="form-group' . $cssClassRow . '">');
        }
        else
        {
            $this->addHtml('<div class="form-group' . $cssClassRow . '">');
        }

        if (strlen($icon) > 0)
        {
            // create html for icon
            if (strpos(admStrToLower($icon), 'http') === 0 && strValidCharacters($icon, 'url'))
            {
                $htmlIcon = '<img class="admidio-icon-info" src="' . $icon . '" title="' . $label . '" alt="' . $label . '" />';
            }
            elseif (admStrIsValidFileName($icon, true))
            {
                $htmlIcon = '<img class="admidio-icon-info" src="' . THEME_URL . '/icons/' . $icon . '" title="' . $label . '" alt="' . $label . '" />';
            }
        }

        if ($helpTextId !== '')
        {
            $htmlHelpIcon = self::getHelpTextIcon($helpTextId);
        }

        // add label element
        if ($this->type === 'vertical' || $this->type === 'navbar')
        {
            if ($label !== '')
            {
                $this->addHtml('<label' . $htmlIdFor . '>' . $htmlIcon . $label . $htmlHelpIcon . '</label>');
            }
        }
        else
        {
            if ($label !== '')
            {
                $this->addHtml(
                    '<label' . $htmlIdFor . ' class="col-sm-3 control-label">' . $htmlIcon . $label . $htmlHelpIcon . '</label>
                    <div class="col-sm-9">'
                );
            }
            else
            {
                $this->addHtml('<div class="col-sm-offset-3 col-sm-9">');
            }
        }
    }

    /**
     * Add a new groupbox to the form. This could be used to group some elements
     * together. There is also the option to set a headline to this group box.
     * @param string $id       Id the the groupbox.
     * @param string $headline (optional) A headline that will be shown to the user.
     * @param string $class    (optional) An additional css classname for the row. The class @b admFieldRow
     *                         is set as default and need not set with this parameter.
     */
    public function openGroupBox($id, $headline = null, $class = '')
    {
        $this->addHtml('<div id="' . $id . '" class="panel panel-default ' . $class . '">');
        // add headline to groupbox
        if ($headline !== null)
        {
            $this->addHtml('<div class="panel-heading">' . $headline . '</div>');
        }
        $this->addHtml('<div class="panel-body">');
    }

    /**
     * Close all html elements of a groupbox that was created before.
     */
    public function closeGroupBox()
    {
        $this->addHtml('</div></div>');
    }

    /**
     * Add a small help icon to the form at the current element which shows the
     * translated text of the text-id on mouseover or when you click on the icon.
     * @param string|string[] $textId    A unique text id from the translation xml files that should be shown e.g. SYS_ENTRY_MULTI_ORGA.
     * @param string          $parameter If you need an additional parameter for the text you can set this parameter.
     * @return string Return a html snippet that contains a help icon with a link to a popup box that shows the message.
     */
    public static function getHelpTextIcon($textId, $parameter = null)
    {
        global $gL10n, $gProfileFields;

        // backwards compatibility
        if (is_array($textId))
        {
            list($textId, $parameter) = $textId;
        }

        if ($parameter === null)
        {
            $text = $gL10n->get($textId);
        }
        else
        {
            if ($textId === 'user_field_description')
            {
                $text = $gProfileFields->getProperty($parameter, 'usf_description');
            }
            else
            {
                $text = $gL10n->get($textId, $parameter);
            }
        }

        return '<img class="admidio-icon-help" src="' . THEME_URL . '/icons/help.png"
            title="' . $gL10n->get('SYS_NOTE') . '" alt="Help" data-toggle="popover" data-html="true"
            data-trigger="hover" data-placement="auto" data-content="' . htmlspecialchars($text) . '" />';
    }

    /**
     * This method send the whole html code of the form to the browser. Call this method
     * if you have finished your form layout. If mandatory fields were set than a notice
     * which marker represents the mandatory will be shown before the form.
     * @param bool $directOutput (optional) If set to @b true (default) the form html will be directly send
     *                                   to the browser. If set to @b false the html will be returned.
     * @return string|null If $directOutput is set to @b false this method will return the html code of the form.
     */
    public function show($directOutput = true)
    {
        global $gL10n;

        // if there are no elements in the form then return nothing
        if ($this->countElements === 0)
        {
            return null;
        }

        $html = '';

        // If required fields were set than a notice which marker represents the required fields will be shown.
        if ($this->flagRequiredFields && $this->showRequiredFields)
        {
            $html .= '<div class="admidio-form-required-notice"><span>' . $gL10n->get('SYS_REQUIRED_FIELDS') . '</span></div>';
        }

        // now get whole form html code
        $html .= $this->getHtmlForm();

        if ($directOutput)
        {
            echo $html;
            return null;
        }

        return $html;
    }
}
