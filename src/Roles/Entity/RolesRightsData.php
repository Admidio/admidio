<?php
namespace Admidio\Roles\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\Role;
use Admidio\Documents\Entity\Folder;
use Admidio\Categories\Entity\Category;
use Admidio\Events\Entity\Event;
use Admidio\Menu\Entity\MenuEntry;
use Admidio\Changelog\Entity\LogChanges;


/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Stores the roles assigned to an object.
 *
 * This class is used to store rights assigned to a role on a specific object. The management 
 * of the rights is done by the RolesRights class. Direct use of this class is typically nor needed.
 *
 */
class RolesRightsData extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_roles_rights_data.
     * If the id is set than the specific record will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $ureId The recordset of the right with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $rrdId = 0)
    {
        parent::__construct($database, TBL_ROLES_RIGHTS_DATA, 'rrd', $rrdId);
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

        $roleID = (int)$this->getValue('rrd_rol_id');
        $rightsID = (int)$this->getValue('rrd_ror_id');
        $objID = $this->getValue('rrd_object_id');

        // create role and RoleRights objects
        $role = new Role($this->db, $roleID);

        $roleRights = new RolesRights($this->db, '', $objID);
        $roleRights->readDataById($rightsID);

        // The object to which the permissions apply
        $objTable = $roleRights->getValue('ror_table');

        switch($objTable) {
            case TBL_FOLDERS:
                $obj = new Folder($this->db, $objID);
                break;
            case TBL_CATEGORIES:
                $obj = new Category($this->db, $objID);
                break;
            case TBL_EVENTS:
                $obj = new Event($this->db, $objID);
                break;
            case TBL_MENU:
                $obj = new MenuEntry($this->db, $objID);
                break;
            default:
                $obj = new Entity($this->db, $objTable, $objID);
        }
        $objUuid = $obj->getValue($obj->columnPrefix . '_uuid'); 
        $objTableclean = str_replace(TABLE_PREFIX . '_', '', $objTable);

        // Log object: Object 'rrd_object_id' from table 'rrd_ror_id'->'ror_table'
        //      LinkTo is: ror_table:rrd_object_id->UUID
        // Related to: Role from rrd_rol_id
        // Field is SYS_USER_RELATION
        // Value is the right from 'rrd_ror_id'->'ror_name_intern'
        $logEntry = new LogChanges($this->db);
        $logEntry->setLogModification($table, 
            $this->dbColumns[$this->keyColumnName],
            $objUuid, $obj->readableName(),
            'rrd_ror_id', 'SYS_PERMISSIONS', 
            null, $roleRights->getValue('ror_name_intern')
        );
        // Since we have objects of various tables, we need to encode the table together with the uuid!
        $logEntry->setLogLinkID($objTableclean . ':' . $objUuid);

        $logEntry->setLogRelated($role->getValue('rol_uuid'), $role->readableName());
        
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

        $roleID = (int)$this->getValue('rrd_rol_id');
        $rightsID = (int)$this->getValue('rrd_ror_id');
        $objID = $this->getValue('rrd_object_id');

        // create role and RoleRights objects
        $role = new Role($this->db, $roleID);

        $roleRights = new RolesRights($this->db, '', $objID);
        $roleRights->readDataById($rightsID);

        // The object to which the permissions apply
        $objTable = $roleRights->getValue('ror_table');

        switch($objTable) {
            case TBL_FOLDERS:
                $obj = new Folder($this->db, $objID);
                break;
            case TBL_CATEGORIES:
                $obj = new Category($this->db, $objID);
                break;
            case TBL_EVENTS:
                $obj = new Event($this->db, $objID);
                break;
            case TBL_MENU:
                $obj = new MenuEntry($this->db, $objID);
                break;
            default:
                $obj = new Entity($this->db, $objTable, $objID);
        }

        // Log object: Object 'rrd_object_id' from table 'rrd_ror_id'->'ror_table'
        //      LinkTo is: ror_table:rrd_object_id->UUID
        // Related to: Role from rrd_rol_id
        // Field is SYS_USER_RELATION
        // Value is the right from 'rrd_ror_id'->'ror_name_intern'
        $logEntry = new LogChanges($this->db);
        $logEntry->setLogModification($table, 
            $this->dbColumns[$this->keyColumnName],
            null, $obj->readableName(),
            'rrd_ror_id', 'SYS_PERMISSIONS', 
            $roleRights->getValue('ror_name_intern'), null
        );
        // Since we have objects of various tables, we need to encode the table together with the uuid!
        $logEntry->setLogLinkID(str_replace(TABLE_PREFIX . '_', '', $objTable) . ':' . 
            $obj->getValue($obj->columnPrefix . '_uuid'));

        $logEntry->setLogRelated($role->getValue('rol_id'), $role->readableName());
        
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
            // The UI does not allow direct modification of role rights, and creation/deletion 
            // already logs all information about the rights!
            return false;
    }

}
