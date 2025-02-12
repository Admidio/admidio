<?php
namespace Admidio\UI\View;

use Admidio\Infrastructure\Exception;
use Admidio\Inventory\Entity\ItemField;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\ValueObjects\ItemsData;

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
class ItemFields extends PagePresenter
{
    /**
     * Create the data for the edit form of a item field.
     * @param string $itemFieldID ID of the item field that should be edited.
     * @throws Exception
     */
    public function createEditForm(string $itemFieldID = '')
    {
        global $gCurrentSession, $gSettingsManager, $gL10n, $gCurrentOrgId, $gDb;

        // Create user-defined field object
        $itemField = new ItemField($gDb, intval($itemFieldID));

        if ($itemFieldID !== '') {
            // Check whether the field belongs to the current organization
            if ($itemField->getValue('inf_org_id') > 0
                && (int)$itemField->getValue('inf_org_id') !== $gCurrentOrgId) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }

        $this->addJavascript('
            $("#usf_type").change(function() {
                if ($("#inf_type").val() === "DROPDOWN" || $("#inf_type").val() === "RADIO_BUTTON") {
                    $("#inf_value_list").attr("required", "required");
                    $("#inf_value_list_group").addClass("admidio-form-group-required");
                    $("#inf_value_list_group").show("slow");
                } else {
                    $("#inf_value_list").removeAttr("required");
                    $("#inf_value_list_group").removeClass("admidio-form-group-required");
                    $("#inf_value_list_group").hide();
                }
            });
            $("#inf_type").trigger("change");', true
        );

        // show form
        $form = new FormPresenter(
            'adm_item_fields_edit_form',
            'modules/item-fields.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/item-fields.php', array('uuid' => $itemFieldID, 'mode' => 'save')),
            $this
        );

        if ($itemField->getValue('inf_system') == 1 && $gSettingsManager->getBool('inventory_system_field_names_editable') == false) {
            $form->addInput(
                'inf_name',
                $gL10n->get('SYS_NAME'),
                htmlentities($itemField->getValue('inf_name', 'database'), ENT_QUOTES),
                array('maxLength' => 100, 'property' => FormPresenter::FIELD_DISABLED)
            );
        } else {
            $form->addInput(
                'inf_name',
                $gL10n->get('SYS_NAME'),
                htmlentities($itemField->getValue('inf_name', 'database'), ENT_QUOTES),
                array('maxLength' => 100, 'property' => FormPresenter::FIELD_REQUIRED)
            );
        }

        $infNameIntern = $itemField->getValue('inf_name_intern');
        // show internal field name for information
        if ($itemFieldID !== '0') {
            $form->addInput(
                'inf_name_intern',
                $gL10n->get('SYS_INTERNAL_NAME'),
                $infNameIntern,
                array('maxLength' => 100, 'property' => FormPresenter::FIELD_DISABLED, 'helpTextId' => 'SYS_INTERNAL_NAME_DESC')
            );
        }

        $itemFieldText = array(
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
        asort($itemFieldText);

        if ($itemField->getValue('inf_system') == 1) {
            // for system fields, the data type may no longer be changed
            $form->addInput(
                'inf_type',
                $gL10n->get('ORG_DATATYPE'),
                $itemFieldText[$itemField->getValue('inf_type')],
                array('maxLength' => 30, 'property' => FormPresenter::FIELD_DISABLED)
            );
        } else {
            // if it's not a system field the user must select the data type
            $form->addSelectBox(
                'inf_type',
                $gL10n->get('ORG_DATATYPE'),
                $itemFieldText,
                array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $itemField->getValue('inf_type'))
            );
        }
        $form->addMultilineTextInput(
            'inf_value_list',
            $gL10n->get('SYS_VALUE_LIST'),
            htmlentities($itemField->getValue('inf_value_list', 'database'), ENT_QUOTES),
            6,
            array('helpTextId' => array('SYS_VALUE_LIST_DESC', array('<a href="https://icons.bootstrap.com">', '</a>')))
        );
        $mandatoryFieldValues = array(0 => 'SYS_NO', 1 => 'SYS_YES');
        $form->addSelectBox(
            'inf_required_input',
            $gL10n->get('SYS_REQUIRED_INPUT'),
            $mandatoryFieldValues,
            array('property' => FormPresenter::FIELD_REQUIRED, 'defaultValue' => $itemField->getValue('inf_required_input'))
        );

        $form->addEditor(
            'inf_description',
            $gL10n->get('SYS_DESCRIPTION'),
            $itemField->getValue('inf_description'),
            array('toolbar' => 'AdmidioComments'));
        $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

