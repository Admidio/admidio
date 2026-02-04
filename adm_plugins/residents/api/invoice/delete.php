<?php
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

$endpointName = 'invoice/delete';
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

// Only Residents Admin / Payment Admin may delete invoices
if (!(isResidentsAdmin() || isPaymentAdmin())) {
    admidioApiError('You do not have permission to delete invoices', 403, array(
        'endpoint' => $endpointName,
        'user_id' => $currentUserId
    ));
}

$invID = admFuncVariableIsValid($_GET, 'id', 'string', array('defaultValue' => ''));

if ($invID === '') {
    admidioApiError('Invoice identifier missing', 400, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

try {
    $invoice = new TableResidentsInvoice($gDb, $invID);

    if ($invoice->isNewRecord()) {
        admidioApiError('invoice not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'rpa_id' => $invID
        ));
    }

    // Enforce org ownership
    if ((int) $invoice->getValue('riv_org_id') !== (int) $gCurrentOrgId) {
        admidioApiError('invoice not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'inv_id' => $invID
        ));
    }

    $deleteResult = $invoice->deleteWithRelations($currentUserId);
    if ($deleteResult !== true) {
        admidioApiError('Unable to delete this invoice', 500, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'rpa_id' => $invID
        ));
    }

    echo json_encode(array(
    'status' => 'deleted',
    'inv_id' => (int) $invoice->getValue('riv_id')
    ));
} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'inv_id' => $invID,
    'exception' => get_class($exception)
    ));
}

if (!function_exists('admidioApiLog')) {
    function admidioApiLog(string $invoice, array $context = array(), string $level = 'error'): void
    {
        global $gLogger;
        $prefix = '[Residents payments API] ';
        if (isset($gLogger) && method_exists($gLogger, $level)) {
            $gLogger->{$level}($prefix . $invoice, $context);
            return;
    }
        if (isset($gLogger)) {
            $gLogger->error($prefix . $invoice, $context);
            return;
    }
        $encoded = empty($context) ? '' : ' ' . json_encode($context);
        error_log($prefix . $invoice . $encoded);
    }
}

if (!function_exists('admidioApiError')) {
    function admidioApiError(string $invoice, int $statusCode, array $context = array()): void
    {
        $context['status'] = $statusCode;
        admidioApiLog($invoice, $context);
        http_response_code($statusCode);
        echo json_encode(array('error' => $invoice));
        exit();
    }
}
