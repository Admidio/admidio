<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_messages
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class manages the set, update and delete in the table adm_messages
 */
class TableMessage extends TableAccess
{
    const MESSAGE_TYPE_EMAIL = 'EMAIL';
    const MESSAGE_TYPE_PM    = 'PM';

    /**
     * @var int
     */
    protected $msgId;

    /**
     * Constructor that will create an object of a recordset of the table adm_messages.
     * If the id is set than the specific message will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $msgId    The recordset of the message with this conversation id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $msgId = 0)
    {
        $this->msgId = $msgId;

        parent::__construct($database, TBL_MESSAGES, 'msg', $this->msgId);
    }

    /**
     * Reads the number of all unread messages of this table
     * @param int $usrId
     * @return int Number of unread messages of this table
     */
    public function countUnreadMessageRecords($usrId)
    {
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.$this->tableName.'
                 WHERE msg_read = 1
                   AND msg_usr_id_receiver = ? -- $usrId';
        $countStatement = $this->db->queryPrepared($sql, array($usrId));

        return (int) $countStatement->fetchColumn();
    }

    /**
     * Reads the number of all conversations in this table
     * @return int Number of conversations in this table
     */
    public function countMessageConversations()
    {
        $sql = 'SELECT COUNT(*) AS count FROM ' . TBL_MESSAGES;
        $countStatement = $this->db->queryPrepared($sql);

        return (int) $countStatement->fetchColumn();
    }

    /**
     * Reads the number of all messages in actual conversation
     * @return int Number of all messages in actual conversation
     */
    public function countMessageParts()
    {
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_MESSAGES_CONTENT.'
                 WHERE msc_msg_id = ? -- $this->getValue(\'msg_id\')';
        $countStatement = $this->db->queryPrepared($sql, array($this->getValue('msg_id')));

        return (int) $countStatement->fetchColumn();
    }

    /**
     * Set a new value for a column of the database table.
     * @param int $usrId of the receiver - just for security reasons.
     * @return false|\PDOStatement Returns **answer** of the SQL execution
     */
    public function setReadValue($usrId)
    {
        $sql = 'UPDATE '.TBL_MESSAGES.'
                   SET msg_read = 0
                 WHERE msg_id   = ? -- $this->msgId
                   AND msg_usr_id_receiver = ? -- $usrId';

        return $this->db->queryPrepared($sql, array($this->msgId, $usrId));
    }

    /**
     * get a list with all messages of an conversation.
     * @param int $msgId of the conversation - just for security reasons.
     * @return false|\PDOStatement Returns **answer** of the SQL execution
     */
    public function getConversation($msgId)
    {
        $sql = 'SELECT msc_usr_id, msc_message, msc_timestamp
                  FROM '. TBL_MESSAGES_CONTENT. '
                 WHERE msc_msg_id = ? -- $msgId
              ORDER BY msc_part_id DESC';

        return $this->db->queryPrepared($sql, array($msgId));
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param int $usrId
     * @return int Returns **ID** of the user that is partner in the actual conversation
     */
    public function getConversationPartner($usrId)
    {
        $sql = 'SELECT
                  CASE
                  WHEN msg_usr_id_sender = ? -- $usrId
                  THEN msg_usr_id_receiver
                  ELSE msg_usr_id_sender
                   END AS user
                  FROM '.TBL_MESSAGES.'
                 WHERE msg_type = \'PM\'
                   AND msg_id = ? -- $this->msgId';
        $partnerStatement = $this->db->queryPrepared($sql, array($usrId, $this->msgId));

        return (int) $partnerStatement->fetchColumn();
    }

    /**
     * Deletes the selected message with all associated fields.
     * After that the class will be initialize.
     * @return bool **true** if message is deleted or message with additional information if it is marked
     *         for other user to delete. On error it is false
     */
    public function delete()
    {
        global $gCurrentUser;

        $this->db->startTransaction();

        $msgId = (int) $this->getValue('msg_id');

        if ($this->getValue('msg_type') === self::MESSAGE_TYPE_EMAIL || (int) $this->getValue('msg_read') === 2)
        {
            $sql = 'DELETE FROM '.TBL_MESSAGES_CONTENT.'
                     WHERE msc_msg_id = ? -- $msgId';
            $this->db->queryPrepared($sql, array($msgId));

            parent::delete();
        }
        else
        {
            $currUsrId = (int) $gCurrentUser->getValue('usr_id');

            $other = (int) $this->getValue('msg_usr_id_sender');
            if ($other === $currUsrId)
            {
                $other = (int) $this->getValue('msg_usr_id_receiver');
            }

            $sql = 'UPDATE '.TBL_MESSAGES.'
                       SET msg_read = 2
                         , msg_usr_id_sender   = ? -- $currUsrId
                         , msg_usr_id_receiver = ? -- $other
                         , msg_timestamp = CURRENT_TIMESTAMP
                     WHERE msg_id = ? -- $msgId';
            $this->db->queryPrepared($sql, array($currUsrId, $other, $msgId));
        }

        $this->db->endTransaction();

        return true;
    }
}