        $this->assignSmartyVariable('fieldNameIntern', $infNameIntern);
        $this->assignSmartyVariable('systemField', $itemField->getValue('inf_system'));
        $this->assignSmartyVariable('sequenceField', $itemField->getValue('inf_sequence'));
        $this->assignSmartyVariable('nameUserCreated', $itemField->getNameOfCreatingUser());
        $this->assignSmartyVariable('timestampUserCreated', $itemField->getValue('ann_timestamp_create'));
        $this->assignSmartyVariable('nameLastUserEdited', $itemField->getNameOfLastEditingUser());
        $this->assignSmartyVariable('timestampLastUserEdited', $itemField->getValue('ann_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create the list with all item fields and their preferences.
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
                    const id = ui.item.attr("data-uuid");
                    $.post("' . ADMIDIO_URL . FOLDER_MODULES . '/item-fields.php?mode=sequence&uuid=" + id + "&order=" + order,
                        {"adm_csrf_token": "' . $gCurrentSession->getCsrfToken() . '"}
                    );
                }
            });
            $(".admidio-field-move").click(function() {
                moveTableRow(
                    $(this),
                    "' . ADMIDIO_URL . FOLDER_MODULES . '/item-fields.php",
                    "' . $gCurrentSession->getCsrfToken() . '"
                );
            });', true
        );

        // define link to create new item field
        $this->addPageFunctionsMenuItem(
            'menu_item_new_field',
            $gL10n->get('ORG_CREATE_PROFILE_FIELD'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/item-fields.php', array('mode' => 'edit')),
            'bi-plus-circle-fill'
        );

        $items = new ItemsData($gDb, $gCurrentOrgId);
        $templateItemFieldsCategories = array();
        $templateItemFields = array();
        $itemFieldCategoryID = -1;

        foreach ($items->getItemFields() as $itemField) {
            if($itemFieldCategoryID === -1) {
                $itemFieldCategoryID = ((bool)$itemField->getValue('inf_system')) ? 1 : 2;
            }

            if ($itemFieldCategoryID !== $itemField->getValue('inf_system') &&  count($templateItemFields) > 0) {
                $templateItemFieldsCategories[] = array(
                    'id' => $templateItemFields[0]['categoryID'],
                    'name' => $templateItemFields[0]['categoryName'],
                    'entries' => $templateItemFields
                );
                $itemFieldCategoryID = ((bool)$itemField->getValue('inf_system')) ? 1 : 2;
                $templateItemFields = array();
            }

            $itemFieldText = array('CHECKBOX' => $gL10n->get('SYS_CHECKBOX'),
                'DATE' => $gL10n->get('SYS_DATE'),
                'DROPDOWN' => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                'EMAIL' => $gL10n->get('SYS_EMAIL'),
                'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
                'PHONE' => $gL10n->get('SYS_PHONE'),
                'TEXT' => $gL10n->get('SYS_TEXT') . ' (100)',
                'TEXT_BIG' => $gL10n->get('SYS_TEXT') . ' (4000)',
                'URL' => $gL10n->get('SYS_URL'),
                'NUMBER' => $gL10n->get('SYS_NUMBER'),
                'DECIMAL' => $gL10n->get('SYS_DECIMAL_NUMBER')
            );

            $mandatoryFieldValues = array(0 => 'SYS_NO', 1 => 'SYS_YES');

            $templateRowItemField = array(
                'categoryID' => ((bool)$itemField->getValue('inf_system')) ? 1 : 2,
                'categoryName' => ((bool)$itemField->getValue('inf_system')) ? $gL10n->get('SYS_BASIC_DATA') : $gL10n->get('SYS_INVENTORY_USER_DEFINED')  /* $itemField->getValue('cat_name') */,
                'id' => $itemField->getValue('inf_id'),
                'name' => $itemField->getValue('inf_name'),
                'description' => $itemField->getValue('inf_description'),
                'urlEdit' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/item-fields.php', array('mode' => 'edit', 'uuid' => $itemField->getValue('inf_id'))),
                'dataType' => $itemFieldText[$itemField->getValue('inf_type')],
                'mandatory' => $gL10n->get($mandatoryFieldValues[$itemField->getValue('inf_required_input')]),
            );

            $templateRowItemField['actions'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/item-fields.php', array('mode' => 'edit', 'uuid' => $itemField->getValue('inf_id'))),
                'icon' => 'bi bi-pencil-square',
                'tooltip' => $gL10n->get('SYS_EDIT')
            );
            if ($itemField->getValue('inf_system') == 1) {
                $templateRowItemField['actions'][] = array(
                    'url' => '',
                    'icon' => 'bi bi-trash invisible',
                    'tooltip' => ''
                );
            } else {
                $templateRowItemField['actions'][] = array(
                    'dataHref' => 'callUrlHideElement(\'row_' . $itemField->getValue('inf_id') . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/item-fields.php', array('mode' => 'delete', 'uuid' => $itemField->getValue('inf_id'))) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_DELETE_ENTRY', array($itemField->getValue('usf_name', 'database'))),
                    'icon' => 'bi bi-trash',
                    'tooltip' => $gL10n->get('SYS_DELETE')
                );
            }

            $templateItemFields[] = $templateRowItemField;
        }

        $templateItemFieldsCategories[] = array(
            'id' => $templateItemFields[0]['categoryID'],
            'name' => $templateItemFields[0]['categoryName'],
            'entries' => $templateItemFields
        );

        $this->smarty->assign('list', $templateItemFieldsCategories);
        $this->smarty->assign('l10n', $gL10n);
        $this->pageContent .= $this->smarty->fetch('modules/item-fields.list.tpl');
    }
}
