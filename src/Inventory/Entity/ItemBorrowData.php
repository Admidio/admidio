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
     * @param int $id The id of the entry. If 0, an empty object of the table is created.
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

    /**
     * Update the record ID of this entity.
     * This method is used when the record ID is generated outside of this entity,
     * for example, after inserting a new record into the database.
     * @param int $recordId The new record ID to set.
     * @return void
     * @throws Exception
     */
    public function updateRecordId(int $recordId): void
    {
        if ($recordId !== 0) {
            $this->setValue('inb_id', $recordId);
            $this->newRecord = false;
            $this->insertRecord = false;
        }
    }

    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * Some tables contain columns _usr_id_create, timestamp_create, etc. We do not want
     * to log changes to these columns.
     * The guestbook table also contains gbc_org_id and gbc_ip_address columns,
     * which we don't want to log.
     * @return array Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(),
            ['inb_id', 'inb_ini_id']/* ,
            ($this->newRecord)?[$this->columnPrefix.'_text']:[] */
        );
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

        $itemDateNew = $logEntry->getValue('log_value_new');
        $itemDateOld = $logEntry->getValue('log_value_old');

        if ($logEntry->getValue('log_field') === 'inb_borrow_date') {
            $infNameIntern = 'BORROW_DATE';
        } else {
            $infNameIntern = 'RETURN_DATE';
        }

        // remove seconds from datetime fields
        if (!empty($itemDateNew) && str_contains($itemDateNew, ' ')) {
            $itemDateNew = substr($itemDateNew, 0, 16);
        }
        if (!empty($itemDateOld) && str_contains($itemDateNew, ' ')) {
            $itemDateOld = substr($itemDateOld, 0, 16);
        }

        $logEntry->setValue('log_value_new', $this->mItemsData->getHtmlValue($infNameIntern, $itemDateNew));
        $logEntry->setValue('log_value_old', $this->mItemsData->getHtmlValue($infNameIntern, $itemDateOld));
        $logEntry->setValue('log_record_name', $itemName);
        $logEntry->setValue('log_record_uuid', $itemUUID);
    }
}
