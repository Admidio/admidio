<?php
/**
 ***********************************************************************************************
 * Class handle role rights, cards and other things of users
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Inventory
 * Diese Klasse dient dazu ein Userobjekt zu erstellen.
 * Ein User kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * deleteUserFieldData()    - delete all user data of profile fields;
 *                            user record will not be deleted
 * getListViewRights()  - Liefert ein Array mit allen Rollen und der
 *                        Berechtigung, ob der User die Liste einsehen darf
 *                      - aehnlich getProperty, allerdings suche ueber usf_id
 * viewProfile          - Ueberprueft ob der User das Profil eines uebrgebenen
 *                        Users einsehen darf
 */
class Inventory extends TableInventory
{
    public $mInventoryFieldsData;           ///< object with current user field structure
    public $mProfileFieldsData  = array();
    protected $listViewRights = array();  ///< Array ueber Listenrechte einzelner Rollen => Zugriff nur über getListViewRights()
    protected $organizationId;              ///< the organization for which the rights are read, could be changed with method @b setOrganization

    /**
     * Constructor that will create an object of a recordset of the users table.
     * If the id is set than this recordset will be loaded.
     * @param \Database        $database        Object of the class database. This could be the default object @b $gDb.
     * @param \InventoryFields $inventoryFields An object of the ProfileFields class with the profile field structure
     *                                          of the current organization. This could be the default object @b $gProfileFields.
     * @param int              $itemId          The id of the user who should be loaded. If id isn't set than an empty object with no specific user is created.
     */
    public function __construct(&$database, $inventoryFields, $itemId = 0)
    {
        global $gCurrentOrganization;

        $this->mInventoryFieldsData = clone $inventoryFields; // create explicit a copy of the object (param is in PHP5 a reference)
        $this->organizationId = $gCurrentOrganization->getValue('org_id');
        parent::__construct($database, $itemId);
    }

    /**
     * Additional to the parent method the user profile fields and
     * all user rights and role memberships will be initialized
     */
    public function clear()
    {
        parent::clear();

        // die Daten der Profilfelder werden geloescht, die Struktur bleibt
        $this->mInventoryFieldsData->clearInventoryData();
    }

    /**
     * @return bool returns true if a column of user table or profile fields has changed
     */
    public function columnsValueChanged()
    {
        return $this->columnsValueChanged || $this->mProfileFieldsData->columnsValueChanged;
    }

    /**
     * delete all user data of profile fields; user record will not be deleted
     */
    public function deleteUserFieldData()
    {
        $this->db->startTransaction();

        // delete every entry from adm_users_data
        foreach($this->mProfileFieldsData->mUserData as $field)
        {
            $field->delete();
        }

        $this->mProfileFieldsData->mUserData = array();
        $this->db->endTransaction();
    }

    /**
     * Returns the id of the organization this user object has been assigned.
     * This is in the default case the default organization of the config file.
     * @return int Returns the id of the organization this user object has been assigned
     */
    public function getOrganization()
    {
        if($this->organizationId > 0)
        {
            return $this->organizationId;
        }
        return 0;
    }

    /**
     * Get the value of a column of the database table if the column has the praefix @b usr_
     * otherwise the value of the profile field of the table adm_user_data will be returned.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read or the internal unique profile field name
     * @param string $format For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *               For text columns the format can be @b database that would return the original database value without any transformations
     * @return mixed Returns the value of the database column or the value of adm_user_fields
     *         If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @par Examples
     * @code  // reads data of adm_users column
     * $loginname = $gCurrentUser->getValue('usr_login_name');
     * // reads data of adm_user_fields
     * $email = $gCurrentUser->getValue('EMAIL'); @endcode
     */
    public function getValue($columnName, $format = '')
    {
        if(strpos($columnName, 'inv_') === 0)
        {
            $file = ADMIDIO_PATH . FOLDER_DATA . '/invent_profile_photos/' . $this->getValue('inv_id') . '.jpg';
            if($columnName === 'inv_photo' && is_file($file))
            {
                return file_get_contents($file);
            }

            return parent::getValue($columnName, $format);
        }

        return $this->mInventoryFieldsData->getValue($columnName, $format);
    }

    /**
     * If this method is called than all further calls of method @b setValue will not check the values.
     * The values will be stored in database without any inspections !
     */
    public function noValueCheck()
    {
        $this->mInventoryFieldsData->noValueCheck();
    }

    /**
     * Reads a user record out of the table adm_users in database selected by the unique user id.
     * Also all profile fields of the object @b mProfileFieldsData will be read.
     * @param int $itemId Unique id of the user that should be read
     * @return bool Returns @b true if one record is found
     */
    public function readDataById($itemId)
    {
        if(parent::readDataById($itemId))
        {
            // read data of all user fields from current user
            $this->mInventoryFieldsData->readInventoryData($itemId, $this->organizationId);
            return true;
        }

        return false;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * First save recordset and then save all user fields. After that the session of this got a renew for the user object.
     * If the user doesn't have the right to save data of this user than an exception will be thrown.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if
     *                                table has columns like @b usr_id_create or @b usr_id_changed
     * @throws AdmException
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentUser;

        // if current user is new or is allowed to edit this user than save data
        if($gCurrentUser->editUsers())
        {
            $this->db->startTransaction();

            // if value of a field changed then update timestamp of user object
            if($this->mInventoryFieldsData->columnsValueChanged)
            {
                $this->columnsValueChanged = true;
            }

            $returnValue = parent::save($updateFingerPrint);

            // save data of all user fields
            $this->mInventoryFieldsData->saveInventoryData($this->getValue('inv_id'));

            $this->db->endTransaction();

            return $returnValue;
        }

        throw new AdmException('The inventory-data for item ', $this->getValue('FIRST_NAME').' '.$this->getValue('LAST_NAME').' could not be saved because you don\'t have the right to do this.');
    }

