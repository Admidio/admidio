<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_messages
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class manages the set, update and delete in the table adm_messages
 */
class TableMessage extends TableAccess
{
    public const MESSAGE_TYPE_EMAIL = 'EMAIL';
    public const MESSAGE_TYPE_PM    = 'PM';

    /**
     * @var array<int,string> This array has all the file names of the attachments. First element is the path and second element is the file name.
     */
    private $msgAttachments = array();
    /**
     * @var array Array with all recipients of the message.
     */
    protected $msgRecipientsArray = array();
    /**
     * @var array with TableAcess objects
     */
    protected $msgRecipientsObjectArray = array();
    /**
     * @var object of TableAcess for the current content of the message.
     */
    protected $msgContentObject;
    /**
     * @var int ID the conversation partner of a private message. This is the recipient of the message from msg_usr_id_sender.
     */
    protected $msgConversationPartnerId = 0;

    /**
     * Constructor that will create an object of a recordset of the table adm_messages.
     * If the id is set than the specific message will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $msgId    The recordset of the message with this conversation id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $msgId = 0)
    {
        parent::__construct($database, TBL_MESSAGES, 'msg', $msgId);

        $this->getContent('database');
    }

    /**
     * Add an attachment from a path on the filesystem.
     * @param string $path        Path to the attachment
     * @param string $name        Overrides the attachment name
     */
    public function addAttachment($path, $name = '')
    {
        $this->msgAttachments[] = array($path, $name);
    }

    /**
     * A role could be added to the class to which the email was send. This information will
     * later be stored in the database. If you need the role name within the class before the
     * data is stored in database than you should set the role name with the parameter $roleName.
     * @param int $roleId   Id the role to which the message was send
     * @param int $roleMode This parameter has the following values:
     *                      0 - only active members of the role
     *                      1 - only former members of the role
     *                      2 - active and former members of the role
     * @param string $roleName Optional the name of the role. Should be set if the name should be used within the class.
     */
    public function addRole($roleId, $roleMode, $roleName = '')
    {
        // first search if role already exists in recipients list
        foreach ($this->msgRecipientsObjectArray as $messageRecipientObject) {
            if ($messageRecipientObject->getValue('msr_rol_id') === $roleId) {
                // if object found than update role mode and exist function
                $messageRecipientObject->setValue('msr_role_mode', $roleMode);
                return;
            }
        }

        // save message recipient as TableAcess object to the array
        $messageRecipient = new TableAccess($this->db, TBL_MESSAGES_RECIPIENTS, 'msr');
        $messageRecipient->setValue('msr_msg_id', $this->getValue('msg_id'));
        $messageRecipient->setValue('msr_rol_id', $roleId);
        $messageRecipient->setValue('msr_role_mode', $roleMode);
        $this->msgRecipientsObjectArray[] = $messageRecipient;

        // now save message recipient into an simple array
        $this->msgRecipientsArray[] =
            array('type'   => 'role',
                  'id'     => $roleId,
                  'name'   => $roleName,
                  'mode'   => $roleMode,
                  'msr_id' => null
            );
    }

    /**
     * A user could be added to the class to which the email was sent. This information will
     * later be stored in the database. If you need the users name within the class before the
     * data is stored in database than you should set the users name with the parameter $fullName.
     * @param int $userId Id the user to which the message was sent
     * @param string $fullName Optional the name of the user. Should be set if the name should be used within the class.
     * @throws AdmException
     */
    public function addUser(int $userId, string $fullName = '')
    {
        // PM always update the recipient if the message exists
        if ($this->getValue('msg_type') === self::MESSAGE_TYPE_PM) {
            if (count($this->msgRecipientsObjectArray) === 1) {
                $this->msgRecipientsObjectArray[0]->setValue('msr_usr_id', $userId);
                return;
            }
        } else { // EMAIL
            // first search if user already exists in recipients list and than exist function
            foreach ($this->msgRecipientsObjectArray as $messageRecipientObject) {
                if ($messageRecipientObject->getValue('msr_usr_id') === $userId) {
                    return;
                }
            }
        }

        // if user doesn't exists in recipient list than save recipient as TableAcess object to the array
        $messageRecipient = new TableAccess($this->db, TBL_MESSAGES_RECIPIENTS, 'msr');
        $messageRecipient->setValue('msr_msg_id', $this->getValue('msg_id'));
        $messageRecipient->setValue('msr_usr_id', $userId);
        $this->msgRecipientsObjectArray[] = $messageRecipient;

        // now save message recipient into an simple array
        $this->msgRecipientsArray[] =
            array('type'   => 'user',
                  'id'     => $userId,
                  'name'   => $fullName,
                  'mode'   => null,
                  'msr_id' => null
            );
    }

