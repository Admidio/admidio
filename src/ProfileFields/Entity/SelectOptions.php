<?php
namespace Admidio\ProfileFields\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;

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
    public function readDataByFieldId($usfId = 0): void
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
     * @param bool $withObsoleteEnries If set to **false** then the obsolete entries of the profile field will not be considered.
     * @return array Returns an array with all options of the select field.
     */
    public function getAllOptions(bool $withObsoleteEnries = true): array
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
     * Option will change the complete sequence.
     * @param array $sequence the new sequence of opions (option IDs)
     * @return bool Return true if the sequence of the options could be changed, otherwise false.
     * @throws Exception
     */
    public function setSequence(array $sequence): bool
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
}
