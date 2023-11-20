<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_roles
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class is used to create a role object.
 * A role can be administered over this class in the database.
 * For this purpose the information of the role as well as the associated category
 * are read out. But only the role data are written
 */
class TableRoles extends TableAccess
{
    public const ROLE_GROUP = 0;
    public const ROLE_EVENT = 1;

    public const VIEW_NOBODY = 0;
    public const VIEW_LEADERS = 3;
    public const VIEW_ROLE_MEMBERS = 1;
    public const VIEW_LOGIN_USERS = 2;
    /**
     * @var int number of leaders of this role
     */
    protected $countLeaders;
    /**
     * @var int number of members (without leaders) of this role
     */
    protected $countMembers;
    /**
     * @var int Represents the type of the role that could be ROLE_GROUP (default) or ROLE_EVENT
     */
    protected $type;

    /**
     * Constructor that will create an object of a recordset of the table adm_roles.
     * If the id is set than the specific role will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $rolId    The recordset of the role with this id will be loaded.
     *                           If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $rolId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'rol_cat_id');

        parent::__construct($database, TBL_ROLES, 'rol', $rolId);

        // set default values for new roles
        if($rolId === 0) {
            $this->setValue('rol_view_memberships', TableRoles::VIEW_ROLE_MEMBERS);
            $this->setValue('rol_view_members_profiles', TableRoles::VIEW_NOBODY);
            $this->setValue('rol_mail_this_role', TableRoles::VIEW_LOGIN_USERS);
        }
    }

    /**
     * checks if user is allowed to assign members to this role
     * @param User $user UserObject of user who should be checked
     * @return bool
     */
    public function allowedToAssignMembers(User $user)
    {
        // you aren't allowed to change membership of not active roles
        if ((int) $this->getValue('rol_valid') === 0) {
            return false;
        }

        if ($user->manageRoles()) {
            // only administrators are allowed to assign new members to administrator role
            if ((int) $this->getValue('rol_administrator') === 0 || $user->isAdministrator()) {
                return true;
            }
        } else {
            $rolLeaderRights = (int) $this->getValue('rol_leader_rights');

            // leader are allowed to assign members if it's configured in the role
            if (($rolLeaderRights === ROLE_LEADER_MEMBERS_ASSIGN || $rolLeaderRights === ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
                && $user->isLeaderOfRole((int) $this->getValue('rol_id'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * checks if user is allowed to edit members of this role
     * @param User $user UserObject of user who should be checked
     * @return bool
     */
    public function allowedToEditMembers(User $user)
    {
        // you aren't allowed to edit users of not active roles
        if ((int) $this->getValue('rol_valid') === 0) {
            return false;
        }

        if ($user->editUsers()) {
            return true;
        }

        $rolLeaderRights = (int) $this->getValue('rol_leader_rights');

        // leader are allowed to assign members if it's configured in the role
        return ($rolLeaderRights === ROLE_LEADER_MEMBERS_EDIT || $rolLeaderRights === ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
            && $user->isMemberOfRole((int) $this->getValue('rol_id'));
    }

    /**
     * Calls clear() Method of parent class and initialize child class specific parameters
     */
    public function clear()
    {
        parent::clear();

        // initialize class members
        $this->countLeaders = -1;
        $this->countMembers = -1;
    }

    /**
     * Method determines the number of active leaders of this role
     * @return int Returns the number of leaders of this role
     */
    public function countLeaders()
    {
        if ($this->countLeaders === -1) {
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_MEMBERS.'
                     WHERE mem_rol_id = ? -- $this->getValue(\'rol_id\')
                       AND mem_leader = false
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW';
            $pdoStatement = $this->db->queryPrepared($sql, array((int) $this->getValue('rol_id'), DATE_NOW, DATE_NOW));

            $this->countLeaders = (int) $pdoStatement->fetchColumn();
        }

        return $this->countLeaders;
    }

    /**
     * Method determines the number of active members (without leaders) of this role.
     * If it's an event role than the approved state will be considered and also the
     * additional guests.
     * @param int $exceptUserId UserId witch shouldn't be counted
     * @return int Returns the number of members of this role
     */
    public function countMembers($exceptUserId = null)
    {
        if ($this->countMembers === -1) {
            $sql = 'SELECT COUNT(*) + SUM(mem_count_guests) AS count
                      FROM '.TBL_MEMBERS.'
                     WHERE mem_rol_id  = ? -- $this->getValue(\'rol_id\')
                       AND mem_usr_id <> ? -- $exceptUserId
                       AND mem_leader  = false
                       AND mem_begin  <= ? -- DATE_NOW
                       AND mem_end     > ? -- DATE_NOW
                       AND (mem_approved IS NULL
                            OR mem_approved < 3)';

            $pdoStatement = $this->db->queryPrepared($sql, array((int) $this->getValue('rol_id'), $exceptUserId, DATE_NOW, DATE_NOW));

            $this->countMembers = (int) $pdoStatement->fetchColumn();
        }

        return $this->countMembers;
    }

    /**
     * Returns the number of available places within this role for participants. If **rol_max_members** is not set
     * than the method returns INF.
     * @param bool $countLeaders Flag if the leaders should be count as participants. As per default they will not count.
     * @return int|float
     */
    public function countVacancies($countLeaders = false)
    {
        $rolMaxMembers = $this->getValue('rol_max_members');

        if (!is_int($rolMaxMembers)) {
            return INF;
        }

        $sql = 'SELECT mem_usr_id
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = ? -- $this->getValue(\'rol_id\')
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW';
        if (!$countLeaders) {
            $sql .= '
                AND mem_leader = false ';
        }
        $pdoStatement = $this->db->queryPrepared($sql, array((int) $this->getValue('rol_id'), DATE_NOW, DATE_NOW));

        return $rolMaxMembers - $pdoStatement->rowCount();
    }

    /**
     * Deletes the selected role of the table and all references in other tables.
     * After that the class will be initialized.
     * @throws AdmException
     * @return bool **true** if no error occurred
     */
    public function delete()
    {
        global $gCurrentSession, $gL10n;

        $rolId = (int) $this->getValue('rol_id');

        if ((int) $this->getValue('rol_default_registration') === 1) {
            // checks if at least one other role has this flag, if not than this role couldn't be deleted
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_ROLES.'
                INNER JOIN '.TBL_CATEGORIES.'
                        ON cat_id = rol_cat_id
                     WHERE rol_default_registration = true
                       AND rol_id    <> ? -- $rolId
                       AND cat_org_id = ? -- $GLOBALS[\'gCurrentOrgId\']';
            $countRolesStatement = $this->db->queryPrepared($sql, array($rolId, $GLOBALS['gCurrentOrgId']));

            if ((int) $countRolesStatement->fetchColumn() === 0) {
                throw new AdmException('SYS_DELETE_NO_DEFAULT_ROLE', array($this->getValue('rol_name'), $gL10n->get('SYS_DEFAULT_ASSIGNMENT_REGISTRATION')));
            }
        }

        // users are not allowed to delete system roles
        if ((int) $this->getValue('rol_system') === 1) {
            throw new AdmException('SYS_DELETE_SYSTEM_ROLE', array($this->getValue('rol_name')));
        }
        if ((int) $this->getValue('rol_administrator') === 1) {
            throw new AdmException('SYS_CANT_DELETE_ROLE', array($gL10n->get('SYS_ADMINISTRATOR')));
        }

        $this->db->startTransaction();

        $sql = 'DELETE FROM '.TBL_ROLE_DEPENDENCIES.'
                 WHERE rld_rol_id_parent = ? -- $rolId
                    OR rld_rol_id_child  = ? -- $rolId';
        $this->db->queryPrepared($sql, array($rolId, $rolId));

        $sql = 'DELETE FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = ? -- $rolId';
        $this->db->queryPrepared($sql, array($rolId));

        $sql = 'UPDATE '.TBL_DATES.'
                   SET dat_rol_id = NULL
                 WHERE dat_rol_id = ? -- $rolId';
        $this->db->queryPrepared($sql, array($rolId));

        $sql = 'DELETE FROM '.TBL_ROLES_RIGHTS_DATA.'
                 WHERE rrd_rol_id = ? -- $rolId';
        $this->db->queryPrepared($sql, array($rolId));

        $return = parent::delete();

        if ($gCurrentSession instanceof Session) {
            // all active users must renew their user data because maybe their
            // rights have been changed if they were members of this role
            $gCurrentSession->reloadAllSessions();
        }

        $this->db->endTransaction();

        return $return;
    }

    /**
     * Returns an array with all cost periods with full name in the specific language.
     * @param int $costPeriod The number of the cost period for which the name should be returned
     *                        (-1 = unique, 1 = annually, 2 = semiyearly, 4 = quarterly, 12 = monthly)
     * @return array<int,string>|string Array with all cost or if param costPeriod is set than the full name of that cost period
     */
    public static function getCostPeriods($costPeriod = null)
    {
        global $gL10n;

        $costPeriods = array(
            -1 => $gL10n->get('SYS_ONE_TIME'),
            1  => $gL10n->get('SYS_ANNUALLY'),
            2  => $gL10n->get('SYS_HALF_YEARLY'),
            4  => $gL10n->get('SYS_QUARTERLY'),
            12 => $gL10n->get('SYS_MONTHLY')
        );

        if ($costPeriod !== null) {
            return $costPeriods[$costPeriod];
        }

        return $costPeriods;
    }

    /**
     * Read the id of the default list of this role. The list is stored in the column **rol_lst_id**.
     * If there is no list stored then the system default list will be returned
     * @return int Returns the default list id of this role
     */
    public function getDefaultList()
    {
        global $gSettingsManager;

        $defaultListId = (int) $this->getValue('rol_lst_id');

        // if default list is set, return it
        if ($defaultListId > 0) {
            return $defaultListId;
        }

        if ($this->type === TableRoles::ROLE_EVENT) {
            // read system default list configuration for events
            return $gSettingsManager->getInt('dates_default_list_configuration');
        } else {
            try {
                // read system default list configuration
                $defaultListConfiguration = $gSettingsManager->getInt('groups_roles_default_configuration');
            } catch (\InvalidArgumentException $exception) {
                // if no default list was set than load another global list of this organization
                $sql = 'SELECT MIN(lst_id) as lst_id
                          FROM '.TBL_LISTS.'
                         WHERE lst_org_id = ? -- $GLOBALS[\'gCurrentOrgId\']
                           AND lst_global = true ';
                $statement = $this->db->queryPrepared($sql, array($GLOBALS['gCurrentOrgId']));
                $row = $statement->fetch();
                $defaultListConfiguration = $row['lst_id'];
            }

            return $defaultListConfiguration;
        }
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format          Returns the field value in a special format **text**, **html**, **database**
     *                                or datetime (detailed description in method description)
     *                                * 'd.m.Y' : a date or timestamp field accepts the format of the PHP date() function
     *                                * 'html'  : returns the value in html-format if this is necessary for that field type.
     *                                * 'database' : returns the value that is stored in database with no format applied
     * @return int|float|string|bool Returns the value of the database column.
     *                               If the value was manipulated before with **setValue** than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        global $gL10n;

        if ($columnName === 'rol_description' && $format === 'html') {
            $value = nl2br(parent::getValue($columnName));
        } else {
            $value = parent::getValue($columnName, $format);
        }

        // if text is a translation-id then translate it
        if ($columnName === 'cat_name' && $format !== 'database' && Language::isTranslationStringId($value)) {
            $value = $gL10n->get($value);
        }

        return $value;
    }

    /**
     * Checks if this role has former members
     * @return bool Returns **true** if the role has former memberships
     */
    public function hasFormerMembers()
    {
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = ? -- $this->getValue(\'rol_id\')
                   AND (  mem_begin > ? -- DATE_NOW
                       OR mem_end   < ? ) -- DATE_NOW';
        $pdoStatement = $this->db->queryPrepared($sql, array((int) $this->getValue('rol_id'), DATE_NOW, DATE_NOW));

        return $pdoStatement->fetchColumn() > 0;
    }

    /**
     * This method checks if the current user is allowed to view this role. Therefore,
     * the view properties of the role will be checked. If it's an event role than
     * we also check if the user is a member of the roles that could participate at the event.
     * @return bool Return true if the current user is allowed to view this role
     */
    public function isVisible()
    {
        global $gCurrentUser, $gValidLogin;

        if (!$gValidLogin) {
            return false;
        }

        $rolId = (int) $this->getValue('rol_id');

        if ($this->type !== TableRoles::ROLE_EVENT) {
            return $gCurrentUser->hasRightViewRole($rolId);
        }

        if ((int) $this->getValue('rol_view_memberships') === 0) {
            return false;
        }

        // check if user is member of a role who could view the event
        $sql = 'SELECT dat_id
                  FROM '.TBL_DATES.'
                 WHERE dat_rol_id = ? -- $rolId';
        $pdoStatement = $this->db->queryPrepared($sql, array($rolId));
        $eventParticipationRoles = new RolesRights($this->db, 'event_participation', $pdoStatement->fetchColumn());

        return count(array_intersect($gCurrentUser->getRoleMemberships(), $eventParticipationRoles->getRolesIds())) > 0;
    }

    /**
     * Reads a record out of the table in database selected by the conditions of the param **$sqlWhereCondition** out of the table.
     * If the sql find more than one record the method returns **false**.
     * Per default all columns of the default table will be read and stored in the object.
     * If one record is found than the type of the role (ROLE_GROUP or ROLE_EVENT) is set.
     * @param string           $sqlWhereCondition Conditions for the table to select one record
     * @param array<int,mixed> $queryParams       The query params for the prepared statement
     * @return bool Returns **true** if one record is found
     * @see TableAccess#readDataById
     * @see TableAccess#readDataByUuid
     * @see TableAccess#readDataByColumns
     */
    protected function readData($sqlWhereCondition, array $queryParams = array())
    {
        if (parent::readData($sqlWhereCondition, $queryParams)) {
            if($this->getValue('cat_name_intern') === 'EVENTS') {
                $this->setType(TableRoles::ROLE_EVENT);
            } else {
                $this->setType(TableRoles::ROLE_GROUP);
            }
            return true;
        }

        return false;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these columns with their timestamp will be updated.
     * For new records the organization and ip address will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentSession, $gCurrentUser;

        $fieldsChanged = $this->columnsValueChanged;

        // the right to edit roles should only be checked for group roles and not for event roles
        if (!$this->saveChangesWithoutRights
        && $this->type === TableRoles::ROLE_GROUP
        && !in_array((int) $this->getValue('rol_cat_id'), $gCurrentUser->getAllEditableCategories('ROL'), true)) {
            throw new AdmException('Role could not be saved because you are not allowed to edit roles of this category.');
        }

        $returnValue = parent::save($updateFingerPrint);

        // after saving check if user objects have to be read in again
        if ($fieldsChanged && $gCurrentSession instanceof Session) {
            // all active users must renew their user data because maybe their
            // rights have been changed if they were members of this role
            $gCurrentSession->reloadAllSessions();
        }

        return $returnValue;
    }

    /**
     * Set the current role active.
     * @return bool Returns **true** if the role could be set to active.
     */
    public function setActive()
    {
        return $this->toggleValid(true);
    }

    /**
     * Set the current role inactive.
     * Administrator and event roles could not be set to inactive.
     * @return bool Returns **true** if the role was set to inactive.
     * @throws AdmException
     */
    public function setInactive(): bool
    {
        if ($this->getValue('rol_administrator')) {
            throw new AdmException('Administrator role cannot be set to inactive.');
        } elseif ($this->getValue('cat_name_intern') === 'EVENTS') {
            throw new AdmException('Event role cannot be set to inactive.');
        }
        return $this->toggleValid(false);
    }

    /**
     * Set a new value for a column of the database table. The value is only saved in the object.
     * You must call the method **save** to store the new value to the database.
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @throws AdmException
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        global $gL10n, $gCurrentUser;

        if ($checkValue) {
            if ($columnName === 'rol_cat_id' && isset($gCurrentUser) && $gCurrentUser instanceof User) {
                $category = new TableCategory($this->db);
                if(is_int($newValue)) {
                    if(!$category->readDataById($newValue)) {
                        throw new AdmException('No Category with the given id '. $newValue. ' was found in the database.');
                    }
                } else {
                    if(!$category->readDataByUuid($newValue)) {
                        throw new AdmException('No Category with the given uuid '. $newValue. ' was found in the database.');
                    }
                    $newValue = $category->getValue('cat_id');
                }
            }

            if ($columnName === 'rol_default_registration' && $newValue == '0' && $this->dbColumns[$columnName] == '1') {
                // checks if at least one other role has this flag
                $sql = 'SELECT COUNT(*) AS count
                          FROM '.TBL_ROLES.'
                    INNER JOIN '.TBL_CATEGORIES.'
                            ON cat_id = rol_cat_id
                         WHERE rol_default_registration = true
                           AND rol_id    <> ? -- $this->getValue(\'rol_id\')
                           AND cat_org_id = ? -- $GLOBALS[\'gCurrentOrgId\']';
                $pdoStatement = $this->db->queryPrepared($sql, array((int) $this->getValue('rol_id'), $GLOBALS['gCurrentOrgId']));

                if ((int) $pdoStatement->fetchColumn() === 0) {
                    throw new AdmException('SYS_NO_DEFAULT_ROLE', array($gL10n->get('SYS_DEFAULT_ASSIGNMENT_REGISTRATION')));
                }
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * Set the type of the role. This could be a role that represents a group ROLE_GROUP that will be used in the
     * groups and roles module or participants of an event ROLE_EVENT that will be used within the event module.
     * @param int $type Represents the type of the role that could be ROLE_GROUP (default) or ROLE_EVENT
     * @return void
     */
    public function setType($type)
    {
        if($type === TableRoles::ROLE_GROUP || $type === TableRoles::ROLE_EVENT) {
            $this->type = $type;
        }
    }

    /**
     * @param bool $status
     * @return bool
     */
    private function toggleValid($status)
    {
        global $gCurrentSession;

        // system roles are always active and could therefore not be toggled
        if ((int) $this->getValue('rol_system') === 0) {
            $sql = 'UPDATE '.TBL_ROLES.'
                       SET rol_valid = ? -- $status
                     WHERE rol_id = ? -- $this->getValue(\'rol_id\')';
            $this->db->queryPrepared($sql, array((bool) $status, (int) $this->getValue('rol_id')));

            // all active users must renew their user data because maybe their
            // rights have been changed if they were members of this role
            $gCurrentSession->reloadAllSessions();

            return true;
        }

        return false;
    }
}