    /**
     * Add the content of the message or email. The content will than
     * be saved if the message will be saved.
     * @param string $content Current content of the message.
     */
    public function addContent($content)
    {
        $this->msgContentObject = new TableMessageContent($this->db);
        $this->msgContentObject->setValue('msc_msg_id', $this->getValue('msg_id'));
        $this->msgContentObject->setValue('msc_message', $content, false);
        $this->msgContentObject->setValue('msc_timestamp', DATETIME_NOW);
    }

    /**
     * Reads the number of all unread messages of this table
     * @param int $usrId
     * @return int Number of unread messages of this table
     */
    public function countUnreadMessageRecords($usrId)
    {
        $sql = 'SELECT COUNT(*) AS count
                  FROM ' . TBL_MESSAGES . '
                 INNER JOIN ' . TBL_MESSAGES_RECIPIENTS . ' ON msr_msg_id = msg_id
                 WHERE msg_read = 1
                   AND msr_usr_id = ? -- $usrId';
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
     * Deletes the selected message with all associated fields.
     * After that the class will be initialized.
     * @return bool **true** if message is deleted or message with additional information if it is marked
     *         for other user to delete. On error, it is false
     */
    public function delete(): bool
    {
        $this->db->startTransaction();

        $msgId = (int) $this->getValue('msg_id');

        if ($this->getValue('msg_type') === self::MESSAGE_TYPE_EMAIL || (int) $this->getValue('msg_read') === 2) {
            // first delete attachments files and the database entry
            $attachments   = $this->getAttachmentsInformations();

            foreach ($attachments as $attachment) {
                // delete attachment in file system
                FileSystemUtils::deleteFileIfExists(ADMIDIO_PATH . FOLDER_DATA . '/messages_attachments/' . $attachment['admidio_file_name']);
            }

            $sql = 'DELETE FROM '.TBL_MESSAGES_ATTACHMENTS.'
                     WHERE msa_msg_id = ? -- $msgId';
            $this->db->queryPrepared($sql, array($msgId));

            $sql = 'DELETE FROM '.TBL_MESSAGES_CONTENT.'
                     WHERE msc_msg_id = ? -- $msgId';
            $this->db->queryPrepared($sql, array($msgId));

            $sql = 'DELETE FROM '.TBL_MESSAGES_RECIPIENTS.'
                     WHERE msr_msg_id = ? -- $msgId';
            $this->db->queryPrepared($sql, array($msgId));

            parent::delete();
        } else {
            $sql = 'UPDATE '.TBL_MESSAGES.'
                       SET msg_read = 2
                     WHERE msg_id = ? -- $msgId';
            $this->db->queryPrepared($sql, array($msgId));
        }

        $this->db->endTransaction();

        return true;
    }

    /**
     * Read all attachments from the database and will return an array with all neccessary informations about
     * the attachments. The array contains for each attachment a subarray with the following elements:
     * **msa_id** and **file_name** and **admidio_file_name**.
     * @return array Returns an array with all attachments and the following elements: **msa_id** and **file_name**
     */
    public function getAttachmentsInformations()
    {
        $attachments = array();

        $sql = 'SELECT msa_id, msa_original_file_name, msa_file_name
                  FROM ' . TBL_MESSAGES_ATTACHMENTS .'
                 WHERE msa_msg_id = ? -- $this->getValue(\'msg_id\')';
        $attachmentsStatement = $this->db->queryPrepared($sql, array($this->getValue('msg_id')));

        while ($row = $attachmentsStatement->fetch()) {
            $attachments[] = array('msa_id' => $row['msa_id'], 'file_name' => $row['msa_original_file_name'], 'admidio_file_name' => $row['msa_file_name']);
        }

        return $attachments;
    }

    /**
     * Get the content of the message or email. If it's a message conversation than only
     * the last content will be returned.
     * @param string $format The format can be **database** that would return the original database value without any transformations
     * @return string Returns the content of the message.
     */
    public function getContent(string $format = ''): string
    {
        $content = '';

        // if content was not set until now than read it from the database if message was already stored there
        if (!is_object($this->msgContentObject) && $this->getValue('msg_id') > 0) {
            $sql = 'SELECT msc_id, msc_msg_id, msc_usr_id, msc_message, msc_timestamp
                      FROM '. TBL_MESSAGES_CONTENT. ' msc1
                     WHERE msc_msg_id = ? -- $this->getValue(\'msg_id\')
                       AND NOT EXISTS (
                           SELECT 1
                             FROM '. TBL_MESSAGES_CONTENT. ' msc2
                            WHERE msc2.msc_msg_id = msc1.msc_msg_id
                              AND msc2.msc_timestamp > msc1.msc_timestamp
                           )';
            $messageContentStatement = $this->db->queryPrepared($sql, array($this->getValue('msg_id')));

            $this->msgContentObject = new TableMessageContent($this->db);
            $this->msgContentObject->setArray($messageContentStatement->fetch());
        }

        // read content of the content object
        if (is_object($this->msgContentObject)) {
            $content = $this->msgContentObject->getValue('msc_message', $format);
        }

        return $content;
    }

    /**
     * get a list with all messages of an conversation.
     * @param int $msgId of the conversation - just for security reasons.
     * @return false|\PDOStatement Returns **answer** of the SQL execution
     */
    public function getConversation($msgId)
    {
        $sql = 'SELECT msc_id, msc_usr_id, msc_message, msc_timestamp
                  FROM '. TBL_MESSAGES_CONTENT. '
                 WHERE msc_msg_id = ? -- $msgId
              ORDER BY msc_id DESC';

        return $this->db->queryPrepared($sql, array($msgId));
    }

    /**
     * If the message type is PM this method will return the conversation partner of the PM. This is the
     * recipient of the message send from **msg_usr_id_sender**.
     * @return int Returns **ID** of the user that is partner in the actual conversation or **false** if it's not a message.
     */
    public function getConversationPartner()
    {
        if ($this->getValue('msg_type') === self::MESSAGE_TYPE_PM) {
            if($this->msgConversationPartnerId === 0) {
                $recipients = $this->readRecipientsData();
                foreach ($recipients as $recipient) {
                    if ($recipient['id'] !== $this->getValue('msg_usr_id_sender')) {
                        $this->msgConversationPartnerId = $recipient['id'];
                    }
                }
            }

            return $this->msgConversationPartnerId;
        }

        return false;
    }

    /**
     * Build a string with all role names and firstname and lastname of the users.
     * The names will be semicolon separated. If $showFullUserNames is set to false only a
     * number of recipients users will be shown.
     * @param bool $showFullUserNames If set to true the first and last name of each user will be shown.
     * @return string Returns a string with all role names and firstname and lastname of the users.
     */
    public function getRecipientsNamesString($showFullUserNames = true)
    {
        global $gProfileFields, $gL10n;

        $recipients = $this->readRecipientsData();
        $recipientsString = '';
        $singleRecipientsCount = 0;

        if ($this->getValue('msg_type') === self::MESSAGE_TYPE_PM) {
            // PM has the conversation initiator and the receiver. Here we must check which
            // role the current user has and show the name of the other user.
            if ((int) $this->getValue('msg_usr_id_sender') === $GLOBALS['gCurrentUserId']) {
                $recipientsString = $recipients[0]['name'];
            } else {
                $user = new User($this->db, $gProfileFields, $this->getValue('msg_usr_id_sender'));
                $recipientsString = $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');
            }
        } else {
            // email receivers are all stored in the recipients array
            foreach ($recipients as $recipient) {
                if ($recipient['type'] === 'user' && !$showFullUserNames) {
                    $singleRecipientsCount++;
                } else {
                    if (strlen($recipientsString) > 0) {
                        $recipientsString .= '; ';
                    }

                    $recipientsString .= $recipient['name'];
                }
            }

            // if full usernames should not be shown than create a text with the number of individual recipients
            if (!$showFullUserNames && $singleRecipientsCount > 0) {
                if ($singleRecipientsCount === 1) {
                    $textIndividualRecipients = $gL10n->get('SYS_COUNT_INDIVIDUAL_RECIPIENT', array($singleRecipientsCount));
                } else {
                    $textIndividualRecipients = $gL10n->get('SYS_COUNT_INDIVIDUAL_RECIPIENTS', array($singleRecipientsCount));
                }

                if (strlen($recipientsString) > 0) {
                    $recipientsString = $gL10n->get('SYS_PARAMETER1_AND_PARAMETER2', array($recipientsString, $textIndividualRecipients));
                } else {
                    $recipientsString = $textIndividualRecipients;
                }
            }
        }

        return $recipientsString;
    }

    /**
     * Method will return true if the PM was sent to the current user and not is already unread.
     * Therefore the current user is not the sender of the PM and the flag **msg_read** is set to 1.
     * Email will always have the status read.
     * @return bool Returns true if the PM was not read from the current user.
     */
    public function isUnread()
    {
        if (self::MESSAGE_TYPE_PM && $this->getValue('msg_read') === 1
        && $this->getValue('msg_usr_id_sender') != $GLOBALS['gCurrentUserId']) {
            return true;
        }

        return false;
    }

    /**
     * Reads all recipients to the message and returns an array. The array has the following structure:
     * array('type' => 'role', 'id' => '4711', 'name' => 'Administrator', 'mode' => '0')
     * Type could be **role** or **user**, the id will be the database id of role or user and the
     * mode will be only used with roles and the following values are used:
     + 0 = active members, 1 = former members, 2 = active and former members
     * @return array Returns an array with all recipients (users and roles)
     */
    public function readRecipientsData()
    {
        global $gProfileFields;

        if (count($this->msgRecipientsArray) === 0) {
            $sql = 'SELECT msg_usr_id_sender, msr_id, msr_rol_id, msr_usr_id, msr_role_mode, rol_name, first_name.usd_value AS firstname, last_name.usd_value AS lastname
                      FROM ' . TBL_MESSAGES . '
                     INNER JOIN ' . TBL_MESSAGES_RECIPIENTS . ' ON msr_msg_id = msg_id
                      LEFT JOIN ' . TBL_ROLES . ' ON rol_id = msr_rol_id
                      LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                             ON last_name.usd_usr_id = msr_usr_id
                            AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                      LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                             ON first_name.usd_usr_id = msr_usr_id
                            AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                    WHERE msg_id = ? -- $this->getValue(\'msg_id\') ';
            $messagesRecipientsStatement = $this->db->queryPrepared(
                $sql,
                array($gProfileFields->getProperty('LAST_NAME', 'usf_id'), $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), $this->getValue('msg_id'))
            );

            while ($row = $messagesRecipientsStatement->fetch()) {
                // save message recipient as TableAcess object to the array
                $messageRecipient = new TableAccess($this->db, TBL_MESSAGES_RECIPIENTS, 'msr');
                $messageRecipient->setArray($row);
                $this->msgRecipientsObjectArray[] = $messageRecipient;

                // now save message recipient into an simple array
                if ($row['msr_usr_id'] > 0) {
                    $recipientUsrId = (int) $row['msr_usr_id'];

                    // add role to recipients
                    $this->msgRecipientsArray[] =
                        array('type'   => 'user',
                              'id'     => $recipientUsrId,
                              'name'   => $row['firstname'] . ' ' . $row['lastname'],
                              'mode'   => 0,
                              'msr_id' => (int) $row['msr_id']
                        );
                } else {
                    // add user to recipients
                    $this->msgRecipientsArray[] =
                        array('type'   => 'role',
                              'id'     => (int) $row['msr_rol_id'],
                              'name'   => $row['rol_name'],
                              'mode'   => (int) $row['msr_role_mode'],
                              'msr_id' => (int) $row['msr_id']
                        );
                }
            }
        }

        return $this->msgRecipientsArray;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the name intern will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if
     *                                table has columns like **usr_id_create** or **usr_id_changed**
     * @throws AdmException
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        if ($this->newRecord) {
            // Insert
            $this->setValue('msg_timestamp', DATETIME_NOW);
        }

        $returnValue = parent::save($updateFingerPrint);

        if ($returnValue) {
            // now save every recipient
            foreach ($this->msgRecipientsObjectArray as $msgRecipientsObject) {
                $msgRecipientsObject->setValue('msr_msg_id', $this->getValue('msg_id'));
                $msgRecipientsObject->save();
            }

            if (is_object($this->msgContentObject)) {
                // now save the message to the database
                $this->msgContentObject->setValue('msc_msg_id', $this->getValue('msg_id'));
                $this->msgContentObject->setValue('msc_usr_id', $GLOBALS['gCurrentUserId']);
                $returnValue = $this->msgContentObject->save();
            }

            $this->saveAttachments();
        }

        return $returnValue;
    }

    /**
     * Saves the files of the stored filenames in the array **$msgAttachments** within the filesystem folder
     * adm_my_files/messages_attachments. Therefore the filename will get the prefix with the id of this
     * message.
     * @throw RuntimeException Folder could not be created
     */
    protected function saveAttachments()
    {
        global $gSettingsManager;

        if ($gSettingsManager->getBool('mail_save_attachments')) {
            try {
                FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/messages_attachments');
            } catch (\RuntimeException $exception) {
                return array(
                    'text' => 'SYS_FOLDER_NOT_CREATED',
                    'path' => ADMIDIO_PATH . FOLDER_DATA . '/messages_attachments'
                );
            }
        }

        foreach ($this->msgAttachments as $attachement) {
            $file_name = $this->getValue('msg_id').'_'.$attachement[1];

            if ($gSettingsManager->getBool('mail_save_attachments')) {
                FileSystemUtils::copyFile($attachement[0], ADMIDIO_PATH . FOLDER_DATA . '/messages_attachments/' . $file_name);
            }

            // save message recipient as TableAccess object to the array
            $messageAttachment = new TableAccess($this->db, TBL_MESSAGES_ATTACHMENTS, 'msa');
            $messageAttachment->setValue('msa_msg_id', $this->getValue('msg_id'));
            $messageAttachment->setValue('msa_file_name', $file_name);
            $messageAttachment->setValue('msa_original_file_name', $attachement[1]);
            $messageAttachment->save();
        }
    }

    /**
     * Set the status of the message to read. Also the global menu will be initalize to update
     * the read badge of messages.
     * @return false|\PDOStatement Returns **answer** of the SQL execution
     */
    public function setReadValue()
    {
        global $gMenu;

        if ($this->getValue('msg_read') > 0) {
            $sql = 'UPDATE '.TBL_MESSAGES.'
                       SET msg_read = 0
                     WHERE msg_id   = ? -- $this->getValue(\'msg_id\') ';

            $gMenu->initialize();
            return $this->db->queryPrepared($sql, array($this->getValue('msg_id')));
        }
    }
}
