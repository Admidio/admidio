<?php
namespace Admidio\UI\View;

use Admidio\Infrastructure\Exception;
use Admidio\UI\Component\Form;
use HtmlPage;
use SecurityUtils;
use TableUserField;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new Menu('adm_menu', $headline);
 * $page->createEditForm();
 * $page->show();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ProfileFields extends HtmlPage
{
    /**
     * Create the data for the edit form of a profile field.
     * @param string $profileFieldUUID UUID of the profile field that should be edited.
     * @throws Exception
     */
    public function createEditForm(string $profileFieldUUID = '')
    {
        global $gCurrentSession, $gL10n, $gCurrentOrgId, $gDb;

        // Create user-defined field object
        $userField = new TableUserField($gDb);

        if ($profileFieldUUID !== '') {
            $userField->readDataByUuid($profileFieldUUID);

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
            // default values for a new field
            $userField->setValue('usf_hidden', 1);
            $userField->setValue('usf_registration', 1);
        }

        $this->addJavascript('
            $("#usf_type").change(function() {
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
            $("#usf_type").trigger("change");', true
        );

        // show form
        $form = new Form(
            'adm_profile_fields_edit_form',
            'modules/profile-fields.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile-fields.php', array('uuid' => $profileFieldUUID, 'mode' => 'save')),
            $this
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
        if ($profileFieldUUID !== '') {
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
        $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

        $this->assignSmartyVariable('fieldNameIntern', $usfNameIntern);
        $this->assignSmartyVariable('systemField', $userField->getValue('usf_system'));
        $this->assignSmartyVariable('nameUserCreated', $userField->getNameOfCreatingUser());
        $this->assignSmartyVariable('timestampUserCreated', $userField->getValue('ann_timestamp_create'));
        $this->assignSmartyVariable('nameLastUserEdited', $userField->getNameOfLastEditingUser());
        $this->assignSmartyVariable('timestampLastUserEdited', $userField->getValue('ann_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create the list with all profile fields and their preferences.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createList()
    {
        global $gL10n, $gCurrentOrgId, $gDb, $gCurrentSession;

        $this->addJavascript('
            $(".admidio-open-close-caret").click(function() {
                showHideBlock($(this));
            });
            $("tbody.admidio-sortable").sortable({
                axis: "y",
                handle: ".handle",
                stop: function(event, ui) {
                    const order = $(this).sortable("toArray", {attribute: "data-uuid"});
                    const uid = ui.item.attr("data-uuid");
                    $.post("' . ADMIDIO_URL . FOLDER_MODULES . '/profile-fields.php?mode=sequence&uuid=" + uid + "&order=" + order,
                        {"adm_csrf_token": "' . $gCurrentSession->getCsrfToken() . '"}
                    );
                }
            });
            $(".admidio-field-move").click(function() {
                moveTableRow(
                    $(this),
                    "' . ADMIDIO_URL . FOLDER_MODULES . '/profile-fields.php",
                    "' . $gCurrentSession->getCsrfToken() . '"
                );
            });', true
        );

        // define link to create new profile field
        $this->addPageFunctionsMenuItem(
            'menu_item_new_field',
            $gL10n->get('ORG_CREATE_PROFILE_FIELD'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile-fields.php', array('mode' => 'edit')),
            'bi-plus-circle-fill'
        );

        // define link to maintain categories
        $this->addPageFunctionsMenuItem(
            'menu_item_maintain_category',
            $gL10n->get('SYS_EDIT_CATEGORIES'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'USF')),
            'bi-hdd-stack-fill'
        );

        $sql = 'SELECT *
                  FROM ' . TBL_USER_FIELDS . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = usf_cat_id
                 WHERE cat_type = \'USF\'
                   AND (  cat_org_id = ?
                       OR cat_org_id IS NULL )
              ORDER BY cat_sequence, usf_sequence';
        $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

        $userField = new TableUserField($gDb);
        $templateProfileFieldsCategories = array();
        $templateProfileFields = array();
        $profileFieldCategoryID = 0;

        while ($row = $statement->fetch()) {
            $userField->clear();
            $userField->setArray($row);

            if($profileFieldCategoryID === 0) {
                $profileFieldCategoryID = $row['usf_cat_id'];
            }

            if ($profileFieldCategoryID !== $row['usf_cat_id'] && count($templateProfileFields) > 0) {
                $templateProfileFieldsCategories[] = array(
                    'uuid' => $templateProfileFields[0]['categoryUUID'],
                    'name' => $templateProfileFields[0]['categoryName'],
                    'entries' => $templateProfileFields
                );
                $profileFieldCategoryID = $row['usf_cat_id'];
                $templateProfileFields = array();
            }

            $userFieldText = array('CHECKBOX' => $gL10n->get('SYS_CHECKBOX'),
                'DATE' => $gL10n->get('SYS_DATE'),
                'DROPDOWN' => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                'EMAIL' => $gL10n->get('SYS_EMAIL'),
                'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
                'PHONE' => $gL10n->get('SYS_PHONE'),
                'TEXT' => $gL10n->get('SYS_TEXT') . ' (100)',
                'TEXT_BIG' => $gL10n->get('SYS_TEXT') . ' (4000)',
                'URL' => $gL10n->get('SYS_URL'),
                'NUMBER' => $gL10n->get('SYS_NUMBER'),
                'DECIMAL' => $gL10n->get('SYS_DECIMAL_NUMBER'));
            $mandatoryFieldValues = array(0 => 'SYS_NO',
                1 => 'SYS_YES',
                2 => 'SYS_ONLY_AT_REGISTRATION_AND_OWN_PROFILE',
                3 => 'SYS_NOT_AT_REGISTRATION');

            $templateRowProfileField = array(
                'categoryUUID' => $userField->getValue('cat_uuid'),
                'categoryName' => $userField->getValue('cat_name'),
                'uuid' => $userField->getValue('usf_uuid'),
                'name' => $userField->getValue('usf_name'),
                'description' => $userField->getValue('usf_description'),
                'urlEdit' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile-fields.php', array('mode' => 'edit', 'uuid' => $userField->getValue('usf_uuid'))),
                'dataType' => $userFieldText[$userField->getValue('usf_type')],
                'hidden' => $userField->getValue('usf_hidden'),
                'disabled' => $userField->getValue('usf_disabled'),
                'registration' => $userField->getValue('usf_registration'),
                'mandatory' => $gL10n->get($mandatoryFieldValues[$userField->getValue('usf_required_input')]),
                'defaultValue' => $userField->getValue('usf_default_value'),
                'regex' => $userField->getValue('usf_regex')
            );

            $templateRowProfileField['actions'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile-fields.php', array('mode' => 'edit', 'uuid' => $userField->getValue('usf_uuid'))),
                'icon' => 'bi bi-pencil-square',
                'tooltip' => $gL10n->get('SYS_EDIT')
            );
            if ($userField->getValue('usf_system') == 1) {
                $templateRowProfileField['actions'][] = array(
                    'url' => '',
                    'icon' => 'bi bi-trash invisible',
                    'tooltip' => ''
                );
            } else {
                $templateRowProfileField['actions'][] = array(
                    'dataHref' => 'callUrlHideElement(\'row_' . $userField->getValue('usf_uuid') . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile-fields.php', array('mode' => 'delete', 'uuid' => $userField->getValue('usf_uuid'))) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($userField->getValue('usf_name', 'database'))),
                    'icon' => 'bi bi-trash',
                    'tooltip' => $gL10n->get('SYS_DELETE')
                );
            }

            $templateProfileFields[] = $templateRowProfileField;
        }

        $templateProfileFieldsCategories[] = array(
            'uuid' => $userField->getValue('cat_uuid'),
            'name' => $userField->getValue('cat_name'),
            'entries' => $templateProfileFields
        );

        $this->smarty->assign('list', $templateProfileFieldsCategories);
        $this->smarty->assign('l10n', $gL10n);
        $this->pageContent .= $this->smarty->fetch('modules/profile-fields.list.tpl');
    }
}