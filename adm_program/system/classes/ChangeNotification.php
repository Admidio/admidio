<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Object to collect change notifications and optionally send a message to the administrator
 *
 * This class can be used to log changes to profile fields and role
 * memberships. It stores all changes and at the end of the request,
 * sends one notification mail per modified user to the administrator, if
 * system notifications for profile field changes are enabled in the configuration of Admidio
 *
 * On startup, a global (singleton) object $gChangeNotifications is created
 * that is automatically used by the User and TableMembers classes to log
 * changes.
 *
 *
 * **Code example**
 * ```
 * // log a change to a profile field (done automatically by the User class)
 * $gChangeNotifications->logProfileChange($usr_id, 123, 'first_name', 'Old Name', 'New Name');
 *
 * // Force sending the change notifications (if configured at all)
 * $gChangeNotifications->sendNotification();
 * ```
 */
class ChangeNotification
{
    /** @var array $changes Queued array of changes (user ID as key) made during this
     *  php process. This data structure is meant to cache all changes and then
     *  send out only one notification mail per user when PHP is finished.
     *  The structure of each entry of this entry is:
     *      uid => array(
     *          'uid'=>123,
     *          'usr_login_name'=>'',
     *          'first_name'=>'',
     *          'last_name'=>'',
     *          'created' => false,
     *          'deleted' => false,
     *          'profile_changes' => array(
     *              field_id => array('Field Name', 'old_value', 'new_value'),
     *          ),
     *          'role_changes' => array(
     *              role_id => array('Role Name', 'fieldName', 'old_value', 'new_value'),
     *          )
     *      )
     */
    protected $changes = array();

    /** @var string $format Whether to send mails as 'html' or 'text' (as configured)
     */
    protected $format = 'html';

    /**
     * Constructor that initialize the class member parameters
     */
    public function __construct()
    {
        global $gSettingsManager;

        $gSettingsManager->disableExceptions();
        $this->format = $gSettingsManager->getBool('mail_html_registered_users') ? 'html' : 'text';

        // Register a shutdown function, which will be called when the whole PHP
        // script is finished, but before all global objects are destroyed
        // => That's the correct place to send out all pending change notification mails!
        register_shutdown_function(array($this, 'shutdown'));
    }

    /**
     * Clear the queue of all recorded changes. No notifications are sent out by
     * this method.
     * @param int $userID The user for whom all recorded changes should be cleared (null for all users)
     */
    public function clearChanges(int $userID = 0)
    {
        if ($userID > 0) {
            unset($this->changes[$userID]);
        } else {
            $this->changes = array();
        }
    }

    /**
     * Initialize the internal data structure to queue changes to a given user ID.
     * @param int $userID The user for whom to prepare the internal data structure.
     * @param User|null $user Optional the user object of the changed user could be set.
     */
    public function prepareUserChanges(int $userID, User $user = null)
    {
        global $gDb, $gProfileFields;
        if (!isset($this->changes[$userID])) {
            if (is_null($user)) {
                $user = new User($gDb, $gProfileFields, $userID);
            }
            $this->changes[$userID] = array(
                'uid' => $userID,
                'usr_login_name' => $user->getValue('usr_login_name'),
                'first_name' => $user->getValue('FIRST_NAME'),
                'last_name' => $user->getValue('LAST_NAME'),
                'created' => false,
                'deleted' => false,
                'profile_changes' => array(),
                'role_changes' => array(),
            );
        }
    }

    /**
     * Records a profile field change for the given user ID and the field ID.
     * Both the old and the new values are stored in an array and sent via
     * a system notification mail to the admin if configured.
     * @param int $userID The user to whom the change applies
     * @param int $fieldId The ID of the modified profile field.
     * @param string $fieldName The human-readable name of the modified profile field.
     * @param string $old_value The previous value of the field before the change
     * @param string $new_value The new value of the field after the change
     * @param string $old_value_db The previous value of the field before the change as stored in the database
     * @param string $new_value_db The new value of the field after the change as stored in the database
     * @param bool $deleting Whether the profile is changed due to deleting the
     *                       user. In this case, the change will not be logged
     *                       in the history database.
     * @throws AdmException
     */
    public function logProfileChange(int $userID, int $fieldId, string $fieldName, string $old_value, string $new_value, string $old_value_db = '', string $new_value_db = '', $user = null, bool $deleting = false)
    {
        global $gSettingsManager, $gDb;
        // 1. Create a database log entry if so configured
        if (!$deleting && $userID > 0 && $gSettingsManager->getBool('profile_log_edit_fields')) {
            $logEntry = new TableAccess($gDb, TBL_USER_LOG, 'usl');
            $logEntry->setValue('usl_usr_id', $userID);
            $logEntry->setValue('usl_usf_id', $fieldId);
            $logEntry->setValue('usl_value_old', $old_value_db);
            $logEntry->setValue('usl_value_new', $new_value_db);
            $logEntry->setValue('usl_comment', '');
            $logEntry->save();
        }

        // 2. Store the change to send out one change notification mail (after all modifications are done)
        $this->prepareUserChanges($userID, $user);
        $this->changes[$userID]['profile_changes'][] = array($fieldName, $old_value, $new_value);
    }

