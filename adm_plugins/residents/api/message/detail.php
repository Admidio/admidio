<?php
/**
 ***********************************************************************************************
 * API endpoint to retrieve message details for a specific conversation or email
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
use Admidio\Messages\Entity\Message;
use Admidio\Users\Entity\User;
use Admidio\Infrastructure\Utils\StringUtils;

$endpointName = 'message/detail';

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

if (!$gSettingsManager->getBool('pm_module_enabled') && $gSettingsManager->getInt('mail_module_enabled') === 0) {
    admidioApiError('Messages module is disabled', 403, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

$getMsgUuid = admFuncVariableIsValid($_GET, 'msg_uuid', 'string', array('requireValue' => true));
$markRead = admFuncVariableIsValid($_GET, 'mark_read', 'bool', array('defaultValue' => true));

if ($getMsgUuid === '') {
    admidioApiError('Message identifier missing', 400, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

try {
    $message = new Message($gDb);
    $message->readDataByUuid($getMsgUuid);

    if ($message->isNewRecord()) {
        admidioApiError('Message not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'msg_uuid' => $getMsgUuid
        ));
    }

    if (!residentsMessageIsVisibleToUser($message, $currentUserId)) {
        admidioApiError('Access denied for this message', 403, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'msg_uuid' => $getMsgUuid
        ));
    }

    if ($markRead && $message->getValue('msg_usr_id_sender') !== $currentUserId) {
        $message->setReadValue();
    }

    $sender = new User($gDb, $gProfileFields, (int) $message->getValue('msg_usr_id_sender'));
    $senderPayload = array(
    'id' => (int) $message->getValue('msg_usr_id_sender'),
    'name' => trim($sender->getValue('FIRST_NAME') . ' ' . $sender->getValue('LAST_NAME')),
    'email' => $sender->getValue('EMAIL'),
    'usr_uuid' => $sender->getValue('usr_uuid')
    );

    $recipientRows = $message->readRecipientsData();
    $recipientPayload = array();
    $recipientEmailCache = array();
    foreach ($recipientRows as $recipient) {
        $recipientType = strtoupper($recipient['type']);
        $recipientId = (int) $recipient['id'];
        $recipientEmail = '';

        if ($recipientType === 'USER' && $recipientId > 0) {
            if (!array_key_exists($recipientId, $recipientEmailCache)) {
                $recipientUser = new User($gDb, $gProfileFields, $recipientId);
                $recipientEmailCache[$recipientId] = (string) $recipientUser->getValue('EMAIL');
            }
            $recipientEmail = $recipientEmailCache[$recipientId];
    }

        $recipientPayload[] = array(
            'type' => $recipientType,
            'id' => $recipientId,
            'display_name' => trim((string) $recipient['name']),
            'mode' => $recipient['mode'],
            'msr_id' => $recipient['msr_id'],
            'email' => $recipientEmail
        );
    }

    $conversation = array();
    $conversationStatement = $message->getConversation((int) $message->getValue('msg_id'));
    $userCache = array();
    while ($entry = $conversationStatement->fetch(PDO::FETCH_ASSOC)) {
        $authorId = (int) $entry['msc_usr_id'];
        if ($authorId > 0 && !array_key_exists($authorId, $userCache)) {
            $userCache[$authorId] = residentsFetchUserNameById($authorId);
    }

        $authorDisplayName = $authorId > 0 ? $userCache[$authorId] : 'System';

        $conversation[] = array(
            'id' => (int) $entry['msc_id'],
            'author_id' => $authorId,
            'author_name' => $authorDisplayName,
            'author_display_name' => $authorDisplayName,
            'is_author_current_user' => ($authorId === $currentUserId),
            'body_plain' => StringUtils::strStripTags((string) $entry['msc_message']),
            'body_html' => $entry['msc_message'],
            'timestamp' => $entry['msc_timestamp']
        );
    }

    $attachments = array();
    foreach ($message->getAttachmentsInformation() as $attachment) {
        $attachments[] = array(
            'id' => (string) $attachment['msa_uuid'],
            'file_name' => $attachment['file_name'],
            'download_url' => SecurityUtils::encodeUrl(
        FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/api/message/attachment.php',
        array('msa_uuid' => (string) $attachment['msa_uuid'])
            )
        );
    }

    $response = array(
    'message' => array(
            'id' => (int) $message->getValue('msg_id'),
            'uuid' => $message->getValue('msg_uuid'),
            'type' => $message->getValue('msg_type'),
            'subject' => $message->getValue('msg_subject'),
            'timestamp' => $message->getValue('msg_timestamp'),
            'read_flag' => (int) $message->getValue('msg_read'),
            'is_sender' => (int) ($message->getValue('msg_usr_id_sender') === $currentUserId),
            'sender' => $senderPayload,
            'recipients' => $recipientPayload,
            'conversation' => $conversation,
            'attachments' => $attachments,
            'reply_targets' => buildReplyTargets($recipientPayload, $currentUserId, $senderPayload),
            'permissions' => array(
        'can_delete' => residentsMessageCanDelete($message, $currentUserId)
            )
    )
    );

    echo json_encode($response);
} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'msg_uuid' => $getMsgUuid,
    'exception' => get_class($exception)
    ));
}

function buildReplyTargets(array $recipients, int $currentUserId, array $sender): array
{
    $targets = array();
    foreach ($recipients as $recipient) {
        if ($recipient['type'] === 'USER' && $recipient['id'] === $currentUserId) {
            continue;
    }
        $targets[] = $recipient;
    }

    if ($sender['id'] !== $currentUserId) {
        $targets[] = array(
            'type' => 'USER',
            'id' => $sender['id'],
            'display_name' => $sender['name'],
            'mode' => null,
            'msr_id' => null
        );
    }

    return $targets;
}

if (!function_exists('admidioApiLog')) {
    function admidioApiLog(string $message, array $context = array(), string $level = 'error'): void
    {
        global $gLogger;
        $prefix = '[Residents Messages API] ';
        if (isset($gLogger) && method_exists($gLogger, $level)) {
            $gLogger->{$level}($prefix . $message, $context);
            return;
    }
        if (isset($gLogger)) {
            $gLogger->error($prefix . $message, $context);
            return;
    }
        $encoded = empty($context) ? '' : ' ' . json_encode($context);
        error_log($prefix . $message . $encoded);
    }
}

if (!function_exists('admidioApiError')) {
    function admidioApiError(string $message, int $statusCode, array $context = array()): void
    {
        $context['status'] = $statusCode;
        admidioApiLog($message, $context);
        http_response_code($statusCode);
        echo json_encode(array('error' => $message));
        exit();
    }
}
