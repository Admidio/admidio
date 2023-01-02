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
class TableMembers extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_members.
     * If the id is set than the specific membership will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $memId    The recordset of the membership with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $memId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_ROLES, 'rol_id', 'mem_rol_id');

        parent::__construct($database, TBL_MEMBERS, 'mem', $memId);
    }

    /**
     * Set a new value for a column of the database table. The value is only saved in the object.
     * You must call the method **save** to store the new value to the database. If the unique key
     * column is set to 0 than this record will be a new record and all other columns are marked as changed.
     * This method also queues the changes to the field for admin notification
     * messages. Apart from this, the parent's setValue is used to set the new value.
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @see TableAccess#getValue
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        global $gChangeNotification, $gCurrentSession;

        // New records will be logged in ::save, because their ID is only generated during first save
        if (!$this->newRecord && $gCurrentSession instanceof Session) {
            if (in_array($columnName, array('mem_begin', 'mem_end'))) {
                $oldValue = $this->getValue($columnName, 'Y-m-d');
            } else {
                $oldValue = $this->getValue($columnName);
            }
            if ($oldValue != $newValue) {
                $gChangeNotification->logRoleChange(
                    $this->getValue('mem_usr_id'),
                    $this->getValue('mem_id'),
                    $this->getValue('rol_name'),
                    $columnName,
                    $oldValue,
                    $newValue
                );
            }
        }
        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * Deletes a membership for the assigned role and user. In opposite to removeMembership
     * this method will delete the entry and you can't see any history assignment.
     * If the user is the current user then initiate a refresh of his role cache.
     * @param int $roleId Stops the membership of this role
     * @param int $userId The user who should loose the member of the role.
     * @return bool Return **true** if the membership was successful deleted.
     */
    public function deleteMembership($roleId = 0, $userId = 0)
    {
        global $gCurrentUser;

        // if role and user is set, than search for this membership and load data into class
        if ($roleId > 0 && $userId > 0) {
            $this->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
        }

        if ($this->getValue('mem_rol_id') > 0 && $this->getValue('mem_usr_id') > 0) {
            $this->delete();

            // if role membership of current user will be changed then renew his rights arrays
            if ($userId === $GLOBALS['gCurrentUserId']) {
                $gCurrentUser->renewRoleData();
            }

            return true;
        }

        return false;
    }

    /**
     * Deletes the selected record of the table and optionally sends an admin notification if configured
     * @return true Returns **true** if no error occurred
     */
    public function delete()
    {
        // Queue admin notification about membership deletion
        global $gChangeNotification;

        // If this is a new record that hasn't been written to the database, simply ignore it
        if (!$this->newRecord && is_object($gChangeNotification)) {
            // Log begin, end and leader as changed (set to NULL)
            $usrId = $this->getValue('mem_usr_id');
            $memId = $this->getValue('mem_id');
            $membership = $this->getValue('rol_name');
            $gChangeNotification->logRoleChange(
                $usrId,
                $memId,
                $membership,
                'mem_begin',
                $this->getValue('mem_begin', 'Y-m-d'),
                null, // user=
                null, // deleting=
                true
            );
            $gChangeNotification->logRoleChange(
                $usrId,
                $memId,
                $membership,
                'mem_end',
                $this->getValue('mem_end', 'Y-m-d'),
                null, // user=
                null, // deleting=
                true
            );
            if ($this->getValue('mem_leader')) {
                $gChangeNotification->logRoleChange(
                    $usrId,
                    $memId,
                    $membership,
                    'mem_leaderf',
                    $this->getValue('mem_leader'),
                    null, // user=
                    null, // deleting=
                    true
                );
            }
        }

        // renew user object of the affected user because of edited role assignment
        $GLOBALS['gCurrentSession']->reload($this->getValue('mem_usr_id'));

        return parent::delete();
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentSession, $gChangeNotification;

        $newRecord = $this->newRecord;

        $returnStatus = parent::save($updateFingerPrint);

        if ($returnStatus && $gCurrentSession instanceof Session) {
            // renew user object of the affected user because of edited role assignment
            $gCurrentSession->reload($this->getValue('mem_usr_id'));
        }

        if ($newRecord && is_object($gChangeNotification)) {
            // Queue admin notification about membership deletion

            // storing a record for the first time does NOT update the fields from
            // the roles table => need to create a new object that loads the
            // role name from the database, too!
            $memId = $this->getValue('mem_id');

            $obj = new self($this->db, $memId);

            // Log begin, end and leader as changed (set to NULL)
            $usrId = $obj->getValue('mem_usr_id');
            $membership = $obj->getValue('rol_name');
            $gChangeNotification->logRoleChange(
                $usrId,
                $memId,
                $membership,
                'mem_begin',
                null,
                $obj->getValue('mem_begin', 'Y-m-d'), // user=
                null, // deleting=
                true
            );
            $gChangeNotification->logRoleChange(
                $usrId,
                $memId,
                $membership,
                'mem_end',
                null,
                $obj->getValue('mem_end', 'Y-m-d'), // user=
                null, // deleting=
                true
            );
            if ($obj->getValue('mem_leader')) {
                $gChangeNotification->logRoleChange(
                    $usrId,
                    $memId,
                    $membership,
                    'mem_leader',
                    null,
                    $obj->getValue('mem_leader'), // user=
                    null, // deleting=
                    true
                );
            }
        }

        return $returnStatus;
    }

    /**
     * Starts a membership for the assigned role and user from now until 31.12.9999.
     * An existing membership will be extended if necessary. If the user is the
     * current user then initiate a refresh of his role cache.
     * @param int  $roleId Assign the membership to this role
     * @param int  $userId The user who should get a member of the role.
     * @param bool $leader If value **1** then the user will be a leader of the role and get more rights.
     * @param int  $approvalState Option for User to confirm and adjust the membership ( **1** = User confirmed membership but maybe disagreed, **2** = user accepted membership
     * @return bool Return **true** if the assignment was successful.
     */
    public function startMembership($roleId = 0, $userId = 0, $leader = null, $approvalState = null)
    {
        global $gCurrentUser;

        // if role and user is set, than search for this membership and load data into class
        if ($roleId > 0 && $userId > 0) {
            $this->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
        }

        if ($this->getValue('mem_rol_id') > 0 && $this->getValue('mem_usr_id') > 0) {
            // Beginn nicht ueberschreiben, wenn schon existiert
            if ($this->newRecord || strcmp($this->getValue('mem_begin', 'Y-m-d'), DATE_NOW) > 0) {
                $this->setValue('mem_begin', DATE_NOW);
            }

            // Leiter sollte nicht ueberschrieben werden, wenn nicht uebergeben wird
            if ($leader === null) {
                if ($this->newRecord) {
                    $this->setValue('mem_leader', false);
                }
            } else {
                $this->setValue('mem_leader', $leader);
            }

            $this->setValue('mem_end', DATE_MAX);

            // User hat Rollenmitgliedschaft bestätigt bzw. angepasst
            if ($approvalState > 0) {
                $this->setValue('mem_approved', $approvalState);
            }

            if ($this->columnsValueChanged) {
                $this->save();

                // if role membership of current user will be changed then renew his rights arrays
                if ($GLOBALS['gCurrentUserId'] === $userId) {
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
     * @return bool Return **true** if the membership removal was successful.
     */
    public function stopMembership($roleId = 0, $userId = 0)
    {
        global $gCurrentUser;

        // if role and user is set, than search for this membership and load data into class
        if ($roleId > 0 && $userId > 0) {
            $this->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
        }

        if (!$this->newRecord && $this->getValue('mem_rol_id') > 0 && $this->getValue('mem_usr_id') > 0) {
            // subtract one day, so that user leaves role immediately
            $now = new \DateTime();
            $oneDayOffset = new \DateInterval('P1D');
            $nowDate = $now->format('Y-m-d');
            $endDate = $now->sub($oneDayOffset)->format('Y-m-d');

            // only stop membership if there is an actual membership
            // the actual date must be after the beginning
            // and the actual date must be before the end date
            if (strcmp($nowDate, $this->getValue('mem_begin', 'Y-m-d')) >= 0
            &&  strcmp($endDate, $this->getValue('mem_end', 'Y-m-d')) < 0) {
                // if role administrator then check if this membership is the last one -> don't delete it
                if ((int) $this->getValue('rol_administrator') === 1) {
                    $sql = 'SELECT mem_id
                              FROM '.TBL_MEMBERS.'
                             WHERE mem_rol_id  = ? -- $this->getValue(\'mem_rol_id\')
                               AND mem_usr_id <> ? -- $this->getValue(\'mem_usr_id\')
                               AND ? BETWEEN mem_begin AND mem_end';
                    $queryParams = array((int) $this->getValue('mem_rol_id'), (int) $this->getValue('mem_usr_id'), DATE_NOW);
                    $memberStatement = $this->db->queryPrepared($sql, $queryParams);

                    if ($memberStatement->rowCount() === 0) {
                        throw new AdmException('SYS_MUST_HAVE_ADMINISTRATOR');
                    }
                }

                // if start date is greater than end date than delete membership
                if (strcmp($this->getValue('mem_begin', 'Y-m-d'), $endDate) >= 0) {
                    $this->delete();
                    $this->clear();
                } else {
                    $this->setValue('mem_end', $endDate);

                    // stop leader
                    if ((int) $this->getValue('mem_leader') === 1) {
                        $this->setValue('mem_leader', 0);
                    }

                    $this->save();
                }

                // if role membership of current user will be changed then renew his rights arrays
                if ($GLOBALS['gCurrentUserId'] === (int) $this->getValue('mem_usr_id')) {
                    $gCurrentUser->renewRoleData();
                }

                return true;
            }
        }

        return false;
    }
}
