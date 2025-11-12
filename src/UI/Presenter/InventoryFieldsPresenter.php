<?php

namespace Admidio\UI\Presenter;

// Admidio namespaces
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\Entity\SelectOptions;
use Admidio\Inventory\ValueObjects\ItemsData;
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
     * Create the data for the edit form of an item field.
     * @param string $itemFieldUUID UUID of the item field to edit. If empty, a new item field will be created.
     * @param string $itemFieldName Name of the item field to create. Only used if $itemFieldUUID is "0".
     * @return void
     * @throws Exception
     */
    public function createEditForm(string $itemFieldUUID = '', string $itemFieldName = ''): void
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
                if ($("#inf_type").val() === "DROPDOWN" || $("#inf_type").val() === "DROPDOWN_MULTISELECT" || $("#inf_type").val() === "DROPDOWN_DATE_INTERVAL" || $("#inf_type").val() === "RADIO_BUTTON") {
                    $("#ifo_inf_options_table").attr("required", "required");
                    $("#ifo_inf_options_group").addClass("admidio-form-group-required");
                    $("#ifo_inf_options_group").show("slow");
                    // find all option input fields in the table and set the required attribute
                    $("#ifo_inf_options_table").find("input[name$=\'[value]\']").each(function() {
                        $(this).attr("required", "required");
                    });
                } else {
                    $("#ifo_inf_options_table").removeAttr("required");
                    $("#ifo_inf_options_group").removeClass("admidio-form-group-required");
                    $("#ifo_inf_options_group").hide();
                    // find all options and remove the required attribute from the input field
                    $("#ifo_inf_options_table").find("input[name$=\'[value]\']").each(function() {
                        $(this).removeAttr("required");
                    });
                }

                var valueListTooltipContainer = document.getElementById("ifo_inf_options_group").getElementsByTagName("div")[0].getElementsByClassName("form-text")[0];
                if ($("#inf_type").val() === "DROPDOWN_DATE_INTERVAL") {
                    // if the field type is dropdown date interval, then the connected field must be a date field
                    $("#inf_connected_field_uuid").attr("required", "required");
                    $("#inf_connected_field_uuid_group").addClass("admidio-form-group-required");
                    $("#inf_connected_field_uuid_group").show();
                    
                    valueListTooltipContainer.innerHTML = "' . $gL10n->get('SYS_INVENTORY_DATE_INTERVAL_LIST_DESC') . '";
                } else {
                    // if the field type is not dropdown date interval, then the connected field must not be required
                    $("#inf_connected_field_uuid").removeAttr("required");
                    $("#inf_connected_field_uuid_group").removeClass("admidio-form-group-required");
                    $("#inf_connected_field_uuid_group").hide();
                    
                    valueListTooltipContainer.innerHTML = "' . $gL10n->get('SYS_VALUE_LIST_DESC') . '";
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
            'DROPDOWN_DATE_INTERVAL' => $gL10n->get('SYS_DROPDOWN_DATE_INTERVAL_LISTBOX'),
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

        // get all date fields from the database
        $sql = 'SELECT inf_uuid, inf_name FROM ' . TBL_INVENTORY_FIELDS . ' WHERE inf_org_id = ' . $gCurrentOrgId
            . ' AND inf_type = \'DATE\' ORDER BY inf_name';

        $form->addSelectBoxFromSql(
            'inf_connected_field_uuid',
            $gL10n->get('SYS_INVENTORY_CONNECTED_FIELD'),
            $gDb,
            $sql,
            array('defaultValue' => $itemField->getValue('inf_connected_field_uuid')),
        );

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
            array('helpTextId' => array('SYS_VALUE_LIST_DESC', array('<a href="https://icons.getbootstrap.com" target="_blank">', '</a>')), 'filename' => 'inventory')
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
     * @return void
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public function createList(): void
    {
        global $gL10n, $gCurrentOrgId, $gDb, $gCurrentSession, $gCurrentUser, $gSettingsManager;

        $this->addJavascript('
            $("tbody.admidio-sortable").sortable({
                axis: "y",
                handle: ".handle",
                stop: function(event, ui) {
                    const order = $(this).sortable("toArray", {attribute: "data-uuid"});
                    const uuid = ui.item.attr("data-uuid");
                    $.post("' . ADMIDIO_URL . FOLDER_MODULES . '/inventory.php?mode=sequence&uuid=" + uuid + "&order=" + order,
                        {"adm_csrf_token": "' . $gCurrentSession->getCsrfToken() . '"}
                    );
                    updateMoveActions("tbody.admidio-sortable", "adm_profile_field", "admidio-field-move");
                }
            });
            $(".admidio-field-move").click(function() {
                moveTableRow(
                    $(this),
                    "' . ADMIDIO_URL . FOLDER_MODULES . '/inventory.php",
                    "' . $gCurrentSession->getCsrfToken() . '"
                );
            });
            $(document).ajaxComplete(function(event, xhr, settings) {
                if (settings.url.indexOf("mode=delete") !== -1) {
                    // wait for callUrlHideElement to finish hiding the element
                    setTimeout(function() {
                        updateMoveActions("tbody.admidio-sortable", "adm_item_field", "admidio-field-move");
                    }, 1000);
                } else {
                    updateMoveActions("tbody.admidio-sortable", "adm_item_field", "admidio-field-move");
                }
            });

            updateMoveActions("tbody.admidio-sortable", "adm_item_field", "admidio-field-move");
            ', true
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

        foreach ($items->getItemFields() as $itemField) {
            if ($gSettingsManager->GetBool('inventory_items_disable_borrowing') && in_array($itemField->getValue('inf_name_intern'), $items->borrowFieldNames)) {
                continue; // skip borrowing fields if borrowing is disabled
            }

            $itemFieldText = array(
                'CATEGORY' => $gL10n->get('SYS_CATEGORY'),
                'CHECKBOX' => $gL10n->get('SYS_CHECKBOX'),
                'DATE' => $gL10n->get('SYS_DATE'),
                'DROPDOWN' => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                'DROPDOWN_MULTISELECT' => $gL10n->get('SYS_DROPDOWN_MULTISELECT_LISTBOX'),
                'DROPDOWN_DATE_INTERVAL' => $gL10n->get('SYS_DROPDOWN_DATE_INTERVAL_LISTBOX'),
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

            $templateRowItemField = [
                'system' => ($itemField->getValue('inf_system')),
                'uuid' => $itemField->getValue('inf_uuid'),
                'name' => $itemField->getValue('inf_name'),
                'description' => $itemField->getValue('inf_description'),
                'urlEdit' => $editUrl,
                'dataType' => $itemFieldText[$itemField->getValue('inf_type')],
                'mandatory' => $gL10n->get($mandatoryFieldValues[$itemField->getValue('inf_required_input')]),
            ];

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

        $this->smarty->assign('list', $templateItemFields);
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