    /**
     * Records a core user field change for the given user ID and the field ID.
     * Both the old and the new values are stored in an array and sent via
     * a system notification mail to the admin if configured.
     * Some user fields are special cased (password, photo), others are ignored
     * for irrelevance (internal fields).
     * @param int $userID The user to whom the change applies
     * @param string $fieldName The ID of the modified profile field.
     * @param string $old_value The previous value of the field before the change
     * @param string $new_value The new value of the field after the change
     * @param User|null $user Optional the object of the changed user.
     */
    public function logUserChange(int $userID, string $fieldName, string $old_value, string $new_value, User $user = null)
    {
        global $gSettingsManager, $gL10n;

        // 1. Create a database log entry if so configured
        if ($gSettingsManager->getBool('profile_log_edit_fields')) {
            // TODO: User table fields are not yet logged in the database!
        }

        // 2. Store the change to send out one change notification mail (after all modifications are done)
        $this->prepareUserChanges($userID, $user);

        $fieldLabel = $fieldName;

        // Ignore all fields (internal logging about who and when a user was
        // last changed) except explicitly handled (login, pwd, photo)
        $ignore = false;

        switch ($fieldName) {
            case 'usr_login_name':
                $fieldLabel = $gL10n->get('SYS_USERNAME');
                break;
            case 'usr_password':
                $fieldLabel = $gL10n->get('SYS_PASSWORD');
                $old_value = $new_value = '********';
                break;
            case 'usr_photo':
                $fieldLabel = $gL10n->get('SYS_PHOTO');
                // Don't show photo data, replace with [...] if set
                $old_value = $old_value ? '[...]' : $old_value;
                $new_value = $new_value ? '[...]' : $new_value;
                break;
            default:
                $ignore = true;
        }

        if (!$ignore) {
            $this->changes[$userID]['profile_changes'][] = array($fieldLabel, $old_value, $new_value);
        }
    }

    /**
     * Records a role membership change for the given user ID and the given role.
     * Both the old and the new values are stored in an array and sent via
     * a system notification mail to the admin if configured.
     * @param int $userID The user to whom the change applies
     * @param string $roleName The name of the modified role.
     * @param string $fieldName The human-readable name of the modified profile field.
     * @param string $old_value The previous value of the field before the change
     * @param string $new_value The new value of the field after the change
     * @param User|null $user Optional the object of the user whose role membership has modified.
     * @param bool $deleting Whether the profile is changed due to deleting the
     *                       user. In this case, the change will not be logged
     *                       in the history database.
     */
    public function logRoleChange(int $userID, string $roleName, string $fieldName, string $old_value, string $new_value, User $user = null, bool $deleting = false)
    {
        global $gSettingsManager, $gL10n;
        // Don't log anything if no User ID is set yet (e.g. user not yet saved to the database!)
        if ($userID == 0) {
            return;
        }
        // 1. Create a database log entry if so configured
        if (!$deleting && $userID > 0 && $gSettingsManager->getBool('profile_log_edit_fields')) {
            // TODO: Role changes are not yet logged in the database!
        }

        // 2. Store the change to send out one change notification mail (after all modifications are done)
        $this->prepareUserChanges($userID, $user);
        $fieldLabel = $fieldName;
        $ignore = false;
        switch ($fieldName) {
            case 'mem_begin':
                $fieldLabel = $gL10n->get('SYS_MEMBERSHIP_START');
                break;
            case 'mem_end':
                $fieldLabel = $gL10n->get('SYS_MEMBERSHIP_END');
                break;
            case 'mem_leader':
                $fieldLabel = $gL10n->get('SYS_LEADER');
                break;
            default:
                $ignore = true;
                break;
        }
        if (!$ignore) {
            $this->changes[$userID]['role_changes'][] = array($roleName, $fieldLabel, $old_value, $new_value);
        }
    }

