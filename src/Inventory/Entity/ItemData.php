<?php

namespace Admidio\Inventory\Entity;

// Admidio namespaces
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Changelog\Entity\LogChanges;
use Admidio\Changelog\Service\ChangelogService;

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

        if (!self::$loggingEnabled) return false;

        $table = str_replace(TABLE_PREFIX . '_', '', $this->tableName);
        if (!ChangelogService::isTableLogged($table)) return false;

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
     * Write one changelog entry for every item value that the given condition selects. Deleting a
     * value means setting the item field to empty, so the entries are modifications to an empty
     * value, exactly like logDeletion() writes them for a single record.
     *
     * A value is logged with the item it belongs to. Reading an Item object per record would read
     * the name of the same item again and again, and deleting an item field affects every item, so
     * the items are read once as a whole.
     *
     * @param array $identifyingColumns The columns that identify a single value. They are not
     *                                  needed here, the whole record is read in one query anyway.
     * @param string $sqlWhereCondition Condition that selects the values, without the leading
     *                                  keyword WHERE and only with columns of adm_inventory_item_data.
     * @param array $queryParams Values of the prepared parameters of the condition.
     * @return int Returns the number of written log entries.
     * @throws Exception
     */
    public function logBulkDeletion(array $identifyingColumns, string $sqlWhereCondition, array $queryParams = array()): int
    {
        if (!self::$loggingEnabled) return 0;
        $table = str_replace(TABLE_PREFIX . '_', '', $this->tableName);
        if (!ChangelogService::isTableLogged($table)) return 0;

        $sql = 'SELECT ind_id, ind_ini_id, ind_inf_id, ind_value
                  FROM ' . TBL_INVENTORY_ITEM_DATA . '
                 WHERE ' . $sqlWhereCondition;
        $records = $this->db->queryPrepared($sql, $queryParams)->fetchAll(\PDO::FETCH_ASSOC);

        // A value that is empty already is no change, and the category and the status of an item
        // are logged for the inventory_items table instead, see logItemfieldChange().
        $records = array_values(array_filter($records, function (array $record) {
            if ($record['ind_value'] === null || $record['ind_value'] === '') {
                return false;
            }
            $fieldNameIntern = $this->mItemsData->getPropertyById((int)$record['ind_inf_id'], 'inf_name_intern', 'database');
            return !in_array($fieldNameIntern, array('CATEGORY', 'STATUS'), true);
        }));
        if (count($records) === 0) {
            return 0;
        }

        $items = $this->readItemsOfRecords(array_column($records, 'ind_ini_id'));
        $logEntries = 0;

        foreach ($records as $record) {
            $itemId = (int)$record['ind_ini_id'];
            $fieldId = (int)$record['ind_inf_id'];

            $logEntry = new LogChanges($this->db, $table);
            $logEntry->setLogModification(
                $table,
                (int)$record['ind_id'],
                $items[$itemId]['ini_uuid'] ?? null,
                $items[$itemId]['name'] ?? (string)$record['ind_id'],
                $fieldId,
                $this->mItemsData->getPropertyById($fieldId, 'inf_name', 'database'),
                $record['ind_value'],
                null
            );
            $logEntry->setLogLinkID($itemId);
            $logEntry->save();
            $logEntry->clear();
            $logEntries++;
        }

        return $logEntries;
    }

    /**
     * Read the uuid and the name of the given items in one query, so that a bulk deletion does not
     * create an Item object for every single record. The name of an item is a value of the item
     * itself, so it is read from the item data of the field ITEMNAME.
     *
     * @param array $itemIds The ids of the items, duplicates are allowed.
     * @return array Returns a named array of item id => array with the keys **ini_uuid** and **name**
     * @throws Exception
     */
    private function readItemsOfRecords(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        if (count($itemIds) === 0) {
            return array();
        }

        $sql = 'SELECT ini_id, ini_uuid, item_name.ind_value AS item_name
                  FROM ' . TBL_INVENTORY_ITEMS . '
             LEFT JOIN ' . TBL_INVENTORY_ITEM_DATA . ' AS item_name
                    ON item_name.ind_ini_id = ini_id
                   AND item_name.ind_inf_id = ? -- $this->mItemsData->getProperty(\'ITEMNAME\', \'inf_id\')
                 WHERE ini_id IN (' . Database::getQmForValues($itemIds) . ')';
        $queryParams = array_merge(array((int)$this->mItemsData->getProperty('ITEMNAME', 'inf_id')), $itemIds);
        $statement = $this->db->queryPrepared($sql, $queryParams);

        $items = array();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $items[(int)$row['ini_id']] = array(
                'ini_uuid' => $row['ini_uuid'],
                'name' => (string)$row['item_name']
            );
        }

        return $items;
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
