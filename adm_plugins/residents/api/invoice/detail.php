<?php
/**
 ***********************************************************************************************
 * API endpoint to return detailed invoice information for a specific invoice
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

global $gDb;
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
$endpointName = 'invoice/detail';

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');


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

$invId = admFuncVariableIsValid($_GET, 'id', 'string');
if ($invId === '') {
    admidioApiError('Invoice identifier missing', 400, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

try{
    $invoiceData = new TableResidentsInvoice($gDb, $invId);

    $invoice = (object)[];
    if ($invoiceData->isNewRecord()) {
        admidioApiError('Invoice not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ));
    }

    // Enforce org ownership (avoid cross-org access by ID)
    if ((int) $invoiceData->getValue('riv_org_id') !== (int) $gCurrentOrgId) {
        admidioApiError('Invoice not found', 404, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId
        ));
    }

    // Permission check: admins can view all, regular users can only view their own
    $canViewAll = isResidentsAdmin() || isPaymentAdmin();
    
    $ownerId = (int)$invoiceData->getValue('riv_usr_id');
    if (!$canViewAll && $ownerId !== $currentUserId) {
        admidioApiError('You do not have permission to view this invoice', 403, array(
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'invoice_id' => (int) $invoiceData->getValue('riv_id')
        ));
    }
    $isPaid = ((int)$invoiceData->getValue('riv_is_paid') === 1);
    $canPay = (!$isPaid && $ownerId === $currentUserId);
    $total = 0.0;
    $currencyFallback = $gSettingsManager->getString('system_currency');
    $currency = '';
    $itemRows = $invoiceData->getItems();
    $inv_items = [];
    foreach ($itemRows as $item) {
        if ($currency === '' && !empty($item['rii_currency'])) {
            $currency = (string)$item['rii_currency'];
            }
        $amount = (float)$item['rii_amount'];
        $total += $amount;

        $inv_items[] = [
            'name' => $item['rii_name'],
            'start_date' => residentsFormatDateForApi((string)($item['rii_start_date'] ?? '')),
            'end_date' => residentsFormatDateForApi((string)($item['rii_end_date'] ?? '')),
            'currency' => $currency,
            'amount' => $amount
        ];
    }
    if ($currency === '') {
        $currency = (string)$currencyFallback;
    }

    $invoice = [
    'id' => (int)$invoiceData->getValue('riv_id'),
    'user_name' => $ownerId > 0 ? residentsFetchUserNameById($ownerId) : '',
    'riv_usr_id' => (int)$invoiceData->getValue('riv_usr_id'),
    'number' => (string)$invoiceData->getValue('riv_number'),
    'rpa_date' => residentsFormatDateForApi((string)$invoiceData->getValue('rpa_date')),
    'riv_is_paid' => (int)$invoiceData->getValue('riv_is_paid'),
    'can_pay' => $canPay,
    'riv_date' => residentsFormatDateForApi((string)$invoiceData->getValue('riv_date')),
    'riv_due_date' => residentsFormatDateForApi((string)$invoiceData->getValue('riv_due_date')),
    'riv_start_date' => residentsFormatDateForApi((string)$invoiceData->getValue('riv_start_date')),
    'riv_end_date' => residentsFormatDateForApi((string)$invoiceData->getValue('riv_end_date')),
    'riv_notes' => (string)$invoiceData->getValue('riv_notes'),
    'currency_symbol' => $currency,
    'inv_items' => $inv_items,
    'inv_total' => $total
    ];

    echo json_encode([ 'invoice' => $invoice ]);
} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'exception' => get_class($exception)
    ));
}
