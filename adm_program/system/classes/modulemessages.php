<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class modulemessages
 * @brief some functions for the messages module
 *
 * This class adds some functions that are used in the messages module to keep the
 * code easy to read and short
 *
 * @par Examples
 * @code // check the given Array for charecter and split it.
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 * // show a message and set a link to a page that should be shown after user click ok
 * $gMessage->setForwardUrl('https://www.example.com/mypage.php');
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 * // show a message with yes and no button and set a link to a page that should be shown after user click yes
 * $gMessage->setForwardYesNo('https://www.example.com/mypage.php');
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID')); @endcode
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
        global $gCurrentOrganization, $gL10n, $gDb;

        $groupInfo = $this->msgGroupSplit($roleIdsString);

        $sql = 'SELECT rol_name
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                 WHERE rol_id = ' . $groupInfo['id'] . '
                   AND (  cat_org_id = ' . $gCurrentOrganization->getValue('org_id') . '
                       OR cat_org_id IS NULL)';
        $statement = $gDb->query($sql);
        $roleName = $statement->fetchColumn();

        switch ($groupInfo['status'])
        {
//            case 'active':
//                return $roleName . ' (' . $gL10n->get('LST_ACTIVE_MEMBERS') . ')';
            case 'former':
                return $roleName . ' (' . $gL10n->get('LST_FORMER_MEMBERS') . ')';
            case 'active_former':
                return $roleName . ' (' . $gL10n->get('LST_ACTIVE_FORMER_MEMBERS') . ')';
            default:
                return $roleName;
        }
    }

    /**
     * check for Group and give back a array with group ID[0] and if it is active, inactive or both [1].
     * @param string $groupString (e.g: "groupID: 4-2")
     * @return array Returns the groupId and status
     */
    public function msgGroupSplit($groupString)
    {
        $groupSplit = explode(':', $groupString);
        $groupIdAndStatus = explode('-', trim($groupSplit[1]));

        if (count($groupIdAndStatus) === 1)
        {
            $status = 'active';
        }
        elseif ($groupIdAndStatus[1] === '1')
        {
            $status = 'former';
        }
        elseif ($groupIdAndStatus[1] === '2')
        {
            $status = 'active_former';
        }
        else
        {
            $status = 'unknown';
        }

        return array(
            'id'     => (int) $groupIdAndStatus[0],
            'status' => $status
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

        $sql = 'SELECT msg_id, msg_usr_id_receiver AS user
                  FROM ' . TBL_MESSAGES . '
                 WHERE msg_type = \'EMAIL\' AND msg_usr_id_sender = ' . $userId . '
              ORDER BY msg_id DESC';

        return $gDb->query($sql);
    }

    /**
     * return an array with all unread Messages of the given user.
     * @param int $userId
     * @return \PDOStatement
     */
    public function msgGetUserUnread($userId)
    {
        global $gDb;

        $sql = 'SELECT msg_id, msg_usr_id_sender, msg_usr_id_receiver
                  FROM ' . TBL_MESSAGES . '
                 WHERE msg_type = \'PM\'
                   AND msg_usr_id_receiver LIKE \'' . $userId . '\' AND msg_read = 1
              ORDER BY msg_id DESC';

        return $gDb->query($sql);
    }

    /**
     * return an array with all unread Messages of the given user.
     * @param int $userId
     * @return \PDOStatement
     */
    public function msgGetUser($userId)
    {
        global $gDb;

        $sql = 'SELECT msg_id, msg_usr_id_sender, msg_usr_id_receiver
                  FROM ' . TBL_MESSAGES . '
                 WHERE msg_type = \'PM\'
                   AND ( (msg_usr_id_receiver LIKE \'' . $userId . '\' AND msg_read <> 1)
                       OR (msg_usr_id_sender = ' . $userId . ' AND msg_read < 2))
              ORDER BY msg_id DESC';

        return $gDb->query($sql);
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
        $statement = $gDb->query($sql);

        return (int) $statement->fetchColumn();
    }

}
