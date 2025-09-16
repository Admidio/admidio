<?php

namespace Admidio\UI\Presenter;

// Admidio namespaces
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\Entity\SelectOptions;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Language;

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
class InventoryFieldsPresenter extends PagePresenter
{
    /**
     * Create the data for the edit form of a item field.
     * @param string $itemFieldID ID of the item field that should be edited.
     * @throws Exception
     */
    public function createEditForm(string $itemFieldUUID = '', string $itemFieldName = '')
    {
        global $gCurrentSession, $gCurrentUser, $gSettingsManager, $gL10n, $gCurrentOrgId, $gDb;

        // Create user-defined field object
        $itemField = new ItemField($gDb);
        $itemField->readDataByUuid($itemFieldUUID);

        if ($itemFieldUUID === "0" && !empty($itemFieldName)) {
            $itemField->setValue('inf_name', $itemFieldName);
        }
        if ($itemFieldUUID !== '') {
            // Check whether the field belongs to the current organization
            if ($itemField->getValue('inf_org_id') > 0
                && (int)$itemField->getValue('inf_org_id') !== $gCurrentOrgId) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }

        // show link to view inventory fields history
        ChangelogService::displayHistoryButton($this, 'inventory', 'inventory_fields,inventory_field_select_options', $gCurrentUser->isAdministratorInventory(), ['uuid' => $itemFieldUUID]);

        $this->addJavascript('
            $("#inf_type").change(function() {
                if ($("#inf_type").val() === "DROPDOWN" || $("#inf_type").val() === "DROPDOWN_MULTISELECT" || $("#inf_type").val() === "RADIO_BUTTON") {
                    $("#ifo_inf_options_table").attr("required", "required");
                    $("#ifo_inf_options_group").addClass("admidio-form-group-required");
                    $("#ifo_inf_options_group").show("slow");
                    // find all option input fields in the table and set the required atribute
                    $("#ifo_inf_options_table").find("input[name$=\'[value]\']").each(function() {
                        $(this).attr("required", "required");
                    });
                } else {
                    $("#ifo_inf_options_table").removeAttr("required");
                    $("#ifo_inf_options_group").removeClass("admidio-form-group-required");
                    $("#ifo_inf_options_group").hide();
                    // find all options and remove the required atribute from the input field
                    $("#ifo_inf_options_table").find("input[name$=\'[value]\']").each(function() {
                        $(this).removeAttr("required");
                    });
                }
            });
            $("#inf_type").trigger("change");', true
        );

        // show form
        $form = new FormPresenter(
            'adm_item_fields_edit_form',
            'modules/inventory.item-fields.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('uuid' => $itemFieldUUID, 'mode' => 'field_save')),
            $this
        );

        if ($itemField->getValue('inf_system') == 1 && !$gSettingsManager->getBool('inventory_system_field_names_editable')) {
            $form->addInput(
                'inf_name',
                $gL10n->get('SYS_NAME'),
                htmlentities($itemField->getValue('inf_name'), ENT_QUOTES),
                array('maxLength' => 100, 'property' => FormPresenter::FIELD_DISABLED)
            );
        } else {
            $form->addInput(
                'inf_name',
                $gL10n->get('SYS_NAME'),
                htmlentities($itemField->getValue('inf_name'), ENT_QUOTES),
                array('maxLength' => 100, 'property' => FormPresenter::FIELD_REQUIRED)
            );
        }

        $infNameIntern = $itemField->getValue('inf_name_intern');
        // show internal field name for information
        if ($itemFieldUUID !== '') {
            $form->addInput(
                'inf_name_intern',
                $gL10n->get('SYS_INTERNAL_NAME'),
                $infNameIntern,
                array('maxLength' => 100, 'property' => FormPresenter::FIELD_DISABLED, 'helpTextId' => 'SYS_INTERNAL_NAME_DESC')
            );
        }

        $itemFieldText = array(
            'CATEGORY' => $gL10n->get('SYS_CATEGORY'),
            'CHECKBOX' => $gL10n->get('SYS_CHECKBOX'),
            'DATE' => $gL10n->get('SYS_DATE'),
            'DECIMAL' => $gL10n->get('SYS_DECIMAL_NUMBER'),
            'DROPDOWN' => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
            'DROPDOWN_MULTISELECT' => $gL10n->get('SYS_DROPDOWN_MULTISELECT_LISTBOX'),
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
            $form->addSelectBox(
                'inf_type',
                $gL10n->get('ORG_DATATYPE'),
                $itemFieldText,
                array('property' => FormPresenter::FIELD_DISABLED, 'defaultValue' => $itemField->getValue('inf_type'))
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

        $options = new SelectOptions($gDb, $itemField->getValue('inf_id'));
        foreach ($options->getAllOptions($gSettingsManager->getBool('inventory_show_obsolete_select_field_options')) as $option) {
            $option['value'] = Language::translateIfTranslationStrId($option['value']);
            $optionValueList[] = $option;
        }
        if (empty($optionValueList)) {
            $optionValueList = array(
                0 => array('id' => 1, 'value' => '', 'system' => false, 'sequence' => 0, 'obsolete' => false)
            );
        }
        $form->addOptionEditor(
            'ifo_inf_options',
            $gL10n->get('SYS_VALUE_LIST'),
            $optionValueList,
            array('helpTextId' => array('SYS_VALUE_LIST_DESC', array('<a href="https://icons.bootstrap.com" target="_blank">', '</a>')), 'filename' => 'inventory')
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
            $itemField->getValue('inf_description', 'database'),
            array('toolbar' => 'AdmidioComments'));

        $form->addSubmitButton(
            'adm_button_save',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg')
        );

        $this->assignSmartyVariable('fieldUUID', $itemFieldUUID);
        $this->assignSmartyVariable('fieldNameIntern', $infNameIntern);
        $this->assignSmartyVariable('systemField', $itemField->getValue('inf_system'));
        $this->assignSmartyVariable('sequenceField', $itemField->getValue('inf_sequence'));
        $this->assignSmartyVariable('userCreatedName', $itemField->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $itemField->getValue('inf_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $itemField->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $itemField->getValue('inf_timestamp_change'));

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
        global $gL10n, $gCurrentOrgId, $gDb, $gCurrentSession, $gCurrentUser, $gSettingsManager;

        $this->addJavascript('
            $(".admidio-open-close-caret").click(function() {
                showHideBlock($(this));
            });
            $("tbody.admidio-sortable").sortable({
                axis: "y",
                handle: ".handle",
                stop: function(event, ui) {
                    const order = $(this).sortable("toArray", {attribute: "data-uuid"});
                    const uuid = ui.item.attr("data-uuid");
                    $.post("' . ADMIDIO_URL . FOLDER_MODULES . '/inventory.php?mode=sequence&uuid=" + uuid + "&order=" + order,
                        {"adm_csrf_token": "' . $gCurrentSession->getCsrfToken() . '"}
                    );
                }
            });
            $(".admidio-field-move").click(function() {
                moveTableRow(
                    $(this),
                    "' . ADMIDIO_URL . FOLDER_MODULES . '/inventory.php",
                    "' . $gCurrentSession->getCsrfToken() . '"
                );
            });', true
        );

        // show link to view inventory fields history
        ChangelogService::displayHistoryButton($this, 'inventory', 'inventory_fields,inventory_field_select_options', $gCurrentUser->isAdministratorInventory());

        // define link to create new item field
        $this->addPageFunctionsMenuItem(
            'menu_item_new_field',
            $gL10n->get('SYS_INVENTORY_ITEMFIELD_CREATE'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'field_edit')),
            'bi-plus-circle-fill'
        );

        $items = new ItemsData($gDb, $gCurrentOrgId);
        $templateItemFieldsCategories = array();
        $templateItemFields = array();
        $itemFieldCategoryID = -1;
        $prevItemFieldCategoryID = -1;
        //array with the internal field names of the borrowing fields
        $borrowingFieldNames = array('LAST_RECEIVER', 'BORROW_DATE', 'RETURN_DATE');

        foreach ($items->getItemFields() as $itemField) {
            if ($gSettingsManager->GetBool('inventory_items_disable_borrowing') && in_array($itemField->getValue('inf_name_intern'), $borrowingFieldNames)) {
                continue; // skip borrowing fields if borrowing is disabled
            }
            $prevItemFieldCategoryID = $itemFieldCategoryID;
            $itemFieldCategoryID = ((bool)$itemField->getValue('inf_system')) ? 1 : 2;

            if ($itemFieldCategoryID !== $prevItemFieldCategoryID && count($templateItemFields) > 0) {
                $templateItemFieldsCategories[] = array(
                    'id' => $templateItemFields[0]['categoryID'],
                    'name' => $templateItemFields[0]['categoryName'],
                    'entries' => $templateItemFields
                );
                $templateItemFields = array();
            }

            $itemFieldText = array(
                'CATEGORY' => $gL10n->get('SYS_CATEGORY'),
                'CHECKBOX' => $gL10n->get('SYS_CHECKBOX'),
                'DATE' => $gL10n->get('SYS_DATE'),
                'DROPDOWN' => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                'DROPDOWN_MULTISELECT' => $gL10n->get('SYS_DROPDOWN_MULTISELECT_LISTBOX'),
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

            // set Edit URL depending on the type of field
            // if the field is a category, the edit URL is different from the other fields
            $editUrl = ($itemField->getValue('inf_type') === 'CATEGORY') ? SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/categories.php', array('type' => 'IVT')) : SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'field_edit', 'uuid' => $itemField->getValue('inf_uuid')));

            $templateRowItemField = array(
                'categoryID' => ((bool)$itemField->getValue('inf_system')) ? 1 : 2,
                'categoryName' => ((bool)$itemField->getValue('inf_system')) ? $gL10n->get('SYS_BASIC_DATA') : $gL10n->get('SYS_INVENTORY_USER_DEFINED_FIELDS'),
                'uuid' => $itemField->getValue('inf_uuid'),
                'name' => $itemField->getValue('inf_name'),
                'description' => $itemField->getValue('inf_description'),
                'urlEdit' => $editUrl,
                'dataType' => $itemFieldText[$itemField->getValue('inf_type')],
                'mandatory' => $gL10n->get($mandatoryFieldValues[$itemField->getValue('inf_required_input')]),
            );

            $templateRowItemField['actions'][] = array(
                'url' => $editUrl,
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
                    'dataHref' => 'callUrlHideElement(\'adm_item_field_' . $itemField->getValue('inf_uuid') . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'field_delete', 'uuid' => $itemField->getValue('inf_uuid'))) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
                    'dataMessage' => $gL10n->get('SYS_INVENTORY_ITEMFIELD_DELETE_DESC', array($itemField->getValue('inf_name'))),
                    'icon' => 'bi bi-trash',
                    'tooltip' => $gL10n->get('SYS_INVENTORY_ITEMFIELD_DELETE')
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
        $this->addJavascript('
            function checkEmptyCategories() {
                const categories = document.querySelectorAll("[id^=\'adm_item_fields_\']"); // Ersetzen Sie "category-" durch das tatsächliche ID-Präfix der Kategorien
                categories.forEach(category => {
                    const entries = category.querySelectorAll("[id^=\'adm_item_field_\']"); // Ersetzen Sie "entry-" durch das tatsächliche ID-Präfix der Einträge
                    let hasVisibleEntries = false;
                    entries.forEach(entry => {
                        if (entry.style.display !== "none") {
                            hasVisibleEntries = true;
                        }
                    });
                    if (!hasVisibleEntries) {
                        category.style.display = "none";
                    }
                });
            }

            document.addEventListener("DOMContentLoaded", function() {
                checkEmptyCategories();
            });
            ', true
        );
        $this->pageContent .= $this->smarty->fetch('modules/inventory.item-fields.list.tpl');
    }
}
