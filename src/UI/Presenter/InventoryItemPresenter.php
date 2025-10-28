<?php

namespace Admidio\UI\Presenter;

// Admidio namespaces
use Admidio\Categories\Service\CategoryService;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\SystemInfoUtils;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\ValueObjects\ItemsData;
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
     * Create the data for the edit form of an item field.
     * @param string $itemUUID UUID of the item that should be edited.
     * @param bool $getCopy Indicates whether a copy of the item should be created.
     * @return void
     * @throws Exception
     */
    public function createEditForm(string $itemUUID = '', bool $getCopy = false): void
    {
        global $gCurrentSession, $gSettingsManager, $gCurrentUser, $gProfileFields, $gL10n, $gCurrentOrgId, $gDb;

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
                $ivtKeeper = $infNameIntern;
            }
        }

        $this->addJavascript('
            function callbackItemPicture() {
                var imgSrc = $("#adm_inventory_item_picture").attr("src");
                var timestamp = new Date().getTime();
                $("#adm_button_delete_picture").hide();
                $("#adm_inventory_item_picture").attr("src", imgSrc + "&" + timestamp);
            }
        ');

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
            if (in_array($infNameIntern, $items->borrowFieldNames)) {
                continue;
            }

            if ($items->getProperty($infNameIntern, 'inf_required_input') == 1) {
                $fieldProperty = FormPresenter::FIELD_REQUIRED;
            } else {
                $fieldProperty = FormPresenter::FIELD_DEFAULT;
            }

            $allowedFields = explode(',', $gSettingsManager->getString('inventory_allowed_keeper_edit_fields'));
            if (!$gCurrentUser->isAdministratorInventory() && !in_array($infNameIntern, $allowedFields)) {
                $fieldProperty = FormPresenter::FIELD_DISABLED;
            }

            switch ($items->getProperty($infNameIntern, 'inf_type')) {
                case 'CHECKBOX':
                    $form->addCheckbox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        ($itemUUID === '') ? true : (bool)$items->getValue($infNameIntern),
                        array(
                            'property' => $fieldProperty,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database')
                        )
                    );
                    break;

                case 'DROPDOWN': // fallthrough
                case 'DROPDOWN_MULTISELECT':
                    $arrOptions = $items->getProperty($infNameIntern, 'ifo_inf_options', '', false);
                    $defaultValue = $items->getValue($infNameIntern, 'database');
                    // prevent adding an empty string to the select-box
                    if ($items->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN_MULTISELECT') {
                        // prevent adding an empty string to the select-box
                        $defaultValue = ($defaultValue !== "") ? explode(',', $defaultValue) : array();
                    }

                    $form->addSelectBox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $arrOptions,
                        array(
                            'property' => $fieldProperty,
                            'defaultValue' => $defaultValue,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                            'multiselect' => ($items->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN_MULTISELECT'),
                            'maximumSelectionNumber' => ($items->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN_MULTISELECT') ? count($arrOptions) : 0,
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
                    if ($fieldProperty === FormPresenter::FIELD_DISABLED) {
                        // user is not allowed to edit the category, so we display the current value as disabled text field
                        $form->addInput(
                            'INF-' . $infNameIntern,
                            $items->getProperty($infNameIntern, 'inf_name'),
                            $items->getValue($infNameIntern),
                            array(
                                'type' => 'text',
                                'property' => $fieldProperty,
                                'helpTextId' => $helpId,
                                'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database')
                            )
                        );
                    } elseif ($categoryService->count() > 0) {
                        if (!empty($categoryService->getEditableCategories())) {
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
                        } else {
                            $form->addInput(
                                'INF-' . $infNameIntern,
                                $gL10n->get('SYS_CATEGORY'),
                                $items->getValue($infNameIntern),
                                array(
                                    'type' => 'text',
                                    'property' => FormPresenter::FIELD_DISABLED
                                )
                            );
                            // add an information that no editable categories are available
                            $this->smarty->append('infoAlerts', array('type' => 'warning', 'value' => $gL10n->get('SYS_INVENTORY_NO_EDITABLE_CATEGORIES')));
                        }
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
                            $("#INF-' . $ivtKeeper . '").select2({
                                theme: "bootstrap-5",
                                allowClear: true,
                                placeholder: "",
                                language: "' . $gL10n->getLanguageLibs() . '",
                            });',
                            true
                        );
                    } else {
                        if ($items->getProperty($infNameIntern, 'inf_type') === 'DATE') {
                            $fieldType = $gSettingsManager->getString('inventory_field_date_time_format');
                            $maxlength = null;
                        } elseif ($items->getProperty($infNameIntern, 'inf_type') === 'NUMBER') {
                            $fieldType = 'number';
                            $minNumber = $gSettingsManager->getBool('inventory_allow_negative_numbers') ? null : '0';
                            $step = '1';
                        } elseif ($items->getProperty($infNameIntern, 'inf_type') === 'DECIMAL') {
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

        if ($getCopy) {
            if ($form->getElements()['INF-CATEGORY']['type'] === 'text') {
                // when copying an item and the user is not allowed to change the category, we cannot create copies
                $form->addSubmitButton(
                    'adm_button_save',
                    $gL10n->get('SYS_COPY'),
                    array('icon' => 'bi-plus-lg', 'property' => FormPresenter::FIELD_DISABLED)
                );
            } else {
                $form->addSubmitButton(
                    'adm_button_save',
                    $gL10n->get('SYS_COPY'),
                    array('icon' => 'bi-plus-lg')
                );
            }
        } else {
            $form->addSubmitButton(
                'adm_button_save',
                $gL10n->get('SYS_SAVE'),
                array('icon' => 'bi-check-lg')
            );
        }

        // Load the select2 in case any of the form uses a select box. Unfortunately, each section
        // is loaded on-demand, when there is no html page anymore to insert the css/JS file loading,
        // so we need to do it here, even when no select-box will be used...
        // TODO_RK: Can/Shall we load these select2 files only on demand (used by some subsections like the changelog)?
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/css/select2.css');
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2-bootstrap-theme/select2-bootstrap-5-theme.css');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/select2.js');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/i18n/' . $gL10n->getLanguageLibs() . '.js');

        $item = new Item($gDb, $items, $items->getItemId());
        $this->assignSmartyVariable('userCreatedName', $item->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $item->getValue('ini_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $item->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $item->getValue('ini_timestamp_change'));

        // only show the item picture if the module setting is enabled, the item is not new, and we don't want to create a copy of the item
        if ($gSettingsManager->GetBool('inventory_item_picture_enabled')) {
            if (!$item->isNewRecord() && !$getCopy) {
                $this->assignSmartyVariable('urlItemPicture', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_picture_show', 'item_uuid' => $itemUUID)));
                // the image can only be deleted if corresponding rights exist
                if ($gCurrentUser->isAdministratorInventory() || ($gSettingsManager->GetBool('inventory_allow_keeper_edit') && InventoryPresenter::isCurrentUserKeeper($items->getItemId()) && in_array('ITEM_PICTURE', $allowedFields))) {
                    $this->assignSmartyVariable('urlItemPictureUpload', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_picture_choose', 'item_uuid' => $itemUUID)));
                    if ((string)$item->getValue('ini_picture') !== '' && $gSettingsManager->getInt('inventory_item_picture_storage') === 0
                        || is_file(ADMIDIO_PATH . FOLDER_DATA . '/inventory_item_pictures/' . $items->getItemId() . '.jpg') && $gSettingsManager->getInt('inventory_item_picture_storage') === 1) {
                        $this->assignSmartyVariable('urlItemPictureDelete', 'callUrlHideElement(\'no_element\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_picture_delete', 'item_uuid' => $itemUUID)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\', \'callbackItemPicture\')');
                    }
                }
            } else {
                // we create a new item or a copy of an existing item, so we can only upload a picture after saving the new item
                $this->smarty->append('infoAlerts', array('type' => 'info', 'value' => $gL10n->get('SYS_INVENTORY_ITEM_CREATE_PICTURE_INFO')));
            }
        }

        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create the data for the edit form of an item field.
     * @param array $itemUUIDs IDs of the items that should be edited.
     * @return void
     * @throws Exception
     */
    public function createEditItemsForm(array $itemUUIDs = array()): void
    {
        global $gCurrentSession, $gSettingsManager, $gCurrentUser, $gProfileFields, $gL10n, $gCurrentOrgId, $gDb;

        // Create user-defined field object
        $items = new ItemsData($gDb, $gCurrentOrgId);
        $categoryService = new CategoryService($gDb, 'IVT');

        // array with the internal field names of the borrow fields not used in the edit form
        // we also exclude ITEMNAME from the edit form, because it is only used for displaying item values based on the first entry,
        // and it is not wanted to change the item name for multiple items at once
        $borrowFieldNames = array_merge($items->borrowFieldNames, array('ITEMNAME'));

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
                $ivtKeeper = $infNameIntern;
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
            if (in_array($infNameIntern, $borrowFieldNames)) {
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
            } else {
                $fieldProperty = FormPresenter::FIELD_DEFAULT;
            }

            $allowedFields = explode(',', $gSettingsManager->getString('inventory_allowed_keeper_edit_fields'));
            if (!$gCurrentUser->isAdministratorInventory() && !in_array($infNameIntern, $allowedFields)) {
                $fieldProperty = FormPresenter::FIELD_DISABLED;
            }

            switch ($items->getProperty($infNameIntern, 'inf_type')) {
                case 'CHECKBOX':
                    $form->addCheckbox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        ($itemUUID === '') ? true : (bool)$items->getValue($infNameIntern),
                        array(
                            'property' => $fieldProperty,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                            'toggleable' => true
                        )
                    );
                    break;

                case 'DROPDOWN': // fallthrough
                case 'DROPDOWN_MULTISELECT':
                    $arrOptions = $items->getProperty($infNameIntern, 'ifo_inf_options', '', false);
                    $defaultValue = $items->getValue($infNameIntern, 'database');
                    // prevent adding an empty string to the select-box
                    if ($items->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN_MULTISELECT') {
                        // prevent adding an empty string to the select-box
                        $defaultValue = ($defaultValue !== "") ? explode(',', $defaultValue) : array();
                    }

                    $form->addSelectBox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $arrOptions,
                        array(
                            'property' => $fieldProperty,
                            'defaultValue' => $defaultValue,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                            'toggleable' => true,
                            'multiselect' => ($items->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN_MULTISELECT'),
                            'maximumSelectionNumber' => ($items->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN_MULTISELECT') ? count($arrOptions) : 0,
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
                    if ($fieldProperty === FormPresenter::FIELD_DISABLED) {
                        // user is not allowed to edit the category, so we display the current value as disabled text field
                        $form->addInput(
                            'INF-' . $infNameIntern,
                            $items->getProperty($infNameIntern, 'inf_name'),
                            $items->getValue($infNameIntern),
                            array(
                                'type' => 'text',
                                'property' => $fieldProperty,
                                'helpTextId' => $helpId,
                                'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                                'toggleable' => true
                            )
                        );

                    } elseif ($categoryService->count() > 0) {
                        if (!empty($gCurrentUser->getAllEditableCategories('IVT'))) {
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
                        } else {
                            $categories = array();
                            foreach ($categoryService->getVisibleCategories() as $category) {
                                $categories[$category['cat_uuid']] = $category['cat_name'];
                            }

                            $form->addSelectBox(
                                'INF-' . $infNameIntern,
                                $gL10n->get('SYS_CATEGORY'),
                                $categories,
                                array(
                                    'property' => FormPresenter::FIELD_REQUIRED,
                                    'defaultValue' => $items->getValue($infNameIntern, 'database'),
                                    'toggleable' => true
                                )
                            );
                        }
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
                            $("#INF-' . $ivtKeeper . '").select2({
                                theme: "bootstrap-5",
                                allowClear: true,
                                placeholder: "",
                                language: "' . $gL10n->getLanguageLibs() . '",
                            });',
                            true
                        );
                    } else {
                        if ($items->getProperty($infNameIntern, 'inf_type') === 'DATE') {
                            $fieldType = $gSettingsManager->getString('inventory_field_date_time_format');
                            $maxlength = null;
                        } elseif ($items->getProperty($infNameIntern, 'inf_type') === 'NUMBER') {
                            $fieldType = 'number';
                            $minNumber = $gSettingsManager->getBool('inventory_allow_negative_numbers') ? null : '0';
                            $step = '1';
                        } elseif ($items->getProperty($infNameIntern, 'inf_type') === 'DECIMAL') {
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
        // is loaded on-demand, when there is no html page anymore to insert the css/JS file loading,
        // so we need to do it here, even when no select-box will be used...
        // TODO_RK: Can/Shall we load these select2 files only on demand (used by some subsections like the changelog)?
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/css/select2.css');
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2-bootstrap-theme/select2-bootstrap-5-theme.css');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/select2.js');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/i18n/' . $gL10n->getLanguageLibs() . '.js');

        $item = new Item($gDb, $items, $items->getItemId());

        // add an information that this is a multi-edit form
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
     * Create the data for the edit form of an item field.
     * @param string $itemUUID UUID of the item that should be borrowed.
     * @return void
     * @throws Exception
     */
    public function createEditBorrowForm(string $itemUUID): void
    {
        global $gCurrentSession, $gSettingsManager, $gCurrentUser, $gL10n, $gCurrentOrgId, $gDb;

        // Create user-defined field object
        $items = new ItemsData($gDb, $gCurrentOrgId);

        // array with the internal field names of the borrow fields used in the edit borrow form
        // we also include ITEMNAME for displaying the item name in the borrow form
        $borrowFieldNames = array_merge($items->borrowFieldNames, array('ITEMNAME'));

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

            if ($infNameIntern === 'LAST_RECEIVER') {
                $ivtLastReceiver = $infNameIntern;
            } elseif ($infNameIntern === 'BORROW_DATE') {
                $ivtBorrowDate = $infNameIntern;
            } elseif ($infNameIntern === 'RETURN_DATE') {
                $ivtReturnDate = $infNameIntern;
            }

            // Skip all fields not used in the borrow form
            if (!in_array($infNameIntern, $borrowFieldNames)) {
                continue;
            }

            if ($items->getProperty($infNameIntern, 'inf_required_input') == 1) {
                $fieldProperty = FormPresenter::FIELD_REQUIRED;
            } else {
                $fieldProperty = FormPresenter::FIELD_DEFAULT;
            }

            $allowedFields = explode(',', $gSettingsManager->getString('inventory_allowed_keeper_edit_fields'));
            if (!$gCurrentUser->isAdministratorInventory() && !in_array($itemField->getValue('inf_name_intern'), $allowedFields)) {
                $fieldProperty = FormPresenter::FIELD_DISABLED;
            }

            if (isset($ivtLastReceiver, $ivtBorrowDate, $ivtReturnDate)) {
                // Add JavaScript to check the LAST_RECEIVER field and set the required attribute for ivtBorrowDate and ivtReturnDate
                $this->addJavascript('
                    document.addEventListener("DOMContentLoaded", function() {
                        if (document.querySelector("[id=\'INF-' . $ivtBorrowDate . '_time\']")) {
                            var pDateTime = "true";
                        } else {
                            var pDateTime = "false";
                        }
        
                        var ivtLastReceiverField = document.querySelector("[id=\'INF-' . $ivtLastReceiver . '\']");
                        var ivtBorrowDateField = document.querySelector("[id=\'INF-' . $ivtBorrowDate . '\']");
                        var ivtReturnDateField = document.querySelector("[id=\'INF-' . $ivtReturnDate . '\']");

                        if (pDateTime === "true") {
                            var ivtBorrowDateFieldTime = document.querySelector("[id=\'INF-' . $ivtBorrowDate . '_time\']");
                            var ivtReturnDateFieldTime = document.querySelector("[id=\'INF-' . $ivtReturnDate . '_time\']");
                        }
        
                        var ivtLastReceiverGroup = document.getElementById("INF-' . $ivtLastReceiver . '_group");
                        var ivtBorrowDateGroup = document.getElementById("INF-' . $ivtBorrowDate . '_group");
                        var ivtReturnDateGroup = document.getElementById("INF-' . $ivtReturnDate . '_group");

                        function setRequired(field, group, required) {
                            if (required) {
                            field.setAttribute("required", "required");
                            group.classList.add("admidio-form-group-required");
                            } else {
                            field.removeAttribute("required");
                            group.classList.remove("admidio-form-group-required");
                            }
                        }

                         window.checkItemBorrowState = function() {
                            var lastReceiverValue = ivtLastReceiverField.value;
                            var borrowDateValue = ivtBorrowDateField.value;
                            var returnDateValue = ivtReturnDateField.value;
                            var requiredLastReceiverCheck = borrowDateValue !== "" || returnDateValue !== "";
                            var requiredBorrowDateCheck = (lastReceiverValue && lastReceiverValue !== "undefined") || returnDateValue !== "";

                            setRequired(ivtLastReceiverField, ivtLastReceiverGroup, requiredLastReceiverCheck);
                            setRequired(ivtBorrowDateField, ivtBorrowDateGroup, requiredBorrowDateCheck);
                            if (pDateTime === "true") {
                                setRequired(ivtBorrowDateFieldTime, ivtBorrowDateGroup, requiredBorrowDateCheck);
                            }
        
                            if (returnDateValue !== "") {
                                setRequired(ivtLastReceiverField, ivtLastReceiverGroup, true);
                                setRequired(ivtBorrowDateField, ivtBorrowDateGroup, true);
                                if (pDateTime === "true") {
                                    setRequired(ivtBorrowDateFieldTime, ivtBorrowDateGroup, true);
                                    setRequired(ivtReturnDateFieldTime, ivtReturnDateGroup, true);
                                }
                            }
                        }
        
                        function validateReceivedOnAndBackOn() {
                            if (pDateTime === "true") {
                                var receivedOnDate = new Date(ivtBorrowDateField.value + " " + ivtBorrowDateFieldTime.value);
                                var receivedBackOnDate = new Date(ivtReturnDateField.value + " " + ivtReturnDateFieldTime.value);
                            } else {
                                var receivedOnDate = new Date(ivtBorrowDateField.value);
                                var receivedBackOnDate = new Date(ivtReturnDateField.value);
                            }
        
                            if (receivedOnDate > receivedBackOnDate) {
                                ivtBorrowDateField.setCustomValidity("' . $gL10n->get('SYS_INVENTORY_BORROW_DATE_WARNING') . '");
                            } else {
                                ivtBorrowDateField.setCustomValidity("");
                            }
                        }
        
                        ivtLastReceiverField.addEventListener("change", window.checkItemBorrowState);
                        ivtBorrowDateField.addEventListener("input",  window.checkItemBorrowState);
                        ivtReturnDateField.addEventListener("input",  window.checkItemBorrowState);

                        ivtBorrowDateField.addEventListener("input", validateReceivedOnAndBackOn);
                        ivtReturnDateField.addEventListener("input", validateReceivedOnAndBackOn);
                        if (pDateTime === "true") {
                            ivtBorrowDateFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                            ivtReturnDateFieldTime.addEventListener("input", validateReceivedOnAndBackOn);
                        }

                        window.checkItemBorrowState();
                    });
                ');
            }

            switch ($items->getProperty($infNameIntern, 'inf_type')) {
                case 'CHECKBOX':
                    $form->addCheckbox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        ($itemUUID === '') ? true : (bool)$items->getValue($infNameIntern),
                        array(
                            'property' => $fieldProperty,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database')
                        )
                    );
                    break;

                case 'DROPDOWN': // fallthrough
                case 'DROPDOWN_MULTISELECT':
                    $arrOptions = $items->getProperty($infNameIntern, 'ifo_inf_options', '', false);
                    $defaultValue = $items->getValue($infNameIntern, 'database');
                    // prevent adding an empty string to the select-box
                    if ($items->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN_MULTISELECT') {
                        // prevent adding an empty string to the select-box
                        $defaultValue = ($defaultValue !== "") ? explode(',', $defaultValue) : array();
                    }

                    $form->addSelectBox(
                        'INF-' . $infNameIntern,
                        $items->getProperty($infNameIntern, 'inf_name'),
                        $arrOptions,
                        array(
                            'property' => $fieldProperty,
                            'defaultValue' => $defaultValue,
                            'helpTextId' => $helpId,
                            'icon' => $items->getProperty($infNameIntern, 'inf_icon', 'database'),
                            'multiselect' => ($items->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN_MULTISELECT'),
                            'maximumSelectionNumber' => ($items->getProperty($infNameIntern, 'inf_type') === 'DROPDOWN_MULTISELECT') ? count($arrOptions) : 0,
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
                                var renderedElement = $("#select2-INF-' . $ivtLastReceiver . '-container");
                                if (renderedElement.length) {
                                    window.checkItemBorrowState();
                                }
                            }
                            // Prüfe, ob der Default-Wert in den Optionen enthalten ist
                            if ($(selectIdLastReceiver + " option[value=\'" + defaultValue + "\']").length === 0) {
                                // Füge den Default-Wert als neuen Tag hinzu
                                var newOption = new Option(defaultText, defaultValue, true, true);
                                $(selectIdLastReceiver).append(newOption).trigger("change");
                            }
        
                            // remove placeholder option from select2 options 
                            $(selectIdLastReceiver + " option[value=\'placeholder\']").remove();

                            $("#INF-' . $ivtLastReceiver . '").select2({
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
                    } else {
                        if ($items->getProperty($infNameIntern, 'inf_type') === 'DATE') {
                            $fieldType = $gSettingsManager->getString('inventory_field_date_time_format');
                            $maxlength = null;
                            $date = new DateTime('now');
                            if ($fieldType === 'datetime') {
                                $defaultDate = $date->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
                            } else {
                                $defaultDate = $date->format($gSettingsManager->getString('system_date'));
                            }

                        } elseif ($infNameIntern === 'ITEMNAME') {
                            $fieldProperty = FormPresenter::FIELD_DISABLED;
                        } else {
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
        // is loaded on-demand, when there is no html page anymore to insert the css/JS file loading,
        // so we need to do it here, even when no select-box will be used...
        // TODO_RK: Can/Shall we load these select2 files only on demand (used by some subsections like the changelog)?
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/css/select2.css');
        $this->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/select2-bootstrap-theme/select2-bootstrap-5-theme.css');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/select2.js');
        $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/select2/js/i18n/' . $gL10n->getLanguageLibs() . '.js');

        $item = new Item($gDb, $items, $items->getItemId());
        $this->assignSmartyVariable('userCreatedName', $item->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $item->getValue('ini_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $item->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $item->getValue('ini_timestamp_change'));

        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create the data for the picture upload form of an item.
     * @param string $itemUUID UUID of the item that should be edited.
     * @return void
     * @throws Exception
     */
    public function createPictureChooseForm(string $itemUUID): void
    {
        global $gCurrentSession, $gL10n;
        // show form
        $form = new FormPresenter(
            'adm_upload_picture_form',
            'modules/inventory.new-item-picture.upload.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_picture_upload', 'item_uuid' => $itemUUID)),
            $this,
            array('enableFileUpload' => true)
        );
        $form->addCustomContent(
            'item_picture_current',
            $gL10n->get('SYS_INVENTORY_ITEM_PICTURE_CURRENT'),
            '<img class="imageFrame" src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_picture_show', 'item_uuid' => $itemUUID)) . '" alt="' . $gL10n->get('SYS_INVENTORY_ITEM_PICTURE_CURRENT') . '" />'
        );
        $form->addFileUpload(
            'item_picture_upload_file',
            $gL10n->get('SYS_SELECT_PHOTO'),
            array(
                'property' => FormPresenter::FIELD_REQUIRED,
                'allowedMimeTypes' => array('image/jpeg', 'image/png'),
                'helpTextId' => array('SYS_INVENTORY_ITEM_PICTURE_RESTRICTIONS', array(round(SystemInfoUtils::getProcessableImageSize() / 1000000, 2), round(PhpIniUtils::getUploadMaxSize() / 1024 ** 2, 2)))
            )
        );
        $form->addSubmitButton(
            'adm_button_upload',
            $gL10n->get('SYS_INVENTORY_ITEM_PICTURE_UPLOAD'),
            array('icon' => 'bi-upload', 'class' => 'offset-sm-3')
        );

        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create the data for the picture preview form of an item.
     * @param string $itemUUID UUID of the item that should be edited.
     * @return void
     * @throws Exception
     */
    public function createPictureReviewForm(string $itemUUID): void
    {
        global $gCurrentSession, $gL10n;
        // show form
        $form = new FormPresenter(
            'adm_review_picture_form',
            'modules/inventory.new-item-picture.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_picture_save', 'item_uuid' => $itemUUID)),
            $this
        );
        $form->addCustomContent(
            'item_picture_current',
            $gL10n->get('SYS_INVENTORY_ITEM_PICTURE_CURRENT'),
            '<img class="imageFrame" src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_picture_show', 'item_uuid' => $itemUUID)) . '" alt="' . $gL10n->get('SYS_INVENTORY_ITEM_PICTURE_CURRENT') . '" />'
        );
        $form->addCustomContent(
            'item_picture_new',
            $gL10n->get('SYS_INVENTORY_ITEM_PICTURE_NEW'),
            '<img class="imageFrame" src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('mode' => 'item_picture_show', 'item_uuid' => $itemUUID, 'new_picture' => 1)) . '" alt="' . $gL10n->get('SYS_INVENTORY_ITEM_PICTURE_NEW') . '" />'
        );
        $form->addSubmitButton(
            'adm_button_save',
            $gL10n->get('SYS_APPLY'),
            array('icon' => 'bi-upload', 'class' => 'offset-sm-3')
        );
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }
}