    /**
     * Records a user creation with the given user ID. The user is assumed to be
     * stored to the database already.
     * All non-empty fields are added to the list of changes and queued for notification.
     *
     * @param int $userID The user to whom the change applies
     * @param User|null $user (optional) The User object of the newly created user
     * @throws AdmException
     */
    public function logUserCreation(int $userID, User $user = null)
    {
        global $gProfileFields, $gDb;

        // If user was never created in the DB, no need to log
        if ($userID == 0) {
            return;
        }
        if (is_null($user)) {
            $user = new User($gDb, $gProfileFields, $userID);
        }

        // 1. TODO: Create a history log database entry for the creation


        // 2. Prepare the admin notifications
        $this->prepareUserChanges($userID, $user);
        $this->changes[$userID]['created'] = true;

        // Username and Passwords
        foreach (array('usr_login_name', 'usr_password', 'usr_photo', 'usr_text') as $fieldName) {
            $newValue = $user->getValue($fieldName, $this->format);
            if ($newValue) {
                $this->logUserChange($userID, $fieldName, '', $newValue, $user);
            }
        }
        // Loop through all profile fields and add all non-empty fields to the notification
        if ($user->getProfileFieldsData() instanceof ProfileFields) {
            foreach ($user->getProfileFieldsData()->getProfileFields() as $fieldName => $field) {
                // Always use the text representation in the notification mail,
                // as the HTML-representation might make use of css classes or
                // image paths that are not available in a mail!
                $newValue = $user->getValue($fieldName, 'text');
                $newValue_db = $user->getValue($fieldName, 'database');
                if ($newValue) {
                    $fieldLabel = $field->getValue('usf_name', $this->format);
                    $fieldID = $field->getValue('usf_id');
                    $this->logProfileChange($userID, $fieldID, $fieldLabel, '', $newValue, '', $newValue_db, $user);
                }
            }
        }
    }

    /**
     * Records a user deletion for the given user ID.
     * All non-empty fields are added to the list of changes and queued for notification.
     * This function must be called before removing the user from the database.
     *
     * @param int $userID The user to whom the change applies
     * @param User|null $user (optional) The User object of the user to be deleted
     * @throws AdmException
     */
    public function logUserDeletion(int $userID, User $user = null)
    {
        global $gProfileFields, $gL10n, $gDb, $gSettingsManager;

        // If user wasn't yet created in the DB, no need to log anything
        if ($userID == 0) {
            return;
        }

        // 1. TODO: Create a history log database entry for the deletion

        // 2. Prepare the admin notifications
        $this->prepareUserChanges($userID, $user);
        $this->changes[$userID]['deleted'] = true;

        $oldUser = new User($gDb, $gProfileFields, $userID);

        // Username and Passwords
        foreach (array('usr_login_name', 'usr_password', 'usr_photo', 'usr_text') as $fieldName) {
            $oldValue = $oldUser->getValue($fieldName, $this->format);
            if ($oldValue) {
                $this->logUserChange($userID, $fieldName, $oldValue, '', $user);
            }
        }
        // Loop through all profile fields and add all non-empty fields to the notification
        if ($oldUser->getProfileFieldsData() instanceof ProfileFields) {
            foreach (array_keys($oldUser->getProfileFieldsData()->getProfileFields()) as $fieldName) {
                // Always use the text representation in the notification mail,
                // as the HTML-representation might make use of css classes or
                // image paths that are not available in a mail!
                $oldValue = $oldUser->getValue($fieldName, 'text');
                $oldValueDB = $oldUser->getValue($fieldName, 'database');
                if ($oldValue) {
                    $oldFieldId = $oldUser->getProfileFieldsData()->getProfileFields()[$fieldName]->getValue('usf_id');
                    $oldFieldName = $oldUser->getProfileFieldsData()->getProfileFields()[$fieldName]->getValue('usf_name', $this->format);
                    $this->logProfileChange($userID, $oldFieldId, $oldFieldName, $oldValue, '', $oldValueDB, '', $user, /*deleting=*/true);
                }
            }
        }

        // Role memberships => For simplicity read directly from database
        global $gDb;
        $sql = 'SELECT *
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE mem_usr_id  = ? -- $userID
                   AND rol_valid   = true
                   AND cat_name_intern <> \'EVENTS\'
                 ORDER BY cat_org_id, cat_sequence, rol_name';
        $query = $gDb->queryPrepared($sql, array($userID));

        while ($row = $query->fetch()) {
            $memBegin = $row['mem_begin'];
            $memEnd = $row['mem_end'];

            $date = new DateTime($memBegin);
            if ($date !== false) {
                $memBegin = $date->format($gSettingsManager->getString('system_date'));
            }
            $this->logRoleChange($userID, $row['rol_name'], $gL10n->get('SYS_MEMBERSHIP_START'), $memBegin, '', $user, /*deleting=*/true);
            if ($memEnd) {
                $date = new DateTime($memEnd);
                if ($date !== false) {
                    $memEnd = $date->format($gSettingsManager->getString('system_date'));
                }
                $this->logRoleChange($userID, $row['rol_name'], $gL10n->get('SYS_MEMBERSHIP_END'), $memEnd, '', $user, /*deleting=*/true);
            }
            if ($row['mem_leader']) {
                $this->logRoleChange($userID, $row['rol_name'], $gL10n->get('SYS_LEADER'), $row['mem_leader'], '', $user, /*deleting=*/true);
            }
        }
    }

