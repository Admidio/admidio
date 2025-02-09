<?php
namespace Admidio\Users\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Changelog\Entity\LogChanges;

/**
 ***********************************************************************************************
 * Class manages access to database table adm_user_data
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 * 
 * Logging of User Fields works differently from all other DB records. User field records are 
 * actually always modifications of the underlying users. A new record indicates that the 
 * corresponding field for the user was changed from empty to a non-empty value. Thus, a 
 * generation should be logged as a modification with old value null. Also, the user data 
 * object describes one data field, although it consists of four different database columns 
 *      => only if the value was changed, do we need to add a log entry!
 * 
 ***********************************************************************************************
 */
class UserData extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_user_data.
     * If the id is set than the specific profile field will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $id The id of the profile field. If 0, an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $id = 0)
    {
        parent::__construct($database, TBL_USER_DATA, 'usd', $id);
        $this->connectAdditionalTable(TBL_USER_FIELDS, 'usf_id', 'usd_usf_id');
    }

    /**
     * Since creation means setting value from NULL to something, deletion mean setting the field to empty,
     * we need one generic change log function that is called on creation, deletion and modification.
     * 
     * The log entries are: record ID for Usd_id, but uuid and link point to User id.
     * log_field is the usf_id and log_field_name is the fields external name.
     * 
     * @param string $oldval previous value before the change (can be null)
     * @param string $newval new value after the change (can be null)
     * @return true returns **true** if no error occurred
     */
    protected function logUserfieldChange(string $oldval = null, string $newval = null) : bool {
        if ($oldval === $newval) {
            // No change, nothing to log
            return true;
        }
        global $gProfileFields;
        $table = str_replace(TABLE_PREFIX . '_', '', $this->tableName);

        $userID = $this->getValue('usd_usr_id');
        $user = new User($this->db, $gProfileFields, $userID);
        $id = $this->dbColumns[$this->keyColumnName];
        $uuid = $user ? ($user->getValue('usr_uuid')) : null;
        $record_name = ($user) ? ($user->readableName()) : $id;


        $field = $this->getValue('usd_usf_id');
        $fieldName = $gProfileFields->getPropertyById($field, 'usf_name', 'database');
        $logEntry = new LogChanges($this->db, $table);
        $logEntry->setLogModification($table, $id, $uuid, $record_name, $field, $fieldName, $oldval, $newval);
        $logEntry->setLogLinkID($userID);
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
    public function logCreation(): bool { return true; }

    /**
     * Logs deletion of the DB record
     * Deletion actually means setting the user field to an empty value, so log a change to empty instead of deletion!
     * 
     * @return true Returns **true** if no error occurred
     */
    public function logDeletion(): bool
    {
        $oldval = $this->columnsInfos['usd_value']['previousValue'];
        return $this->logUserfieldChange($oldval, null);
    }


    /**
     * Logs all modifications of the DB record
     * @param array $logChanges Array of all changes, generated by the save method
     * @return true Returns **true** if no error occurred
     * @throws Exception
     */
    public function logModifications(array $logChanges): bool
    {
        if ($logChanges['usd_value']) {
            return $this->logUserfieldChange($logChanges['usd_value']['oldValue'], $logChanges['usd_value']['newValue']);
        } else {
            // Nothing to log at all!
            return true;
        }
    }


    /**
     * Return a human-readable representation of the given database field/column.
     * By default, the column name is returned unmodified. Subclasses can override this method.
     * @param string $field The database column
     * @return string The readable representation of the DB column (can also be a translatable identifier)
     */
    public function getFieldTitle(string $field): string
    {
        global $gProfileFields;
        if ($this->dbColumns['usd_usf_id']) {
            $fieldName = $gProfileFields->getPropertyById($field, 'usf_name');  
            return $fieldName;
        } else {
            return parent::getFieldTitle($field);
        }
    }

}
