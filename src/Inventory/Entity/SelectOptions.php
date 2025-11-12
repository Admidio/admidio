<?php

namespace Admidio\Inventory\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Changelog\Entity\LogChanges;

/**
 * @brief Class manages access to database table adm_inventory_field_selection_options.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SelectOptions extends Entity
{
    public const MOVE_UP = 'UP';
    public const MOVE_DOWN = 'DOWN';
    protected int $infId = 0;
    protected array $optionValues = array();

    /**
     * Constructor that will create an object of a recordset of the table adm_field_selection_options.
     * If the id is set than the specific weblink will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $infId The recordset of the weblink with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $infId = 0)
    {
        $this->infId = $infId;
        // read also data of assigned field
        $this->connectAdditionalTable(TBL_INVENTORY_FIELDS, 'inf_id', $infId);

        parent::__construct($database, TBL_INVENTORY_FIELD_OPTIONS, 'ifo');
        $this->readDataByFieldId($this->infId);
    }

    /**
     * Read all options of the select field with the id $infId from the database and store them in the internal array $optionValues.
     * The values are stored in the array with their ID as key.
     * If no id is set than no data will be read.
     * @param int $infId The ID of the select field to read the options for. If 0 then the id of the object will be used.
     * @throws Exception
     */
    public function readDataByFieldId(int $infId = 0): void
    {
        // if no id is set then use the id of the object
        if ($infId > 0) {
            $this->infId = $infId;
        }

        // reset the internal array with the option values
        $this->optionValues = array();

        // if id is set than read the data of the recordset
        if ($this->infId > 0) {
            $sql = 'SELECT ifo_id, ifo_value, ifo_system, ifo_sequence, ifo_obsolete
                    FROM ' . TBL_INVENTORY_FIELD_OPTIONS . '
                    WHERE ifo_inf_id = ? -- $infId
                    ORDER BY ifo_sequence';
            $pdoStatement = $this->db->queryPrepared($sql, array($this->infId));
            $optionsList = $pdoStatement->fetchAll();
            foreach ($optionsList as $row) {
                $this->optionValues[$row['ifo_id']] = $row;
            }
        }
    }

    /**
     * Reads the data of the select option with the given ID from the database.
     * If the ID is not an option of the select field then false will be returned.
     * @param int $id The ID of the option to read.
     * @return bool Returns true if the data could be read, otherwise false.
     * @throws Exception
     */
    public function readDataById(int $id): bool
    {
        // check if the id is an option of the select field
        if (isset($this->optionValues[$id])) {
            return parent::readDataById($id);
        } else {
            return false;
        }
    }

    /**
     * Returns an array with all options of the select field.
     * If the parameter $format is set to 'database' then the values will be returned with their database column names.
     * If the parameter $format is not set or empty then the values will be returned with their value.
     * @param bool $withObsoleteEntries If set to **false** then the obsolete entries of the profile field will not be considered.
     * @return array Returns an array with all options of the select field.
     */
    public function getAllOptions(bool $withObsoleteEntries = true): array
    {
        $values = array();
        if (!empty($this->optionValues)) {
            // if format is not database than return the values with their value
            foreach ($this->optionValues as $value) {
                // if obsolete entries should not be returned then skip them
                if (!$withObsoleteEntries && $value['ifo_obsolete']) {
                    continue;
                }
                $values[$value['ifo_id']] = array(
                    'id' => $value['ifo_id'],
                    'value' => $value['ifo_value'],
                    'system' => $value['ifo_system'],
                    'sequence' => $value['ifo_sequence'],
                    'obsolete' => $value['ifo_obsolete']
                );
            }
        }
        return $values;
    }

    /**
     * Checks if an option with the given ID is used in the database.
     * This is used to check if an option can be deleted or not.
     * @param int $ifoId The ID of the option to check.
     * @return bool Returns true if the option is used in the database, otherwise false.
     * @throws Exception
     */
    public function isOptionUsed(int $ifoId): bool
    {
        if ($this->infId > 0) {
            $sql = 'SELECT COUNT(*) FROM ' . TBL_INVENTORY_ITEMS . '
                WHERE ini_status = ? -- $ifoId
                ';
            $stmt = $this->db->queryPrepared($sql, array($ifoId));
            return ((int)$stmt->fetchColumn() > 0);
        } else {
            // if no infId is set then it is a new profile field and no options are used in the database
            return false;
        }
    }

    /**
     * Deletes an option from the select field.
     * The option will be removed from the database and the internal array of options.
     * @param int $ifoId The ID of the option to delete.
     * @return bool Returns true if the option could be deleted, otherwise false.
     * @throws Exception
     */
    public function deleteOption(int $ifoId): bool
    {
        if ($this->infId > 0) {
            // delete the option from the database
            $sql = 'DELETE FROM ' . TBL_INVENTORY_FIELD_OPTIONS . '
                    WHERE ifo_id = ? -- option ID
                    AND ifo_inf_id = ? -- $infId';
            $this->db->queryPrepared($sql, array($ifoId, $this->infId));

            // remove the option from the internal array
            unset($this->optionValues[$ifoId]);
            return true;
        } else {
            // if no infId is set then it is a new profile field and no options
            return false;
        }
    }

    /**
     * Option will change the complete sequence.
     * @param array $sequence the new sequence of options (option IDs)
     * @return bool Return true if the sequence of the options could be changed, otherwise false.
     * @throws Exception
     */
    public function setSequence(array $sequence): bool
    {
        $ifoID = $this->getValue('ifo_id');

        $sql = 'UPDATE ' . TBL_INVENTORY_FIELD_OPTIONS . '
                   SET ifo_sequence = ? -- new order sequence
                 WHERE ifo_id     = ? -- option ID;
            ';

        $newSequence = -1;
        foreach ($sequence as $id => $pos) {
            if ($id == $ifoID) {
                // Store position for later update
                $newSequence = $pos + 1;
            } else {
                $this->db->queryPrepared($sql, array($pos + 1, $id));
            }
        }

        if ($newSequence > 0) {
            $this->setValue('ifo_sequence', $newSequence);
        }
        return $this->save();
    }

    /**
     * Set the values of the options of a dropdown, radio button or multiselect field.
     * The values are stored in the database and the sequence of the options is updated.
     * @param array $newValues Array with new values for the options. The key is the option ID and the value is an array with the new values.
     * @return bool Returns true if the values could be saved, otherwise false.
     * @throws Exception
     */
    public function setOptionValues(array $newValues): bool
    {
        $ret = true;
        $newOption = false;
        $arrValues = array();
        // first save the new values of the options
        foreach ($newValues as $id => $values) {
            if ($this->readDataById($id)) {
                foreach ($values as $key => $value) {
                    $this->setValue('ifo_' . $key, $value);
                }
                $ret = $this->save();
                $arrValues[$id] = $values;
            } else {
                $newOption = true;
                $option = new SelectOptions($this->db);

                // if the options value does not exist then create a new entry
                $option->setValue('ifo_inf_id', $this->infId);

                foreach ($values as $key => $value) {
                    $option->setValue('ifo_' . $key, $value);
                }
                $ret = $option->save();

                // update the ID of the new option in the array
                $newId = $option->getValue('ifo_id');
                $arrValues[$newId] = $values;
            }
        }

        // if new Options were added then the sequence of the options must be updated
        if ($newOption) {
            $this->readDataByFieldId($this->infId);
        }
        // now change the sequence of the options
        $allOptions = $this->getAllOptions(); // load all options of the options

        // determinate current sequence based on allOptions sequence values
        $currentSequence = array();
        foreach ($allOptions as $option) {
            $currentSequence[$option['id']] = $option['sequence'] - 1; // -1 because sequence starts with 1 in database
            if ($option['system'] == 1) {
                $lastSystemSequence = $option['sequence'];
            }
        }
        // determinate new sequence based on array position
        $newSequence = array();

        // if there are system options then start the sequence after the last system option
        $sequence = $lastSystemSequence ?? 0;

        // if there are already other options defined and these options are not in the new values array
        // then add them to the new sequence to keep them
        foreach ($allOptions as $option) {
            if (!isset($arrValues[$option['id']])) {
                $newSequence[$option['id']] = $sequence++;
            }
        }
        // now add all options from the new values array to the new sequence
        foreach ($arrValues as $id => $values) {
            $newSequence[$id] = $sequence++;
        }

        // check if the sequence of the options has changed
        if ($currentSequence !== $newSequence) {
            // if the sequence has changed then update the sequence of the options
            $this->readDataById(array_key_first($newSequence));
            $this->setSequence($newSequence);
        }

        return $ret;
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
        $this->db->startTransaction();

        if ($this->newRecord) {
            $this->infId = (int)$this->dbColumns['ifo_inf_id'];
            // Determine the highest sequence number of the option when inserting
            $sql = 'SELECT COUNT(*) AS count
                      FROM ' . TBL_INVENTORY_FIELD_OPTIONS . '
                     WHERE ifo_inf_id = ? -- $this->infId';
            $countOptionsStatement = $this->db->queryPrepared($sql, array($this->infId));

            $this->setValue('ifo_sequence', (int)$countOptionsStatement->fetchColumn() + 1);
        }

        $returnValue = parent::save($updateFingerPrint);

        $this->db->endTransaction();

        // read all options again to update the internal cache
        if ($returnValue) {
            $this->readDataByFieldId();
        }

        return $returnValue;
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
        return array_merge(parent::getIgnoredLogColumns(), ['ifo_inf_id']);
    }

    /**
     * Adjust the changelog entry for this db record: Add the first forum post as a related object
     * @param LogChanges $logEntry The log entry to adjust
     * @return void
     * @throws Exception
     */
    protected function adjustLogEntry(LogChanges $logEntry): void
    {
        $itemField = new ItemField($this->db);

        $fieldId = $this->getValue('ifo_inf_id');
        $id = $this->dbColumns[$this->keyColumnName];
        $itemField->readDataById($fieldId);
        $uuid = $itemField->getValue('inf_uuid', 'database');
        $fieldName = $itemField->getValue('inf_name', 'database');

        $logEntry->setValue('log_record_name', $fieldName);
        $logEntry->setValue('log_record_id', $id);
        $logEntry->setValue('log_record_uuid', $uuid);
        $logEntry->setValue('log_related_name', $this->getValue('ifo_value'));
    }
}
