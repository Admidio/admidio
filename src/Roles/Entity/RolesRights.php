<?php
namespace Admidio\Roles\Entity;

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;

 include_once __DIR__ . '/RolesRightsData.php';

/**
 * Manages the assignment of roles to an object.
 *
 * This class will manage roles rights for a specific object. The possible rights are defined
 * within the table **adm_roles_rights**. The assigned roles will be stored within the table
 * **adm_roles_rights_data**. There is also the link to the specific object. The assigned roles
 * of the object could be managed with this class. You can add or remove roles and check if a
 * user has access to the specific right.
 *
 * **Code examples**
 * ```
 * // check if the current user has the right to view a folder of the documents & files module
 * $folderViewRolesObject = new RolesRights($gDb, 'folder_view', $folderId);
 * if($folderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships()))
 * {
 *   // do something
 * }
 *
 * // add new roles to the special roles right of a folder
 * $folderViewRolesObject = new RolesRights($gDb, 'folder_view', $folderId);
 * $folderViewRolesObject->addRoles(array(2, 5));
 *
 * // get ids of all assigned roles to an object
 * $categoryViewRolesObject = new RolesRights($gDb, 'category_view', $categoryId);
 * $rolesArray = $categoryViewRolesObject->getRolesIds();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class RolesRights extends Entity
{
    /**
     * @var array<int,Entity>
     */
    protected array $rolesRightsDataObjects;
    /**
     * @var array<int,int> Array with all roles ids as values
     */
    protected array $rolesIds;
    /**
     * @var int ID of the object for which the roles right should be loaded.
     */
    protected int $objectId;
    /**
     * @var string Name of the current role right
     */
    protected string $rolesRightName;

    /**
     * Constructor that will create an object of a recordset of the table adm_roles_rights.
     * If the id is set than the specific category will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $rolesRightName The recordset of the roles right with this name will be loaded.
     * @param int $objectId ID of the object of which the roles should be loaded.
     * @throws Exception
     */
    public function __construct(Database $database, string $rolesRightName, int $objectId)
    {
        parent::__construct($database, TBL_ROLES_RIGHTS, 'ror');

        $this->rolesRightName = $rolesRightName;
        $this->objectId = $objectId;

        $this->readDataByColumns(array('ror_name_intern' => $rolesRightName));
    }

    /**
     * Add all roles of the parameter array to the current roles rights object.
     * @param array<int,int> $roleIds Array with all role ids that should be added.
     * @throws Exception
     */
    public function addRoles(array $roleIds)
    {
        foreach ($roleIds as $roleId) {
            if (!in_array($roleId, $this->rolesIds, true) && $roleId > 0) {
                $rolesRightsData = new RolesRightsData($this->db);
                $rolesRightsData->setValue('rrd_ror_id', (int) $this->getValue('ror_id'));
                $rolesRightsData->setValue('rrd_rol_id', $roleId);
                $rolesRightsData->setValue('rrd_object_id', $this->objectId);
                $rolesRightsData->save();

                $this->rolesRightsDataObjects[$roleId] = $rolesRightsData;
                $this->rolesIds[] = $roleId;
            }
        }
    }

    /**
     * Initializes all class parameters and deletes all read data.
     * @throws Exception
     */
    public function clear(): void
    {
        $this->rolesRightsDataObjects = array();
        $this->rolesIds = array();

        parent::clear();
    }

    /**
     * Deletes all assigned roles to the current object.
     * After that the class will be initialized.
     * @return bool **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
    {
        if (count($this->rolesRightsDataObjects) > 0) {
            $this->db->startTransaction();

            foreach ($this->rolesRightsDataObjects as $object) {
                $object->delete();
            }

            $this->clear();
            $this->db->endTransaction();
        }
        return true;
    }

    /**
     * Get all roles ids that where assigned to the current roles right and the selected object.
     * @return array<int,int> Returns an array with all role ids
     */
    public function getRolesIds(): array
    {
        return $this->rolesIds;
    }

    /**
     * Get all names of the roles that where assigned to the current roles right and the selected object.
     * @return array<int,string> Returns an array with all roles names
     * @throws Exception
     */
    public function getRolesNames(): array
    {
        $arrRolesNames = array();

        if (count($this->rolesIds) > 0) {
            $sql = 'SELECT rol_name
                      FROM '.TBL_ROLES.'
                     WHERE rol_id IN ('.Database::getQmForValues($this->rolesIds).') ';
            $rolesStatement = $this->db->queryPrepared($sql, $this->rolesIds);

            while ($rowRole = $rolesStatement->fetch()) {
                $arrRolesNames[] = $rowRole['rol_name'];
            }
        }

        return $arrRolesNames;
    }

    /**
     * Check if one of the assigned roles is also a role of the current object.
     * Method will return true if at least one role was found.
     * @param array<int,int> $assignedRoles Array with all assigned roles of the user whose rights should be checked
     * @return bool Return **true** if at least one role of the assigned roles exists at the current object.
     */
    public function hasRight(array $assignedRoles): bool
    {
        return count($assignedRoles) > 0 && count(array_intersect($this->rolesIds, $assignedRoles)) > 0;
    }

    /**
     * Reads a record out of the table in database selected by the conditions of the param **$sqlWhereCondition** out of the table.
     * If the sql find more than one record the method returns **false**.
     * Per default all columns of the default table will be read and stored in the object.
     * @param string $sqlWhereCondition Conditions for the table to select one record
     * @param array<int,mixed> $queryParams The query params for the prepared statement
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByUuid
     * @see Entity#readDataByColumns
     * @see Entity#readDataById
     */
    protected function readData(string $sqlWhereCondition, array $queryParams = array()): bool
    {
        if (parent::readData($sqlWhereCondition, $queryParams)) {
            $sql = 'SELECT *
                      FROM '.TBL_ROLES_RIGHTS_DATA.'
                     WHERE rrd_ror_id    = ? -- $this->getValue(\'ror_id\')
                       AND rrd_object_id = ? -- $this->objectId';
            $rolesRightsStatement = $this->db->queryPrepared($sql, array((int) $this->getValue('ror_id'), $this->objectId));

            while ($row = $rolesRightsStatement->fetch()) {
                $rolId = (int) $row['rrd_rol_id'];
                $this->rolesRightsDataObjects[$rolId] = new RolesRightsData($this->db);
                $this->rolesRightsDataObjects[$rolId]->setArray($row);
                $this->rolesIds[] = $rolId;
            }
            return true;
        }

        return false;
    }

    /**
     * Remove all roles of the parameter array from the current roles rights object.
     * @param array<int,int> $roleIds Array with all role ids that should be removed.
     * @throws Exception
     */
    public function removeRoles(array $roleIds)
    {
        foreach ($roleIds as $roleId) {
            if (in_array($roleId, $this->rolesIds, true)) {
                $this->rolesRightsDataObjects[$roleId]->delete();
            }
        }
    }

    /**
     * Save all roles of the array to the current roles rights object.
     * The method will only save the changes to an existing object. Therefore,
     * it will check which roles already exists and which roles must be removed.
     * If the current right has a parent right then all roles will also be added
     * to the parent right and saved.
     * @param array<int,int> $roleIds Array with all role ids that should be saved.
     * @throws Exception
     */
    public function saveRoles(array $roleIds)
    {
        // if array is empty or only contain the role id = 0 then delete all roles rights
        if (count($roleIds) === 0 || (count($roleIds) === 1 && $roleIds[0] === 0)) {
            $this->delete();
        }
        // save new roles rights to the database
        else {
            // get new roles and removed roles
            $addRoles = array_diff($roleIds, $this->getRolesIds());
            $removeRoles = array_diff($this->getRolesIds(), $roleIds);

            // now save changes to database
            $this->addRoles($addRoles);
            $this->removeRoles($removeRoles);

            // if current right has a parent role right then add the roles also to the parent role right
            if ((int) $this->getValue('ror_ror_id_parent') > 0) {
                $parentRight      = new Entity($this->db, TBL_ROLES_RIGHTS, 'ror', (int) $this->getValue('ror_ror_id_parent'));
                $parentRolesRight = new self($this->db, $parentRight->getValue('ror_name_intern'), $this->objectId);
                $parentRolesRight->saveRolesOfChildRight($roleIds);
            }
        }
    }

    /**
     * Unlike saveRoles, this method adds all the roles of the passed array to the current role right.
     * This method should only be called from a child role right, which wants to store its roles
     * in the parent role right.
     * @param array<int,int> $roleIds Array with all role ids that should be saved.
     * @throws Exception
     */
    public function saveRolesOfChildRight(array $roleIds)
    {
        // if array is empty of new roles is empty or viewable roles array is empty then add nothing
        if (count($roleIds) > 0 && count($this->getRolesIds()) > 0) {
            // add new roles and save them to database
            $addRoles = array_diff($roleIds, $this->getRolesIds());
            $this->addRoles($addRoles);
        }
    }
}
