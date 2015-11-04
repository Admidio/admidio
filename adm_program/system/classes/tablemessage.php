<?php
/******************************************************************************
 * Class manages access to database table adm_messages
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * This class manages the set, update and delete in the table adm_messages
 *
 *****************************************************************************/

class TableMessage extends TableAccess
{
    protected $msg_id;

    /** Constructor that will create an object of a recordset of the table adm_messages.
     *  If the id is set than the specific message will be loaded.
     *  @param object $database Object of the class Database. This should be the default global object @b $gDb.
     *  @param int    $msg_id   The recordset of the message with this conversation id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $msg_id = 0)
    {
        $this->msg_id = $msg_id;

        parent::__construct($database, TBL_MESSAGES, 'msg', $this->msg_id);
    }

    /** Reads the number of all unread messages of this table
     *  @return Number of unread messages of this table
     */
    public function countUnreadMessageRecords($usr_id)
    {
        $sql = 'SELECT COUNT(1) as count FROM '.$this->tableName.' WHERE msg_usr_id_receiver LIKE \''. $usr_id .'\' and msg_read = 1';
        $countStatement = $this->db->query($sql);
        $row = $countStatement->fetch();
        return $row['count'];
    }

    /** Reads the number of all conversations in this table
     *  @return Number of conversations in this table
     */
    public function countMessageConversations()
    {
        $sql = 'SELECT COUNT(1) as count FROM '. TBL_MESSAGES;
        $countStatement = $this->db->query($sql);
        $row = $countStatement->fetch();
        return $row['count'];
    }

    /** Reads the number of all messages in actual conversation
     *  @return Number of all messages in actual conversation
     */
    public function countMessageParts()
    {
        $sql = 'SELECT COUNT(1) as count FROM '.TBL_MESSAGES_CONTENT.'
                 WHERE msc_msg_id = '.$this->getValue('msg_id');
        $countStatement = $this->db->query($sql);
        $row = $countStatement->fetch();
        return $row['count'];
    }

    /** Set a new value for a column of the database table.
     *  @param $usr_id of the receiver - just for security reasons.
     *  @return Returns @b answer of the SQL execution
     */
    public function setReadValue($usr_id)
    {
        $sql = 'UPDATE '. TBL_MESSAGES. ' SET  msg_read = \'0\'
                 WHERE msg_id = '.$this->msg_id.'
                   AND msg_usr_id_receiver LIKE \''.$usr_id.'\'';
        return $this->db->query($sql);
    }

    /** Set a new value for a column of the database table.
     *  The value is only saved in the object. You must call the method @b save to store the new value to the database
     *  @param $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.
     *  @return Returns @b ID of the user that is partner in the actual conversation
     */
    public function getConversationPartner($usr_id)
    {
        $sql = 'SELECT msg_id,
                 CASE WHEN msg_usr_id_sender = '. $usr_id .' THEN msg_usr_id_receiver
                 ELSE msg_usr_id_sender
                  END AS user
                 FROM '. TBL_MESSAGES. '
                WHERE msg_type = \'PM\'
                  AND msg_id = '. $this->msg_id;
        $partnerStatement = $this->db->query($sql);
        $row = $partnerStatement->fetch();

        return $row['user'];
    }

    /** Deletes the selected message with all associated fields.
     *  After that the class will be initialize.
     *  @return @b 'done' if message is deleted or message with additional information if it is marked
                for other user to delete. On error it is "delete not OK"
     */
    public function delete()
    {
        global $gCurrentUser;

        $return = 'delete not OK';

        if($this->getValue('msg_read') == 2 || $this->getValue('msg_type') == 'EMAIL')
        {
            $sql = "DELETE FROM ".TBL_MESSAGES_CONTENT."
             WHERE msc_msg_id = ". $this->getValue('msg_id');
            $this->db->query($sql);

            $sql = "DELETE FROM ".TBL_MESSAGES."
             WHERE msg_id = ". $this->getValue('msg_id');
            $this->db->query($sql);

            $return = 'done';
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

            $return = 'done';
        }

        return $return;
    }
}
