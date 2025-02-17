<?php
namespace Admidio\Roles\Entity;

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Changelog\Entity\LogChanges;


/**
 * Manages the dependencies of roles
 *
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class RolesDependencies extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_role_dependencies.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @throws Exception
     */
    public function __construct(Database $database)
    {
        parent::__construct($database, TBL_ROLE_DEPENDENCIES, 'rld');
    }
   
    /**
     * Deletes the selected record of the table and initializes the class
     * Since the role_dependencies table does not have a single auto-increment 
     * key, but two columne (parent-child), we have to override the parent't 
     * method.
     * 
     * @return true Returns **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
    {
        if (array_key_exists('rld_rol_id_parent', $this->dbColumns) && $this->dbColumns['rld_rol_id_parent'] !== '' &&
            array_key_exists('rld_rol_id_child', $this->dbColumns) && $this->dbColumns['rld_rol_id_child'] !== ''
        ) {
            // Log record deletion, then delete
            $this->logDeletion();
            $sql = 'DELETE FROM '.$this->tableName.'
                     WHERE rld_rol_id_parent = ? -- $this->dbColumns[\'rld_rol_id_parent\']
                     AND rld_rol_id_child = ? -- $this->dbColumns[\'rld_rol_id_child\']';
            $this->db->queryPrepared($sql, array($this->dbColumns['rld_rol_id_parent'], $this->dbColumns['rld_rol_id_child']));
        }

        $this->clear();
        return true;
    }

    /**
     * List of database fields that are ignored for the changelog.
     * Some tables contain columns _usr_id_create, timestamp_create, etc. We do not want
     * to log changes to these columns. Subclasses can also add further fields 
     * (e.g. the users table stores and auto-increments the login count, which 
     * we do not want to log)
     * 
     * @return true Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        $ignored = parent::getIgnoredLogColumns();
        return array_merge($ignored, ['rld_rol_id_parent', 'rld_rol_id_child']);
    }

    /**
     * Adjust the changelog entry for this db record. Since we don't have any id in the table,
     * insert the child as record and the parent as relatd.
     * 
     * @param LogChanges $logEntry The log entry to adjust
     * 
     * @return void
     */
    protected function adjustLogEntry(LogChanges $logEntry) {
        $parent_role = new Role($this->db, $this->getValue('rld_rol_id_parent'));
        $child_role = new Role($this->db, $this->getValue('rld_rol_id_child'));

        $logEntry->setValue('log_record_id', $child_role->getValue('rol_id'));
        $logEntry->setValue('log_record_uuid', $child_role->getValue('rol_uuid'));
        $logEntry->setValue('log_record_name', $child_role->readableName());
        
        $logEntry->setLogRelated($parent_role->getValue('rol_uuid'), $parent_role->readableName());
    }


}
