<?php
/**
 ***********************************************************************************************
 * CCAvenue redirect - Builds a form and redirects the user to the CCAvenue payment gateway
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
if (file_exists(__DIR__ . '/../../../system/login_valid.php')) {
    require_once(__DIR__ . '/../../../system/login_valid.php');
} else {
    require_once(__DIR__ . '/../../../system/login_valid.php');
}
require_once(__DIR__ . '/ccavenue_config.php');
require_once(__DIR__ . '/ccavenue_crypto.php');

global $gDb, $gCurrentUser, $gCurrentOrgId, $gSettingsManager, $gL10n, $gProfileFields;

// SECURITY: Validate CSRF token for POST requests (web payment initiation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_ids'])) {
    try {
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token'] ?? '');
    } catch (AdmException $e) {
        $gMessage->show($gL10n->get('SYS_INVALID_CSRF_TOKEN'));
    }
}

// Validate Configuration
if (empty(CCAVENUE_MERCHANT_ID) || empty(CCAVENUE_ACCESS_CODE) || empty(CCAVENUE_WORKING_KEY) || empty(CCAVENUE_API_URL)) {
    $gMessage->show($gL10n->get('RE_PG_CONFIG_MISSING'));
}

$invoiceIds = array();

// Check for array from POST (confirm_pay.php)
if (isset($_POST['invoice_ids']) && is_array($_POST['invoice_ids'])) {
    $invoiceIds = array_map('intval', $_POST['invoice_ids']);
} 
// Check for single ID from GET (legacy/direct link)
elseif (isset($_GET['invoice_id'])) {
    $invoiceIds[] = admFuncVariableIsValid($_GET, 'invoice_id', 'int');
}

// Filter out invalid IDs
$invoiceIds = array_filter($invoiceIds, function($id) { return $id > 0; });

if (empty($invoiceIds)) {
    $gMessage->show($gL10n->get('RE_NO_DATA'));
}

// Validate all invoices belong to user and are open
$totalAmount = 0.0;
$currency = '';
$ownerId = 0;

foreach ($invoiceIds as $invId) {
    $invoiceStmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ? AND riv_org_id = ?', array($invId, (int)$gCurrentOrgId), false);
    if ($invoiceStmt === false) {
        $gMessage->show($gL10n->get('SYS_DATABASE_ERROR'));
    }
    $invoice = $invoiceStmt->fetch();
    
    if (!$invoice) {
        // Invoice not found or belongs to different organization
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
    
    // Check paid flag
    $isPaid = (int)$invoice['riv_is_paid'] === 1;
    if ($isPaid) {
        $gMessage->show($gL10n->get('RE_INVOICE_ALREADY_PAID') . ' (ID: ' . $invId . ')');
    }
    
    // Check owner (must be same for all)
    if ($ownerId === 0) {
        $ownerId = (int)$invoice['riv_usr_id'];
    } elseif ($ownerId !== (int)$invoice['riv_usr_id']) {
        $gMessage->show($gL10n->get('RE_PG_INVOICES_SAME_USER'));
    }
    
    // Add to total
    $totals = residentsGetInvoiceTotals($invId);
    $totalAmount += (float)$totals['amount'];
    $currency = $totals['currency'];
}

if ($totalAmount <= 0) {
    $gMessage->show($gL10n->get('RE_PAYMENT_FAILED') . ' (Total Amount: ' . $totalAmount . ')');
}

$isAdmin = isResidentsAdmin();
if (!$isAdmin && $ownerId !== (int)$gCurrentUser->getValue('usr_id')) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Use the first invoice ID for legacy checks/references if needed, or just 0
$primaryInvoiceId = $invoiceIds[0];

// Mark OLD initiated payments as TIMEOUT first (global check)
// This must happen BEFORE checking for recent initiated payments
residentsCheckPaymentTimeouts();

// Check for recent initiated payments (timeout from config or default 15 mins)
$timeoutMins = isset($pgConf['timeout']) && (int)$pgConf['timeout'] > 0 ? (int)$pgConf['timeout'] : 15;
$timeoutTime = date('Y-m-d H:i:s', strtotime('-' . $timeoutMins . ' minutes'));

// Check if ANY of the selected invoices have a recent pending payment (status = IT only)
// Payments with SU (success), FA (failure), or TO (timeout) status will NOT block new transactions
$initiatedInvoices = array();
foreach ($invoiceIds as $invId) {
    // Check for RECENTLY INITIATED payments (within last X mins)
    $checkRecentSql = 'SELECT COUNT(*)
            FROM ' . TBL_RE_TRANS_ITEMS . ' i
            JOIN ' . TBL_RE_TRANS . ' p ON p.rtr_id = i.rti_pg_payment_id
            WHERE i.rti_inv_id = ? AND p.rtr_status = ? AND p.rtr_timestamp_create >= ?';
    $checkRecentStmt = $gDb->queryPrepared($checkRecentSql, array($invId, 'IT', $timeoutTime), false);
    if ($checkRecentStmt === false) {
        $gMessage->show($gL10n->get('SYS_DATABASE_ERROR'));
    }
    if ($checkRecentStmt->fetchColumn() > 0) {
        $invNumStmt = $gDb->queryPrepared('SELECT riv_number FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ?', array($invId), false);
        if ($invNumStmt === false) {
        $gMessage->show($gL10n->get('SYS_DATABASE_ERROR'));
    }
        $invNum = $invNumStmt->fetchColumn();
        $initiatedInvoices[] = $invNum;
    }
}

if (!empty($initiatedInvoices)) {
    $gMessage->show(sprintf($gL10n->get('RE_PAYMENT_ALREADY_INITIATED'), $timeoutMins) . ' (Invoices: ' . implode(', ', $initiatedInvoices) . ')');
}

// Check if ANY of the selected invoices already have a successful payment (status = SU)
$alreadyPaidInvoices = array();
foreach ($invoiceIds as $invId) {
    $checkPaidSql = 'SELECT COUNT(*)
            FROM ' . TBL_RE_TRANS_ITEMS . ' i
            JOIN ' . TBL_RE_TRANS . ' p ON p.rtr_id = i.rti_pg_payment_id
            WHERE i.rti_inv_id = ? AND p.rtr_status = ?';
    $checkPaidStmt = $gDb->queryPrepared($checkPaidSql, array($invId, 'SU'), false);
    if ($checkPaidStmt === false) {
        $gMessage->show($gL10n->get('SYS_DATABASE_ERROR'));
    }
    if ($checkPaidStmt->fetchColumn() > 0) {
        $invNumStmt = $gDb->queryPrepared('SELECT riv_number FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ?', array($invId), false);
        if ($invNumStmt === false) {
            $gMessage->show($gL10n->get('SYS_DATABASE_ERROR'));
        }
        $invNum = $invNumStmt->fetchColumn();
        $alreadyPaidInvoices[] = $invNum;
    }
}

if (!empty($alreadyPaidInvoices)) {
    $gMessage->show($gL10n->get('RE_PAYMENT_ALREADY_PAID') . ' (Invoices: ' . implode(', ', $alreadyPaidInvoices) . ')');
}

$isAdmin = isResidentsAdmin();
$ownerId = (int)$invoice['riv_usr_id'];
if (!$isAdmin && $ownerId !== (int)$gCurrentUser->getValue('usr_id')) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Already calculated above
$amount = $totalAmount;

// order_id will be set to the inserted bil_pg_payments.id
$order_id = null;

$defaultResponseUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payment_gateway/ccavenue_response.php';
$redirectUrl = !empty($pgConf['redirect_url']) ? $pgConf['redirect_url'] : $defaultResponseUrl;
$cancelUrl = !empty($pgConf['cancel_url']) ? $pgConf['cancel_url'] : $defaultResponseUrl;

// Persist INIT rows into bil_pg_payments and bil_pg_payment_items BEFORE merchant data is formed
$paymentId = 0;
try {
    // Insert into bil_pg_payments with IT status (pg_request NULL for now)
    $insertSql = 'INSERT INTO ' . TBL_RE_TRANS . ' (
            rtr_pg_id, rtr_status,
            rtr_amount, rtr_currency, rtr_payment_id, rtr_usr_id, rtr_org_id,
            rtr_pg_pay_method, rtr_pg_msg, rtr_pg_trans_date, rtr_pg_request,
            rtr_pg_response, rtr_usr_id_create, rtr_usr_id_change
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    if ($gDb->queryPrepared($insertSql, array(
    null,
    'IT',
    number_format($amount, 2, '.', ''),
    $currency,
    null,
    $ownerId,
    $gCurrentOrgId ?? null,
    'CCAvenue',
    'Invoice Payment',
    null,
    null,
    null,
    $ownerId,
    $ownerId
    ), false) === false) {
        throw new RuntimeException('Failed to create initiated payment record.');
    }

    // Get the inserted payment ID
    $paymentId = (int)$gDb->lastInsertId();

    // Insert into bil_pg_payment_items with IT status (linked to the payment)
    if ($paymentId > 0) {
        $insertItemSql = 'INSERT INTO ' . TBL_RE_TRANS_ITEMS . ' (
        rti_pg_payment_id, rti_inv_id,
        rti_amount, rti_currency, rti_usr_id, rti_org_id, rti_usr_id_create, rti_usr_id_change
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

        foreach ($invoiceIds as $invId) {
            $invTotal = residentsGetInvoiceTotals($invId);
            $invAmount = (float)$invTotal['amount'];
            
            if ($gDb->queryPrepared($insertItemSql, array(
        $paymentId,
        $invId,
        number_format($invAmount, 2, '.', ''),
        $currency,
        $ownerId,
        $gCurrentOrgId ?? null,
        $ownerId,
        $ownerId
            ), false) === false) {
                throw new RuntimeException('Failed to create initiated payment item record.');
            }
    }
    }
} catch (Exception $e) {
    // If this fails, still continue redirect; log the error
    error_log('Payment INIT persist failed: ' . $e->getMessage());
}

// Get user information for the payer (current session user)
$payer = $gCurrentUser;
// Fetch address details from profile fields
$payerId = $payer->getValue('usr_id');
$addr = residentsGetUserAddress($payerId);

$re_name    = $addr['name'];
$re_email   = $addr['email'];
$re_address = $addr['address'];
$re_city    = $addr['city'];
$re_zip     = $addr['zip'];
$re_country = $addr['country'];
$re_tel     = $addr['tel'];
$re_state   = $addr['state'];

// Set defaults if empty
if ($re_address === '') $re_address = 'NA';
if ($re_city === '')    $re_city = 'NA';
if ($re_country === '') $re_country = 'India';
if ($re_tel === '')     $re_tel = '0000000000';
if ($re_state === '')   $re_state = 'TN';
// Set order_id to the inserted payment id
$order_id = (string)$paymentId;

// Create merchant data array with minimum required fields
$merchantData = array(
    'merchant_id' => CCAVENUE_MERCHANT_ID,
    'order_id' => $order_id,
    'amount' => number_format($amount, 2, '.', ''),
    'currency' => 'INR',
    'redirect_url' => $redirectUrl,
    'cancel_url' => $cancelUrl,
    'language' => 'EN',
    're_name' => $re_name !== '' ? $re_name : 'Member',
    're_address' => $re_address,
    're_city' => $re_city,
    're_state' => $re_state,
    're_zip' => $re_zip,
    're_country' => $re_country,
    're_tel' => $re_tel,
    're_email' => $re_email !== '' ? $re_email : 'member@example.com',
    'merchant_param2' => implode(',', $invoiceIds),
    'merchant_param3' => strval($ownerId),
    'merchant_param4' => strval($paymentId),
    'merchant_param5' => session_id()
);

// Build query string exactly as Ruby: key=value&key2=value2&...
$merchantDataStr = '';
foreach ($merchantData as $key => $value) {
    $merchantDataStr .= $key . '=' . $value . '&';
}
$merchantDataStr = rtrim($merchantDataStr, '&'); // Remove trailing &

// Update pg_request now that we have the final merchant data
if ($paymentId > 0) {
    try {
        if ($gDb->queryPrepared(
            'UPDATE ' . TBL_RE_TRANS . ' SET rtr_pg_request = ?, rtr_timestamp_change = CURRENT_TIMESTAMP, rtr_usr_id_change = ? WHERE rtr_id = ?',
            array($merchantDataStr, $ownerId, $paymentId),
            false
        ) === false) {
            throw new RuntimeException('Failed to update payment request payload.');
    }
    } catch (Exception $e) {
        error_log('Failed to update pg_request for paymentId ' . $paymentId . ': ' . $e->getMessage());
    }
}

// Encrypt the data
$encryptedData = encrypt_ccavenue($merchantDataStr, CCAVENUE_WORKING_KEY);

// Prepare form and auto-submit
?><html>
<head>
    <meta charset="utf-8">
    <title>Redirecting to CCAvenue</title>
    <style>
    .loading {
            text-align: center;
            padding: 20px;
            font-family: Arial, sans-serif;
    }
    .spinner {
            width: 40px;
            height: 40px;
            margin: 20px auto;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
    }
    @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
    }
    </style>
</head>
<body>
    <div class="loading">
    <div class="spinner"></div>
    <p>Please wait while we redirect you to the payment gateway...</p>
    </div>
    <form id="ccform" method="post" action="<?php echo CCAVENUE_API_URL; ?>">
    <input type="hidden" name="encRequest" value="<?php echo htmlspecialchars($encryptedData, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="access_code" value="<?php echo htmlspecialchars(CCAVENUE_ACCESS_CODE, ENT_QUOTES, 'UTF-8'); ?>" />
    <noscript>
            <div style="text-align: center; padding: 20px;">
    <p>Please click the button below to proceed to payment.</p>
    <button type="submit" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Proceed to Payment
    </button>
            </div>
    </noscript>
    </form>
    <script>
    // Submit form after a short delay to ensure proper loading
    window.onload = function() {
            setTimeout(function() {
                document.getElementById('ccform').submit();
            }, 500);
    };
    </script>
</body>
</html>
