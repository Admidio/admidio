<?php

namespace Admidio\Inventory\Entity;

// Admidio namespaces
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
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
class Item extends Entity
{
    /**
     * @var ItemsData object with current item field structure
     */
    protected ItemsData $mItemsData;
    /**
     * @var int the organization for which the rights are read, could be changed with method **setOrganization**
     */
    protected int $organizationId;
    /**
     * @var int the id of the item which should be loaded. If id isn't set than an empty object with no specific item is created.
     */
    protected int $itemId;
    /**
     * @var string the uuid of the item which should be loaded. If uuid isn't set than an empty object with no specific item is created.
     */
    protected string $itemUUID = '';
    /**
     * @var bool Flag if the changes to the item data should be handled by the ChangeNotification service.
     */
    protected bool $changeNotificationEnabled;

    /**
     * Constructor that will create an object of a recordset of the users table.
     * If the id is set than this recordset will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param ItemsData|null $itemFields An object of the ItemsData class with the profile field structure
     *                                  of the current organization.
     * @param int $itemId The id of the item which should be loaded. If id isn't set than an empty
     *                                  object with no specific item is created.
     * @throws Exception
     */
    public function __construct(Database $database, ?ItemsData $itemsData = null, int $itemId = 0)
    {
        $this->changeNotificationEnabled = true;

        if ($itemsData !== null) {
            $this->mItemsData = clone $itemsData; // create explicit a copy of the object (param is in PHP5 a reference)
        } else {
            $this->mItemsData = new ItemsData($database, $GLOBALS['gCurrentOrgId']);
        }

        $this->organizationId = $GLOBALS['gCurrentOrgId'];
        $this->itemId = $itemId;

        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'ini_cat_id');

        parent::__construct($database, TBL_INVENTORY_ITEMS, 'ini', $itemId);
    }

    /**
     * Changes to user data could be sent as a notification email to a specific role if this
     * function is enabled in the settings. If you want to suppress this logic you can
     * explicit disable it with this method for this user. So no changes to this user object will
     * result in a notification email.
     * @return void
     */
    public function disableChangeNotification(): void
    {
        $this->changeNotificationEnabled = false;
    }

    /**
     * Reads a record out of the table in database selected by the unique id column in the table.
     * Per default all columns of the default table will be read and stored in the object.
     * @param int $id Unique id of id column of the table.
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readData
     * @see Entity#readDataByUuid
     */
    public function readDataById(int $id): bool
    {
        $this->itemId = $id;
        $ret = parent::readDataById($id);
        if ($ret) {
            $this->itemId = $id;
            $this->itemUUID = $this->getValue('ini_uuid');
        }
        return $ret;
    }

    public function readDataByUuid(string $uuid): bool
    {
        $ret = parent::readDataByUuid($uuid);
        if ($ret) {
            $this->itemUUID = $uuid;
            $this->itemId = $this->getValue('ini_id');
        }
        return $ret;
    }

    /**
     * Return a human-readable representation of this record.
     * If a column [prefix]_name exists, it is returned, otherwise the id.
     * This method can be overridden in child classes for custom behavior.
     *
     * @return string The readable representation of the record (can also be a translatable identifier)
     * @throws Exception
     */
    public function readableName(): string
    {
        global $gDb;
        // check if mItemsData is set
        $itemData = new ItemData($gDb, $this->mItemsData);
        $itemData->readDataByColumns(array('ind_ini_id' => $this->itemId, 'ind_inf_id' => $this->mItemsData->getProperty('ITEMNAME', 'inf_id')));
        return $itemData->getValue('ind_value');
    }

    /**
     * Get the status of the item.
     * @return int The status of the item.
     * @throws Exception
     */
    public function getStatus(): int
    {
        return $this->getValue('ini_status');
    }

    /**
     * Check if the item is retired.
     * @return bool Returns true if the item is retired, false otherwise.
     * @throws Exception
     */
    public function isRetired(): bool
    {
        global $gDb;
        $optionId = $this->getStatus();
        $option = new SelectOptions($gDb, $this->mItemsData->getProperty('STATUS', 'inf_id'));
        if ($option->readDataById($optionId)) {
            return $option->getValue('ifo_value') === 'SYS_INVENTORY_FILTER_RETIRED_ITEMS';
        }
        return false;
    }

    /**
     * Check if the item is in use.
     * @return bool Returns true if the item is in use, false otherwise.
     * @throws Exception
     */
    public function isInUse(): bool
    {
        global $gDb;
        $optionId = $this->getStatus();
        $option = new SelectOptions($gDb, $this->mItemsData->getProperty('STATUS', 'inf_id'));
        if ($option->readDataById($optionId)) {
            return $option->getValue('ifo_value') === 'SYS_INVENTORY_FILTER_IN_USE_ITEMS';
        }
        return false;
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
            ['ini_id', 'ini_uuid', 'ini_org_id', 'ind_id', 'ind_inf_id', 'ind_ini_id']/* ,
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
        $itemName = $this->mItemsData->getValue('ITEMNAME', 'database');
        if (isset($_POST['INF-ITEMNAME']) && $itemName === '') {
            $itemName = $_POST['INF-ITEMNAME'];
        } elseif (!isset($_POST['INF-ITEMNAME']) && $itemName === '') {
            $itemName = $logEntry->getValue('log_record_name');
        }

        // If the item status is changed convert the status id to the actual status text
        if ($logEntry->getValue('log_field') === 'ini_status') {
            global $gDb;
            $itemStatusIdNew = (int)$logEntry->getValue('log_value_new');
            $itemStatusIdOld = (int)$logEntry->getValue('log_value_old');
            $option = new SelectOptions($gDb, $this->mItemsData->getProperty('STATUS', 'inf_id'));
            if ($option->readDataById($itemStatusIdNew)) {
                $logEntry->setValue('log_value_new', Language::translateIfTranslationStrId($option->getValue('ifo_value')));
            }
            if ($option->readDataById($itemStatusIdOld)) {
                $logEntry->setValue('log_value_old', Language::translateIfTranslationStrId($option->getValue('ifo_value')));
            }
        }

        $logEntry->setValue('log_record_name', $itemName);
        $logEntry->setValue('log_related_id', $logEntry->getValue('log_record_id'));
    }
}
