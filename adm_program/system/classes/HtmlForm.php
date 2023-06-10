<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Creates an Admidio specific form with special elements
 *
 * This class inherits the common HtmlFormBasic class and extends their elements
 * with custom Admidio form elements. The class should be used to create the
 * html part of all Admidio forms. The Admidio elements will contain
 * the label of fields and some other specific features like an identification
 * of mandatory fields, help buttons and special css classes for every
 * element.
 *
 * **Code examples**
 * ```
 * // create a simple form with one input field and a button
 * $form = new HtmlForm('simple-form', 'next_page.php');
 * $form->openGroupBox('gbSimpleForm', $gL10n->get('SYS_SIMPLE_FORM'));
 * $form->addInput('name', $gL10n->get('SYS_NAME'), $formName);
 * $form->addSelectBox('type', $gL10n->get('SYS_TYPE'), array('simple' => 'SYS_SIMPLE', 'very-simple' => 'SYS_VERY_SIMPLE'),
 *                     array('defaultValue' => 'simple', 'showContextDependentFirstEntry' => true));
 * $form->closeGroupBox();
 * $form->addSubmitButton('next-page', $gL10n->get('SYS_NEXT'), array('icon' => 'fa-arrow-circle-right'));
 * $form->show();
 * ```
 */
class HtmlForm extends HtmlFormBasic
{
    public const FIELD_DEFAULT  = 0;
    public const FIELD_REQUIRED = 1;
    public const FIELD_DISABLED = 2;
    public const FIELD_READONLY = 3;
    public const FIELD_HIDDEN   = 4;

    public const SELECT_BOX_MODUS_EDIT = 'EDIT_CATEGORIES';
    public const SELECT_BOX_MODUS_FILTER = 'FILTER_CATEGORIES';

    /**
     * @var bool Flag if this form has required fields. Then a notice must be written at the end of the form
     */
    protected $flagRequiredFields = false;
    /**
     * @var bool Flag if required fields should get a special css class to make them more visible to the user.
     */
    protected $showRequiredFields;
    /**
     * @var HtmlPage A HtmlPage object that will be used to add javascript code or files to the html output page.
     */
    protected $htmlPage;
    /**
     * @var int Number of visible elements in this form. Hidden elements are not count because no interaction is possible.
     */
    protected $countElements = 0;
    /**
     * @var string Form type. Possible values are **default**, **vertical** or **navbar**.
     */
    protected $type;
    /**
     * @var string ID of the form
     */
    protected $id;
    /**
     * @var bool Flag that indicates if a bootstrap button-group is open and should be closed later
     */
    protected $buttonGroupOpen = false;

    /**
     * Constructor creates the form element
     * @param string   $id       ID of the form
     * @param string   $action   Action attribute of the form
     * @param HtmlPage|null $htmlPage (optional) A HtmlPage object that will be used to add javascript code or files to the html output page.
     * @param array    $options  (optional) An array with the following possible entries:
     *                           - **type** : Set the form type. Every type has some special features:
     *                             + **default**  : A form that can be used to edit and save data of a database table. The label
     *                               and the element have a horizontal orientation.
     *                             + **vertical** : A form that can be used to edit and save data but has a vertical orientation.
     *                               The label is positioned above the form element.
     *                             + **navbar**   : A form that should be used in a navbar. The form content will
     *                               be sent with the 'GET' method and this form should not get a default focus.
     *                           - **method** : Method how the values of the form are submitted.
     *                             Possible values are **get** and **post** (default).
     *                           - **enableFileUpload** : Set specific parameters that are necessary for file upload with a form
     *                           - **showRequiredFields** : If this is set to **true** (default) then every required field got a special
     *                             css class and also the form got a **div** that explains the required layout.
     *                             If this is set to **false** then only the html flag **required** will be set.
     *                           - **setFocus** : Default is set to **true**. Set the focus on page load to the first field
     *                             of this form.
     *                           - **class** : An additional css classname. The class **form-horizontal**
     *                             is set as default and need not set with this parameter.
     */
    public function __construct($id, $action = null, HtmlPage $htmlPage = null, array $options = array())
    {
        // create array with all options
        $optionsDefault = array(
            'type'               => 'default',
            'enableFileUpload'   => false,
            'showRequiredFields' => true,
            'setFocus'           => true,
            'class'              => '',
            'method'             => 'post'
        );

        // navbar forms should send the data as GET if it's not explicit set
        if (isset($options['type']) && $options['type'] === 'navbar' && !isset($options['method'])) {
            $options['method'] = 'get';
        }

        $optionsAll = array_replace($optionsDefault, $options);

        parent::__construct($action, $id, $optionsAll['method']);

        $this->showRequiredFields = $optionsAll['showRequiredFields'];
        $this->type = $optionsAll['type'];
        $this->id   = $id;

        // set specific Admidio css form class
        $this->addAttribute('role', 'form');

        if ($this->type === 'default') {
            $optionsAll['class'] .= ' form-horizontal form-dialog';
        } elseif ($this->type === 'vertical') {
            $optionsAll['class'] .= ' admidio-form-vertical form-dialog';
        } elseif ($this->type === 'navbar') {
            $optionsAll['class'] .= ' form-inline ';
        }

        if ($optionsAll['class'] !== '') {
            $this->addAttribute('class', $optionsAll['class']);
        }

        // Set specific parameters that are necessary for file upload with a form
        if ($optionsAll['enableFileUpload']) {
            $this->addAttribute('enctype', 'multipart/form-data');
        }

        if ($optionsAll['method'] === 'post' && isset($GLOBALS['gCurrentSession'])) {
            // add a hidden field with the csrf token to each form
            $this->addInput(
                'admidio-csrf-token',
                'csrf-token',
                $GLOBALS['gCurrentSession']->getCsrfToken(),
                array('property' => self::FIELD_HIDDEN)
            );
        }

        if ($htmlPage instanceof HtmlPage) {
            $this->htmlPage =& $htmlPage;
        }

        // if it's not a navbar form and not a static form then first field of form should get focus
        if ($optionsAll['setFocus']) {
            $this->addJavascriptCode('$(".form-dialog:first *:input:enabled:visible:not([readonly]):first").focus();', true);
        }
    }

    /**
     * Add a new button with a custom text to the form. This button could have
     * an icon in front of the text.
     * @param string $id      ID of the button. This will also be the name of the button.
     * @param string $text    Text of the button
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **icon** : Optional parameter. Path and filename of an icon.
     *                          If set an icon will be shown in front of the text.
     *                        - **link** : If set a javascript click event with a page load to this link
     *                          will be attached to the button.
     *                        - **class** : Optional an additional css classname. The class **admButton**
     *                          is set as default and need not set with this parameter.
     *                        - **type** : Optional a button type could be set. The default is **button**.
     */
    public function addButton($id, $text, array $options = array())
    {
        // create array with all options
        $optionsDefault = array('formtype' => $this->type,
            'icon' => '',
            'link' => '',
            'class' => '',
            'type' => 'button',
            'data-admidio' => '',
            'id' => $id,
            'value' => $text,
        );
        $optionsAll = array_replace($optionsDefault, $options);
        $attributes = array();
        $attributes['class'] = $optionsAll['class'];
        $attributes['type'] = $optionsAll['type'];
        $attributes['data-admidio'] = $optionsAll['data-admidio'];
        ++$this->countElements;

        if(strstr($attributes['class'], ' btn ') === false) {
            $attributes['class'] = "btn btn-secondary " . $optionsAll['class'];

            if ($this->type !== 'navbar') {
                $attributes['class'] .= '  admidio-margin-bottom';
            }
        }

        $optionsAll['attributes'] = $attributes;
        $this->addHtml($this->render('form.button', $optionsAll));
    }

