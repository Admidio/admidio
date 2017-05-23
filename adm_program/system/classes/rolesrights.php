<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class RolesRights
 * @brief Manages a special right of the table adm_roles_rights for a special object.
 *
 * This class will manage roles rights for a specific object. The possible rights are defined
 * within the table adm_roles_rights. The assigned roles will be stored within the table
 * adm_roles_rights_data. There is also the link to the specific object. The assigned roles
 * of the object could be managed with this class. You can add or remove roles and check if a
 * user has access to the specific right.
 * @par Examples
 * @code // check if the current user has the right to view a folder of the download module
 * $folderViewRolesObject = new RolesRights($gDb, 'folder_view', $folderId);
 * if($folderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships()))
 * {
 *   // do something
 * } @endcode
 * @code // add new roles to the special roles right of a folder
 * $folderViewRolesObject = new RolesRights($gDb, 'folder_view', $folderId);
 * $folderViewRolesObject->addRoles(array(2, 5)); @endcode
 */
class RolesRights extends TableAccess
{
    protected $rolesRightsDataObjects;
    protected $rolesIds;        ///< Array with all roles ids as values
    protected $objectId;        ///< Id of the object for which the roles right should be loaded.
    protected $rolesRightName;  ///< Name of the current role right

    /**
     * Constructor that will create an object of a recordset of the table adm_roles_rights.
     * If the id is set than the specific category will be loaded.
     * @param \Database $database       Object of the class Database. This should be the default global object @b $gDb.
     * @param string    $rolesRightName The recordset of the roles right with this name will be loaded.
     * @param string    $objectId       Id of the object of which the roles should be loaded.
     */
    public function __construct(&$database, $rolesRightName, $objectId)
    {
        parent::__construct($database, TBL_ROLES_RIGHTS, 'ror');

        $this->rolesRightName = $rolesRightName;
        $this->objectId = $objectId;

        $this->readDataByColumns(array('ror_name_intern' => $rolesRightName));
    }

    /**
     * Add all roles of the parameter array to the current roles rights object.
     * @param int[] $roleIds Array with all role ids that should be add.
     */
    public function addRoles(array $roleIds)
    {
        foreach (array_map('intval', $roleIds) as $roleId)
        {
            if (!in_array($roleId, $this->rolesIds, true) && $roleId > 0)
            {
                $rolesRightsData = new TableAccess($this->db, TBL_ROLES_RIGHTS_DATA, 'rrd');
                $rolesRightsData->setValue('rrd_ror_id', $this->getValue('ror_id'));
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
     */
    public function clear()
    {
        $this->rolesRightsDataObjects = array();
        $this->rolesIds = array();

        parent::clear();
    }

    /**
     * Deletes all assigned roles to the current object.
     * After that the class will be initialize.
     * @return bool @b true if no error occurred
     */
    public function delete()
    {
        if(count($this->rolesRightsDataObjects) > 0)
        {
            $this->db->startTransaction();

            foreach($this->rolesRightsDataObjects as $object)
            {
                $object->delete();
            }

            $this->clear();
            $this->db->endTransaction();
        }
        return true;
    }

    /**
     * Get all roles ids that where assigned to the current roles right and the selected object.
     * @return int[] Returns an array with all role ids
     */
    public function getRolesIds()
    {
        return $this->rolesIds;
    }

    /**
     * Get all names of the roles that where assigned to the current roles right and the selected object.
     * @return string[] Returns an array with all roles names
     */
    public function getRolesNames()
    {
        $arrRolesNames = array();

        if (count($this->rolesIds) > 0)
        {
            $sql = 'SELECT rol_name
                      FROM '.TBL_ROLES.'
                     WHERE rol_id IN ('.replaceValuesArrWithQM($this->rolesIds).') ';
            $rolesStatement = $this->db->queryPrepared($sql, $this->rolesIds);

            while ($rowRole = $rolesStatement->fetch())
            {
                $arrRolesNames[] = $rowRole['rol_name'];
            }
        }

        return $arrRolesNames;
    }

    /**
     * Check if one of the assigned roles is also a role of the current object.
     * Method will return true if at least one role was found.
     * @param int[] $assignedRoles Array with all assigned roles of the user whose rights should be checked
     * @return bool Return @b true if at least one role of the assigned roles exists at the current object.
     */
    public function hasRight(array $assignedRoles)
    {
        return count($assignedRoles) > 0 && count(array_intersect($this->rolesIds, $assignedRoles)) > 0;
    }

    /**
     * Reads a record out of the table in database selected by the conditions of the param @b $sqlWhereCondition out of the table.
     * If the sql will find more than one record the method returns @b false.
     * Per default all columns of the default table will be read and stored in the object.
     * @param string $sqlWhereCondition Conditions for the table to select one record
     * @param array  $queryParams       The query params for the prepared statement
     * @return bool Returns @b true if one record is found
     * @see TableAccess#readDataById
     * @see TableAccess#readDataByColumns
     */
    protected function readData($sqlWhereCondition, array $queryParams = array())
    {
        if(parent::readData($sqlWhereCondition, $queryParams))
        {
            $sql = 'SELECT * FROM '.TBL_ROLES_RIGHTS_DATA.'
                     WHERE rrd_ror_id    = ? -- $this->getValue(\'ror_id\')
                       AND rrd_object_id = ? -- $this->objectId';
            $rolesRightsStatement = $this->db->queryPrepared($sql, array($this->getValue('ror_id'), $this->objectId));

            while($row = $rolesRightsStatement->fetch())
            {
                $this->rolesRightsDataObjects[$row['rrd_rol_id']] = new TableAccess($this->db, TBL_ROLES_RIGHTS_DATA, 'rrd');
                $this->rolesRightsDataObjects[$row['rrd_rol_id']]->setArray($row);
                $this->rolesIds[] = (int) $row['rrd_rol_id'];
            }
        }

        return false;
    }

    /**
     * Remove all roles of the parameter array from the current roles rights object.
     * @param int[] $roleIds Array with all role ids that should be removed.
     */
    public function removeRoles(array $roleIds)
    {
        foreach (array_map('intval', $roleIds) as $roleId)
        {
            if (in_array($roleId, $this->rolesIds, true))
            {
                $this->rolesRightsDataObjects[$roleId]->delete();
            }
        }
    }

    /**
     * Save all roles of the array to the current roles rights object.
     * The method will only save the changes to an existing object. Therefore
     * it will check which roles already exists and which roles must be removed.
     * @param int[] $roleIds Array with all role ids that should be saved.
     */
    public function saveRoles(array $roleIds)
    {
        // if array is empty or only contain the role id = 0 then delete all roles rights
        if(count($roleIds) === 0 || (count($roleIds) === 1 && $roleIds[0] === '0'))
        {
            $this->delete();
        }
        // save new roles rights to the database
        else
        {
            $roleIds = array_map('intval', $roleIds);

            // get new roles and removed roles
            $addRoles = array_diff($roleIds, $this->getRolesIds());
            $removeRoles = array_diff($this->getRolesIds(), $roleIds);

            // now save changes to database
            $this->addRoles($addRoles);
            $this->removeRoles($removeRoles);
        }
    }
}
