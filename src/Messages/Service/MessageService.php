<?php

namespace Admidio\Messages\Service;

use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Messages\Entity\Message;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Users\Entity\User;
use Ramsey\Uuid\Uuid;

/**
 * Service for sending email and private messages independent of the web form.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class MessageService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Send already validated message data.
     *
     * Recipient values use the same representation as the current messages module:
     * user UUIDs or role values understood by MessageService::splitRoleRecipient().
     *
     * @param array<int,string> $recipients
     * @param array<int,array{path:string,name:string,type?:string}> $attachments
     * @throws Exception
     */
    public function sendData(
        string $messageType,
        string $subject,
        string $body,
        array $recipients = array(),
        string $messageUuid = '',
        string $userUuid = '',
        string $listUuid = '',
        array $attachments = array(),
        string $senderName = '',
        string $senderEmail = '',
        bool $deliveryConfirmation = false,
        bool $carbonCopy = false
    ): Message {
        global $gCurrentOrgId, $gCurrentUser, $gCurrentUserId, $gDebug, $gL10n, $gMessage,
            $gProfileFields, $gSettingsManager, $gValidLogin;

        $messageType = strtoupper($messageType) === Message::MESSAGE_TYPE_PM
            ? Message::MESSAGE_TYPE_PM
            : Message::MESSAGE_TYPE_EMAIL;

        if ($messageType === Message::MESSAGE_TYPE_PM && !$gSettingsManager->getBool('pm_module_enabled')) {
            throw new Exception('SYS_MODULE_DISABLED');
        }

        if ($messageType === Message::MESSAGE_TYPE_EMAIL
            && !($gSettingsManager->getInt('mail_module_enabled') === 1
                || ($gSettingsManager->getInt('mail_module_enabled') === 2 && $gValidLogin))) {
            throw new Exception('SYS_MODULE_DISABLED');
        }

        $message = new Message($this->db);
        if ($messageUuid !== '') {
            $message->readDataByUuid($messageUuid);
            if ($message->isNewRecord()) {
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            }
            $messageType = (string)$message->getValue('msg_type');
        } else {
            $message->setValue('msg_type', $messageType);
            $message->setValue('msg_usr_id_sender', $gCurrentUserId);
            $message->setValue('msg_subject', $subject);
        }

        $message->addContent($body);

        if ($messageType === Message::MESSAGE_TYPE_EMAIL) {
            $email = new Email();

            foreach ($recipients as $recipient) {
                if (str_contains($recipient, ':')) {
                    $group = self::splitRoleRecipient($recipient);

                    $sql = 'SELECT rol_mail_this_role, rol_id, rol_name
                              FROM ' . TBL_ROLES . '
                        INNER JOIN ' . TBL_CATEGORIES . '
                                ON cat_id = rol_cat_id
                               AND (cat_org_id = ? OR cat_org_id IS NULL)
                             WHERE rol_uuid = ?';
                    $statement = $this->db->queryPrepared(
                        $sql,
                        array($gCurrentOrgId, $group['uuid'])
                    );
                    $row = $statement->fetch();

                    if ($row === false
                        || (!$gValidLogin && (int)$row['rol_mail_this_role'] !== 3)
                        || ($gValidLogin && !$gCurrentUser->hasRightSendMailToRole((int)$row['rol_id']))) {
                        throw new Exception('SYS_INVALID_PAGE_VIEW');
                    }

                    $message->addRole(
                        (int)$row['rol_id'],
                        (int)$group['role_mode'],
                        (string)Language::translateIfTranslationStrId($row['rol_name'])
                    );
                    $email->addRecipientsByRole((string)$group['uuid'], (int)$group['status']);
                } else {
                    $user = new User($this->db, $gProfileFields);
                    $user->readDataByUuid($recipient);

                    if (!$user->isNewRecord() && $gCurrentUser->hasRightViewProfile($user)) {
                        $message->addUser(
                            (int)$user->getValue('usr_id'),
                            $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME')
                        );
                        $email->addRecipientsByUser((string)$user->getValue('usr_uuid'));
                    }
                }
            }

            if ($email->countRecipients() === 0) {
                throw new Exception('SYS_NO_VALID_RECIPIENTS');
            }

            if (!($gCurrentUserId > 0 && $gSettingsManager->getInt('mail_delivery_confirmation') === 2)
                && $gSettingsManager->getInt('mail_delivery_confirmation') !== 1) {
                $deliveryConfirmation = false;
            }

            if (!$gValidLogin) {
                $email->setSender($senderEmail, $senderName);
            } elseif ($senderEmail !== '' && Uuid::isValid($senderEmail)) {
                $pdoStatement = $this->db->queryPrepared(
                    'SELECT usd_value
                       FROM ' . TBL_USER_FIELDS . '
                 INNER JOIN ' . TBL_USER_DATA . '
                         ON usd_usf_id = usf_id
                      WHERE usf_uuid = ?
                        AND usd_usr_id = ?
                        AND usd_value IS NOT NULL',
                    array($senderEmail, $gCurrentUserId)
                );

                $senderName = $gCurrentUser->getValue('FIRST_NAME') . ' '
                    . $gCurrentUser->getValue('LAST_NAME');
                $senderEmail = (string)$pdoStatement->fetchColumn();
                $email->setSender($senderEmail, $senderName);
            } else {
                $senderName = $gCurrentUser->getValue('FIRST_NAME') . ' '
                    . $gCurrentUser->getValue('LAST_NAME');
                $senderEmail = (string)$gCurrentUser->getValue('EMAIL');
            }

            $email->setSubject($subject);
            $attachmentSize = 0;

            foreach ($attachments as $attachment) {
                $path = (string)($attachment['path'] ?? '');
                $name = (string)($attachment['name'] ?? basename($path));
                $type = (string)($attachment['type'] ?? 'application/octet-stream');

                if ($path === '' || !is_file($path) || !is_readable($path)) {
                    throw new Exception('SYS_FILE_NOT_EXIST');
                }

                StringUtils::strIsValidFileName($name, false);
                if (!FileSystemUtils::allowedFileExtension($name)) {
                    throw new Exception('SYS_FILE_EXTENSION_INVALID');
                }

                $attachmentSize += (int)filesize($path);
                if ($attachmentSize > Email::getMaxAttachmentSize()) {
                    throw new Exception('SYS_ATTACHMENT_TO_LARGE');
                }

                if ($type === '') {
                    $type = 'application/octet-stream';
                }

                $email->addAttachment($path, $name, 'base64', $type);
                $message->addAttachment($path, $name);
            }

            if ($gValidLogin && $gSettingsManager->getBool('mail_html_registered_users')) {
                $email->setHtmlMail();
            }

            if ($deliveryConfirmation) {
                $email->ConfirmReadingTo = (string)$gCurrentUser->getValue('EMAIL');
            }

            if ($listUuid !== '') {
                $showList = new ListConfiguration($this->db);
                $showList->readDataByUuid($listUuid);
                $listName = $showList->getValue('lst_name');
                $receiverName = $gL10n->get('SYS_LIST')
                    . ($listName === '' ? '' : ' - ' . $listName);
            } elseif ($gSettingsManager->getBool('mail_into_to')) {
                $receiverName = $message->getRecipientsNamesString();
            } else {
                $receiverName = $message->getRecipientsNamesString(false);
            }

            $email->setTemplateText(
                $body,
                $senderName,
                $senderEmail,
                (string)$gCurrentUser->getValue('usr_uuid'),
                $receiverName
            );

            /*
             * Write the message, its recipients, its content and its attachments before the first
             * external delivery: afterwards a failing write can no longer be undone. The
             * transaction stays open until the mail was handed over, so a message that was never
             * sent also leaves no history behind.
             */
            $transactionStarted = false;
            if ($gValidLogin) {
                $this->db->startTransaction();
                $transactionStarted = true;
                $message->save();
            }

            try {
                $sendResult = $email->sendEmail();

                if ($sendResult !== true) {
                    throw new Exception('SYS_EMAIL_NOT_SEND', array('SYS_RECIPIENT', $sendResult));
                }

                // A copy to the sender is a convenience, its delivery never invalidates the message.
                if ($carbonCopy && $gValidLogin) {
                    $email->sendCopyEmail();
                }

                if ($transactionStarted) {
                    $this->db->endTransaction();
                    $transactionStarted = false;
                }
            } catch (\Throwable $exception) {
                if ($transactionStarted) {
                    $this->db->rollback();
                }
                throw $exception;
            }

            if ($gDebug && PHP_SAPI !== 'cli' && headers_sent()) {
                $email->isSMTP();
                $gMessage->showHtmlTextOnly();
            }
        } else {
            if ($messageUuid === '' && $userUuid === '' && isset($recipients[0]) && Uuid::isValid($recipients[0])) {
                $userUuid = $recipients[0];
            }

            if (!in_array(
                $gCurrentUserId,
                array((int)$message->getValue('msg_usr_id_sender'), (int)$message->getConversationPartner()),
                true
            )) {
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            }

            if ($messageUuid !== '') {
                $userId = (int)(
                    (int)$message->getValue('msg_usr_id_sender') !== $gCurrentUserId
                        ? $message->getValue('msg_usr_id_sender')
                        : $message->getConversationPartner()
                );
                $user = new User($this->db, $gProfileFields, $userId);
            } elseif ($userUuid !== '') {
                $user = new User($this->db, $gProfileFields);
                $user->readDataByUuid($userUuid);
            } else {
                throw new Exception('SYS_FIELD_EMPTY', array('SYS_TO'));
            }

            if ($message->isNewRecord()) {
                $message->addUser((int)$user->getValue('usr_id'));
            }
            $message->setValue('msg_read', 1);
            $message->setValue('msg_timestamp', DATETIME_NOW);

            if ((!$gCurrentUser->isAdministratorUsers() && !$user->isMemberOfOrganization())
                || (int)$user->getValue('usr_id') === 0) {
                throw new Exception('SYS_USER_ID_NOT_FOUND');
            }

            if ((string)$user->getValue('usr_login_name') === '') {
                throw new Exception('SYS_FIELD_EMPTY', array('SYS_TO'));
            }
        }

        // The email branch has already written its history before the mail was handed over.
        if ($gValidLogin && $messageType === Message::MESSAGE_TYPE_PM) {
            $message->save();
        }

        return $message;
    }

    /**
     * Return the role name with the selected membership status.
     * @param string $roleIdsString A role recipient such as "groupID: UUID+2".
     * @throws Exception
     */
    public function getRoleRecipientName(string $roleIdsString): string
    {
        global $gL10n, $gCurrentOrgId;

        $groupInfo = self::splitRoleRecipient($roleIdsString);

        $sql = 'SELECT rol_name
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                 WHERE rol_uuid = ? -- $groupInfo[\'uuid\']
                   AND (  cat_org_id = ? -- $gCurrentOrgId
                       OR cat_org_id IS NULL)';
        $statement = $this->db->queryPrepared($sql, array($groupInfo['uuid'], $gCurrentOrgId));
        $roleName = $statement->fetchColumn();

        return match ($groupInfo['status']) {
            Email::EMAIL_ONLY_FORMER_MEMBERS => $roleName . ' (' . $gL10n->get('SYS_FORMER_MEMBERS') . ')',
            Email::EMAIL_ALL_MEMBERS => $roleName . ' (' . $gL10n->get('SYS_ACTIVE_FORMER_MEMBERS') . ')',
            default => $roleName,
        };
    }

    /**
     * Split a role recipient into its UUID and membership selection.
     * @param string $groupString A role recipient such as "groupID: 93ce816e-7cfd-45e1-b025-a3644828c47c+2".
     * @return array{uuid:string,status:int,role_mode:string|int}
     */
    public static function splitRoleRecipient(string $groupString): array
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
     * Return the email messages sent by a user.
     * @throws Exception
     */
    public function getUserEmails(int $userId): \PDOStatement
    {
        $sql = 'SELECT msg_id
                  FROM ' . TBL_MESSAGES . '
                 WHERE msg_type = \'EMAIL\'
                   AND msg_usr_id_sender = ? -- $userId
              ORDER BY msg_id DESC';

        return $this->db->queryPrepared($sql, array($userId));
    }

    /**
     * Return private messages for a user.
     * @throws Exception
     */
    public function getUserUnreadMessages(int $userId): \PDOStatement
    {
        $sql = 'SELECT msg_id
                  FROM ' . TBL_MESSAGES . '
                 INNER JOIN ' . TBL_MESSAGES_RECIPIENTS . ' ON msr_msg_id = msg_id
                 WHERE msg_type = \'PM\'
                   AND msr_usr_id = ? -- $userId
                   AND msg_read = 1
              ORDER BY msg_id DESC';

        return $this->db->queryPrepared($sql, array($userId));
    }

    /**
     * Return private messages involving a user.
     * @throws Exception
     */
    public function getUserMessages(int $userId): \PDOStatement
    {
        $sql = 'SELECT msg_id
                  FROM ' . TBL_MESSAGES . '
                 INNER JOIN ' . TBL_MESSAGES_RECIPIENTS . ' ON msr_msg_id = msg_id
                 WHERE msg_type = \'PM\'
                   AND ( (msr_usr_id = ? AND msg_read <> 1) -- $userId
                       OR (msg_usr_id_sender  = ? AND msg_read < 2)) -- $userId
              ORDER BY msg_id DESC';

        return $this->db->queryPrepared($sql, array($userId, $userId));
    }

    /**
     * Return the message ID of the Admidio chat.
     * @throws Exception
     */
    public function getChatId(): int
    {
        $sql = 'SELECT msg_id
                  FROM ' . TBL_MESSAGES . '
                 WHERE msg_type = \'CHAT\'';
        $statement = $this->db->queryPrepared($sql);

        return (int) $statement->fetchColumn();
    }
}
