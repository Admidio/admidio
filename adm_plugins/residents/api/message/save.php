<?php
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
use Admidio\Messages\Entity\Message;
use Admidio\Infrastructure\Email;
use Admidio\Users\Entity\User;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Infrastructure\Utils\FileSystemUtils;

$endpointName = 'message/save';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admidioApiError('POST required', 405, array(
    'endpoint' => $endpointName,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
    ));
}

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

$contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;
$rawPayload = $isMultipart ? ($_POST['payload'] ?? '') : file_get_contents('php://input');
$payload = json_decode((string) $rawPayload, true);
if (!is_array($payload)) {
    admidioApiError('Invalid payload', 400, array('endpoint' => $endpointName));
}

$uploadedAttachments = array();
if ($isMultipart) {
    try {
        $uploadedAttachments = collectUploadedAttachments($_FILES['attachments'] ?? null);
    } catch (InvalidArgumentException $attachmentException) {
        admidioApiError($attachmentException->getMessage(), 400, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ));
    }
}
$msgUuid = trim((string) ($payload['msg_uuid'] ?? ''));
$msgTypeInput = strtoupper(trim((string) ($payload['msg_type'] ?? Message::MESSAGE_TYPE_EMAIL)));
$msgSubjectInput = trim((string) ($payload['msg_subject'] ?? ''));
$msgBody = trim((string) ($payload['msg_body'] ?? ''));
$recipientInput = $payload['recipients'] ?? array();
$forwardSourceUuid = trim((string) ($payload['forward_source_uuid'] ?? ''));

if ($msgBody === '') {
    admidioApiError('Message body is required', 400, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

$message = new Message($gDb);
if ($msgUuid !== '') {
    $message->readDataByUuid($msgUuid);
    if ($message->isNewRecord()) {
        admidioApiError('Conversation not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'msg_uuid' => $msgUuid
        ));
    }
    $msgType = $message->getValue('msg_type');
    $msgSubject = $message->getValue('msg_subject');
    $recipientRows = $message->readRecipientsData();
    if ($forwardSourceUuid !== '') {
        admidioApiError('Cannot forward within an existing conversation', 400, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'msg_uuid' => $msgUuid
        ));
    }
} else {
    $msgType = $msgTypeInput === Message::MESSAGE_TYPE_PM ? Message::MESSAGE_TYPE_PM : Message::MESSAGE_TYPE_EMAIL;
    $msgSubject = $msgSubjectInput;
    if ($msgSubject === '') {
        admidioApiError('Subject is required', 400, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ));
    }
    if (!is_array($recipientInput) || count($recipientInput) === 0) {
        admidioApiError('At least one recipient is required', 400, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ));
    }
    $message->setValue('msg_subject', $msgSubject);
    $message->setValue('msg_type', $msgType);
    $recipientRows = normalizeRecipientInput($recipientInput);
}

if ($forwardSourceUuid !== '') {
    if ($msgType !== Message::MESSAGE_TYPE_EMAIL) {
        admidioApiError('Forwarding is only supported for email messages', 400, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'forward_uuid' => $forwardSourceUuid
        ));
    }

    $sourceMessage = new Message($gDb);
    $sourceMessage->readDataByUuid($forwardSourceUuid);
    if ($sourceMessage->isNewRecord()) {
        admidioApiError('Original message not found for forwarding', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'forward_uuid' => $forwardSourceUuid
        ));
    }

    if (!residentsMessageIsVisibleToUser($sourceMessage, $currentUserId)) {
        admidioApiError('Forward not permitted for this message', 403, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'forward_uuid' => $forwardSourceUuid
        ));
    }

    $forwardAttachments = $sourceMessage->getAttachmentsInformation();
} else {
    $forwardAttachments = array();
}

$message->addContent($msgBody);
// Force an update so replies append content even when no other columns change
$message->setValue('msg_timestamp', date('Y-m-d H:i:s'));

$sendResult = false;

