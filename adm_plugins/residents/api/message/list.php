<?php
/**
 ***********************************************************************************************
 * API endpoint to return a list of messages (PM and Email) for the current user
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
use Admidio\Infrastructure\Utils\StringUtils;

$endpointName = 'message/list';

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

$typeFilterRaw = admFuncVariableIsValid($_GET, 'type', 'string', array('defaultValue' => ''));
$typeFilter = strtoupper(trim($typeFilterRaw));
$allowedTypes = array(Message::MESSAGE_TYPE_PM, Message::MESSAGE_TYPE_EMAIL);
if ($typeFilter !== '' && !in_array($typeFilter, $allowedTypes, true)) {
    admidioApiError('Invalid message type filter', 400, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'type' => $typeFilterRaw
    ));
}

if (!$gSettingsManager->getBool('pm_module_enabled') && !$gSettingsManager->getBool('mail_module_enabled')) {
    admidioApiError('Messages module is disabled', 403, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

if (!isMember($currentUserId)) {
    admidioApiError('User has no active membership in this organization', 403, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}
try {
    $visibilityFilters = array('msg.msg_usr_id_sender = ?');
    $queryParams = array($currentUserId);

    $visibilityFilters[] = '(
    msg.msg_type = ?
    AND EXISTS (
            SELECT 1 FROM ' . TBL_MESSAGES_RECIPIENTS . ' AS msr_user
                WHERE msr_user.msr_msg_id = msg.msg_id
                AND msr_user.msr_usr_id = ?
    )
    )';
    $queryParams[] = Message::MESSAGE_TYPE_PM;
    $queryParams[] = $currentUserId;

    $visibilityFilters[] = '(
    msg.msg_type = ?
    AND EXISTS (
            SELECT 1 FROM ' . TBL_MESSAGES_RECIPIENTS . ' AS msr_mail
                WHERE msr_mail.msr_msg_id = msg.msg_id
                AND msr_mail.msr_usr_id = ?
    )
    )';
    $queryParams[] = Message::MESSAGE_TYPE_EMAIL;
    $queryParams[] = $currentUserId;

    $whereClause = '(' . implode(' OR ', $visibilityFilters) . ')';

    if ($typeFilter !== '') {
        $whereClause .= ' AND msg.msg_type = ?';
        $queryParams[] = $typeFilter;
    }

    $latestContentSubquery = 'SELECT msc_outer.msc_msg_id, msc_outer.msc_message, msc_outer.msc_timestamp
                    FROM ' . TBL_MESSAGES_CONTENT . ' AS msc_outer
        INNER JOIN (
                    SELECT msc_msg_id, MAX(msc_timestamp) AS latest_ts
                                            FROM ' . TBL_MESSAGES_CONTENT . '
                    GROUP BY msc_msg_id
        ) AS latest
                    ON latest.msc_msg_id = msc_outer.msc_msg_id
                        AND latest.latest_ts = msc_outer.msc_timestamp';

    $attachmentCountSubquery = 'SELECT msa_msg_id, COUNT(*) AS attachment_count
                    FROM ' . TBL_MESSAGES_ATTACHMENTS . '
                                    GROUP BY msa_msg_id';

    $sql = 'SELECT msg.msg_id, msg.msg_uuid, msg.msg_type, msg.msg_subject, msg.msg_usr_id_sender,
                            msg.msg_timestamp, msg.msg_read, content.msc_message,
                            COALESCE(content.msc_timestamp, msg.msg_timestamp) AS last_activity,
            COALESCE(att_count.attachment_count, 0) AS attachment_count
                    FROM ' . TBL_MESSAGES . ' AS msg
        LEFT JOIN (' . $latestContentSubquery . ') AS content
            ON content.msc_msg_id = msg.msg_id
        LEFT JOIN (' . $attachmentCountSubquery . ') AS att_count
    ON att_count.msa_msg_id = msg.msg_id
            WHERE ' . $whereClause . '
    ORDER BY COALESCE(content.msc_timestamp, msg.msg_timestamp) DESC';

    $statement = $gDb->queryPrepared($sql, $queryParams, false);
    if ($statement === false) {
        admidioApiError('Database error', 500, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ));
    }

    $messages = array();
    $messageIds = array();
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $messageId = (int) $row['msg_id'];
        $messageIds[] = $messageId;
        $canDelete = ((int) $row['msg_usr_id_sender'] === $currentUserId);
        $messages[] = array(
            'id'        => $messageId,
            'uuid'      => $row['msg_uuid'],
            'type'      => $row['msg_type'],
            'subject'   => $row['msg_subject'],
            'sender'    => array(
        'id'   => (int) $row['msg_usr_id_sender'],
        'name' => residentsFetchUserNameById((int) $row['msg_usr_id_sender'])
            ),
            'is_sender' => (int) $row['msg_usr_id_sender'] === $currentUserId,
            'message'   => StringUtils::strStripTags((string) ($row['msc_message'] ?? '')),
            'date'      => $row['msg_timestamp'],
            'last_activity' => $row['last_activity'],
            'read_flag' => (int) $row['msg_read'],
            'attachments' => array(
        'count' => (int) $row['attachment_count'],
        'has_any' => (int) $row['attachment_count'] > 0
            ),
            'permissions' => array(
        'can_delete' => $canDelete
            ),
            'recipients' => array()
        );
    }

    if (count($messageIds) > 0) {
        global $gProfileFields;

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $recipientSql = 'SELECT msr_msg_id, msr_id, msr_usr_id, msr_rol_id, msr_role_mode,
                rol_name, first_name.usd_value AS firstname, last_name.usd_value AS lastname
                            FROM ' . TBL_MESSAGES_RECIPIENTS . ' AS recipients
                                            LEFT JOIN ' . TBL_ROLES . ' AS roles ON roles.rol_id = recipients.msr_rol_id
                                            LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                                ON last_name.usd_usr_id = recipients.msr_usr_id
                            AND last_name.usd_usf_id = ?
                                            LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                                ON first_name.usd_usr_id = recipients.msr_usr_id
                            AND first_name.usd_usf_id = ?
                                                    WHERE recipients.msr_msg_id IN (' . $placeholders . ')';
        $recipientParams = array(
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id')
        );
        $recipientParams = array_merge($recipientParams, $messageIds);

        $recipientStatement = $gDb->queryPrepared($recipientSql, $recipientParams, false);
        if ($recipientStatement === false) {
            admidioApiError('Database error', 500, array(
        'endpoint' => $endpointName,
        'user_id' => $currentUserId
            ));
    }
        $recipientMap = array();
        while ($recipientRow = $recipientStatement->fetch(PDO::FETCH_ASSOC)) {
            $targetMessageId = (int) $recipientRow['msr_msg_id'];
            if (!array_key_exists($targetMessageId, $recipientMap)) {
                $recipientMap[$targetMessageId] = array();
            }

            if ((int) $recipientRow['msr_usr_id'] > 0) {
                $fullName = trim((string) ($recipientRow['firstname'] ?? '') . ' ' . ($recipientRow['lastname'] ?? ''));
                $recipientMap[$targetMessageId][] = array(
                    'type' => 'USER',
                    'id' => (int) $recipientRow['msr_usr_id'],
                    'display_name' => $fullName,
                    'recipient_name' => $fullName,
                    'mode' => null,
                    'msr_id' => (int) $recipientRow['msr_id']
                );
            } else {
                $recipientMap[$targetMessageId][] = array(
                    'type' => 'ROLE',
                    'id' => (int) $recipientRow['msr_rol_id'],
                    'display_name' => $recipientRow['rol_name'],
                    'recipient_name' => $recipientRow['rol_name'],
                    'mode' => (int) $recipientRow['msr_role_mode'],
                    'msr_id' => (int) $recipientRow['msr_id']
                );
            }
    }

        foreach ($messages as $index => $messagePayload) {
            $messageId = $messagePayload['id'];
            $recipientsForMessage = $recipientMap[$messageId] ?? array();
            $messages[$index]['recipients'] = $recipientsForMessage;

            if (
        !$messages[$index]['permissions']['can_delete']
        && $messages[$index]['type'] === Message::MESSAGE_TYPE_PM
            ) {
                foreach ($recipientsForMessage as $recipient) {
                    if ($recipient['type'] === 'USER' && (int) $recipient['id'] === $currentUserId) {
                        $messages[$index]['permissions']['can_delete'] = true;
                        break;
                    }
        }
            }
    }
    }

    echo json_encode(array('messages' => $messages));
} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'exception' => get_class($exception)
    ));
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
