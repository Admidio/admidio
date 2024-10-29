<?php
/**
 ***********************************************************************************************
 * Create and edit profile fields
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * usf_uuid : UUID of the profile field that should be edited
 ***********************************************************************************************
 */
use Admidio\Exception;
use Admidio\UserInterface\Form;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getUsfUuid = admFuncVariableIsValid($_GET, 'usf_uuid', 'uuid');

    // only authorized users can edit the profile fields
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // Create user-defined field object
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

        // Check whether the field belongs to the current organization
        if ($userField->getValue('cat_org_id') > 0
            && (int)$userField->getValue('cat_org_id') !== $gCurrentOrgId) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    } else {
        $headline = $gL10n->get('ORG_CREATE_PROFILE_FIELD');

        // default values for a new field
        $userField->setValue('usf_hidden', 1);
        $userField->setValue('usf_registration', 1);
    }

    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = new HtmlPage('admidio-profile-fields-edit', $headline);

    $page->addJavascript('
        $("#usf_type").change(function() {
            $classes = $("#suf_value_list_group").attr("class");
            if ($("#usf_type").val() === "DROPDOWN" || $("#usf_type").val() === "RADIO_BUTTON") {
                $("#usf_value_list").attr("required", "required");
                $("#usf_value_list_group").addClass("admidio-form-group-required");
                $("#usf_value_list_group").show("slow");
            } else {
                $("#usf_value_list").removeAttr("required");
                $("#usf_value_list_group").removeClass("admidio-form-group-required");
                $("#usf_value_list_group").hide();
            }
        });
        $("#usf_type").trigger("change");',
        true
    );

    // show form
    $form = new Form(
        'adm_profile_fields_edit_form',
        'modules/profile-fields.edit.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile-fields/profile_fields_function.php', array('uuid' => $getUsfUuid, 'mode' => 'edit')),
        $page
    );

    if ($userField->getValue('usf_system') == 1) {
        $form->addInput(
            'usf_name',
            $gL10n->get('SYS_NAME'),
            htmlentities($userField->getValue('usf_name', 'database'), ENT_QUOTES),
            array('maxLength' => 100, 'property' => Form::FIELD_DISABLED)
        );
    } else {
        $form->addInput(
            'usf_name',
            $gL10n->get('SYS_NAME'),
            htmlentities($userField->getValue('usf_name', 'database'), ENT_QUOTES),
            array('maxLength' => 100, 'property' => Form::FIELD_REQUIRED)
        );
    }

    $usfNameIntern = $userField->getValue('usf_name_intern');
    // show internal field name for information
    if ($getUsfUuid !== '') {
        $form->addInput(
            'usf_name_intern',
            $gL10n->get('SYS_INTERNAL_NAME'),
            $usfNameIntern,
            array('maxLength' => 100, 'property' => Form::FIELD_DISABLED, 'helpTextId' => 'SYS_INTERNAL_NAME_DESC')
        );
    }

    if ($userField->getValue('usf_system') == 1) {
        $form->addInput(
            'usf_cat_id',
            $gL10n->get('SYS_CATEGORY'),
            $userField->getValue('cat_name'),
            array('maxLength' => 100, 'property' => Form::FIELD_DISABLED)
        );
    } else {
        $form->addSelectBoxForCategories(
            'usf_cat_id',
            $gL10n->get('SYS_CATEGORY'),
            $gDb,
            'USF',
            Form::SELECT_BOX_MODUS_EDIT,
            array('property' => Form::FIELD_REQUIRED, 'defaultValue' => $userField->getValue('cat_uuid'))
        );
    }
    $userFieldText = array(
        'CHECKBOX' => $gL10n->get('SYS_CHECKBOX'),
        'DATE' => $gL10n->get('SYS_DATE'),
        'DECIMAL' => $gL10n->get('SYS_DECIMAL_NUMBER'),
        'DROPDOWN' => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
        'EMAIL' => $gL10n->get('SYS_EMAIL'),
        'NUMBER' => $gL10n->get('SYS_NUMBER'),
        'PHONE' => $gL10n->get('SYS_PHONE'),
        'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
        'TEXT' => $gL10n->get('SYS_TEXT') . ' (100 ' . $gL10n->get('SYS_CHARACTERS') . ')',
        'TEXT_BIG' => $gL10n->get('SYS_TEXT') . ' (4000 ' . $gL10n->get('SYS_CHARACTERS') . ')',
        'URL' => $gL10n->get('SYS_URL')
    );
    asort($userFieldText);

    if ($userField->getValue('usf_system') == 1) {
        // for system fields, the data type may no longer be changed
        $form->addInput(
            'usf_type',
            $gL10n->get('ORG_DATATYPE'),
            $userFieldText[$userField->getValue('usf_type')],
            array('maxLength' => 30, 'property' => Form::FIELD_DISABLED)
        );
    } else {
        // if it's not a system field the user must select the data type
        $form->addSelectBox(
            'usf_type',
            $gL10n->get('ORG_DATATYPE'),
            $userFieldText,
            array('property' => Form::FIELD_REQUIRED, 'defaultValue' => $userField->getValue('usf_type'))
        );
    }
    $form->addMultilineTextInput(
        'usf_value_list',
        $gL10n->get('SYS_VALUE_LIST'),
        htmlentities($userField->getValue('usf_value_list', 'database'), ENT_QUOTES),
        6,
        array('helpTextId' => array('SYS_VALUE_LIST_DESC', array('<a href="https://icons.bootstrap.com">', '</a>')))
    );
    $mandatoryFieldValues = array(0 => 'SYS_NO', 1 => 'SYS_YES', 2 => 'SYS_ONLY_AT_REGISTRATION_AND_OWN_PROFILE', 3 => 'SYS_NOT_AT_REGISTRATION');
    if (in_array($usfNameIntern, array('LAST_NAME', 'FIRST_NAME'))) {
        $form->addInput(
            'usf_required_input',
            $gL10n->get('SYS_REQUIRED_INPUT'),
            $gL10n->get($mandatoryFieldValues[$userField->getValue('usf_required_input')]),
            array('maxLength' => 50, 'property' => Form::FIELD_DISABLED)
        );
    } else {
        $form->addSelectBox(
            'usf_required_input',
            $gL10n->get('SYS_REQUIRED_INPUT'),
            $mandatoryFieldValues,
            array('property' => Form::FIELD_REQUIRED, 'defaultValue' => $userField->getValue('usf_required_input'))
        );
    }
    $form->addCheckbox(
        'usf_hidden',
        $gL10n->get('ORG_FIELD_NOT_HIDDEN'),
        (bool)$userField->getValue('usf_hidden'),
        array('helpTextId' => 'ORG_FIELD_HIDDEN_DESC', 'icon' => 'bi-eye-fill')
    );
    $form->addCheckbox(
        'usf_disabled',
        $gL10n->get('ORG_FIELD_DISABLED', array($gL10n->get('SYS_RIGHT_EDIT_USER'))),
        (bool)$userField->getValue('usf_disabled'),
        array('helpTextId' => 'ORG_FIELD_DISABLED_DESC', 'icon' => 'bi-key-fill')
    );

    if (in_array($usfNameIntern, array('LAST_NAME', 'FIRST_NAME', 'EMAIL'))) {
        $form->addCheckbox(
            'usf_registration',
            $gL10n->get('ORG_FIELD_REGISTRATION'),
            (bool)$userField->getValue('usf_registration'),
            array('property' => Form::FIELD_DISABLED, 'icon' => 'bi-card-checklist')
        );
    } else {
        $form->addCheckbox(
            'usf_registration',
            $gL10n->get('ORG_FIELD_REGISTRATION'),
            (bool)$userField->getValue('usf_registration'),
            array('icon' => 'bi-card-checklist')
        );
    }
    $form->addInput(
        'usf_default_value',
        $gL10n->get('SYS_DEFAULT_VALUE'),
        $userField->getValue('usf_default_value'),
        array('helpTextId' => 'SYS_DEFAULT_VALUE_DESC')
    );
    $form->addInput(
        'usf_regex',
        $gL10n->get('SYS_REGULAR_EXPRESSION'),
        $userField->getValue('usf_regex'),
        array('helpTextId' => 'SYS_REGULAR_EXPRESSION_DESC')
    );
    $form->addInput(
        'usf_icon',
        $gL10n->get('SYS_ICON'),
        $userField->getValue('usf_icon', 'database'),
        array(
            'maxLength' => 100,
            'helpTextId' => $gL10n->get('SYS_ICON_FONT_DESC', array('<a href="https://icons.getbootstrap.com/" target="_blank">', '</a>'))
        )
    );
    $form->addInput(
        'usf_url',
        $gL10n->get('SYS_URL'),
        $userField->getValue('usf_url'),
        array('type' => 'url', 'maxLength' => 2000, 'helpTextId' => 'ORG_FIELD_URL_DESC')
    );
    $form->addEditor(
        'usf_description',
        $gL10n->get('SYS_DESCRIPTION'),
        $userField->getValue('usf_description'),
        array('toolbar' => 'AdmidioComments'));
    $form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

    $page->assignSmartyVariable('fieldNameIntern', $usfNameIntern);
    $page->assignSmartyVariable('systemField', $userField->getValue('usf_system'));
    $page->assignSmartyVariable('nameUserCreated', $userField->getNameOfCreatingUser());
    $page->assignSmartyVariable('timestampUserCreated', $userField->getValue('ann_timestamp_create'));
    $page->assignSmartyVariable('nameLastUserEdited', $userField->getNameOfLastEditingUser());
    $page->assignSmartyVariable('timestampLastUserEdited', $userField->getValue('ann_timestamp_change'));
    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);

    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