try {
    if ($msgType === Message::MESSAGE_TYPE_PM) {
        $sendResult = processPrivateMessageSave($message, $recipientRows, $currentUserId, $uploadedAttachments, $endpointName);
    } else {
        $sendResult = processEmailMessageSave($message, $recipientRows, $currentUser, $uploadedAttachments, $forwardAttachments, $endpointName, $currentUserId);
    }

    if ($sendResult !== true) {
        $errorMessage = is_string($sendResult) ? $sendResult : 'Unable to send message';
        admidioApiError($errorMessage, 500, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ));
    }

    $message->setValue('msg_usr_id_sender', $currentUserId);
    $message->save();

    echo json_encode(array(
    'message' => array(
            'uuid' => $message->getValue('msg_uuid'),
            'type' => $message->getValue('msg_type'),
            'subject' => $message->getValue('msg_subject'),
            'timestamp' => $message->getValue('msg_timestamp')
    )
    ));
} catch (AdmException $exception) {
    admidioApiError($exception->getText(), 500, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'exception' => get_class($exception)
    ));
} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'exception' => get_class($exception)
    ));
}

function processPrivateMessageSave(Message $message, array $recipients, int $currentUserId, array $uploadedAttachments, string $endpointName)
{
    global $gSettingsManager;

    if (!empty($uploadedAttachments)) {
        admidioApiError('Attachments are only supported for email messages.', 400, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ));
    }

    if (!$gSettingsManager->getBool('pm_module_enabled')) {
        admidioApiError('Private messages are disabled', 403, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ));
    }

    return handlePrivateMessageSend($message, $recipients, $currentUserId);
}

function processEmailMessageSave(Message $message, array $recipients, User $currentUser, array $uploadedAttachments, array $forwardAttachments, string $endpointName, int $currentUserId)
{
    global $gSettingsManager;

    if ($gSettingsManager->getInt('mail_module_enabled') === 0) {
        admidioApiError('Email module is disabled', 403, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ));
    }

    $emailAttachmentSources = prepareEmailAttachments($message, $uploadedAttachments, $forwardAttachments);

    return handleEmailSend($message, $recipients, $currentUser, $emailAttachmentSources);
}

function prepareEmailAttachments(Message $message, array $uploadedAttachments, array $forwardAttachments): array
{
    $attachmentSources = array();

    if (!empty($uploadedAttachments)) {
        foreach ($uploadedAttachments as $attachmentMeta) {
            $message->addAttachment($attachmentMeta['path'], $attachmentMeta['name']);
            $attachmentSources[] = array(
        'path' => $attachmentMeta['path'],
        'name' => $attachmentMeta['name'],
        'type' => $attachmentMeta['type'] ?? 'application/octet-stream'
            );
    }
    }

    if (!empty($forwardAttachments)) {
        foreach ($forwardAttachments as $attachment) {
            $attachmentPath = ADMIDIO_PATH . FOLDER_DATA . '/messages_attachments/' . $attachment['admidio_file_name'];
            if (!is_file($attachmentPath)) {
                continue;
            }
            $message->addAttachment($attachmentPath, $attachment['file_name']);
            $attachmentSources[] = array(
        'path' => $attachmentPath,
        'name' => $attachment['file_name'],
        'type' => function_exists('mime_content_type') ? mime_content_type($attachmentPath) : 'application/octet-stream'
            );
    }
    }

    return $attachmentSources;
}

