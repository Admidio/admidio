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
    protected string $ID;
    protected int $postCopyField;
    protected int $postCopyNumber;
    protected int $postImported;

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $profileFieldUUID UUID if the profile field that should be managed within this class
     * @throws Exception
     */
    public function __construct(Database $database, string $itemID = '', int $postCopyField = 0, int $postCopyNumber = 1, int $postImported = 0)
    {
        global $gCurrentOrgId;

        $this->db = $database;
        $this->ID = $itemID;
        $this->postCopyField = $postCopyField;
        $this->postCopyNumber = $postCopyNumber;
        $this->postImported = $postImported;

        $this->itemRessource = new ItemsData($database, $gCurrentOrgId);
        $this->itemRessource->readItemData($itemID);
    }

    /**
     * Marks the item as former.
     *
     * @throws Exception
     */
    public function makeItemFormer()
    {
        $this->itemRessource->makeItemFormer($this->ID);
    }

    /**
     * Reverts the item to its previous state.
     * @throws Exception
     */
    public function undoItemFormer()
    {
        $this->itemRessource->undoItemFormer($this->ID);
    }

    /**
     * Delete the current profile field form into the database.
     * 
     * @throws Exception
     */
    public function delete()
    {
        $this->itemRessource->deleteItem($this->ID);
    }

    /**
     * Save data from the profile field form into the database.
     * 
     * @throws Exception
     */
    public function save()
    {
        global $gCurrentSession, $gL10n, $gSettingsManager;

        // check form field input and sanitized it from malicious content
        $itemFieldsEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $itemFieldsEditForm->validate($_POST);
        
        $startIdx = 1;
        if ($this->postCopyField > 0 && isset($formValues['inf-' . $this->postCopyField])) {
            $startIdx = (int)$formValues['inf-' . $this->postCopyField] + 1;
        }
        $stopIdx = $startIdx + $this->postCopyNumber;
        
        for ($i = $startIdx; $i < $stopIdx; ++$i) {
            $formValues['inf-' . $this->postCopyField] = $i;
        
            $this->itemRessource->readItemData($this->ID);
        
            if ($this->ID == 0) {
                $this->itemRessource->getNewItemId();
            }
        
            // check all item fields
            foreach ($this->itemRessource->getItemFields() as $itemField) {
                $postId = 'inf-' . $itemField->getValue('inf_id');
        
                if (isset($formValues[$postId])) {
                    if (strlen($formValues[$postId]) === 0 && $itemField->getValue('inf_mandatory') == 1) {
                        throw new Exception($gL10n->get('SYS_FIELD_EMPTY', array(convlanguagePIM($itemField->getValue('inf_name')))));
                    }
        
                    if ($itemField->getValue('inf_type') === 'DATE' && $gSettingsManager->get('inventory_field_date_time_format') == 'datetime') {
                        // Check if time is set separately
                        isset($formValues[$postId . '_time'])? $dateValue= $formValues[$postId] . ' ' . $formValues[$postId . '_time'] : $dateValue = $formValues[$postId];
        
                        // Write value from field to the item class object with time
                        if (!$this->itemRessource->setValue($itemField->getValue('inf_name_intern'), $dateValue)) {
                            throw new Exception($gL10n->get('SYS_DATABASE_ERROR'), $gL10n->get('SYS_ERROR'));
                        }
                    }
                    else {
                        // Write value from field to the item class object
                        if (!$this->itemRessource->setValue($itemField->getValue('inf_name_intern'), $formValues[$postId])) {
                            throw new Exception($gL10n->get('SYS_DATABASE_ERROR'), $gL10n->get('SYS_ERROR'));
                        }
                    }
                }
                elseif ($itemField->getValue('inf_type') === 'CHECKBOX') {
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
        //$this->itemRessource->sendNotification();

    }
}
