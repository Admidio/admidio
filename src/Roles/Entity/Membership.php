<?php

namespace Admidio\Roles\Entity;

use DateInterval;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Changelog\Entity\LogChanges;
use Admidio\Users\Entity\User;
use DateTime;
use Throwable;

/**
 * @brief Handle memberships of roles and manage it in the database table adm_members
 *
 * The class search in the database table **adm_members** for role memberships of
 * users. It has easy methods to start or stop a membership.
 *
 * **Code example**
 * ```
 * // start membership without reading data before
 * $membership = new Membership($gDb);
 * $membership->startMembership($roleId, $userId);
 *
 * // read membership data and then stop membership
 * $membership = new Membership($gDb);
 * $membership->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
 * $membership->stopMembership();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Membership extends Entity
{
    /**
     * Constructor that will create an object from a recordset of the table adm_members.
     * If the id is set, then the specific membership will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $memId The recordset of the membership with this id will be loaded. If id isn't set, then an empty object of the table is created.
     * @throws Exception
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
     * column is set to 0, then this record will be a new record and all other columns are marked as changed.
     * This method also queues the changes to the field for admin notification
     * messages. Apart from this, the parent's **setValue** is used to set the new value.
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed $newValue The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** then the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @throws Exception
     * @see Entity#getValue
     */
    public function setValue(string $columnName, mixed $newValue, bool $checkValue = true): bool
    {
        global $gChangeNotification, $gCurrentSession, $gSettingsManager;

        // New records will be logged in save, because their ID is only generated during first save
        if (!$this->newRecord && isset($gCurrentSession)) {
            if (in_array($columnName, array('mem_begin', 'mem_end'))) {
                $oldValue = $this->getValue($columnName, $gSettingsManager->getString('system_date'));
            } else {
                $oldValue = $this->getValue($columnName);
            }
            // format the new value in system date format for logging and notification
            $newValueLogging = $newValue;
            try {
                $date = new DateTime($newValue);
                $newValueLogging = $date->format($gSettingsManager->getString('system_date'));
            } catch (Throwable) {
                // not a date, so keep original value
            }
            if ($oldValue != $newValueLogging) {
                $memId = $this->getValue('mem_id');
                $obj = new self($this->db, $memId);
                $gChangeNotification->logRoleChange(
                    $obj,
                    $columnName,
                    (string)$oldValue,
                    (string)$newValueLogging
                );
            }
        }
        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * Deletes a membership for the assigned role and user. In opposite to removeMembership
     * this method will delete the entry, and you can't see any history assignment.
     * If the user is the current user, then initiate a refresh of his role cache.
     * @param int $roleId Stops the membership of this role
     * @param int $userId The user who should lose the member of the role.
     * @return bool Return **true** if the membership was successfully deleted.
     * @throws Exception
     */
    public function deleteMembership(int $roleId = 0, int $userId = 0): bool
    {
        global $gCurrentUser;

        // if role and user are set, then search for this membership and load data into class
        if ($roleId > 0 && $userId > 0) {
            $this->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
        }

        if ($this->getValue('mem_rol_id') > 0 && $this->getValue('mem_usr_id') > 0) {
            $this->delete();

            // if role membership of current user is changed then renew his rights arrays
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
     * @throws Exception
     */
    public function delete(): bool
    {
        // Queue admin notification about membership deletion
        global $gChangeNotification, $gCurrentSession, $gSettingsManager;

        // If this is a new record that hasn't been written to the database, simply ignore it
        if (!$this->newRecord && is_object($gChangeNotification)) {
            $memId = $this->getValue('mem_id');
            $obj = new self($this->db, $memId);

            // Log begin, end and leader as changed (set to NULL)
            $gChangeNotification->logRoleChange(
                $obj,
                'mem_begin',
                $this->getValue('mem_begin', $gSettingsManager->getString('system_date')),
                ''
            );
            $gChangeNotification->logRoleChange(
                $obj,
                'mem_end',
                $this->getValue('mem_end', $gSettingsManager->getString('system_date')),
                ''
            );
            if ($this->getValue('mem_leader')) {
                $gChangeNotification->logRoleChange(
                    $obj,
                    'mem_leader',
                    $this->getValue('mem_leader'),
                    ''
                );
            }
        }

        // renew a user object of the affected user because of edited role assignment
        $gCurrentSession->reload((int)$this->getValue('mem_usr_id'));

        return parent::delete();
    }

    /**
     * Save all changed columns of the recordset in table of a database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor, then these columns
     * with their timestamp will be updated.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done, then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        global $gCurrentSession, $gChangeNotification, $gCurrentUser, $gSettingsManager;

        // if a role is administrator than only administrator can add new user,
        // but don't change their own membership, because there must be at least one administrator
        if ($this->getValue('rol_administrator') && !$gCurrentUser->isAdministrator()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $newRecord = $this->newRecord;

        $returnStatus = parent::save($updateFingerPrint);

        if ($returnStatus && isset($gCurrentSession)) {
            // renew a user object of the affected user because of edited role assignment
            $gCurrentSession->reload((int)$this->getValue('mem_usr_id'));
        }

        if ($newRecord && is_object($gChangeNotification)) {
            // Queue admin notification about membership deletion

            // storing a record for the first time does NOT update the fields from
            // the role table => need to create a new object that loads the
            // role name from the database too!
            $memId = $this->getValue('mem_id');
            $obj = new self($this->db, $memId);

            // Log begin, end and leader as changed (set to NULL)
            $gChangeNotification->logRoleChange(
                $obj,
                'mem_begin',
                '',
                $obj->getValue('mem_begin', $gSettingsManager->getString('system_date'))
            );
            $gChangeNotification->logRoleChange(
                $obj,
                'mem_end',
                '',
                $obj->getValue('mem_end', $gSettingsManager->getString('system_date'))
            );
            if ($obj->getValue('mem_leader')) {
                $gChangeNotification->logRoleChange(
                    $obj,
                    'mem_leader',
                    '',
                    $obj->getValue('mem_leader')
                );
            }
        }

        return $returnStatus;
    }

    /**
     * Starts a membership for the assigned role and user from now until 31.12.9999.
     * An existing membership will be extended if necessary. If the user is the
     * current user, then initiate a refresh of his role cache.
     * @param int $roleId Assign the membership to this role
     * @param int $userId The user who should get a member of the role.
     * @param bool|null $leader If value **1** then the user will be a leader of the role and get more rights.
     * @param int|null $approvalState Option for User to confirm and adjust the membership (**1** = User confirmed membership but maybe disagreed, **2** = user accepted membership
     * @return bool Return **true** if the assignment was successful.
     * @throws Exception
     */
    public function startMembership(int $roleId = 0, int $userId = 0, bool|null $leader = null, int|null $approvalState = null): bool
    {
        global $gCurrentUser, $gCurrentUserId;

        // if role and user are set, then search for this membership and load data into class
        if ($roleId > 0 && $userId > 0) {
            $this->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
        }

        if ($this->getValue('mem_rol_id') > 0 && $this->getValue('mem_usr_id') > 0) {
            // Do not overwrite start date if already exists
            if ($this->newRecord || strcmp($this->getValue('mem_begin', 'Y-m-d'), DATE_NOW) > 0) {
                $this->setValue('mem_begin', DATE_NOW);
            }

            // Leaders should not be overwritten if parameter not set
            if ($leader === null) {
                if ($this->newRecord) {
                    $this->setValue('mem_leader', false);
                }
            } else {
                $this->setValue('mem_leader', $leader);
            }

            $this->setValue('mem_end', DATE_MAX);

            // User has confirmed or adjusted role membership
            if ($approvalState > 0) {
                $this->setValue('mem_approved', $approvalState);
            }

            if ($this->columnsValueChanged) {
                $this->save();

                // if role membership of current user is changed then renew his rights arrays
                if ($gCurrentUserId === $userId) {
                    $gCurrentUser->renewRoleData();
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Stops a membership for the assigned role and user from now until 31.12.9999.
     * If the user is the current user, then initiate a refresh of his role cache. If
     * the last membership of an administrator role should be stopped, then throw an exception.
     * @param int $roleId Stops the membership of this role
     * @param int $userId The user who should lose the member of the role.
     * @return bool Return **true** if the membership removal was successful.
     * @throws Exception|\DateInvalidOperationException
     * @deprecated 4.3.0:4.4.0 "stopMembership()" is deprecated, use "TableRoles::stopMembership()" instead.
     */
    public function stopMembership(int $roleId = 0, int $userId = 0): bool
    {
        global $gCurrentUser;

        // if role and user are set, then search for this membership and load data into class
        if ($roleId > 0 && $userId > 0) {
            $this->readDataByColumns(array('mem_rol_id' => $roleId, 'mem_usr_id' => $userId));
        }

        if (!$this->newRecord && $this->getValue('mem_rol_id') > 0 && $this->getValue('mem_usr_id') > 0) {
            // subtract one day, so that user leaves a role immediately
            $now = new DateTime();
            $oneDayOffset = new DateInterval('P1D');
            $nowDate = $now->format('Y-m-d');
            $endDate = $now->sub($oneDayOffset)->format('Y-m-d');

            // only stop membership if there is an actual membership,
            // the actual date must be after the beginning,
            // and the actual date must be before the end date
            if (strcmp($nowDate, $this->getValue('mem_begin', 'Y-m-d')) >= 0
                && strcmp($endDate, $this->getValue('mem_end', 'Y-m-d')) < 0) {
                // if role administrator then check if this membership is the last one -> don't delete it
                if ((int)$this->getValue('rol_administrator') === 1) {
                    $sql = 'SELECT mem_id
                              FROM ' . TBL_MEMBERS . '
                             WHERE mem_rol_id  = ? -- $this->getValue(\'mem_rol_id\')
                               AND mem_usr_id <> ? -- $this->getValue(\'mem_usr_id\')
                               AND ? BETWEEN mem_begin AND mem_end';
                    $queryParams = array((int)$this->getValue('mem_rol_id'), (int)$this->getValue('mem_usr_id'), DATE_NOW);
                    $memberStatement = $this->db->queryPrepared($sql, $queryParams);

                    if ($memberStatement->rowCount() === 0) {
                        throw new Exception('SYS_MUST_HAVE_ADMINISTRATOR');
                    }
                }

                // if the start date is greater than end date than delete membership
                if (strcmp($this->getValue('mem_begin', 'Y-m-d'), $endDate) >= 0) {
                    $this->delete();
                    $this->clear();
                } else {
                    $this->setValue('mem_end', $endDate);

                    // stop leader
                    if ((int)$this->getValue('mem_leader') === 1) {
                        $this->setValue('mem_leader', 0);
                    }

                    $this->save();
                }

                // if role membership of current user is changed then renew his rights arrays
                if ($GLOBALS['gCurrentUserId'] === (int)$this->getValue('mem_usr_id')) {
                    $gCurrentUser->renewRoleData();
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Calculates the duration of a membership in years, months and days
     * @param string|null $startDate Start date of the membership in format YYYY-MM-DD (if null, uses mem_begin)
     * @param string|null $endDate End date of the membership in format YYYY-MM-DD (if null, uses mem_end)
     * @return array Array with years, months, days and a formatted string
     * @throws Exception
     * @throws \Exception
     */
    public function calculateDuration(?string $startDate = null, ?string $endDate = null): array
    {
        global $gL10n;

        $startDate = $startDate ?? $this->getValue('mem_begin', 'Y-m-d');
        $endDate = $endDate ?? $this->getValue('mem_end', 'Y-m-d');

        $startDateTime = new DateTime($startDate);

        // If membership is ongoing, use current date as end date
        if ($endDate === DATE_MAX) {
            $endDateTime = new DateTime();
        } else {
            $endDateTime = new DateTime($endDate);
        }

        // If the end date is in the future, use the current date for duration calculation
        $now = new DateTime();
        if ($endDateTime > $now && $endDate !== DATE_MAX) {
            $endDateTime = $now;
        }

        // Add one day to include the end date in the duration
        $endDateTime->modify('+1 day');

        // Calculate difference
        $interval = $startDateTime->diff($endDateTime);

        $years = $interval->y;
        $months = $interval->m;
        $days = $interval->d;

        // Format a human-readable string
        $durationText = '';

        if ($years > 0) {
            $durationText .= $years . ' ' . ($years === 1 ? $gL10n->get('SYS_YEAR') : $gL10n->get('SYS_YEARS'));
        }

        if ($months > 0) {
            if ($durationText !== '') {
                $durationText .= ', ';
            }
            $durationText .= $months . ' ' . ($months === 1 ? $gL10n->get('SYS_MONTH') : $gL10n->get('SYS_MONTHS'));
        }

        if ($days > 0 || ($years === 0 && $months === 0)) {
            if ($durationText !== '') {
                $durationText .= ', ';
            }
            $durationText .= $days . ' ' . ($days === 1 ? $gL10n->get('SYS_DAY') : $gL10n->get('SYS_DAYS'));
        }

        return [
            'years' => $years,
            'months' => $months,
            'days' => $days,
            'formatted' => $durationText
        ];
    }

    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * Some tables contain columns _usr_id_create, timestamp_create, etc. We do not want
     * to log changes to these columns.
     * The Membership table also contains mem_rol_id and mem_usr_id, which cannot be changed,
     * so their initial setting on creation should not be logged. Instead, they will be used
     * when displaying the log entry.
     *
     * @return array Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        $ignored = parent::getIgnoredLogColumns();
        $ignored[] = 'mem_rol_id';
        $ignored[] = 'mem_usr_id';
        $ignored[] = 'mem_uuid';
        return $ignored;
    }

    /**
     * Adjust the changelog entry for this db record.
     *
     * For group memberships, we want to display the user's name as record name
     * and link to the user_id (the membership does not have any modification page).
     * Also, we want to show and link the group as related id&name.
     *
     * @param LogChanges $logEntry The log entry to adjust
     * @return void
     * @throws Exception
     */
    protected function adjustLogEntry(LogChanges $logEntry): void
    {
        global $gProfileFields;
        $usrId = (int)$this->getValue('mem_usr_id');

        $user = new User($this->db, $gProfileFields, $usrId);
        $logEntry->setValue('log_record_name', $user->readableName());
        $logEntry->setValue('log_record_uuid', $user->getValue('usr_uuid'));
        $logEntry->setLogLinkID($usrId);

        $rolId = $this->getValue('mem_rol_id');
        $role = new Role($this->db, $rolId);

        $logEntry->setLogRelated($role->getValue('rol_uuid'), $role->getValue('rol_name'));
    }
}
