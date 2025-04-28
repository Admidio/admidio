<?php

namespace Admidio\Inventory\Entity;

// Admidio namespaces
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Inventory\Entity\Item;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Changelog\Entity\LogChanges;
use Admidio\Users\Entity\User;
use Admidio\Infrastructure\Utils\SecurityUtils;

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
     * @var Item object with current item structure
     */
    protected Item $mItem;
    /**
     * @var int the organization for which the rights are read, could be changed with method **setOrganization**
     */
    protected int $organizationId;
    /**
     * @var int the id of the item which should be loaded. If id isn't set than an empty object with no specific item is created.
     */
    protected int $itemId;
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
    public function __construct(Database $database, ?ItemsData $itemsData = null,  ?Item $item = null)
    {
        $this->changeNotificationEnabled = true;
        $this->organizationId = $GLOBALS['gCurrentOrgId'];

        if ($itemsData !== null) {
            $this->mItemsData = clone $itemsData; // create explicit a copy of the object (param is in PHP5 a reference)
        } else {
            $this->mItemsData = new ItemsData($database, $this->organizationId);
        }

        if ($item !== null) {
            $this->mItem = clone $item; // create explicit a copy of the object (param is in PHP5 a reference)
        } else {
            $this->mItem = new Item($database, $this->mItemsData);
        }

        $this->itemId = $this->mItem->getValue('ini_id', 'database');
        
        parent::__construct($database, TBL_INVENTORY_DATA, 'ind');
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
        global $gDb;
        $this->itemId = $id;
        $this->mItem = new Item($gDb, $this->mItemsData, $this->itemId);
        return parent::readDataById($id);
    }

    /**
     * Return a human-readable representation of this record.
     * If a column [prefix]_name exists, it is returned, otherwise the id.
     * This method can be overridden in child classes for custom behavior.
     * 
     * @return string The readable representation of the record (can also be a translatable identifier)
     */
    public function readableName(): string
    {
        // check if mItemsData is set
        $this->readDataByColumns(array('ind_ini_id' => $this->itemId, 'ind_inf_id' => $this->mItemsData->getProperty('ITEMNAME', 'inf_id')));
        return $this->getValue('ind_value');
    }

    /**
     * Changes to user data could be sent as a notification email to a specific role if this
     * function is enabled in the settings. If you want to suppress this logic you can
     * explicit disable it with this method for this user. So no changes to this user object will
     * result in a notification email.
     * @return void
     */
    public function disableChangeNotification()
    {
        $this->changeNotificationEnabled = false;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the name intern will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
/*         $table = $this->tableName;
        $table = str_replace(TABLE_PREFIX . '_', '', $table);

 
        $logEntry = new LogChanges($this->db);
        $logEntry->setLogModification($table, $this->keyColumnName,
            $this->mItem->getValue('ini_id', 'database'), $this->mItem->readableName(),
            'ind_ini_id', 'SYS_INVENTORY_DATA', 
            null, $this->mItemsData->getValue('ITEMNAME', 'database')
        ); */
        if ($this->newRecord) {
            // remove log entry for new record
            
        }

        return parent::save($updateFingerPrint);
    }

    /**
     * Logs Creation of the DB record -> Changes to the log table are NOT logged!
     * 
     * @return true Returns **false**, since no logging occurs
     */
    public function logCreation(): bool
    {
        return false;
    }

    /**
     * Logs deletion of the DB record -> Changes to the log table are NOT logged!
     * 
     * @return true Returns **false**, since no logging occurs
     */
    public function logDeletion(): bool
    {
        global $gDb;

        $retVal = true;
        $table = $this->tableName;
        $table = str_replace(TABLE_PREFIX . '_', '', $table);
        $id = $this->dbColumns[$this->keyColumnName];
        $record_name = $this->readableName();
        if (array_key_exists($this->columnPrefix . '_uuid', $this->dbColumns)) {
            $uuid = (string) $this->getValue($this->columnPrefix . '_uuid');
        } else {
            $uuid = null;
        }

        $logEntry = new LogChanges($this->db, $table);
        $field = 'ind_value';

        $sql = 'SELECT ind_value FROM ' . TBL_INVENTORY_DATA . ' WHERE ind_id = ? AND ind_inf_id = ?';
        $queryParameters = array($id, $this->getValue('ind_inf_id'));
        $result = $gDb->queryPrepared($sql, $queryParameters);
        while ($row = $result->fetch()) {
            $logEntry->setLogModification($table, $id, $uuid, $record_name, $field, $field, $row['ind_value'], null);
        }

        //$logEntry->setLogModification($table, $id, $uuid, $record_name, $field, $field, $this->getValue($field), null);
        $this->adjustLogEntry($logEntry);
        $retVal = $retVal && $logEntry->save();
        $logEntry->clear();
        $logEntry->saveChangesWithoutRights();

        return $retVal;
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
            ['ini_id', 'ini_org_id', 'ind_id', 'ind_inf_id', 'ind_ini_id']/* ,
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
        // get the name of the field which was changed
        $infNameIntern = $this->mItemsData->getPropertyById($this->getValue('ind_inf_id'), 'inf_name_intern');
        $infType = $this->mItemsData->getPropertyById($this->getValue('ind_inf_id'), 'inf_type');
        $itemName = $this->mItemsData->getValue('ITEMNAME', 'database');

        if ($infType === 'DROPDOWN' || $infType === 'RADIOBUTTON') {
            $vallist = $this->mItemsData->getProperty($infNameIntern, 'inf_value_list');
            if (isset($vallist[$logEntry->getValue('log_value_old')])) {
                $logEntry->setValue('log_value_old', $vallist[$logEntry->getValue('log_value_old')]);
            }
            if (isset($vallist[$logEntry->getValue('log_value_new')])) {
                $logEntry->setValue('log_value_new', $vallist[$logEntry->getValue('log_value_new')]);
            }
        }
        elseif ($infType === 'CHECKBOX') {
            $logEntry->setValue('log_field_name', $logEntry->getValue('log_field_name') . '_bool');
        }
        elseif ($infType === 'TEXT') {
            if ($infNameIntern === 'ITEMNAME' && $itemName === '') {
                $itemName = $logEntry->getValue('log_value_new');
            }
            elseif ($infNameIntern === 'KEEPER' || $infNameIntern === 'LAST_RECEIVER') {
                $logEntry->setValue('log_field_name', $logEntry->getValue('log_field_name') . '_usr');
            }
        }
        elseif ($infType === 'DATE') {
            $logEntry->setValue('log_field_name', $logEntry->getValue('log_field_name') . '_date');
        }
        elseif ($infType === 'EMAIL') {
            $logEntry->setValue('log_field_name', $logEntry->getValue('log_field_name') . '_mail');
        }
        elseif ($infType === 'URL') {
            $logEntry->setValue('log_field_name', $logEntry->getValue('log_field_name') . '_url');
        }
        elseif ($infType === 'ICON') {
            $logEntry->setValue('log_field_name', $logEntry->getValue('log_field_name') . '_icon');
        }

        $logEntry->setLogRelated($this->mItem->getValue('ini_id', 'database'), $itemName);
        $logEntry->setValue('log_record_name', $this->mItemsData->getPropertyById($this->getValue('ind_inf_id'), 'inf_name'));
        $logEntry->setLogLinkID($this->mItem->getValue('ini_id', 'database'));
    }
}
