<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
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
 * system notifications for profile field changes are enabled in Admidio's
 * configuration.
 *
 * On startup, a global (singleton) object $gChangeNotifications is created
 * that is automatically used the User and TableMembers classes to log
 * changes.
 *
 *
 * **Code example**
 * ```
 * // log a change to a profile field (done automatically by the User classe)
 * $gChangeNotifications->logProfileChange($usr_id, 123, 'first_name', 'Old Name', 'New Name');
 *
 * // Force sending the change notifications (if configured at all)
 * $gChangeNotifications->sendNotification();
 * ```
 */
class ChangeNotification
{
    /** @var $changes Queued array of changes (user ID as key) made during this
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
     *              role_id => array('Role Name', 'fieldname', 'old_value', 'new_value'),
     *          )
     *      )
     */
    protected $changes = array();

    /** @var $format Whether to send mails as 'html' or 'text' (as configured)
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
     * @param int $userId The user for whom all recorded changes should be cleared (null for all users)
     */
    public function clearChanges($userId = null)
    {
        if ($userId) {
            unset($this->changes[$userId]);
        } else {
            $this->changes = array();
        }
    }

    /**
     * Initialize the internal data structure to queue changes to a given user ID.
     * @param int $userId The user for whom to prepare the internal data structure
     */
    public function prepareUserChanges($userId, $user = null)
    {
        global $gDb, $gProfileFields;
        if (!isset($this->changes[$userId])) {
            if (is_null($user)) {
                $user = new User($gDb, $gProfileFields, $userId);
            }
            $this->changes[$userId] = array(
                'uid' => $userId,
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
     * @param int $userId The user to whom the change applies
     * @param int $fieldId The ID of the modified profile field.
     * @param string $fieldname The human-readable name of the modified profile field.
     * @param string $old_value The previous value of the field before the change
     * @param string $new_value The new value of the field after the change
     * @param string $old_value_db The previous value of the field before the change as stored in the database
     * @param string $new_value_db The new value of the field after the change as stored in the database
     * @param bool $deleting Whether the profile is changed due to deleting the
     *                       user. In this case, the change will not be logged
     *                       in the history database.
     */
    public function logProfileChange($userId, $fieldId, $fieldname, $old_value, $new_value, $old_value_db = null, $new_value_db = null, $user = null, $deleting = false)
    {
        global $gSettingsManager, $gDb;
        // 1. Create a database log entry if so configured
        if (!$deleting && $userId > 0 && $gSettingsManager->getBool('profile_log_edit_fields')) {
            $logEntry = new TableAccess($gDb, TBL_USER_LOG, 'usl');
            $logEntry->setValue('usl_usr_id', $userId);
            $logEntry->setValue('usl_usf_id', $fieldId);
            $logEntry->setValue('usl_value_old', $old_value_db);
            $logEntry->setValue('usl_value_new', $new_value_db);
            $logEntry->setValue('usl_comment', '');
            $logEntry->save();
        }

        // 2. Store the change to send out one change notification mail (after all modifications are done)
        $this->prepareUserChanges($userId, $user);
        $this->changes[$userId]['profile_changes'][] = array($fieldname, $old_value, $new_value);
    }

    /**
     * Records a core user field change for the given user ID and the field ID.
     * Both the old and the new values are stored in an array and sent via
     * a system notification mail to the admin if configured.
     * Some user fields are special cased (password, photo), others are ignored
     * for irrelevance (internal fields).
     * @param int $userId The user to whom the change applies
     * @param int $fieldId The ID of the modified profile field.
     * @param string $fieldname The human-readable name of the modified profile field.
     * @param string $old_value The previous value of the field before the change
     * @param string $new_value The new value of the field after the change
     * @param bool $deleting Whether the profile is changed due to deleting the
     *                       user. In this case, the change will not be logged
     *                       in the history database.
     */
    public function logUserChange($userId, $fieldId, $old_value, $new_value, $user = null, $deleting = false)
    {
        global $gSettingsManager, $gL10n;

        // 1. Create a database log entry if so configured
        if ($gSettingsManager->getBool('profile_log_edit_fields')) {
            // TODO: User table fields are not yet logged in the database!
        }

        // 2. Store the change to send out one change notification mail (after all modifications are done)
        $this->prepareUserChanges($userId, $user);

        $fieldtitle = $fieldId;

        // Ignore all fields (internal logging about who and when a user was
        // last changed) except explicitly handled (login, pwd, photo)
        $ignore = false;

        switch ($fieldId) {
            case 'usr_login_name':
                $fieldtitle = $gL10n->get('SYS_USERNAME');
                break;
            case 'usr_password':
                $fieldtitle = $gL10n->get('SYS_PASSWORD');
                $old_value = $new_value = '********';
                break;
            case 'usr_photo':
                $fieldtitle = $gL10n->get('SYS_PHOTO');
                // Don't show photo data, replace with [...] if set
                $old_value = $old_value ? '[...]' : $old_value;
                $new_value = $new_value ? '[...]' : $new_value;
                break;
            default:
                $ignore = true;
        }

        if (!$ignore) {
            $this->changes[$userId]['profile_changes'][] = array($fieldtitle, $old_value, $new_value);
        }
    }

    /**
     * Records a role membership change for the given user ID and the given role.
     * Both the old and the new values are stored in an array and sent via
     * a system notification mail to the admin if configured.
     * @param int $userId The user to whom the change applies
     * @param int $fieldId The ID of the modified profile field.
     * @param string $fieldname The human-readable name of the modified profile field.
     * @param string $old_value The previous value of the field before the change
     * @param string $new_value The new value of the field after the change
     * @param bool $deleting Whether the profile is changed due to deleting the
     *                       user. In this case, the change will not be logged
     *                       in the history database.
     */
    public function logRoleChange($userId, $roleId, $role, $fieldname, $old_value, $new_value, $user = null, $deleting = false)
    {
        global $gSettingsManager, $gL10n;
        // Don't log anything if no User ID is set yet (e.g. user not yet saved to the database!)
        if ($userId == 0) {
            return;
        }
        // 1. Create a database log entry if so configured
        if (!$deleting && $userId > 0 && $gSettingsManager->getBool('profile_log_edit_fields')) {
            // TODO: Role changes are not yet logged in the database!
        }

        // 2. Store the change to send out one change notification mail (after all modifications are done)
        $this->prepareUserChanges($userId, $user);
        $fieldtitle = $fieldname;
        $ignore = false;
        switch ($fieldname) {
            case 'mem_begin':
                $fieldtitle = $gL10n->get('SYS_MEMBERSHIP_START');
                break;
            case 'mem_end':
                $fieldtitle = $gL10n->get('SYS_MEMBERSHIP_END');
                break;
            case 'mem_leader':
                $fieldtitle = $gL10n->get('SYS_LEADER');
                break;
            default:
                $ignore = true;
                break;
        }
        if (!$ignore) {
            $this->changes[$userId]['role_changes'][] = array($role, $fieldtitle, $old_value, $new_value);
        }
    }

    /**
     * Records a user creation with the given user ID. The user is assumed to be
     * stored to the database already.
     * All non-empty fields are added to the list of changes and queued for notification.
     *
     * @param int $userId The user to whom the change applies
     * @param User $user(optional) The User object of the newly created user
     */
    public function logUserCreation($userId, $user = null)
    {
        global $gProfileFields, $gL10n;

        // If user was never created in the DB, no need to log
        if ($userId == 0) {
            return;
        }
        if (is_null($user)) {
            $user = new User($this->db, $gProfileFields, $userId);
        }

        // 1. TODO: Create a history log database entry for the creation


        // 2. Prepare the admin notifications
        $this->prepareUserChanges($userId, $user);
        $this->changes[$userId]['created'] = true;

        // Username and Passwords
        foreach (array('usr_login_name', 'usr_password', 'usr_photo', 'usr_text') as $fieldname) {
            $newval = $user->getValue($fieldname, $this->format);
            if ($newval) {
                $this->logUserChange($userId, $fieldname, null, $newval, null, $user);
            }
        }
        // Loop through all profile fields and add all non-empty fields to the notification
        if ($user->getProfileFieldsData() instanceof ProfileFields) {
            foreach ($user->getProfileFieldsData()->getProfileFields() as $fieldname => $field) {
                // Always use the text representation in the notification mail,
                // as the HTML-representation might make use of css classes or
                // image paths that are not available in a mail!
                $newval = $user->getValue($fieldname, 'text');
                $newval_db = $user->getValue($fieldname, 'database');
                if ($newval) {
                    $fieldtitle = $field->getValue('usf_name', $this->format);
                    $fieldid = $field->getValue('usf_id');
                    $this->logProfileChange($userId, $fieldid, $fieldtitle, null, $newval, null, $newval_db, $user, /*deleting=*/false);
                }
            }
        }
    }

    /**
     * Records a user deletion for the given user ID.
     * All non-empty fields are added to the list of changes and queued for notification.
     * This function must be called before removing the user from the database.
     *
     * @param int $userId The user to whom the change applies
     * @param User $user(optional) The User object of the user to be deleted
     */
    public function logUserDeletion($userId, $user = null)
    {
        global $gProfileFields, $gL10n, $gDb;

        // If user wasn't yet created in the DB, no need to log anything
        if ($userId == 0) {
            return;
        }

        // 1. TODO: Create a history log database entry for the deletion

        // 2. Prepare the admin notifications
        $this->prepareUserChanges($userId, $user);
        $this->changes[$userId]['deleted'] = true;

        $olddata = new User($gDb, $gProfileFields, $userId);

        // Username and Passwords
        foreach (array('usr_login_name', 'usr_password', 'usr_photo', 'usr_text') as $fieldname) {
            $oldval = $olddata->getValue($fieldname, $this->format);
            if ($oldval) {
                $this->logUserChange($userId, $fieldname, $oldval, null, $user);
            }
        }
        // Loop through all profile fields and add all non-empty fields to the notification
        if ($olddata->getProfileFieldsData() instanceof ProfileFields) {
            foreach (array_keys($olddata->getProfileFieldsData()->getProfileFields()) as $fieldname) {
                // Always use the text representation in the notification mail,
                // as the HTML-representation might make use of css classes or
                // image paths that are not available in a mail!
                $oldval = $olddata->getValue($fieldname, 'text');
                $oldval_db = $olddata->getValue($fieldname, 'database');
                if ($oldval) {
                    $fieldtitle = $olddata->getProfileFieldsData()->getProfileFields()[$fieldname]->getValue('usf_name', $this->format);
                    $this->logProfileChange($userId, $fieldname, $fieldtitle, $oldval, null, $oldval_db, null, $user, /*deleting=*/true);
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
                 WHERE mem_usr_id  = ? -- $userId
                   AND rol_valid   = true
                   AND cat_name_intern <> \'EVENTS\'
                 ORDER BY cat_org_id, cat_sequence, rol_name';
        $query = $gDb->queryPrepared($sql, array($userId));

        while ($row = $query->fetch()) {
            $this->logRoleChange($userId, $row['rol_name'], $gL10n->get('SYS_MEMBERSHIP_START'), $row['mem_begin'], null, $user, /*deleting=*/true);
            if ($row['mem_end']) {
                $this->logRoleChange($userId, $row['rol_name'], $gL10n->get('SYS_MEMBERSHIP_END'), $row['mem_end'], null, $user, /*deleting=*/true);
            }
            if ($row['mem_leader']) {
                $this->logRoleChange($userId, $row['rol_name'], $gL10n->get('SYS_LEADER'), $row['mem_leader'], null, $user, /*deleting=*/true);
            }
        }
    }

    /**
     * Send out all queued change notifications, if the configuration has system
     * change notifications enabled at all.
     * @param int $userId The user for whom the notification shall be sent (null for all queued notifications)
     */
    public function sendNotifications($userId = null)
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
                $table_begin = '<br><br><table border="1">';
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
            if ($userId) {
                $changes = array();
                $changes[$userId] = $this->changes[$userId];
            }
            foreach ($changes as $uid => $userdata) {
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

        $this->clearChanges($userId);
    }


    /**
     * Shutdown function for cleanup: Send out all pending notification when the php processing is finished.
     */
    public function shutdown()
    {
        $this->sendNotifications();
    }
}
