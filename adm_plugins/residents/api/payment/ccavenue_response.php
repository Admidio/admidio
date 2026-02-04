<?php
/**
    * CCAvenue Mobile Response Handler
    * 
    * Clean HTML response page for mobile WebView (no Admidio theme/session).
    * Displays success/fail message with mobile-friendly styling.
    */

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.auto_start', '0');
}

require_once __DIR__ . '/../../common_function.php';
require_once __DIR__ . '/../../payment_gateway/ccavenue_config.php';
require_once __DIR__ . '/../../payment_gateway/ccavenue_crypto.php';

ob_clean();

global $gDb;

/**
    * Render mobile-friendly result page
    */
function renderMobileResultPage(bool $success, array $data): void
{
    $statusColor = $success ? '#28a745' : '#dc3545';
    $statusIcon = $success ? '✓' : '✕';
    $statusTitle = $success ? 'Payment Successful' : 'Payment Failed';
    $statusTitleKey = $success ? 'RE_PG_PAYMENT_SUCCESSFUL' : 'RE_PG_PAYMENT_FAILED';
    $statusMessage = $data['message'] ?? ($success ? 'Your payment has been processed successfully.' : 'Your payment could not be completed.');
    $statusMessageKey = $data['message_code'] ?? ($success ? 'RE_PG_SUCCESS_MSG' : 'RE_PG_FAILED_MSG');
    
    $orderId = htmlspecialchars($data['order_id'] ?? '-');
    $trackingId = htmlspecialchars($data['tracking_id'] ?? '-');
    $amount = htmlspecialchars($data['amount'] ?? '-');
    $currency = htmlspecialchars($data['currency'] ?? '₹');
    
    // JavaScript boolean string for script embedding
    $successJs = $success ? 'true' : 'false';
    
    // Build JSON data for mobile app to read (includes i18n keys)
    $resultData = json_encode([
        'success' => $success,
        'order_id' => $data['order_id'] ?? null,
        'tracking_id' => $data['tracking_id'] ?? null,
        'amount' => $data['amount'] ?? null,
        'message' => $statusMessage,
        'message_code' => $statusMessageKey,
        'title_code' => $statusTitleKey,
    ]);
    
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{$statusTitle}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #0B1120 0%, #1a2942 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #fff;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            padding: 32px 24px;
            width: 100%;
            max-width: 360px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: {$statusColor};
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: #fff;
            animation: scaleIn 0.4s ease-out;
        }
        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .title {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        .message {
            font-size: 14px;
            color: #666;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-size: 13px;
            color: #888;
        }
        .detail-value {
            font-size: 13px;
            color: #333;
            font-weight: 600;
        }
        .amount-row .detail-value {
            font-size: 18px;
            color: {$statusColor};
            font-weight: 700;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:active {
            transform: scale(0.98);
        }
        .btn-primary {
            background: #349aaa;
            color: #fff;
        }
        .btn-primary:hover {
            box-shadow: 0 4px 12px rgba(52, 154, 170, 0.4);
        }
        
        /* Hidden data element for mobile app to read */
        #payment-result-data {
            display: none;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-circle">{$statusIcon}</div>
        <h1 class="title">{$statusTitle}</h1>
        <p class="message">{$statusMessage}</p>
        
        <div class="details">
            <div class="detail-row amount-row">
                <span class="detail-label">Amount</span>
                <span class="detail-value">{$currency}{$amount}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Order ID</span>
                <span class="detail-value">{$orderId}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Transaction ID</span>
                <span class="detail-value">{$trackingId}</span>
            </div>
        </div>
        
        <button class="btn btn-primary" onclick="closePayment()">Done</button>
    </div>
    
    <!-- Hidden element containing result data for React Native to extract -->
    <div id="payment-result-data" data-result='{$resultData}'></div>
    
    <script>
        // For React Native WebView to detect completion
        function closePayment() {
            // Communicate with React Native WebView
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: 'PAYMENT_COMPLETE',
                    success: {$successJs},
                    data: {$resultData}
                }));
            }
        }
        
        // Auto-post result to React Native on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: 'PAYMENT_RESULT_LOADED',
                    success: {$successJs},
                    data: {$resultData}
                }));
            }
        });
    </script>