function collectUploadedAttachments(?array $files): array
{
    if (!is_array($files) || !isset($files['name'])) {
        return array();
    }

    if (!is_array($files['name'])) {
        $files = array(
            'name' => array($files['name']),
            'type' => array($files['type']),
            'tmp_name' => array($files['tmp_name']),
            'error' => array($files['error']),
            'size' => array($files['size'])
        );
    }

    $count = count($files['name']);
    $attachments = array();
    $runningSize = 0;
    $maxSize = (int) Email::getMaxAttachmentSize(Email::SIZE_UNIT_BYTE, 0);

    for ($index = 0; $index < $count; $index++) {
        $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
    }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Attachment upload failed.');
    }

        $tmpName = (string) ($files['tmp_name'][$index] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('Attachment missing or invalid.');
    }

        $originalName = (string) ($files['name'][$index] ?? 'attachment');
        StringUtils::strIsValidFileName($originalName, false);
        if (!FileSystemUtils::allowedFileExtension($originalName)) {
            throw new InvalidArgumentException('Attachment type not allowed.');
    }

        $size = (int) ($files['size'][$index] ?? 0);
        $runningSize += $size;
        if ($maxSize > 0 && $runningSize > $maxSize) {
            throw new InvalidArgumentException('Attachments exceed the configured size limit.');
    }

        $mimeType = $files['type'][$index] ?? 'application/octet-stream';
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
    }

        $attachments[] = array(
            'path' => $tmpName,
            'name' => $originalName,
            'type' => $mimeType,
        );
    }

    return $attachments;
}

function normalizeRecipientInput(array $recipients): array
{
    $normalized = array();
    foreach ($recipients as $recipient) {
        if (!is_array($recipient)) {
            continue;
    }
        $type = strtolower((string) ($recipient['type'] ?? 'user'));
        if ($type === 'role') {
            $normalized[] = array(
        'type' => 'role',
        'role_uuid' => $recipient['role_uuid'] ?? '',
        'mode' => isset($recipient['role_mode']) ? (int) $recipient['role_mode'] : 0
            );
    } else {
            $normalized[] = array(
        'type' => 'user',
        'usr_uuid' => $recipient['usr_uuid'] ?? ''
            );
    }
    }

    return $normalized;
}

