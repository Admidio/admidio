<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Some functions for the messages module
 *
 * This class adds some functions that are used in the messages module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // check the given Array for charecter and split it.
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 * // show a message and set a link to a page that should be shown after user click ok
 * $gMessage->setForwardUrl('https://www.example.com/mypage.php');
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 * // show a message with yes and no button and set a link to a page that should be shown after user click yes
 * $gMessage->setForwardYesNo('https://www.example.com/mypage.php');
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 * ```
 */
class ModuleMessages
{
    /**
     * Constructor that initialize the class member parameters
     */
    public function __construct()
    {
    }

    /**
     * Check for roles and give back a string with rolename. If former members or activa and former
     * members were selected than an additional string will be shown after the rolename.
     * @param string $roleIdsString A string with several role ids. (e.g: "groupID: 4-2")
     * @return string Returns the rolename and the status if former members were selected.
     */
    public function msgGroupNameSplit($roleIdsString)
    {
        global $gL10n, $gDb;

        $groupInfo = $this->msgGroupSplit($roleIdsString);

        $sql = 'SELECT rol_name
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                 WHERE rol_id = ? -- $groupInfo[\'id\']
                   AND (  cat_org_id = ? -- $GLOBALS[\'gCurrentOrgId\']
                       OR cat_org_id IS NULL)';
        $statement = $gDb->queryPrepared($sql, array($groupInfo['id'], $GLOBALS['gCurrentOrgId']));
        $roleName = $statement->fetchColumn();

        switch ($groupInfo['status']) {
//            case 'active':
//                return $roleName . ' (' . $gL10n->get('SYS_ACTIVE_MEMBERS') . ')';
            case 'former':
                return $roleName . ' (' . $gL10n->get('SYS_FORMER_MEMBERS') . ')';
            case 'active_former':
                return $roleName . ' (' . $gL10n->get('SYS_ACTIVE_FORMER_MEMBERS') . ')';
            default:
                return $roleName;
        }
    }

    /**
     * check for Group and give back a array with group ID[0] and if it is active, inactive or both [1].
     * @param string $groupString (e.g: "groupID: 93ce816e-7cfd-45e1-b025-a3644828c47c+2")
     * @return array<string,string|int> Returns the groupId and status
     */
    public static function msgGroupSplit($groupString)
    {
        $groupSplit = explode(':', $groupString);
        $groupIdAndStatus = explode('+', trim($groupSplit[1]));

        if (count($groupIdAndStatus) === 1) {
            $status = Email::EMAIL_ONLY_ACTIVE_MEMBERS;
            $groupIdAndStatus[] = 0;
        } elseif ($groupIdAndStatus[1] === '1') {
            $status = Email::EMAIL_ONLY_FORMER_MEMBERS;
        } elseif ($groupIdAndStatus[1] === '2') {
            $status = Email::EMAIL_ALL_MEMBERS;
        } else {
            $status = Email::EMAIL_ONLY_ACTIVE_MEMBERS;
        }

        return array(
            'uuid'      => $groupIdAndStatus[0],
            'status'    => $status,
            'role_mode' => $groupIdAndStatus[1]
        );
    }

    /**
     * return an array with all Email-Messages of the given user.
     * @param int $userId
     * @return \PDOStatement
     */
    public function msgGetUserEmails($userId)
    {
        global $gDb;

        $sql = 'SELECT msg_id
                  FROM ' . TBL_MESSAGES . '
                 WHERE msg_type = \'EMAIL\'
                   AND msg_usr_id_sender = ? -- $userId
              ORDER BY msg_id DESC';

        return $gDb->queryPrepared($sql, array($userId));
    }

    /**
     * return an array with all unread Messages of the given user.
     * @param int $userId
     * @return \PDOStatement
     */
    public function msgGetUserUnread($userId)
    {
        global $gDb;

        $sql = 'SELECT msg_id
                  FROM ' . TBL_MESSAGES . '
                 INNER JOIN ' . TBL_MESSAGES_RECIPIENTS . ' ON msr_msg_id = msg_id
                 WHERE msg_type = \'PM\'
                   AND msr_usr_id = ? -- $userId
                   AND msg_read = 1
              ORDER BY msg_id DESC';

        return $gDb->queryPrepared($sql, array($userId));
    }

    /**
     * return an array with all unread Messages of the given user.
     * @param int $userId
     * @return \PDOStatement
     */
    public function msgGetUser($userId)
    {
        global $gDb;

        $sql = 'SELECT msg_id
                  FROM ' . TBL_MESSAGES . '
                 INNER JOIN ' . TBL_MESSAGES_RECIPIENTS . ' ON msr_msg_id = msg_id
                 WHERE msg_type = \'PM\'
                   AND ( (msr_usr_id = ? AND msg_read <> 1) -- $userId
                       OR (msg_usr_id_sender  = ? AND msg_read < 2)) -- $userId
              ORDER BY msg_id DESC';

        return $gDb->queryPrepared($sql, array($userId, $userId));
    }

    /**
     * return the message ID of the admidio chat.
     * @return int
     */
    public function msgGetChatId()
    {
        global $gDb;

        $sql = 'SELECT msg_id
                  FROM ' . TBL_MESSAGES . '
                 WHERE msg_type = \'CHAT\'';
        $statement = $gDb->queryPrepared($sql);

        return (int) $statement->fetchColumn();
    }
}
