<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableMembers
 * @brief Handle memberships of roles and manage it in the database table adm_members
 *
 * The class search in the database table @b adm_members for role memberships of
 * users. It has easy methods to start or stop a membership.
 * @par Examples
 * @code // start membership without read data before
 * $membership = new TableMembers($gDb);
 * $membership->startMembership($roleId, $userId);
 *
 * // read membership data and then stop membership
 * $membership = new TableMembers($gDb);
 * $membership->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
 * $membership->stopMembership(); @endcode
 */
class TableMembers extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_members.
     * If the id is set than the specific membership will be loaded.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $memId    The recordset of the membership with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $memId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_ROLES, 'rol_id', 'mem_rol_id');

        parent::__construct($database, TBL_MEMBERS, 'mem', $memId);
    }

    /**
     * Deletes a membership for the assigned role and user. In opposite to removeMembership
     * this method will delete the entry and you can't see any history assignment.
     * If the user is the current user then initiate a refresh of his role cache.
     * @param int $roleId Stops the membership of this role
     * @param int $userId The user who should loose the member of the role.
     * @return bool Return @b true if the membership was successful deleted.
     */
    public function deleteMembership($roleId = 0, $userId = 0)
    {
        global $gCurrentUser;

        // if role and user is set, than search for this membership and load data into class
        if ($roleId > 0 && $userId > 0)
        {
            $this->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
        }

        if ($this->getValue('mem_rol_id') > 0 && $this->getValue('mem_usr_id') > 0)
        {
            $this->delete();

            // if role membership of current user will be changed then renew his rights arrays
            if ($userId === (int) $gCurrentUser->getValue('usr_id'))
            {
                $gCurrentUser->renewRoleData();
            }

            return true;
        }

        return false;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentSession;

        $returnStatus = parent::save($updateFingerPrint);

        if ($returnStatus && $gCurrentSession instanceof \Session)
        {
            // renew user object of the affected user because of edited role assignment
            $gCurrentSession->renewUserObject($this->getValue('mem_usr_id'));
        }

        return $returnStatus;
    }

    /**
     * Starts a membership for the assigned role and user from now until 31.12.9999.
     * An existing membership will be extended if necessary. If the user is the
     * current user then initiate a refresh of his role cache.
     * @param int  $roleId Assign the membership to this role
     * @param int  $userId The user who should get a member of the role.
     * @param bool $leader If value @b 1 then the user will be a leader of the role and get more rights.
     * @return bool Return @b true if the assignment was successful.
     */
    public function startMembership($roleId = 0, $userId = 0, $leader = null)
    {
        global $gCurrentUser;

        // if role and user is set, than search for this membership and load data into class
        if ($roleId > 0 && $userId > 0)
        {
            $this->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
        }

        if ($this->getValue('mem_rol_id') > 0 && $this->getValue('mem_usr_id') > 0)
        {
            // Beginn nicht ueberschreiben, wenn schon existiert
            if ($this->new_record || strcmp($this->getValue('mem_begin', 'Y-m-d'), DATE_NOW) > 0)
            {
                $this->setValue('mem_begin', DATE_NOW);
            }

            // Leiter sollte nicht ueberschrieben werden, wenn nicht uebergeben wird
            if ($leader === null)
            {
                if ($this->new_record)
                {
                    $this->setValue('mem_leader', false);
                }
            }
            else
            {
                $this->setValue('mem_leader', $leader);
            }

            $this->setValue('mem_end', DATE_MAX);

            if ($this->columnsValueChanged)
            {
                $this->save();

                // if role membership of current user will be changed then renew his rights arrays
                if ((int) $gCurrentUser->getValue('usr_id') === $userId)
                {
                    $gCurrentUser->renewRoleData();
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Stops a membership for the assigned role and user from now until 31.12.9999.
     * If the user is the current user then initiate a refresh of his role cache. If
     * the last membership of a administrator role should be stopped then throw an exception.
     * @param int $roleId Stops the membership of this role
     * @param int $userId The user who should loose the member of the role.
     * @throws AdmException
     * @return bool Return @b true if the membership removal was successful.
     */
    public function stopMembership($roleId = 0, $userId = 0)
    {
        global $gCurrentUser;

        // if role and user is set, than search for this membership and load data into class
        if ($roleId > 0 && $userId > 0)
        {
            $this->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
        }

        if (!$this->new_record && $this->getValue('mem_rol_id') > 0 && $this->getValue('mem_usr_id') > 0)
        {
            // subtract one day, so that user leaves role immediately
            $now = new DateTime();
            $oneDayOffset = new DateInterval('P1D');
            $nowDate = $now->format('Y-m-d');
            $endDate = $now->sub($oneDayOffset)->format('Y-m-d');

            // only stop membership if there is an actual membership
            // the actual date must be after the beginning
            // and the actual date must be before the end date
            if (strcmp($nowDate, $this->getValue('mem_begin', 'Y-m-d')) >= 0
            &&  strcmp($endDate, $this->getValue('mem_end',   'Y-m-d')) < 0)
            {
                // if role administrator then check if this membership is the last one -> don't delete it
                if ((int) $this->getValue('rol_administrator') === 1)
                {
                    $sql = 'SELECT mem_id
                              FROM '.TBL_MEMBERS.'
                             WHERE mem_rol_id  = '.$this->getValue('mem_rol_id').'
                               AND mem_usr_id <> '.$this->getValue('mem_usr_id').'
                               AND \''.DATE_NOW.'\' BETWEEN mem_begin AND mem_end ';
                    $memberStatement = $this->db->query($sql);

                    if ($memberStatement->rowCount() === 0)
                    {
                        throw new AdmException('LST_MUST_HAVE_ADMINISTRATOR');
                    }
                }

                // if start date is greater than end date than delete membership
                if (strcmp($this->getValue('mem_begin', 'Y-m-d'), $endDate) >= 0)
                {
                    $this->delete();
                    $this->clear();
                }
                else
                {
                    $this->setValue('mem_end', $endDate);

                    // stop leader
                    if ((int) $this->getValue('mem_leader') === 1)
                    {
                        $this->setValue('mem_leader', 0);
                    }

                    $this->save();
                }

                // if role membership of current user will be changed then renew his rights arrays
                if ((int) $gCurrentUser->getValue('usr_id') === (int) $this->getValue('mem_usr_id'))
                {
                    $gCurrentUser->renewRoleData();
                }

                return true;
            }
        }

        return false;
    }
}
