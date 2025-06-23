<?php
namespace Admidio\ProfileFields\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Changelog\Entity\LogChanges;
use Admidio\ProfileFields\Entity\ProfileField;

/**
 * @brief Class manages access to database table adm_field_selection_options.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SelectOptions extends Entity
{
    public const MOVE_UP   = 'UP';
    public const MOVE_DOWN = 'DOWN';
    protected int $usfId = 0;
    protected array $optionValues = array();
    /**
     * Constructor that will create an object of a recordset of the table adm_field_selection_options.
     * If the id is set than the specific weblink will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $usfId The recordset of the weblink with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $usfId = 0)
    {
        $this->usfId = $usfId;
        // read also data of assigned field
        $this->connectAdditionalTable(TBL_USER_FIELDS, 'usf_id', $usfId);

        parent::__construct($database, TBL_USER_FIELD_OPTIONS, 'ufo');
        $this->readDataByFieldId($this->usfId);
    }

    /**
     * Read all options of the select field with the id $usfId from the database and store them in the internal array $optionValues.
     * The values are stored in the array with their ID as key.
     * If no id is set than no data will be read.
     * @throws Exception
     */
    public function readDataByFieldId(int $usfId = 0) : void
    {
        // if no id is set then use the id of the object
        if ($usfId > 0) {
            $this->usfId = $usfId;
        }

        // reset the internal array with the option values
        $this->optionValues = array();

        // if id is set than read the data of the recordset
        if ($this->usfId > 0) {
            $sql = 'SELECT ufo_id, ufo_value, ufo_sequence, ufo_obsolete
                    FROM ' . TBL_USER_FIELD_OPTIONS . '
                    WHERE ufo_usf_id = ? -- $usfId
                    ORDER BY ufo_sequence';
            $pdoStatement = $this->db->queryPrepared($sql, array($this->usfId));
            $optionsList = $pdoStatement->fetchAll();
            foreach ($optionsList as $row) {
                $this->optionValues[$row['ufo_id']] = $row;
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
    public function readDataById(int $id) : bool
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
     * @param bool $withObsoleteEnries If set to **false** then the obsolete entries of the profile field will not be considered.
     * @return array Returns an array with all options of the select field.
     */
    public function getAllOptions(bool $withObsoleteEnries = true) : array
    {
        $values = array();
        if (!empty($this->optionValues)) {
            // if format is not database than return the values with their value
            foreach ($this->optionValues as $value) {
                // if obsolete entries should not be returned then skip them
                if (!$withObsoleteEnries && $value['ufo_obsolete']) {
                    continue;
                }
                $values[$value['ufo_id']] = array(
                    'id' => $value['ufo_id'],
                    'value' => $value['ufo_value'],
                    'sequence' => $value['ufo_sequence'],
                    'obsolete' => $value['ufo_obsolete']
                );
            }
        }
        return $values;
    }

    /**
     * Checks if an option with the given ID is used in the database.
     * This is used to check if an option can be deleted or not.
     * @param int $ufoId The ID of the option to check.
     * @return bool Returns true if the option is used in the database, otherwise false.
     */
    public function isOptionUsed(int $ufoId) : bool
    {
        if ($this->usfId > 0) {
            $sql = 'SELECT COUNT(*) FROM ' . TBL_USER_DATA . '
                WHERE usd_value = ?
                  AND usd_usf_id = ? -- $usfId'; // only check for not obsolete entries
        $stmt = $this->db->queryPrepared($sql, array($ufoId, $this->usfId));
        return ((int)$stmt->fetchColumn() > 0);
        } else {
            // if no usfId is set then it is a new profile field and no options are used in the database
            return false;
        }
    }
    
    /**
     * Deletes an option from the select field.
     * The option will be removed from the database and the internal array of options.
     * @param int $ufoId The ID of the option to delete.
     * @return bool Returns true if the option could be deleted, otherwise false.
     * @throws Exception
     */
    public function deleteOption(int $ufoId) : bool
    {
        if ($this->usfId > 0) {
            // delete the option from the database
            $sql = 'DELETE FROM ' . TBL_USER_FIELD_OPTIONS . '
                    WHERE ufo_id = ? -- option ID
                    AND ufo_usf_id = ? -- $usfId';
            $this->db->queryPrepared($sql, array($ufoId, $this->usfId));

            // remove the option from the internal array
            unset($this->optionValues[$ufoId]);
            return true;
        } else {
            // if no usfId is set then it is a new profile field and no options
            return false;
        }
    }

    /**
     * Option will change the complete sequence.
     * @param array $sequence the new sequence of opions (option IDs)
     * @return bool Return true if the sequence of the options could be changed, otherwise false.
     * @throws Exception
     */
    public function setSequence(array $sequence) : bool
    {
        $ufoID = $this->getValue('ufo_id');

        $sql = 'UPDATE ' . TBL_USER_FIELD_OPTIONS . '
                   SET ufo_sequence = ? -- new order sequence
                 WHERE ufo_id     = ? -- opion ID;
            ';

        $newSequence = -1;
        foreach ($sequence as $id => $pos) {
            if ($id == $ufoID) {
                // Store position for later update
                $newSequence = $pos + 1;
            } else {
                $this->db->queryPrepared($sql, array($pos + 1, $id));
            }
        }

        if ($newSequence > 0) {
            $this->setValue('ufo_sequence', $newSequence);
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
    public function setOptionValues(array $newValues) : bool
    {
        $ret = true;
        $newOption = false;
        $arrValues = $newValues;
        // first save the new values of the options
        foreach ($newValues as $id => $values) {
            if ($this->readDataById($id)) {                                       
                foreach ($values as $key => $value) {
                    $this->setValue('ufo_' . $key, $value);
                }
                $ret = $this->save();
            } else {
                $newOption = true;
                $option = new SelectOptions($this->db);

                // if the options value does not exist then create a new entry
                $option->setValue('ufo_usf_id', $this->usfId);

                foreach ($values as $key => $value) {
                    $option->setValue('ufo_' . $key, $value);
                }
                $ret = $option->save();

                // update the ID of the new option in the array
                if ($id != $option->getValue('ufo_id')) {
                    $newId = $option->getValue('ufo_id');
                    $newArr = [];
                    foreach ($arrValues as $key => $value) {
                        if ($key === $id) {
                            $newArr[$newId] = $values;
                        } else {
                            $newArr[$key] = $value;
                        }
                    }
                    $arrValues = $newArr;
                }
            }
        }

        // if new Opions were added then the sequence of the options must be updated
        if ($newOption) {
            $this->readDataByFieldId($this->usfId);
        }
        // now change the sequence of the options
        $allOptions = $this->getAllOptions(); // load all options of the options

        // determinalte current sequence based on allOpions sequence values
        $currentSequence = array();
        foreach ($allOptions as $option) {
            $currentSequence[$option['id']] = $option['sequence'] - 1; // -1 because sequence starts with 1 in database
        }
        // determinate new sequence based on array position
        $newSequence = array();
        $sequence = 0;
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
    public function save(bool $updateFingerPrint = true) : bool
    {
        $this->db->startTransaction();

        if ($this->newRecord) {
            $this->usfId = (int)$this->dbColumns['ufo_usf_id'];
            // Determine the highest sequence number of the option when inserting
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_USER_FIELD_OPTIONS.'
                     WHERE ufo_usf_id = ? -- $this->usfId';
            $countOptionsStatement = $this->db->queryPrepared($sql, array($this->usfId));

            $this->setValue('ufo_sequence', (int)$countOptionsStatement->fetchColumn() + 1);
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
     * Adjust the changelog entry for this db record: Add the first forum post as a related object
     * @param LogChanges $logEntry The log entry to adjust
     * @return void
     * @throws Exception
     */
    protected function adjustLogEntry(LogChanges $logEntry) : void
    {      
        $profileField = new ProfileField($this->db);

        $fieldId = $this->getValue('ufo_usf_id');
        $id = $this->dbColumns[$this->keyColumnName];
        $profileField->readDataById($fieldId);
        $uuid = $profileField->getValue('usf_uuid', 'database');
        $fieldName = $profileField->getValue('usf_name', 'database');

        $logEntry->setValue('log_record_name', $fieldName);
        $logEntry->setValue('log_record_id', $id);
        $logEntry->setValue('log_record_uuid', $uuid);
        $logEntry->setValue('log_related_name', $this->getValue('ufo_value'));
    }
}
