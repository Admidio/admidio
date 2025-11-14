<?php
namespace Admidio\Users\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Changelog\Entity\LogChanges;

/**
 * @brief Class manages access to database table adm_user_relations
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class UserRelation extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_user_relation_types.
     * If the id is set than the specific message will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $ureId The recordset of the relation with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $ureId = 0)
    {
        parent::__construct($database, TBL_USER_RELATIONS, 'ure', $ureId);
    }

    /**
     * Returns the inverse relation.
     * @return null|self Returns the inverse relation
     * @throws Exception
     */
    public function getInverse(): ?UserRelation
    {
        $relationType = new UserRelationType($this->db, (int) $this->getValue('ure_urt_id'));
        if ($relationType->getValue('urt_id_inverse') === null) {
            return null;
        }

        $selectColumns = array(
            'ure_urt_id'  => (int) $relationType->getValue('urt_id_inverse'),
            'ure_usr_id1' => (int) $this->getValue('ure_usr_id2'),
            'ure_usr_id2' => (int) $this->getValue('ure_usr_id1')
        );
        $inverse = new self($this->db);
        $inverse->readDataByColumns($selectColumns);

        if ($inverse->isNewRecord()) {
            return null;
        }

        return $inverse;
    }

    /**
     * Deletes the selected record of the table and initializes the class
     * @param bool $deleteInverse
     * @return bool Returns **true** if no error occurred
     * @throws Exception
     */
    public function delete(bool $deleteInverse = true): bool
    {
        $this->db->startTransaction();

        if ($deleteInverse) {
            $inverse = $this->getInverse();
            if ($inverse) {
                $inverse->delete(false);
            }
        }
        parent::delete();

        return $this->db->endTransaction();
    }

    /**
     * Logs creation of the DB record
     * 
     * @return true Returns **true** if no error occurred
     */
    public function logCreation(): bool
    {
        global $gProfileFields;
        if (!self::$loggingEnabled) return false;
        $table = $this->tableName;
        $table = str_replace(TABLE_PREFIX . '_', '', $table);

        // extract both users
        $usrId1 = (int)$this->getValue('ure_usr_id1');
        $usrId2 = (int)$this->getValue('ure_usr_id2');
        $user1 = new User($this->db, $gProfileFields, $usrId1);
        $user2 = new User($this->db, $gProfileFields, $usrId2);

        // extract relationship type
        $urtID = $this->getValue('ure_urt_id');
        $relationType = new UserRelationType($this->db, $urtID);

        // Log entry: ID from relationship, but UUID, readableName from User1
        // Related to: UUID and readable Name from User2
        // Field is SYS_USER_RELATION
        // Value is the relationship type
        $logEntry = new LogChanges($this->db);
        $logEntry->setLogModification($table, 
            $this->dbColumns[$this->keyColumnName],
            $user1->getValue('usr_uuid'), $user1->readableName(),
            'ure_urt_id', 'SYS_USER_RELATION', 
            null, $relationType->getValue('urt_name')
        );

        $logEntry->setLogRelated($user2->getValue('usr_uuid'), $user2->readableName());
        
        $this->adjustLogEntry($logEntry);
        return $logEntry->save();
    }

    /**
     * Logs deletion of the DB record
     * 
     * @return true Returns **true** if no error occurred
     */
    public function logDeletion(): bool
    {
        global $gProfileFields;
        if (!self::$loggingEnabled) return false;
        $table = $this->tableName;
        $table = str_replace(TABLE_PREFIX . '_', '', $table);

        // extract both users
        $usrId1 = (int)$this->getValue('ure_usr_id1');
        $usrId2 = (int)$this->getValue('ure_usr_id2');
        $user1 = new User($this->db, $gProfileFields, $usrId1);
        $user2 = new User($this->db, $gProfileFields, $usrId2);

        // extract relationship type
        $urtID = $this->getValue('ure_urt_id');
        $relationType = new UserRelationType($this->db, $urtID);

        // Log entry: ID from relationship, but UUID, readableName from User1
        // Related to: UUID and readable Name from User2
        // Field is SYS_USER_RELATION
        // Value is the relationship type
        $logEntry = new LogChanges($this->db);
        $logEntry->setLogModification($table, 
            $this->dbColumns[$this->keyColumnName],
            $user1->getValue('usr_uuid'), $user1->readableName(),
            'ure_urt_id', 'SYS_USER_RELATION', 
            $relationType->getValue('urt_name'), null
        );

        $logEntry->setLogRelated($user2->getValue('usr_uuid'), $user2->readableName());

        $this->adjustLogEntry($logEntry);
        return $logEntry->save();
    }


    /**
     * Logs all modifications of the DB record
     * @param array $logChanges Array of all changes, generated by the save method
     * @return true Returns **true** if no error occurred
     * @throws Exception
     */
    public function logModifications(array $logChanges): bool
    {
            // We don't want to log modifications of individual record columns!
            // The UI does not allow modifying relationships, and creation/deletion 
            // already logs all information about the relationship!
            return false;
    }
}
