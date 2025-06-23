<?php

namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use DateTime;
use PDO;
use Securimage;
use Admidio\Infrastructure\Utils\SecurityUtils;
use SimpleXMLElement;
use Smarty\Smarty;
use HTMLPurifier;
use HTMLPurifier_Config;
use Admidio\Infrastructure\Utils\StringUtils;

/**
 * @brief Creates an Admidio specific form
 *
 * This class should be used to create a form based on a Smarty template. Therefore, a method for each
 * possible form field is available and could be customized through various parameters. If the form is fully
 * defined with all fields it could be added to a PagePresenter object. The form object should be stored in
 * session parameter so the input could later be validated against the form configuration.
 *
 * **Code examples**
 * ```
 * script_a.php
 * // create a simple form with one input field and a button
 * $form = $form = new FormPresenter(
 *    'announcements_edit_form',
 *    'modules/announcements.edit.tpl',
 *    ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements_function.php',
 *    $page
 * );
 * $form->addInput('name', $gL10n->get('SYS_NAME'), $formName);
 * $form->addSelectBox('type', $gL10n->get('SYS_TYPE'), array('simple' => 'SYS_SIMPLE', 'very-simple' => 'SYS_VERY_SIMPLE'),
 *                     array('defaultValue' => 'simple', 'showContextDependentFirstEntry' => true));
 * $form->addSubmitButton('next-page', $gL10n->get('SYS_NEXT'), array('icon' => 'bi-arrow-right-circle-fill'));
 * $form->addToHtmlPage();
 * $_SESSION['announcementsEditForm'] = $form;
 *
 * script_b.php
 * // do the validation of the form input
 * if (isset($_SESSION['announcementsEditForm'])) {
 *    $announcementEditForm = $_SESSION['announcementsEditForm'];
 *    $announcementEditForm->validate($_POST);
 * } else {
 *    throw new Exception('SYS_INVALID_PAGE_VIEW');
 * }
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class FormPresenter
{
    public const FIELD_DEFAULT = 0;
    public const FIELD_REQUIRED = 1;
    public const FIELD_DISABLED = 2;
    public const FIELD_READONLY = 3;
    public const FIELD_HIDDEN = 4;

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
     * @var PagePresenter A PagePresenter object that will be used to add javascript code or files to the html output page.
     */
    protected PagePresenter $htmlPage;
    /**
     * @var string Javascript of this form that must be integrated in the html page.
     */
    protected string $javascript = '';
    /**
     * @var string Form type. Possible values are **default**, **vertical** or **navbar**.
     */
    protected string $type = '';
    /**
     * @var string ID of the form
     */
    protected string $id = '';
    /**
     * @var string a 30 character long CSRF token
     */
    protected string $csrfToken = '';
    /**
     * @var string Smarty template with necessary path
     */
    protected string $template = '';
    /**
     * @var array Array with all possible attributes of the form e.g. class, action, id ...
     */
    protected array $attributes = array();
    /**
     * @var array Array with all elements of the form and their attributes as array
     */
    protected array $elements = array();

    /**
     * Constructor creates the form element
     * @param string $id ID of the form
     * @param string $action Action attribute of the form
     * @param PagePresenter|null $htmlPage (optional) A PagePresenter object that will be used to add javascript code or files to the html output page.
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
     * @throws Exception
     */
    public function __construct(string $id, string $template, string $action = '', ?PagePresenter $htmlPage = null, array $options = array())
    {
        // create array with all options
        $optionsDefault = array(
            'type' => 'default',
            'enableFileUpload' => false,
            'showRequiredFields' => true,
            'setFocus' => true,
            'class' => '',
            'method' => 'post'
        );

        // navbar form should send the data as GET if it's not explicit set
        if (isset($options['type']) && $options['type'] === 'navbar' && !isset($options['method'])) {
            $options['method'] = 'get';
        }

        $optionsAll = array_replace($optionsDefault, $options);
        $this->showRequiredFields = $optionsAll['showRequiredFields'];
        $this->type = $optionsAll['type'];
        $this->id = $id;
        $this->template = $template;

        // set specific Admidio css form class
        $this->attributes['id'] = $this->id;
        $this->attributes['role'] = 'form';
        $this->attributes['action'] = $action;
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

        if ($optionsAll['method'] === 'post') {
            // add a hidden field with the csrf token to each form
            $this->addInput(
                'adm_csrf_token',
                'csrf-token',
                $this->getCsrfToken(),
                array('property' => self::FIELD_HIDDEN)
            );
        }

        if ($htmlPage instanceof PagePresenter) {
            $this->htmlPage =& $htmlPage;
        }

        // if it's not a navbar form and not a static form then first field of form should get focus
        if ($optionsAll['setFocus']) {
            $this->addJavascriptCode('$(".form-dialog:first *:input:enabled:visible:not([readonly]):first").focus();', true);
        }
    }

    /**
     * We need the sleep function at this place because otherwise the system will serialize a SimpleXMLElement
     * which will lead to an exception.
     * @return array<int,string>
     */
    public function __sleep()
    {
        global $gLogger;

        if ($gLogger instanceof \Psr\Log\LoggerInterface) {
            $gLogger->debug('FORM: sleep/serialize!');
        }

        return array('flagRequiredFields', 'showRequiredFields', 'javascript', 'type', 'id', 'csrfToken', 'template', 'attributes', 'elements');
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
     */
    public function addButton(string $id, string $text, array $options = array()): void
    {
        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'button',
            'id' => $id,
            'value' => $text
        ), $options));
        $attributes = array();
        $attributes['type'] = $optionsAll['type'];
        $attributes['data-admidio'] = $optionsAll['data-admidio'];
        if (array_key_exists('style', $optionsAll)) {
            $attributes['style'] = $optionsAll['style'];
        }
        // disable field
        if ($optionsAll['property'] === self::FIELD_DISABLED) {
            $attributes['disabled'] = 'disabled';
        }
        if (!isset($options['link'])) {
            $optionsAll['link'] = '';
        }
        if (!str_contains($optionsAll['class'], 'btn-')) {
            $optionsAll['class'] .= " btn-secondary";

            if ($this->type !== 'navbar') {
                $optionsAll['class'] .= '  admidio-margin-bottom';
            }
        }

        $optionsAll['attributes'] = $attributes;
        $this->elements[$id] = $optionsAll;
    }

    /**
     * Add a new selectbox with a label to the form. The selectbox
     * could have different values and a default value could be set.
     * @param string $id ID of the selectbox. This will also be the name of the selectbox.
     * @param array $values Array with all entries of the radio button group.
     *                      Each entry is an array with the following structure:
     *                      array(0 => id, 1 => value name, 2 => destination url)
     *                      The destination url is optional and contains the url where the user will be redirected
     *                      if the button is selected.
     * @param array $options (optional) An array with the following possible entries:
     *                        - **defaultValue** : This is the value the selectbox shows when loaded. If **multiselect** is activated than
     *                          an array with all default values could be set.
     *                        - **arrayKeyIsNotValue** : If set to **true** than the entry of the values-array will be used as
     *                          option value and not the key of the array
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alert box
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     *                        - **autocomplete** : Set the html attribute autocomplete to support this feature
     *                          https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete
     * @throws Exception
     */
    public function addButtonGroupRadio(string $id, array $values, array $options = array()): void
    {
        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'button-group.radio',
            'id' => $id,
            'label' => '',
            'defaultValue' => '',
            'valueAttributes' => ''
        ), $options));
        $attributes = array('name' => $id);

        // reorganize the values
        $javascriptCode = '';
        $valuesArray = array();

        foreach ($values as $value) {
            if (is_array($value)) {
                $valuesArray[] = array(
                    'id' => $value[0],
                    'value' => Language::translateIfTranslationStrId($value[1]),
                    'default' => $optionsAll['defaultValue'] === $value[0],
                    'url' => ($value[2] ?? '')
                );

                if (isset($value[2])) {
                    $javascriptCode .= '
                        $("#' . $value[0] . '").click(function() {
                            window.location.href = "' . $value[2] . '";
                        });';
                }
            }
        }

        $this->addJavascriptCode($javascriptCode, true);

        $optionsAll["values"] = $valuesArray;
        $optionsAll["attributes"] = $attributes;

        $this->elements[$id] = $optionsAll;
    }

    /**
     * Add a captcha with an input field to the form. The captcha could be a picture with a character code
     * or a simple mathematical calculation that must be solved.
     * @param string $id ID of the captcha field. This will also be the name of the captcha field.
     * @throws Exception
     */
    public function addCaptcha(string $id): void
    {
        global $gL10n;

        $this->addJavascriptCode('
            $("#' . $id . '_refresh").click(function() {
                $("#adm_captcha").attr("src", "' . ADMIDIO_URL . FOLDER_LIBS . '/securimage/securimage_show.php?" + Math.random());
            });', true);
        // now add a row with a text field where the user can write the solution for the puzzle
        $this->addInput(
            $id,
            $gL10n->get('SYS_CAPTCHA_CONFIRMATION_CODE'),
            '',
            array(
                'type' => 'captcha',
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
     *                          will be the text of the alert box
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addCheckbox(string $id, string $label, bool $checked = false, array $options = array()): void
    {
        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'checkbox',
            'id' => $id,
            'label' => $label
        ), $options));
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

        $this->elements[$id] = $optionsAll;
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
     *                          will be the text of the alert box
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addCustomContent(string $id, string $label, string $content, array $options = array()): void
    {
        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'custom-content',
            'id' => $id,
            'label' => $label,
            'content' => $content
        ), $options));

        $this->elements[$id] = $optionsAll;
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
     */
    public function addEditor(string $id, string $label, string $value, array $options = array()): void
    {
        global $gSettingsManager, $gL10n;

        $flagLabelVertical = $this->type;

        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'editor',
            'id' => $id,
            'label' => $label,
            'toolbar' => 'AdmidioDefault',
            'labelVertical' => true,
            'value' => $value
        ), $options));

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
                uploadUrl: "' . ADMIDIO_URL . FOLDER_SYSTEM . '/ckeditor_upload_handler.php?id=' . $id . '"
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
            if (isset($this->htmlPage)) {
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

        $this->elements[$id] = $optionsAll;
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
     */
    public function addFileUpload(string $id, string $label, array $options = array()): void
    {
        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'file',
            'id' => $id,
            'label' => $label,
            'maxUploadSize' => PhpIniUtils::getFileUploadMaxFileSize(),
            'allowedMimeTypes' => array(),
            'enableMultiUploads' => false,
            'hideUploadField' => false,
            'multiUploadLabel' => ''
        ), $options));

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

        $this->elements[$id] = $optionsAll;
        $this->elements['MAX_FILE_SIZE'] = array('id' => 'MAX_FILE_SIZE', 'type' => 'hidden');
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
     *                          will be the text of the alert box
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     *                        - **autocomplete** : Set the html attribute autocomplete to support this feature
     *                          https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete
     * @throws Exception
     */
    public function addInput(string $id, string $label, string $value, array $options = array()): void
    {
        global $gSettingsManager, $gLogger, $gL10n;

        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'text',
            'id' => $id,
            'label' => $label,
            'value' => $value,
            'placeholder' => '',
            'pattern' => '',
            'minLength' => null,
            'maxLength' => null,
            'minNumber' => null,
            'maxNumber' => null,
            'step' => null,
            'passwordStrength' => false,
            'passwordUserData' => array()
        ), $options));

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

            // add time input field to elements array
            $optionsTime = $optionsAll;
            $optionsTime['id'] = $id . '_time';
            $optionsTime['label'] = $label . ' ' . $gL10n->get('SYS_TIME');
            $optionsTime['type'] = 'time';
            $this->elements[$id . '_time'] = $optionsTime;
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

        // set field properties
        switch ($optionsAll['property']) {
            case self::FIELD_DISABLED:
                if ($optionsAll['type'] === 'datetime') {
                    $attributes['dateValueAttributes']['disabled'] = 'disabled';
                    $attributes['timeValueAttributes']['disabled'] = 'disabled';
                } else {
                    $attributes['disabled'] = 'disabled';
                }
                break;

            case self::FIELD_READONLY:
                if ($optionsAll['type'] === 'datetime') {
                    $attributes['dateValueAttributes']['readonly'] = 'readonly';
                    $attributes['timeValueAttributes']['readonly'] = 'readonly';
                } else {
                    $attributes['readonly'] = 'readonly';
                }
                break;

            case self::FIELD_REQUIRED:
                if ($optionsAll['type'] === 'datetime') {
                    $attributes['dateValueAttributes']['required'] = 'required';
                    $attributes['timeValueAttributes']['required'] = 'required';
                } else {
                    $attributes['required'] = 'required';
                }
                $this->flagRequiredFields = true;
                break;

            case self::FIELD_HIDDEN:
                $attributes['hidden'] = 'hidden';
                $optionsAll['class'] .= ' invisible';
                break;
        }

        if ($optionsAll['passwordStrength']) {
            $passwordStrengthLevel = 1;
            if (isset($gSettingsManager) && $gSettingsManager->getInt('password_min_strength')) {
                $passwordStrengthLevel = $gSettingsManager->getInt('password_min_strength');
            }

            if (isset($this->htmlPage)) {
                $zxcvbnUserInputs = json_encode($optionsAll['passwordUserData'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $javascriptCode = '
                    $("#adm_password_strength_minimum").css("margin-left", "calc(" + $("#adm_password_strength").css("width") + " / 4 * ' . $passwordStrengthLevel . ')");

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

        if (isset($optionsAll['autocomplete'])) {
            $attributes['autocomplete'] = $optionsAll['autocomplete'];
        }

        $optionsAll['attributes'] = $attributes;
        // replace quotes with html entities to prevent xss attacks
        $optionsAll['value'] = $value;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->elements[$id] = $optionsAll;
    }

    /**
     * Adds any javascript content to the page. The javascript will be added to the page header or as inline script.
     * @param string $javascriptCode A valid javascript code that will be added to the header of the page or as inline script.
     * @param bool $executeAfterPageLoad (optional) If set to **true** the javascript code will be executed after
     *                                     the page is fully loaded.
     */
    protected function addJavascriptCode(string $javascriptCode, bool $executeAfterPageLoad = false): void
    {
        if (isset($this->htmlPage)) {
            $this->htmlPage->addJavascript($javascriptCode, $executeAfterPageLoad);
            return;
        }

        if ($executeAfterPageLoad) {
            $javascriptCode = '$(function() { ' . $javascriptCode . ' });';
        }
        $this->javascript .= '<script type="text/javascript">' . $javascriptCode . '</script>';
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
     */
    public function addMultilineTextInput(string $id, string $label, string $value, int $rows, array $options = array()): void
    {
        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'multiline',
            'id' => $id,
            'label' => $label,
            'maxLength' => 0,
            'value' => $value
        ), $options));
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
                $attributes['class'] = ' invisible';
                break;
        }

        if ($optionsAll['maxLength'] > 0) {
            $attributes['maxLength'] = $optionsAll['maxLength'];

            // if max field length is set then show a counter how many characters still available
            $javascriptCode = '
                $("#' . $id . '").NobleCount("#' . $id . '_counter", {
                    max_chars: ' . $optionsAll['maxLength'] . ',
                    on_negative: "systeminfoBad",
                    block_negative: true
                });';

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if (isset($this->htmlPage)) {
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

        $this->elements[$id] = $optionsAll;
    }

    public function addOptionEditor(string $id, string $label, array $values, array $options = array()): void
    {
        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'option-editor',
            'id' => $id,
            'label' => $label,
            'values' => $values
        ), $options));
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
                $attributes['class'] = ' invisible';
                break;
        }

        $optionsAll["attributes"] = $attributes;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->addJavascriptCode('
            function updateEntryMoves() {
                $("tbody.admidio-sortable").each(function() {
                    var $rows = $(this).find(\'tr[id*="_option_"]\').has(\'td[id*="_move_actions"]\').has(".admidio-entry-move");
                    $rows.each(function(index) {
                        var $upArrow   = $(this).find(\'.admidio-entry-move[data-direction="UP"]\');
                        var $downArrow = $(this).find(\'.admidio-entry-move[data-direction="DOWN"]\');

                        if (index === 0) {
                            $upArrow.css("visibility", "hidden");
                        } else {
                            $upArrow.css("visibility", "visible");
                        }

                        if (index === $rows.length - 1) {
                            $downArrow.css("visibility", "hidden");
                        } else {
                            $downArrow.css("visibility", "visible");
                        }
                    });
                });
            }
            function addOptionRow(dataId, deleteUrl, csrfToken, translationStrings) {
                const table = document.getElementById(dataId + "_table").getElementsByTagName("tbody")[0];
                const newRow = document.createElement("tr");
                const rows = table.querySelectorAll(\'tr[id^="\' + dataId + \'_option_"]\');
                let maxId = 0;
                rows.forEach(row => {
                    const currentId = row.id.replace(dataId + "_option_", "");
                    const num = parseInt(currentId, 10);
                    if (!isNaN(num) && num > maxId) {
                        maxId = num;
                    }
                });
                const optionId = maxId + 1;
                newRow.innerHTML = `
                    <td><input class="form-control focus-ring" type="text" name="${dataId}[${optionId}][value]" required="required"></td>
                    <td class="align-middle" style="display: none;">
                        <div class="admidio-form-group form-check form-switch d-flex justify-content-center">
                            <input class="form-control focus-ring" type="text" name="${dataId}[${optionId}][obsolete]" value="">
                        </div>
                    </td>
                    <td id="${dataId}_option_${optionId}_move_actions" class="text-center align-middle">
                        <a class="admidio-icon-link admidio-entry-move" href="javascript:void(0)"
                            data-direction="UP" data-target="${dataId}_option_${optionId}">
                            <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="${translationStrings.move_up}"></i>
                        </a>
                        <a class="admidio-icon-link admidio-entry-move" href="javascript:void(0)"
                            data-direction="DOWN" data-target="${dataId}_option_${optionId}">
                            <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="${translationStrings.move_down}"></i>
                        </a>
                        <a class="admidio-icon-link">
                            <i class="bi bi-arrows-move handle" data-bs-toggle="tooltip" title="${translationStrings.move_var}"></i>
                        </a>
                    </td>
                    <td id="${dataId}_option_${optionId}_delete_actions" class="text-center align-middle">
                        <a id="${dataId}_option_${optionId}_restore" class="admidio-icon-link" href="javascript:void(0)" onclick="restoreEntry(\'${dataId}\', \'${optionId}\');" style="display: none;">
                            <i class="bi bi-arrow-counterclockwise text-success" data-bs-toggle="tooltip" title="${translationStrings.restore}"></i>
                        </a>
                        <a id="${dataId}_option_${optionId}_delete" class="admidio-icon-link" href="javascript:void(0)" onclick="deleteEntry(\'${dataId}\', \'${optionId}\', \'${deleteUrl}\', \'${csrfToken}\');">
                            <i class="bi bi-trash-fill text-danger" data-bs-toggle="tooltip" title="${translationStrings.delete}"></i>
                        </a>
                    </td>
                `;
                newRow.id = dataId + "_option_" + optionId;
                newRow.setAttribute("data-uuid", optionId);
                newRow.querySelectorAll(\'[data-bs-toggle="tooltip"]\').forEach((el) => {
                    new bootstrap.Tooltip(el);
                });
                table.insertBefore(newRow, table.querySelector("tr#table_row_button"));
                updateEntryMoves();
            }
            function deleteEntry(dataId, entryId, url, csrfToken) {
                $.post(url, {
                    adm_csrf_token: csrfToken
                    }, function(data) {
                        const returnData = (typeof data === "object") ? data : JSON.parse(data);
                        const returnStatus = returnData.status;

                        const row = document.getElementById(`${dataId}_option_${entryId}`);
                        // Handle responses
                        if (returnStatus === "used") {
                            if (!row) return;
                            const table = row.parentNode;
                            const countOptions = table.querySelectorAll(\'tr[id^="\' + dataId + \'_option_"]\').length;
                            if (row.querySelector(\'input[name$="[value]"]\').value.trim() === "" && row.querySelector(\'input[name$="[obsolete]"]\').value.trim() === "") {
                                // check if the row is the last one
                                if (countOptions > 1) {
                                    row.remove(); // Remove the row if the value is empty
                                }
                                return;
                            } else if (row.querySelector(\'input[name$="[value]"]\').value.trim() === "") {
                                // If the value is empty, just remove the row
                                if (countOptions <= 1) {
                                    return;
                                }
                            }
                            // Mark the entry as obsolete
                            row.querySelector(\'input[name$="[obsolete]"]\').value = 1;
                            // disable input fields
                            row.querySelector(\'input[name$="[value]"]\').disabled = true;
                            // change displayed delete/restore option
                            row.querySelector("#" + dataId + "_option_" + entryId + "_delete").style.display = "none";
                            row.querySelector("#" + dataId + "_option_" + entryId + "_restore").style.display = "inline";
                        } else if (returnStatus === "deleted") {
                            // delete the row if the entry was deleted
                            if (row) row.remove();
                        } else {
                            // unknown status, do nothing
                        }
                    }
                );
            }
            function restoreEntry(dataId, entryId) {
                const row = document.getElementById(dataId + "_option_" + entryId);
                if (row) {
                    row.querySelector(\'input[name$="[obsolete]"]\').value = 0; // Unmark as obsolete
                    // enable input fields
                    row.querySelector(\'input[name$="[value]"]\').disabled = false;
                    // change displayed delete option
                    row.querySelector("#" + dataId + "_option_" + entryId + "_delete").style.display = "inline"; // Show delete icon
                    row.querySelector("#" + dataId + "_option_" + entryId + "_restore").style.display = "none"; // Hide restore icon
                }
            }'
        );

        $this->addJavascriptCode('
            $("tbody.admidio-sortable").sortable({
                axis: "y",
                handle: ".handle",
                stop: function(event, ui) {
                    updateEntryMoves();
                }
            });
            $("tbody.admidio-sortable").on("click", ".admidio-entry-move", function() {
                var direction = $(this).data("direction");
                var target = $(this).data("target");

                if (direction === "UP") {
                    $("#"+target).prev().before($("#"+target));
                } else {
                    $("#"+target).next().after($("#"+target));
                }
                updateEntryMoves();
            });

            updateEntryMoves();
            ', true
        );
        $this->elements[$id] = $optionsAll;
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
     *                          will be the text of the alert box
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     */
    public function addRadioButton(string $id, string $label, array $values, array $options = array()): void
    {
        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'radio',
            'id' => $id,
            'label' => $label,
            'defaultValue' => '',
            'showNoValueButton' => false,
            'values' => $values
        ), $options));
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

        $this->elements[$id] = $optionsAll;
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
     *                          another array with the combination of attributes name and attributes value.
     *                        - **helpTextId** : A unique text id from the translation xml files that should be shown
     *                          e.g. SYS_DATA_CATEGORY_GLOBAL. The text will be shown under the form control.
     *                          If you need an additional parameter for the text you can add an array. The first entry
     *                          must be the unique text id and the second entry will be a parameter of the text id.
     *                        - **alertWarning** : Add a bootstrap info alert box after the select box. The value of this option
     *                          will be the text of the alert box
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     *                        - **autocomplete** : Set the html attribute autocomplete to support this feature
     *                          https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete
     * @throws Exception
     */
    public function addSelectBox(string $id, string $label, array $values, array $options = array()): void
    {
        global $gL10n;

        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'select',
            'id' => $id,
            'label' => $label,
            'defaultValue' => '',
            'showContextDependentFirstEntry' => true,
            'firstEntry' => '',
            'arrayKeyIsNotValue' => false,
            'multiselect' => false,
            'search' => false,
            'placeholder' => '',
            'maximumSelectionNumber' => 0,
            'valueAttributes' => ''
        ), $options));
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
        foreach ($values as $arrayKey => $arrayValue) {
            if (is_array($arrayValue)) {
                if (array_key_exists(2, $arrayValue)) {
                    $valuesArray[] = array(
                        'id' => ($optionsAll['arrayKeyIsNotValue'] ? $arrayValue[1] : $arrayValue[0]),
                        'value' => Language::translateIfTranslationStrId($arrayValue[1]),
                        'group' => Language::translateIfTranslationStrId($arrayValue[2])
                    );
                } else {
                    $valuesArray[] = array(
                        'id' => ($optionsAll['arrayKeyIsNotValue'] ? $arrayValue[1] : $arrayValue[0]),
                        'value' => Language::translateIfTranslationStrId($arrayValue[1])
                    );
                }
            } else {
                $valuesArray[] = array(
                    'id' => ($optionsAll['arrayKeyIsNotValue'] ? $arrayValue : $arrayKey),
                    'value' => Language::translateIfTranslationStrId($arrayValue));
            }
        }

        // if special value attributes are set then add them to the values array
        if (is_array($optionsAll['valueAttributes']) && count($optionsAll['valueAttributes']) > 0) {
            foreach ($valuesArray as &$valueArray) {
                if (isset($optionsAll['valueAttributes'][$valueArray['id']])) {
                    foreach ($optionsAll['valueAttributes'][$valueArray['id']] as $key => $value) {
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
                // if there is only one entry and a required field than select this entry
                if (count($values) === 1) {
                    $optionsAll['defaultValue'] = array_key_first($values);
                } else {
                    array_unshift($valuesArray, array('id' => '', 'value' => '- ' . $gL10n->get('SYS_PLEASE_CHOOSE') . ' -'));
                }
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

            // if multiselect is enabled and it is not a required field then it is possible that the user has not selected any entry.
            if ($optionsAll['multiselect'] && $optionsAll['property'] !== self::FIELD_REQUIRED) {
                // add a javascript code to select an empty entry when no entry is selected
                $javascriptCode .= '
                    // if the user unselects all entries then we add an empty entry to the select box and select it.
                    $("#' . $id . '").on("select2:unselect", function(e) {
                        var $sel = $(this);
                        var current = $sel.val(); // Array oder null
                        if (current === null || current.length === 0) {
                            if ($sel.find("option[value=\'\']").length === 0) {
                                $sel.append(\'<option value="" selected></option>\');
                                $sel.trigger("change.select2");
                            } else {
                                $sel.val("").trigger("change.select2");
                            }
                        }
                    });
                    
                    // if the user selects an entry and the empty entry is selected then remove the empty entry
                    $("#' . $id . '").on("select2:select", function(e) {
                        var $sel = $(this);
                        var current = $sel.val() || [];
                        if (Array.isArray(current) && current.length > 1 && current.indexOf("") !== -1) {
                            $sel.find(\'option[value=""]\').remove();
                            var newVals = current.filter(function(v) {
                                return v !== "";
                            });
                            $sel.val(newVals).trigger("change.select2");
                        }
                    });';

            }

            if (is_array($optionsAll['defaultValue']) && count($optionsAll['defaultValue']) > 0) {
                // add default values to multi select
                $htmlDefaultValues = '"' . implode('", "', $optionsAll['defaultValue']) . '"';

                $javascriptCode .= ' $("#' . $id . '").val([' . $htmlDefaultValues . ']).trigger("change.select2");';
            } elseif (count($values) === 1 && $optionsAll['property'] === self::FIELD_REQUIRED) {
                // if there is only one entry and a required field than select this entry
                $javascriptCode .= ' $("#' . $id . '").val("' . $values[0][0] . '").trigger("change.select2");';
            }

            // if a htmlPage object was set then add code to the page, otherwise to the current string
            if (isset($this->htmlPage)) {
                $this->htmlPage->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/css/select2.css');
                $this->htmlPage->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2-bootstrap-theme/select2-bootstrap-5-theme.css');
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/select2.js');
                $this->htmlPage->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/i18n/' . $gL10n->getLanguageLibs() . '.js');
            }
            $this->addJavascriptCode($javascriptCode, true);
        }

        if (isset($optionsAll['autocomplete'])) {
            $attributes['autocomplete'] = $optionsAll['autocomplete'];
        }

        $optionsAll["values"] = $valuesArray;
        $optionsAll["attributes"] = $attributes;

        // required field should not be highlighted so set it to a default field
        if (!$this->showRequiredFields && $optionsAll['property'] === self::FIELD_REQUIRED) {
            $optionsAll['property'] = self::FIELD_DEFAULT;
        }

        $this->elements[$id] = $optionsAll;
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
     *                                 will be the text of the alert box
     *                               - **icon** : An icon can be set. This will be placed in front of the label.
     *                               - **class** : An additional css classname. The class **admSelectbox**
     *                                 is set as default and need not set with this parameter.
     * **Code examples**
     * ```
     * // create a selectbox with all profile fields of a specific category
     * $sql = 'SELECT usf_id, usf_name FROM '.TBL_USER_FIELDS.' WHERE usf_cat_id = 4711'
     * $form = new FormPresenter('simple-form', 'adm_next_page.php');
     * $form->addSelectBoxFromSql('admProfileFieldsBox', $gL10n->get('SYS_FIELDS'), $gDb, $sql, array('defaultValue' => $gL10n->get('SYS_SURNAME'), 'showContextDependentFirstEntry' => true));
     * $form->show();
     * ```
     * @throws Exception
     */
    public function addSelectBoxFromSql(string $id, string $label, Database $database, array|string $sql, array $options = array()): void
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
                $row[2] = Language::translateIfTranslationStrId((string)$row[2]);

                $selectBoxEntries[] = array($row[0], (string)$row[1], $row[2]);
            } else {
                $selectBoxEntries[$row[0]] = (string)$row[1];
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
     *                          will be the text of the alert box
     *                        - **icon** : An icon can be set. This will be placed in front of the label.
     *                        - **class** : An additional css classname. The class **admSelectbox**
     *                          is set as default and need not set with this parameter.
     * @throws Exception
     */
    public function addSelectBoxFromXml(string $id, string $label, string $xmlFile, string $xmlValueTag, string $xmlViewTag, array $options = array()): void
    {
        $selectBoxEntries = array();

        try {
            $xmlRootNode = new SimpleXMLElement($xmlFile, 0, true);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

        /**
         * @var SimpleXMLElement $xmlChildNode
         */
        foreach ($xmlRootNode->children() as $xmlChildNode) {
            $key = '';
            $value = '';

            /**
             * @var SimpleXMLElement $xmlChildNode
             */
            foreach ($xmlChildNode->children() as $xmlChildChildNode) {
                if ($xmlChildChildNode->getName() === $xmlValueTag) {
                    $key = (string)$xmlChildChildNode;
                }
                if ($xmlChildChildNode->getName() === $xmlViewTag) {
                    $value = (string)$xmlChildChildNode;
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
     * @param Database $database An Admidio database object that contains a valid connection to a database
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
     *                                   will be the text of the alert box
     *                                 - **icon** : An icon can be set. This will be placed in front of the label.
     *                                 - **class** : An additional css classname. The class **admSelectbox**
     *                                   is set as default and need not set with this parameter.
     * @throws Exception
     */
    public function addSelectBoxForCategories(string $id, string $label, Database $database, string $categoryType, string $selectBoxModus, array $options = array()): void
    {
        global $gCurrentOrganization, $gCurrentUser, $gL10n;

        $optionsAll = $this->buildOptionsArray(array_replace(array(
            'type' => 'select',
            'id' => $id,
            'label' => $label,
            'defaultValue' => '',
            'arrayKeyIsNotValue' => false,
            'showContextDependentFirstEntry' => true,
            'multiselect' => false,
            'showSystemCategory' => true
        ), $options));

        if ($selectBoxModus === self::SELECT_BOX_MODUS_EDIT && $gCurrentOrganization->countAllRecords() > 1) {
            $optionsAll['alertWarning'] = $gL10n->get('SYS_ALL_ORGANIZATIONS_DESC', array(implode(', ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true))));

            $this->addJavascriptCode(
                '
                $("#' . $id . '").change(function() {
                    if($("option:selected", this).attr("data-global") == 1) {
                        $("#' . $id . '_alert").show("slow");
                    } else {
                        $("#' . $id . '_alert").hide();
                    }
                });
                $("#' . $id . '").trigger("change");',
                true
            );
        }

        $sqlTables = '';
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
                    $row['cat_name'] .= ' (' . $gL10n->get('SYS_ALL_ORGANIZATIONS') . ')';
                }
                $optionsAll['valueAttributes'][$row['cat_uuid']] = array('data-global' => 1);
            } else {
                $optionsAll['valueAttributes'][$row['cat_uuid']] = array('data-global' => 0);

            }
            // if text is a translation-id then translate it
            $categoriesArray[] = array($row['cat_uuid'], Language::translateIfTranslationStrId($row['cat_name']));
        }

        // now call method to create select box from array
        $this->addSelectBox($id, $label, $categoriesArray, $optionsAll);
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
     */
    public function addSubmitButton(string $id, string $text, array $options = array()): void
    {
        $options['type'] = 'submit';

        if (!isset($options['class'])) {
            $options['class'] = '';
        }
        $options['class'] .= ' btn-primary';
        if ($this->type !== 'navbar') {
            $options['class'] .= ' admidio-margin-bottom';
        }
        if (!isset($options['link'])) {
            $options['link'] = '';
        }

        // now add button to form
        $this->addButton($id, $text, $options);
    }

    /**
     * This method add the form attributes and all form elements to the PagePresenter object. Also, the
     * template file of the form is set to the page. After this method is called the whole form
     * could be rendered through the PagePresenter.
     * @param bool $ajaxSubmit If set to true the form will be submitted by an AJAX call and
     *                         the result will be presented inline. If set to false a default
     *                         form submit will be done and a new page will be called.
     * @return void
     * @throws Exception
     */
    public function addToHtmlPage(bool $ajaxSubmit = true): void
    {
        try {
            if (isset($this->htmlPage)) {
                if ($this->type === 'navbar') {
                    $this->htmlPage->assignSmartyVariable('navbarID', 'navbar_' . $this->id);
                } elseif ($ajaxSubmit) {
                    $this->htmlPage->addJavascript('
                        $("#' . $this->id . '").submit(formSubmit);
                    ', true);
                }
                $this->htmlPage->assignSmartyVariable('formType', $this->type);
                $this->htmlPage->assignSmartyVariable('attributes', $this->attributes);
                $this->htmlPage->assignSmartyVariable('elements', $this->elements);
                $this->htmlPage->assignSmartyVariable('hasRequiredFields', ($this->flagRequiredFields && $this->showRequiredFields ? true : false));
                $this->htmlPage->addHtmlByTemplate($this->template);
            }
        } catch (\Smarty\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }


    /**
     * This method add the form attributes and all form elements to the PagePresenter object. Also, the
     * template file of the form is set to the page. After this method is called the whole form
     * could be rendered through the PagePresenter.
     * @param Smarty $smarty
     * @return void
     */
    public function addToSmarty(Smarty $smarty): void
    {
        global $gL10n, $gSettingsManager;

        $smarty->assign('urlAdmidio', ADMIDIO_URL);
        $smarty->assign('l10n', $gL10n);
        $smarty->assign('settings', $gSettingsManager);
        $smarty->assign('formType', $this->type);
        $smarty->assign('attributes', $this->attributes);
        $smarty->assign('elements', $this->elements);
        $smarty->assign('javascript', $this->javascript);
        $smarty->assign('hasRequiredFields', ($this->flagRequiredFields && $this->showRequiredFields ? true : false));
    }

    /**
     * This method returns the attributes array.
     * @return array Returns all attributes of the form.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * This method returns the elements array.
     * @return array Returns all elements of the form.
     */
    public function getElements(): array
    {
        return $this->elements;
    }


    /**
     * Method merge the default options of all fields with the initial options set for the
     * specific field.
     * @param array $options Array with all initial options for the field.
     * @return array Array with initial options and default options of the field.
     */
    protected function buildOptionsArray(array $options): array
    {
        $optionsDefault = array(
            'property' => self::FIELD_DEFAULT,
            'type' => '',
            'data-admidio' => '',
            'id' => 'admidio_form_field_' . (count($this->elements) + 1),
            'label' => '',
            'value' => '',
            'helpTextId' => '',
            'icon' => '',
            'class' => '',
            'alertWarning' => '',
        );
        return array_replace($optionsDefault, $options);
    }

    /**
     * Add a small help icon to the form at the current element which shows the translated text of the
     * text-id or an individual text on mouseover. The title will be note if it's a text-id or
     * description if it's an individual text.
     * @param string $string A text that should be shown or a unique text id from the translation xml files
     *                          that should be shown e.g. SYS_DATA_CATEGORY_GLOBAL.
     * @param array $parameter If you need an additional parameters for the text you can set this parameter values within an array.
     * @return string Return a html snippet that contains a help icon with a link to a popup box that shows the message.
     * @throws Exception
     */
    public static function getHelpTextIcon(string $string, array $parameter = array()): string
    {
        global $gL10n;

        $html = '';

        if (strlen($string) > 0) {
            if (Language::isTranslationStringId($string)) {
                $text = $gL10n->get($string, $parameter);
            } else {
                $text = $string;
            }

            $html = '<i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
            data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
            data-bs-content="' . SecurityUtils::encodeHTML($text) . '"></i>';
        }
        return $html;
    }

    /**
     * Returns a CSRF token from the session. If no CSRF token exists a new one will be
     * generated and stored within the session. The next call of the method will then
     * return the existing token. The CSRF token has 30 characters. A new token could
     * be forced by the parameter **$newToken**
     * @param bool $newToken If set to true, always a new token will be generated.
     * @return string Returns the CSRF token
     * @throws \Exception
     */
    public function getCsrfToken(bool $newToken = false): string
    {
        if ($this->csrfToken === '' || $newToken) {
            $this->csrfToken = SecurityUtils::getRandomString(30);
        }

        return $this->csrfToken;
    }

    /**
     * Validates the input of a form against the form definition. Therefore, this method needs
     * the $_POST variable as parameter $fieldValues. An exception is thrown if a required
     * form field doesn't have a value in the $fieldValues array. EEmails and urls must have a
     * valid format. The method will return an array with all form input with sanitized html
     * from editor fields and html free content of all other fields.
     * @param array $fieldValues Array with field name as key and field value as array value.
     * @return array Returns an array with all valid fields and their values of this form
     * @throws Exception
     */
    public function validate(array $fieldValues): array
    {
        $validFieldValues = array();

        if (isset($fieldValues['adm_csrf_token'])) {
            // check the CSRF token of the form against the session token
            if ($fieldValues['adm_csrf_token'] !== $this->csrfToken) {
                throw new Exception('Invalid or missing CSRF token!');
            }
            unset($fieldValues['adm_csrf_token']);
        } else {
            throw new Exception('No CSRF token provided.');
        }

        foreach ($fieldValues as $key => $value) {
            // security check if the form payload includes unexpected fields
            if (!array_key_exists($key, $this->elements)) {
                throw new Exception('Invalid payload of the form!');
            }
        }

        foreach ($this->elements as $element) {
            // check if element is required and given value in array $fieldValues is empty
            if (isset($element['property']) && $element['property'] === $this::FIELD_REQUIRED) {
                if (isset($fieldValues[$element['id']])) {
                    if ((is_array($fieldValues[$element['id']]) && count($fieldValues[$element['id']]) === 0)
                        || (!is_array($fieldValues[$element['id']]) && (string)$fieldValues[$element['id']] === '')) {
                        throw new Exception('SYS_FIELD_EMPTY', array($element['label']));
                    }
                } elseif ($element['type'] === 'file') {
                    // file field has no POST variable but the FILES array should be filled
                    if (count($_FILES) === 0 || strlen($_FILES['userfile']['tmp_name'][0]) === 0) {
                        throw new Exception('SYS_FIELD_EMPTY', array($element['label']));
                    }
                } else {
                    throw new Exception('SYS_FIELD_EMPTY', array($element['label']));
                }
            } elseif (isset($element['property']) && $element['property'] === $this::FIELD_DISABLED) {
                // no value should be set if a field is marked as disabled
                if (isset($fieldValues[$element['id']])) {
                    unset($fieldValues[$element['id']]);
                }
            }

            // if element is a checkbox than add entry to $fieldValues if checkbox is unchecked
            if ($element['type'] === 'checkbox' && !isset($fieldValues[$element['id']])) {
                $validFieldValues[$element['id']] = "0";
            }

            if (isset($fieldValues[$element['id']])) {
                // remove html from every input value
                $validFieldValues[$element['id']] = StringUtils::strStripTags($fieldValues[$element['id']]);

                // check value depending on the field type
                if (!is_array($fieldValues[$element['id']]) && strlen($fieldValues[$element['id']]) > 0) {
                    switch ($element['type']) {
                        case 'captcha':
                            $this->validateCaptcha($fieldValues[$element['id']]);
                            break;
                        case 'editor':
                            // check html string vor invalid tags and scripts
                            $config = HTMLPurifier_Config::createDefault();
                            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
                            $config->set('Attr.AllowedFrameTargets', array('_blank', '_top', '_self', '_parent'));
                            $config->set('Cache.SerializerPath', ADMIDIO_PATH . FOLDER_DATA . '/templates');

                            $filter = new HTMLPurifier($config);
                            $validFieldValues[$element['id']] = $filter->purify($fieldValues[$element['id']]);
                            break;
                        case 'email':
                            if (!StringUtils::strValidCharacters($fieldValues[$element['id']], 'email')) {
                                throw new Exception('SYS_EMAIL_INVALID', array($element['label']));
                            }
                            break;
                        case 'number':
                            if (!is_numeric($fieldValues[$element['id']]) || $fieldValues[$element['id']] < 0) {
                                throw new Exception('SYS_FIELD_INVALID_INPUT', array($element['label']));
                            }
                            break;
                        case 'url':
                            if (!StringUtils::strValidCharacters($fieldValues[$element['id']], 'url')) {
                                throw new Exception('SYS_URL_INVALID_CHAR', array($element['label']));
                            }
                            break;
                    }
                }
            }
        }
        return $validFieldValues;
    }

    /**
     * Checks if the value of the captcha input matches with the captcha image.
     * @param string $value Value of the captcha input field.
     * @return true Returns **true** if the value matches the captcha image.
     *              Otherwise, throw an exception SYS_CAPTCHA_CODE_INVALID.
     * @throws Exception SYS_CAPTCHA_CALC_CODE_INVALID, SYS_CAPTCHA_CODE_INVALID
     */
    public function validateCaptcha(string $value): bool
    {
        global $gSettingsManager;

        $secureImage = new Securimage();

        if ($secureImage->check($value)) {
            return true;
        }

        if ($gSettingsManager->getString('captcha_type') === 'calc') {
            throw new Exception('SYS_CAPTCHA_CALC_CODE_INVALID');
        }

        throw new Exception('SYS_CAPTCHA_CODE_INVALID');
    }
}
