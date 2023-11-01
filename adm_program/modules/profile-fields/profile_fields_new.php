<?php
/**
 ***********************************************************************************************
 * Create and edit profile fields
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * usf_uuid : UUID of the profile field that should be edited
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUsfUuid = admFuncVariableIsValid($_GET, 'usf_uuid', 'string');

// only authorized users can edit the profile fields
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// benutzerdefiniertes Feldobjekt anlegen
$userField = new TableUserField($gDb);

if ($getUsfUuid !== '') {
    $userField->readDataByUuid($getUsfUuid);

    $headline = $gL10n->get('ORG_EDIT_PROFILE_FIELD');

    // hidden must be 0, if the flag should be set
    if ($userField->getValue('usf_hidden') == 1) {
        $userField->setValue('usf_hidden', 0);
    } else {
        $userField->setValue('usf_hidden', 1);
    }

    // Pruefung, ob das Feld zur aktuellen Organisation gehoert
    if ($userField->getValue('cat_org_id') > 0
    && (int) $userField->getValue('cat_org_id') !== $gCurrentOrgId) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
} else {
    $headline = $gL10n->get('ORG_CREATE_PROFILE_FIELD');

    // default values for a new field
    $userField->setValue('usf_hidden', 1);
    $userField->setValue('usf_registration', 1);
}

$gNavigation->addUrl(CURRENT_URL, $headline);

if (isset($_SESSION['fields_request'])) {
    // hidden must be 0, if the flag should be set
    if ($_SESSION['fields_request']['usf_hidden'] == 1) {
        $_SESSION['fields_request']['usf_hidden'] = 0;
    } else {
        $_SESSION['fields_request']['usf_hidden'] = 1;
    }

    // due to incorrect input, the user has returned to this form
    // Now write the previously entered content into the object
    $userFieldDescription = admFuncVariableIsValid($_SESSION['fields_request'], 'usf_description', 'html');
    $userField->setArray(SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['fields_request'])));
    $userField->setValue('usf_description', $userFieldDescription);
    unset($_SESSION['fields_request']);
}

// create html page object
$page = new HtmlPage('admidio-profile-fields-edit', $headline);

$page->addJavascript(
    '
    $("#usf_type").change(function() {
        if ($("#usf_type").val() === "DROPDOWN" || $("#usf_type").val() === "RADIO_BUTTON") {
            $("#usf_value_list_group").show("slow");
            $("#usf_value_list").attr("required", "required");
        } else {
            $("#usf_value_list").removeAttr("required");
            $("#usf_value_list_group").hide();
        }
    });
    $("#usf_type").trigger("change");',
    true
);

// show form
$form = new HtmlForm('profile_fields_edit_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile-fields/profile_fields_function.php', array('usf_uuid' => $getUsfUuid, 'mode' => '1')), $page);
$form->openGroupBox('gb_designation', $gL10n->get('SYS_DESIGNATION'));
if ($userField->getValue('usf_system') == 1) {
    $form->addInput(
        'usf_name',
        $gL10n->get('SYS_NAME'),
        htmlentities($userField->getValue('usf_name', 'database'), ENT_QUOTES),
        array('maxLength' => 100, 'property' => HtmlForm::FIELD_DISABLED)
    );
} else {
    $form->addInput(
        'usf_name',
        $gL10n->get('SYS_NAME'),
        htmlentities($userField->getValue('usf_name', 'database'), ENT_QUOTES),
        array('maxLength' => 100, 'property' => HtmlForm::FIELD_REQUIRED)
    );
}

$usfNameIntern = $userField->getValue('usf_name_intern');
// show internal field name for information
if ($getUsfUuid !== '') {
    $form->addInput(
        'usf_name_intern',
        $gL10n->get('SYS_INTERNAL_NAME'),
        $usfNameIntern,
        array('maxLength' => 100, 'property' => HtmlForm::FIELD_DISABLED, 'helpTextIdLabel' => 'SYS_INTERNAL_NAME_DESC')
    );
}

if ($userField->getValue('usf_system') == 1) {
    $form->addInput(
        'usf_cat_id',
        $gL10n->get('SYS_CATEGORY'),
        $userField->getValue('cat_name'),
        array('maxLength' => 100, 'property' => HtmlForm::FIELD_DISABLED)
    );
} else {
    $form->addSelectBoxForCategories(
        'usf_cat_id',
        $gL10n->get('SYS_CATEGORY'),
        $gDb,
        'USF',
        HtmlForm::SELECT_BOX_MODUS_EDIT,
        array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $userField->getValue('cat_uuid'))
    );
}
$form->closeGroupBox();
$form->openGroupBox('gb_properties', $gL10n->get('SYS_PROPERTIES'));
$userFieldText = array(
    'CHECKBOX'     => $gL10n->get('SYS_CHECKBOX'),
    'DATE'         => $gL10n->get('SYS_DATE'),
    'DECIMAL'      => $gL10n->get('SYS_DECIMAL_NUMBER'),
    'DROPDOWN'     => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
    'EMAIL'        => $gL10n->get('SYS_EMAIL'),
    'NUMBER'       => $gL10n->get('SYS_NUMBER'),
    'PHONE'        => $gL10n->get('SYS_PHONE'),
    'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
    'TEXT'         => $gL10n->get('SYS_TEXT').' (100 '.$gL10n->get('SYS_CHARACTERS').')',
    'TEXT_BIG'     => $gL10n->get('SYS_TEXT').' (4000 '.$gL10n->get('SYS_CHARACTERS').')',
    'URL'          => $gL10n->get('SYS_URL')
);
asort($userFieldText);

if ($userField->getValue('usf_system') == 1) {
    // for system fields, the data type may no longer be changed
    $form->addInput(
        'usf_type',
        $gL10n->get('ORG_DATATYPE'),
        $userFieldText[$userField->getValue('usf_type')],
        array('maxLength' => 30, 'property' => HtmlForm::FIELD_DISABLED)
    );
} else {
    // if it's not a system field the user must select the data type
    $form->addSelectBox(
        'usf_type',
        $gL10n->get('ORG_DATATYPE'),
        $userFieldText,
        array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $userField->getValue('usf_type'))
    );
}
$form->addMultilineTextInput(
    'usf_value_list',
    $gL10n->get('ORG_VALUE_LIST'),
    htmlentities($userField->getValue('usf_value_list', 'database'), ENT_QUOTES),
    6,
    array('property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdLabel' => 'ORG_VALUE_LIST_DESC')
);
$mandatoryFieldValues = array(0 => 'SYS_NO', 1 => 'SYS_YES', 2 => 'SYS_ONLY_AT_REGISTRATION_AND_OWN_PROFILE', 3 => 'SYS_NOT_AT_REGISTRATION');
if ($usfNameIntern === 'LAST_NAME' || $usfNameIntern === 'FIRST_NAME') {
    $form->addInput(
        'usf_required_input',
        $gL10n->get('SYS_REQUIRED_INPUT'),
        $gL10n->get($mandatoryFieldValues[$userField->getValue('usf_required_input')]),
        array('maxLength' => 50, 'property' => HtmlForm::FIELD_DISABLED)
    );
} else {
    $form->addSelectBox(
        'usf_required_input',
        $gL10n->get('SYS_REQUIRED_INPUT'),
        $mandatoryFieldValues,
        array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $userField->getValue('usf_required_input'))
    );
}
$form->addCheckbox(
    'usf_hidden',
    $gL10n->get('ORG_FIELD_NOT_HIDDEN'),
    (bool) $userField->getValue('usf_hidden'),
    array('helpTextIdLabel' => 'ORG_FIELD_HIDDEN_DESC', 'icon' => 'fa-eye')
);
$form->addCheckbox(
    'usf_disabled',
    $gL10n->get('ORG_FIELD_DISABLED', array($gL10n->get('SYS_RIGHT_EDIT_USER'))),
    (bool) $userField->getValue('usf_disabled'),
    array('helpTextIdLabel' => 'ORG_FIELD_DISABLED_DESC', 'icon' => 'fa-key')
);

if ($usfNameIntern === 'LAST_NAME' || $usfNameIntern === 'FIRST_NAME' || $usfNameIntern === 'EMAIL') {
    $form->addCheckbox(
        'usf_registration',
        $gL10n->get('ORG_FIELD_REGISTRATION'),
        (bool) $userField->getValue('usf_registration'),
        array('property' => HtmlForm::FIELD_DISABLED, 'icon' => 'fa-address-card')
    );
} else {
    $form->addCheckbox(
        'usf_registration',
        $gL10n->get('ORG_FIELD_REGISTRATION'),
        (bool) $userField->getValue('usf_registration'),
        array('icon' => 'fa-address-card')
    );
}
$form->addInput(
    'usf_default_value',
    $gL10n->get('SYS_DEFAULT_VALUE'),
    $userField->getValue('usf_default_value'),
    array('helpTextIdLabel' => 'SYS_DEFAULT_VALUE_DESC')
);
$form->addInput(
    'usf_regex',
    $gL10n->get('SYS_REGULAR_EXPRESSION'),
    $userField->getValue('usf_regex'),
    array('helpTextIdLabel' => 'SYS_REGULAR_EXPRESSION_DESC')
);
$form->addInput(
    'usf_icon',
    $gL10n->get('SYS_ICON'),
    htmlentities($userField->getValue('usf_icon', 'database'), ENT_QUOTES),
    array(
        'maxLength' => 100,
        'helpTextIdLabel' => $gL10n->get('SYS_FONT_AWESOME_DESC', array('<a href="https://fontawesome.com/icons?d=gallery&s=brands,solid&m=free" target="_blank">', '</a>'))
    )
);
$form->addInput(
    'usf_url',
    $gL10n->get('SYS_URL'),
    $userField->getValue('usf_url'),
    array('maxLength' => 2000, 'helpTextIdLabel' => 'ORG_FIELD_URL_DESC')
);
$form->closeGroupBox();
$form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'), 'admidio-panel-editor');
$form->addEditor('usf_description', '', $userField->getValue('usf_description'), array('height' => '200px'));
$form->addDescription($gL10n->get('SYS_DESCRIPTION_POPOVER_DESC'));
$form->addCheckbox(
    'usf_description_inline',
    $gL10n->get('SYS_DESCRIPTION_INLINE_DESC'),
    (bool) $userField->getValue('usf_description_inline')
);
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'fa-check'));
$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $userField->getValue('usf_usr_id_create'),
    $userField->getValue('usf_timestamp_create'),
    (int) $userField->getValue('usf_usr_id_change'),
    $userField->getValue('usf_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