</body>
</html>
HTML;
    exit;
}

// ============================================================
// PROCESS CCAVENUE RESPONSE
// ============================================================

if (!isset($_POST['encResp'])) {
    renderMobileResultPage(false, [
        'message' => 'Invalid response from payment gateway.',
        'message_code' => 'RE_PG_INVALID_RESPONSE',
        'order_id' => '-',
        'tracking_id' => '-',
        'amount' => '-'
    ]);
}

// Decrypt response
$encResponse = $_POST['encResp'];
$decResponse = decrypt_ccavenue($encResponse, CCAVENUE_WORKING_KEY);
parse_str($decResponse, $received);

// Extract fields
$orderId    = $received['order_id'] ?? ($received['merchant_param4'] ?? '');
$amount     = $received['amount'] ?? ($received['order_amount'] ?? '');
$currency   = $received['currency'] ?? ($received['order_currency'] ?? '');
$status     = $received['order_status'] ?? ($received['status'] ?? '');
$trackingId = $received['tracking_id'] ?? '';
$bankRefNo  = $received['bank_ref_no'] ?? '';
$merchantParam = $received['merchant_param3'] ?? '';
$statusMessage = $received['status_message'] ?? '';

if (strtoupper($currency) === 'INR') {
    $currency = '₹';
}

// Check if payment exists
$paymentId = (int)$orderId;
$pgPaymentData = null;

try {
    $stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_TRANS . ' WHERE rtr_id = ?', [$paymentId], false);
    if ($stmt !== false) {
        $pgPaymentData = $stmt->fetch();
    }
} catch (Exception $e) {
    error_log('Mobile response: Failed to retrieve payment: ' . $e->getMessage());
}

if ($pgPaymentData === null) {
    renderMobileResultPage(false, [
        'message' => 'Database error.',
        'message_code' => 'RE_PG_DATABASE_ERROR',
        'order_id' => $orderId,
        'tracking_id' => $trackingId,
        'amount' => $amount,
        'currency' => $currency
    ]);
}

if (!$pgPaymentData) {
    renderMobileResultPage(false, [
        'message' => 'Payment record not found.',
        'message_code' => 'RE_PG_RECORD_NOT_FOUND',
        'order_id' => $orderId,
        'tracking_id' => $trackingId,
        'amount' => $amount,
        'currency' => $currency
    ]);
}

$orgId = isset($pgPaymentData['rtr_org_id']) ? (int)$pgPaymentData['rtr_org_id'] : null;

// Format transaction date
$transDate = $received['trans_date'] ?? '';
$formattedTransDate = null;
if ($transDate !== '') {
    $dt = DateTime::createFromFormat('d/m/Y H:i:s', $transDate);
    $formattedTransDate = $dt !== false ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
} else {
    $formattedTransDate = date('Y-m-d H:i:s');
}

// Update payment record
try {
    $updateSql = 'UPDATE ' . TBL_RE_TRANS . ' SET
        rtr_pg_id = ?,
        rtr_bank_ref_no = ?,
        rtr_status = ?,
        rtr_amount = ?,
        rtr_currency = ?,
        rtr_pg_pay_method = ?,
        rtr_pg_msg = ?,
        rtr_pg_response = ?,
        rtr_usr_id_change = ?,
        rtr_pg_trans_date = ?,
        rtr_timestamp_change = NOW()
    WHERE rtr_id = ?';

    $updated = $gDb->queryPrepared($updateSql, [
        !empty($trackingId) ? substr((string)$trackingId, 0, 30) : $pgPaymentData['rtr_pg_id'],
        !empty($bankRefNo) ? substr((string)$bankRefNo, 0, 255) : null,
        residentsGetPaymentStatus($status),
        !empty($amount) ? $amount : null,
        !empty($currency) ? $currency : null,
        $received['payment_mode'] ?? null,
        $statusMessage ?: $trackingId,
        $decResponse,
        is_numeric($merchantParam) ? (int)$merchantParam : null,
        $formattedTransDate,
        $paymentId
    ], false);
    if ($updated === false) {
        error_log('Mobile response: Failed to update payment: DB error');
    }
} catch (Exception $e) {
    error_log('Mobile response: Failed to update payment: ' . $e->getMessage());
}

