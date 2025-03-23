<?php
namespace Admidio\Roles\Entity;

use Admidio\Categories\Entity\Category;
use Admidio\Events\ValueObject\Participants;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Roles\ValueObject\RoleDependency;
use Admidio\Users\Entity\User;
use DateInterval;
use DateTime;

/**
 * @brief Class manages access to database table adm_roles
 *
 * This class is used to create a role object.
 * A role can be administered over this class in the database.
 * For this purpose the information of the role as well as the associated category
 * are read out. But only the role data are written
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Role extends Entity
{
    public const ROLE_GROUP = 0;
    public const ROLE_EVENT = 1;

    public const VIEW_NOBODY = 0;
    public const VIEW_LEADERS = 3;
    public const VIEW_ROLE_MEMBERS = 1;
    public const VIEW_LOGIN_USERS = 2;

    // constants for column rol_leader_rights
    const ROLE_LEADER_NO_RIGHTS = 0;
    const ROLE_LEADER_MEMBERS_ASSIGN = 1;
    const ROLE_LEADER_MEMBERS_EDIT = 2;
    const ROLE_LEADER_MEMBERS_ASSIGN_EDIT = 3;

    /**
     * @var int number of leaders of this role
     */
    protected int $countLeaders;
    /**
     * @var int number of members (without leaders) of this role
     */
    protected int $countMembers;
    /**
     * @var int|null Represents the type of the role that could be ROLE_GROUP (default) or ROLE_EVENT
     */
    protected int $type = Role::ROLE_GROUP;

    /**
     * Constructor that will create an object of a recordset of the table adm_roles.
     * If the id is set than the specific role will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $rolId The recordset of the role with this id will be loaded.
     *                           If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $rolId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'rol_cat_id');

        parent::__construct($database, TBL_ROLES, 'rol', $rolId);

        // set default values for new roles
        if ($rolId === 0) {
            $this->setValue('rol_view_memberships', Role::VIEW_ROLE_MEMBERS);
            $this->setValue('rol_view_members_profiles', Role::VIEW_NOBODY);
            $this->setValue('rol_mail_this_role', Role::VIEW_LOGIN_USERS);
        }
    }

    /**
     * Set the current role active.
     * Event roles could not be set active.
     * @throws Exception
     */
    public function activate(): void
    {
        if ($this->type === self::ROLE_EVENT) {
            throw new Exception('Event role cannot be set to inactive.');
        }
        $this->toggleValid(true);
    }

    /**
     * checks if user is allowed to assign members to this role
     * @param User $user UserObject of user who should be checked
     * @return bool
     * @throws Exception
     */
    public function allowedToAssignMembers(User $user): bool
    {
        // you aren't allowed to change membership of not active roles
        if ((int)$this->getValue('rol_valid') === 0) {
            return false;
        }

        if ($user->manageRoles()) {
            // only administrators are allowed to assign new members to administrator role
            if ((int)$this->getValue('rol_administrator') === 0 || $user->isAdministrator()) {
                return true;
            }
        } else {
            $rolLeaderRights = (int)$this->getValue('rol_leader_rights');

            // leader are allowed to assign members if it's configured in the role
            if (($rolLeaderRights === $this::ROLE_LEADER_MEMBERS_ASSIGN || $rolLeaderRights === $this::ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
                && $user->isLeaderOfRole((int)$this->getValue('rol_id'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calls clear() Method of parent class and initialize child class specific parameters
     * @throws Exception
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
     * @throws Exception
     */
    public function countLeaders(): int
    {
        if ($this->countLeaders === -1) {
            $sql = 'SELECT COUNT(*) AS count
                      FROM ' . TBL_MEMBERS . '
                     WHERE mem_rol_id = ? -- $this->getValue(\'rol_id\')
                       AND mem_leader = false
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW';
            $pdoStatement = $this->db->queryPrepared($sql, array((int)$this->getValue('rol_id'), DATE_NOW, DATE_NOW));

            $this->countLeaders = (int)$pdoStatement->fetchColumn();
        }

        return $this->countLeaders;
    }

    /**
     * Method determines the number of active members (without leaders) of this role.
     * If it's an event role than the approved state will be considered and also the
     * additional guests.
     * @return int Returns the number of members of this role
     * @throws Exception
     */
    public function countMembers(): int
    {
        if ($this->countMembers === -1) {
            $sql = 'SELECT COUNT(*) + SUM(mem_count_guests) AS count
                      FROM ' . TBL_MEMBERS . '
                     WHERE mem_rol_id  = ? -- $this->getValue(\'rol_id\')
                       AND mem_leader  = false
                       AND ? BETWEEN mem_begin AND mem_end -- DATE_NOW
                       AND (mem_approved IS NULL
                            OR mem_approved < 3)';

            $pdoStatement = $this->db->queryPrepared($sql,
                array((int)$this->getValue('rol_id'), DATE_NOW));
            $this->countMembers = (int)$pdoStatement->fetchColumn();
        }

        return $this->countMembers;
    }

    /**
     * Returns the number of available places within this role for participants. If **rol_max_members** is not set
     * than the method returns INF.
     * @param bool $countLeaders Flag if the leaders should be count as participants. As per default they will not count.
     * @return int|float
     * @throws Exception
     */
    public function countVacancies(bool $countLeaders = false)
    {
        $rolMaxMembers = $this->getValue('rol_max_members');

        if (!is_int($rolMaxMembers)) {
            return INF;
        }

        $sql = 'SELECT mem_usr_id
                  FROM ' . TBL_MEMBERS . '
                 WHERE mem_rol_id = ? -- $this->getValue(\'rol_id\')
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW';
        if (!$countLeaders) {
            $sql .= '
                AND mem_leader = false ';
        }
        $pdoStatement = $this->db->queryPrepared($sql, array((int)$this->getValue('rol_id'), DATE_NOW, DATE_NOW));

        return $rolMaxMembers - $pdoStatement->rowCount();
    }

    /**
     * Set the current role inactive.
     * Administrator and event roles could not be set inactive.
     * @throws Exception
     */
    public function deactivate(): void
    {
        if ($this->getValue('rol_administrator')) {
            throw new Exception('Administrator role cannot be set to inactive.');
        } elseif ($this->type === self::ROLE_EVENT) {
            throw new Exception('Event role cannot be set to inactive.');
        }
        $this->toggleValid(false);
    }

    /**
     * Deletes the selected role of the table and all references in other tables.
     * After that the class will be initialized.
     * @return bool **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
    {
        global $gCurrentSession, $gL10n;

        $rolId = (int)$this->getValue('rol_id');

        if ((int)$this->getValue('rol_default_registration') === 1) {
            // checks if at least one other role has this flag, if not than this role couldn't be deleted
            $sql = 'SELECT COUNT(*) AS count
                      FROM ' . TBL_ROLES . '
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                     WHERE rol_default_registration = true
                       AND rol_id    <> ? -- $rolId
                       AND cat_org_id = ? -- $GLOBALS[\'gCurrentOrgId\']';
            $countRolesStatement = $this->db->queryPrepared($sql, array($rolId, $GLOBALS['gCurrentOrgId']));

            if ((int)$countRolesStatement->fetchColumn() === 0) {
                throw new Exception('SYS_DELETE_NO_DEFAULT_ROLE', array($this->getValue('rol_name'), $gL10n->get('SYS_DEFAULT_ASSIGNMENT_REGISTRATION')));
            }
        }

        // users are not allowed to delete system roles
        if ((int)$this->getValue('rol_system') === 1) {
            throw new Exception('SYS_DELETE_SYSTEM_ROLE', array($this->getValue('rol_name')));
        }
        if ((int)$this->getValue('rol_administrator') === 1) {
            throw new Exception('SYS_CANT_DELETE_ROLE', array($gL10n->get('SYS_ADMINISTRATOR')));
        }

        $this->db->startTransaction();

        $sql = 'DELETE FROM ' . TBL_ROLE_DEPENDENCIES . '
                 WHERE rld_rol_id_parent = ? -- $rolId
                    OR rld_rol_id_child  = ? -- $rolId';
        $this->db->queryPrepared($sql, array($rolId, $rolId));

        $sql = 'DELETE FROM ' . TBL_MEMBERS . '
                 WHERE mem_rol_id = ? -- $rolId';
        $this->db->queryPrepared($sql, array($rolId));

        $sql = 'UPDATE ' . TBL_EVENTS . '
                   SET dat_rol_id = NULL
                 WHERE dat_rol_id = ? -- $rolId';
        $this->db->queryPrepared($sql, array($rolId));

        $sql = 'DELETE FROM ' . TBL_ROLES_RIGHTS_DATA . '
                 WHERE rrd_rol_id = ? -- $rolId';
        $this->db->queryPrepared($sql, array($rolId));

        $return = parent::delete();

        if (isset($gCurrentSession)) {
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
     * @throws Exception
     */
    public static function getCostPeriods(int $costPeriod = 0)
    {
        global $gL10n;

        $costPeriods = array(
            -1 => $gL10n->get('SYS_ONE_TIME'),
            1 => $gL10n->get('SYS_ANNUALLY'),
            2 => $gL10n->get('SYS_HALF_YEARLY'),
            4 => $gL10n->get('SYS_QUARTERLY'),
            12 => $gL10n->get('SYS_MONTHLY')
        );

        if ($costPeriod !== 0) {
            return $costPeriods[$costPeriod];
        }

        return $costPeriods;
    }

    /**
     * Read the id of the default list of this role. The list is stored in the column **rol_lst_id**.
     * If there is no list stored then the system default list will be returned
     * @return int Returns the default list id of this role
     * @throws Exception
     */
    public function getDefaultList(): int
    {
        global $gSettingsManager;

        $defaultListId = (int)$this->getValue('rol_lst_id');

        // if default list is set, return it
        if ($defaultListId > 0) {
            return $defaultListId;
        }

        if ($this->type === Role::ROLE_EVENT) {
            // read system default list configuration for events
            return $gSettingsManager->getInt('events_list_configuration');
        } else {
            try {
                // read system default list configuration
                $defaultListConfiguration = $gSettingsManager->getInt('groups_roles_default_configuration');
            } catch (\InvalidArgumentException $exception) {
                // if no default list was set than load another global list of this organization
                $sql = 'SELECT MIN(lst_id) as lst_id
                          FROM ' . TBL_LISTS . '
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
     * @param string $format Returns the field value in a special format **text**, **html**, **database**
     *                                or datetime (detailed description in method description)
     *                                * 'd.m.Y' : a date or timestamp field accepts the format of the PHP date() function
     *                                * 'html'  : returns the value in html-format if this is necessary for that field type.
     *                                * 'database' : returns the value that is stored in database with no format applied
     * @return int|float|string|bool Returns the value of the database column.
     *                               If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @throws Exception
     */
    public function getValue(string $columnName, string $format = '')
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
     * @throws Exception
     */
    public function hasFormerMembers(): bool
    {
        $sql = 'SELECT COUNT(*) AS count
                  FROM ' . TBL_MEMBERS . '
                 WHERE mem_rol_id = ? -- $this->getValue(\'rol_id\')
                   AND (  mem_begin > ? -- DATE_NOW
                       OR mem_end   < ? ) -- DATE_NOW';
        $pdoStatement = $this->db->queryPrepared($sql, array((int)$this->getValue('rol_id'), DATE_NOW, DATE_NOW));

        return $pdoStatement->fetchColumn() > 0;
    }

    /**
     * This method checks if the current user is allowed to view this role. Therefore,
     * the view properties of the role will be checked. If it's an event role than
     * we also check if the user is a member of the roles that could participate at the event.
     * @return bool Return true if the current user is allowed to view this role
     * @throws Exception
     */
    public function isVisible(): bool
    {
        global $gCurrentUser, $gValidLogin;

        if (!$gValidLogin) {
            return false;
        }

        $rolId = (int)$this->getValue('rol_id');

        if ($this->type !== Role::ROLE_EVENT) {
            return $gCurrentUser->hasRightViewRole($rolId);
        }

        if ((int)$this->getValue('rol_view_memberships') === 0) {
            return false;
        }

        // check if user is member of a role who could view the event
        $sql = 'SELECT dat_id
                  FROM ' . TBL_EVENTS . '
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
            if ($this->getValue('cat_name_intern') === 'EVENTS') {
                $this->setType(Role::ROLE_EVENT);
            } else {
                $this->setType(Role::ROLE_GROUP);
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
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        global $gCurrentSession, $gCurrentUser;

        $fieldsChanged = $this->columnsValueChanged;

        // the right to edit roles should only be checked for group roles and not for event roles
        if (!$this->saveChangesWithoutRights
            && $this->type === Role::ROLE_GROUP
            && !in_array((int)$this->getValue('rol_cat_id'), $gCurrentUser->getAllEditableCategories('ROL'), true)) {
            throw new Exception('Role could not be saved because you are not allowed to edit roles of this category.');
        }

        $returnValue = parent::save($updateFingerPrint);

        // after saving check if user objects have to be read in again
        if ($fieldsChanged && isset($gCurrentSession)) {
            // all active users must renew their user data because maybe their
            // rights have been changed if they were members of this role
            $gCurrentSession->reloadAllSessions();
        }

        return $returnValue;
    }

    /**
     * Method will set a membership with the given start and end date. If there are cutting time periods these periods
     * be adjusted. Periods within the new periods will be deleted. The leader flag will be respected and could lead
     * to separate membership periods. If someone has already a membership and get a leader than he has two
     * sequential periods. If the current role has dependent parent roles than the membership will be also assigned to
     * the parent roles. After all a session refresh for the user will be initiated to get the new role
     * assignments at once.
     * @param int $userId ID if the user who should get the membership to this role.
     * @param string $startDate Date in format YYYY-MM-DD at which the role membership should start.
     * @param string $endDate Date in format YYYY-MM-DD at which the role membership should end.
     * @param null|bool $leader Flag if the user is assigned as a leader to this role.
     *                          If set to null than the leader flag will not be changed if a membership already exists
     *                          and set to false if it doesn't exist.
     * @param bool $forcePeriod If set, the period of the allocation is shortened if the new period starts later
     *                          than it is already allocated.
     * @return void
     * @throws Exception
     */
    public function setMembership(int $userId, string $startDate, string $endDate, ?bool $leader = null, bool $forcePeriod = false)
    {
        global $gCurrentUser, $gCurrentUserId, $gCurrentSession;

        $newMembershipSaved = false;
        $updateNecessary = true;
        $startDateSaved = '';
        $endDateSaved = '';

        // if role is administrator than only administrator can add new user,
        // but don't change their own membership, because there must be at least one administrator
        if ((bool)$this->getValue('rol_administrator') === true) {
            if (!$gCurrentUser->isAdministrator()) {
                throw new Exception('Members to administrator role could only be assigned by administrators!');
            } elseif ($gCurrentUser->isAdministrator() && $userId === $gCurrentUserId) {
                throw new Exception('You cannot edit your own membership in an administrator role!');
            }
        }

        // search for existing periods of membership and adjust them
        $sql = 'SELECT mem_id, mem_uuid, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_leader
              FROM ' . TBL_MEMBERS . '
             WHERE mem_rol_id = ? -- $this->getValue(\'rol_id\')
               AND mem_usr_id = ? -- $userId
             ORDER BY mem_begin';
        $queryParams = array(
            $this->getValue('rol_id'),
            $userId
        );
        $membersStatement = $this->db->queryPrepared($sql, $queryParams);
        $membersList = $membersStatement->fetchAll();
        $this->db->startTransaction();

        foreach ($membersList as $row) {
            if($leader === null && $startDate >= $row['mem_begin'] && $startDate <= $row['mem_end']) {
                // set leader flag to existing state because the state should not change
                $leader = (bool) $row['mem_leader'];
            }

            if ($endDate === $row['mem_end'] && $startDate >= $row['mem_begin'] && $leader === (bool) $row['mem_leader'] && !$forcePeriod) {
                // assignment already exists and must not be updated
                $updateNecessary = false;
            } else {
                $tempStartDate = DateTime::createFromFormat('Y-m-d', $startDate);
                $oneDayBeforeStartDate = $tempStartDate->sub(new DateInterval('P1D'))->format('Y-m-d');

                if ($startDate < $row['mem_end'] && $endDate >= $row['mem_end']) {
                    // new period starts in existing period and ends after existing period
                    if ($leader === (bool)$row['mem_leader']) {
                        $newMembershipSaved = true;

                        // change existing membership period
                        $membership = new Membership($this->db);
                        $membership->setArray($row);
                        if ($startDateSaved !== $startDate && $endDateSaved !== $endDate) {
                            $membership->setValue('mem_begin', $startDate);
                            $membership->setValue('mem_end', $endDate);
                            $membership->save();
                            $startDateSaved = $startDate;
                            $endDateSaved = $endDate;
                        } else {
                            // this period was already saved so delete this duplicate entry
                            $membership->delete();
                        }
                    } else {
                        // End existing period and later add new period with changed leader flag
                        $tempEndDate = DateTime::createFromFormat('Y-m-d', $startDate);
                        $newEndDate = $tempEndDate->sub(new DateInterval('P1D'))->format('Y-m-d');

                        $membership = new Membership($this->db);
                        $membership->setArray($row);
                        if ($newEndDate < $row['mem_begin']) {
                            // leader flag changed at the day the membership started then delete old membership
                            $membership->delete();
                        } else {
                            if ($startDateSaved !== $startDate && $endDateSaved !== $endDate) {
                                $membership->setValue('mem_end', $newEndDate);
                                $membership->save();
                            } else {
                                // this period was already saved so delete this duplicate entry
                                $membership->delete();
                            }
                        }
                    }
                } elseif ($startDate <= $row['mem_begin'] && $endDate >= $row['mem_begin'] && !$newMembershipSaved) {
                    // new period starts before existing period and ends in existing period
                    $membership = new Membership($this->db);
                    $membership->setArray($row);

                    if ($startDateSaved !== $startDate && $endDateSaved !== $endDate) {
                        if ($leader === (bool)$row['mem_leader']) {
                            // change existing membership period
                            $newMembershipSaved = true;
                            $membership->setValue('mem_begin', $startDate);
                            $membership->setValue('mem_end', $endDate);
                            $startDateSaved = $startDate;
                            $endDateSaved = $endDate;
                        } else {
                            // End existing period and later add new period with changed leader flag
                            $tempStartDate = DateTime::createFromFormat('Y-m-d', $endDate);
                            $newStartDate = $tempStartDate->add(new DateInterval('P1D'))->format('Y-m-d');
                            $membership->setValue('mem_end', $newStartDate);
                        }
                        $membership->save();
                    } else {
                        // this period was already saved so delete this duplicate entry
                        $membership->delete();
                    }
                } elseif ($oneDayBeforeStartDate === $row['mem_end'] && $leader === (bool)$row['mem_leader']) {
                    // existing period ends 1 day before new period than merge the two periods
                    $newMembershipSaved = true;

                    // save new membership period
                    $membership = new Membership($this->db);
                    $membership->setArray($row);
                    if ($startDateSaved !== $startDate && $endDateSaved !== $endDate) {
                        $membership->setValue('mem_end', $endDate);
                        $membership->save();
                        $startDateSaved = $row['mem_begin'];
                        $endDateSaved = $endDate;
                    } else {
                        // this period was already saved so delete this duplicate entry
                        $membership->delete();
                    }
                } elseif ($endDate === $row['mem_end'] && $startDate === $row['mem_begin'] && $leader !== (bool)$row['mem_leader']) {
                    // exact same time period but the leader flag has changed than delete current period
                    // and updated period later
                    $membership = new Membership($this->db);
                    $membership->setArray($row);
                    $membership->delete();
                } elseif ($startDate < $row['mem_begin'] && $endDate > $row['mem_end']) {
                    // new time period surrounds existing time period than delete that period
                    $membership = new Membership($this->db);
                    $membership->setArray($row);
                    $membership->delete();
                } elseif ($startDate === $row['mem_begin'] && $startDate > $endDate) {
                    // new time period is negative than search for equal start date and delete this period
                    $newMembershipSaved = true;
                    $membership = new Membership($this->db);
                    $membership->setArray($row);
                    $membership->delete();
                }
            }
        }

        if (!$newMembershipSaved && $updateNecessary) {
            // new existing period was adjusted to the new membership than save the new membership
            $membership = new Membership($this->db);
            $membership->setValue('mem_rol_id', $this->getValue('rol_id'));
            $membership->setValue('mem_usr_id', $userId);
            $membership->setValue('mem_begin', $startDate);
            $membership->setValue('mem_end', $endDate);
            if($leader === null) {
                // no leader state was set than set it to false
                $membership->setValue('mem_leader', false);
            } else {
                $membership->setValue('mem_leader', $leader);
            }

            if ($this->type === self::ROLE_EVENT) {
                $membership->setValue('mem_approved', Participants::PARTICIPATION_YES);
            }
            $membership->save();
        }

        // reload session of that user because of changes to the assigned roles and rights
        $gCurrentSession->reload($userId);

        $this->db->endTransaction();
    }

    /**
     * Set the type of the role. This could be a role that represents a group ROLE_GROUP that will be used in the
     * groups and roles module or participants of an event ROLE_EVENT that will be used within the event module.
     * @param int $type Represents the type of the role that could be ROLE_GROUP (default) or ROLE_EVENT
     * @return void
     */
    public function setType(int $type)
    {
        if ($type === Role::ROLE_GROUP || $type === Role::ROLE_EVENT) {
            $this->type = $type;
        }
    }

    /**
     * Set a new value for a column of the database table. The value is only saved in the object.
     * You must call the method **save** to store the new value to the database.
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed $newValue The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @throws Exception
     */
    public function setValue(string $columnName, $newValue, bool $checkValue = true): bool
    {
        global $gL10n, $gCurrentUser;

        if ($checkValue) {
            if ($columnName === 'rol_cat_id' && isset($gCurrentUser) && $gCurrentUser instanceof User) {
                $category = new Category($this->db);
                if (is_int($newValue)) {
                    if (!$category->readDataById($newValue)) {
                        throw new Exception('No Category with the given id ' . $newValue . ' was found in the database.');
                    }
                } else {
                    if (!$category->readDataByUuid($newValue)) {
                        throw new Exception('No Category with the given uuid ' . $newValue . ' was found in the database.');
                    }
                    $newValue = $category->getValue('cat_id');
                }
            }

            if ($columnName === 'rol_default_registration' && $newValue == '0' && $this->dbColumns[$columnName] == '1') {
                // checks if at least one other role has this flag
                $sql = 'SELECT COUNT(*) AS count
                          FROM ' . TBL_ROLES . '
                    INNER JOIN ' . TBL_CATEGORIES . '
                            ON cat_id = rol_cat_id
                         WHERE rol_default_registration = true
                           AND rol_id    <> ? -- $this->getValue(\'rol_id\')
                           AND cat_org_id = ? -- $GLOBALS[\'gCurrentOrgId\']';
                $pdoStatement = $this->db->queryPrepared($sql, array((int)$this->getValue('rol_id'), $GLOBALS['gCurrentOrgId']));

                if ((int)$pdoStatement->fetchColumn() === 0) {
                    throw new Exception('SYS_NO_DEFAULT_ROLE', array($gL10n->get('SYS_DEFAULT_ASSIGNMENT_REGISTRATION')));
                }
            }

            // administrators should always assign roles and view all contacts
            if ($this->getValue('rol_administrator')
                && in_array($columnName, array('rol_assign_roles', 'rol_all_lists_view'))) {
                $newValue = 1;
            }

            if ($this->type === Role::ROLE_EVENT) {
                if (!$this->isNewRecord()
                    && in_array($columnName, array('rol_name', 'rol_description', 'rol_cat_id'))) {
                    return false;
                }

                if (!empty($newValue) && in_array($columnName, array('rol_start_date', 'rol_start_time', 'rol_end_date', 'rol_end_time', 'rol_max_members'))) {
                    return false;
                }
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * Starts a new membership of the given user to the role of this class. The membership will start today
     * and will "never" ends. End date is set to 9999-12-31.
     * @param int $userId ID if the user who should get the membership to this role.
     * @param null|bool $leader Flag if the user is assigned as a leader to this role.
     * *                          If set to null than the leader flag will not be changed if a membership already exists
     * *                          and set to false if it doesn't exist.
     * @return void
     * @throws Exception
     */
    public function startMembership(int $userId, ?bool $leader = null)
    {
        if ($this->getValue('rol_max_members') > $this->countMembers()
            || (int)$this->getValue('rol_max_members') === 0) {
            $this->db->startTransaction();
            $this->setMembership($userId, DATE_NOW, '9999-12-31', $leader);

            // find the parent roles and assign user to parent roles
            $dependencies = RoleDependency::getParentRoles($this->db, $this->getValue('rol_id'));

            foreach ($dependencies as $tmpRole) {
                $parentRole = new Role($this->db, $tmpRole);
                $parentRole->startMembership($userId, $leader);
            }
            $this->db->endTransaction();
        } else {
            throw new Exception('SYS_ROLE_MAX_MEMBERS', array($this->getValue('rol_name')));
        }
    }

    /**
     * Stops a current membership of the given user to the role of this class. The membership will stop
     * yesterday.
     * @param int $userId ID if the user who should get the membership to this role.
     * @return void
     * @throws Exception
     */
    public function stopMembership(int $userId)
    {
        // search for existing periods of membership and adjust them
        $sql = 'SELECT mem_id, mem_uuid, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_leader
                  FROM ' . TBL_MEMBERS . '
                 WHERE mem_rol_id = ? -- $this->getValue(\'rol_id\')
                   AND mem_usr_id = ? -- $userId
                   AND ? BETWEEN mem_begin AND mem_end ';
        $queryParams = array(
            $this->getValue('rol_id'),
            $userId,
            DATE_NOW
        );
        $membersStatement = $this->db->queryPrepared($sql, $queryParams);
        $membersList = $membersStatement->fetchAll();

        if (count($membersList) > 0) {
            $endDate = DateTime::createFromFormat('Y-m-d', DATE_NOW);
            $newEndDate = $endDate->sub(new DateInterval('P1D'))->format('Y-m-d');
            $this->setMembership($userId, $membersList[0]['mem_begin'], $newEndDate, (bool)$membersList[0]['mem_leader']);
        }
    }

    /**
     * Toggle the valid status of a role. The role could be set to inactive or active again.
     * @param bool $status Valid status that should be set.
     * @throws Exception
     */
    private function toggleValid(bool $status): void
    {
        global $gCurrentSession;

        // system roles are always active and could therefore not be toggled
        if ((int)$this->getValue('rol_system') === 0) {
            $this->setValue('rol_valid', $status);
            $this->save();

            // all active users must renew their user data because maybe their
            // rights have been changed if they were members of this role
            $gCurrentSession->reloadAllSessions();
        } else {
            throw new Exception('Role ' . $this->getValue('rol_name') . ' is a system role and could not be set inactive!');
        }
    }
}