    /**
     * Send out all queued change notifications, if the configuration has system
     * change notifications enabled at all.
     * @param int $userID The user for whom the notification shall be sent (null for all queued notifications)
     * @throws AdmException
     */
    public function sendNotifications(int $userID = 0)
    {
        global $gSettingsManager, $gL10n, $gCurrentUser;

        if ($gSettingsManager->has('system_notifications_profile_changes')
            && $gSettingsManager->getBool('system_notifications_profile_changes')
            && is_object($gCurrentUser)) {
            $currentName = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME') . ' (login: ' . $gCurrentUser->getValue('usr_login_name') . ')';
            if ($this->format == 'html') {
                $format_hdr = "<tr><th> %s </th><th> %s </th><th> %s </th></tr>\n";
                $format_row = "<tr><th> %s </th><td> %s </td><td> %s </td></tr>\n";
                $format_rolhdr = "<tr><th> %s </th><th> %s </th><th> %s </th><th> %s </th></tr>\n";
                $format_rolrow = "<tr><th> %s </th><td> %s </td><td> %s </td><td> %s </td></tr>\n";
                $table_begin = '<br><br><table style="border-width: 1px;">';
                $table_end = '</table><br>';
            } else {
                $format_hdr = "%25s %25s -> %25s\n";
                $format_row = "%25.25s %25.25s -> %25s\n";
                $format_rolhdr = "%25s %25s %25s -> %25s\n";
                $format_rolrow = "%25.25s %25s %25.25s -> %25s\n";
                $table_begin = "\n";
                $table_end = "\n\n";
            }

            $changes = $this->changes;
            if ($userID > 0) {
                $changes = array();
                $changes[$userID] = $this->changes[$userID];
            }
            foreach ($changes as $userdata) {
                $notification = new Email();
                $hasContent = false;

                if ($userdata['deleted']) {
                    $message = 'SYS_EMAIL_DELETE_NOTIFICATION_MESSAGE';
                    $messageTitle = 'SYS_EMAIL_DELETE_NOTIFICATION_TITLE';
                } elseif ($userdata['created']) {
                    $message = 'SYS_EMAIL_CREATE_NOTIFICATION_MESSAGE';
                    $messageTitle = 'SYS_EMAIL_CREATE_NOTIFICATION_TITLE';
                } else {
                    $message = 'SYS_EMAIL_CHANGE_NOTIFICATION_MESSAGE';
                    $messageTitle = 'SYS_EMAIL_CHANGE_NOTIFICATION_TITLE';
                }

                $message = $gL10n->get(
                    $message,
                    array($userdata['first_name'], $userdata['last_name'], $userdata['usr_login_name'], $currentName)
                );

                $changes = $userdata['profile_changes'];
                if ($changes) {
                    $hasContent = true;
                    $message .= $table_begin .
                        sprintf(
                            $format_hdr,
                            $gL10n->get('SYS_FIELD'),
                            $gL10n->get('SYS_PREVIOUS_VALUE'),
                            $gL10n->get('SYS_NEW_VALUE')
                        );
                    foreach ($changes as $c) {
                        $message .= sprintf($format_row, $c[0], $c[1], $c[2]);
                    }
                    $message .= $table_end;
                }

                $changes = $userdata['role_changes'];
                if ($changes) {
                    $hasContent = true;
                    $message .= $table_begin .
                        sprintf(
                            $format_rolhdr,
                            $gL10n->get('SYS_ROLE'),
                            $gL10n->get('SYS_FIELD'),
                            $gL10n->get('SYS_PREVIOUS_VALUE'),
                            $gL10n->get('SYS_NEW_VALUE')
                        );
                    foreach ($changes as $c) {
                        $message .= sprintf($format_rolrow, $c[0], $c[1], $c[2], $c[3]);
                    }
                    $message .= $table_end;
                }

                if ($hasContent) {
                    $notification->sendNotification(
                        $gL10n->get(
                            $messageTitle,
                            array($userdata['first_name'], $userdata['last_name'], $userdata['usr_login_name'])
                        ),
                        $message
                    );
                }
            }
        }

        $this->clearChanges($userID);
    }


    /**
     * Shutdown function for cleanup: Send out all pending notification when the php processing is finished.
     */
    public function shutdown()
    {
        try {
            $this->sendNotifications();
        } catch (AdmException $e) {
            $e->showText();
        }
    }
}
