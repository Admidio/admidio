<?php

namespace Admidio\Inventory\Entity;

// Admidio namespaces
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Changelog\Entity\LogChanges;
use Admidio\Users\Entity\User;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Inventory\Entity\Item;

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
class ItemBorrowData extends Entity
{
    /**
     * @var ItemsData object with current item field structure
     */
    protected ItemsData $mItemsData;

    /**
     * Constructor that will create an object of a recordset of the table adm_user_data.
     * If the id is set than the specific item will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $id The id of the item. If 0, an empty object of the table is created.
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

        parent::__construct($database, TBL_INVENTORY_ITEM_BORROW_DATA, 'inb', $id);
    }

    public function updateRecordId(int $recordId) : void
    {
        if ($recordId !== 0) {
            $this->setValue('inb_id', $recordId);
            $this->newRecord = false;
            $this->insertRecord = false;
        }
    }

    /**
     * Adjust the changelog entry for this db record: Add the first forum post as a related object
     * @param LogChanges $logEntry The log entry to adjust
     * @return void
     * @throws Exception
     */
    protected function adjustLogEntry(LogChanges $logEntry): void
    {
        global $gDb;

        $itemID = $this->getValue('inb_ini_id');
        $item = new Item($gDb, $this->mItemsData, $itemID);
        $itemUUID = $item->getValue('ini_uuid');
        $itemName = $item->readableName();

        $logEntry->setValue('log_record_name', $itemName);
        $logEntry->setValue('log_record_uuid', $itemUUID);
    }
}
