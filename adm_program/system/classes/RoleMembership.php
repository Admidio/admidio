<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Handle memberships of roles and manage it in the database table adm_members
 *
 * The class search in the database table **adm_members** for role memberships of
 * users. It has easy methods to start or stop a membership.
 *
 * **Code example**
 * ```
 * // start membership without read data before
 * $membership = new TableMembers($gDb);
 * $membership->startMembership($roleId, $userId);
 *
 * // read membership data and then stop membership
 * $membership = new TableMembers($gDb);
 * $membership->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
 * $membership->stopMembership();
 * ```
 */
class RoleMembership extends TableRoles
{
    protected $mMembershipPeriods;

    /**
     * Constructor that will create an object of a recordset of the table adm_roles.
     * If the id is set than the specific role will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $roleId   The recordset of the role with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $roleId = 0)
    {
        $this->mMembershipPeriods = array();

        parent::__construct($database, $roleId);
    }

    /**
     * @param int $userId ID if the user who should get the membership to this role.
     * @param string $startDate Date in format YYYY-MM-DD at which the role membership should start.
     * @param string $endDate Date in format YYYY-MM-DD at which the role membership should end.
     * @param bool $leader Flag if the user is assigned as a leader to this role.
     * @return void
     * @throws AdmException
     */
    public function setMembership(int $userId, string $startDate, string $endDate, bool $leader = false)
    {
        global $gCurrentUser, $gCurrentUserId;

        $newMembershipSaved = false;
        $updateNecessary = true;

        // search for existing periods of membership and adjust them
        $sql = 'SELECT mem_id, mem_uuid, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_leader
              FROM ' . TBL_MEMBERS . '
             WHERE mem_rol_id = ? -- $this->getValue(\'rol_id\')
               AND mem_usr_id = ? -- $userId
             ORDER BY mem_begin ASC';
        $queryParams = array(
            $this->getValue('rol_id'),
            $userId
        );
        $membersStatement = $this->db->queryPrepared($sql, $queryParams);
        $membersList = $membersStatement->fetchAll();
        $this->db->startTransaction();

        foreach ($membersList as $row) {
            if ($endDate === $row['mem_end'] && $startDate >= $row['mem_begin']) {
                // assignment already exists and must not be updated
                $updateNecessary = false;
            } else {
                if ($startDate < $row['mem_end'] && $endDate > $row['mem_end']) {
                    // existing period overlaps the new start date
                    if ($leader === (bool) $row['mem_leader']) {
                        $newMembershipSaved = true;

                        // save new membership period
                        $membership = new TableMembers($this->db);
                        $membership->setArray($row);
                        $membership->setValue('mem_end', $endDate);
                    } else {
                        // End existing period and later add new period with changed leader flag
                        $tempEndDate = DateTime::createFromFormat('Y-m-d', $startDate);
                        $newEndDate = $tempEndDate->sub(new DateInterval('P1D'))->format('Y-m-d');

                        $membership = new TableMembers($this->db);
                        $membership->setArray($row);
                        $membership->setValue('mem_end', $newEndDate);
                    }
                    $membership->save();
                } elseif ($startDate < $row['mem_begin'] && $endDate > $row['mem_end']) {
                    // new time period surrounds existing time period than delete that period
                    $membership = new TableMembers($this->db);
                    $membership->setArray($row);
                    $membership->delete();
                } elseif ($startDate === $row['mem_begin'] && $startDate > $endDate) {
                    // new time period is negative than search for equal start date and delete this period
                    $newMembershipSaved = true;
                    $membership = new TableMembers($this->db);
                    $membership->setArray($row);
                    $membership->delete();
                } elseif ($startDate < $row['mem_begin'] && $endDate > $row['mem_begin'] && !$newMembershipSaved) {
                    // existing period overlaps the new end date
                    if ($leader === (bool) $row['mem_leader']) {
                        $newMembershipSaved = true;

                        // save new membership period
                        $membership = new TableMembers($this->db);
                        $membership->setArray($row);
                        $membership->setValue('mem_begin', $startDate);
                    } else {
                        // End existing period and later add new period with changed leader flag
                        $tempStartDate = DateTime::createFromFormat('Y-m-d', $endDate);
                        $newStartDate = $tempStartDate->add(new DateInterval('P1D'))->format('Y-m-d');

                        $membership = new TableMembers($this->db);
                        $membership->setArray($row);
                        $membership->setValue('mem_end', $newStartDate);
                    }
                    $membership->save();
                }
            }
        }

        if (!$newMembershipSaved && $updateNecessary) {
            // new existing period was adjusted to the new membership than save the new membership
            $membership = new TableMembers($this->db);
            $membership->setValue('mem_rol_id', $this->getValue('rol_id'));
            $membership->setValue('mem_usr_id', $userId);
            $membership->setValue('mem_begin', $startDate);
            $membership->setValue('mem_end', $endDate);
            if ($this->getValue('cat_name_intern') === 'EVENTS') {
                $membership->setValue('mem_approved', Participants::PARTICIPATION_YES);
            }
            $membership->save();
        }

        // if role is administrator than only administrator can add new user,
        // but don't change their own membership, because there must be at least one administrator
        if ($updateNecessary &&
            (bool) $this->getValue('rol_administrator') === true
            && !$gCurrentUser->isAdministrator()) {
            $this->db->rollback();
            if ($userId !== $gCurrentUserId) {
                throw new AdmException('You could not edit your own membership to an administrator role!');
            } else {
                throw new AdmException('Members to administrator role could only be assigned by administrators!');
            }
        }


        $this->db->endTransaction();
    }

    /**
     * Starts a new membership of the given user to the role of this class. The membership will start today
     * and will "never" ends. End date is set to 9999-12-31.
     * @param int $userId ID if the user who should get the membership to this role.
     * @param bool $leader Flag if the user is assigned as a leader to this role.
     * @return void
     * @throws AdmException
     */
    public function startMembership(int $userId, bool $leader = false)
    {
        $this->setMembership($userId, DATE_NOW, '9999-12-31', $leader);
    }

    /**
     * Stops a current membership of the given user to the role of this class. The membership will stop
     * yesterday.
     * @param int $userId ID if the user who should get the membership to this role.
     * @return void
     * @throws AdmException
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
            $this->setMembership($userId, $membersList[0]['mem_begin'], $newEndDate);
        }
    }
}
