<?php

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;

/**
 * @brief Creates an Admidio specific form with special elements
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
 * $form = new HtmlForm('simple-form', 'adm_next_page.php');
 * $form->openGroupBox('gbSimpleForm', $gL10n->get('SYS_SIMPLE_FORM'));
 * $form->addInput('name', $gL10n->get('SYS_NAME'), $formName);
 * $form->addSelectBox('type', $gL10n->get('SYS_TYPE'), array('simple' => 'SYS_SIMPLE', 'very-simple' => 'SYS_VERY_SIMPLE'),
 *                     array('defaultValue' => 'simple', 'showContextDependentFirstEntry' => true));
 * $form->closeGroupBox();
 * $form->addSubmitButton('next-page', $gL10n->get('SYS_NEXT'), array('icon' => 'bi-arrow-right-circle-fill'));
 * $form->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * @deprecated 5.0.0:5.1.0 Class "HtmlForm" is deprecated, use class "Form" instead.
 */
class HtmlForm
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
    protected bool $flagRequiredFields = false;
    /**
     * @var bool Flag if required fields should get a special css class to make them more visible to the user.
     */
    protected bool $showRequiredFields;
    /**
     * @var HtmlPage A HtmlPage object that will be used to add javascript code or files to the html output page.
     */
    protected $htmlPage;
    /**
     * @var string String with prepared html
     */
    protected string $htmlString = '';
    /**
     * @var int Number of visible elements in this form. Hidden elements are not count because no interaction is possible.
     */
    protected int $countElements = 0;
    /**
     * @var string Form type. Possible values are **default**, **vertical** or **navbar**.
     */
    protected $type;
    /**
     * @var string ID of the form
     */
    protected string $id;
    /**
     * @var string Content of the action attribute ot the form
     */
    protected string $action;
    /**
     * @var string CSS classes of the form
     */
    protected array $attributes = array();
    /**
     * @var bool Flag that indicates if a bootstrap button-group is open and should be closed later
     */
    protected bool $buttonGroupOpen = false;

    /**
     * Constructor creates the form element
     * @param string $id ID of the form
     * @param string|null $action Action attribute of the form
     * @param HtmlPage|null $htmlPage (optional) A HtmlPage object that will be used to add javascript code or files to the html output page.
     * @param array $options (optional) An array with the following possible entries:
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
     * @throws \Smarty\Exception
     * @deprecated 5.0.0:5.1.0 Class "HtmlForm" is deprecated, use class "Form" instead.
     */
    public function __construct(string $id, string $action = '', HtmlPage $htmlPage = null, array $options = array())
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

        // navbar form should send the data as GET if it's not explicit set
        if (isset($options['type']) && $options['type'] === 'navbar' && !isset($options['method'])) {
            $options['method'] = 'get';
        }

        $optionsAll = array_replace($optionsDefault, $options);
        $this->showRequiredFields = $optionsAll['showRequiredFields'];
        $this->type   = $optionsAll['type'];
        $this->id     = $id;
        $this->action = $action;

        // set specific Admidio css form class
        $this->attributes['role'] = 'form';
        $this->attributes['method'] = $optionsAll['method'];

        if ($this->type === 'default') {
            $optionsAll['class'] .= ' form-horizontal form-dialog';
        } elseif ($this->type === 'vertical') {
            $optionsAll['class'] .= ' admidio-form-vertical form-dialog';
        } elseif ($this->type === 'navbar') {
            $optionsAll['class'] .= ' d-lg-flex ';
        }

        if ($optionsAll['class'] !== '') {
            $this->attributes['class'] = $optionsAll['class'];
        }

        // Set specific parameters that are necessary for file upload with a form
        if ($optionsAll['enableFileUpload']) {
            $this->attributes['enctype'] = 'multipart/form-data';
        }

        if ($optionsAll['method'] === 'post' && isset($GLOBALS['gCurrentSession'])) {
            // add a hidden field with the csrf token to each form
            $this->addInput(
                'adm_csrf_token',
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
     * @param string $id ID of the button. This will also be the name of the button.
     * @param string $text Text of the button
     * @param array $options (optional) An array with the following possible entries:
     *                        - **icon** : Optional parameter. Path and filename of an icon.
     *                          If set an icon will be shown in front of the text.
     *                        - **link** : If set a javascript click event with a page load to this link
     *                          will be attached to the button.
     *                        - **class** : Optional an additional css classname. The class **admButton**
     *                          is set as default and need not set with this parameter.
     *                        - **type** : Optional a button type could be set. The default is **button**.
     * @throws \Smarty\Exception|Exception
     */
    public function addButton(string $id, string $text, array $options = array())
    {
        ++$this->countElements;
        // create array with all options
        $optionsDefault = array(
            'property'     => self::FIELD_DEFAULT,
            'icon'         => '',
            'link'         => '',
            'class'        => '',
            'type'         => 'button',
            'data-admidio' => '',
            'id'           => $id,
            'value'        => $text,
        );
        $optionsAll = array_replace($optionsDefault, $options);
        $attributes = array();
        $attributes['type'] = $optionsAll['type'];
        $attributes['data-admidio'] = $optionsAll['data-admidio'];

        // disable field
        if ($optionsAll['property'] === self::FIELD_DISABLED) {
            $attributes['disabled'] = 'disabled';
        }

        if(strstr($optionsAll['class'], 'btn-') === false) {
            $optionsAll['class'] .= " btn-primary";

            if ($this->type !== 'navbar') {
                $optionsAll['class'] .= '  admidio-margin-bottom';
            }
        }

        $optionsAll['attributes'] = $attributes;
        $this->render('form.button', $optionsAll);
    }

    /**
     * Add a captcha with an input field to the form. The captcha could be a picture with a character code
     * or a simple mathematical calculation that must be solved.
     * @param string $id ID of the captcha field. This will also be the name of the captcha field.
     * @param string $class (optional) An additional css classname. The class **admTextInput**
     *                      is set as default and need not set with this parameter.
     * @throws \Smarty\Exception
     * @throws Exception
     */
    public function addCaptcha(string $id, string $class = '')
    {
        global $gL10n;

        ++$this->countElements;

        $this->addJavascriptCode('
            $("#' . $id . '_refresh").click(function() {
                document.getElementById("captcha").src="' . ADMIDIO_URL . FOLDER_LIBS . '/securimage/securimage_show.php?" + Math.random();
            });', true);
        $this->render('form.captcha', ['class' => $class, 'id' => $id]);
        // now add a row with a text field where the user can write the solution for the puzzle
        $this->addInput(
            $id,
            $gL10n->get('SYS_CAPTCHA_CONFIRMATION_CODE'),
            '',
            array(
                'property' => self::FIELD_REQUIRED,
                'helpTextId' => 'SYS_CAPTCHA_DESCRIPTION',
                'class' => 'form-control-small'
            )
        );

    }

    /**
     * Add a new checkbox with a label to the form.
     * @param string $id ID of the checkbox. This will also be the name of the checkbox.
     * @param string $label The label of the checkbox.
     * @param bool $checked A value for the checkbox. The value could only be **0** or **1**. If the value is **1** then
     *                        the checkbox will be checked when displayed.
     * @param array $options (optional) An array with the following possible entries:
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                          + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     * @throws \Smarty\Exception|Exception
     */
    public function addCheckbox(string $id, string $label, bool $checked = false, array $options = array())
    {
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'property'         => self::FIELD_DEFAULT,
            'helpTextId'       => '',
            'icon'             => '',
            'class'            => '',
            'alertWarning'     => '',
            'id'               => $id,
            'label'            => $label
        );
        $optionsAll = array_replace($optionsDefault, $options);
        $attributes = array();

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

        $optionsAll["attributes"] = $attributes;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->render('form.checkbox', $optionsAll);
    }

    /**
     * Add custom html content to the form within the default field structure.
     * The Label will be set but instead of a form control you can define any html.
     * If you don't need the field structure and want to add html then use the method addHtml()
     * @param string $label The label of the custom content.
     * @param string $content A simple Text or html that would be placed instead of a form element.
     * @param array $options (optional) An array with the following possible entries:
     *                        - **referenceId** : Optional the id of a form control if this is defined within the custom content
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     * @throws \Smarty\Exception|Exception
     */
    public function addCustomContent(string $label, string $content, array $options = array())
    {
        // create array with all options
        $optionsDefault = array(
            'property'         => '',
            'referenceId'      => '',
            'helpTextId'       => '',
            'alertWarning'     => '',
            'icon'             => '',
            'class'            => '',
            'id'               => $label,
            'label'            => $label,
            'content'          => $content,
        );
        $optionsAll = array_replace($optionsDefault, $options);

        $this->render('form.custom-content', $optionsAll);
    }

    /**
     * Add a line with a custom description to the form. No form elements will be displayed in this line.
     * @param string $text The (html) text that should be displayed.
     */
    public function addDescription(string $text)
    {
        $this->htmlString .= '<p>' . $text . '</p>';
    }

    /**
     * Add a new CKEditor element to the form.
     * @param string $id ID of the password field. This will also be the name of the password field.
     * @param string $label The label of the password field.
     * @param string $value A value for the editor field. The editor will contain this value when created.
     * @param array $options (optional) An array with the following possible entries:
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                        - **toolbar** : Optional set a predefined toolbar for the editor. Possible values are
     *                          **AdmidioDefault**, **AdmidioComments** and **AdmidioNoMedia**
     *                        - **labelVertical** : If set to **true** (default) then the label will be display above the control and the control get a width of 100%.
     *                          Otherwise, the label will be displayed in front of the control.
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function addEditor(string $id, string $label, string $value, array $options = array())
    {
        global $gSettingsManager, $gL10n;

        $flagLabelVertical = $this->type;
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'property'         => self::FIELD_DEFAULT,
            'toolbar'          => 'AdmidioDefault',
            'alertWarning'     => '',
            'helpTextId'       => '',
            'labelVertical'    => true,
            'icon'             => '',
            'class'            => '',
            'id'               => $id,
            'label'            => $label,
            'value'            => $value,
        );
        $optionsAll = array_replace($optionsDefault, $options);
        $attributes = array();

        if ($optionsAll['labelVertical']) {
            $this->type = 'vertical';
        }

        if ($optionsAll['property'] === self::FIELD_REQUIRED) {
            $attributes['required'] = 'required';
            $this->flagRequiredFields = true;
        }

        if ($optionsAll['toolbar'] === 'AdmidioComments') {
            $toolbarJS = 'toolbar: ["bold", "italic", "link", "|", "numberedList", "bulletedList", "alignment", "|", "fontFamily", "fontSize", "fontColor", "|", "undo", "redo"],';
        } elseif ($optionsAll['toolbar'] === 'AdmidioNoMedia') {
            $toolbarJS = 'toolbar: ["bold", "italic", "|", "numberedList", "bulletedList", "alignment", "|", "fontFamily", "fontSize", "fontColor", "|", "link", "blockQuote", "insertTable", "|", "undo", "redo"],';
        } else {
            $toolbarJS = '';
        }

        $javascriptCode = '
        let editor;
        ClassicEditor
        .create( document.querySelector( "#' . $id . '" ), {
            ' . $toolbarJS . '
            language: "' . $gL10n->getLanguageLibs() . '",
            simpleUpload: {
                uploadUrl: "' . ADMIDIO_URL . '/adm_program/system/ckeditor_upload_handler.php?id=' . $id . '"
            }
        } )
        .then( newEditor => {
            editor = newEditor;
        })
        .catch( error => {
            console.error( error );
        } );';

        if ($gSettingsManager->getBool('system_js_editor_enabled')) {
            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if ($this->htmlPage instanceof HtmlPage) {
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/ckeditor/ckeditor.js');
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/ckeditor/translations/' . $gL10n->getLanguageLibs() . '.js');
            }
            $this->addJavascriptCode($javascriptCode, true);
        }

        $this->type = $flagLabelVertical;
        $optionsAll["attributes"] = $attributes;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->render('form.editor', $optionsAll);
    }

    /**
     * Add a field for file upload. If necessary multiple files could be uploaded.
     * The fields for multiple upload could be added dynamically to the form by the user.
     * @param string $id ID of the input field. This will also be the name of the input field.
     * @param string $label The label of the input field.
     * @param array $options (optional) An array with the following possible entries:
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                          + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                        - **allowedMimeTypes** : An array with the allowed MIME types (https://wiki.selfhtml.org/wiki/MIME-Type/%C3%9Cbersicht).
     *                          If this is set then the user can only choose the specified files with the browser file dialog.
     *                          You should check the uploaded file against the MIME type because the file could be manipulated.
     *                        - **maxUploadSize** : The size in byte that could be maximum uploaded.
     *                          The default will be $gSettingsManager->getInt('documents_files_max_upload_size') * 1024 * 1024.
     *                        - **enableMultiUploads** : If set to true a button will be added where the user can
     *                          add new upload fields to upload more than one file.
     *                        - **multiUploadLabel** : The label for the button who will add new upload fields to the form.
     *                        - **hideUploadField** : Hide the upload field if multi uploads are enabled. Then the first
     *                          upload field will be shown if the user will click the multi upload button.
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     * @throws \Smarty\Exception
     * @throws Exception
     */
    public function addFileUpload(string $id, string $label, array $options = array())
    {
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'property'           => self::FIELD_DEFAULT,
            'maxUploadSize'      => PhpIniUtils::getFileUploadMaxFileSize(),
            'allowedMimeTypes'   => array(),
            'enableMultiUploads' => false,
            'hideUploadField'    => false,
            'multiUploadLabel'   => '',
            'alertWarning'       => '',
            'helpTextId'         => '',
            'icon'               => '',
            'class'              => '',
            'id'                 => $id,
            'label'              => $label,
        );
        $optionsAll = array_replace($optionsDefault, $options);
        $attributes = array();

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
            $optionsAll['icon'] = 'bi-upload';
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
                    $(newAttachment).attr("class", "form-control mb-2 focus-ring ' . $optionsAll['class'] . '");
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

        $this->render('form.file', $optionsAll);
    }

    /**
     * Add any string to the html output. If the main element wasn't written to the
     * html string than this will be done before your string will be added.
     * @param string $string Text as string in current string position
     */
    public function addHtml(string $string)
    {
        $this->htmlString .= $string;
    }

    /**
     * Add a new input field with a label to the form.
     * @param string $id ID of the input field. This will also be the name of the input field.
     * @param string $label The label of the input field.
     * @param string $value A value for the text field. The field will be created with this value.
     * @param array $options (optional) An array with the following possible entries:
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
     *                          + **self::FIELD_HIDDEN**   : The field will not be shown. Useful to transport additional information.
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     * @throws \Smarty\Exception
     * @throws Exception
     */
    public function addInput(string $id, string $label, string $value, array $options = array())
    {
        global $gSettingsManager, $gLogger;

        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
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
            'helpTextId'       => '',
            'icon'             => '',
            'class'            => '',
            'alertWarning'     => ''
        );
        $optionsAll = array_replace($optionsDefault, $options);

        $optionsAll['helpTextId'] = self::getHelpText($optionsAll['helpTextId']);

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
                $optionsAll['class'] .= ' invisible';
                break;
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
                    $("#adm_password_strength_minimum").css("margin-left", "calc(" + $("#adm_password_strength").css("width") + " / 4 * '.$passwordStrengthLevel.')");

                    $("#' . $id . '").keyup(function(e) {
                        const result = zxcvbn(e.target.value, ' . $zxcvbnUserInputs . ');
                        const cssClasses = ["bg-danger", "bg-danger", "bg-warning", "bg-info", "bg-success"];

                        const progressBar = $("#adm_password_strength .progress-bar");
                        progressBar.attr("aria-valuenow", result.score * 25);
                        progressBar.css("width", result.score * 25 + "%");
                        progressBar.removeClass(cssClasses.join(" "));
                        progressBar.addClass(cssClasses[result.score]);
                    });
                ';
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/zxcvbn/dist/zxcvbn.js');
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

        $this->render("form.input", $optionsAll);
    }

    /**
     * Adds any javascript content to the page. The javascript will be added to the page header or as inline script.
     * @param string $javascriptCode       A valid javascript code that will be added to the header of the page or as inline script.
     * @param bool $executeAfterPageLoad (optional) If set to **true** the javascript code will be executed after
     *                                     the page is fully loaded.
     */
    protected function addJavascriptCode(string $javascriptCode, bool $executeAfterPageLoad = false)
    {
        if ($this->htmlPage instanceof HtmlPage) {
            $this->htmlPage->addJavascript($javascriptCode, $executeAfterPageLoad);
            return;
        }

        if ($executeAfterPageLoad) {
            $javascriptCode = '$(function() { ' . $javascriptCode . ' });';
        }
        $this->htmlString .= '<script type="text/javascript">' . $javascriptCode . '</script>';
    }

    /**
     * Add a simple line to the form. This could be used to structure a form. The line has only a visual effect.
     */
    public function addLine()
    {
        $this->htmlString .= '<hr />';
    }

    /**
     * Add a new textarea field with a label to the form.
     * @param string $id ID of the input field. This will also be the name of the input field.
     * @param string $label The label of the input field.
     * @param string $value A value for the text field. The field will be created with this value.
     * @param int $rows The number of rows that the textarea field should have.
     * @param array $options (optional) An array with the following possible entries:
     *                        - **maxLength** : The maximum number of characters that are allowed in this field. If set
     *                          then show a counter how many characters still available
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                          + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     * @throws \Smarty\Exception|Exception
     */
    public function addMultilineTextInput(string $id, string $label, string $value, int $rows, array $options = array())
    {
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'property'         => self::FIELD_DEFAULT,
            'maxLength'        => 0,
            'alertWarning'     => '',
            'helpTextId'       => '',
            'icon'             => '',
            'class'            => '',
            'id'               => $id,
            'label'            => $label,
            'value'            => $value
        );
        $optionsAll = array_replace($optionsDefault, $options);
        $attributes = array();

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
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/noblecount/jquery.noblecount.js');
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

        $this->render('form.multiline', $optionsAll);
    }

    /**
     * Add a new radio button with a label to the form. The radio button could have different status
     * which could be defined with an array.
     * @param string $id ID of the radio button. This will also be the name of the radio button.
     * @param string $label The label of the radio button.
     * @param array $values Array with all entries of the radio button;
     *                        Array key will be the internal value of the entry
     *                        Array value will be the visual value of the entry
     * @param array $options (optional) An array with the following possible entries:
     *                        - **property** : With this param you can set the following properties:
     *                          + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                          + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                          + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                        - **defaultValue** : This is the value of that radio button that is preselected.
     *                        - **showNoValueButton** : If set to true than one radio with no value will be set in front of the other array.
     *                          This could be used if the user should also be able to set no radio to value.
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     * @throws \Smarty\Exception|Exception
     */
    public function addRadioButton(string $id, string $label, array $values, array $options = array())
    {
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'property'          => self::FIELD_DEFAULT,
            'defaultValue'      => '',
            'showNoValueButton' => false,
            'alertWarning'      => '',
            'helpTextId'        => '',
            'icon'              => '',
            'class'             => '',
            'id'                => $id,
            'label'             => $label,
            'values'            => $values
        );
        $optionsAll = array_replace($optionsDefault, $options);
        $attributes = array();

        // disable field
        if ($optionsAll['property'] === self::FIELD_DISABLED) {
            $attributes['disabled'] = 'disabled';
        } elseif ($optionsAll['property'] === self::FIELD_REQUIRED) {
            $attributes['required'] = 'required';
            $this->flagRequiredFields = true;
        }

        $optionsAll["attributes"] = $attributes;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->render('form.radio', $optionsAll);
    }

    /**
     * Add a new selectbox with a label to the form. The selectbox
     * could have different values and a default value could be set.
     * @param string $id ID of the selectbox. This will also be the name of the selectbox.
     * @param string $label The label of the selectbox.
     * @param array $values Array with all entries of the select box;
     *                        Array key will be the internal value of the entry
     *                        Array value will be the visual value of the entry
     *                        If you need an option group within the selectbox than you must add an array as value.
     *                        This array exists of 3 entries: array(0 => id, 1 => value name, 2 => option group name)
     * @param array $options (optional) An array with the following possible entries:
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
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     * @throws \Smarty\Exception
     * @throws Exception
     */
    public function addSelectBox(string $id, string $label, array $values, array $options = array())
    {
        global $gL10n;

        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
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
            'helpTextId'                     => '',
            'icon'                           => '',
            'class'                          => '',
            'id'                             => $id,
            'label'                          => $label
        );
        $optionsAll = array_replace($optionsDefault, $options);
        $attributes = array('name' => $id);

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

        // reorganize the values. Each value item should be an array with the following structure:
        // array(0 => id, 1 => value name, 2 => option group name)
        $valuesArray = array();
        foreach($values as $arrayKey => $arrayValue) {
            if (is_array($arrayValue)) {
                if (array_key_exists(2, $arrayValue)) {
                    $valuesArray[] = array(
                        'id' => ($optionsAll['arrayKeyIsNotValue'] ? $arrayValue[1] : $arrayValue[0]),
                        'value' => Admidio\Infrastructure\Language::translateIfTranslationStrId($arrayValue[1]),
                        'group' => Admidio\Infrastructure\Language::translateIfTranslationStrId($arrayValue[2])
                    );
                } else {
                    $valuesArray[] = array(
                        'id' => ($optionsAll['arrayKeyIsNotValue'] ? $arrayValue[1] : $arrayValue[0]),
                        'value' => Admidio\Infrastructure\Language::translateIfTranslationStrId($arrayValue[1])
                    );
                }
            } else {
                $valuesArray[] = array('id' => ($optionsAll['arrayKeyIsNotValue'] ? $arrayValue : $arrayKey), 'value' => Admidio\Infrastructure\Language::translateIfTranslationStrId($arrayValue));
            }
        }

        // if special value attributes are set then add them to the values array
        if(is_array($optionsAll['valueAttributes']) && count($optionsAll['valueAttributes']) > 0) {
            foreach($valuesArray as &$valueArray) {
                if (isset($optionsAll['valueAttributes'][$valueArray['id']])) {
                    foreach($optionsAll['valueAttributes'][$valueArray['id']] as $key => $value) {
                        $valueArray[$key] = $value;
                    }
                }
            }
        }

        if ($optionsAll['multiselect']) {
            $attributes['multiple'] = 'multiple';
            $attributes['name'] = $id . '[]';

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

        if ($optionsAll['firstEntry'] !== '') {
            if (is_array($optionsAll['firstEntry'])) {
                array_unshift($valuesArray, array('id' => $optionsAll['firstEntry'][0], 'value' => $optionsAll['firstEntry'][1]));
            } else {
                array_unshift($valuesArray, array('id' => '', 'value' => '- ' . $optionsAll['firstEntry'] . ' -'));
            }
        } elseif ($optionsAll['showContextDependentFirstEntry']) {
            if ($optionsAll['property'] === self::FIELD_REQUIRED) {
                array_unshift($valuesArray, array('id' => '', 'value' => '- ' . $gL10n->get('SYS_PLEASE_CHOOSE') . ' -'));
            } else {
                array_unshift($valuesArray, array('id' => '', 'value' => ''));
            }
        } elseif (count($valuesArray) === 0) {
            $valuesArray[] = array('id' => '', 'value' => '');
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
                    theme: "bootstrap-5",
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
                $this->htmlPage->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/css/select2.css');
                $this->htmlPage->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2-bootstrap-theme/select2-bootstrap-5-theme.css');
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/select2.js');
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/i18n/' . $gL10n->getLanguageLibs() . '.js');
            }
            $this->addJavascriptCode($javascriptCode, true);
        }

        $optionsAll["values"] = $valuesArray;
        $optionsAll["attributes"] = $attributes;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->render('form.select', $optionsAll);
    }

    /**
     * Add a new selectbox with a label to the form. The selectbox get their data from a sql statement.
     * You can create any sql statement and this method should create a selectbox with the found data.
     * The sql must contain at least two columns. The first column represents the value and the second
     * column represents the label of each option of the selectbox. Optional you can add a third column
     * to the sql statement. This column will be used as label for an optiongroup. Each time the value
     * of the third column changed a new optiongroup will be created.
     * @param string $id ID of the selectbox. This will also be the name of the selectbox.
     * @param string $label The label of the selectbox.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param array|string $sql Any SQL statement that return 2 columns. The first column will be the internal value of the
     *                               selectbox item and will be submitted with the form. The second column represents the
     *                               displayed value of the item. Each row of the result will be a new selectbox entry.
     * @param array $options (optional) An array with the following possible entries:
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
     *                                 another array with the combination of attributes name and attributes value.
     *                               - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                                 e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                                 If you need an additional parameter for the text you can add an array. The first entry
     *                                 must be the unique text id and the second entry will be a parameter of the text id.
     *                               - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                                 will be the text of the alertbox
     *                               - **icon** : An icon can be set. This will be placed in front of the label.
     *                               - **class** : An additional css classname. The class **admSelectbox**
     *                                 is set as default and need not set with this parameter.
     * **Code examples**
     * ```
     * // create a selectbox with all profile fields of a specific category
     * $sql = 'SELECT usf_id, usf_name FROM '.TBL_USER_FIELDS.' WHERE usf_cat_id = 4711'
     * $form = new HtmlForm('simple-form', 'adm_next_page.php');
     * $form->addSelectBoxFromSql('admProfileFieldsBox', $gL10n->get('SYS_FIELDS'), $gDb, $sql, array('defaultValue' => $gL10n->get('SYS_SURNAME'), 'showContextDependentFirstEntry' => true));
     * $form->show();
     * ```
     * @throws \Smarty\Exception
     * @throws Exception
     */
    public function addSelectBoxFromSql(string $id, string $label, Database $database, $sql, array $options = array())
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
                $row[2] = Admidio\Infrastructure\Language::translateIfTranslationStrId((string) $row[2]);

                $selectBoxEntries[] = array($row[0], (string) $row[1], $row[2]);
            } else {
                $selectBoxEntries[$row[0]] = (string) $row[1];
            }
        }

        // now call default method to create a selectbox
        $this->addSelectBox($id, $label, $selectBoxEntries, $options);
    }

    /**
     * Add a new selectbox with a label to the form. The selectbox could have
     * different values and a default value could be set.
     * @param string $id ID of the selectbox. This will also be the name of the selectbox.
     * @param string $label The label of the selectbox.
     * @param string $xmlFile Server path to the xml file
     * @param string $xmlValueTag Name of the xml tag that should contain the internal value of a selectbox entry
     * @param string $xmlViewTag Name of the xml tag that should contain the visual value of a selectbox entry
     * @param array $options (optional) An array with the following possible entries:
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
     *                          another array with the combination of attributes name and attributes value.
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     * @throws Exception
     */
    public function addSelectBoxFromXml(string $id, string $label, string $xmlFile, string $xmlValueTag, string $xmlViewTag, array $options = array())
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
     * You must define the category type (roles, events, links ...). All categories of this type will be shown.
     * @param string $id ID of the selectbox. This will also be the name of the selectbox.
     * @param string $label The label of the selectbox.
     * @param Database $database A Admidio database object that contains a valid connection to a database
     * @param string $categoryType Type of category ('EVT', 'LNK', 'ROL', 'USF') that should be shown.
     *                                 The type 'ROL' will ot list event role categories. Therefore, you need to set
     *                                 the type 'ROL_EVENT'. It's not possible to show role categories together with
     *                                 event categories.
     * @param string $selectBoxModus The selectbox could be shown in 2 different modus.
     *                                 - **EDIT_CATEGORIES** : First entry will be "Please choose" and default category will be preselected.
     *                                 - **FILTER_CATEGORIES** : First entry will be "All" and only categories with children will be shown.
     * @param array $options (optional) An array with the following possible entries:
     *                                 - **property** : With this param you can set the following properties:
     *                                   + **self::FIELD_DEFAULT**  : The field can accept an input.
     *                                   + **self::FIELD_REQUIRED** : The field will be marked as a mandatory field where the user must insert a value.
     *                                   + **self::FIELD_DISABLED** : The field will be disabled and could not accept an input.
     *                                 - **defaultValue** : ID of category that should be selected per default.
     *.                                - **arrayKeyIsNotValue** : If set to **true** than the entry of the values-array will be used as
     *                                   option value and not the key of the array
     *                                 - **showSystemCategory** : Show user defined and system categories
     *                                 - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                                   e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                                   If you need an additional parameter for the text you can add an array. The first entry
     *                                   must be the unique text id and the second entry will be a parameter of the text id.
     *                                 - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                                   will be the text of the alertbox
     *                                 - **icon** : An icon can be set. This will be placed in front of the label.
     *                                 - **class** : An additional css classname. The class **admSelectbox**
     *                                   is set as default and need not set with this parameter.
     * @throws \Smarty\Exception
     * @throws Exception
     */
    public function addSelectBoxForCategories(string $id, string $label, Database $database, string $categoryType, string $selectBoxModus, array $options = array())
    {
        global $gCurrentOrganization, $gCurrentUser, $gL10n;

        // create array with all options
        $optionsDefault = array(
            'property'                       => self::FIELD_DEFAULT,
            'defaultValue'                   => '',
            'arrayKeyIsNotValue'             => false,
            'showContextDependentFirstEntry' => true,
            'multiselect'                    => false,
            'showSystemCategory'             => true,
            'alertWarning'                   => '',
            'helpTextId'                     => '',
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
                case 'EVT':
                    $sqlTables = ' INNER JOIN ' . TBL_EVENTS . ' ON cat_id = dat_cat_id ';
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

        // if no or only one category exist and in filter modus, then don't show category
        if ($selectBoxModus === self::SELECT_BOX_MODUS_FILTER && ($countCategories === 0 || $countCategories === 1)) {
            return;
        }

        $categoriesArray = array();
        $optionsAll['valueAttributes'] = array();

        if ($selectBoxModus === self::SELECT_BOX_MODUS_FILTER && $countCategories > 1) {
            $categoriesArray[] = array('', $gL10n->get('SYS_ALL'));
            $optionsAll['valueAttributes'][0] = array('data-global' => 0);
        }

        while ($row = $pdoStatement->fetch()) {
            // if several categories exist than select default category
            if ($selectBoxModus === self::SELECT_BOX_MODUS_EDIT && $optionsAll['defaultValue'] === ''
                && ($countCategories === 1 || $row['cat_default'] === 1)) {
                $optionsAll['defaultValue'] = $row['cat_uuid'];
            }

            // add label that this category is visible to all organizations
            if ($row['cat_org_id'] === null) {
                if ($row['cat_name'] !== $gL10n->get('SYS_ALL_ORGANIZATIONS')) {
                    $row['cat_name'] .=  ' (' . $gL10n->get('SYS_ALL_ORGANIZATIONS') . ')';
                }
                $optionsAll['valueAttributes'][$row['cat_uuid']] = array('data-global' => 1);
            } else {
                $optionsAll['valueAttributes'][$row['cat_uuid']] = array('data-global' => 0);

            }
            // if text is a translation-id then translate it
            $categoriesArray[] = array($row['cat_uuid'], Admidio\Infrastructure\Language::translateIfTranslationStrId($row['cat_name']));
        }

        // now call method to create select box from array
        $this->addSelectBox($id, $label, $categoriesArray, $optionsAll);
    }

    /**
     * Add a new static control to the form. A static control is only a simple text instead of an input field.
     * This could be used if the value should not be changed by the user.
     * @param string $id ID of the static control. This will also be the name of the static control.
     * @param string $label The label of the static control.
     * @param string $value A value of the static control. The control will be created with this value.
     * @param array $options (optional) An array with the following possible entries:
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alertbox
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     * @throws \Smarty\Exception|Exception
     */
    public function addStaticControl(string $id, string $label, string $value, array $options = array())
    {
        ++$this->countElements;

        // create array with all options
        $optionsDefault = array(
            'property'         => '',
            'alertWarning'     => '',
            'helpTextId'       => '',
            'icon'             => '',
            'class'            => '',
            'id'               => $id,
            'label'            => $label,
            'value'            => $value);
        $optionsAll     = array_replace($optionsDefault, $options);

        $this->render('form.static', $optionsAll);
    }

    /**
     * Add a new button with a custom text to the form. This button could have
     * an icon in front of the text. Different to addButton this method adds an
     * **div** around the button and the type of the button is **submit**.
     * @param string $id ID of the button. This will also be the name of the button.
     * @param string $text Text of the button
     * @param array $options (optional) An array with the following possible entries:
     *                        - **icon** : Optional parameter. Path and filename of an icon.
     *                          If set an icon will be shown in front of the text.
     *                        - **link** : If set a javascript click event with a page load to this link
     *                          will be attached to the button.
     *                        - **class** : Optional an additional css classname. The class **admButton**
     *                          is set as default and need not set with this parameter.
     *                        - **type** : If set to true this button get the type **submit**. This will
     *                          be the default.
     * @throws \Smarty\Exception|Exception
     */
    public function addSubmitButton(string $id, string $text, array $options = array())
    {
        // create array with all options
        $optionsDefault = array('icon' => '', 'link' => '', 'class' => '', 'type' => 'submit');
        $optionsAll     = array_replace($optionsDefault, $options);

        // add default css classes
        $optionsAll['class'] .= ' btn-primary';
        if ($this->type !== 'navbar') {
            $optionsAll['class'] .= '  admidio-margin-bottom';
        }

        // now add button to form
        $this->addButton($id, $text, $optionsAll);

        if (!$this->buttonGroupOpen) {
            $this->htmlString .= '<div class="form-alert" style="display: none;">&nbsp;</div>';
        }
    }

    /**
     * Close an open bootstrap btn-group
     */
    public function closeButtonGroup()
    {
        $this->buttonGroupOpen = false;
        $this->htmlString .= '</div><div class="form-alert" style="display: none;">&nbsp;</div>';
    }

    /**
     * Close all html elements of a groupbox that was created before.
     */

    public function closeGroupBox()
    {
        $this->htmlString .= '</div></div>';
    }

    /**
     * Add a small help icon to the form at the current element which shows the translated text of the
     * text-id or an individual text on mouseover. The title will be note if it's a text-id or
     * description if it's an individual text.
     * @param string $string A text that should be shown or a unique text id from the translation xml files
     *                          that should be shown e.g. SYS_DATA_CATEGORY_GLOBAL.
     * @param string $title A text-id that represents the title of the help text. Default will be SYS_NOTE.
     * @param array $parameter If you need an additional parameters for the text you can set this parameter values within an array.
     * @return string Return a html snippet that contains a help icon with a link to a popup box that shows the message.
     * @throws Exception
     */
    public static function getHelpTextIcon(string $string, string $title = 'SYS_NOTE', array $parameter = array()): string
    {
        global $gL10n;

        $html = '';

        if(strlen($string) > 0) {
            if (Admidio\Infrastructure\Language::isTranslationStringId($string)) {
                $text  = $gL10n->get($string, $parameter);
            } else {
                $text  = $string;
            }

            $html = '<i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
            data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
            title="' . $gL10n->get($title) . '" data-bs-content="' . SecurityUtils::encodeHTML($text) . '"></i>';
        }
        return $html;
    }

    /**
     * @throws Exception
     */
    public static function getHelpText($text)
    {
        $parameters = array();

        if (is_array($text)) {
            $parameters = $text[1];
            $text = $text[0];
        }

        if ($text !== '') {
            // if text is a translation-id then translate it
            if (Admidio\Infrastructure\Language::isTranslationStringId($text)) {
                foreach ($parameters as &$parameter) {
                    // parameters should be strings
                    $parameter = (string)$parameter;

                    // if parameter is a translation-id then translate it
                    $parameter = Admidio\Infrastructure\Language::translateIfTranslationStrId($parameter);
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
        $this->htmlString .= '<div class="btn-group" role="group">';
    }

    /**
     * Add a new groupbox to the form. This could be used to group some elements
     * together. There is also the option to set a headline to this group box.
     * @param string $id       ID of the groupbox.
     * @param string|null $headline (optional) A headline that will be shown to the user.
     * @param string $class    (optional) An additional css classname for the row. The class **admFieldRow**
     *                         is set as default and need not set with this parameter.
     */

    public function openGroupBox(string $id, string $headline = null, string $class = '')
    {
        $this->htmlString .= '<div id="' . $id . '" class="card admidio-field-group ' . $class . '">';
        // add headline to groupbox
        if ($headline !== null) {
            $this->htmlString .= '<div class="card-header">' . $headline . '</div>';
        }
        $this->htmlString .= '<div class="card-body">';
    }

    /**
     * Create the html code from the template and add this to the internal $htmlString variable.
     * @param string $templateName Name of the template file that should be used.
     * @param array $assigns Array with variables that should be assigned to the template.
     * @return void
     * @throws \Smarty\Exception|Exception
     */
    private function render(string $templateName, array $assigns)
    {
        global $gL10n;

        if (is_object($this->htmlPage)) {
            $smarty = $this->htmlPage->getSmartyTemplate();
        } else {
            $smarty = HtmlPage::createSmartyObject();
        }

        $smarty->assign('formType', $this->type);
        $smarty->assign('data', $assigns);
        $smarty->assign('urlAdmidio', ADMIDIO_URL);
        $smarty->assign('l10n', $gL10n);
        $this->htmlString .= $smarty->fetch("sys-template-parts/".$templateName.'.tpl');
    }

    /**
     * This method create the whole html code of the form. Call this method
     * if you have finished your form layout. If mandatory fields were set than a notice
     * which marker represents the mandatory will be shown before the form.
     * @return string Return the html code of the form.
     * @throws Exception
     */
    public function show(): string
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
        $html .= '<form id="'.$this->id.'" action="'.$this->action.'"';
        foreach($this->attributes as $name => $value) {
            $html .= ' '.$name.'="'.$value.'"';
        }
        $html .= '>' . $this->htmlString . '</form>';

        return $html;
    }
}
