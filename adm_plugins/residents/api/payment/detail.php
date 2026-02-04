<?php
global $gDb, $gCurrentUser;
require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
$endpointName = 'payment/detail';

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

$payId = admFuncVariableIsValid($_GET, 'id', 'int', ['defaultValue' => 0]);
if ($payId <= 0) {
    admidioApiError('Payment identifier missing', 400, [
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ]);
}

try {
    $paymentData = new TableResidentsPayment($gDb, $payId);

    if ($paymentData->isNewRecord()) {
        admidioApiError('Payment not found', 404, [
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'payment_id' => $payId
        ]);
    }

    // Enforce org ownership (avoid cross-org access by ID)
    if ((int) $paymentData->getValue('rpa_org_id') !== (int) $gCurrentOrgId) {
        admidioApiError('Payment not found', 404, [
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'payment_id' => $payId
        ]);
    }

    // Permission check: admins can view all, regular users can only view their own
    $canViewAll = isResidentsAdmin() || isPaymentAdmin();
    $ownerId = (int)$paymentData->getValue('rpa_usr_id');
    
    if (!$canViewAll && $ownerId !== $currentUserId) {
        admidioApiError('You do not have permission to view this payment', 403, [
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'payment_id' => $payId
        ]);
    }

    $total = 0.0;
    $currency = '';
    $itemRows = $paymentData->getItems(true);
    $pay_items = [];
    
    foreach ($itemRows as $item) {
        $currency = $item['rpi_currency'] ?: $currency;
        $amount = (float)$item['rpi_amount'];
        $total += $amount;

        $pay_items[] = [
            'inv_no' => $item['riv_number'] ?? ('#' . (int)$item['rpi_inv_id']),
            'currency' => $currency,
            'amount' => $amount
        ];
    }

    $payment = [
    'id' => (int)$paymentData->getValue('rpa_id'),
    'user_name' => $ownerId > 0 ? residentsFetchUserNameById($ownerId) : '',
    'rpa_date' => (string)$paymentData->getValue('rpa_date', 'd.m.Y H:i'),
    'rpa_pay_type' => (string)$paymentData->getValue('rpa_pay_type'),
    'rpa_pg_pay_method' => (string)$paymentData->getValue('rpa_pg_pay_method'),
    'rpa_trans_id' => (string)$paymentData->getValue('rpa_trans_id'),
    'rpa_bank_ref_no' => (string)$paymentData->getValue('rpa_bank_ref_no'),
    'pay_items' => $pay_items,
    'pay_total' => $total
    ];

    echo json_encode(['payment' => $payment]);

} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, [
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'payment_id' => $payId,
    'exception' => get_class($exception)
    ]);
}

