<?php
/******************************************************************************
/** @class modulemessages
 *  @brief some functions for the messages module
 *
 *  This class adds some functions that are used in the messages module to keep the
 *  code easy to read and short

 *  @par Examples
 *  @code // check the given Array for charecter and split it.
 *  $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 *  // show a message and set a link to a page that should be shown after user click ok
 *  $gMessage->setForwardUrl('http://www.example.de/mypage.php');
 *  $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 *  // show a message with yes and no button and set a link to a page that should be shown after user click yes
 *  $gMessage->setForwardYesNo('http://www.example.de/mypage.php');
 *  $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID')); @endcode
 */
 /*****************************************************************************
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class ModuleMessages
{
    /** Constructor that initialize the class member parameters
     */
    public function __construct()
    {

    }

    /** check for Group and give back a string with groupname and if it is active, inactive or both.
     */
    public function msgGroupNameSplit($groupstring)
    {
        global $gCurrentOrganization, $gL10n, $gDb;

        $group = $this->msgGroupSplit($groupstring);

        $sql = 'SELECT rol_name, rol_id
                      FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                     WHERE rol_cat_id    = cat_id
                       AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id').'
                           OR cat_org_id IS NULL)
                       AND rol_id = '.$group[0];
        $result = $gDb->query($sql);
        $row    = $gDb->fetch_array($result);

        if($group[1] == 1)
        {
            // only former members
            $ReceiverNameLong = $row['rol_name'] . ' (' .$gL10n->get('LST_FORMER_MEMBERS') . ')';
        }
        elseif($group[1] == 2)
        {
            // former members and active members
            $ReceiverNameLong = $row['rol_name'] . ' (' . $gL10n->get('LST_ACTIVE_FORMER_MEMBERS') . ')';
        }
        else
        {
            // only active members
            $ReceiverNameLong = $row['rol_name'] . ' (' .$gL10n->get('LST_ACTIVE_MEMBERS') . ')';
        }

        return $ReceiverNameLong;
    }

    /** check for Group and give back a array with group ID[0] and if it is active, inactive or both [1].
     */
    public function msgGroupSplit($groupstring)
    {
        $groupsplit = explode(':', $groupstring);

        if (strpos($groupsplit[1], '-') == true)
        {
            $group = explode('-', $groupsplit[1]);
        }
        else
        {
            $group[0] = $groupsplit[1];
            $group[1] = 0;
        }

        return $group;
    }

    /** return an array with all Email-Messages of the given user.
     */
    public function msgGetUserEmails($user)
    {
        global $gDb;

        $sql = "SELECT msg_id, msg_usr_id_receiver AS user
        FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'EMAIL' and msg_usr_id_sender = ". $user ."
         ORDER BY msg_id DESC";

        return $gDb->query($sql);
    }

    /** return an array with all unread Messages of the given user.
     */
    public function msgGetUserUnread($userId)
    {
        global $gDb;

        $sql = "
        SELECT msg_id, msg_usr_id_sender, msg_usr_id_receiver
          FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM'
           AND msg_usr_id_receiver LIKE '". $userId ."' and msg_read = 1
         ORDER BY msg_id DESC";

        return $gDb->query($sql);
    }

    /** return an array with all unread Messages of the given user.
     */
    public function msgGetUser($userId)
    {
        global $gDb;

        $sql = "
        SELECT msg_id, msg_usr_id_sender, msg_usr_id_receiver
          FROM ". TBL_MESSAGES. "
         WHERE msg_type = 'PM'
           AND ( (msg_usr_id_receiver LIKE '". $userId ."' and msg_read <> 1)
               OR (msg_usr_id_sender = ". $userId ." and msg_read < 2))
         ORDER BY msg_id DESC";

        return $gDb->query($sql);
    }

    /** return the message ID of the admidio chat.
     */
    public function msgGetChatId()
    {
        global $gDb;

        $sql = "SELECT msg_id FROM ". TBL_MESSAGES. " WHERE msg_type = 'CHAT'";
        $result = $gDb->query($sql);
        $row = $gDb->fetch_array($result);

        return $row['msg_id'];
    }

}
