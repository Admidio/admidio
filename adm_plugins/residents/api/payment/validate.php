<?php
/**
 * Mobile Payment Validation API
 * 
 * Validates if payment can be initiated for the given invoices.
 * Returns JSON with success/error status.
 */

require_once __DIR__ . '/../../../../system/common.php';
require_once __DIR__ . '/../../common_function.php';
require_once __DIR__ . '/../../payment_gateway/ccavenue_config.php';

header('Content-Type: application/json');

global $gDb, $gCurrentOrgId, $pgConf;

// validateApiKey() handles API key extraction from headers/GET/POST internally
// It also exits with JSON error if API key is missing or invalid
$gCurrentUser = validateApiKey();

$userId = (int)$gCurrentUser->getValue('usr_id');

// Read invoice IDs
$invoiceIds = $_GET['invoice_ids'] ?? $_POST['invoice_ids'] ?? [];
$invoiceIds = array_map('intval', (array)$invoiceIds);

if (empty($invoiceIds)) {
    echo json_encode(['success' => false, 'error' => 'No invoices selected']);
    exit;
}

// Mark OLD initiated payments as TIMEOUT first
residentsCheckPaymentTimeouts();

// Check for recent initiated payments (timeout from config or default 15 mins)
$timeoutMins = isset($pgConf['timeout']) && (int)$pgConf['timeout'] > 0 ? (int)$pgConf['timeout'] : 15;
$timeoutTime = date('Y-m-d H:i:s', strtotime('-' . $timeoutMins . ' minutes'));

// Check if ANY of the selected invoices have a recent pending payment (status = IT only)
$initiatedInvoices = [];
foreach ($invoiceIds as $invId) {
    $invId = (int)$invId;
    $checkRecentSql = 'SELECT COUNT(*)
        FROM ' . TBL_RE_TRANS_ITEMS . ' i
        JOIN ' . TBL_RE_TRANS . ' p ON p.rtr_id = i.rti_pg_payment_id
        WHERE i.rti_inv_id = ? AND p.rtr_status = ? AND p.rtr_timestamp_create >= ?';
    $checkRecentStmt = $gDb->queryPrepared($checkRecentSql, [$invId, 'IT', $timeoutTime], false);
    if ($checkRecentStmt !== false && $checkRecentStmt->fetchColumn() > 0) {
        $invNumStmt = $gDb->queryPrepared('SELECT riv_number FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ?', [$invId], false);
        $invNum = $invNumStmt !== false ? $invNumStmt->fetchColumn() : $invId;
        $initiatedInvoices[] = $invNum ?: $invId;
    }
}

if (!empty($initiatedInvoices)) {
    echo json_encode([
        'success' => false,
        'error' => 'A payment was already initiated for invoice(s) ' . implode(', ', $initiatedInvoices) . '. Please wait ' . $timeoutMins . ' minutes before trying again.',
        'error_code' => 'PAYMENT_INITIATED'
    ]);
    exit;
}

// Check if ANY of the selected invoices already have a successful payment (status = SU)
$alreadyPaidInvoices = [];
foreach ($invoiceIds as $invId) {
    $invId = (int)$invId;
    $checkPaidSql = 'SELECT COUNT(*)
        FROM ' . TBL_RE_TRANS_ITEMS . ' i
        JOIN ' . TBL_RE_TRANS . ' p ON p.rtr_id = i.rti_pg_payment_id
        WHERE i.rti_inv_id = ? AND p.rtr_status = ?';
    $checkPaidStmt = $gDb->queryPrepared($checkPaidSql, [$invId, 'SU'], false);
    if ($checkPaidStmt !== false && $checkPaidStmt->fetchColumn() > 0) {
        $invNumStmt = $gDb->queryPrepared('SELECT riv_number FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ?', [$invId], false);
        $invNum = $invNumStmt !== false ? $invNumStmt->fetchColumn() : $invId;
        $alreadyPaidInvoices[] = $invNum ?: $invId;
    }
}

if (!empty($alreadyPaidInvoices)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invoice(s) ' . implode(', ', $alreadyPaidInvoices) . ' already have a successful payment. Cannot initiate another payment.',
        'error_code' => 'ALREADY_PAID'
    ]);
    exit;
}

// Validate invoices belong to user and are payable
foreach ($invoiceIds as $invId) {
    $invId = (int)$invId;
    
    $stmt = $gDb->queryPrepared(
        'SELECT riv_usr_id, riv_is_paid FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ? AND riv_org_id = ?',
        [$invId, $gCurrentOrgId],
        false
    );
    
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
    
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        echo json_encode(['success' => false, 'error' => 'Invalid invoice']);
        exit;
    }
    
    if ((int)$invoice['riv_usr_id'] !== $userId) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized invoice access']);
        exit;
    }
    
    if ((int)$invoice['riv_is_paid'] === 1) {
        echo json_encode(['success' => false, 'error' => 'Invoice already marked as paid']);
        exit;
    }
}

// All validations passed
echo json_encode([
    'success' => true,
    'message' => 'Payment can be initiated'
]);