function handlePrivateMessageSend(Message $message, array $recipients, int $currentUserId)
{
    global $gDb, $gProfileFields;

    $recipientUser = resolvePmRecipient($recipients, $message, $currentUserId);

    if ($recipientUser === null) {
        return 'Private messages require a user recipient';
    }

    $user = new User($gDb, $gProfileFields);
    if (!empty($recipientUser['usr_uuid'])) {
        $user->readDataByUuid($recipientUser['usr_uuid']);
    } else {
        $user->readDataById((int) ($recipientUser['id'] ?? 0));
    }

    $recipientUserId = (int) $user->getValue('usr_id');
    if ($recipientUserId === 0) {
        return 'Recipient does not exist';
    }

    if ($recipientUserId === $currentUserId) {
        return 'Cannot send a private message to yourself';
    }

    $message->addUser($recipientUserId, trim($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME')));
    $message->setValue('msg_read', 1);

    return true;
}

function resolvePmRecipient(array $recipients, Message $message, int $currentUserId): ?array
{
    $candidates = array();

    foreach ($recipients as $recipient) {
        if (($recipient['type'] ?? '') === 'user') {
            $candidates[] = $recipient;
    }
    }

    $previousSenderId = (int) $message->getValue('msg_usr_id_sender');
    if ($previousSenderId > 0 && $previousSenderId !== $currentUserId) {
        $candidates[] = array('type' => 'user', 'id' => $previousSenderId);
    }

    foreach ($candidates as $candidate) {
        $candidateId = (int) ($candidate['id'] ?? 0);
        if ($candidateId > 0 && $candidateId !== $currentUserId) {
            return $candidate;
    }

        $candidateUuid = trim((string) ($candidate['usr_uuid'] ?? ''));
        if ($candidateUuid !== '') {
            return $candidate;
    }
    }

    return null;
}

function handleEmailSend(Message $message, array $recipients, User $currentUser, array $uploadedAttachments = array())
{
    global $gSettingsManager, $gProfileFields, $gDb;

    $senderEmail = $currentUser->getValue('EMAIL');
    $senderName = trim($currentUser->getValue('FIRST_NAME') . ' ' . $currentUser->getValue('LAST_NAME'));

    if (!StringUtils::strValidCharacters($senderEmail, 'email')) {
        return 'Current user has no valid email address';
    }

    $email = new Email();
    if (!$email->setSender($senderEmail, $senderName)) {
        return 'Unable to set sender';
    }

    $email->setSubject($message->getValue('msg_subject'));
    foreach ($uploadedAttachments as $attachmentMeta) {
        $email->addAttachment(
            $attachmentMeta['path'],
            $attachmentMeta['name'],
            'base64',
            $attachmentMeta['type'] ?? 'application/octet-stream'
        );
    }
    if ($gSettingsManager->getBool('mail_html_registered_users')) {
        $email->isHTML(true);
    }
    $email->setText($message->getContent('database'));

    $recipientCount = 0;
    $deliverableCount = 0;
    $recipientIssues = array();

    foreach ($recipients as $recipient) {
        $type = $recipient['type'] ?? '';
        $countBefore = $email->countRecipients();

        if ($type === 'role') {
            $roleUuid = $recipient['role_uuid'] ?? '';
            $roleId = (int) ($recipient['id'] ?? 0);
            $role = new TableRoles($gDb);
            if ($roleUuid !== '') {
                $role->readDataByUuid($roleUuid);
            } elseif ($roleId > 0) {
                $role->readDataById($roleId);
            }

            if ($role->getValue('rol_id') === null) {
                $recipientIssues[] = 'Role recipient not found';
                continue;
            }

            $memberStatus = mapRoleModeToEmailStatus((int) ($recipient['mode'] ?? 0));
            $message->addRole((int) $role->getValue('rol_id'), (int) ($recipient['mode'] ?? 0), $role->getValue('rol_name'));
            $email->addRecipientsByRole($role->getValue('rol_uuid'), $memberStatus);

            $addedCount = $email->countRecipients() - $countBefore;
            if ($addedCount <= 0) {
                $recipientIssues[] = 'Role "' . $role->getValue('rol_name') . '" has no deliverable email addresses';
                continue;
            }

            $recipientCount++;
            $deliverableCount += $addedCount;
    } elseif ($type === 'user') {
            $user = new User($gDb, $gProfileFields);
            if (!empty($recipient['usr_uuid'])) {
                $user->readDataByUuid($recipient['usr_uuid']);
            } else {
                $user->readDataById((int) ($recipient['id'] ?? 0));
            }

            if ((int) $user->getValue('usr_id') === 0) {
                $recipientIssues[] = 'User recipient not found';
                continue;
            }

            $userEmail = trim((string) $user->getValue('EMAIL'));
            $userDisplayName = trim($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'));
            if (!StringUtils::strValidCharacters($userEmail, 'email')) {
                $recipientIssues[] = 'User "' . ($userDisplayName !== '' ? $userDisplayName : 'User #' . (int) $user->getValue('usr_id')) . '" has no valid email address';
                continue;
            }

            $message->addUser((int) $user->getValue('usr_id'), $userDisplayName);
            $email->addRecipientsByUser($user->getValue('usr_uuid'));

            $addedCount = $email->countRecipients() - $countBefore;
            if ($addedCount <= 0) {
                $recipientIssues[] = 'User "' . ($userDisplayName !== '' ? $userDisplayName : 'User #' . (int) $user->getValue('usr_id')) . '" could not be added as email recipient';
                continue;
            }

            $recipientCount++;
            $deliverableCount += $addedCount;
    }
    }

    if ($recipientCount === 0) {
        $reason = empty($recipientIssues) ? 'No recipients supplied' : implode('; ', $recipientIssues);
        return $reason;
    }

    if ($deliverableCount === 0) {
        $reason = 'No deliverable recipients found';
        if (!empty($recipientIssues)) {
            $reason .= ': ' . implode('; ', $recipientIssues);
    }
        return $reason;
    }

    return $email->sendEmail();
}

function mapRoleModeToEmailStatus(int $mode): int
{
    switch ($mode) {
        case 1:
            return Email::EMAIL_ONLY_FORMER_MEMBERS;
        case 2:
            return Email::EMAIL_ALL_MEMBERS;
        default:
            return Email::EMAIL_ONLY_ACTIVE_MEMBERS;
    }
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
