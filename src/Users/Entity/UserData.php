<?php
namespace Admidio\Users\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Changelog\Entity\LogChanges;
use Admidio\Changelog\Service\ChangelogService;

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
    protected function logUserfieldChange(?string $oldval = null, ?string $newval = null) : bool {
        if ($oldval === $newval) {
            // No change, nothing to log
            return true;
        }
        if (!self::$loggingEnabled) return false;

        global $gProfileFields;
        $table = str_replace(TABLE_PREFIX . '_', '', $this->tableName);
        if (!ChangelogService::isTableLogged($table)) return false;

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
     * Write one changelog entry for every profile field value that the given condition selects.
     * Deleting a value means setting the profile field to empty, so the entries are modifications
     * to an empty value, exactly like logDeletion() writes them for a single record.
     *
     * A value is logged with the user it belongs to. Reading a User object per record would read
     * the profile field data of every affected user, and deleting a profile field affects every
     * user of the installation, so the users are read once as a whole.
     *
     * @param array $identifyingColumns The columns that identify a single value. They are not
     *                                  needed here, the whole record is read in one query anyway.
     * @param string $sqlWhereCondition Condition that selects the values, without the leading
     *                                  keyword WHERE and only with columns of adm_user_data.
     * @param array $queryParams Values of the prepared parameters of the condition.
     * @return int Returns the number of written log entries.
     * @throws Exception
     */
    public function logBulkDeletion(array $identifyingColumns, string $sqlWhereCondition, array $queryParams = array()): int
    {
        global $gProfileFields;

        if (!self::$loggingEnabled) return 0;
        $table = str_replace(TABLE_PREFIX . '_', '', $this->tableName);
        if (!ChangelogService::isTableLogged($table)) return 0;

        $sql = 'SELECT usd_id, usd_usr_id, usd_usf_id, usd_value
                  FROM ' . TBL_USER_DATA . '
                 WHERE ' . $sqlWhereCondition;
        $records = $this->db->queryPrepared($sql, $queryParams)->fetchAll(\PDO::FETCH_ASSOC);

        // A value that is empty already is no change and is not logged, see logUserfieldChange().
        $records = array_values(array_filter($records, function (array $record) {
            return $record['usd_value'] !== null && $record['usd_value'] !== '';
        }));
        if (count($records) === 0) {
            return 0;
        }

        $users = $this->readUsersOfRecords(array_column($records, 'usd_usr_id'));
        $logEntries = 0;

        foreach ($records as $record) {
            $userId = (int)$record['usd_usr_id'];
            $fieldId = (int)$record['usd_usf_id'];

            $logEntry = new LogChanges($this->db, $table);
            $logEntry->setLogModification(
                $table,
                (int)$record['usd_id'],
                $users[$userId]['usr_uuid'] ?? null,
                $users[$userId]['name'] ?? (string)$record['usd_id'],
                $fieldId,
                $gProfileFields->getPropertyById($fieldId, 'usf_name', 'database'),
                $record['usd_value'],
                null
            );
            $logEntry->setLogLinkID($userId);
            $logEntry->save();
            $logEntry->clear();
            $logEntries++;
        }

        return $logEntries;
    }

    /**
     * Read the uuid and the readable name of the given users in one query, so that a bulk deletion
     * does not create a User object with all its profile field data for every single record.
     *
     * @param array $userIds The ids of the users, duplicates are allowed.
     * @return array Returns a named array of user id => array with the keys **usr_uuid** and **name**
     * @throws Exception
     */
    private function readUsersOfRecords(array $userIds): array
    {
        global $gProfileFields;

        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (count($userIds) === 0) {
            return array();
        }

        $sql = 'SELECT usr_id, usr_uuid, last_name.usd_value AS last_name, first_name.usd_value AS first_name
                  FROM ' . TBL_USERS . '
             LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
             LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                 WHERE usr_id IN (' . Database::getQmForValues($userIds) . ')';
        $queryParams = array_merge(
            array((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id')),
            $userIds
        );
        $statement = $this->db->queryPrepared($sql, $queryParams);

        $users = array();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $users[(int)$row['usr_id']] = array(
                'usr_uuid' => $row['usr_uuid'],
                'name' => $row['last_name'] . ', ' . $row['first_name']
            );
        }

        return $users;
    }

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
