<?php
/**
 ***********************************************************************************************
 * API endpoint to save payment transaction details
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

$endpointName = 'payment/save';


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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admidioApiError('POST required', 405, array(
    'endpoint' => $endpointName,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
    ));
}

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');
$currentOrgId = isset($gCurrentOrgId) ? (int)$gCurrentOrgId : (isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : 0);

// Permission check: admins can manage payments for others; regular users only for themselves
$canManageAll = isResidentsAdmin() || isPaymentAdmin();

$contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;
$rawPayload = $isMultipart ? ($_POST['payload'] ?? '') : file_get_contents('php://input');
$payload = json_decode((string) $rawPayload, true);
if (!is_array($payload)) {
    admidioApiError('Invalid payload', 400, array('endpoint' => $endpointName));
}
$payID= (int)($payload['rpa_id'] ?? 0);
$ownerId= (int)($payload['rpa_usr_id'] ?? 0);
$payDate = $payload['rpa_date'] ?? date('Y-m-d');
$payType= trim((string) ($payload['rpa_pay_type'] ?? ''));
$payMethod= trim((string) ($payload['rpa_pg_pay_method'] ?? ''));
$payTransId= trim((string) ($payload['rtr_pg_id'] ?? ''));
$payRefNo= trim((string) ($payload['rtr_bank_ref_no'] ?? ''));
$invIds = $payload['rpi_inv_ids'] ?? array();

if (!$canManageAll) {
    if ($ownerId > 0 && $ownerId !== $currentUserId) {
        admidioApiError('You do not have permission to create payments for other users', 403, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ));
    }
    $ownerId = $currentUserId;
}

$invoiceItems = [];
$processedInvoices = [];
$mainCurrency = $gSettingsManager->getString('system_currency');

foreach ($invIds as $invoiceId) {
    $invoiceId = (int)$invoiceId;
    if ($invoiceId <= 0 || isset($processedInvoices[$invoiceId])) {
        continue;
    }

    $processedInvoices[$invoiceId] = true;
    $invoice = new TableResidentsInvoice($gDb, $invoiceId);
    if ($invoice->isNewRecord()) {
        echo json_encode(['error' => 'Invalid invoice: ' . $invoiceId]);
        exit;
    }

    if ($currentOrgId > 0 && (int)$invoice->getValue('riv_org_id') !== $currentOrgId) {
        admidioApiError('Invoice not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'invoice_id' => $invoiceId
        ));
    }

    if ((int)$invoice->getValue('riv_usr_id') !== $ownerId) {
        echo json_encode(['error' => 'Invoice does not belong to owner']);
        exit;
    }

    if ((int)$invoice->getValue('riv_is_paid') === 1) {
        echo json_encode(['error' => 'Invoice already paid']);
        exit;
    }

    $items = $invoice->getItems();
    $amountTotal = 0.0;
    $currency = '';

    foreach ($items as $item) {
        $amountTotal += (float)$item['rii_amount'];
        $itemCurrency = trim((string)$item['rii_currency']);
        if ($currency === '' && $itemCurrency !== '') {
            $currency = $itemCurrency;
    }
    }

    if ($amountTotal == 0.0) {
        continue;
    }

    if ($currency === '') {
        $currency = $mainCurrency;
    } else {
        $mainCurrency = $currency;
    }

    $invoiceItems[] = [
    'amount'     => number_format($amountTotal, 2, '.', ''),
    'currency'   => $currency,
    'invoice_id' => $invoiceId
    ];
}

if ($ownerId <= 0 || count($invoiceItems) === 0 ) {
    admidioApiError($gL10n->get('RE_VALIDATION_OWNER_AND_ITEM'), 400, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}


try {
    $payment = new TableResidentsPayment($gDb, $payID);
    if ($payID > 0 && $payment->isNewRecord()) {
        echo json_encode(['error' => 'Invalid payment ID']);
        exit;
    }

    if ($payID > 0 && $currentOrgId > 0 && (int)$payment->getValue('rpa_org_id') !== $currentOrgId) {
        admidioApiError('Payment not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'payment_id' => $payID
        ));
    }
    if ($currentOrgId > 0) {
        $payment->setValue('rpa_org_id', $currentOrgId);
    }

    // Set fields
    $payment->setValue('rpa_usr_id', $ownerId);
    $payment->setValue('rpa_date', $payDate);
    $payment->setValue('rpa_pg_pay_method', $payMethod);
    $payment->setValue('rpa_pay_type', $payType);
    $payment->setValue('rpa_trans_id', $payTransId);
    $payment->setValue('rpa_bank_ref_no', $payRefNo);

    if ($payment->isNewRecord()) {
        $payment->setValue('rpa_status', 'SU');
        $payment->setValue('rpa_usr_id_create', $currentUserId);
        $payment->setValue('rpa_timestamp_create', date('Y-m-d H:i:s'));
    } else {
        $payment->setValue('rpa_usr_id_change', $currentUserId);
        $payment->setValue('rpa_timestamp_change', date('Y-m-d H:i:s'));
    }

    if (!$payment->save()) {
        throw new Exception("Failed to save payment");
    }

    $paymentId = (int)$payment->getValue('rpa_id');

    // Replace items
    $payment->replaceItems($invoiceItems, $currentUserId);

    // Mark invoices as paid
    foreach ($invoiceItems as $item) {
        $inv = new TableResidentsInvoice($gDb, (int)$item['invoice_id']);
        if ($inv->isNewRecord()) continue;

        if ($currentOrgId > 0 && (int)$inv->getValue('riv_org_id') !== $currentOrgId) {
            continue;
        }

        $inv->setValue('riv_is_paid', 1);
        $inv->setValue('riv_usr_id_change', $currentUserId);
        $inv->setValue('riv_timestamp_change', date('Y-m-d H:i:s'));
        $inv->save();
    }

    echo json_encode([
    'success'    => true,
    'payment_id' => $paymentId
    ]);
    exit;
} catch (AdmException $exception) {
    admidioApiError($exception->getText(), 500, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'exception' => get_class($exception)
    ));
}
