<?php
/**
 ***********************************************************************************************
 * API endpoint to delete a message conversation or email
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

$endpointName = 'message/delete';
$allowedMethods = array('DELETE', 'POST');
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (!in_array($requestMethod, $allowedMethods, true)) {
    admidioApiError('DELETE required', 405, array(
    'endpoint' => $endpointName,
    'method' => $requestMethod
    ));
}

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

if (!$gSettingsManager->getBool('pm_module_enabled') && $gSettingsManager->getInt('mail_module_enabled') === 0) {
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

$msgUuid = admFuncVariableIsValid($_GET, 'msg_uuid', 'string', array('defaultValue' => ''));
if ($msgUuid === '') {
    $rawBody = file_get_contents('php://input');
    if ($rawBody !== false && $rawBody !== '') {
        $body = json_decode($rawBody, true);
        if (is_array($body)) {
            $msgUuid = trim((string) ($body['msg_uuid'] ?? ''));
    }
    }
}

if ($msgUuid === '' && isset($_POST['msg_uuid'])) {
    $msgUuid = trim((string) $_POST['msg_uuid']);
}

if ($msgUuid === '') {
    admidioApiError('Message identifier missing', 400, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

try {
    $message = new Message($gDb);
    $message->readDataByUuid($msgUuid);

    if ($message->isNewRecord()) {
        admidioApiError('Message not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'msg_uuid' => $msgUuid
        ));
    }

    if (!residentsMessageIsVisibleToUser($message, $currentUserId)) {
        admidioApiError('Access denied for this message', 403, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'msg_uuid' => $msgUuid
        ));
    }

    if (!residentsMessageCanDelete($message, $currentUserId)) {
        admidioApiError('Insufficient permission to delete this message', 403, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'msg_uuid' => $msgUuid
        ));
    }

    $deleteResult = $message->delete();
    if ($deleteResult !== true) {
        admidioApiError('Unable to delete this message', 500, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'msg_uuid' => $msgUuid
        ));
    }

    echo json_encode(array(
    'status' => 'deleted',
    'msg_uuid' => $msgUuid
    ));
} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'msg_uuid' => $msgUuid,
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
