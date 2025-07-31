<?php

namespace Admidio\UI\Presenter;

// Admidio namespaces
use Admidio\Categories\Service\CategoryService;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;
use DateTime;
use Ramsey\Uuid\Uuid;

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
    public function createEditForm(string $itemUUID = '', bool $getCopy = false)
    {
        global $gCurrentSession, $gSettingsManager, $gCurrentUser, $gProfileFields, $gL10n, $gCurrentOrgId, $gDb;
        //array with the internal field names of the borrow fields not used in the edit form
        $borrowFieldNames = array('IN_INVENTORY', 'LAST_RECEIVER', 'BORROW_DATE', 'RETURN_DATE');

        // Create user-defined field object
        $items = new ItemsData($gDb, $gCurrentOrgId);
        $categoryService = new CategoryService($gDb, 'IVT');
    
        if ($itemUUID !== '') {
            $items->readItemData($itemUUID);
            // Check whether the field belongs to the current organization
             if ($items->getValue('ini_org_id') > 0
                && (int)$items->getItemFields()[0]->getValue('ini_org_id') !== $gCurrentOrgId) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }

        // display History button
        ChangelogService::displayHistoryButton($this, 'inventory', 'inventory_item_data', $gCurrentUser->isAdministratorInventory(), ['uuid' => $itemUUID]);

        foreach ($items->getItemFields() as $itemField) {  
            $infNameIntern = $itemField->getValue('inf_name_intern');
            if ($infNameIntern === 'KEEPER') {
                $pimKeeper = $infNameIntern;
            }
        }

        // show form
        $form = new FormPresenter(
            'adm_item_edit_form',
            'modules/inventory.item.edit.tpl',
            ($getCopy) ? SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('item_uuid' => $itemUUID, 'mode' => 'item_save', 'copy' => true)) : SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('item_uuid' => $itemUUID, 'mode' => 'item_save')),
            $this
        );
        
        foreach ($items->getItemFields() as $itemField) {  
            $helpId = '';
            $infNameIntern = $itemField->getValue('inf_name_intern');
            // Skip borrow fields that are not used in the edit form
            if (in_array($itemField->getValue('inf_name_intern'), $borrowFieldNames)) {
                if ($infNameIntern === 'IN_INVENTORY') {
                    // we need to add the checkbox for IN_INVENTORY defaulting to true
                    $form->addInput(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        ($itemUUID === '') ? true : (bool)$items->getValue($infNameIntern),
                        array('property' => FormPresenter::FIELD_HIDDEN)
                    );
                }
                continue;
            }       

            if ($items->getProperty($infNameIntern, 'inf_required_input') == 1) {
                $fieldProperty = FormPresenter::FIELD_REQUIRED;
            }
            else {
                $fieldProperty = FormPresenter::FIELD_DEFAULT;
            }
        
            $allowedFields = explode(',', $gSettingsManager->getString('inventory_allowed_keeper_edit_fields'));
            if (!$gCurrentUser->isAdministratorInventory() && !in_array($itemField->getValue('inf_name_intern'), $allowedFields)) {
                $fieldProperty = FormPresenter::FIELD_DISABLED;
            }
            
      
            if ($itemField->getValue('inf_type') === 'DATE' && $itemField->getValue('inf_sequence') === '1') {
                $form->addInput('dummy', 'dummy', 'dummy', ['type' => $gSettingsManager->getString('inventory_field_date_time_format'), 'property' => FormPresenter::FIELD_HIDDEN]);
            }
        
            switch ($items->getProperty($infNameIntern, 'inf_type')) {
                case 'CHECKBOX':
                    $form->addCheckbox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        ($itemUUID === '') ? true : (bool) $items->getValue($infNameIntern),
                        array(
                            'property' => $fieldProperty,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database')
                        )
                    );
                    break;
        
                case 'DROPDOWN':
                    $form->addSelectBox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $items->getProperty($infNameIntern, 'ifo_inf_options'),
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
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $items->getProperty($infNameIntern, 'ifo_inf_options', 'html'),
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
                        'INF-' . $infNameIntern,
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
                
                case 'CATEGORY':
                    if ($categoryService->count() > 0) {
                        $form->addSelectBoxForCategories(
                            'INF-' . $infNameIntern,
                            $gL10n->get('SYS_CATEGORY'),
                            $gDb,
                            'IVT',
                            FormPresenter::SELECT_BOX_MODUS_EDIT,
                            array(
                                'property' => FormPresenter::FIELD_REQUIRED,
                                'defaultValue' => $items->getValue($infNameIntern, 'database'),
                            )
                        );
                    }
                    break;
                default:
                    $fieldType = 'text';
                    $maxlength = '50';
        
                    if ($infNameIntern === 'KEEPER') {
                        $sql = $items->getSqlOrganizationsUsersComplete();
                        if ($gSettingsManager->getBool('inventory_current_user_default_keeper')) {
                            $user = new User($gDb, $gProfileFields);
                            $user->readDataByUuid($gCurrentUser->getValue('usr_uuid'));
                        }
                        
                        $form->addSelectBoxFromSql(
                            'INF-' . $infNameIntern,
                            $items->getProperty($infNameIntern, 'inf_name'),
                            $gDb,
                            $sql,
                            array(
                                'property' => $fieldProperty,
                                'helpTextId' => $helpId,
                                'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                                'defaultValue' => ($gSettingsManager->getBool('inventory_current_user_default_keeper')) ? $user->getValue('usr_id') : $items->getValue($infNameIntern),
                                'multiselect' => false
                            )
                        );

                        $this->addJavascript('
                            // Select2 für KEEPER initialisieren
                            $("#INF-' . $pimKeeper .'").select2({
                                theme: "bootstrap-5",
                                allowClear: true,
                                placeholder: "",
                                language: "' . $gL10n->getLanguageLibs() . '",
                            });',
                            true
                        );
                    }
                    else {
                        if ($items->getProperty($infNameIntern, 'inf_type') === 'DATE') {
                            $fieldType = $gSettingsManager->getString('inventory_field_date_time_format');
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
                            $step = pow(10, -$gSettingsManager->getInt('inventory_decimal_places'));
                        }
                        $form->addInput(
                            'INF-' . $infNameIntern,
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

        $item = new Item($gDb,  $items, $items->getItemId());
        $this->assignSmartyVariable('userCreatedName', $item->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $item->getValue('ini_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $item->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $item->getValue('ini_timestamp_change'));
        
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create the data for the edit form of a item field.
     * @param array $itemUUIDs IDs of the items that should be edited.
     * @throws Exception
     */
    public function createEditItemsForm(array $itemUUIDs = array())
    {
        global $gCurrentSession, $gSettingsManager, $gCurrentUser, $gProfileFields, $gL10n, $gCurrentOrgId, $gDb;
        // array with the internal field names of the borrow fields not used in the edit form
        // we also exclude IITEMNAME from the edit form, because it is only used for displaying item values based on the first entry
        // and it is not wanted to change the item name for multiple items at once
        $borrowFieldNames = array('ITEMNAME', 'IN_INVENTORY', 'LAST_RECEIVER', 'BORROW_DATE', 'RETURN_DATE');

        // Create user-defined field object
        $items = new ItemsData($gDb, $gCurrentOrgId);
        $categoryService = new CategoryService($gDb, 'IVT');
    
        // for editing multiple items, we will use the first itemUUID as value reference
        $itemUUID = $itemUUIDs[0] ?? '';

        if ($itemUUID !== '') {
            $items->readItemData($itemUUID);
            // Check whether the field belongs to the current organization
             if ($items->getValue('ini_org_id') > 0
                && (int)$items->getItemFields()[0]->getValue('ini_org_id') !== $gCurrentOrgId) {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }

        foreach ($items->getItemFields() as $itemField) {  
            $infNameIntern = $itemField->getValue('inf_name_intern');
            if ($infNameIntern === 'KEEPER') {
                $pimKeeper = $infNameIntern;
            }
        }

        // show form
        $form = new FormPresenter(
            'adm_item_edit_form',
            'modules/inventory.item.edit.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('item_uuids' => $itemUUIDs, 'mode' => 'item_save')),
            $this
        );
        
        foreach ($items->getItemFields() as $itemField) {  
            $helpId = '';
            $infNameIntern = $itemField->getValue('inf_name_intern');
            // Skip borrow fields that are not used in the edit form
            if (in_array($itemField->getValue('inf_name_intern'), $borrowFieldNames)) {
                if ($infNameIntern === 'ITEMNAME') {
                    // If the item is new, we need to add the input for ITEMNAME
                    $itemNames = '';
                    foreach ($itemUUIDs as $uuid) {
                        $item = new ItemsData($gDb, $gCurrentOrgId);
                        $item->readItemData($uuid);
                        if ($itemNames !== '') {
                            $itemNames .= "\n";
                        }
                        $itemNames .= "- " . $item->getValue($infNameIntern);
                    }
                    $form->addMultilineTextInput(
                        'INF-' . $infNameIntern,
                        $gL10n->get('SYS_INVENTORY_ITEMS'),
                        $itemNames,
                        min(count($itemUUIDs), 5),
                        array('property' => FormPresenter::FIELD_DISABLED)
                    );
                }
                continue;
            }       

            if ($items->getProperty($infNameIntern, 'inf_required_input') == 1) {
                $fieldProperty = FormPresenter::FIELD_REQUIRED;
            }
            else {
                $fieldProperty = FormPresenter::FIELD_DEFAULT;
            }
        
            $allowedFields = explode(',', $gSettingsManager->getString('inventory_allowed_keeper_edit_fields'));
            if (!$gCurrentUser->isAdministratorInventory() && !in_array($itemField->getValue('inf_name_intern'), $allowedFields)) {
                $fieldProperty = FormPresenter::FIELD_DISABLED;
            }
            
      
            if ($itemField->getValue('inf_type') === 'DATE' && $itemField->getValue('inf_sequence') === '1') {
                $form->addInput('dummy', 'dummy', 'dummy', ['type' => $gSettingsManager->getString('inventory_field_date_time_format'), 'property' => FormPresenter::FIELD_HIDDEN]);
            }
        
            switch ($items->getProperty($infNameIntern, 'inf_type')) {
                case 'CHECKBOX':
                    $form->addCheckbox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        ($itemUUID === '') ? true : (bool) $items->getValue($infNameIntern),
                        array(
                            'property' => $fieldProperty,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                            'toggleable' => true
                        )
                    );
                    break;
        
                case 'DROPDOWN':
                    $form->addSelectBox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $items->getProperty($infNameIntern, 'ifo_inf_options'),
                        array(
                            'property' => $fieldProperty,
                            'defaultValue' => $items->getValue($infNameIntern, 'database'),
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                            'toggleable' => true
                        )
                    );
                    break;
        
                case 'RADIO_BUTTON':
                    $form->addRadioButton(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $items->getProperty($infNameIntern, 'ifo_inf_options', 'html'),
                        array(
                            'property' => $fieldProperty,
                            'defaultValue' => $items->getValue($infNameIntern, 'database'),
                            'showNoValueButton' => $items->getProperty($infNameIntern, 'inf_required_input') == 0,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                            'toggleable' => true
                        )
                    );
                    break;
        
                case 'TEXT_BIG':
                    $form->addMultilineTextInput(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $items->getValue($infNameIntern),
                        3,
                        array(
                            'maxLength' => 4000,
                            'property' => $fieldProperty,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                            'toggleable' => true
                        )
                    );
                    break;
                
                case 'CATEGORY':
                    if ($categoryService->count() > 0) {
                        $form->addSelectBoxForCategories(
                            'INF-' . $infNameIntern,
                            $gL10n->get('SYS_CATEGORY'),
                            $gDb,
                            'IVT',
                            FormPresenter::SELECT_BOX_MODUS_EDIT,
                            array(
                                'property' => FormPresenter::FIELD_REQUIRED,
                                'defaultValue' => $items->getValue($infNameIntern, 'database'),
                                'toggleable' => true
                            )
                        );
                    }
                    break;
                default:
                    $fieldType = 'text';
                    $maxlength = '50';
        
                    if ($infNameIntern === 'KEEPER') {
                        $sql = $items->getSqlOrganizationsUsersComplete();
                        if ($gSettingsManager->getBool('inventory_current_user_default_keeper')) {
                            $user = new User($gDb, $gProfileFields);
                            $user->readDataByUuid($gCurrentUser->getValue('usr_uuid'));
                        }
                        
                        $form->addSelectBoxFromSql(
                            'INF-' . $infNameIntern,
                            $items->getProperty($infNameIntern, 'inf_name'),
                            $gDb,
                            $sql,
                            array(
                                'property' => $fieldProperty,
                                'helpTextId' => $helpId,
                                'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                                'defaultValue' => ($gSettingsManager->getBool('inventory_current_user_default_keeper')) ? $user->getValue('usr_id') : $items->getValue($infNameIntern),
                                'multiselect' => false,
                                'toggleable' => true
                            )
                        );

                        $this->addJavascript('
                            // Select2 für KEEPER initialisieren
                            $("#INF-' . $pimKeeper .'").select2({
                                theme: "bootstrap-5",
                                allowClear: true,
                                placeholder: "",
                                language: "' . $gL10n->getLanguageLibs() . '",
                            });',
                            true
                        );
                    }
                    else {
                        if ($items->getProperty($infNameIntern, 'inf_type') === 'DATE') {
                            $fieldType = $gSettingsManager->getString('inventory_field_date_time_format');
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
                            $step = pow(10, -$gSettingsManager->getInt('inventory_decimal_places'));
                        }
                        $form->addInput(
                            'INF-' . $infNameIntern,
                            $items->getProperty($infNameIntern, 'inf_name'),
                            $items->getValue($infNameIntern),
                            array(
                                'type' => $fieldType,
                                'maxLength' => isset($maxlength) ? $maxlength : null,
                                'minNumber' => isset($minNumber) ? $minNumber : null,
                                'step' => isset($step) ? $step : null,
                                'property' => $fieldProperty,
                                'helpTextId' => $helpId,
                                'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                                'toggleable' => true
                            )
                        );
                    }
                    break;
            }
        }
        
        $form->addSubmitButton(
            'adm_button_save',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg')
        );

        // add javascript to toggle item fields editability
        $this->addJavascript('
            function toggleItemFields(fieldIdPrefix) {
                var toggleCheckbox = document.getElementById("toggle_" + fieldIdPrefix);
                // Select all elements that have an id equal to fieldIdPrefix or starting with fieldIdPrefix_
                var fieldElements = document.querySelectorAll(\'[id^="\' + fieldIdPrefix + \'"]\');
                if (toggleCheckbox) {
                    fieldElements.forEach(function(fieldElement) {
                        if (fieldElement.id === fieldIdPrefix || fieldElement.id.indexOf(fieldIdPrefix + "_") === 0) {
                            fieldElement.disabled = !toggleCheckbox.checked;
                        }
                    });
                }
            }

            document.addEventListener("DOMContentLoaded", function () {
                // Find all toggle checkboxes and attach the change event
                var toggleCheckboxes = document.querySelectorAll(\'[id^="toggle_"]\');
                toggleCheckboxes.forEach(function(toggle) {
                    var fieldIdPrefix = toggle.id.replace("toggle_", "");
                    // Set the initial state
                    toggleItemFields(fieldIdPrefix);
                    // Update field state on toggle change
                    toggle.addEventListener("change", function() {
                        toggleItemFields(fieldIdPrefix);
                    });
                });
            });'
        );

        // Load the select2 in case any of the form uses a select box. Unfortunately, each section
        // is loaded on-demand, when there is no html page any more to insert the css/JS file loading,
        // so we need to do it here, even when no selectbox will be used...
        // TODO_RK: Can/Shall we load these select2 files only on demand (used by some subsections like the changelog)?
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/css/select2.css');
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2-bootstrap-theme/select2-bootstrap-5-theme.css');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/select2.js');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/i18n/' . $gL10n->getLanguageLibs() . '.js');

        $item = new Item($gDb,  $items, $items->getItemId());

        // add a information that this is a multi-edit form
        $infoAlert = $gL10n->get('SYS_INVENTORY_ITEMS_EDIT_DESC');

        $this->assignSmartyVariable('infoAlert', $infoAlert);
        $this->assignSmartyVariable('multiEdit', true);
        $this->assignSmartyVariable('userCreatedName', $item->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $item->getValue('ini_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $item->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $item->getValue('ini_timestamp_change'));
        
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }
    
    /**
     * Create the data for the edit form of a item field.
     * @param string $itemFieldID ID of the item field that should be edited.
     * @throws Exception
     */
    public function createEditBorrowForm(string $itemUUID)
    {
        global $gCurrentSession, $gSettingsManager, $gCurrentUser, $gL10n, $gCurrentOrgId, $gDb;
        //array with the internal field names of the borrow fields not used in the edit form
        $borrowFieldNames = array('ITEMNAME', 'IN_INVENTORY', 'LAST_RECEIVER', 'BORROW_DATE', 'RETURN_DATE');

        // Create user-defined field object
        $items = new ItemsData($gDb, $gCurrentOrgId);
    
        // Check if itemUUID is valid
        if (!Uuid::isValid($itemUUID)) {
            throw new Exception('The parameter "' . $itemUUID . '" is not a valid UUID!');
        } elseif ($gSettingsManager->GetBool('inventory_items_disable_borrowing')) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // display History button
        ChangelogService::displayHistoryButton($this, 'inventory', 'inventory_item_borrow_data', $gCurrentUser->isAdministratorInventory(), ['uuid' => $itemUUID]);

        // Read item data
        $items->readItemData($itemUUID);
        // Check whether the field belongs to the current organization
        if ($items->getValue('ini_org_id') > 0
            && (int)$items->getItemFields()[0]->getValue('ini_org_id') !== $gCurrentOrgId) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // show form
        $form = new FormPresenter(
            'adm_item_edit_borrow_form',
            'modules/inventory.item.edit.borrow.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('item_uuid' => $itemUUID, 'mode' => 'item_save')),
            $this
        );
        
        foreach ($items->getItemFields() as $itemField) {  
            $helpId = '';
            $infNameIntern = $itemField->getValue('inf_name_intern');
        
            if($infNameIntern === 'IN_INVENTORY') {
                $ivtInInventory = $infNameIntern;
            }
            elseif($infNameIntern === 'LAST_RECEIVER') {
                $ivtLastReceiver = $infNameIntern;
            }
            elseif ($infNameIntern === 'BORROW_DATE') {
                $ivtReceivedOn = $infNameIntern;
            }
            elseif ($infNameIntern === 'RETURN_DATE') {
                $ivtReceivedBackOn = $infNameIntern;
            }

            // Skip all fields not used in the borrow form
            if (!in_array($infNameIntern, $borrowFieldNames)) {
                continue;
            }

            if ($items->getProperty($infNameIntern, 'inf_required_input') == 1) {
                $fieldProperty = FormPresenter::FIELD_REQUIRED;
            }
            else {
                $fieldProperty = FormPresenter::FIELD_DEFAULT;
            }
        
            $allowedFields = explode(',', $gSettingsManager->getString('inventory_allowed_keeper_edit_fields'));
            if (!$gCurrentUser->isAdministratorInventory() && !in_array($itemField->getValue('inf_name_intern'), $allowedFields)) {
                $fieldProperty = FormPresenter::FIELD_DISABLED;
            }
            
            if (isset($ivtInInventory, $ivtLastReceiver, $ivtReceivedOn, $ivtReceivedBackOn)) {
                // Add JavaScript to check the LAST_RECEIVER field and set the required attribute for ivtReceivedOnId and ivtReceivedBackOnId
                $this->addJavascript('
                    document.addEventListener("DOMContentLoaded", function() {
                        if (document.querySelector("[id=\'INF-' . $ivtReceivedOn . '_time\']")) {
                            var pDateTime = "true";
                        } else {
                            var pDateTime = "false";
                        }
        
                        var ivtInInventoryField = document.querySelector("[id=\'INF-' . $ivtInInventory . '\']");
                        var ivtInInventoryGroup = document.getElementById("INF-' . $ivtInInventory . '_group");
                        var ivtLastReceiverField = document.querySelector("[id=\'INF-' . $ivtLastReceiver . '\']");
                        var ivtLastReceiverGroup = document.getElementById("INF-' . $ivtLastReceiver . '_group");
                        var ivtReceivedOnField = document.querySelector("[id=\'INF-' . $ivtReceivedOn . '\']");
        
                        if (pDateTime === "true") {
                            var ivtReceivedOnFieldTime = document.querySelector("[id=\'INF-' . $ivtReceivedOn . '_time\']");
                            var ivtReceivedBackOnFieldTime = document.querySelector("[id=\'INF-' . $ivtReceivedBackOn . '_time\']");
                        }
        
                        var ivtReceivedOnGroup = document.getElementById("INF-' . $ivtReceivedOn . '_group");
                        var ivtReceivedBackOnField = document.querySelector("[id=\'INF-' . $ivtReceivedBackOn . '\']");
                        var ivtReceivedBackOnGroup = document.getElementById("INF-' . $ivtReceivedBackOn . '_group");
        
                        function setRequired(field, group, required) {
                            if (required) {
                            field.setAttribute("required", "required");
                            group.classList.add("admidio-form-group-required");
                            } else {
                            field.removeAttribute("required");
                            group.classList.remove("admidio-form-group-required");
                            }
                        }
        
                        window.checkivtInInventory = function() {
                            var isInInventoryChecked = ivtInInventoryField.checked;
                            var lastReceiverValue = ivtLastReceiverField.value;
                            var receivedBackOnValue = ivtReceivedBackOnField.value;
        
                            setRequired(ivtReceivedOnField, ivtReceivedOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                            setRequired(ivtReceivedBackOnField, ivtReceivedBackOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                            if (pDateTime === "true") {
                                setRequired(ivtReceivedOnFieldTime, ivtReceivedOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                                setRequired(ivtReceivedBackOnFieldTime, ivtReceivedBackOnGroup, isInInventoryChecked && (lastReceiverValue && lastReceiverValue !== "undefined"));
                            }
        
                            setRequired(ivtLastReceiverField, ivtLastReceiverGroup, !isInInventoryChecked);
                            setRequired(ivtReceivedOnField, ivtReceivedOnGroup, !isInInventoryChecked);
                            if (pDateTime === "true") {
                                setRequired(ivtReceivedOnFieldTime, ivtReceivedOnGroup, !isInInventoryChecked);
                            }
        
                            if (!isInInventoryChecked && (lastReceiverValue === "undefined" || !lastReceiverValue)) {
                                ivtReceivedOnField.value = "";
                                if (pDateTime === "true") {
                                    ivtReceivedOnFieldTime.value = "";
                                }
                            }
        
                            if (receivedBackOnValue !== "") {
                                setRequired(ivtLastReceiverField, ivtLastReceiverGroup, true);
                                setRequired(ivtReceivedOnField, ivtReceivedOnGroup, true);
                                if (pDateTime === "true") {
                                    setRequired(ivtReceivedOnFieldTime, ivtReceivedOnGroup, true);
                                    setRequired(ivtReceivedBackOnFieldTime, ivtReceivedBackOnGroup, true);
                                }
                            }
        
                            var previousivtInInventoryState = isInInventoryChecked;
        
                            ivtInInventoryField.addEventListener("change", function() {
                                if (!ivtInInventoryField.checked && previousivtInInventoryState) {
                                    ivtReceivedBackOnField.value = "";
                                    if (pDateTime === "true") {
                                        ivtReceivedBackOnFieldTime.value = "";
                                    }
                                }
                                previousivtInInventoryState = ivtInInventoryField.checked;
                                window.checkivtInInventory();
                            });
        
                            ivtLastReceiverField.addEventListener("change", window.checkivtInInventory);
                            ivtReceivedBackOnField.addEventListener("input", window.checkivtInInventory);
                            ivtReceivedOnField.addEventListener("input", validateReceivedOnAndBackOn);
                            if (pDateTime === "true") {
                                ivtReceivedOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                                ivtReceivedBackOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                            }
                        }
        
                        function validateReceivedOnAndBackOn() {
                            if (pDateTime === "true") {
                                var receivedOnDate = new Date(ivtReceivedOnField.value + " " + ivtReceivedOnFieldTime.value);
                                var receivedBackOnDate = new Date(ivtReceivedBackOnField.value + " " + ivtReceivedBackOnFieldTime.value);
                            } else {
                                var receivedOnDate = new Date(ivtReceivedOnField.value);
                                var receivedBackOnDate = new Date(ivtReceivedBackOnField.value);
                            }
        
                            if (receivedOnDate > receivedBackOnDate) {
                                ivtReceivedOnField.setCustomValidity("ReceivedOn date cannot be after ReceivedBack date.");
                            } else {
                                ivtReceivedOnField.setCustomValidity("");
                            }
                        }
        
                        ivtInInventoryField.addEventListener("change", window.checkivtInInventory);
                        ivtLastReceiverField.addEventListener("change", window.checkivtInInventory);
        
                        ivtReceivedOnField.addEventListener("input", validateReceivedOnAndBackOn);
                        ivtReceivedBackOnField.addEventListener("input", window.checkivtInInventory);
                        
                        if (pDateTime === "true") {
                            ivtReceivedOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                            ivtReceivedBackOnFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                        }
                        ivtReceivedBackOnField.addEventListener("input", validateReceivedOnAndBackOn);
                        window.checkivtInInventory();
                    });
                ');
            }
        
            if ($itemField->getValue('inf_type') === 'DATE' && $itemField->getValue('inf_sequence') === '1') {
                $form->addInput('dummy', 'dummy', 'dummy', ['type' => $gSettingsManager->getString('inventory_field_date_time_format'), 'property' => FormPresenter::FIELD_HIDDEN]);
            }
        
            switch ($items->getProperty($infNameIntern, 'inf_type')) {
                case 'CHECKBOX':
                    $form->addCheckbox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        ($itemUUID === '') ? true : (bool) $items->getValue($infNameIntern),
                        array(
                            'property' => $fieldProperty,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database')
                        )
                    );
                    break;
        
                case 'DROPDOWN':
                    $form->addSelectBox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $items->getProperty($infNameIntern, 'ifo_inf_options'),
                        array(
                            'property' => $fieldProperty,
                            'defaultValue' => $items->getValue($infNameIntern, 'database'),
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database')
                        )
                    );
                    break;
        
                default:
                    $fieldType = 'text';
                    $maxlength = '50';
        
                    if ($infNameIntern === "LAST_RECEIVER") {
                        $sql = $items->getSqlOrganizationsUsersComplete();
        
                        $form->addSelectBoxFromSql(
                            'INF-' . $infNameIntern,
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
            
                        $this->addJavascript('
                            var selectIdLastReceiver = "#INF-' . $ivtLastReceiver . '";
        
                            var defaultValue = "' . htmlspecialchars($items->getValue($infNameIntern)) . '";
                            var defaultText = "' . htmlspecialchars($items->getValue($infNameIntern)) . '"; // Der Text für den Default-Wert
        
                            function isSelect2Empty(selectId) {
                                // Hole den aktuellen Wert des Select2-Feldes
                                var renderedElement = $("#select2-INF-' . $ivtLastReceiver .'-container");
                                if (renderedElement.length) {
                                    window.checkivtInInventory();
                                }
                            }
                            // Prüfe, ob der Default-Wert in den Optionen enthalten ist
                            if ($(selectIdLastReceiver + " option[value=\'" + defaultValue + "\']").length === 0) {
                                // Füge den Default-Wert als neuen Tag hinzu
                                var newOption = new Option(defaultText, defaultValue, true, true);
                                $(selectIdLastReceiver).append(newOption).trigger("change");
                            }
        
                            $("#INF-' . $ivtLastReceiver .'").select2({
                                theme: "bootstrap-5",
                                allowClear: true,
                                placeholder: "",
                                language: "' . $gL10n->getLanguageLibs() . '",
                                tags: true
                            });
        
                            // Überwache Änderungen im Select2-Feld
                            $(selectIdLastReceiver).on("change.select2", function() {
                                isSelect2Empty(selectIdLastReceiver);
                            });',
                            true
                        );
                    }
                    else {
                        if ($items->getProperty($infNameIntern, 'inf_type') === 'DATE') {
                            $fieldType = $gSettingsManager->getString('inventory_field_date_time_format');
                            $maxlength = null;
                            $date = new DateTime('now');
                            if ($fieldType === 'datetime') {
                                $defaultDate = $date->format('Y-m-d H:i');
                            } else {
                                $defaultDate = $date->format('Y-m-d');
                            }

                        }
                        elseif ($infNameIntern === 'ITEMNAME') {
                            $fieldProperty = FormPresenter::FIELD_DISABLED;
                        }
                        else {
                            break;
                        }

                        $form->addInput(
                            'INF-' . $infNameIntern,
                            $items->getProperty($infNameIntern, 'inf_name'),
                            ($items->getValue($infNameIntern) === '' && $infNameIntern === 'BORROW_DATE') ? $defaultDate : $items->getValue($infNameIntern),
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

        $item = new Item($gDb,  $items, $items->getItemId());
        $this->assignSmartyVariable('userCreatedName', $item->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $item->getValue('ini_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $item->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $item->getValue('ini_timestamp_change'));
        
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }
}
