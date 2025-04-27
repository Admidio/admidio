<?php

namespace Admidio\UI\Presenter;

// Admidio namespaces
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

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
class InventoryItemPresenter extends PagePresenter
{
    /**
     * Create the data for the edit form of a item field.
     * @param string $itemFieldID ID of the item field that should be edited.
     * @throws Exception
     */
    public function createEditForm(string $itemID = '', bool $getCopy = false)
    {
        global $gCurrentSession, $gSettingsManager, $gCurrentUser, $gProfileFields, $gL10n, $gCurrentOrgId, $gDb;

        // Create user-defined field object
        $items = new ItemsData($gDb, $gCurrentOrgId);
    
        if ($itemID !== '') {
            $items->readItemData($itemID);
            // Check whether the field belongs to the current organization
             if ($items->getValue('ini_org_id') > 0
                && (int)$items->getItemFields()[0]->getValue('ini_org_id') !== $gCurrentOrgId) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }

        foreach ($items->getItemFields() as $itemField) {  
            $infNameIntern = $itemField->getValue('inf_name_intern');
            if($infNameIntern === 'IN_INVENTORY') {
                $pimInInventoryId = $items->getProperty($infNameIntern, 'inf_id');
            }
            if($infNameIntern === 'LAST_RECEIVER') {
                $pimLastReceiverId = $items->getProperty($infNameIntern, 'inf_id');
            }
            if ($infNameIntern === 'RECEIVED_ON') {
                $pimReceivedOnId = $items->getProperty($infNameIntern, 'inf_id');
            }
            if ($infNameIntern === 'RECEIVED_BACK_ON') {
                $pimReceivedBackOnId = $items->getProperty($infNameIntern, 'inf_id');
            }
        }

        // show form
        $form = new FormPresenter(
            'adm_item_edit_form',
            'modules/inventory.item.edit.tpl',
            ($getCopy) ? SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('item_id' => $itemID, 'mode' => 'item_save', 'copy' => true)) : SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('item_id' => $itemID, 'mode' => 'item_save')),
            $this
        );
        
        foreach ($items->getItemFields() as $itemField) {  
            $helpId = '';
            $infNameIntern = $itemField->getValue('inf_name_intern');
        
            if ($items->getProperty($infNameIntern, 'inf_required_input') == 1) {
                $fieldProperty = FormPresenter::FIELD_REQUIRED;
            }
            else {
                $fieldProperty = FormPresenter::FIELD_DEFAULT;
            }
        
            if (!$gCurrentUser->isAdministratorInventory() && !in_array($itemField->getValue('inf_name_intern'), $gSettingsManager->get('inventory_allowed_keeper_edit_fields'))) {
                $fieldProperty = FormPresenter::FIELD_DISABLED;
            }
            
            if (isset($pimInInventoryId, $pimLastReceiverId, $pimReceivedOnId, $pimReceivedBackOnId) && $infNameIntern === 'IN_INVENTORY') {
                $gSettingsManager->get('inventory_field_date_time_format') === 'datetime' ? $datetime = 'true' : $datetime = 'false';
        
                // Add JavaScript to check the LAST_RECEIVER field and set the required attribute for pimReceivedOnId and pimReceivedBackOnId
                $this->addJavascript('
                    document.addEventListener("DOMContentLoaded", function() {
                        if (document.querySelector("[id=\'inf-' . $pimReceivedOnId . '_time\']")) {
                            var pDateTime = "true";
                        } else {
                            var pDateTime = "false";
                        }
        
                        var pimInInventoryField = document.querySelector("[id=\'inf-' . $pimInInventoryId . '\']");
                        var pimInInventoryGroup = document.getElementById("inf-' . $pimInInventoryId . '_group");
                        var pimLastReceiverField = document.querySelector("[id=\'inf-' . $pimLastReceiverId . '\']");
                        var pimLastReceiverFieldHidden = document.querySelector("[id=\'inf-' . $pimLastReceiverId . '-hidden\']");
                        var pimLastReceiverGroup = document.getElementById("inf-' . $pimLastReceiverId . '_group");
                        var pimReceivedOnField = document.querySelector("[id=\'inf-' . $pimReceivedOnId . '\']");
        
                        if (pDateTime === "true") {
                            var pimReceivedOnFieldTime = document.querySelector("[id=\'inf-' . $pimReceivedOnId . '_time\']");
                            var pimReceivedBackOnFieldTime = document.querySelector("[id=\'inf-' . $pimReceivedBackOnId . '_time\']");
                        }
        
                        var pimReceivedOnGroup = document.getElementById("inf-' . $pimReceivedOnId . '_group");
                        var pimReceivedBackOnField = document.querySelector("[id=\'inf-' . $pimReceivedBackOnId . '\']");
                        var pimReceivedBackOnGroup = document.getElementById("inf-' . $pimReceivedBackOnId . '_group");
        
                        function setRequired(field, group, required) {
                            if (required) {
                            field.setAttribute("required", "required");
                            group.classList.add("admidio-form-group-required");
                            } else {
                            field.removeAttribute("required");
                            group.classList.remove("admidio-form-group-required");
                            }
                        }
        
                        window.checkPimInInventory = function() {
                            var isInInventoryChecked = pimInInventoryField.checked;
                            var lastReceiverValue = pimLastReceiverFieldHidden.value;
                            var receivedBackOnValue = pimReceivedBackOnField.value;
        
                            setRequired(pimReceivedOnField, pimReceivedOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                            setRequired(pimReceivedBackOnField, pimReceivedBackOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                            if (pDateTime === "true") {
                                setRequired(pimReceivedOnFieldTime, pimReceivedOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                                setRequired(pimReceivedBackOnFieldTime, pimReceivedBackOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                            }
        
                            setRequired(pimLastReceiverField, pimLastReceiverGroup, !isInInventoryChecked);
                            setRequired(pimReceivedOnField, pimReceivedOnGroup, !isInInventoryChecked);
                            if (pDateTime === "true") {
                                setRequired(pimReceivedOnFieldTime, pimReceivedOnGroup, !isInInventoryChecked);
                            }
        
                            console.log("Receiver: " + lastReceiverValue);
                            if (!isInInventoryChecked && (lastReceiverValue === "undefined" || !lastReceiverValue)) {
                                pimReceivedOnField.value = "";
                                if (pDateTime === "true") {
                                    pimReceivedOnFieldTime.value = "";
                                }
                            }
        
                            if (receivedBackOnValue !== "") {
                                setRequired(pimLastReceiverField, pimLastReceiverGroup, true);
                                setRequired(pimReceivedOnField, pimReceivedOnGroup, true);
                                if (pDateTime === "true") {
                                    setRequired(pimReceivedOnFieldTime, pimReceivedOnGroup, true);
                                    setRequired(pimReceivedBackOnFieldTime, pimReceivedBackOnGroup, true);
                                }
                            }
        
                            var previousPimInInventoryState = isInInventoryChecked;
        
                            pimInInventoryField.addEventListener("change", function() {
                                if (!pimInInventoryField.checked && previousPimInInventoryState) {
                                    pimReceivedBackOnField.value = "";
                                    if (pDateTime === "true") {
                                        pimReceivedBackOnFieldTime.value = "";
                                    }
                                }
                                previousPimInInventoryState = pimInInventoryField.checked;
                                window.checkPimInInventory();
                            });
        
                            pimLastReceiverFieldHidden.addEventListener("change", window.checkPimInInventory);
                            pimReceivedBackOnField.addEventListener("input", window.checkPimInInventory);
                            pimReceivedOnField.addEventListener("input", validateReceivedOnAndBackOn);
                            if (pDateTime === "true") {
                                pimReceivedOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                                pimReceivedBackOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                            }
                        }
        
                        function validateReceivedOnAndBackOn() {
                            if (pDateTime === "true") {
                                var receivedOnDate = new Date(pimReceivedOnField.value + " " + pimReceivedOnFieldTime.value);
                                var receivedBackOnDate = new Date(pimReceivedBackOnField.value + " " + pimReceivedBackOnFieldTime.value);
                            } else {
                                var receivedOnDate = new Date(pimReceivedOnField.value);
                                var receivedBackOnDate = new Date(pimReceivedBackOnField.value);
                            }
        
                            if (receivedOnDate > receivedBackOnDate) {
                                pimReceivedOnField.setCustomValidity("ReceivedOn date cannot be after ReceivedBack date.");
                            } else {
                                pimReceivedOnField.setCustomValidity("");
                            }
                        }
        
                        pimInInventoryField.addEventListener("change", window.checkPimInInventory);
                        pimLastReceiverFieldHidden.addEventListener("change", window.checkPimInInventory);
        
                        pimReceivedOnField.addEventListener("input", validateReceivedOnAndBackOn);
                        pimReceivedBackOnField.addEventListener("input", window.checkPimInInventory);
                        
                        if (pDateTime === "true") {
                            pimReceivedOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                            pimReceivedBackOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                        }
                        pimReceivedBackOnField.addEventListener("input", validateReceivedOnAndBackOn);
                        window.checkPimInInventory();
                    });
                ');
            }
        
            if ($itemField->getValue('inf_type') === 'DATE' && $itemField->getValue('inf_sequence') === '1') {
                $form->addInput('dummy', 'dummy', 'dummy', ['type' => $gSettingsManager->get('inventory_field_date_time_format'), 'property' => FormPresenter::FIELD_HIDDEN]);
            }
        
            switch ($items->getProperty($infNameIntern, 'inf_type')) {
                case 'CHECKBOX':
                    $form->addCheckbox(
                        'inf-' . $items->getProperty($infNameIntern, 'inf_id'),
                        $items->getProperty($infNameIntern, 'inf_name'),
                        ($itemID === 0) ? true : (bool) $items->getValue($infNameIntern),
                        array(
                            'property' => $fieldProperty,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database')
                        )
                    );
                    break;
        
                case 'DROPDOWN':
                    $form->addSelectBox(
                        'inf-' . $items->getProperty($infNameIntern, 'inf_id'),
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $items->getProperty($infNameIntern, 'inf_value_list'),
                        array(
                            'property' => $fieldProperty,
                            'defaultValue' => $items->getValue($infNameIntern, 'database'),
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database')
                        )
                    );
                    break;
        
                case 'RADIO_BUTTON':
                    $form->addRadioButton(
                        'inf-' . $items->getProperty($infNameIntern, 'inf_id'),
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $items->getProperty($infNameIntern, 'inf_value_list', 'html'),
                        array(
                            'property' => $fieldProperty,
                            'defaultValue' => $items->getValue($infNameIntern, 'database'),
                            'showNoValueButton' => $items->getProperty($infNameIntern, 'inf_required_input') == 0,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database')
                        )
                    );
                    break;
        
                case 'TEXT_BIG':
                    $form->addMultilineTextInput(
                        'inf-' . $items->getProperty($infNameIntern, 'inf_id'),
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $items->getValue($infNameIntern),
                        3,
                        array(
                            'maxLength' => 4000,
                            'property' => $fieldProperty,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database')
                        )
                    );
                    break;
        
                default:
                    $fieldType = 'text';
                    $maxlength = '50';
        
                    if ($infNameIntern === 'KEEPER') {
                        $sql = $items->getSqlOrganizationsUsersComplete();
                        if ($gSettingsManager->getBool('inventory_current_user_default_keeper') === true) {
                            $user = new User($gDb, $gProfileFields);
                            $user->readDataByUuid($gCurrentUser->getValue('usr_uuid'));
                        }
                        
                                $form->addSelectBoxFromSql(
                            'inf-' . $items->getProperty($infNameIntern, 'inf_id'),
                            $items->getProperty($infNameIntern, 'inf_name'),
                            $gDb,
                            $sql,
                            array(
                                'property' => $fieldProperty,
                                'helpTextId' => $helpId,
                                'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                                'defaultValue' => ($gSettingsManager->getBool('inventory_current_user_default_keeper') === true) ? $user->getValue('usr_id') : $items->getValue($infNameIntern),
                                'multiselect' => false
                            )
                        );
                    }
                    elseif ($infNameIntern === "LAST_RECEIVER") {
                        $sql = $items->getSqlOrganizationsUsersComplete();
        
                        $form->addSelectBoxFromSql(
                            'inf-' . $items->getProperty($infNameIntern, 'inf_id'),
                            $items->getProperty($infNameIntern, 'inf_name'),
                            $gDb,
                            $sql,
                            array(
                                'property' => $fieldProperty,
                                'helpTextId' => $helpId,
                                'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                                'defaultValue' => $items->getValue($infNameIntern),
                                'multiselect' => false
                            )
                        );
        
                        $form->addInput(
                            'inf-' . $items->getProperty($infNameIntern, 'inf_id') . '-hidden',
                            '',
                            $items->getValue($infNameIntern),
                            array(
                                'type' => 'hidden',
                                'property' => FormPresenter::FIELD_DISABLED
                            )
                        );
            
                        $this->addJavascript('
                        $(document).ready(function() {
                            var selectId = "#inf-' . $pimLastReceiverId . '";
        
                            var defaultValue = "' . htmlspecialchars($items->getValue($infNameIntern)) . '";
                            var defaultText = "' . htmlspecialchars($items->getValue($infNameIntern)) . '"; // Der Text für den Default-Wert
        
                            function isSelect2Empty(selectId) {
                                // Hole den aktuellen Wert des Select2-Feldes
                                var lastReceiverValueHidden = document.querySelector("[id=\'inf-' . $pimLastReceiverId . '-hidden\']");
                                var renderedElement = $("#select2-inf-' . $pimLastReceiverId .'-container");
                                if (renderedElement.length) {
                                    lastReceiverValueHidden.value = renderedElement.attr("title");
                                    console.log("Hidden: " + lastReceiverValueHidden.value);
                                    window.checkPimInInventory();
                                }
                            }
                            // Prüfe, ob der Default-Wert in den Optionen enthalten ist
                            if ($(selectId + " option[value=\'" + defaultValue + "\']").length === 0) {
                                // Füge den Default-Wert als neuen Tag hinzu
                                var newOption = new Option(defaultText, defaultValue, true, true);
                                $(selectId).append(newOption).trigger("change");
                            }
        
                            $("#inf-' . $pimLastReceiverId .'").select2({
                                theme: "bootstrap-5",
                                allowClear: true,
                                placeholder: "",
                                language: "' . $gL10n->getLanguageLibs() . '",
                                tags: true
                            });
        
                            // Überwache Änderungen im Select2-Feld
                            $(selectId).on("change.select2", function() {
                                isSelect2Empty(selectId);
                            });
                        });', true);
                    }
                    else {
                        if ($items->getProperty($infNameIntern, 'inf_type') === 'DATE') {
                            $fieldType = $gSettingsManager->get('inventory_field_date_time_format');
                            $maxlength = null;
                        }
                        elseif ($items->getProperty($infNameIntern, 'inf_type') === 'NUMBER') {
                            $fieldType = 'number';
                            $minNumber = $gSettingsManager->getBool('inventory_allow_negative_numbers') ? null : '0';
                            $step = '1';
                        }
                        elseif ($items->getProperty($infNameIntern, 'inf_type') === 'DECIMAL') {
                            $fieldType = 'number';
                            $minNumber = $gSettingsManager->getBool('inventory_allow_negative_numbers') ? null : '0';
                            $step = pow(10, -$gSettingsManager->get('inventory_decimal_places'));
                        }
                        $form->addInput(
                            'inf-' . $items->getProperty($infNameIntern, 'inf_id'),
                            $items->getProperty($infNameIntern, 'inf_name'),
                            $items->getValue($infNameIntern),
                            array(
                                'type' => $fieldType,
                                'maxLength' => isset($maxlength) ? $maxlength : null,
                                'minNumber' => isset($minNumber) ? $minNumber : null,
                                'step' => isset($step) ? $step : null,
                                'property' => $fieldProperty,
                                'helpTextId' => $helpId,
                                'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database')
                            )
                        );
                    }
                    break;
            }
        }
        
        if ($getCopy) {     
            //$form->addDescription($gL10n->get('SYS_INVENTORY_COPY_PREFERENCES') . '<br/>');
            $form->addInput('item_copy_number', $gL10n->get('SYS_INVENTORY_NUMBER'), 1, array('type' => 'number', 'minNumber' => 1, 'maxNumber' => 9999, 'step' => 1, 'helpTextId' => 'SYS_INVENTORY_NUMBER_DESC'));
            $sql = 'SELECT inf_id, inf_name FROM ' . TBL_INVENTORY_FIELDS . ' WHERE inf_type = \'NUMBER\' AND (inf_org_id = ' . $gCurrentOrgId . ' OR inf_org_id IS NULL);';
            $form->addSelectBoxFromSql('item_copy_field', $gL10n->get('SYS_INVENTORY_FIELD'), $gDb, $sql, array('multiselect' => false, 'helpTextId' => 'SYS_INVENTORY_FIELD_DESC'));        
        }
        $form->addSubmitButton(
            'adm_button_save',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg')
        );

        // Load the select2 in case any of the form uses a select box. Unfortunately, each section
        // is loaded on-demand, when there is no html page any more to insert the css/JS file loading,
        // so we need to do it here, even when no selectbox will be used...
        // TODO_RK: Can/Shall we load these select2 files only on demand (used by some subsections like the changelog)?
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/css/select2.css');
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2-bootstrap-theme/select2-bootstrap-5-theme.css');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/select2.js');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/i18n/' . $gL10n->getLanguageLibs() . '.js');

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
                    $.post("' . ADMIDIO_URL . FOLDER_MODULES . '/inventory.php?mode=field_sequence&uuid=" + id + "&order=" + order,
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

        // define link to create new item field
        $this->addPageFunctionsMenuItem(
            'menu_item_new_field',
            $gL10n->get('ORG_CREATE_PROFILE_FIELD'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'field_edit')),
            'bi-plus-circle-fill'
        );

        $items = new ItemsData($gDb, $gCurrentOrgId);
        $templateItemFieldsCategories = array();
        $templateItemFields = array();
        $itemFieldCategoryID = -1;
        $prevItemFieldCategoryID = -1;

        foreach ($items->getItemFields() as $itemField) {
            $prevItemFieldCategoryID = $itemFieldCategoryID;
            $itemFieldCategoryID = ((bool)$itemField->getValue('inf_system')) ? 1 : 2;

            if ($itemFieldCategoryID !== $prevItemFieldCategoryID &&  count($templateItemFields) > 0) {
                $templateItemFieldsCategories[] = array(
                    'id' => $templateItemFields[0]['categoryID'],
                    'name' => $templateItemFields[0]['categoryName'],
                    'entries' => $templateItemFields
                );
                //$prevItemFieldCategoryID = $itemFieldCategoryID; // = ((bool)$itemField->getValue('inf_system')) ? 1 : 2;
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
                'categoryName' => ((bool)$itemField->getValue('inf_system')) ? $gL10n->get('SYS_BASIC_DATA') : $gL10n->get('SYS_INVENTORY_USER_DEFINED_FIELDS')  /* $itemField->getValue('cat_name') */,
                'id' => $itemField->getValue('inf_id'),
                'name' => $itemField->getValue('inf_name'),
                'description' => $itemField->getValue('inf_description'),
                'urlEdit' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'field_edit', 'uuid' => $itemField->getValue('inf_id'))),
                'dataType' => $itemFieldText[$itemField->getValue('inf_type')],
                'mandatory' => $gL10n->get($mandatoryFieldValues[$itemField->getValue('inf_required_input')]),
            );

            $templateRowItemField['actions'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'field_edit', 'uuid' => $itemField->getValue('inf_id'))),
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
                    'dataHref' => 'callUrlHideElement(\'adm_item_field_' . $itemField->getValue('inf_id') . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'field_delete', 'uuid' => $itemField->getValue('inf_id'))) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')',
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
        $this->pageContent .= $this->smarty->fetch('modules/item-fields.list.tpl');
    }
}