    /**
     * Add a captcha with an input field to the form. The captcha could be a picture with a character code
     * or a simple mathematical calculation that must be solved.
     * @param string $id    ID of the captcha field. This will also be the name of the captcha field.
     * @param string $class (optional) An additional css classname. The class **admTextInput**
     *                      is set as default and need not set with this parameter.
     */
    public function addCaptcha($id, $class = '')
    {
        global $gL10n;

        $attributes = array('class' => 'captcha');
        ++$this->countElements;

        // set specific css class for this field
        if ($class !== '') {
            $attributes['class'] .= ' ' . $class;
        }

        $this->addHtml($this->render('form.captcha', ['attributes' => $attributes]));
        // now add a row with a text field where the user can write the solution for the puzzle
        $this->addInput(
            $id,
            $gL10n->get('SYS_CAPTCHA_CONFIRMATION_CODE'),
            '',
            array('property' => self::FIELD_REQUIRED, 'helpTextIdLabel' => 'SYS_CAPTCHA_DESCRIPTION', 'class' => 'form-control-small')
        );

    }

    /**
     * Add a new checkbox with a label to the form.
     * @param string $id      ID of the checkbox. This will also be the name of the checkbox.
     * @param string $label   The label of the checkbox.
     * @param bool   $checked A value for the checkbox. The value could only be **0** or **1**. If the value is **1** then
     *                        the checkbox will be checked when displayed.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                          + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                        - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hovers over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addCheckbox($id, $label, $checked = false, array $options = array())
    {
        $attributes   = array('class' => '');
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array('formtype' => $this->type,
            'property'         => self::FIELD_DEFAULT,
            'helpTextIdLabel'  => '',
            'helpTextIdInline' => '',
            'icon'             => '',
            'class'            => '',
            'alertWarning'     => '',
            'id'               => $id,
            'label'            => $label
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // disable field
        if ($optionsAll['property'] === self::FIELD_DISABLED) {
            $attributes['disabled'] = 'disabled';
        } elseif ($optionsAll['property'] === self::FIELD_REQUIRED) {
            $attributes['required'] = 'required';
            $this->flagRequiredFields = true;
        }

        // if checked = true then set checkbox checked
        if ($checked) {
            $attributes['checked'] = 'checked';
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '') {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        $optionsAll["attributes"] = $attributes;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->addHtml($this->render('form.checkbox', $optionsAll));
    }

    /**
     * Add custom html content to the form within the default field structure.
     * The Label will be set but instead of a form control you can define any html.
     * If you don't need the field structure and want to add html then use the method addHtml()
     * @param string $label   The label of the custom content.
     * @param string $content A simple Text or html that would be placed instead of a form element.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **referenceId** : Optional the id of a form control if this is defined within the custom content
     *                        - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hovers over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addCustomContent($label, $content, array $options = array())
    {
        // create array with all options
        $optionsDefault = array('formtype' => $this->type,
            'property'         => '',
            'referenceId'      => '',
            'helpTextIdLabel'  => '',
            'helpTextIdInline' => '',
            'icon'             => '',
            'class'            => '',
            'label'            => $label,
            'content'          => $content,
        );
        $optionsAll = array_replace($optionsDefault, $options);

        $this->addHtml($this->render('form.customcontent', $optionsAll));


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
     * @param string $id      ID of the password field. This will also be the name of the password field.
     * @param string $label   The label of the password field.
     * @param string $value   A value for the editor field. The editor will contain this value when created.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                        - **toolbar** : Optional set a predefined toolbar for the editor. Possible values are
     *                          **AdmidioDefault**, **AdmidioGuestbook** and **AdmidioPlugin_WC**
     *                        - **height** : Optional set the height in pixel of the editor. The default will be 300.
     *                        - **labelVertical** : If set to **true** (default) then the label will be display above the control and the control get a width of 100%.
     *                          Otherwise, the label will be displayed in front of the control.
     *                        - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hovers over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addEditor($id, $label, $value, array $options = array())
    {
        global $gSettingsManager, $gL10n;

        $attributes = array('class' => 'editor');
        $flagLabelVertical = $this->type;
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array('formtype' => $this->type,
            'property'         => self::FIELD_DEFAULT,
            'toolbar'          => 'AdmidioDefault',
            'height'           => '300',
            'helpTextIdLabel'  => '',
            'helpTextIdInline' => '',
            'labelVertical'    => true,
            'icon'             => '',
            'class'            => '',
            'id'               => $id,
            'label'            => $label,
            'value'            => $value,
        );
        $optionsAll = array_replace($optionsDefault, $options);

        if ($optionsAll['labelVertical']) {
            $this->type = 'vertical';
        }

        if ($optionsAll['property'] === self::FIELD_REQUIRED) {
            $attributes['required'] = 'required';
            $this->flagRequiredFields = true;
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '') {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }
        $javascriptCode = '
            CKEDITOR.replace("' . $id . '", {
                toolbar: "' . $optionsAll['toolbar'] . '",
                language: "' . $gL10n->getLanguageLibs() . '",
                uiColor: "' . $gSettingsManager->getString('system_js_editor_color') . '",
                filebrowserUploadMethod: "form",
                filebrowserImageUploadUrl: "' . ADMIDIO_URL . '/adm_program/system/ckeditor_upload_handler.php"
            });
            CKEDITOR.config.height = "' . $optionsAll['height'] . '";';

        if ($gSettingsManager->getBool('system_js_editor_enabled')) {
            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if ($this->htmlPage instanceof HtmlPage) {
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/ckeditor/ckeditor.js');
            }
            $this->addJavascriptCode($javascriptCode, true);
        }

        $this->type = $flagLabelVertical;
        $optionsAll["attributes"] = $attributes;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->addHtml($this->render('form.editor', $optionsAll));
    }

    /**
     * Add a field for file upload. If necessary multiple files could be uploaded.
     * The fields for multiple upload could be added dynamically to the form by the user.
     * @param string $id      ID of the input field. This will also be the name of the input field.
     * @param string $label   The label of the input field.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                          + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                        - **allowedMimeTypes** : An array with the allowed MIME types (https://wiki.selfhtml.org/wiki/MIME-Type/%C3%9Cbersicht).
     *                          If this is set then the user can only choose the specified files with the browser file dialog.
     *                          You should check the uploaded file against the MIME type because the file could be manipulated.
     *                        - **maxUploadSize** : The size in byte that could be maximum uploaded.
     *                          The default will be $gSettingsManager->getInt('max_file_upload_size') * 1024 * 1024.
     *                        - **enableMultiUploads** : If set to true a button will be added where the user can
     *                          add new upload fields to upload more than one file.
     *                        - **multiUploadLabel** : The label for the button who will add new upload fields to the form.
     *                        - **hideUploadField** : Hide the upload field if multi uploads are enabled. Then the first
     *                          upload field will be shown if the user will click the multi upload button.
     *                        - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hovers over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addFileUpload($id, $label, array $options = array())
    {
        global $gSettingsManager;

        $attributes = array('class' => 'form-control');
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array('formtype' => $this->type,
            'property'           => self::FIELD_DEFAULT,
            'maxUploadSize'      => $gSettingsManager->getInt('max_file_upload_size') * 1024 * 1024, // MiB
            'allowedMimeTypes'   => array(),
            'enableMultiUploads' => false,
            'hideUploadField'    => false,
            'multiUploadLabel'   => '',
            'helpTextIdLabel'    => '',
            'helpTextIdInline'   => '',
            'icon'               => '',
            'class'              => '',
            'id'                 => $id,
            'label'              => $label,
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // disable field
        if ($optionsAll['property'] === self::FIELD_DISABLED) {
            $attributes['disabled'] = 'disabled';
        } elseif ($optionsAll['property'] === self::FIELD_REQUIRED) {
            $attributes['required'] = 'required';
            $this->flagRequiredFields = true;
        }

        if (count($optionsAll['allowedMimeTypes']) > 0) {
            $attributes['accept'] = implode(',', $optionsAll['allowedMimeTypes']);
        }

        if ($optionsAll['icon'] === '') {
            $optionsAll['icon'] = 'fa-upload';
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '') {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        // if multiple uploads are enabled then add javascript that will
        // dynamically add new upload fields to the form
        if ($optionsAll['enableMultiUploads']) {
            $javascriptCode = '
                // add new line to add new attachment to this mail
                $("#btn_add_attachment_' . $id . '").click(function() {
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

        $optionsAll["attributes"] = $attributes;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->addHtml($this->render('form.file', $optionsAll));
    }

    /**
     * Add a new input field with a label to the form.
     * @param string $id      ID of the input field. This will also be the name of the input field.
     * @param string $label   The label of the input field.
     * @param string $value   A value for the text field. The field will be created with this value.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **type** : Set the type if the field. Default will be **text**. Possible values are **text**,
     *                          **number**, **date**, **datetime** or **birthday**. If **date**, **datetime** or **birthday** are set
     *                          than a small calendar will be shown if the date field will be selected.
     *                        - **maxLength** : The maximum number of characters that are allowed in a text field.
     *                        - **minNumber** : The minimum number that is allowed in a number field.
     *                        - **maxNumber** : The maximum number that is allowed in a number field.
     *                        - **step** : The steps between two numbers that are allowed.
     *                          E.g. if steps is set to 5 then only values 5, 10, 15 ... are allowed
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                          + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                          + **self::FIELD_HIDDEN**   : The field will not be shown. Useful to transport additional informations.
     *                        - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hovers over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     *                        - **htmlAfter** : Add html code after the input field.
     */
    public function addInput($id, $label, $value, array $options = array())
    {
        global $gSettingsManager, $gLogger;

        $attributes = array('class' => 'form-control');
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array('formtype' => $this->type,
            'id'               => $id,
            'label'            => $label,
            'value'            => $value,
            'type'             => 'text',
            'placeholder'      => '',
            'pattern'          => '',
            'minLength'        => null,
            'maxLength'        => null,
            'minNumber'        => null,
            'maxNumber'        => null,
            'step'             => null,
            'property'         => self::FIELD_DEFAULT,
            'passwordStrength' => false,
            'passwordUserData' => array(),
            'helpTextIdLabel'  => '',
            'helpTextIdInline' => '',
            'icon'             => '',
            'class'            => '',
            'htmlAfter'        => '',
            'alertWarning'     => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        $optionsAll['helpTextIdInline'] = self::getHelpText($optionsAll['helpTextIdInline']);

        $attributes['placeholder'] = $optionsAll['placeholder'];

        // set min/max input length
        switch ($optionsAll['type']) {
            case 'text': // fallthrough
            case 'search': // fallthrough
            case 'email': // fallthrough
            case 'url': // fallthrough
            case 'tel': // fallthrough
            case 'password':
                $attributes['pattern'] = $optionsAll['pattern'];

                $attributes['minlength'] = $optionsAll['minLength'];

                if ($optionsAll['maxLength'] > 0) {
                    $attributes['maxlength'] = $optionsAll['maxLength'];

                    if ($attributes['minlength'] > $attributes['maxlength']) {
                        $gLogger->warning(
                            'Attribute "minlength" is greater than "maxlength"!',
                            array('minlength' => $attributes['maxlength'], 'maxlength' => $attributes['maxlength'])
                        );
                    }
                }
                break;
            case 'number':
                $attributes['min'] = $optionsAll['minNumber'];
                $attributes['max'] = $optionsAll['maxNumber'];
                $attributes['step'] = $optionsAll['step'];

                if ($attributes['min'] > $attributes['max']) {
                    $gLogger->warning(
                        'Attribute "min" is greater than "max"!',
                        array('min' => $attributes['min'], 'max' => $attributes['max'])
                    );
                }
                break;
        }

        // set field properties
        switch ($optionsAll['property']) {
            case self::FIELD_DISABLED:
                $attributes['disabled'] = 'disabled';
                break;

            case self::FIELD_READONLY:
                $attributes['readonly'] = 'readonly';
                break;

            case self::FIELD_REQUIRED:
                $attributes['required'] = 'required';
                $this->flagRequiredFields = true;
                break;

            case self::FIELD_HIDDEN:
                $attributes['hidden'] = 'hidden';
                $attributes['class'] .= ' invisible';
                break;
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '') {
            $attributes['class'] .= ' '.$optionsAll['class'];
        }

        // Remove attributes that are not set
        $attributes = array_filter($attributes, function ($attribute) {
            return $attribute !== '' && $attribute !== null;
        });

        // if datetime then add a time field behind the date field
        if ($optionsAll['type'] === 'datetime') {
            $datetime = DateTime::createFromFormat($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'), $value);

            // now add a date and a time field to the form
            $attributes['dateValue'] = null;
            $attributes['timeValue'] = null;

            if ($datetime) {
                $attributes['dateValue'] = $datetime->format('Y-m-d');
                $attributes['timeValue'] = $datetime->format('H:i');
            }

            // now add a date and a time field to the form
            $attributes['dateValueAttributes'] = array();
            $attributes['dateValueAttributes']['class'] = 'form-control datetime-date-control';
            $attributes['dateValueAttributes']['pattern'] = '\d{4}-\d{2}-\d{2}';

            $attributes['timeValueAttributes'] = array();
            $attributes['timeValueAttributes']['class'] = 'form-control datetime-time-control';
        } elseif ($optionsAll['type'] === 'date') {
            $datetime = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $value);
            if (!empty($value) && is_object($datetime))
                $value = $datetime->format('Y-m-d');
            $attributes['pattern'] = '\d{4}-\d{2}-\d{2}';
        } elseif ($optionsAll['type'] === 'time') {
            $datetime = DateTime::createFromFormat('Y-m-d' . $gSettingsManager->getString('system_time'), DATE_NOW . $value);
            if (!empty($value) && is_object($datetime))
                $value = $datetime->format('H:i');
        }

        if ($optionsAll['passwordStrength']) {
            $passwordStrengthLevel = 1;
            if ($gSettingsManager instanceof SettingsManager && $gSettingsManager->getInt('password_min_strength')) {
                $passwordStrengthLevel = $gSettingsManager->getInt('password_min_strength');
            }

            if ($this->htmlPage instanceof HtmlPage) {
                $zxcvbnUserInputs = json_encode($optionsAll['passwordUserData'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $javascriptCode = '
                    $("#admidio-password-strength-minimum").css("margin-left", "calc(" + $("#admidio-password-strength").css("width") + " / 4 * '.$passwordStrengthLevel.')");

                    $("#' . $id . '").keyup(function(e) {
                        var result = zxcvbn(e.target.value, ' . $zxcvbnUserInputs . ');
                        var cssClasses = ["bg-danger", "bg-danger", "bg-warning", "bg-info", "bg-success"];

                        var progressBar = $("#admidio-password-strength .progress-bar");
                        progressBar.attr("aria-valuenow", result.score * 25);
                        progressBar.css("width", result.score * 25 + "%");
                        progressBar.removeClass(cssClasses.join(" "));
                        progressBar.addClass(cssClasses[result.score]);
                    });
                ';
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/zxcvbn/dist/zxcvbn.js');
                $this->htmlPage->addJavascript($javascriptCode, true);
            }
        }

        $optionsAll["attributes"] = $attributes;
        // replace quotes with html entities to prevent xss attacks
        $optionsAll['value'] = $value;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->addHtml($this->render("form.input", $optionsAll));
    }

    /**
     * Adds any javascript content to the page. The javascript will be added to the page header or as inline script.
     * @param string $javascriptCode       A valid javascript code that will be added to the header of the page or as inline script.
     * @param bool   $executeAfterPageLoad (optional) If set to **true** the javascript code will be executed after
     *                                     the page is fully loaded.
     */
    protected function addJavascriptCode($javascriptCode, $executeAfterPageLoad = false)
    {
        if ($this->htmlPage instanceof HtmlPage) {
            $this->htmlPage->addJavascript($javascriptCode, $executeAfterPageLoad);
            return;
        }

        if ($executeAfterPageLoad) {
            $javascriptCode = '$(function() { ' . $javascriptCode . ' });';
        }
        $this->addHtml('<script type="text/javascript">' . $javascriptCode . '</script>');
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
     * @param string $id      ID of the input field. This will also be the name of the input field.
     * @param string $label   The label of the input field.
     * @param string $value   A value for the text field. The field will be created with this value.
     * @param int    $rows    The number of rows that the textarea field should have.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **maxLength** : The maximum number of characters that are allowed in this field. If set
     *                          then show a counter how many characters still available
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                          + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                        - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hovers over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addMultilineTextInput($id, $label, $value, $rows, array $options = array())
    {
        ++$this->countElements;
        $attributes = array('class' => 'form-control');

        // create array with all options
        $optionsDefault = array('formtype' => $this->type,
            'property'         => self::FIELD_DEFAULT,
            'maxLength'        => 0,
            'helpTextIdLabel'  => '',
            'helpTextIdInline' => '',
            'icon'             => '',
            'class'            => '',
            'id'               => $id,
            'label'            => $label,
            'value'            => $value
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // set field properties
        switch ($optionsAll['property']) {
            case self::FIELD_DISABLED:
                $attributes['disabled'] = 'disabled';
                break;

            case self::FIELD_READONLY:
                $attributes['readonly'] = 'readonly';
                break;

            case self::FIELD_REQUIRED:
                $attributes['required'] = 'required';
                $this->flagRequiredFields = true;
                break;

            case self::FIELD_HIDDEN:
                $attributes['hidden'] = 'hidden';
                $attributes['class'] .= ' invisible';
                break;
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '') {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        if ($optionsAll['maxLength'] > 0) {
            $attributes['maxlength'] = $optionsAll['maxLength'];

            // if max field length is set then show a counter how many characters still available
            $javascriptCode = '
                $("#' . $id . '").NobleCount("#' . $id . '_counter", {
                    max_chars: ' . $optionsAll['maxLength'] . ',
                    on_negative: "systeminfoBad",
                    block_negative: true
                });';

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if ($this->htmlPage instanceof HtmlPage) {
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/noblecount/jquery.noblecount.js');
            }
            $this->addJavascriptCode($javascriptCode, true);
        }

        $attributes["rows"] = $rows;
        $attributes["cols"] = 80;
        $optionsAll["attributes"] = $attributes;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->addHtml($this->render('form.multiline', $optionsAll));
    }

    /**
     * Add a new radio button with a label to the form. The radio button could have different status
     * which could be defined with an array.
     * @param string $id      ID of the radio button. This will also be the name of the radio button.
     * @param string $label   The label of the radio button.
     * @param array  $values  Array with all entries of the radio button;
     *                        Array key will be the internal value of the entry
     *                        Array value will be the visual value of the entry
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                          + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                        - **defaultValue** : This is the value of that radio button that is preselected.
     *                        - **showNoValueButton** : If set to true than one radio with no value will be set in front of the other array.
     *                          This could be used if the user should also be able to set no radio to value.
     *                        - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hovers over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addRadioButton($id, $label, array $values, array $options = array())
    {
        ++$this->countElements;
        $attributes = array('class' => '');

        // create array with all options
        $optionsDefault = array('formtype' => $this->type,
            'property'          => self::FIELD_DEFAULT,
            'defaultValue'      => '',
            'showNoValueButton' => false,
            'helpTextIdLabel'   => '',
            'helpTextIdInline'  => '',
            'icon'              => '',
            'class'             => '',
            'id'                => $id,
            'label'             => $label,
            'values'             => $values
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // disable field
        if ($optionsAll['property'] === self::FIELD_DISABLED) {
            $attributes['disabled'] = 'disabled';
        } elseif ($optionsAll['property'] === self::FIELD_REQUIRED) {
            $attributes['required'] = 'required';
            $this->flagRequiredFields = true;
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '') {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        $optionsAll["attributes"] = $attributes;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->addHtml($this->render('form.radio', $optionsAll));
    }

    /**
     * Add a new selectbox with a label to the form. The selectbox
     * could have different values and a default value could be set.
     * @param string $id      ID of the selectbox. This will also be the name of the selectbox.
     * @param string $label   The label of the selectbox.
     * @param array  $values  Array with all entries of the select box;
     *                        Array key will be the internal value of the entry
     *                        Array value will be the visual value of the entry
     *                        If you need an option group within the selectbox than you must add an array as value.
     *                        This array exists of 3 entries: array(0 => id, 1 => value name, 2 => option group name)
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                          + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                        - **defaultValue** : This is the value the selectbox shows when loaded. If **multiselect** is activated than
     *                          an array with all default values could be set.
     *                        - **showContextDependentFirstEntry** : If set to **true** the select box will get an additional first entry.
     *                          If self::FIELD_REQUIRED is set than "Please choose" will be the first entry otherwise
     *                          an empty entry will be added, so you must not select something.
     *                        - **firstEntry** : Here you can define a string that should be shown as firstEntry and will be the
     *                          default value if no other value is set. This entry will only be added if **showContextDependentFirstEntry**
     *                          is set to false!
     *                        - **arrayKeyIsNotValue** : If set to **true** than the entry of the values-array will be used as
     *                          option value and not the key of the array
     *                        - **multiselect** : If set to **true** than the jQuery plugin Select2 will be used to create a selectbox
     *                          where the user could select multiple values from the selectbox. Then an array will be
     *                          created within the $_POST array.
     *                        - **search** : If set to **true** the jQuery plugin Select2 will be used to create a selectbox
     *                          with a search field.
     *                        - **placeholder** : When using the jQuery plugin Select2 you can set a placeholder that will be shown
     *                          if no entry is selected
     *                        - **maximumSelectionNumber** : If **multiselect** is enabled then you can configure the maximum number
     *                          of selections that could be done. If this limit is reached the user can't add another entry to the selectbox.
     *                        - **valueAttributes**: An array which contain the same ids as the value array. The value of this array will be
     *                          onother array with the combination of attributes name and attributes value.
     *                        - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hovers over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addSelectBox($id, $label, array $values, array $options = array())
    {
        global $gL10n;

        ++$this->countElements;
        $attributes = array('class' => 'form-control');
        $name = $id;

        // create array with all options
        $optionsDefault = array('formtype' => $this->type,
            'property'                       => self::FIELD_DEFAULT,
            'defaultValue'                   => '',
            'showContextDependentFirstEntry' => true,
            'firstEntry'                     => '',
            'arrayKeyIsNotValue'             => false,
            'multiselect'                    => false,
            'search'                         => false,
            'placeholder'                    => '',
            'maximumSelectionNumber'         => 0,
            'valueAttributes'                => '',
            'alertWarning'                   => '',
            'helpTextIdLabel'                => '',
            'helpTextIdInline'               => '',
            'icon'                           => '',
            'class'                          => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        // set field properties
        switch ($optionsAll['property']) {
            case self::FIELD_DISABLED:
                $attributes['disabled'] = 'disabled';
                break;

            case self::FIELD_READONLY:
                $attributes['readonly'] = 'readonly';
                break;

            case self::FIELD_REQUIRED:
                $attributes['required'] = 'required';
                $this->flagRequiredFields = true;
                break;

            case self::FIELD_HIDDEN:
                $attributes['hidden'] = 'hidden';
                $attributes['class'] .= ' invisible';
                break;
        }

        if ($optionsAll['multiselect']) {
            $attributes['multiple'] = 'multiple';
            $name = $id . '[]';

            if ($optionsAll['defaultValue'] !== '' && !is_array($optionsAll['defaultValue'])) {
                $optionsAll['defaultValue'] = array($optionsAll['defaultValue']);
            }

            if ($optionsAll['showContextDependentFirstEntry'] && $optionsAll['property'] === self::FIELD_REQUIRED) {
                if ($optionsAll['placeholder'] === '') {
                    $optionsAll['placeholder'] = $gL10n->get('SYS_SELECT_FROM_LIST');
                }

                // reset the preferences so the logic for not multiselect will not be performed
                $optionsAll['showContextDependentFirstEntry'] = false;
            }
        }

        // set specific css class for this field
        if ($optionsAll['class'] !== '') {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        // now create html for the field
        $this->openControlStructure($id, $label, $optionsAll['property'], $optionsAll['helpTextIdLabel'], $optionsAll['icon']);

        $this->addSelect($name, $id, $attributes);

        // add an additional first entry to the select box and set this as preselected if necessary
        $defaultEntry = false;
        if ($optionsAll['firstEntry'] !== '' || $optionsAll['showContextDependentFirstEntry']) {
            if ($optionsAll['defaultValue'] === '') {
                $defaultEntry = true;
            }
        }

        if ($optionsAll['firstEntry'] !== '') {
            if (is_array($optionsAll['firstEntry'])) {
                $this->addOption($optionsAll['firstEntry'][0], $optionsAll['firstEntry'][1], null, $defaultEntry);
            } else {
                $this->addOption('', '- ' . $optionsAll['firstEntry'] . ' -', null, $defaultEntry);
            }
        } elseif ($optionsAll['showContextDependentFirstEntry']) {
            if ($optionsAll['property'] === self::FIELD_REQUIRED) {
                $this->addOption('', '- ' . $gL10n->get('SYS_PLEASE_CHOOSE') . ' -', null, $defaultEntry);
            } else {
                $this->addOption('', ' ', null, $defaultEntry);
            }
        } elseif (count($values) === 0) {
            $this->addOption('', '');
        }

        $optionGroup = null;

        foreach ($values as $key => $value) {
            // create entry in html
            $defaultEntry = false;

            // if each array element is an array then create option groups
            if (is_array($value)) {
                // add optiongroup if necessary
                if ($optionGroup !== $value[2]) {
                    if ($optionGroup !== null) {
                        $this->closeOptionGroup();
                    }

                    $this->addOptionGroup(Language::translateIfTranslationStrId($value[2]));
                    $optionGroup = $value[2];
                }

                // if value is a translation string we must translate it
                $value[1] = Language::translateIfTranslationStrId($value[1]);

                // add option
                if (!$optionsAll['multiselect'] && $optionsAll['defaultValue'] == $value[0]) {
                    $defaultEntry = true;
                }

                if (is_array($optionsAll['valueAttributes'])) {
                    $this->addOption((string) $value[0], $value[1], null, $defaultEntry, false, $optionsAll['valueAttributes'][$value[0]]);
                } else {
                    $this->addOption((string) $value[0], $value[1], null, $defaultEntry);
                }
            } else {
                // if value is a translation string we must translate it
                $value = Language::translateIfTranslationStrId($value);

                // set the value attribute of the option tag
                $optionValue = $key;

                if ($optionsAll['arrayKeyIsNotValue']) {
                    $optionValue = $value;
                }

                // array has only key and value then create a normal selectbox without optiongroups
                if (!$optionsAll['multiselect'] && $optionsAll['defaultValue'] == $optionValue) {
                    $defaultEntry = true;
                }

                if (is_array($optionsAll['valueAttributes'])) {
                    $this->addOption((string) $optionValue, $value, null, $defaultEntry, false, $optionsAll['valueAttributes'][$key]);
                } else {
                    $this->addOption((string) $optionValue, $value, null, $defaultEntry);
                }
            }
        }

        if ($optionGroup !== null) {
            $this->closeOptionGroup();
        }

        if ($optionsAll['multiselect'] || $optionsAll['search']) {
            $maximumSelectionNumber = '';
            $allowClear = 'false';

            if ($optionsAll['maximumSelectionNumber'] > 0) {
                $maximumSelectionNumber = ' maximumSelectionLength: ' . $optionsAll['maximumSelectionNumber'] . ', ';
                $allowClear = 'true';
            }

            $javascriptCode = '
                $("#' . $id . '").select2({
                    theme: "bootstrap4",
                    allowClear: ' . $allowClear . ',
                    ' . $maximumSelectionNumber . '
                    placeholder: "' . $optionsAll['placeholder'] . '",
                    language: "' . $gL10n->getLanguageLibs() . '"
                });';

            if (is_array($optionsAll['defaultValue']) && count($optionsAll['defaultValue']) > 0) {
                // add default values to multi select
                $htmlDefaultValues = '"' . implode('", "', $optionsAll['defaultValue']) . '"';

                $javascriptCode .= ' $("#' . $id . '").val([' . $htmlDefaultValues . ']).trigger("change.select2");';
            } elseif (count($values) === 1 && $optionsAll['property'] === self::FIELD_REQUIRED) {
                // if there is only one entry and a required field than select this entry
                $javascriptCode .= ' $("#' . $id . '").val("'.$values[0][0].'").trigger("change.select2");';
            }

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if ($this->htmlPage instanceof HtmlPage) {
                $this->htmlPage->addCssFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/select2/css/select2.css');
                $this->htmlPage->addCssFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/select2-bootstrap-theme/select2-bootstrap4.css');
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/select2/js/select2.js');
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS_CLIENT . '/select2/js/i18n/' . $gL10n->getLanguageLibs() . '.js');
            }
            $this->addJavascriptCode($javascriptCode, true);
        }

        $this->closeSelect();

        $this->closeControlStructure($optionsAll);
    }

    /**
     * Add a new selectbox with a label to the form. The selectbox get their data from a sql statement.
     * You can create any sql statement and this method should create a selectbox with the found data.
     * The sql must contain at least two columns. The first column represents the value and the second
     * column represents the label of each option of the selectbox. Optional you can add a third column
     * to the sql statement. This column will be used as label for an optiongroup. Each time the value
     * of the third column changed a new optiongroup will be created.
     * @param string       $id       ID of the selectbox. This will also be the name of the selectbox.
     * @param string       $label    The label of the selectbox.
     * @param Database     $database Object of the class Database. This should be the default global object **$gDb**.
     * @param array|string $sql      Any SQL statement that return 2 columns. The first column will be the internal value of the
     *                               selectbox item and will be submitted with the form. The second column represents the
     *                               displayed value of the item. Each row of the result will be a new selectbox entry.
     * @param array        $options  (optional) An array with the following possible entries:
     *                               - **property** : With this param you can set the following properties:
     *                                 + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                                 + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                                 + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                               - **defaultValue** : This is the value the selectbox shows when loaded. If **multiselect** is activated than
     *                                 an array with all default values could be set.
     *                               - **arrayKeyIsNotValue** : If set to **true** than the entry of the values-array will be used as
     *                                 option value and not the key of the array
     *                               - **showContextDependentFirstEntry** : If set to **true** the select box will get an additional first entry.
     *                                 If self::FIELD_REQUIRED is set than "Please choose" will be the first entry otherwise
     *                                 an empty entry will be added, so you must not select something.
     *                               - **firstEntry** : Here you can define a string that should be shown as firstEntry and will be the
     *                                 default value if no other value is set. This entry will only be added if **showContextDependentFirstEntry**
     *                                 is set to false!
     *                               - **multiselect** : If set to **true** than the jQuery plugin Select2 will be used to create a selectbox
     *                                 where the user could select multiple values from the selectbox. Then an array will be
     *                                 created within the $_POST array.
     *                               - **maximumSelectionNumber** : If **multiselect** is enabled then you can configure the maximum number
     *                                 of selections that could be done. If this limit is reached the user can't add another entry to the selectbox.
     *                               - **valueAttributes**: An array which contain the same ids as the value array. The value of this array will be
     *                                 onother array with the combination of attributes name and attributes value.
     *                               - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                                 e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                                 the user can see the text if he hovers over the icon. If you need an additional parameter
     *                                 for the text you can add an array. The first entry must be the unique text id and the second
     *                                 entry will be a parameter of the text id.
     *                               - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                                 e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                                 If you need an additional parameter for the text you can add an array. The first entry must
     *                                 be the unique text id and the second entry will be a parameter of the text id.
     *                               - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                                 will be the text of the alertbox
     *                               - **icon** : An icon can be set. This will be placed in front of the label.
     *                               - **class** : An additional css classname. The class **admSelectbox**
     *                                 is set as default and need not set with this parameter.
     * **Code examples**
     * ```
     * // create a selectbox with all profile fields of a specific category
     * $sql = 'SELECT usf_id, usf_name FROM '.TBL_USER_FIELDS.' WHERE usf_cat_id = 4711'
     * $form = new HtmlForm('simple-form', 'next_page.php');
     * $form->addSelectBoxFromSql('admProfileFieldsBox', $gL10n->get('SYS_FIELDS'), $gDb, $sql, array('defaultValue' => $gL10n->get('SYS_SURNAME'), 'showContextDependentFirstEntry' => true));
     * $form->show();
     * ```
     */
    public function addSelectBoxFromSql($id, $label, Database $database, $sql, array $options = array())
    {
        $selectBoxEntries = array();

        // execute the sql statement
        if (is_array($sql)) {
            $pdoStatement = $database->queryPrepared($sql['query'], $sql['params']);
        } else {
            // TODO deprecated: remove in Admidio 4.0
            $pdoStatement = $database->query($sql);
        }

        // create array from sql result
        while ($row = $pdoStatement->fetch(PDO::FETCH_NUM)) {
            // if result has 3 columns then create an array in array
            if (array_key_exists(2, $row)) {
                // translate category name
                $row[2] = Language::translateIfTranslationStrId($row[2]);

                $selectBoxEntries[] = array($row[0], $row[1], $row[2]);
            } else {
                $selectBoxEntries[$row[0]] = $row[1];
            }
        }

        // now call default method to create a selectbox
        $this->addSelectBox($id, $label, $selectBoxEntries, $options);
    }

    /**
     * Add a new selectbox with a label to the form. The selectbox could have
     * different values and a default value could be set.
     * @param string $id          ID of the selectbox. This will also be the name of the selectbox.
     * @param string $label       The label of the selectbox.
     * @param string $xmlFile     Serverpath to the xml file
     * @param string $xmlValueTag Name of the xml tag that should contain the internal value of a selectbox entry
     * @param string $xmlViewTag  Name of the xml tag that should contain the visual value of a selectbox entry
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                          + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                        - **defaultValue** : This is the value the selectbox shows when loaded. If **multiselect** is activated than
     *                          an array with all default values could be set.
     *                        - **arrayKeyIsNotValue** : If set to **true** than the entry of the values-array will be used as
     *                          option value and not the key of the array
     *                        - **showContextDependentFirstEntry** : If set to **true** the select box will get an additional first entry.
     *                          If self::FIELD_REQUIRED is set than "Please choose" will be the first entry otherwise
     *                          an empty entry will be added, so you must not select something.
     *                        - **firstEntry** : Here you can define a string that should be shown as firstEntry and will be the
     *                          default value if no other value is set. This entry will only be added if **showContextDependentFirstEntry**
     *                          is set to false!
     *                        - **multiselect** : If set to **true** than the jQuery plugin Select2 will be used to create a selectbox
     *                          where the user could select multiple values from the selectbox. Then an array will be
     *                          created within the $_POST array.
     *                        - **maximumSelectionNumber** : If **multiselect** is enabled then you can configure the maximum number
     *                          of selections that could be done. If this limit is reached the user can't add another entry to the selectbox.
     *                        - **valueAttributes**: An array which contain the same ids as the value array. The value of this array will be
     *                          onother array with the combination of attributes name and attributes value.
     *                        - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hovers over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addSelectBoxFromXml($id, $label, $xmlFile, $xmlValueTag, $xmlViewTag, array $options = array())
    {
        $selectBoxEntries = array();

        $xmlRootNode = new SimpleXMLElement($xmlFile, 0, true);

        /**
         * @var SimpleXMLElement $xmlChildNode
         */
        foreach ($xmlRootNode->children() as $xmlChildNode) {
            $key   = '';
            $value = '';

            /**
             * @var SimpleXMLElement $xmlChildChildNode
             */
            foreach ($xmlChildNode->children() as $xmlChildChildNode) {
                if ($xmlChildChildNode->getName() === $xmlValueTag) {
                    $key = (string) $xmlChildChildNode;
                }
                if ($xmlChildChildNode->getName() === $xmlViewTag) {
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
     * @param string   $id             ID of the selectbox. This will also be the name of the selectbox.
     * @param string   $label          The label of the selectbox.
     * @param Database $database       A Admidio database object that contains a valid connection to a database
     * @param string   $categoryType   Type of category ('DAT', 'LNK', 'ROL', 'USF') that should be shown.
     *                                 The type 'ROL' will ot list event role categories. Therefore, you need to set
     *                                 the type 'ROL_EVENT'. It's not possible to show role categories together with
     *                                 event categories.
     * @param string   $selectBoxModus The selectbox could be shown in 2 different modus.
     *                                 - **EDIT_CATEGORIES** : First entry will be "Please choose" and default category will be preselected.
     *                                 - **FILTER_CATEGORIES** : First entry will be "All" and only categories with childs will be shown.
     * @param array    $options        (optional) An array with the following possible entries:
     *                                 - **property** : With this param you can set the following properties:
     *                                   + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                                   + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                                   + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                                 - **defaultValue** : ID of category that should be selected per default.
     *.                                - **arrayKeyIsNotValue** : If set to **true** than the entry of the values-array will be used as
     *                                   option value and not the key of the array
     *                                 - **showSystemCategory** : Show user defined and system categories
     *                                 - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                                   e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                                   the user can see the text if he hovers over the icon. If you need an additional parameter
     *                                   for the text you can add an array. The first entry must be the unique text id and the second
     *                                   entry will be a parameter of the text id.
     *                                 - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                                   e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                                   If you need an additional parameter for the text you can add an array. The first entry must
     *                                   be the unique text id and the second entry will be a parameter of the text id.
     *                                 - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                                   will be the text of the alertbox
     *                                 - **icon** : An icon can be set. This will be placed in front of the label.
     *                                 - **class** : An additional css classname. The class **admSelectbox**
     *                                   is set as default and need not set with this parameter.
     */
    public function addSelectBoxForCategories($id, $label, Database $database, $categoryType, $selectBoxModus, array $options = array())
    {
        global $gCurrentOrganization, $gCurrentUser, $gL10n;

        // create array with all options
        $optionsDefault = array('formtype' => $this->type,
            'property'                       => self::FIELD_DEFAULT,
            'defaultValue'                   => '',
            'arrayKeyIsNotValue'             => false,
            'showContextDependentFirstEntry' => true,
            'multiselect'                    => false,
            'showSystemCategory'             => true,
            'alertWarning'                   => '',
            'helpTextIdLabel'                => '',
            'helpTextIdInline'               => '',
            'icon'                           => '',
            'class'                          => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        if ($selectBoxModus === self::SELECT_BOX_MODUS_EDIT && $gCurrentOrganization->countAllRecords() > 1) {
            $optionsAll['alertWarning'] = $gL10n->get('SYS_ALL_ORGANIZATIONS_DESC', array(implode(', ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true))));

            $this->addJavascriptCode(
                '
                $("#'.$id.'").change(function() {
                    if($("option:selected", this).attr("data-global") == 1) {
                        $("#'.$id.'_alert").show("slow");
                    } else {
                        $("#'.$id.'_alert").hide();
                    }
                });
                $("#'.$id.'").trigger("change");',
                true
            );
        }

        $sqlTables     = '';
        $sqlConditions = '';

        // create sql conditions if category must have child elements
        if ($selectBoxModus === self::SELECT_BOX_MODUS_FILTER) {
            $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories($categoryType));
            $optionsAll['showContextDependentFirstEntry'] = false;

            switch ($categoryType) {
                case 'DAT':
                    $sqlTables = ' INNER JOIN ' . TBL_DATES . ' ON cat_id = dat_cat_id ';
                    break;
                case 'LNK':
                    $sqlTables = ' INNER JOIN ' . TBL_LINKS . ' ON cat_id = lnk_cat_id ';
                    break;
                case 'ROL':
                case 'ROL_EVENT':
                    $sqlTables = ' INNER JOIN ' . TBL_ROLES . ' ON cat_id = rol_cat_id';
                    break;
            }
        } else {
            $catIdParams = array_merge(array(0), $gCurrentUser->getAllEditableCategories(($categoryType === 'ROL_EVENT' ? 'ROL' : $categoryType)));
        }

        switch ($categoryType) {
            case 'ROL':
                // don't show event categories
                $sqlConditions .= ' AND cat_name_intern <> \'EVENTS\' ';
                break;
            case 'ROL_EVENT':
                // only show event categories
                $sqlConditions .= ' AND cat_name_intern = \'EVENTS\' ';
                break;
        }

        if (!$optionsAll['showSystemCategory']) {
            $sqlConditions .= ' AND cat_system = false ';
        }

        // within edit dialogs child organizations are not allowed to assign categories of all organizations
        if ($selectBoxModus === self::SELECT_BOX_MODUS_EDIT && $gCurrentOrganization->isChildOrganization()) {
            $sqlConditions .= ' AND cat_org_id = ? -- $gCurrentOrgId ';
        } else {
            $sqlConditions .= ' AND (  cat_org_id = ? -- $gCurrentOrgId
                                    OR cat_org_id IS NULL ) ';
        }

        // the sql statement which returns all found categories
        $sql = 'SELECT DISTINCT cat_id, cat_org_id, cat_uuid, cat_name, cat_default, cat_sequence
                  FROM ' . TBL_CATEGORIES . '
                       ' . $sqlTables . '
                 WHERE cat_id IN (' . Database::getQmForValues($catIdParams) . ')
                   AND cat_type = ? -- $categoryType
                       ' . $sqlConditions . '
              ORDER BY cat_sequence ASC';
        $queryParams = array_merge(
            $catIdParams,
            array(
                ($categoryType === 'ROL_EVENT' ? 'ROL' : $categoryType),
                $GLOBALS['gCurrentOrgId']
            )
        );
        $pdoStatement = $database->queryPrepared($sql, $queryParams);
        $countCategories = $pdoStatement->rowCount();

        // if no or only one category exist and in filter modus, than don't show category
        if ($selectBoxModus === self::SELECT_BOX_MODUS_FILTER && ($countCategories === 0 || $countCategories === 1)) {
            return;
        }

        $categoriesArray = array();
        $optionsAll['valueAttributes'] = array();

        if ($selectBoxModus === self::SELECT_BOX_MODUS_FILTER && $countCategories > 1) {
            $categoriesArray[0] = $gL10n->get('SYS_ALL');
            $optionsAll['valueAttributes'][0] = array('data-global' => 0);
        }

        while ($row = $pdoStatement->fetch()) {
            // if several categories exist than select default category
            if ($selectBoxModus === self::SELECT_BOX_MODUS_EDIT && $optionsAll['defaultValue'] === ''
            && ($countCategories === 1 || $row['cat_default'] === 1)) {
                $optionsAll['defaultValue'] = $row['cat_uuid'];
            }

            // if text is a translation-id then translate it
            $categoriesArray[$row['cat_uuid']] = Language::translateIfTranslationStrId($row['cat_name']);

            // add label that this category is visible to all organizations
            if ($row['cat_org_id'] === null) {
                if ($categoriesArray[$row['cat_uuid']] !== $gL10n->get('SYS_ALL_ORGANIZATIONS')) {
                    $categoriesArray[$row['cat_uuid']] = $categoriesArray[$row['cat_uuid']] . ' (' . $gL10n->get('SYS_ALL_ORGANIZATIONS') . ')';
                }
                $optionsAll['valueAttributes'][$row['cat_uuid']] = array('data-global' => 1);
            } else {
                $optionsAll['valueAttributes'][$row['cat_uuid']] = array('data-global' => 0);
            }
        }

        // now call method to create select box from array
        $this->addSelectBox($id, $label, $categoriesArray, $optionsAll);
    }

    /**
     * Add a new static control to the form. A static control is only a simple text instead of an input field.
     * This could be used if the value should not be changed by the user.
     * @param string $id      ID of the static control. This will also be the name of the static control.
     * @param string $label   The label of the static control.
     * @param string $value   A value of the static control. The control will be created with this value.
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **helpTextIdLabel** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set a help icon will be shown after the control label where
     *                          the user can see the text if he hovers over the icon. If you need an additional parameter
     *                          for the text you can add an array. The first entry must be the unique text id and the second
     *                          entry will be a parameter of the text id.
     *                        - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addStaticControl($id, $label, $value, array $options = array())
    {
        $attributes = array('class' => 'form-control-static');
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array('formtype' => $this->type,'property' => '', 'helpTextIdLabel' => '', 'helpTextIdInline' => '', 'icon' => '', 'class' => '');
        $optionsAll     = array_replace($optionsDefault, $options);

        // set specific css class for this field
        if ($optionsAll['class'] !== '') {
            $attributes['class'] .= ' ' . $optionsAll['class'];
        }

        // now create html for the field
        $this->openControlStructure('', $label, self::FIELD_DEFAULT, $optionsAll['helpTextIdLabel'], $optionsAll['icon']);
        $this->addHtml('<p class="' . $attributes['class'] . '">' . $value . '</p>');
        $this->closeControlStructure($optionsAll);
    }

    /**
     * Add a new button with a custom text to the form. This button could have
     * an icon in front of the text. Different to addButton this method adds an
     * additional **div** around the button and the type of the button is **submit**.
     * @param string $id      ID of the button. This will also be the name of the button.
     * @param string $text    Text of the button
     * @param array  $options (optional) An array with the following possible entries:
     *                        - **icon** : Optional parameter. Path and filename of an icon.
     *                          If set a icon will be shown in front of the text.
     *                        - **link** : If set a javascript click event with a page load to this link
     *                          will be attached to the button.
     *                        - **class** : Optional an additional css classname. The class **admButton**
     *                          is set as default and need not set with this parameter.
     *                        - **type** : If set to true this button get the type **submit**. This will
     *                          be the default.
     */
    public function addSubmitButton($id, $text, array $options = array())
    {
        // create array with all options
        $optionsDefault = array('formtype' => $this->type,'icon' => '', 'link' => '', 'class' => '', 'type' => 'submit');
        $optionsAll     = array_replace($optionsDefault, $options);

        // add default css classes
        $optionsAll['class'] .= ' btn btn-primary';
        if ($this->type !== 'navbar') {
            $optionsAll['class'] .= '  admidio-margin-bottom';
        }

        // now add button to form
        $this->addButton($id, $text, $optionsAll);

        if (!$this->buttonGroupOpen) {
            $this->addHtml('<div class="form-alert" style="display: none;">&nbsp;</div>');
        }
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
     * @param array $options (optional) An array with the following possible entries:
     *                        - **helpTextIdInline** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. If set the complete text will be shown after the form element.
     *                          If you need an additional parameter for the text you can add an array. The first entry must
     *                          be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap warning alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     */

    protected function closeControlStructure(array $options = array())
    {
        if ($options['property'] !== self::FIELD_HIDDEN) {
            $parameters = array();
            $helpTextId = $options['helpTextIdInline'];

            if (is_array($options['helpTextIdInline'])) {
                $parameters = $options['helpTextIdInline'][1];
                $helpTextId = $options['helpTextIdInline'][0];
            }

            if ($helpTextId !== '') {
                // if text is a translation-id then translate it
                if (Language::isTranslationStringId($helpTextId)) {
                    foreach ($parameters as &$parameter) {
                        // parameters should be strings
                        $parameter = (string)$parameter;

                        // if parameter is a translation-id then translate it
                        $parameter = Language::translateIfTranslationStrId($parameter);
                    }
                    unset($parameter);

                    $helpText = $GLOBALS['gL10n']->get($helpTextId, $parameters);
                } else {
                    $helpText = $helpTextId;
                }

                $this->addHtml('<div class="help-block">' . $helpText . '</div>');
            }

            // add block with warning alert
            if (isset($options['alertWarning']) && $options['alertWarning'] !== '') {
                $this->addHtml('<div class="alert alert-warning mt-3" role="alert">
                <i class="fas fa-exclamation-triangle"></i>' . $options['alertWarning'] . '
            </div>');
            }

            if ($this->type === 'vertical' || $this->type === 'navbar') {
                $this->addHtml('</div>');
            } else {
                $this->addHtml('</div></div>');
            }
        }
    }

    /**
     * Close all html elements of a groupbox that was created before.
     */

    public function closeGroupBox()
    {
        $this->addHtml('</div></div>');
    }

    /**
     * Add a small help icon to the form at the current element which shows the translated text of the
     * text-id or an individual text on mouseover. The title will be note if it's a text-id or
     * description if it's an individual text.
     * @param string $string    A text that should be shown or a unique text id from the translation xml files
     *                          that should be shown e.g. SYS_DATA_CATEGORY_GLOBAL.
     * @param string $title     A text-id that represents the title of the help text. Default will be SYS_NOTE.
     * @param array $parameter If you need an additional parameters for the text you can set this parameter values within an array.
     * @return string Return a html snippet that contains a help icon with a link to a popup box that shows the message.
     */
    public static function getHelpTextIcon(string $string, string $title = 'SYS_NOTE', array $parameter = array())
    {
        global $gL10n;

        $html = '';

        if(strlen($string) > 0) {
            if (Language::isTranslationStringId($string)) {
                $text  = $gL10n->get($string, $parameter);
            } else {
                $text  = $string;
            }

            $html = '<i class="fas fa-info-circle admidio-info-icon" data-toggle="popover"
            data-html="true" data-trigger="hover click" data-placement="auto"
            title="' . $gL10n->get($title) . '" data-content="' . SecurityUtils::encodeHTML($text) . '"></i>';
        }
        return $html;
    }

    public static function getHelpText($text)
    {
        $parameters = array();

        if (is_array($text)) {
            $parameters = $text[1];
            $text = $text[0];
        }

        if ($text !== '') {
            // if text is a translation-id then translate it
            if (Language::isTranslationStringId($text)) {
                foreach ($parameters as &$parameter) {
                    // parameters should be strings
                    $parameter = (string)$parameter;

                    // if parameter is a translation-id then translate it
                    $parameter = Language::translateIfTranslationStrId($parameter);
                }
                unset($parameter);

                $text = $GLOBALS['gL10n']->get($text, $parameters);
            }
        }
        return $text;
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
     * Creates a html structure for a form field. This structure contains the label and the div for the form element.
     * After the form element is added the method closeControlStructure must be called.
     * @param string $id         The id of this field structure.
     * @param string $label      The label of the field. This string should already be translated.
     * @param int    $property   (optional) With this param you can set the following properties:
     *                           - **self::FIELD_DEFAULT**  : The field can accept an input.
     *                           - **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                           - **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     * @param string $helpTextId (optional) A unique text id from the translation xml files that should be shown e.g. SYS_DATA_CATEGORY_GLOBAL.
     *                           If set a help icon will be shown where the user can see the text if he hovers over the icon.
     *                           If you need an additional parameter for the text you can add an array. The first entry
     *                           must be the unique text id and the second entry will be a parameter of the text id.
     * @param string $icon       (optional) An icon can be set. This will be placed in front of the label.
     * @param string $class      (optional) An additional css classname for the row. The class **admFieldRow**
     *                           is set as default and need not set with this parameter.
     */

    protected function openControlStructure($id, $label, $property = self::FIELD_DEFAULT, $helpTextId = '', $icon = '', $class = '')
    {
        if ($property !== self::FIELD_HIDDEN) {
            $cssClassRow = 'form-group';
            $htmlIcon = '';
            $htmlHelpIcon = '';
            $htmlIdFor = '';

            // set specific css class for this row
            if ($class !== '') {
                $cssClassRow .= ' ' . $class;
            }

            if ($this->type === 'default') {
                $cssClassRow .= ' row';
            }

            // if necessary set css class for a mandatory element
            if ($property === self::FIELD_REQUIRED && $this->showRequiredFields) {
                $cssClassRow .= ' admidio-form-group-required';
            }

            if ($id !== '') {
                $htmlIdFor = ' for="' . $id . '"';
                $this->addHtml('<div id="' . $id . '_group" class="' . $cssClassRow . '">');
            } else {
                $this->addHtml('<div class="' . $cssClassRow . '">');
            }

            if ($icon !== '') {
                // create html for icon
                $htmlIcon = Image::getIconHtml($icon, $label);
            }

            if ($helpTextId !== '') {
                $htmlHelpIcon = self::getHelpTextIcon($helpTextId);
            }

            // add label element
            if ($this->type === 'vertical' || $this->type === 'navbar') {
                if ($label !== '') {
                    $this->addHtml('<label' . $htmlIdFor . '>' . $htmlIcon . $label . $htmlHelpIcon . '</label>');
                }
            } else {
                if ($label !== '') {
                    $this->addHtml(
                        '<label' . $htmlIdFor . ' class="col-sm-3 control-label">' . $htmlIcon . $label . $htmlHelpIcon . '</label>
                    <div class="col-sm-9">'
                    );
                } else {
                    $this->addHtml('<div class="offset-sm-3 col-sm-9">');
                }
            }
        }
    }

    /**
     * Add a new groupbox to the form. This could be used to group some elements
     * together. There is also the option to set a headline to this group box.
     * @param string $id       Id the the groupbox.
     * @param string $headline (optional) A headline that will be shown to the user.
     * @param string $class    (optional) An additional css classname for the row. The class **admFieldRow**
     *                         is set as default and need not set with this parameter.
     */

    public function openGroupBox($id, $headline = null, $class = '')
    {
        $this->addHtml('<div id="' . $id . '" class="card admidio-field-group ' . $class . '">');
        // add headline to groupbox
        if ($headline !== null) {
            $this->addHtml('<div class="card-header">' . $headline . '</div>');
        }
        $this->addHtml('<div class="card-body">');
    }


    /**
     * This method create the whole html code of the form. Call this method
     * if you have finished your form layout. If mandatory fields were set than a notice
     * which marker represents the mandatory will be shown before the form.
     * @return string Return the html code of the form.
     */
    public function show()
    {
        global $gL10n;

        // if there are no elements in the form then return nothing
        if ($this->countElements === 0) {
            return '';
        }

        $html = '';

        // If required fields were set than a notice which marker represents the required fields will be shown.
        if ($this->flagRequiredFields && $this->showRequiredFields) {
            $html .= '<div class="admidio-form-required-notice"><span>' . $gL10n->get('SYS_REQUIRED_INPUT') . '</span></div>';
        }

        // now get whole form html code
        $html .= $this->getHtmlForm();

        return $html;
    }
}
