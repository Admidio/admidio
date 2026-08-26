<?php
namespace Admidio\Infrastructure;

use Admidio\Hooks\Hooks;
use Admidio\Hooks\ValueObject\EntityChangeSet;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Exception;
use DateTime;
use Throwable;

/**
 * @brief Collects the changes to the users of this request and sends one notification mail per user
 *
 * The class listens to the committed persistence hooks of the three tables a person consists of -
 * **adm_users**, **adm_user_data** and **adm_members** - groups what it hears by the user it belongs
 * to and sends one mail per affected user at the end of the request, if system notifications for
 * profile changes are enabled in the configuration of Admidio.
 *
 * Listening instead of being called by hand is what makes the mail describe what really happened:
 *
 * - a value that was set on an object but never saved is not reported, because no hook fires;
 * - a change whose transaction is lost is not reported, because the hooks wait for the commit;
 * - a value that is written twice is one line, and a value that ends where it started is no line at
 *   all;
 * - a deleted user can still be described, because the change sets of the deletion carry the values
 *   the record held.
 *
 * On startup, a global (singleton) object $gChangeNotification is created in system/common.php,
 * which registers the listeners. Nothing else has to be done for a change to be reported.
 *
 * Two things are deliberately not reported. A membership of a role of the category **EVENTS** is a
 * participation in an event and not a role of the person. And a profile value or a membership that
 * disappears because the profile field or the role itself was deleted is a change of that field or
 * role, not of the hundreds of people who happened to have a value in it - the change set names the
 * record that caused the deletion, and only the person's own deletion counts as their change.
 *
 * **Code example**
 * ```
 * // send the pending notifications before the end of the request (they are sent at shutdown anyway)
 * $gChangeNotification->sendNotifications();
 *
 * // keep the changes of one user out of the notification, for a registration for example
 * $gChangeNotification->suppressUser($userId);
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ChangeNotification
{
    /**
     * The columns of adm_users that the notification reports, with their translation. Everything
     * else in that table is either bookkeeping of the record or is kept out of a mail on purpose.
     */
    protected const USER_COLUMNS = array(
        'usr_login_name' => 'SYS_USERNAME',
        'usr_password' => 'SYS_PASSWORD',
        'usr_photo' => 'SYS_PHOTO',
        'usr_text' => 'SYS_TEXT'
    );

    /**
     * The columns of adm_members that the notification reports, with their translation.
     */
    protected const MEMBERSHIP_COLUMNS = array(
        'mem_begin' => 'SYS_MEMBERSHIP_START',
        'mem_end' => 'SYS_MEMBERSHIP_END',
        'mem_leader' => 'SYS_LEADER'
    );

    /**
     * @var array $changes What the request changed, keyed by user ID and in the order in which the
     *  users were first touched. One entry looks like this:
     *      uid => array(
     *          'uid' => 123,
     *          'usr_uuid' => '',
     *          'usr_login_name' => '',
     *          'usr_valid' => true,
     *          'first_name' => '',
     *          'last_name' => '',
     *          'created' => false,
     *          'deleted' => false,
     *          'reasons' => array('user' => true, 'profile' => true, 'membership' => true),
     *          'profile_changes' => array(
     *              key => array('Field Name', 'old_value', 'new_value'),
     *          ),
     *          'role_changes' => array(
     *              key => array('Role Name', 'Field Name', 'old_value', 'new_value'),
     *          )
     *      )
     *  The changes are keyed by the field they belong to, so that a field which is written twice in
     *  one request stays one line of the mail, from the value it had before to the value it has now.
     */
    protected array $changes = array();

    /** @var array<int,bool> $suppressed The users whose changes must not be reported at all. */
    protected array $suppressed = array();

    /** @var array<int,array|null> $roles The name and category of a role, read at most once. */
    protected array $roles = array();

    /** @var string $format Whether to send mails as 'html' or 'text' (as configured) */
    protected string $format = 'html';

    /**
     * Constructor that initialize the class member parameters
     * @throws Exception
     */
    public function __construct()
    {
        global $gSettingsManager;

        $this->format = $gSettingsManager->getBool('mail_html_registered_users') ? 'html' : 'text';

        $this->registerListeners();

        // Register a shutdown function, which will be called when the whole PHP
        // script is finished, but before all global objects are destroyed
        // => That's the correct place to send out all pending change notification mails!
        register_shutdown_function(array($this, 'shutdown'));
    }

    /**
     * Listen to the committed hooks of the three tables a person consists of. The registrations use
     * an explicit ID, so that a second ChangeNotification object - the CLI builds one when a command
     * runs outside a web request - replaces the listeners of the first instead of doubling them.
     * @return void
     */
    protected function registerListeners(): void
    {
        foreach (array('created', 'updated', 'deleted') as $stage) {
            Hooks::addAction('user_' . $stage, array($this, 'onUserChanged'), Hooks::DEFAULT_PRIORITY, 1, 'change_notification');
            Hooks::addAction('user_data_' . $stage, array($this, 'onUserDataChanged'), Hooks::DEFAULT_PRIORITY, 1, 'change_notification');
            Hooks::addAction('membership_' . $stage, array($this, 'onMembershipChanged'), Hooks::DEFAULT_PRIORITY, 1, 'change_notification');
        }
    }

    /**
     * Keep every change of a user out of the notification. User::save() calls it for a user whose
     * change notification was switched off with User::disableChangeNotification(), a registration
     * for example, which sends a notification of its own and must not send this one as well.
     * @param int $userID The user whose changes must not be reported.
     * @return void
     */
    public function suppressUser(int $userID): void
    {
        if ($userID > 0) {
            $this->suppressed[$userID] = true;
        }
    }

    /**
     * Clear the queue of all recorded changes. No notifications are sent out by
     * this method.
     * @param int $userID The user for whom all recorded changes should be cleared (0 for all users)
     * @return void
     */
    public function clearChanges(int $userID = 0): void
    {
        if ($userID > 0) {
            unset($this->changes[$userID]);
        } else {
            $this->changes = array();
        }
    }

    /**
     * A committed create, update or delete of a record of adm_users.
     * @param EntityChangeSet $changeSet What the operation changed.
     * @return void
     * @throws Exception
     */
    public function onUserChanged(EntityChangeSet $changeSet): void
    {
        global $gL10n;

        $userID = (int)($changeSet->getId() ?? $this->valueOf($changeSet, 'usr_id'));
        if ($userID === 0) {
            return;
        }

        // A login writes nothing but the counters of the record, and that is not a change of the
        // user. getBusinessChanges() drops them, because they are the columns the changelog ignores.
        $businessChanges = $changeSet->getBusinessChanges();
        if ($changeSet->isUpdate() && count($businessChanges) === 0) {
            return;
        }

        $entry =& $this->entry($userID);
        $entry['created'] = $entry['created'] || $changeSet->isCreate();
        $entry['deleted'] = $entry['deleted'] || $changeSet->isDelete();
        $entry['reasons']['user'] = true;

        // The record is gone by the time the mail is written, so remember what identifies the user.
        if ($changeSet->getUuid() !== null) {
            $entry['usr_uuid'] = $changeSet->getUuid();
        }
        $loginName = $this->valueOf($changeSet, 'usr_login_name');
        if ($loginName !== null && $loginName !== '') {
            $entry['usr_login_name'] = (string)$loginName;
        }
        $entry['usr_valid'] = (bool)$this->valueOf($changeSet, 'usr_valid');

        foreach (self::USER_COLUMNS as $column => $translationId) {
            if (!$changeSet->hasChanged($column)) {
                continue;
            }

            $this->recordChange(
                $entry['profile_changes'],
                'usr:' . $column,
                array(
                    $gL10n->get($translationId),
                    $this->userValue($column, $changeSet->getOldValue($column)),
                    $this->userValue($column, $changeSet->getNewValue($column))
                ),
                1,
                2
            );
        }
    }

    /**
     * A committed create, update or delete of a record of adm_user_data, which is one profile field
     * value of one user. Creating the record means the field was filled for the first time and
     * deleting it means it was emptied, so all three operations are reported as one value change.
     * @param EntityChangeSet $changeSet What the operation changed.
     * @return void
     * @throws Exception
     */
    public function onUserDataChanged(EntityChangeSet $changeSet): void
    {
        global $gProfileFields;

        if ($this->isForeignCascade($changeSet) || !is_object($gProfileFields)) {
            return;
        }

        $userID = (int)$this->valueOf($changeSet, 'usd_usr_id');
        $fieldID = (int)$this->valueOf($changeSet, 'usd_usf_id');
        if ($userID === 0 || $fieldID === 0) {
            return;
        }

        $fieldNameIntern = (string)$gProfileFields->getPropertyById($fieldID, 'usf_name_intern');
        $oldValue = $this->profileValue($fieldNameIntern, $changeSet->getOldValue('usd_value'));
        $newValue = $this->profileValue($fieldNameIntern, $changeSet->getNewValue('usd_value'));

        $entry =& $this->entry($userID);

        // The row is gone by the time the mail is written, so remember what identifies the user.
        if ($fieldNameIntern === 'FIRST_NAME' || $fieldNameIntern === 'LAST_NAME') {
            $key = ($fieldNameIntern === 'FIRST_NAME') ? 'first_name' : 'last_name';
            $entry[$key] = ($newValue !== '') ? $newValue : $oldValue;
        }

        $changed = $this->recordChange(
            $entry['profile_changes'],
            'usf:' . $fieldID,
            array(
                (string)$gProfileFields->getPropertyById($fieldID, 'usf_name', $this->format),
                $oldValue,
                $newValue
            ),
            1,
            2
        );

        if ($changed) {
            $entry['reasons']['profile'] = true;
        }
    }

    /**
     * A committed create, update or delete of a record of adm_members, which is one membership of
     * one user in one role.
     * @param EntityChangeSet $changeSet What the operation changed.
     * @return void
     * @throws Exception
     */
    public function onMembershipChanged(EntityChangeSet $changeSet): void
    {
        global $gL10n;

        if ($this->isForeignCascade($changeSet)) {
            return;
        }

        $userID = (int)$this->valueOf($changeSet, 'mem_usr_id');
        $roleID = (int)$this->valueOf($changeSet, 'mem_rol_id');
        if ($userID === 0 || $roleID === 0) {
            return;
        }

        $role = $this->role($roleID);
        if ($role === null || $role['category'] === 'EVENTS') {
            // taking part in an event is not one of the roles of a person
            return;
        }

        $entry =& $this->entry($userID);

        foreach (self::MEMBERSHIP_COLUMNS as $column => $translationId) {
            if (!$changeSet->hasChanged($column)) {
                continue;
            }

            $changed = $this->recordChange(
                $entry['role_changes'],
                'mem:' . $roleID . ':' . $column,
                array(
                    $role['name'],
                    $gL10n->get($translationId),
                    $this->membershipValue($column, $changeSet->getOldValue($column)),
                    $this->membershipValue($column, $changeSet->getNewValue($column))
                ),
                2,
                3
            );

            if ($changed) {
                $entry['reasons']['membership'] = true;
            }
        }
    }

    /**
     * Whether the operation only happened because another record was deleted, and that record is
     * not the user themselves. Deleting a profile field or a role removes a value or a membership
     * of everybody who had one, and that is news about the field or the role, not about the people.
     * @param EntityChangeSet $changeSet The operation.
     * @return bool Returns **true** if the operation must not be reported.
     */
    protected function isForeignCascade(EntityChangeSet $changeSet): bool
    {
        $cause = $changeSet->getCauseHookId();

        return $cause !== null && $cause !== 'user';
    }

    /**
     * The value a column has after the operation: the value that was written, the value the record
     * held when it was deleted, or the value it holds unchanged.
     * @param EntityChangeSet $changeSet The operation.
     * @param string $column Name of the database column.
     * @return mixed Returns the value, or **null** if the change set does not know the column.
     */
    protected function valueOf(EntityChangeSet $changeSet, string $column): mixed
    {
        if ($changeSet->hasChanged($column)) {
            return $changeSet->getNewValue($column) ?? $changeSet->getOldValue($column);
        }

        return $changeSet->getSnapshot()[$column] ?? null;
    }

    /**
     * The entry of one user, created on first use. It is returned by reference, so that a caller
     * can write into it.
     * @param int $userID The user.
     * @return array Returns the entry of that user.
     */
    protected function &entry(int $userID): array
    {
        if (!array_key_exists($userID, $this->changes)) {
            $this->changes[$userID] = array(
                'uid' => $userID,
                'usr_uuid' => null,
                'usr_login_name' => null,
                'usr_valid' => true,
                'first_name' => null,
                'last_name' => null,
                'created' => false,
                'deleted' => false,
                'reasons' => array(),
                'profile_changes' => array(),
                'role_changes' => array()
            );
        }

        return $this->changes[$userID];
    }

    /**
     * Put one change into the list of a user. A field that is written a second time keeps the value
     * it had before the request and gets the value it has now, and a field that ends where it
     * started is dropped again - a request that sets a value and takes it back changed nothing.
     * @param array $changes The list the change belongs to, by reference.
     * @param string $key What makes two changes the same field.
     * @param array $change The change, as the mail prints it.
     * @param int $oldIndex Index of the previous value within the change.
     * @param int $newIndex Index of the new value within the change.
     * @return bool Returns **true** if a change is now in the list.
     */
    protected function recordChange(array &$changes, string $key, array $change, int $oldIndex, int $newIndex): bool
    {
        if (array_key_exists($key, $changes)) {
            $change[$oldIndex] = $changes[$key][$oldIndex];
        }

        if ($change[$oldIndex] === $change[$newIndex]) {
            unset($changes[$key]);
            return false;
        }

        $changes[$key] = $change;

        return true;
    }

    /**
     * The value of a column of adm_users as the mail prints it. The password and the photo are
     * withheld by the change set already; the photo is shown as the placeholder the change history
     * uses for it, because '********' would suggest a secret.
     * @param string $column Name of the database column.
     * @param mixed $value The value of the change set.
     * @return string Returns the value for the mail.
     */
    protected function userValue(string $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($column === 'usr_photo') {
            return '[...]';
        }

        return (string)$value;
    }

    /**
     * The value of a profile field as the mail prints it. The change set carries the value of the
     * database column, so a date is Y-m-d and a dropdown is the number of its option; the mail
     * always uses the text representation, because the HTML one may use CSS classes and image paths
     * that do not exist in a mail.
     * @param string $fieldNameIntern The **usf_name_intern** of the profile field.
     * @param mixed $value The value of the change set.
     * @return string Returns the value for the mail.
     * @throws Exception
     */
    protected function profileValue(string $fieldNameIntern, mixed $value): string
    {
        global $gProfileFields;

        if ($value === null || $value === '') {
            return '';
        }

        return (string)$gProfileFields->formatValue($fieldNameIntern, (string)$value, 'text');
    }

    /**
     * The value of a column of adm_members as the mail prints it.
     * @param string $column Name of the database column.
     * @param mixed $value The value of the change set.
     * @return string Returns the value for the mail.
     * @throws Exception
     */
    protected function membershipValue(string $column, mixed $value): string
    {
        global $gSettingsManager;

        if ($value === null || $value === '') {
            return '';
        }

        if ($column === 'mem_leader') {
            return $value ? '1' : '';
        }

        try {
            $date = new DateTime((string)$value);
            return $date->format($gSettingsManager->getString('system_date'));
        } catch (Throwable) {
            // not a date, so keep the value of the column
            return (string)$value;
        }
    }

    /**
     * The name and the category of a role, read at most once per request. A membership that was
     * deleted can still be described this way, because the role itself is still there.
     * @param int $roleID The role.
     * @return array|null Returns **array('name', 'category')**, or **null** if the role is gone.
     * @throws Exception
     */
    protected function role(int $roleID): ?array
    {
        global $gDb;

        if (!array_key_exists($roleID, $this->roles)) {
            $sql = 'SELECT rol_name, cat_name_intern
                      FROM ' . TBL_ROLES . '
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                     WHERE rol_id = ? -- $roleID';
            $row = $gDb->queryPrepared($sql, array($roleID))->fetch(\PDO::FETCH_ASSOC);

            $this->roles[$roleID] = ($row === false || $row === null)
                ? null
                : array('name' => $row['rol_name'], 'category' => $row['cat_name_intern']);
        }

        return $this->roles[$roleID];
    }

    /**
     * What identifies a user in the mail. The change sets are asked first, because they are the only
     * source left for a user that was deleted; everything they do not carry is read from the
     * database in one query.
     * @param array $userdata The entry of the user.
     * @return array Returns the keys **usr_uuid**, **usr_login_name**, **first_name** and **last_name**.
     * @throws Exception
     */
    protected function identity(array $userdata): array
    {
        global $gDb, $gProfileFields;

        $identity = array(
            'usr_uuid' => $userdata['usr_uuid'],
            'usr_login_name' => $userdata['usr_login_name'],
            'first_name' => $userdata['first_name'],
            'last_name' => $userdata['last_name']
        );

        if (!in_array(null, $identity, true)) {
            return $identity;
        }

        $sql = 'SELECT usr_uuid, usr_login_name,
                       last_name.usd_value AS last_name, first_name.usd_value AS first_name
                  FROM ' . TBL_USERS . '
             LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
             LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                 WHERE usr_id = ? -- $userdata[\'uid\']';
        $row = $gDb->queryPrepared($sql, array(
            (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $userdata['uid']
        ))->fetch(\PDO::FETCH_ASSOC);

        foreach ($identity as $column => $value) {
            if ($value === null) {
                $identity[$column] = ($row === false || $row === null) ? '' : (string)$row[$column];
            }
        }

        return $identity;
    }

    /**
     * Tell everybody who is interested that the state of a user changed, once per user and request.
     * The reasons say which of the three parts of a person changed - **user** for the record itself,
     * **profile** for a profile field value, **membership** for a role membership - so that a
     * consumer which only cares about one of them can leave early. The counters of the login are not
     * a reason, they are bookkeeping of the record.
     *
     * Whether the user was created or deleted is not part of this: a consumer that has to know
     * listens to **user_created** and **user_deleted**, which carry the whole change set.
     *
     * It runs at the head of sendNotifications(), which is the one place where the collected changes
     * of a user are used up, so a user is reported exactly once however the request ends.
     * @param int $userID The user for whom the action should be dispatched (0 for all).
     * @return void
     */
    protected function dispatchStateChanges(int $userID = 0): void
    {
        if (!Hooks::hasAction('user_state_changed')) {
            return;
        }

        foreach ($this->scopedChanges($userID) as $userdata) {
            if (count($userdata['reasons']) === 0) {
                continue;
            }

            $identity = $this->identity($userdata);
            Hooks::doAction('user_state_changed', $identity['usr_uuid'], array_keys($userdata['reasons']));
        }
    }

    /**
     * The collected changes of one user, or of everybody.
     * @param int $userID The user, or 0 for everybody.
     * @return array Returns the entries, keyed by user ID.
     */
    protected function scopedChanges(int $userID): array
    {
        if ($userID === 0) {
            return $this->changes;
        }

        return array_key_exists($userID, $this->changes) ? array($userID => $this->changes[$userID]) : array();
    }

    /**
     * Whether the changes of one user are worth a mail at all.
     * @param array $userdata The entry of the user.
     * @return bool Returns **true** if the user should get a notification.
     */
    protected function isReportable(array $userdata): bool
    {
        if (array_key_exists($userdata['uid'], $this->suppressed)) {
            // a registration sends a notification of its own
            return false;
        }

        if ($userdata['deleted'] && !$userdata['usr_valid']) {
            // an account that was never activated is not worth a deletion notice
            return false;
        }

        return true;
    }

    /**
     * Send out all queued change notifications, if the configuration has system
     * change notifications enabled at all.
     * @param int $userID The user for whom the notification shall be sent (0 for all queued notifications)
     * @throws Exception
     */
    public function sendNotifications(int $userID = 0)
    {
        global $gSettingsManager, $gL10n, $gCurrentUser;

        $this->dispatchStateChanges($userID);

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

            foreach ($this->scopedChanges($userID) as $userdata) {
                if (!$this->isReportable($userdata)) {
                    continue;
                }

                $identity = $this->identity($userdata);
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
                    array($identity['first_name'], $identity['last_name'], $identity['usr_login_name'], $currentName)
                );

                $changesOfUser = $userdata['profile_changes'];
                if ($changesOfUser) {
                    $hasContent = true;
                    $message .= $table_begin .
                        sprintf(
                            $format_hdr,
                            $gL10n->get('SYS_FIELD'),
                            $gL10n->get('SYS_PREVIOUS_VALUE'),
                            $gL10n->get('SYS_NEW_VALUE')
                        );
                    foreach ($changesOfUser as $c) {
                        $message .= sprintf($format_row, $c[0], $c[1], $c[2]);
                    }
                    $message .= $table_end;
                }

                $changesOfUser = $userdata['role_changes'];
                if ($changesOfUser) {
                    $hasContent = true;
                    $message .= $table_begin .
                        sprintf(
                            $format_rolhdr,
                            $gL10n->get('SYS_ROLE'),
                            $gL10n->get('SYS_FIELD'),
                            $gL10n->get('SYS_PREVIOUS_VALUE'),
                            $gL10n->get('SYS_NEW_VALUE')
                        );
                    foreach ($changesOfUser as $c) {
                        $message .= sprintf($format_rolrow, $c[0], $c[1], $c[2], $c[3]);
                    }
                    $message .= $table_end;
                }

                if ($hasContent) {
                    $notification->sendNotification(
                        $gL10n->get(
                            $messageTitle,
                            array($identity['first_name'], $identity['last_name'], $identity['usr_login_name'])
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
        } catch (Throwable $e) {
            echo $e->getMessage();
        }
    }
}
