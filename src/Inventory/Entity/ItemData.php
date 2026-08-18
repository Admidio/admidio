<?php

namespace Admidio\Inventory\Entity;

// Admidio namespaces
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Changelog\Entity\LogChanges;

/**
 * @brief Class manages access to database table adm_files
 *
 * With the given ID a file object is created from the data in the database table **adm_files**.
 * The class will handle the communication with the database and give easy access to the data. New
 * file could be created or existing file could be edited. Special properties of
 * data like save urls, checks for evil code or timestamps of last changes will be handled within this class.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ItemData extends Entity
{
    /**
     * @var ItemsData object with current item field structure
     */
    protected ItemsData $mItemsData;

    /**
     * Constructor that will create an object of a recordset of the table adm_user_data.
     * If the id is set than the specific profile field will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $id The id of the profile field. If 0, an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, ?ItemsData $itemsData = null, int $id = 0)
    {
        global $gCurrentOrgId;
        if ($itemsData !== null) {
            $this->mItemsData = clone $itemsData; // create explicit a copy of the object (param is in PHP5 a reference)
        } else {
            $this->mItemsData = new ItemsData($database, $gCurrentOrgId);
        }

        $this->connectAdditionalTable(TBL_INVENTORY_FIELDS, 'inf_id', 'ind_inf_id');
        parent::__construct($database, TBL_INVENTORY_ITEM_DATA, 'ind', $id);
    }

    /**
     * Since creation means setting value from NULL to something, deletion mean setting the field to empty,
     * we need one generic change log function that is called on creation, deletion and modification.
     *
     * The log entries are: record ID for ind_id, but uuid, name and link point to the item.
     * log_field is the inf_id and log_field_name is the fields external name.
     *
     * @param string|null $oldval previous value before the change (can be null)
     * @param string|null $newval new value after the change (can be null)
     * @return bool returns **true** if no error occurred
     * @throws Exception
     */
    protected function logItemfieldChange(?string $oldval = null, ?string $newval = null): bool
    {
        if ($oldval === $newval) {
            // No change, nothing to log
            return true;
        }

        $field = (int)$this->getValue('ind_inf_id');
        $fieldNameIntern = $this->mItemsData->getPropertyById($field, 'inf_name_intern', 'database');

        // The category and the status of an item are stored in the item record itself,
        // so their changes are logged for the inventory_items table instead.
        if (in_array($fieldNameIntern, array('CATEGORY', 'STATUS'), true)) {
            return true;
        }

        // The creation of an item is already logged with its name, so the initial setting of the
        // name must not be logged as a change of the item as well.
        if ($fieldNameIntern === 'ITEMNAME' && $this->mItemsData->isNewItem()) {
            return true;
        }

        $table = str_replace(TABLE_PREFIX . '_', '', $this->tableName);

        $itemID = (int)$this->getValue('ind_ini_id');
        $item = new Item($this->db, $this->mItemsData, $itemID);

        $id = $this->dbColumns[$this->keyColumnName];
        $fieldName = $this->mItemsData->getPropertyById($field, 'inf_name', 'database');

        $logEntry = new LogChanges($this->db, $table);
        $logEntry->setLogModification($table, $id, $item->getValue('ini_uuid'), $item->readableName(), $field, $fieldName, $oldval, $newval);
        $logEntry->setLogLinkID($itemID);
        return $logEntry->save();
    }


    /**
     * Logs creation of the DB record -> For user fields, no need to log anything as
     * the actual value change from NULL to something will be logged as a modification
     * immediately after creation, anyway.
     *
     * @return bool Returns **true** if no error occurred
     */
    public function logCreation(): bool
    {
        return true;
    }

    /**
     * Logs deletion of the DB record
     * Deletion actually means setting the user field to an empty value, so log a change to empty instead of deletion!
     *
     * @return bool Returns **true** if no error occurred
     * @throws Exception
     */
    public function logDeletion(): bool
    {
        $oldval = $this->columnsInfos['ind_value']['previousValue'];
        return $this->logItemfieldChange($oldval, null);
    }


    /**
     * Logs all modifications of the DB record
     * @param array $logChanges Array of all changes, generated by the save method
     * @return bool Returns **true** if no error occurred
     * @throws Exception
     */
    public function logModifications(array $logChanges): bool
    {
        if (!empty($logChanges['ind_value'])) {
            return $this->logItemfieldChange($logChanges['ind_value']['oldValue'], $logChanges['ind_value']['newValue']);
        } else {
            // Nothing to log at all!
            return true;
        }
    }
}
