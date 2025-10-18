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
     * The log entries are: record ID for ind_id, but uuid and link point to User id.
     * log_field is the inf_id and log_field_name is the fields external name.
     *
     * @param string $oldval previous value before the change (can be null)
     * @param string $newval new value after the change (can be null)
     * @return true returns **true** if no error occurred
     */
    protected function logItemfieldChange(?string $oldval = null, ?string $newval = null): bool
    {
        global $gDb, $gProfileFields;

        if ($oldval === $newval) {
            // No change, nothing to log
            return true;
        }

        $table = str_replace(TABLE_PREFIX . '_', '', $this->tableName);

        $itemID = $this->getValue('ind_ini_id');
        $item = new Item($gDb, $this->mItemsData, $itemID);
        $itemUUID = $item->getValue('ini_uuid');

        $id = $this->dbColumns[$this->keyColumnName];
        $field = $this->getValue('ind_inf_id');
        $fieldName = 'ind_value';
        $objectName = $this->mItemsData->getPropertyById($field, 'inf_name', 'database');
        $fieldNameIntern = $this->mItemsData->getPropertyById($field, 'inf_name_intern', 'database');
        $infType = $this->mItemsData->getPropertyById($field, 'inf_type');
        $itemName = $this->mItemsData->getValue('ITEMNAME', 'database');

        if ($infType === 'CATEGORY') {
            // Category changes are logged in the inventory items table
            return true;
        } elseif ($infType === 'DROPDOWN' || $infType === 'DROPDOWN_MULTISELECT' || $infType === 'DROPDOWN_DATE_INTERVAL' || $infType === 'RADIOBUTTON') {
            $vallist = $this->mItemsData->getProperty($fieldNameIntern, 'ifo_inf_options');
            if (isset($vallist[$oldval])) {
                $oldval = $vallist[$oldval];
            }
            if (isset($vallist[$newval])) {
                $newval = $vallist[$newval];
            }
        } elseif ($infType === 'CHECKBOX') {
            $fieldName = $fieldName . '_bool';
        } elseif ($infType === 'TEXT') {
            if ($fieldNameIntern === 'ITEMNAME' && $itemName === '') {
                $itemName = $newval;
            } elseif ($fieldNameIntern === 'KEEPER') {
                $fieldName = $fieldName . '_usr';
            } elseif ($fieldNameIntern === 'LAST_RECEIVER') {
                $user = new User($this->db, $gProfileFields);
                if (is_numeric($oldval) && is_numeric($newval)) {
                    $foundOld = $user->readDataById($oldval);
                    $foundNew = $user->readDataById($newval);
                    if ($foundOld && $foundNew) {
                        $fieldName = $fieldName . '_usr';
                    }
                } elseif (is_numeric($oldval)) {
                    if ($user->readDataById($oldval)) {
                        $oldval = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                    }
                } elseif (is_numeric($newval)) {
                    if ($user->readDataById($newval)) {
                        $newval = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $user->getValue('usr_uuid'))) . '">' . $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME') . '</a>';
                    }
                }
            }
        } elseif ($infType === 'DATE') {
            $fieldName = $fieldName . '_date';
        } elseif ($infType === 'EMAIL') {
            $fieldName = $fieldName . '_mail';
        } elseif ($infType === 'URL') {
            $fieldName = $fieldName . '_url';
        } elseif ($infType === 'ICON') {
            $fieldName = $fieldName . '_icon';
        }

        $logEntry = new LogChanges($this->db, $table);
        $logEntry->setLogModification($table, $id, $itemUUID, $objectName, $field, $fieldName, $oldval, $newval);
        /* $logEntry->setLogRelated($itemUUID, $itemName); */
        $logEntry->setLogLinkID($itemID);
        return $logEntry->save();
    }


    /**
     * Logs creation of the DB record -> For user fields, no need to log anything as
     * the actual value change from NULL to something will be logged as a modification
     * immediately after creation, anyway.
     *
     * @return true Returns **true** if no error occurred
     * @throws Exception
     */
    public function logCreation(): bool
    {
        return true;
    }

    /**
     * Logs deletion of the DB record
     * Deletion actually means setting the user field to an empty value, so log a change to empty instead of deletion!
     *
     * @return true Returns **true** if no error occurred
     */
    public function logDeletion(): bool
    {
        $oldval = $this->columnsInfos['ind_value']['previousValue'];
        return $this->logItemfieldChange($oldval, null);
    }


    /**
     * Logs all modifications of the DB record
     * @param array $logChanges Array of all changes, generated by the save method
     * @return true Returns **true** if no error occurred
     * @throws Exception
     */
    public function logModifications(array $logChanges): bool
    {
        if ($logChanges['ind_value']) {
            return $this->logItemfieldChange($logChanges['ind_value']['oldValue'], $logChanges['ind_value']['newValue']);
        } else {
            // Nothing to log at all!
            return true;
        }
    }
}
