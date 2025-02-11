<?php
namespace Admidio\Infrastructure;

use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Exception;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Roles\Entity\Membership;
use Admidio\Users\Entity\User;

/**
 * @brief Object to collect change notifications and optionally send a message to the administrator
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
 * Functions provided:
 *   * logProfileChange(int $userID, int $fieldId, string $fieldName, string $old_value, string $new_value, string $old_value_db = '', string $new_value_db = '', string $action = "MODIFIED", $user = null)
 *   * logUserChange(int $userID, string $fieldName, string $old_value, string $new_value, User $user = null)
 *   * logRoleChange(int $userID, string $roleName, string $fieldName, string $old_value, string $new_value, string $action = "MODIFIED", User $user = null)
 *   * logUserCreation(int $userID, User $user = null)
 *   * logUserDeletion(int $userID, User $user = null)
 *   * logRoleCreation(int $userID, ....)
 *   * logRoleDeletion(int $userID, ....)
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
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
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
    protected array $changes = array();

    /** @var string $format Whether to send mails as 'html' or 'text' (as configured)
     */
    protected string $format = 'html';

    /**
     * Constructor that initialize the class member parameters
     * @throws Exception
     */
    public function __construct()
    {
        global $gSettingsManager;
 
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
     * @throws Exception
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

    /** Returns the full name of the user for display  Internal function*/
    function userDisplayName($userID, $user = null) {
        global $gDb, $gProfileFields;
        if (is_null($user)) {
            $user = new User($gDb, $gProfileFields, $userID);
        }
        return $user->getValue('LAST_NAME') . ", " . $user->getValue('FIRST_NAME');
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
     * @throws Exception
     */
    public function logProfileChange(int $userID, int $fieldId, string $fieldName, string $old_value = null, string $new_value = null, string $old_value_db = '', string $new_value_db = '', string $reason = "MODIFIED", $user = null)
    {
        // Store the change to send out one change notification mail (after all modifications are done)
        $this->prepareUserChanges($userID, $user);
        $this->changes[$userID]['profile_changes'][] = array($fieldName, $old_value, $new_value);
    }

    /**
     * Records a core user field change for the given user ID and the field ID.
     * Both the old and the new values are stored in an array and sent via
     * a system notification mail to the admin if configured.
     * Some user fields are special cased (password, photo), others are ignored
     * for irrelevance (internal fields).
     * The change log ist kept in a separate table in the database from the user
     * fields changes.
     * @param int $userID The user to whom the change applies
     * @param string $fieldName The ID of the modified profile field.
     * @param string $old_value The previous value of the field before the change
     * @param string $new_value The new value of the field after the change
     * @param User|null $user Optional the object of the changed user.
     * @throws Exception
     */
    public function logUserChange(int $userID, string $fieldName, string $old_value = null, string $new_value = null, string $action = "MODIFIED", User $user = null)
    {
        global $gSettingsManager, $gL10n, $gDb;

        // User Profile fields are accessed by their field name, so we need to extract the identifier for translation
        // Also, some fields need to be special cased (password replaced by *********, image by [...])
        // Ignore all fields (internal logging about who and when a user was
        // last changed) except explicitly handled (login, pwd, photo)
        $ignore = false;
        $fieldLabel = $fieldName;
        $fieldTag = $fieldName;
        switch ($fieldName) {
            case 'usr_login_name':
                $fieldTag = 'SYS_USERNAME';
                $fieldLabel = $gL10n->get($fieldTag);
                break;
            case 'usr_password':
                $fieldTag = 'SYS_PASSWORD';
                $fieldLabel = $gL10n->get($fieldTag);
                $old_value = $old_value ? '********' : $old_value;
                $new_value = $new_value ? '********' : $new_value;
                break;
            case 'usr_photo':
                $fieldTag = 'SYS_PHOTO';
                $fieldLabel = $gL10n->get($fieldTag);
                // Don't show photo data, replace with [...] if set
                $old_value = $old_value ? '[...]' : $old_value;
                $new_value = $new_value ? '[...]' : $new_value;
                break;
            case 'usr_text':
                $fieldTag = 'SYS_TEXT';
                $fieldLabel = $gL10n->get($fieldTag);
                break;
            default:
                $ignore = true;
        }

        $this->prepareUserChanges($userID, $user);

        if (!$ignore) {
            $this->changes[$userID]['profile_changes'][] = array($fieldLabel, $old_value, $new_value);
        }
    }

    /**
     * Records a role membership change for the given user ID and the given role.
     * Both the old and the new values are stored in an array and sent via
     * a system notification mail to the admin if configured.
     * @param Membership $membership The membership record that was changed
     * @param string $fieldName The human-readable name of the modified profile field.
     * @param string $old_value The previous value of the field before the change
     * @param string $new_value The new value of the field after the change
     */
    public function logRoleChange(Membership $membership, string $fieldName, string $old_value = null, string $new_value = null)
    {
        global $gSettingsManager, $gL10n;
        $userID = $membership->getValue("mem_usr_id");
        // Don't log anything if no User ID is set yet (e.g. user not yet saved to the database!)
        if ($userID == 0) {
            return;
        }

        // Human-readable representation of the changed attribute (start, end, leadership)
        // Also, ignore all other db table column changes...
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

        // Store the change to send out one change notification mail (after all modifications are done)
        $this->prepareUserChanges($userID);
        if (!$ignore) {
            $roleName = $membership->getValue('rol_name'); // TODO_RK: Check if this really works. First attempts indicate, it does not!
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
     * @throws Exception
     */
    public function logUserCreation(int $userID, User $user = null)
    {
        global $gProfileFields, $gDb, $gSettingsManager;

        // If user was never created in the DB, no need to log
        if ($userID == 0) {
            return;
        }
        if (is_null($user)) {
            $user = new User($gDb, $gProfileFields, $userID);
        }

        // Prepare the admin notifications
        $this->prepareUserChanges($userID, $user);
        $this->changes[$userID]['created'] = true;

        // Username and Passwords
        foreach (array('usr_login_name', 'usr_password', 'usr_photo', 'usr_text') as $fieldName) {
            $newValue = $user->getValue($fieldName, $this->format);
            if ($newValue) {
                $this->logUserChange($userID, $fieldName, null, $newValue, "CREATED", $user);
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
                    $this->logProfileChange($userID, $fieldID, $fieldLabel, '', $newValue, '', $newValue_db, "CREATED", $user);
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
     * @throws Exception
     */
    public function logUserDeletion(int $userID, User $user = null)
    {
        global $gProfileFields, $gL10n, $gDb, $gSettingsManager;

        // If user wasn't yet created in the DB, no need to log anything
        if ($userID == 0) {
            return;
        }

        // Prepare the admin notifications
        $this->prepareUserChanges($userID, $user);
        $this->changes[$userID]['deleted'] = true;

        $oldUser = new User($gDb, $gProfileFields, $userID);

        // Username and Passwords
        foreach (array('usr_login_name', 'usr_password', 'usr_photo', 'usr_text') as $fieldName) {
            $oldValue = $oldUser->getValue($fieldName, $this->format);
            if ($oldValue) {
                $this->logUserChange($userID, $fieldName, $oldValue, '', "DELETED", $user);
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
                    $this->logProfileChange($userID, $oldFieldId, $oldFieldName, $oldValue, '', $oldValueDB, '', "DELETE", $user);
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
            $this->logRoleChange($userID, $row['rol_name'], $gL10n->get('SYS_MEMBERSHIP_START'), $row['mem_begin'], '', "DELETE", $user);
            if ($row['mem_end']) {
                $this->logRoleChange($userID, $row['rol_name'], $gL10n->get('SYS_MEMBERSHIP_END'), $row['mem_end'], '', "DELETE", $user);
            }
            if ($row['mem_leader']) {
                $this->logRoleChange($userID, $row['rol_name'], $gL10n->get('SYS_LEADER'), $row['mem_leader'], '', "DELETE", $user);
            }
        }
    }

    /**
     * Send out all queued change notifications, if the configuration has system
     * change notifications enabled at all.
     * @param int $userID The user for whom the notification shall be sent (null for all queued notifications)
     * @throws Exception
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
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
