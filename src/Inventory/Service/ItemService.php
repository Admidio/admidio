<?php

namespace Admidio\Inventory\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\Inventory\ValueObjects\ItemsData;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ItemService
{
    protected ItemsData $itemRessource;
    protected Database $db;
    protected string $itemUUID;
    protected int $postCopyField;
    protected int $postCopyNumber;
    protected int $postImported;

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $profileFieldUUID UUID if the profile field that should be managed within this class
     * @throws Exception
     */
    public function __construct(Database $database, string $itemUUID = '', int $postCopyField = 0, int $postCopyNumber = 1, int $postImported = 0)
    {
        global $gCurrentOrgId;

        $this->db = $database;
        $this->itemUUID = $itemUUID;
        $this->postCopyField = $postCopyField;
        $this->postCopyNumber = $postCopyNumber;
        $this->postImported = $postImported;

        $this->itemRessource = new ItemsData($database, $gCurrentOrgId);
        $this->itemRessource->readItemData($itemUUID);
    }

    /**
     * Marks the item as retired.
     *
     * @throws Exception
     */
    public function retireItem(): void
    {
        $this->itemRessource->retireItem();

        // Send notification to all users
        $this->itemRessource->sendNotification();
    }

    /**
     * Reverts the item to its previous state.
     * @throws Exception
     */
    public function reinstateItem(): void
    {
        $this->itemRessource->reinstateItem();

        // Send notification to all users
        $this->itemRessource->sendNotification();
    }

    /**
     * Delete the current profile field form into the database.
     * 
     * @throws Exception
     */
    public function delete(): void
    {
        $this->itemRessource->deleteItem();

        // Send notification to all users
        $this->itemRessource->sendNotification();
    }

    /**
     * Save data from the profile field form into the database.
     * @param bool $multiEdit If true, the form is used for multi-editing of items.
     * @throws Exception
     */
    public function save(bool $multiEdit = false): void
    {
        global $gCurrentSession, $gL10n, $gSettingsManager;

        // check form field input and sanitized it from malicious content
        if (!$this->postImported)
        {
            $itemFieldsEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $itemFieldsEditForm->validate($_POST, $multiEdit);
        }
        else
        {
            $formValues = $_POST;
        }

        $startIdx = 1;
        if ($this->postCopyField > 0) {
            foreach ($this->itemRessource->getItemFields() as $itemField) {
                if ($itemField->getValue('inf_id') == $this->postCopyField) {
                    $itemCopyFieldName = $itemField->getValue('inf_name_intern');
                    break;
                }      
            }

            if (isset($itemCopyField)) {
                $startIdx = (int)$formValues['INF-' . $itemCopyFieldName] + 1;
            }
        }
        $stopIdx = $startIdx + $this->postCopyNumber;

        for ($i = $startIdx; $i < $stopIdx; ++$i) {
            if (isset($itemCopyFieldName)) {
                $formValues['INF-' . $itemCopyFieldName] = $i;
            }

            $this->itemRessource->readItemData($this->itemUUID);

            if ($this->itemUUID === '') {
                $this->itemRessource->createNewItem($formValues['INF-CATEGORY']);
            }

            // check all item fields
            foreach ($this->itemRessource->getItemFields() as $itemField) {
                $infNameIntern = $itemField->getValue('inf_name_intern');
                $postKey = 'INF-' . $infNameIntern;

                if (isset($formValues[$postKey])) {
                    if (is_array($formValues[$postKey]) && empty($formValues[$postKey])) {
                        throw new Exception($gL10n->get('SYS_FIELD_EMPTY', array($itemField->getValue('inf_name'))));
                    } elseif (is_string($formValues[$postKey]) && (strlen($formValues[$postKey]) === 0 && $itemField->getValue('inf_required_input') == 1)) {
                        throw new Exception($gL10n->get('SYS_FIELD_EMPTY', array($itemField->getValue('inf_name'))));
                    }

                    if ($itemField->getValue('inf_type') === 'DATE' && $gSettingsManager->get('inventory_field_date_time_format') == 'datetime') {
                        // Check if time is set separately
                        isset($formValues[$postKey . '_time']) ? $dateValue = $formValues[$postKey] . ' ' . $formValues[$postKey . '_time'] : $dateValue = $formValues[$postKey];

                        // Write value from field to the item class object with time
                        if (!$this->itemRessource->setValue($infNameIntern, $dateValue)) {
                            throw new Exception($gL10n->get('SYS_DATABASE_ERROR'), $gL10n->get('SYS_ERROR'));
                        }
                    } else {
                        // Write value from field to the item class object
                        if (!$this->itemRessource->setValue($infNameIntern, $formValues[$postKey])) {
                            throw new Exception($gL10n->get('SYS_DATABASE_ERROR'), $gL10n->get('SYS_ERROR'));
                        }
                    }
                } elseif ($itemField->getValue('inf_type') === 'CHECKBOX' && !$multiEdit) {
                    // Set value to '0' for unchecked checkboxes
                    $this->itemRessource->setValue($itemField->getValue('inf_name_intern'), '0');
                }
            }

            // save item data
            $this->itemRessource->saveItemData();
        }

        //mark item as imported to prevent notification
        if ($this->postImported == 1) {
            $this->itemRessource->setImportedItem();
        }

        // Send notification to all users
        $this->itemRessource->sendNotification();
    }
}
