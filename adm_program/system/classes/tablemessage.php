<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_messages
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * This class manages the set, update and delete in the table adm_messages
 ***********************************************************************************************
 */
class TableMessage extends TableAccess
{
    protected $msg_id;

    /**
     * Constructor that will create an object of a recordset of the table adm_messages.
     * If the id is set than the specific message will be loaded.
     * @param object $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int    $msg_id   The recordset of the message with this conversation id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $msg_id = 0)
    {
        $this->msg_id = $msg_id;

        parent::__construct($database, TBL_MESSAGES, 'msg', $this->msg_id);
    }

    /**
     * Reads the number of all unread messages of this table
     * @param int $usrId
     * @return int Number of unread messages of this table
     */
    public function countUnreadMessageRecords($usrId)
    {
        $sql = 'SELECT COUNT(*) as count
                  FROM '.$this->tableName.'
                 WHERE msg_usr_id_receiver LIKE \''. $usrId .'\'
                   AND msg_read = 1';
        $countStatement = $this->db->query($sql);
        $row = $countStatement->fetch();
        return $row['count'];
    }

    /**
     * Reads the number of all conversations in this table
     * @return int Number of conversations in this table
     */
    public function countMessageConversations()
    {
        $sql = 'SELECT COUNT(*) as count FROM '. TBL_MESSAGES;
        $countStatement = $this->db->query($sql);
        $row = $countStatement->fetch();
        return $row['count'];
    }

    /**
     * Reads the number of all messages in actual conversation
     * @return int Number of all messages in actual conversation
     */
    public function countMessageParts()
    {
        $sql = 'SELECT COUNT(*) as count
                  FROM '.TBL_MESSAGES_CONTENT.'
                 WHERE msc_msg_id = '.$this->getValue('msg_id');
        $countStatement = $this->db->query($sql);
        $row = $countStatement->fetch();
        return $row['count'];
    }

    /**
     * Set a new value for a column of the database table.
     * @param int $usrId of the receiver - just for security reasons.
     * @return object Returns @b answer of the SQL execution
     */
    public function setReadValue($usrId)
    {
        $sql = 'UPDATE '.TBL_MESSAGES.' SET  msg_read = \'0\'
                 WHERE msg_id = '.$this->msg_id.'
                   AND msg_usr_id_receiver LIKE \''.$usrId.'\'';
        return $this->db->query($sql);
    }

    /**
     * get a list with all messages of an conversation.
     * @param int $msgId of the conversation - just for security reasons.
     * @return object Returns @b answer of the SQL execution
     */
    public function getConversation($msgId)
    {
        $sql = 'SELECT msc_usr_id, msc_message, msc_timestamp
                  FROM '. TBL_MESSAGES_CONTENT. '
                 WHERE msc_msg_id = '. $msgId .'
              ORDER BY msc_part_id DESC';
        return $this->db->query($sql);
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method @b save to store the new value to the database
     * @param int $usrId
     * @return int Returns @b ID of the user that is partner in the actual conversation
     */
    public function getConversationPartner($usrId)
    {
        $sql = 'SELECT msg_id,
                  CASE WHEN msg_usr_id_sender = '. $usrId .' THEN msg_usr_id_receiver
                  ELSE msg_usr_id_sender
                   END AS user
                  FROM '.TBL_MESSAGES.'
                 WHERE msg_type = \'PM\'
                   AND msg_id = '. $this->msg_id;
        $partnerStatement = $this->db->query($sql);
        $row = $partnerStatement->fetch();

        return $row['user'];
    }

    /**
     * Deletes the selected message with all associated fields.
     * After that the class will be initialize.
     * @return bool @b true if message is deleted or message with additional information if it is marked
     *         for other user to delete. On error it is false
     */
    public function delete()
    {
        global $gCurrentUser;

        $this->db->startTransaction();

        if($this->getValue('msg_read') == 2 || $this->getValue('msg_type') === 'EMAIL')
        {
            $sql = 'DELETE FROM '.TBL_MESSAGES_CONTENT.'
             WHERE msc_msg_id = '. $this->getValue('msg_id');
            $this->db->query($sql);

            parent::delete();
        }
        else
        {
            $other = $this->getValue('msg_usr_id_sender');
            if($other == $gCurrentUser->getValue('usr_id'))
            {
                $other = $this->getValue('msg_usr_id_receiver');
            }

            $sql = "UPDATE ". TBL_MESSAGES. " SET msg_read = 2, msg_timestamp = CURRENT_TIMESTAMP
                                                , msg_usr_id_sender = ".$gCurrentUser->getValue('usr_id').", msg_usr_id_receiver = '".$other."'
             WHERE msg_id = ".$this->getValue('msg_id');
            $this->db->query($sql);
        }

        $this->db->endTransaction();
        return true;
    }
}
