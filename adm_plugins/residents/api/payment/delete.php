<?php
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

$endpointName = 'payment/delete';
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

if (!isMember($currentUserId)) {
    admidioApiError('User has no active membership in this organization', 403, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

// Only Residents Admin / Payment Admin may delete payments
if (!(isResidentsAdmin() || isPaymentAdmin())) {
    admidioApiError('You do not have permission to delete payments', 403, array(
        'endpoint' => $endpointName,
        'user_id' => $currentUserId
    ));
}

$payID = admFuncVariableIsValid($_GET, 'id', 'string', array('defaultValue' => ''));

if ($payID === '') {
    admidioApiError('Payment identifier missing', 400, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

try {
    $payment = new TableResidentsPayment($gDb, $payID);

    if ($payment->isNewRecord()) {
        admidioApiError('payment not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'rpa_id' => $payID
        ));
    }

    // Enforce org ownership
    if ((int) $payment->getValue('rpa_org_id') !== (int) $gCurrentOrgId) {
        admidioApiError('payment not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'rpa_id' => $payID
        ));
    }

    $deleteResult = $payment->deleteWithRelations($currentUserId);
    if ($deleteResult !== true) {
        admidioApiError('Unable to delete this payment', 500, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'rpa_id' => $payID
        ));
    }

    echo json_encode(array(
    'status' => 'deleted',
    'rpa_id' => (int) $payment->getValue('rpa_id')
    ));
} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'rpa_id' => $payID,
    'exception' => get_class($exception)
    ));
}

if (!function_exists('admidioApiLog')) {
    function admidioApiLog(string $payment, array $context = array(), string $level = 'error'): void
    {
        global $gLogger;
        $prefix = '[Residents payments API] ';
        if (isset($gLogger) && method_exists($gLogger, $level)) {
            $gLogger->{$level}($prefix . $payment, $context);
            return;
    }
        if (isset($gLogger)) {
            $gLogger->error($prefix . $payment, $context);
            return;
    }
        $encoded = empty($context) ? '' : ' ' . json_encode($context);
        error_log($prefix . $payment . $encoded);
    }
}

if (!function_exists('admidioApiError')) {
    function admidioApiError(string $payment, int $statusCode, array $context = array()): void
    {
        $context['status'] = $statusCode;
        admidioApiLog($payment, $context);
        http_response_code($statusCode);
        echo json_encode(array('error' => $payment));
        exit();
    }
}