// Check success
$successIndicators = ['Success', 'SUCCESS', 'success', 'Captured', 'CAPTURED', 'AUTHORISED', 'Authorised'];
$isSuccess = in_array($status, $successIndicators, true);

// Process success: create bil_payments and update invoices
if ($isSuccess) {
    try {
        $itemStmt = $gDb->queryPrepared(
            'SELECT * FROM ' . TBL_RE_TRANS_ITEMS . ' WHERE rti_pg_payment_id = ? AND rti_org_id = ?',
            [$paymentId, $orgId],
            false
        );
        $pgPaymentItems = $itemStmt ? $itemStmt->fetchAll() : array();

        if ($pgPaymentItems) {
            $insertPaymentSql = 'INSERT INTO ' . TBL_RE_PAYMENTS . ' (
                rpa_status, rpa_date, rpa_pg_pay_method, rpa_pay_type, rpa_trans_id, rpa_bank_ref_no, rpa_usr_id, rpa_org_id, rpa_usr_id_create, rpa_usr_id_change
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $paymentStatus = residentsGetPaymentStatus($status);
            $merchantUser = is_numeric($merchantParam) ? (int)$merchantParam : null;
            
            $insertedPayment = $gDb->queryPrepared($insertPaymentSql, [
                $paymentStatus,
                $formattedTransDate,
                $received['payment_mode'] ?? $pgPaymentData['rtr_pg_pay_method'] ?? null,
                'Online',
                $trackingId,
                $bankRefNo,
                $merchantUser,
                $orgId,
                $merchantUser,
                $merchantUser
            ], false);

            if ($insertedPayment === false) {
                error_log('Mobile response: Failed to insert payment: DB error');
                $pgPaymentItems = array();
            }

            $bilPaymentId = $gDb->lastInsertId();

            if ($bilPaymentId > 0) {
                // Link to pg_payment
                $linked = $gDb->queryPrepared(
                    'UPDATE ' . TBL_RE_TRANS . ' SET rtr_payment_id = ?, rtr_usr_id_change = ?, rtr_timestamp_change = NOW() WHERE rtr_id = ?',
                    [$bilPaymentId, $merchantUser, $paymentId],
                    false
                );
                if ($linked === false) {
                    error_log('Mobile response: Failed to link payment: DB error');
                }

                // Insert payment items and mark invoices paid
                $insertItemSql = 'INSERT INTO ' . TBL_RE_PAYMENT_ITEMS . ' (
                    rpi_payment_id, rpi_inv_id, rpi_amount, rpi_currency, rpi_usr_id, rpi_org_id, rpi_usr_id_create, rpi_usr_id_change
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

                foreach ($pgPaymentItems as $item) {
                    $insertedItem = $gDb->queryPrepared($insertItemSql, [
                        $bilPaymentId,
                        $item['rti_inv_id'] ?? null,
                        $item['rti_amount'] ?? null,
                        $item['rti_currency'] ?? null,
                        $merchantUser,
                        $orgId,
                        $merchantUser,
                        $merchantUser
                    ], false);
                    if ($insertedItem === false) {
                        error_log('Mobile response: Failed to insert payment item: DB error');
                        continue;
                    }

                    // Mark invoice as paid
                    if ($paymentStatus === 'SU' && !empty($item['rti_inv_id'])) {
                        $marked = $gDb->queryPrepared(
                            'UPDATE ' . TBL_RE_INVOICES . ' SET riv_is_paid = ? WHERE riv_id = ? AND riv_org_id = ?',
                            [1, (int)$item['rti_inv_id'], $orgId],
                            false
                        );
                        if ($marked === false) {
                            error_log('Mobile response: Failed to mark invoice paid: DB error');
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Mobile response: Failed to process success: ' . $e->getMessage());
    }
}

// Render result page
renderMobileResultPage($isSuccess, [
    'message' => $isSuccess 
        ? 'Your payment has been processed successfully.' 
        : ($statusMessage ?: 'Your payment could not be completed. Please try again.'),
    'message_code' => $isSuccess ? 'RE_PG_SUCCESS_MSG' : 'RE_PG_FAILED_MSG',
    'order_id' => $orderId,
    'tracking_id' => $trackingId,
    'amount' => $amount,
    'currency' => $currency
]);
