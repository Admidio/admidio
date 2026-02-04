<?php
/**
    * CCAvenue CORE logic for MOBILE
    * Common CCAvenue transaction functions (shared by web & mobile)
    * No session, no $gMessage, WebView compatible
    */

require_once __DIR__ . '/../common_function.php';
require_once __DIR__ . '/ccavenue_config.php';
require_once __DIR__ . '/ccavenue_crypto.php';

/**
    * Common CCAvenue transaction initiation
    * Used by both web and mobile to start payment
    * 
    * @param array $invoiceIds Array of invoice IDs to pay
    * @param int $userId User ID making the payment
    * @param string $source 'web' or 'mobile' - determines redirect URLs
    * @return array Payment data including encrypted request
    */
function initCcavenueTransaction(array $invoiceIds, int $userId, string $source = 'web'): array
{
    global $gDb, $gCurrentOrgId, $pgConf;

    // Validate config
    if (
        empty(CCAVENUE_MERCHANT_ID) ||
        empty(CCAVENUE_ACCESS_CODE) ||
        empty(CCAVENUE_WORKING_KEY) ||
        empty(CCAVENUE_API_URL)
    ) {
        return ['error' => 'CCAvenue is not configured. Please contact administrator.', 'error_code' => 'RE_PG_NOT_CONFIGURED'];
    }

    if (empty($invoiceIds)) {
        return ['error' => 'No invoices selected', 'error_code' => 'RE_PG_NO_INVOICES'];
    }

    // Mark OLD initiated payments as TIMEOUT first (global check)
    // This must happen BEFORE checking for recent initiated payments
    residentsCheckPaymentTimeouts();

    // Check for recent initiated payments (timeout from config or default 15 mins)
    $timeoutMins = isset($pgConf['timeout']) && (int)$pgConf['timeout'] > 0 ? (int)$pgConf['timeout'] : 15;
    $timeoutTime = date('Y-m-d H:i:s', strtotime('-' . $timeoutMins . ' minutes'));

    // Check if ANY of the selected invoices have a recent pending payment (status = IT only)
    // Payments FA (failure), or TO (timeout) status will NOT block new transactions
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
        return [
            'error' => 'A payment was already initiated for invoice(s) ' . implode(', ', $initiatedInvoices) . '. Please wait ' . $timeoutMins . ' minutes before trying again.',
            'error_code' => 'RE_PG_PAYMENT_INITIATED',
            'invoices' => implode(', ', $initiatedInvoices),
            'timeout' => $timeoutMins
        ];
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
        return [
            'error' => 'Invoice(s) ' . implode(', ', $alreadyPaidInvoices) . ' already have a successful payment. Cannot initiate another payment.',
            'error_code' => 'RE_PG_ALREADY_PAID',
            'invoices' => implode(', ', $alreadyPaidInvoices)
        ];
    }

    // Validate invoices & calculate total
    $totalAmount = 0.0;
    $currency = '';
    $ownerId = 0;

    foreach ($invoiceIds as $invId) {
        $invId = (int)$invId;

        $stmt = $gDb->queryPrepared(
            'SELECT * FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ? AND riv_org_id = ?',
            [$invId, $gCurrentOrgId],
            false
        );
        if ($stmt === false) {
            return ['error' => 'Database error', 'error_code' => 'RE_PG_DATABASE_ERROR'];
        }
        $invoice = $stmt->fetch();

        if (!$invoice) {
            return ['error' => 'Invalid invoice', 'error_code' => 'RE_PG_INVALID_INVOICE'];
        }

        if ((int)$invoice['riv_is_paid'] === 1) {
            return ['error' => 'Invoice already paid', 'error_code' => 'RE_PG_INVOICE_PAID'];
        }

        if ($ownerId === 0) {
            $ownerId = (int)$invoice['riv_usr_id'];
        } elseif ($ownerId !== (int)$invoice['riv_usr_id']) {
            return ['error' => 'Invoices must belong to same user', 'error_code' => 'RE_PG_INVOICES_SAME_USER'];
        }

        if ($ownerId !== $userId) {
            return ['error' => 'Unauthorized invoice access', 'error_code' => 'RE_PG_UNAUTHORIZED'];
        }

        $totals = residentsGetInvoiceTotals($invId);
        $totalAmount += (float)$totals['amount'];
        $currency = $totals['currency'];
    }

    if ($totalAmount <= 0) {
        return ['error' => 'Invalid payment amount', 'error_code' => 'RE_PG_INVALID_AMOUNT'];
    }

    // Determine redirect URLs based on source
    $baseUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payment_gateway/';
    $apiBaseUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/api/payment/';
    
    if ($source === 'mobile') {
        // Mobile uses dedicated response handler with clean HTML output (in api folder)
        $redirectUrl = $apiBaseUrl . 'ccavenue_response.php';
        $cancelUrl = $apiBaseUrl . 'ccavenue_response.php';
    } else {
        // Web uses standard response handler with Admidio theme redirect
        $redirectUrl = !empty($pgConf['redirect_url']) ? $pgConf['redirect_url'] : $baseUrl . 'ccavenue_response.php';
        $cancelUrl = !empty($pgConf['cancel_url']) ? $pgConf['cancel_url'] : $baseUrl . 'ccavenue_response.php';
    }

    // Insert payment record (status = IT for initiated)
    if ($gDb->queryPrepared(
        'INSERT INTO ' . TBL_RE_TRANS . ' (
            rtr_pg_id,
            rtr_status,
            rtr_amount,
            rtr_currency,
            rtr_usr_id,
            rtr_org_id,
            rtr_pg_pay_method,
            rtr_usr_id_create,
            rtr_usr_id_change
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            null,
            'IT',
            number_format($totalAmount, 2, '.', ''),
            $currency,
            $ownerId,
            $gCurrentOrgId,
            'CCAvenue',
            $ownerId,
            $ownerId
        ],
        false
    ) === false) {
        return ['error' => 'Failed to create initiated payment', 'error_code' => 'RE_PG_CREATE_FAILED'];
    }

    $paymentId = (int)$gDb->lastInsertId();

    // Insert payment items
    foreach ($invoiceIds as $invId) {
        $invTotals = residentsGetInvoiceTotals($invId);

        if ($gDb->queryPrepared(
            'INSERT INTO ' . TBL_RE_TRANS_ITEMS . ' (
                rti_pg_payment_id,
                rti_inv_id,
                rti_amount,
                rti_currency,
                rti_usr_id,
                rti_org_id,
                rti_usr_id_create,
                rti_usr_id_change
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $paymentId,
                $invId,
                number_format((float)$invTotals['amount'], 2, '.', ''),
                $currency,
                $ownerId,
                $gCurrentOrgId,
                $ownerId,
                $ownerId
            ],
            false
        ) === false) {
            return ['error' => 'Failed to create initiated payment items', 'error_code' => 'RE_PG_ITEMS_FAILED'];
        }
    }

    // Build merchant data
    $merchantData = [
        'merchant_id'     => CCAVENUE_MERCHANT_ID,
        'order_id'        => (string)$paymentId,
        'amount'          => number_format($totalAmount, 2, '.', ''),
        'currency'        => 'INR',
        'redirect_url'    => $redirectUrl,
        'cancel_url'      => $cancelUrl,
        'language'        => 'EN',
        'merchant_param2' => implode(',', $invoiceIds),
        'merchant_param3' => (string)$userId,
        'merchant_param4' => (string)$paymentId
    ];

    $merchantStr = '';
    foreach ($merchantData as $key => $value) {
        $merchantStr .= $key . '=' . $value . '&';
    }
    $merchantStr = rtrim($merchantStr, '&');

    $encryptedData = encrypt_ccavenue($merchantStr, CCAVENUE_WORKING_KEY);

    // Save request data to rtr_pg_request
    $gDb->queryPrepared(
        'UPDATE ' . TBL_RE_TRANS . ' SET rtr_pg_request = ?, rtr_timestamp_change = NOW() WHERE rtr_id = ?',
        [$merchantStr, $paymentId],
        false
    );

    return [
        'success'      => true,
        'payment_id'   => $paymentId,
        'amount'       => $totalAmount,
        'currency'     => $currency,
        'enc_request'  => $encryptedData,
        'access_code'  => CCAVENUE_ACCESS_CODE,
        'gateway_url'  => CCAVENUE_API_URL,
        'redirect_url' => $redirectUrl,
        'cancel_url'   => $cancelUrl
    ];
}

