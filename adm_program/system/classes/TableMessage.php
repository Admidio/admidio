<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_messages
 *
 * @copyright 2004-2021 The Admidio Team
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
     * @var array
     */
    protected $msgRecipients = array();

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
        $countStatement = $this->db->queryPrepared($sql, array((int) $this->getValue('msg_id')));

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
        global $gDbType;

        if($gDbType === Database::PDO_ENGINE_PGSQL)
        {
            $messageSender = 'msg_usr_id_sender::varchar(255)';
        }
        else
        {
            $messageSender = 'CONVERT(msg_usr_id_sender, CHAR)';
        }

        $sql = 'SELECT
                  CASE
                  WHEN msg_usr_id_sender = ? -- $usrId
                  THEN msg_usr_id_receiver
                  ELSE ' . $messageSender . '
                   END AS user
                  FROM '.TBL_MESSAGES.'
                 WHERE msg_type = \'PM\'
                   AND msg_id = ? -- $this->msgId';
        $partnerStatement = $this->db->queryPrepared($sql, array($usrId, $this->msgId));

        return (int) $partnerStatement->fetchColumn();
    }

    /**
     * Reads all recipients to the message and returns an array. The array has the following structure:
     * array('type' => 'role', 'id' => '4711', 'name' => 'Administrator', 'mode' => '0')
     * Type could be **role** or **user**, the id will be the database id of role or user and the
     * mode will be only used with roles and the following values are used:
     + 0 = active members, 1 = former members, 2 = active and former members
     * @return array Returns an array with all recipients (users and roles)
     */
    public function getRecipientsArray()
    {
        global $gProfileFields;

        if(count($this->msgRecipients) === 0)
        {
            $sql = 'SELECT msr_rol_id, msr_usr_id, msr_role_mode, rol_name, first_name.usd_value AS firstname, last_name.usd_value AS lastname
                      FROM ' . TBL_MESSAGES_RECIPIENTS . '
                      LEFT JOIN ' . TBL_ROLES . ' ON rol_id = msr_rol_id
                      LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                             ON last_name.usd_usr_id = msr_usr_id
                            AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                      LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                             ON first_name.usd_usr_id = msr_usr_id
                            AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                    WHERE msr_msg_id = ? -- $this->getValue(\'msg_id\') ';
            $messagesRecipientsStatement = $this->db->queryPrepared($sql,
                array($gProfileFields->getProperty('LAST_NAME', 'usf_id'), $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), $this->getValue('msg_id')));

            while($row = $messagesRecipientsStatement->fetch())
            {
                if($row['msr_rol_id'] > 0)
                {
                    // add role to recipients
                    $this->msgRecipients[] =
                        array('type' => 'user',
                              'id'   => (int) $row['msr_usr_id'],
                              'name' => $row['rol_name'],
                              'mode' => 0
                        );
                }
                else
                {
                    // add user to recipients
                    $this->msgRecipients[] =
                        array('type' => 'role',
                              'id'   => (int) $row['msr_rol_id'],
                              'name' => $row['firstname'] . ' ' . $row['lastname'],
                              'mode' => (int) $row['msr_role_mode']
                        );
                }
            }
        }

        return $this->msgRecipients;
    }

    /**
     * Build a string with all role names and firstname and lastname of the users.
     * The names will be semicolon separated.
     * @return string Returns a string with all role names and firstname and lastname of the users.
     */
    public function getRecipientsNamesString()
    {
        global $gCurrentUser, $gProfileFields;

        $recipients = $this->getRecipientsArray();
        $recipientsString = '';

        if($this->getValue('msg_type') === TableMessage::MESSAGE_TYPE_PM)
        {
            // PM has the conversation initiator and the receiver. Here we must check which
            // role the current user has and show the name of the other user.
            if((int) $this->getValue('msg_usr_id_sender') === (int) $gCurrentUser->getValue('usr_id'))
            {
                $recipientsString = $recipients[0]['name'];
            }
            else
            {
                $user = new User($this->db, $gProfileFields, $this->getValue('msg_usr_id_sender'));
                $recipientsString = $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');
            }
        }
        else
        {
            // email receivers are all stored in the recipients array
            foreach($recipients as $recipient)
            {
                if(strlen($recipientsString) > 0)
                {
                    $recipientsString .= '; ';
                }
                $recipientsString .= $recipient['name'];
            }
        }

        return $recipientsString;
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