    /**
     * Set the id of the organization which should be used in this user object.
     * The organization is used to read the rights of the user. If @b setOrganization isn't called
     * than the default organization @b gCurrentOrganization is set for the current user object.
     * @param int $organizationId Id of the organization
     */
    public function setOrganization($organizationId)
    {
        if(is_numeric($organizationId))
        {
            $this->organizationId = (int) $organizationId;
        }
    }

    /**
     * Set a new value for a column of the database table if the column has the praefix @b usr_
     * otherwise the value of the profile field of the table adm_user_data will set.
     * If the user log is activated than the change of the value will be logged in @b adm_user_log.
     * The value is only saved in the object. You must call the method @b save to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value or the internal unique profile field name
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @return bool Returns @b true if the value is stored in the current object and @b false if a check failed
     * @par Examples
     * @code
     * // set data of adm_users column
     * $gCurrentUser->getValue('usr_login_name', 'Admidio');
     * // reads data of adm_user_fields
     * $gCurrentUser->getValue('EMAIL', 'webmaster@admidio.org');
     * @endcode
     */
    public function setValue($columnName, $newValue)
    {
        global $gCurrentUser, $gPreferences;

        $returnCode    = true;
        $oldFieldValue = $this->mInventoryFieldsData->getValue($columnName, 'database');

        if(strpos($columnName, 'inv_') !== 0)
        {
            // user data from adm_user_fields table

            // only to a update if value has changed
            if(strcmp($newValue, $oldFieldValue) !== 0)
            {
                // Disabled fields can only be edited by users with the right "edit_users" except on registration.
                // Here is no need to check hidden fields because we check on save() method that only users who
                // can edit the profile are allowed to save and change data.
                if(($this->mInventoryFieldsData->getProperty($columnName, 'inf_disabled') == 1
                   && $gCurrentUser->editUsers())
                || $this->mInventoryFieldsData->getProperty($columnName, 'inf_disabled') == 0
                || ((int) $gCurrentUser->getValue('inv_id') === 0 && (int) $this->getValue('inv_id') === 0))
                {
                    $returnCode = $this->mInventoryFieldsData->setValue($columnName, $newValue);
                }
            }
        }
        else
        {
            // users data from adm_users table
            $returnCode = parent::setValue($columnName, $newValue);
        }

        return $returnCode;
    }

    /**
     * Checks if the current user is allowed to view the profile of the user of the parameter.
     * If will check if user has edit rights with method editProfile or if the user is a member
     * of a role where the current user has the right to view profiles.
     * @param $item User object of the user that should be checked if the current user can view his profile.
     * @return bool Return @b true if the current user is allowed to view the profile of the user from @b $user.
     */
    public function hasRightViewItem($item)
    {
        global $gCurrentUser;

        $viewProfile = false;

        if($item instanceof \User)
        {
            // Hat ein User Profileedit rechte, darf er es natuerlich auch sehen
            if(!$gCurrentUser->editInventory($item))
            {
                $viewProfile = true;
            }
            else
            {
                // Benutzer, die alle Listen einsehen duerfen, koennen auch alle Profile sehen
                if($gCurrentUser->checkRolesRight('rol_inventory'))
                {
                    $viewProfile = true;
                }
                else
                {
                    $sql = 'SELECT rol_id, rol_this_list_view
                              FROM '.TBL_INVENT.'
                        INNER JOIN '.TBL_ROLES.'
                                ON rol_id = mem_rol_id
                        INNER JOIN '.TBL_CATEGORIES.'
                                ON cat_id = rol_cat_id
                             WHERE mem_usr_id = '.$item->getValue('inv_id'). '
                               AND mem_begin <= \''.DATE_NOW.'\'
                               AND mem_end    > \''.DATE_NOW.'\'
                               AND rol_valid  = 1
                               AND (  cat_org_id = '.$this->organizationId.'
                                   OR cat_org_id IS NULL ) ';
                    $pdoStatement = $this->db->query($sql);

                    if($pdoStatement->rowCount() > 0)
                    {
                        while($row = $pdoStatement->fetch())
                        {
                            if($row['rol_this_list_view'] == 2)
                            {
                                // alle angemeldeten Benutzer duerfen Rollenlisten/-profile sehen
                                $viewProfile = true;
                            }
                            elseif($row['rol_this_list_view'] == 1
                            && isset($this->listViewRights[$row['rol_id']]))
                            {
                                // nur Rollenmitglieder duerfen Rollenlisten/-profile sehen
                                $viewProfile = true;
                            }
                        }
                    }
                }
            }
        }
        return $viewProfile;
    }

}