/**
    * Render auto-submit HTML form for mobile WebView
    * Uses common initCcavenueTransaction function
    */
function renderCcavenueForMobile(array $invoiceIds, int $userId)
{
    // Use common transaction init with mobile source
    $result = initCcavenueTransaction($invoiceIds, $userId, 'mobile');
    
    if (isset($result['error'])) {
        echo '<!DOCTYPE html>';
        echo '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>';
        echo '<body style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;text-align:center;padding:40px;background:#f8f9fa">';
        echo '<div style="background:#fff;padding:30px;border-radius:12px;max-width:320px;margin:auto;box-shadow:0 4px 20px rgba(0,0,0,0.1)">';
        echo '<div style="font-size:48px;margin-bottom:16px">⚠️</div>';
        echo '<h3 style="color:#dc3545;margin:0 0 12px">Payment Error</h3>';
        echo '<p style="color:#666;margin:0">' . htmlspecialchars($result['error']) . '</p>';
        echo '</div></body></html>';
        exit;
    }
    
    // Render auto-submit form
    echo '<!DOCTYPE html>';
    echo '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Redirecting…</title>';
    echo '<style>';
    echo 'body{margin:0;padding:60px 20px;text-align:center;background:linear-gradient(135deg,#0B1120 0%,#1a2942 100%);font-family:-apple-system,BlinkMacSystemFont,sans-serif;min-height:100vh;box-sizing:border-box}';
    echo '.loader{border:4px solid rgba(255,255,255,0.2);border-top:4px solid #349aaa;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin:0 auto 24px}';
    echo '@keyframes spin{to{transform:rotate(360deg)}}';
    echo 'h3{color:#fff;font-weight:600;margin:0 0 8px}';
    echo 'p{color:rgba(255,255,255,0.7);font-size:14px;margin:0}';
    echo '</style></head>';
    echo '<body onload="document.forms[0].submit()">';
    echo '<div class="loader"></div>';
    echo '<h3>Connecting to Payment Gateway</h3>';
    echo '<p>Please wait, do not close this window...</p>';
    echo '<form method="post" action="' . htmlspecialchars($result['gateway_url']) . '">';
    echo '<input type="hidden" name="encRequest" value="' . htmlspecialchars($result['enc_request']) . '">';
    echo '<input type="hidden" name="access_code" value="' . htmlspecialchars($result['access_code']) . '">';
    echo '</form>';
    echo '</body></html>';
    exit;
}
